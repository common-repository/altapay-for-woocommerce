<?php
/**
 * AltaPay module for WooCommerce
 *
 * Copyright © 2020 AltaPay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
?>

<html>
<head>
    <meta name="viewport" content="width = device-width, initial-scale = 1">
    <link rel="stylesheet" href="https://unpkg.com/tachyons@4.10.0/css/tachyons.min.css"/>
    <style>
        #TB_title {
            height: 40px !important;
            padding: 5px !important;
        }
        #TB_window {
            max-width: 100% !important;
        }
        #TB_ajaxContent {
            max-width: 100% !important;
        }

        @media screen and (max-width: 800px){
            #TB_window {
                left: 0 !important;
                margin-left: 5% !important;
                width: 90% !important;
            }
        }
    </style>
</head>

<body>
@php add_thickbox(); @endphp
<input id="txnID" value="{{$order->get_transaction_id()}}" hidden>
<!-- Capture Modal -->
<div style="display: none" id="captureModal">
    <div class="capture-status" style="margin-bottom:10px;"></div>
    <div id="capture-details">
        <div style="overflow-x:auto;">
            <div class="responsive-table">
                @include('tables.capture', ['order' => $order])
            </div>
        </div>
        @php
            $toBeCaptured = (float)number_format($reserved - $captured, 2, '.', '');
            $toBeRefunded = (float)number_format($captured - $refunded, 2, '.', '');
        @endphp
        @if ( $captured < $reserved )
            <div class="row row-ap">
                <br>
                <div class="col-lg-12">
                    <div>
                        <input class="action-select filled-in" name="allow-orderlines"
                               type="checkbox" id="ap-allow-orderlines" checked="checked"/>
                        <label for="ap-allow-orderlines" class="form-check-label"> <?php esc_html_e( 'Send order lines', 'altapay' ); ?></label>
                    </div>
                </div>
                <br>
                <div>
                    <input type="text" pattern="[0-9]+(\.[0-9]{0,2})?%?" id="capture-amount"
                           name="capture-amount" value="{{max($toBeCaptured, 0)}}"
                           placeholder="<?php esc_html_e( 'Amount', 'altapay' ); ?>"/>
                    <a id="altapay_capture" class="f7 link dim ph4 pv2 mb1 dib white"
                       style="margin-left:20px; color:white; background-color:#006064; cursor:pointer; border-radius: 4px;"><?php esc_html_e( 'Capture', 'altapay' ); ?></a>
                </div>
            </div>
        @endif
    </div>
</div>


<!-- Refund Modal -->
<div style="display: none;" id="refundModal">
    <div class="refund-status" style="margin-bottom:10px;"></div>
    <div style="overflow-x:auto;">
        <div class="responsive-table">
            @include('tables.refund', ['order' => $order])
        </div>
    </div>
    @php
        $toBeCaptured = (float)number_format($reserved - $captured, 2, '.', '');
        $toBeRefunded = (float)number_format($captured - $refunded, 2, '.', '');
    @endphp
    @if ( $refunded < $reserved )
        <div class="row row-ap">
            <br>
            <div class="col-lg-12">
                <div>
                    <input class="action-select filled-in" name="allow-refund-orderlines"
                           type="checkbox" id="ap-allow-refund-orderlines" checked="checked"/>
                    <label for="ap-allow-refund-orderlines" class="form-check-label"> <?php esc_html_e( 'Send order lines', 'altapay' ); ?></label>
                </div>
            </div>
            <br>
            <div>
                <input type="text" pattern="[0-9]+(\.[0-9]{0,2})?%?" id="refund-amount" name="refund-amount"
                       value="{{max($toBeRefunded, 0)}}" placeholder="<?php esc_html_e( 'Amount', 'altapay' ); ?>"/>
                <a id="altapay_refund" class="f7 link dim ph4 pv2 mb1 dib white"
                   style="margin-left:20px; color:white; background-color:#006064; cursor:pointer; border-radius: 4px;"><?php esc_html_e( 'Refund', 'altapay' ); ?></a>
            </div>
        </div>
    @endif
</div>


<div>
    <div class="release-status" style="margin-bottom:10px;"></div>
    <div>
        <strong><?php esc_html_e( 'Transaction ID', 'altapay' ); ?>:</strong>
        <span>{{$transaction_id}}</span>
    </div>
    <br>
    <div>
        <strong><?php esc_html_e( 'Reserved', 'altapay' ); ?>:</strong>
        <span class="payment-reserved">{{number_format($reserved, 2)}}</span> {{$order->get_currency()}}
    </div>
    <br>
    <div>
        <strong><?php esc_html_e( 'Chargeable', 'altapay' ); ?>:</strong>
        <span class="payment-chargeable">{{number_format($charge, 2)}}</span> {{$order->get_currency()}}
    </div>
    <br>
    <div>
        <strong><?php esc_html_e( 'Captured', 'altapay' ); ?>:</strong>
        <span class="payment-captured">{{number_format($captured, 2)}}</span> {{$order->get_currency()}}
    </div>
    <br>
    <div>
        <strong><?php esc_html_e( 'Refunded', 'altapay' ); ?>:</strong>
        <span class="payment-refunded">{{number_format($refunded, 2)}}</span> {{$order->get_currency()}}
    </div>
    @if ( $captured < $reserved )
        <br>
        <a id="openCaptureModal" title="<?php esc_html_e( 'Capture Payment', 'altapay' ); ?>"  href="#TB_inline?&width=800&inlineId=captureModal" class="thickbox f7 link dim ph4 pv2 mb1 dib white"
           style="color:white; background-color:#006064; cursor:pointer; border-radius: 4px; width: 100%;text-align: center;font-weight: bold;margin-bottom: 15px;"><?php esc_html_e( 'Capture', 'altapay' ); ?></a>
    @endif
    @if ( $refunded < $reserved and $captured)
        <br>
        <a id="openRefundModal" title="<?php esc_html_e( 'Refund Payment', 'altapay' ); ?>"  href="#TB_inline?&width=800&inlineId=refundModal" class="thickbox f7 link dim ph4 pv2 mb1 dib white"
           style="color:white; background-color:#006064; cursor:pointer; border-radius: 4px; width: 100%;text-align: center;font-weight: bold;margin-bottom: 15px;"><?php esc_html_e( 'Refund', 'altapay' ); ?></a>
    @endif
    @if ($order->get_transaction_id() && $captured == 0)
        <br>
        <a id="altapay_release_payment" class="f7 link dim ph4 pv2 mb1 dib white"
           style="color:white; background-color:#ed2939; cursor:pointer; border-radius: 4px; width: 100%;text-align: center;font-weight: bold;margin-bottom: 15px;"><?php esc_html_e( 'Release Payment', 'altapay' ); ?></a>
    @endif
</div>
</body>
</html>