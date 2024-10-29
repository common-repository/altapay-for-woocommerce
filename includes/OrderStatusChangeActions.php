<?php

add_action('woocommerce_order_status_changed', 'orderStatusChanged');
/**
 * Trigger when the order status is changed - Cancelled order scenario is handled
 * @param $order
 * @return WP_Error
 * @throws Exception
 */
function orderStatusChanged($order)
{
    global $woocommerce;
    global $order_id, $order;

    $orderID = sanitize_text_field($order);
    $order = new WC_Order($orderID);
    $txnID = $order->get_transaction_id();
    $currentOrderStatus = $order->get_status();
    $captured = 0;
    $reserved = 0;
    $refunded = 0;
    $status = '';

    $api = new AltapayMerchantAPI(esc_attr(get_option('altapay_gateway_url')),
        esc_attr(get_option('altapay_username')), esc_attr(get_option('altapay_password')), null);
    try {
        $api->login();
    } catch (Exception $e) {
        $_SESSION['altapay_login_error'] = $e->getMessage();
        return new WP_Error('error', "Could not login to the Merchant API: " . $e->getMessage());
    }

    $payment = $api->getPayment($txnID);
    $payments = $payment->getPayments();
    foreach ($payments as $pay) {
        $reserved = $pay->getReservedAmount();
        $captured = $pay->getCapturedAmount();
        $refunded = $pay->getRefundedAmount();
        $status = $pay->getCurrentStatus();

    }

    if ($currentOrderStatus == 'cancelled') {
        try {
            if ($status == 'released') {
                return;
            } else if ($captured == 0 && $refunded == 0) {
                $releaseResult = $api->releaseReservation($txnID);
                if ($releaseResult->wasSuccessful()) {
                    update_post_meta($orderID, '_released', true);
                    $order->add_order_note(__('Order released: "The order has been released"', 'altapay'));
                }
            } else if ($captured == $refunded && $refunded == $reserved || $refunded == $reserved) {
                $order->update_status('refunded');
                $releaseResult = $api->releaseReservation($txnID);
                if (!$releaseResult->wasSuccessful()) {
                    $order->add_order_note(__('Release failed: ' . $releaseResult->getMerchantErrorMessage(), 'altapay'));
                    echo json_encode(array('status' => 'error', 'message' => $releaseResult->getMerchantErrorMessage()));
                }
            } else {
                $order->update_status('processing');
                $releaseResult = $api->releaseReservation($txnID);
                if (!$releaseResult->wasSuccessful()) {
                    $order->add_order_note(__('Release failed: Order cannot be released', 'altapay'));
                }
            }
        } catch (Exception $e) {
            return WP_Error('error', "Could not login to the Merchant API: " . $e->getMessage());
        }
    }

    else if ($currentOrderStatus == 'completed') {
        try {
            if ($status == 'captured') {
                return;
            } else if ($captured == 0) {
                $captureResult = $api->captureReservation($txnID);
                if ($captureResult->wasSuccessful()) {
                    update_post_meta($orderID, '_captured', true);
                    $order->add_order_note(__('Order captured: "The order has been fully captured"', 'altapay'));
                }
            }
        } catch (Exception $e) {
            return WP_Error('error', "Could not login to the Merchant API: " . $e->getMessage());
        }
    }
}
