<?php
/**
 * AltaPay module for WooCommerce
 *
 * Copyright © 2020 AltaPay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Altapay\Helpers\Traits;

use Altapay\Api\Payments\RefundCapturedReservation;
use Altapay\Api\Payments\ReleaseReservation;
use Exception;
use WC_Order;
use WP_Error;
use Altapay\Authentication;
use Altapay\Api\Test\TestAuthentication;
use AltaPay\vendor\GuzzleHttp\Exception\ClientException;
use Altapay\Api\Subscription\ChargeSubscription;
use Altapay\Classes\Core;

trait AltapayMaster {

	/**
	 * @param int $order_id
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order = new WC_Order( $order_id );

		// Return goto payment url
		return array(
			'result'   => 'success',
			'redirect' => $order->get_checkout_payment_url( true ),
		);
	}

	/**
	 * Tackle scenario for scheduled subscriptions
	 *
	 * @param float    $amount
	 * @param WC_Order $renewal_order
	 * @return void
	 */
	public function scheduledSubscriptionsPayment( $amount, $renewal_order ) {
		try {
			if ( $amount == 0 ) {
				$renewal_order->payment_complete();
				return;
			}

			if ( wcs_order_contains_renewal( $renewal_order->get_id() ) ) {
				$subscriptions = wcs_get_subscriptions_for_renewal_order( $renewal_order->get_id() );
			}

			foreach ( $subscriptions as $subscription ) {
				$parent_order = $subscription->get_parent();
				$agreement_id = $parent_order->get_meta( '_agreement_id' );

				if ( ! $agreement_id ) {
					// Set subscription payment as failure
					$renewal_order->update_status( 'failed', __( 'AltaPay could not locate agreement ID', 'altapay' ) );
					return;
				}

				$login = $this->altapayApiLogin();
				if ( ! $login || is_wp_error( $login ) ) {
					echo '<p><b>' . __( 'Could not connect to AltaPay!', 'altapay' ) . '</b></p>';
					return;
				}

				// @phpstan-ignore-next-line
				if ( $this->payment_action === 'authorize_capture' ) {
					$reconciliationId = wp_generate_uuid4();

					$api = new ChargeSubscription( $this->getAuth() );
					$api->setTransaction( $agreement_id );
					$api->setAmount( round( $amount, 2 ) );
					$api->setReconciliationIdentifier( wp_generate_uuid4() );

					$response = $api->call();

					$xmlToJson          = wp_json_encode( $response->Transactions );
					$jsonToArray        = json_decode( $xmlToJson, true );
					$latest_transaction = $this->getLatestTransaction( $jsonToArray, 'subscription_payment' );
					$transaction        = $jsonToArray[ $latest_transaction ];
					$transaction_id     = $transaction['TransactionId'];

					$renewal_order->update_meta_data( '_transaction_id', $transaction_id );
					$renewal_order->save();

					if ( $response->Result === 'Success' ) {
						$reconciliation = new Core\AltapayReconciliation();
						$reconciliation->saveReconciliationIdentifier( $renewal_order->get_id(), $transaction_id, $reconciliationId, 'captured' );
						$renewal_order->payment_complete();
					} elseif ( $response->Result === 'Open' ) {
						$renewal_order->update_status( 'on-hold', 'The payment is pending an update from the payment provider.' );
					} else {
						$renewal_order->update_status(
							'failed',
							sprintf( __( 'AltaPay payment declined: %s', 'altapay' ), $response->MerchantErrorMessage )
						);
					}
				} else {
					$renewal_order->payment_complete();
				}

				$renewal_order->update_meta_data( '_agreement_id', $agreement_id );
				$renewal_order->save();
			}
		} catch ( Exception $e ) {
			$renewal_order->update_status(
				'failed',
				sprintf( __( 'AltaPay payment declined: %s', 'altapay' ), $e->getMessage() )
			);
		}
	}

	/**
	 * @return Authentication
	 */
	public function getAuth() {

		$apiUser = esc_attr( get_option( 'altapay_username' ) );
		$apiPass = get_option( 'altapay_password' );
		$url     = esc_attr( get_option( 'altapay_gateway_url' ) );

		return new Authentication( $apiUser, $apiPass, $url );

	}

	/**
	 * Method for AltaPay api login using credentials provided in AltaPay settings page
	 *
	 * @return bool|WP_Error
	 */
	public function altapayApiLogin() {
		try {
			$api      = new TestAuthentication( $this->getAuth() );
			$response = $api->call();
			if ( ! $response ) {
				set_transient( 'altapay_login_error', 'Could not login to the Merchant API', 30 );
				return false;
			}
		} catch ( ClientException $e ) {
			set_transient( 'altapay_login_error', wp_kses_post( $e->getMessage() ), 30 );
			return new WP_Error( 'error', 'Could not login to the Merchant API: ' . $e->getMessage() );
		} catch ( Exception $e ) {
			set_transient( 'altapay_login_error', wp_kses_post( $e->getMessage() ), 30 );
			return new WP_Error( 'error', 'Could not login to the Merchant API: ' . $e->getMessage() );
		}

		return true;
	}

	/**
	 * @param int $number
	 * @return string
	 */
	public function altapayGetCurrencyCode( $number ) {
		 $codes = array(
			 '004' => 'AFA',
			 '012' => 'DZD',
			 '020' => 'ADP',
			 '031' => 'AZM',
			 '032' => 'ARS',
			 '036' => 'AUD',
			 '044' => 'BSD',
			 '048' => 'BHD',
			 '050' => 'BDT',
			 '051' => 'AMD',
			 '052' => 'BBD',
			 '060' => 'BMD',
			 '064' => 'BTN',
			 '068' => 'BOB',
			 '072' => 'BWP',
			 '084' => 'BZD',
			 '096' => 'BND',
			 '100' => 'BGL',
			 '108' => 'BIF',
			 '124' => 'CAD',
			 '132' => 'CVE',
			 '152' => 'CLP',
			 '156' => 'CNY',
			 '170' => 'COP',
			 '188' => 'CRC',
			 '191' => 'HRK',
			 '192' => 'CUP',
			 '196' => 'CYP',
			 '203' => 'CZK',
			 '208' => 'DKK',
			 '214' => 'DOP',
			 '218' => 'ECS',
			 '230' => 'ETB',
			 '232' => 'ERN',
			 '233' => 'EEK',
			 '238' => 'FKP',
			 '242' => 'FJD',
			 '262' => 'DJF',
			 '270' => 'GMD',
			 '288' => 'GHC',
			 '292' => 'GIP',
			 '320' => 'GTQ',
			 '324' => 'GNF',
			 '328' => 'GYD',
			 '340' => 'HNL',
			 '344' => 'HKD',
			 '532' => 'ANG',
			 '533' => 'AWG',
			 '578' => 'NOK',
			 '624' => 'GWP',
			 '752' => 'SEK',
			 '756' => 'CHF',
			 '784' => 'AED',
			 '818' => 'EGP',
			 '826' => 'GBP',
			 '840' => 'USD',
			 '973' => 'AOA',
			 '974' => 'BYR',
			 '975' => 'BGN',
			 '976' => 'CDF',
			 '977' => 'BAM',
			 '978' => 'EUR',
			 '981' => 'GEL',
			 '983' => 'ECV',
			 '984' => 'BOV',
			 '986' => 'BRL',
			 '990' => 'CLF',
		 );
		 return $codes[ $number ];
	}

	/**
	 * @param $transactions
	 * @param $authType
	 * @return int|string
	 */
	public function getLatestTransaction( $transactions, $authType ) {
		$max_date           = '';
		$latest_transaction = '';
		foreach ( $transactions as $key => $transaction ) {
			if (
				is_array( $transaction ) &&
				isset( $transaction['AuthType'] ) &&
				$transaction['AuthType'] === $authType &&
				isset( $transaction['CreatedDate'] ) &&
				$transaction['CreatedDate'] > $max_date
			) {
				$max_date           = $transaction['CreatedDate'];
				$latest_transaction = $key;
			}
		}

		return $latest_transaction;
	}

	/**
	 * @param $order_id
	 * @param $txn_id
	 * @param $transaction
	 * @param $fraud_recommendation
	 *
	 * @return bool
	 */
	public function detectFraud( $order_id, $txn_id, $transaction, $fraud_recommendation ) {
		$return             = false;
		$detect_fraud       = get_option( 'altapay_fraud_detection' );
		$do_action_on_fraud = get_option( 'altapay_fraud_detection_action' );
		$order              = wc_get_order( $order_id );
		if ( $detect_fraud and $do_action_on_fraud and $fraud_recommendation === 'Deny' ) {
			$return = true;
			try {
				$auth = $this->getAuth();
				if ( in_array( $transaction['TransactionStatus'], array( 'captured', 'bank_payment_finalized' ), true ) and ! get_post_meta( $order_id, '_refunded', true ) ) {
					$reconciliation_id = wp_generate_uuid4();
					$api               = new RefundCapturedReservation( $auth );
					$api->setReconciliationIdentifier( $reconciliation_id );
				} elseif ( get_post_meta( $order_id, '_released', true ) ) {
					$api = new ReleaseReservation( $auth );
				}
				$api->setTransaction( $transaction['TransactionId'] );
				$response = $api->call();
				if ( $response->Result === 'Success' ) {
					if ( ! empty( $reconciliation_id ) ) {
						$transaction = json_decode( wp_json_encode( $response->Transactions ), true );
						$transaction = reset( $transaction );

						$reconciliation = new Core\AltapayReconciliation();
						$reconciliation->saveReconciliationIdentifier( (int) $order_id, $transaction['TransactionId'], $reconciliation_id, 'refunded' );
						$order->update_meta_data( '_refunded', true );
						$order->save();

						// Release agreement
						if ( $txn_id != $transaction['TransactionId'] ) {
							$api = new ReleaseReservation( $auth );
							$api->setTransaction( $txn_id );
							$response = $api->call();
							if ( $response->Result !== 'Success' ) {
								error_log( "altapay_fraud_detection_action error releasing agreement: $response->MerchantErrorMessage" );
							}
						}
					} else {
						$order->update_meta_data( '_released', true );
						$order->save();
					}
				} else {
					error_log( "altapay_fraud_detection_action error: $response->MerchantErrorMessage" );
				}
			} catch ( Exception $e ) {
				error_log( "altapay_fraud_detection_action exception: {$e->getMessage()}" );
			}
		}
		return $return;
	}
}
