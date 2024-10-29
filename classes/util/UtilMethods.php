<?php
/**
 * AltaPay module for WooCommerce
 *
 * Copyright © 2020 AltaPay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Altapay\Classes\Util;

use WC_Coupon;
use WP_Error;
use Altapay\Request\OrderLine;

class UtilMethods {

	/**
	 * Generates order lines from an order or from an order refund.
	 *
	 * @param WC_Order|WC_Order_Refund $order
	 * @param array                    $products
	 * @param bool                     $wcRefund
	 *
	 * @return array  list of order lines
	 */
	public function createOrderLines( $order, $products = array(), $wcRefund = false, $isSubscription = false ) {
		$orderLines     = array();
		$itemsToCapture = array();
		$cartItems      = $order->get_items();

		// If capture request is triggered
		if ( $products ) {
			foreach ( $cartItems as $key => $value ) {
				if ( in_array( intval( $key ), $products['itemList'], true ) ) {
					$itemsToCapture[ $key ] = $value;
				}
			}
			$cartItems = $itemsToCapture;
		}

		// if cart is empty
		if ( ! $cartItems ) {
			return new WP_Error( 'error', __( 'There are no items in the cart ', 'altapay' ) );
		}

		$i = 0;

		// generate order lines product by product
		foreach ( $cartItems as $key => $item ) {
			$total    = $item->get_total();
			$subtotal = $item->get_subtotal();
			$discount = 0;

			if ( $total == 0 && $isSubscription ) {
				$total = (float) $item->get_product()->get_regular_price();
			}

			if ( $total == 0 ) {
				continue;
			}

			$discountPercentage = 0;
			if ( $subtotal > 0 ) {
				$discount           = $subtotal - $total;
				$discountPercentage = ( $subtotal - $total ) / $subtotal * 100;
			}
			// get product details for each order line
			$productDetails = $this->getProductDetails( $item, $discount, $discountPercentage, ( ( $i == 0 ) ? $isSubscription : 0 ) );

			if ( $wcRefund ) {
				$orderLines[ $key ] = array(
					'qty'          => $products['itemQty'][ $key ],
					'refund_total' => ( $item['line_total'] / $item->get_quantity() ) * $products['itemQty'][ $key ],
					'refund_tax'   => ( $item->get_total_tax() / $item->get_quantity() ) * $products['itemQty'][ $key ],
				);
			} else {
				$orderLines[] = $productDetails['product'];
			}

			$i ++;
		}
		// get the shipping Details
		$shippingDetails = $this->getShippingDetails(
			$order,
			$products,
			$wcRefund
		);

		if ( $shippingDetails ) {
			$shippingDetails = reset( $shippingDetails );
			$orderLines []   = $shippingDetails;
		}

		if ( ! $wcRefund ) {
			// Calculate compensation amount
			$totalCompensationAmount = $this->totalCompensationAmount( $orderLines, $order->get_total() );

			if ( $totalCompensationAmount > 0 || $totalCompensationAmount < 0 ) {
				$orderLines[] = $this->compensationAmountOrderline( 'total', $totalCompensationAmount );
			}
		}

		return $orderLines;
	}

	/**
	 * Returns product Details based on product type and tax configuration settings
	 *
	 * @param object $item
	 * @param float  $discount
	 * @param float  $discountPercentage
	 *
	 * @return array
	 */
	private function getProductDetails( $item, $discount, $discountPercentage, $isSubscription = false ) {
		$product         = $item->get_product();
		$quantity        = $item->get_quantity();
		$regularPrice    = (float) $product->get_regular_price();
		$salePrice       = (float) $product->get_sale_price();
		$subtotal        = $item->get_subtotal();
		$taxRate         = $subtotal > 0 ? $item->get_subtotal_tax() / $subtotal : 0;
		$productId       = $product->get_sku() ? $item->get_id() . '-' . $product->get_sku() : $item->get_id();
		$productData     = array();
		$productDiscount = 0;

		if ( $product->get_type() === 'variable' || $product->get_type() === 'variable-subscription' ) {
			$variationId  = (int) $item->get_variation_id();
			$product_data = wc_get_product( $variationId );
			$regularPrice = (float) $product_data->get_regular_price();
			$salePrice    = (float) $product_data->get_sale_price() ? $product_data->get_sale_price() : 0;
		}

		// calculate discount if catalogue rule is applied on order line i.e. product sale price is set
		if ( $product->is_on_sale() ) {
			$productDiscount = $regularPrice - $salePrice;
			// convert discount amount into percentage
			$discountPercentage += round( ( $productDiscount / $regularPrice ) * 100, 2 );
		}

		if ( wc_prices_include_tax() ) {
			$taxRate      = 1 + $taxRate;
			$productPrice = round( $regularPrice / $taxRate, 2 );
			$taxAmount    = $regularPrice - $productPrice;
		} else {
			$productPrice = $regularPrice;
			$taxAmount    = $productPrice * $taxRate;
		}

		$productPrice = $isSubscription ? ( $productPrice + ( $taxAmount * $quantity ) - ( $discount + $productDiscount ) ) : round( $productPrice, 2 );
		$taxPercent   = $productPrice > 0 ? round( ( $taxAmount / $productPrice ) * 100, 2 ) : 0;
		$taxAmount    = round( $taxAmount * $quantity, 2 );
		$discount     = round( $discountPercentage, 2 );

		// generate line date with all the calculated parameters
		$orderLine             = new OrderLine(
			$item->get_name(),
			$productId,
			$quantity,
			round( $productPrice, 2 )
		);
		$orderLine->productUrl = $product->get_permalink();
		$orderLine->imageUrl   = wp_get_attachment_url( $product->get_image_id() );
		$orderLine->unitCode   = $quantity > 1 ? 'units' : 'unit';

		if ( ! $isSubscription ) {
			$orderLine->discount   = $discount;
			$orderLine->taxAmount  = $taxAmount;
			$orderLine->taxPercent = $taxPercent;
		}

		$goodsType = ( $isSubscription ) ? 'subscription_model' : 'item';
		$orderLine->setGoodsType( $goodsType );

		$productData['product'] = $orderLine;

		return $productData;
	}

	/**
	 * Returns compensation amount orderline to bind within payment request
	 *
	 * @param int   $productId
	 * @param float $compensationAmount
	 *
	 * @return OrderLine
	 */
	public function compensationAmountOrderline( $productId, $compensationAmount ) {
		// Generate compensation amount orderline for payment, capture and refund requests
		$orderLine             = new OrderLine(
			'Compensation',
			'comp-' . $productId,
			1,
			$compensationAmount
		);
		$orderLine->taxAmount  = 0.00;
		$orderLine->taxPercent = 0.00;
		$orderLine->unitCode   = 'unit';
		$orderLine->discount   = 0.00;
		$orderLine->setGoodsType( 'handling' );

		return $orderLine;
	}

	/**
	 * Returns the shipping method orderline for order
	 *
	 * @param WC_Order $order
	 * @param array    $products
	 * @param bool     $wcRefund
	 *
	 * @return array|bool
	 */
	private function getShippingDetails( $order, $products, $wcRefund ) {
		// Get the shipping method
		$orderShippingMethods = $order->get_shipping_methods();
		$shippingID           = 'NaN';
		$shippingDetails      = array();

		$orderShippingKey = 0;
		foreach ( $orderShippingMethods as $orderShippingKey => $orderShippingMethods ) {
			$shippingID = $orderShippingMethods['method_id'];
		}
		// In a refund it's possible to have order_shipping == 0 and order_shipping_tax != 0 at the same time
		if ( $order->get_shipping_total() != 0 || $order->get_shipping_tax() != 0 ) {
			if ( $products ) {
				if ( ! in_array( $shippingID, $products['itemList'] ) ) {
					return false;
				}
			}
			// getting shipping total and tax applied on it
			$totalShipping    = $order->get_shipping_total();
			$totalShippingTax = $order->get_shipping_tax();

			// This will trigger in case a refund action is performed
			if ( $wcRefund ) {
				$shippingDetails[ $orderShippingKey ] = array(
					'qty'          => 1,
					'refund_total' => wc_format_decimal( $totalShipping ),
					'refund_tax'   => wc_format_decimal( $totalShippingTax ),
				);
			} else {
				$orderLine             = new OrderLine(
					$order->get_shipping_method(),
					$shippingID,
					1,
					round( $totalShipping, 2 )
				);
				$orderLine->taxAmount  = round( $totalShippingTax, 2 );
				$orderLine->taxPercent = round( ( $totalShippingTax / $totalShipping ) * 100, 2 );
				$goodsType             = 'shipment';
				$orderLine->setGoodsType( $goodsType );
				$shippingDetails[] = $orderLine;
			}
		}

		return $shippingDetails;
	}

	/**
	 * @param $orderLines
	 * @param $total
	 * @return float
	 */
	public function totalCompensationAmount( $orderLines, $total ) {
		$orderLinesTotal = 0;
		foreach ( $orderLines as $orderLine ) {
			$orderLinePriceWithTax = ( $orderLine->unitPrice * $orderLine->quantity ) + $orderLine->taxAmount;
			$orderLinesTotal      += $orderLinePriceWithTax - ( $orderLinePriceWithTax * ( $orderLine->discount / 100 ) );
		}

		return round( ( $total - $orderLinesTotal ), 3 );
	}
}
