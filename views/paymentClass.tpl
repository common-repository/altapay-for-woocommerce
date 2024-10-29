<?php
/**
 * AltaPay module for WooCommerce

 * Copyright © 2020 AltaPay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Altapay\Helpers\Traits\AltapayMaster;
use Altapay\Classes\Util;
use Altapay\Classes\Core;
use Altapay\Helpers;
use Altapay\Api\Ecommerce\PaymentRequest;
use Altapay\Request\Address;
use Altapay\Request\Customer;
use Altapay\Request\Config;
use Altapay\Exceptions\ClientException;
use Altapay\Exceptions\ResponseHeaderException;
use Altapay\Exceptions\ResponseMessageException;
use Altapay\Api\Payments\CaptureReservation;
use Altapay\Api\Payments\RefundCapturedReservation;
use Altapay\Api\Payments\ReleaseReservation;

class WC_Gateway_{key} extends WC_Payment_Gateway {

	use AltapayMaster;

    /**
     * Terminal name.
     *
     * @var string
     */
    public $terminal = '';

    /**
     * Terminal name.
     *
     * @var string
     */
    public $token = '';

    /**
     * Payment type.
     *
     * @var string
     */
    public $payment_action = '';

    public $is_apple_pay;
    public $apple_pay_label;
    public $apple_pay_supported_networks;
    public $secret;

	public function __construct() {
		// Set default gateway values
		$this->id                           = strtolower( 'altapay_{key}' );
		$this->has_fields                   = false;
		$this->method_title                 = 'AltaPay - {name}';
		$this->method_description           = __( 'Adds AltaPay Payment Gateway to use with WooCommerce', 'altapay' );
		$this->supports                     = $this->supportedFeatures();
		$this->terminal                     = '{name}';
		$this->enabled                      = $this->get_option( 'enabled' );
		$this->title                        = $this->get_option( 'title' );
		$this->description                  = $this->get_option( 'description' );
		$this->token                        = $this->get_option( 'token' );
		$this->payment_action               = $this->get_option( 'payment_action' );
		$this->is_apple_pay                 = $this->get_option( 'is_apple_pay' );
		$this->apple_pay_label              = $this->get_option( 'apple_pay_label' );
		$this->apple_pay_supported_networks = $this->get_option( 'apple_pay_supported_networks' );
		$this->secret                       = $this->get_option( 'secret' );


		// Load form fields
		$this->init_form_fields();
		$this->init_settings();

		// Add actions
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_api_wc_gateway_' . $this->id, array( $this, 'checkAltapayResponse' ) );

		// Subscription actions
		add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, array( $this, 'scheduledSubscriptionsPayment' ), 10, 2 );

	}

	/**
	 * Settings page
	 *
	 * @return void
	 */
	public function admin_options() {
		echo '<h3>{name}</h3>';
		echo '<table class="form-table">';
		$this->generate_settings_html();
		echo '</table>';
	}

	/**
	 * Process the payment and return the result.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 * @throws Exception
	 */
	public function process_payment( $order_id ) {
		if ( $this->is_apple_pay === 'yes' ) {
			return [
				'result'   => 'success',
				'redirect' => null,
				'order_id' => $order_id,
			];
		} else {
			$payment_request = $this->createPaymentRequest( $order_id );

			if ( $payment_request['result'] === 'error' ) {
				throw new Exception( $payment_request['message'] );
			}

			// Perform redirect
			return [
				'result'   => 'success',
				'redirect' => $payment_request['formurl'],
			];
		}
	}

	/**
	 * Load form fields
	 *
	 * @return void
	 */
	public function init_form_fields() {
		$tokenStatus = '{tokenStatus}';
		$this->form_fields = include dirname( ALTAPAY_PLUGIN_FILE ) . '/includes/AltapayFormFields.php';
	}

	/**
	 * @param int $order_id
	 *
	 * @return array
	 * @throws Exception
	 */
	public function createPaymentRequest( $order_id ) {
		global $wpdb;
		$utilMethods 	= new Util\UtilMethods;
		$altapayHelpers = new Helpers\AltapayHelpers;
		// Create form request etc.
		$login = $this->altapayApiLogin();
		if ( ! $login || is_wp_error( $login ) ) {
			throw new Exception( 'Could not connect to AltaPay!' );
		}
		// Create payment request
		$order = new WC_Order( $order_id );

		// TODO Get terminal form instance
		$terminal 		= $this->terminal;
		$amount 		= $this->getOrderAmount( $order );
		$currency		= $order->get_currency();
		$customerInfo	= $this->setCustomer( $order );
		$cookie 		= isset($_SERVER['HTTP_COOKIE']) ? $_SERVER['HTTP_COOKIE'] : '';
		$language 		= 'en';
		$languages = array(
			'ca',
			'cs',
			'da',
			'de',
			'ee',
			'en',
			'es',
			'et',
			'fi',
			'fr',
			'hr',
			'is',
			'it',
			'ja',
			'lt',
			'nb',
			'nl',
			'nn',
			'no',
			'pl',
			'pt',
			'ru',
			'sk',
			'sl',
			'sv',
			'th',
			'tr',
			'zh'
		);

		$wpLocale = strtok(get_locale(), '_');

		if (in_array($wpLocale, $languages)) {
			$language = $wpLocale;
		}

		$wpml_language = $order->get_meta( 'wpml_language' );

		if ( ! empty( $wpml_language ) ) {
			$language = $wpml_language;
		}

		// Get chosen page from AltaPay's settings
		$form_page_id = esc_attr( get_option('altapay_payment_page') );
		$configUrl = array(
			'callback_form' 		=> get_page_link($form_page_id),
			'callback_ok' 			=> add_query_arg(
					array(
							'type' => 'ok',
							'wc-api' => 'WC_Gateway_'.$this->id,
					),
					$this->get_return_url($order)
			),
			'callback_fail'			=> add_query_arg(
					array(
							'type' => 'fail',
							'wc-api' => 'WC_Gateway_'.$this->id,
					),
					$this->get_return_url($order)
			),
			'callback_open' 		=> add_query_arg(
					array(
							'type' => 'open',
							'wc-api' => 'WC_Gateway_'.$this->id,
					),
					$this->get_return_url($order)
			),
			'callback_notification' => add_query_arg(
					array(
							'type' => 'notification',
							'wc-api' => 'WC_Gateway_'.$this->id,
					),
					$this->get_return_url($order)
			),
		);

		$config = new Config();
		$config->setCallbackOk( $configUrl['callback_ok'] );
		$config->setCallbackFail( $configUrl['callback_fail'] );
		$config->setCallbackOpen( $configUrl['callback_open'] );
		$config->setCallbackNotification( $configUrl['callback_notification'] );
		$config->setCallbackForm( $configUrl['callback_form'] );

		$callback_redirect_page = get_option( 'altapay_callback_redirect_page' );

		if ( ! empty( $callback_redirect_page ) ) {
			$config->setCallbackRedirect( get_page_link( $callback_redirect_page ) );
		}

		// Make these as settings
		$payment_type = 'payment';
		if ( $this->payment_action === 'authorize_capture' ) {
			$payment_type = 'paymentAndCapture';
		}

		$transactionInfo                = $altapayHelpers->transactionInfo();
		$transactionInfo['ecomOrderId'] = $order->get_order_number();

		try {
			$savedCardNumber = WC()->session->get( 'cardNumber', 0 );
			if ( !$savedCardNumber ) {
				$ccToken = null;
			} else {
				$results = $wpdb->get_results("SELECT ccToken FROM {$wpdb->prefix}altapayCreditCardDetails WHERE creditCardNumber='$savedCardNumber'");
				foreach ( $results as $result ) {
					$ccToken = $result->ccToken;
				}
			}

			$auth    = $this->getAuth();
			$request = new PaymentRequest( $auth );
			$request->setTerminal( $terminal )
			        ->setShopOrderId( $order_id )
			        ->setAmount( round( $amount, 2 ) )
			        ->setCurrency( $currency )
			        ->setCustomerInfo( $customerInfo )
			        ->setConfig( $config )
			        ->setTransactionInfo( $transactionInfo )
			        ->setSalesTax( round( $order->get_total_tax(), 2 ) )
			        ->setCookie( $cookie )
			        ->setCcToken( $ccToken )
			        ->setFraudService( null )
			        ->setLanguage( $language )
			        ->setSaleReconciliationIdentifier( wp_generate_uuid4() );

			// Check if WooCommerce subscriptions is enabled and contains subscription product
			if ( class_exists( 'WC_Subscriptions_Order' ) && wcs_order_contains_subscription( $order_id ) ) {
				if ( $this->payment_action === 'authorize_capture' ) {
					$payment_type = 'subscriptionAndCharge';
				} else {
					$payment_type = 'subscription';
				}

				$request->setAgreement( $this->getAgreementDetail( $order ) );
			}

			$request->setType( $payment_type );

			// Add orderlines to AltaPay request
			$orderLines = $utilMethods->createOrderLines( $order, [], false, in_array( $payment_type, [
				'subscription',
				'subscriptionAndCharge'
			] ) );
			if ( $orderLines instanceof WP_Error ) {
				return $orderLines; // Some error occurred
			}

			$request->setOrderLines( $orderLines );


			if ( $request ) {
				try {
					$response                 = $request->call();
					$requestParams['result']  = 'success';
					$requestParams['formurl'] = $response->Url;
				} catch ( ClientException $e ) {
					$requestParams['result']  = 'error';
					$requestParams['message'] = $e->getResponse()->getBody();
				} catch ( ResponseHeaderException $e ) {
					$requestParams['result']  = 'error';
					$requestParams['message'] = $e->getHeader()->ErrorMessage;
				} catch ( ResponseMessageException | \Exception $e ) {
					$requestParams['result']  = 'error';
					$requestParams['message'] = $e->getMessage();
				}

				$order->add_order_note( __( "Gateway Order ID: $order_id", 'altapay' ) );

				return $requestParams;
			}
		} catch ( Exception $e ) {
			error_log( 'Could not create the payment request: ' . $e->getMessage() );
			$order->add_order_note( __( 'Could not create the payment request: ' . $e->getMessage(), 'altapay' ) );

			throw new Exception( 'Could not create the payment request' );
		}
	}

	/**
	 * Check for Gateway Response
	 *
	 * @return void
	 */
	public function checkAltapayResponse() {
		// Check if callback is altapay and the allowed API
		if ( isset( $_GET['wc-api'] ) && $_GET['wc-api'] === 'WC_Gateway_' . $this->id ) {

			$checksum       = isset( $_POST['checksum'] ) ? sanitize_text_field( wp_unslash( $_POST['checksum'] ) ) : '';
			$altapay_helper = new Helpers\AltapayHelpers();
			if ( ! empty( $checksum ) and ! empty( $this->secret ) and $altapay_helper->calculateChecksum( $_POST, $this->secret ) !== $checksum ) {
				exit;
			}

			$order_id             = isset( $_POST['shop_orderid'] ) ? sanitize_text_field( wp_unslash( $_POST['shop_orderid'] ) ) : '';
			$txnId                = isset( $_POST['transaction_id'] ) ? sanitize_text_field( wp_unslash( $_POST['transaction_id'] ) ) : '';
			$amount               = isset( $_POST['amount'] ) ? sanitize_text_field( wp_unslash( $_POST['amount'] ) ) : '';
			$merchantError        = isset( $_POST['merchant_error_message'] ) ? sanitize_text_field( wp_unslash( $_POST['merchant_error_message'] ) ) : '';
			$errorMessage         = isset( $_POST['error_message'] ) ? sanitize_text_field( wp_unslash( $_POST['error_message'] ) ) : '';
			$payment_status       = isset( $_POST['payment_status'] ) ? sanitize_text_field( wp_unslash( $_POST['payment_status'] ) ) : '';
			$status               = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';
			$type                 = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : '';
			$requireCapture       = isset( $_POST['require_capture'] ) ? sanitize_text_field( wp_unslash( $_POST['require_capture'] ) ) : '';
			$fraud_recommendation = isset( $_POST['fraud_recommendation'] ) ? sanitize_text_field( wp_unslash( $_POST['fraud_recommendation'] ) ) : '';
			$callback_type        = isset( $_GET['type'] ) ? sanitize_text_field( wp_unslash( $_GET['type'] ) ) : '';
			$ccToken              = isset( $_POST['credit_card_token'] ) ? sanitize_text_field( wp_unslash( $_POST['credit_card_token'] ) ) : '';
			$saveCreditCard       = isset( $_POST['transaction_info']['savecreditcard'] ) && sanitize_text_field( wp_unslash( $_POST['transaction_info']['savecreditcard'] ) );
			$reservedAmount       = 0;

			if ( $type == 'subscription_payment' ) {
				$query = new WC_Order_Query( array(
					'limit' => 1,
					'return' => 'ids',
					'meta_key'   => '_transaction_id',
					'meta_value' => $txnId,
				) );
				$orders = $query->get_orders();
				if ( ! empty( $orders ) ) {
					$order_id = $orders[0];
				}
			}

			$order          = wc_get_order( $order_id );
			$transaction_id = $order->get_transaction_id();
			$agreement_id   = $type === 'subscriptionAndCharge' || $type === 'subscription' ? $txnId : '';
			$transaction    = array();

			$xmlResponse = isset( $_POST['xml'] ) ? wp_unslash( $_POST['xml'] ) : '';

			try {

				$xml = new SimpleXMLElement( $xmlResponse );

				if ( $type === 'subscriptionAndCharge' ) {
					$xmlToJson         = wp_json_encode( $xml->Body->Transactions );
					$jsonToArray       = json_decode( $xmlToJson, true );
					$latestTransaction = $this->getLatestTransaction( $jsonToArray['Transaction'], 'subscription_payment' );

					if ( $latestTransaction ) {
						$transaction = $jsonToArray['Transaction'][ $latestTransaction ];
						$txnId       = $transaction['TransactionId'];

						if ( $status === 'succeeded' ) {
							foreach ( $jsonToArray['Transaction'] as $transaction_data ) {
								if ( $transaction_data['AuthType'] === 'subscription_payment' &&
								! in_array( $transaction_data['TransactionStatus'], array( 'captured', 'pending' ) ) ) {
									$order->add_order_note( __( 'Payment failed!', 'altapay' ) );
									wc_add_notice( __( 'Payment failed!', 'altapay' ), 'error' );
									wp_redirect( wc_get_cart_url() );
									exit;
								}
							}
						}
					}
				} else {
					$xmlToJson   = wp_json_encode( $xml->Body->Transactions->Transaction );
					$transaction = json_decode( $xmlToJson, true );
				}

				if ( $type === 'subscription' ) {
					$txnId = '';
				}

				$paymentScheme  = $transaction['PaymentSchemeName'] ?? '';
				$lastFourDigits = $transaction['CardInformation']['LastFourDigits'] ?? '';
				$ccExpiryDate   = isset( $transaction['CreditCardExpiry'] ) ? ( $transaction['CreditCardExpiry']['Month'] . '/' . $transaction['CreditCardExpiry']['Year'] ) : '';
				$reservedAmount = $transaction['ReservedAmount'] ?? 0;

				/*
				Exit if payment already completed against the same order and
				the new transaction ID is different
				*/
				if ( ! empty( $transaction_id ) && $transaction_id != $txnId ) {
					// Release duplicate transaction from the gateway side
					if ( $status === 'succeeded' ) {
						$auth = $this->getAuth();
						if ( in_array( $transaction['TransactionStatus'], array( 'captured', 'bank_payment_finalized' ), true ) ) {
							$api = new RefundCapturedReservation( $auth );
						} else {
							$api = new ReleaseReservation( $auth );
						}
						$api->setTransaction( $txnId );
						$api->call();
					}

					exit;
				}
			} catch ( Exception $e ) {
				error_log( $e->getMessage() );
				$order->add_order_note( $e->getMessage(), 'altapay' );
			}

			// If order already on-hold
			if ( $order->has_status( 'on-hold' ) ) {

				if ( $status === 'succeeded' || $reservedAmount > 0 ) {

					$order->update_meta_data( '_agreement_id', $agreement_id );
					$order->save();
					$order->set_transaction_id( $txnId );

					$reconciliation = new Core\AltapayReconciliation();
					if ( ! empty( $transaction['ReconciliationIdentifiers'] ) ) {
						foreach ( $transaction['ReconciliationIdentifiers'] as $val ) {
							$reconciliation->saveReconciliationIdentifier( $order_id, $txnId, $val['Id'], $val['Type'] );
						}
					}

					if ( $saveCreditCard ) {
						$objTokenControl = new Core\AltapayTokenControl();
						$objTokenControl->saveCreditCardDetails( $order_id, $lastFourDigits, $ccToken, $paymentScheme, $ccExpiryDate );
					}

					if ( $this->detectFraud($order_id, $agreement_id, $transaction, $fraud_recommendation) ) {
						$order->add_order_note( __( "Payment declined due to suspected fraud: {$_POST['fraud_explanation']}." , 'altapay' ) );
						exit;
					}

					$order->add_order_note( __( 'Notification completed', 'altapay' ) );
					$order->payment_complete();

				} elseif ( $status === 'error' || $status === 'failed' ) {
						$order->update_status( 'failed', 'Payment failed' . $errorMessage );
						$order->add_order_note( __( 'Payment failed' . $errorMessage . ' Merchant error: ' . $merchantError, 'altapay' ) );
				}

				exit;
			}

			if ( $status === 'open' ) {
				$order->update_status( 'on-hold', 'The payment is pending an update from the payment provider.' );
				$redirect = $this->get_return_url( $order );
				wp_redirect( $redirect );
				exit;
			}

			if ( $payment_status === 'released' ) {
				$order->add_order_note( __( 'Payment failed: payment released', 'altapay' ) );
				wc_add_notice( __( 'Payment error:', 'altapay' ) . ' Payment released', 'error' );
				wp_redirect( wc_get_cart_url() );
				exit;
			}

			// Make some validation
			if ( ( ( $errorMessage || $merchantError ) && $reservedAmount == 0 ) || array_key_exists( 'cancel_order', $_GET ) ) {
				$order->add_order_note( __( 'Payment failed: ' . $errorMessage . ' Merchant error: ' . $merchantError, 'altapay' ) );
				wc_add_notice( __( 'Payment error:', 'altapay' ) . ' ' . $errorMessage, 'error' );
				wp_redirect( wc_get_cart_url() );
				exit;
			}

			if ( $order->has_status( 'pending' ) && ( $status === 'succeeded' || $reservedAmount > 0 ) ) {

				$order->set_transaction_id( $txnId );
				$order->update_meta_data( '_agreement_id', $agreement_id );

				$reconciliation = new Core\AltapayReconciliation();
				if ( ! empty( $transaction['ReconciliationIdentifiers'] ) ) {
					foreach ( $transaction['ReconciliationIdentifiers'] as $val ) {
						$reconciliation->saveReconciliationIdentifier( $order_id, $txnId, $val['Id'], $val['Type'] );
					}
				}

				if ( $saveCreditCard ) {
					$objTokenControl = new Core\AltapayTokenControl();
					$objTokenControl->saveCreditCardDetails( $order_id, $lastFourDigits, $ccToken, $paymentScheme, $ccExpiryDate );
				}

				if ( $this->detectFraud($order_id, $agreement_id, $transaction, $fraud_recommendation) ) {
					$order->update_status( 'on-hold', "Fraud detected: {$_POST['fraud_explanation']}." );
					wc_add_notice( __( 'Payment error:', 'altapay' ) . ' Payment Declined', 'error' );
					wp_redirect( wc_get_cart_url() );
					exit;
				}

				// Payment completed
				$order->add_order_note( __( 'Callback completed', 'altapay' ) );
				if ( $transaction['AuthType'] === 'subscription_payment' and $transaction['TransactionStatus'] === 'pending' ) {
					$order->update_status( 'on-hold', 'The payment is pending an update from the payment provider.' );
				} else {
					$order->add_order_note( __( 'Callback completed', 'altapay' ) );
					$order->payment_complete();
				}

			}

			if ( $status === 'succeeded' && $payment_status === 'bank_payment_refunded' && $transaction_id == $txnId ) {

				$amount = $transaction['RefundedAmount'];

				$refund = wc_create_refund(
					array(
						'amount'         => $amount,
						'reason'         => null,
						'order_id'       => $order_id,
						'line_items'     => array(),
						'refund_payment' => false,
						'restock_items'  => true,
					)
				);

				if ( $refund instanceof WP_Error ) {
					$order->add_order_note( __( $refund->get_error_message(), 'altapay' ) );
				} else {
					$order->add_order_note( __( 'Refunded products have been re-added to the inventory', 'altapay' ) );
				}

				$order->update_meta_data( '_refunded', true );
				$order->save();

				$reconciliation = new Core\AltapayReconciliation();

				$identifier = $transaction['ReconciliationIdentifiers']['ReconciliationIdentifier'];

				if ( count( $identifier ) == count( $identifier, COUNT_RECURSIVE ) ) {
					$reconciliation->saveReconciliationIdentifier(
						$order_id,
						$txnId,
						$identifier['Id'],
						$identifier['Type']
					);
				} else {
					foreach ( $identifier as $val ) {
						$reconciliation->saveReconciliationIdentifier(
							$order_id,
							$txnId,
							$val['Id'],
							$val['Type']
						);
					}
				}
				exit();
			}

			// Redirect to Order Confirmation Page
			if ( $type === 'paymentAndCapture' && $requireCapture === 'true' && $callback_type == 'ok' ) {
				$login = $this->altapayApiLogin();
				if ( ! $login || is_wp_error( $login ) ) {
					error_log( 'Could not connect to AltaPay!' );
					return;
				}

				$api = new CaptureReservation( $this->getAuth() );
				$api->setAmount( round( $amount, 2 ) );
				$api->setTransaction( $txnId );
				$api->setReconciliationIdentifier( wp_generate_uuid4() );

				/** @var CaptureReservationResponse $response */
				try {
					$response = $api->call();

					$transaction = json_decode( json_encode( $response->Transactions ), true );
					$transaction = reset( $transaction );

					$reconciliation = new Core\AltapayReconciliation();
					if ( ! empty( $transaction['ReconciliationIdentifiers'] ) ) {
						foreach ( $transaction['ReconciliationIdentifiers'] as $val ) {
							$reconciliation->saveReconciliationIdentifier( $order_id, $txnId, $val['Id'], $val['Type'] );
						}
					}

				} catch ( ResponseHeaderException $e ) {
					error_log( 'Response header exception ' . $e->getMessage() );
				} catch ( \Exception $e ) {
					error_log( 'Exception ' . $e->getMessage() );
				}
			}
			$redirect = $this->get_return_url( $order );
			wp_redirect( $redirect );
			exit;
		}
	}

	/**
	 * Process refund.
	 *
	 * If the gateway declares 'refunds' support, this will allow it to refund.
	 * a passed in amount.
	 *
	 * @param  int        $order_id Order ID.
	 * @param  float|null $amount Refund amount.
	 * @param  string     $reason Refund reason.
	 * @return boolean True on success, or a WP_Error object.
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {

		$refund = altapayRefundPayment( $order_id, $amount, $reason, false );

		if ( isset( $refund['error'] ) ) {
			return new WP_Error( 'error', __( $refund['error'], 'altapay' ) );
		}

		return true;
	}

	/**
	 * @param $order
	 *
	 * @return float
	 */
	private function getOrderAmount( $order ) {

		$amount = $order->get_total();

		if ( $amount == 0 && class_exists( 'WC_Subscriptions_Order' ) && wcs_order_contains_subscription( $order ) ) {
			$subscriptions = wcs_get_subscriptions_for_order( $order );
			foreach ( $subscriptions as $subscription ) {
				$amount += $subscription->get_total();
			}
		}

		return $amount;
	}

	/**
	 * @param array   $addressInfo
	 * @param Address $address
	 *
	 * @return void
	 */
	private function populateAddressObject( $addressInfo, $address ) {
		$address->Firstname  = $addressInfo['firstname'];
		$address->Lastname   = $addressInfo['lastname'];
		$address->Address    = $addressInfo['address'];
		$address->City       = $addressInfo['city'];
		$address->PostalCode = $addressInfo['postcode'];
		$address->Region     = $addressInfo['region'] ?: '0';

		if ( ! empty( $addressInfo['country'] ) ) {
			$address->Country = $addressInfo['country'];
		}
	}

	/**
	 * @param WC_Order $order
	 *
	 * @return Customer
	 * @throws Exception
	 */
	public function setCustomer( $order ) {
		$address        = new Address();
		$altapayHelpers = new Helpers\AltapayHelpers();
		$billingInfo    = array(
			'firstname' => $order->get_billing_first_name(),
			'lastname'  => $order->get_billing_last_name(),
			'address'   => $order->get_billing_address_1(),
			'postcode'  => $order->get_billing_postcode(),
			'city'      => $order->get_billing_city(),
			'region'    => $order->get_billing_state(),
			'country'   => $order->get_billing_country(),
		);
		$shippingInfo   = array(
			'firstname' => $order->get_shipping_first_name(),
			'lastname'  => $order->get_shipping_last_name(),
			'address'   => $order->get_shipping_address_1(),
			'postcode'  => $order->get_shipping_postcode(),
			'city'      => $order->get_shipping_city(),
			'region'    => $order->get_shipping_state(),
			'country'   => $order->get_shipping_country(),
		);
		if ( $order->get_billing_address_1() ) {
			$this->populateAddressObject( $billingInfo, $address );
		}
		$customer = new Customer( $address );
		if ( $order->get_shipping_address_1() ) {
			$shippingAddress = new Address();
			$this->populateAddressObject( $shippingInfo, $shippingAddress );
			$customer->setShipping( $shippingAddress );
		} else {
			$customer->setShipping( $address );
		}
		$customer->setEmail( $order->get_billing_email() );
		$customer->setUsername( $order->get_billing_email() );
		$customer->setPhone( str_replace( ' ', '', $order->get_billing_phone() ) );
		$customer->setClientIP( $_SERVER['REMOTE_ADDR'] );
		$customer->setClientAcceptLanguage( substr( $_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2 ) );
		$customer->setHttpUserAgent( $_SERVER['HTTP_USER_AGENT'] );
		$customer->setClientSessionID( crypt( session_id(), '$5$rounds=5000$customersessionid$' ) );

		// Get user registration date
		if ( is_user_logged_in() && $order->get_user_id() ) {
			$userData            = get_userdata( $order->get_user_id() );
			$customerCreatedDate = $altapayHelpers->convertDateTimeFormat( $userData->user_registered );
			$customer->setCreatedDate( new \DateTime( $customerCreatedDate ) );
		}

		return $customer;
	}

	/**
	 * @param $order
	 * @return array
	 */
	public function getAgreementDetail( $order ) {
		$agreementDetails             = array();
			$agreementDetails['type'] = 'recurring';
			$subscriptions            = wcs_get_subscriptions_for_order( $order );

		foreach ( $subscriptions as $subscription ) {
			$agreementDetails['frequency']        = $this->agreementFrequency( $subscription->get_billing_period() );
			$agreementDetails['next_charge_date'] = date( 'Ymd', $subscription->get_time( 'next_payment' ) );
			$agreementDetails['admin_url']        = $subscription->get_view_order_url();

			if ( $subscription->get_time( 'end' ) ) {
				$agreementDetails['expiry'] = date( 'Ymd', $subscription->get_time( 'end' ) );
			}
		}

		return $agreementDetails;
	}

	/**
	 * @param $billing_period
	 * @return string
	 */
	public function agreementFrequency( $billing_period ) {

		$arr = array(
			'day'   => '1',
			'week'  => '7',
			'month' => '30',
			'year'  => '365',
		);

		return $arr[ $billing_period ] ?? '30';
	}

	/**
	 * @return string[]
	 */
	public function supportedFeatures() {
		$supportSubscriptions = '{supportSubscriptions}';
		$features             = array( 'products','refunds' );

		if ( $supportSubscriptions == true ) {
			$features = array_merge(
				$features,
				array(
					'subscriptions',
					'subscription_cancellation',
					'subscription_suspension',
					'subscription_reactivation',
					'subscription_amount_changes',
					'subscription_date_changes',
				)
			);
		}

		return $features;
	}

    /**
     * Gets the payment method's icon.
     *
     * @return string The icon HTML.
     */
    public function get_icon() {
        $icon_html = '<span>';
        $icons = $this->get_option('payment_icon');
        if ( ! empty( $icons ) and is_array( $icons ) ) {
            foreach ( $icons as $icon ) {
                if ( ! empty( $icon ) and $icon !== 'default' ) {
                    $icon_html .= '<img src="' . untrailingslashit(plugins_url('/assets/images/payment_icons/' . $icon, ALTAPAY_PLUGIN_FILE)) . '" alt="' . $this->title . '">';
                }
            }
        } elseif( ! empty( $icons ) and $icons !== 'default' ) {
            $icon_html .= '<img src="' . untrailingslashit(plugins_url('/assets/images/payment_icons/' . $icons, ALTAPAY_PLUGIN_FILE)) . '" alt="' . $this->title . '">';
        }
        $icon_html .= '</span>';
        return apply_filters( 'woocommerce_gateway_icon', $icon_html, $this->id );
    }
}
