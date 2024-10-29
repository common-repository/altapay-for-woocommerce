<?php
/**
 * Created by PhpStorm.
 * User: emerson
 * Updates: simion
 * Date: 4/11/17
 * Time: 5:45 PM
 */

/**
 * Generates order lines from an order or from an order refund.
 *
 * @param $order WC_Order|WC_Order_Refund
 * @return array list of order lines
 */
function createOrderLines ($order, $products = array()) {

    global $wpdb;

    // Add orderlines to altapay request
    $orderlines = $order->get_items();
    $linedata = array();
	/**
	 * @var $orderline WC_Order_item
	 */
    foreach ($orderlines as $orderline_key => $orderline) {
	    if ($orderline['qty'] == 0) { // WooCommerce may return 0 for some items during a partial refund
		    if ($orderline['line_total'] != 0 || $orderline['line_tax'] != 0) {
			    return new WP_Error('error', __('Quantity cannot be 0 for item ' . $orderline['name'], 'altapay'));
		    }
		    continue; // Ignore this order line
	    }

	    $_product = wc_get_product($orderline['product_id']);
	    $sku = $_product->get_sku();
	    $variation_id = $orderline['variation_id'];

	    if ($variation_id) {
		    $result = $wpdb->get_results("SELECT meta_value FROM $wpdb->postmeta WHERE meta_key = '_sku' AND post_id = '" . $variation_id . "'");
		    if ($result[0]->meta_value) {
			    $sku = $result[0]->meta_value;
		    }
	    }
	    //$line_discount_price = $orderline['line_subtotal'] - $orderline['line_total'];
	    //$line_discount_percent = ($line_discount_price > 0) ? ($line_discount_price / $orderline['line_subtotal']) * 100 : 0;

	    $qty =  $orderline['qty'];
	    $priceExcTaxPerUnit = (float)number_format($orderline['line_total'] / $qty, 2, '.', '');
	    $totalTax = $orderline['line_tax'];
	    // Skip the products that are not defined in the list - if there is such a list
	    if (!empty($products)) {
		    if (in_array($sku, $products['skuList'])) {
			    $totalTax = (float)number_format(($totalTax / $qty) * $products['skuQty'][$sku], 2, '.', '');
			    $qty =  $products['skuQty'][$sku];
		    } else continue;
	    }

	    $linedata[] = array(
		    'description' => $orderline['name'],
		    'itemId' => $sku,
		    'quantity' => $qty,
		    'unitPrice' => (float)number_format($priceExcTaxPerUnit, 2, '.', ''),
		    'taxAmount' => (float)number_format($totalTax, 2, '.', ''),
		    //'discount' => (float) ($line_discount_percent > 0) ? $line_discount_percent : '',
	    );
    }
	// Add shipping prices
	$order_shipping_methods = $order->get_shipping_methods();
	$shipping_id = 'NaN';
	foreach ($order_shipping_methods as $ordershipping_key => $ordershippingmethods) {
		$shipping_id = $ordershippingmethods['method_id'];
	}
	// In a refund it's possible to have order_shipping == 0 and order_shipping_tax != 0 at the same time
	if ($order->get_shipping_total() <> 0 || $order->get_shipping_tax() <> 0) {
		if (!empty($products)) {
			if (!in_array($shipping_id, $products['skuList'])) {
				return $linedata;
			}
		}
		$linedata[] = array(
			'description' => $order->get_shipping_method(),
			'itemId' => $shipping_id,
			'quantity' => 1,
			'unitPrice' => (float)number_format($order->get_shipping_total(), 2, '.', ''),
			'taxAmount' => (float)number_format($order->get_shipping_tax(), 2, '.', ''),
			'goodsType' => 'shipment'
		);
	}

    return $linedata;

}