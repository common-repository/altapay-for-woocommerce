<?php
/**
 * AltaPay module for WooCommerce
 *
 * Copyright © 2020 AltaPay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
?>

<table class="w-100 center" cellspacing="0">
    <thead>
    <tr>
        <th width="28%" class="fw6 bb b--black-20 tl pb3 pr3 bg-white"><?php esc_html_e( 'Product name', 'altapay' ); ?></th>
        <th width="12%" class="fw6 bb b--black-20 tl pb3 pr3 bg-white"><?php esc_html_e( 'Price with tax', 'altapay' ); ?></th>
        <th width="12%" class="fw6 bb b--black-20 tl pb3 pr3 bg-white"><?php esc_html_e( 'Price without tax', 'altapay' ); ?></th>
        <th width="12%" class="fw6 bb b--black-20 tl pb3 pr3 bg-white"><?php esc_html_e( 'Ordered', 'altapay' ); ?></th>
        <th width="12%" class="fw6 bb b--black-20 tl pb3 pr3 bg-white"><?php esc_html_e( 'Discount Percent', 'altapay' ); ?></th>
        <th width="12%" class="fw6 bb b--black-20 tl pb3 pr3 bg-white"><?php esc_html_e( 'Quantity', 'altapay' ); ?></th>
        <th width="12%" class="fw6 bb b--black-20 tl pb3 pr3 bg-white"><?php esc_html_e( 'Total amount', 'altapay' ); ?></th>
    </tr>
    </thead>
    <tbody class="lh-copy">

    @php
        $productsWithCoupon = array();
    @endphp

    @foreach($order->get_items() as $item)

        @if($item->get_total() == 0)
            @continue;
        @endif
        @php
            $productID = $item->get_id();
		    $product = $item->get_product();
            $qty = $item->get_quantity();
            $captured = $items_captured[$productID] ?? 0;
            $capturableQty = $qty - $captured;
			$subtotal = $item->get_subtotal();
			$total = $item->get_total();

            $discountPercent = round((($subtotal - $total) / $subtotal) * 100, 2);
            $productUnitPriceWithoutTax = round(($total / $qty), 2);
            $productUnitPriceWithTax = round(($total / $qty) + ($item->get_total_tax() / $qty), 2);
            $totalIncTax = round($total + $item->get_total_tax(), 2);
        @endphp

        <tr class="ap-orderlines-capture">
            <td style="display:none">
                <input class="form-control ap-order-product-sku pv3 pr3 bb b--black-20" name="productID" type="text" value="{{$productID}}"/>
            </td>
            <td class="pv3 pr3 bb b--black-20"> {{$item->get_name()}} </td>
            <td class="ap-orderline-unit-price pv3 pr3 bb b--black-20">{{$productUnitPriceWithTax}}</td>
            <td class="pv3 pr3 bb b--black-20">{{$productUnitPriceWithoutTax}}</td>
            <td class="ap-orderline-capture-max-quantity pv3 pr3 bb b--black-20">{{ $qty }}</td>
            <td class="ap-orderline-discount-percent pv3 pr3 bb b--black-20">{{$discountPercent}}</td>
            <td class="pv3 pr3 bb b--black-20">
                <input style="width: 100px;" class="form-control ap-order-capture-modify"
                       name="qty" value="{{$capturableQty}}"
                       type="number"
                       {{ !$capturableQty ? 'disabled' : '' }} />
            </td>
            <td class="ap-orderline-totalprice-capture pv3 pr3 bb b--black-20">
                <span class="totalprice-capture">{{$order->get_currency()}} {{$totalIncTax}}</span>
            </td>
        </tr>
    @endforeach

    @if ($order->get_shipping_total() <> 0 || $order->get_shipping_tax() <> 0)
        @php
            $order_shipping_methods = $order->get_shipping_methods();
            $discountPercentage = 0;
            $totalIncTax = (float)number_format($order->get_shipping_total() + $order->get_shipping_tax(), 2, '.', '');
            $excTax =$order->get_shipping_total();
        @endphp

        @foreach ($order_shipping_methods as $ordershipping_key => $ordershippingmethods)
            @php($shipping_id = $ordershippingmethods['method_id'])
        @endforeach

        <tr class="ap-orderlines-capture">
            @php
                $capturableQty = ( isset( $items_captured[$shipping_id] ) && $items_captured[$shipping_id]  == 1 ) ? 0 : 1;
            @endphp
            <td style="display:none">
                <input class="form-control ap-order-product-sku pv3 pr3 bb b--black-20" name="productID"
                       type="text" value="{{$shipping_id}}"/>
            </td>
            <td class="pv3 pr3 bb b--black-20">{{$order->get_shipping_method()}}</td>
            <td class="ap-orderline-unit-price pv3 pr3 bb b--black-20">{{$totalIncTax}}</td>
            <td class="pv3 pr3 bb b--black-20">{{$excTax}}</td>
            <td class="ap-orderline-capture-max-quantity pv3 pr3 bb b--black-20">1</td>
            <td class="ap-orderline-discount-percent pv3 pr3 bb b--black-20">{{$discountPercentage}}</td>
            <td class="pv3 pr3 bb b--black-20">
                <input class="form-control ap-order-capture-modify" name="qty"
                       value="{{$capturableQty}}" type="number" style="width: 100px;"  {{ $capturableQty === 0 ? 'disabled' : '' }} />
            </td>
            <td class="ap-orderline-totalprice-capture pv3 pr3 bb b--black-20">
                <span class="totalprice-capture">{{$order->get_currency()}} {{$totalIncTax}}</span>
            </td>
        </tr>
    @endif
    </tbody>
</table>