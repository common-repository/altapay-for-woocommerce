<?php
/**
 * AltaPay module for WooCommerce
 *
 * Copyright © 2020 AltaPay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Altapay\Classes\Core;

use WP_Error;
use Altapay\Classes\Core;
use Altapay\Api\Others\Payments;
use Altapay\Api\Payments\CaptureReservation;
use Altapay\Api\Payments\ReleaseReservation;
use Altapay\Exceptions\ResponseHeaderException;
use Exception;

class AltapayOrderStatus {

	/**
	 * Register required hooks
	 *
	 * @return void
	 */
	public function registerHooks() {
		add_action( 'woocommerce_order_status_changed', array( $this, 'orderStatusChanged' ), 10, 4 );
	}

	/**
	 * Trigger when the order status is changed - Cancelled/Completed order scenario is handled
	 *
	 * @param int      $orderID
	 * @param string   $previousStatus
	 * @param string   $newStatus
	 * @param WC_Order $order
	 *
	 * @return void|WP_Error
	 */
	public function orderStatusChanged( $orderID, $previousStatus, $newStatus, $order ) {
		$txnID    = $order->get_transaction_id();
		$captured = 0;
		$reserved = 0;
		$refunded = 0;
		$status   = '';

		$settings = new Core\AltapaySettings();
		$login    = $settings->altapayApiLogin();

		if ( ! $login || is_wp_error( $login ) ) {
			echo '<p><b>' . __( 'Could not connect to AltaPay!', 'altapay' ) . '</b></p>';
			return;
		}

		$auth = $settings->getAuth();
		try {
			$api = new Payments( $auth );
			$api->setTransaction( $txnID );
			$payments = $api->call();
		} catch ( Exception $e ) {
			echo '<p><b>' . __( 'Could not fetch Payments from AltaPay!', 'altapay' ) . '</b></p>';
			return;
		}

		if ( $payments ) {
			foreach ( $payments as $pay ) {
				$reserved += $pay->ReservedAmount;
				$captured += $pay->CapturedAmount;
				$refunded += $pay->RefundedAmount;
				$status    = $pay->TransactionStatus;
			}
		}

		if ( $newStatus === 'cancelled' ) {
			try {
				if ( $status === 'released' ) {
					return;
				} elseif ( $captured === 0 && $refunded === 0 ) {
					$orderStatus = 'cancelled';
				} elseif ( $captured == $refunded && $refunded == $reserved || $refunded == $reserved ) {
					$orderStatus = 'refunded';
				} else {
					$orderStatus = 'processing';
				}

				$api = new ReleaseReservation( $auth );
				$api->setTransaction( $txnID );
				$response = $api->call();

				if ( $response->Result === 'Success' ) {
					$order->update_status( $orderStatus );
					if ( $orderStatus === 'cancelled' ) {
						$order->update_meta_data( '_released', true );
						$order->add_order_note( __( 'Order released: "The order has been released"', 'altapay' ) );
						$order->save();
					}
				} else {
					$order->add_order_note( __( 'Release failed: ' . $response->MerchantErrorMessage, 'altapay' ) );
					echo wp_json_encode(
						array(
							'status'  => 'error',
							'message' => $response->MerchantErrorMessage,
						)
					);
				}
			} catch ( ResponseHeaderException $e ) {
				error_log( 'Exception: ' . $e->getMessage() );
			}
		} elseif ( $newStatus === 'completed' ) {
			try {
				if ( $status === 'captured' ) {
					return;
				} elseif ( $captured == 0 ) {
					$api = new CaptureReservation( $auth );
					$api->setTransaction( $txnID );

					$response = $api->call();
					if ( isset( $response->Result ) && $response->Result === 'Success' ) {
						$order->update_meta_data( '_captured', true );
						$order->add_order_note( __( 'Order captured: "The order has been fully captured"', 'altapay' ) );
						$order->save();
					}
				}
			} catch ( Exception $e ) {
				return new WP_Error( 'error', 'Could not login to the Merchant API: ' . $e->getMessage() );
			}
		}
	}
}

