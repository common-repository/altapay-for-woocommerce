<?php
/**
 * AltaPay module for WooCommerce
 *
 * Copyright © 2020 AltaPay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Altapay\Classes\Core;

use Altapay\Helpers;
use Altapay\Helpers\Traits\AltapayMaster;
use Altapay\Api\Others\Terminals;
use Altapay\Api\Others\Payments;
use Altapay\Api\Payments\CaptureReservation;
use Exception;
use AltaPay\vendor\GuzzleHttp\Exception\ClientException;
use WC_Order;
use Altapay\Classes\Core;
use Altapay\Api\Subscription\ChargeSubscription;
use Altapay\Exceptions\ResponseHeaderException;

class AltapaySettings {

	use AltapayMaster;

	/**
	 * AltapaySettings constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'altapayCapturesPostInit' ) );
		// Add admin menu
		add_action( 'admin_menu', array( $this, 'altapaySettingsMenu' ), 60 );
		// Register settings
		add_action( 'admin_init', array( $this, 'altapayRegisterSettings' ) );
		// Add settings link on plugin page
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'addActionLinks' ) );
		// Order completed interceptor:
		add_action( 'woocommerce_order_status_completed', array( $this, 'altapayOrderStatusCompleted' ) );

		add_action( 'admin_notices', array( $this, 'loginError' ) );
		add_action( 'admin_notices', array( $this, 'captureFailed' ) );
		add_action( 'admin_notices', array( $this, 'captureWarning' ) );
		add_action( 'admin_notices', array( $this, 'terminals_directory_error' ) );
	}

	/**
	 * @param int $orderID
	 * @return void
	 */
	public function altapayOrderStatusCompleted( $orderID ) {
		// Load order
		$order = new WC_Order( $orderID );
		$txnID = $order->get_transaction_id();

		$subscription = false;
		$authType     = 'payment';
		if ( class_exists( 'WC_Subscriptions_Order' ) && ( wcs_order_contains_subscription( $orderID, 'parent' ) || wcs_order_contains_subscription( $orderID, 'renewal' ) ) ) {

			if ( $order->get_total() == 0 || ! empty( $order->get_meta( '_captured' ) ) ) {
				return;
			}

			$txnID        = $order->get_meta( '_agreement_id' );
			$subscription = true;
			$authType     = 'subscription_payment';
		}

		if ( ! $txnID ) {
			return;
		}

		$login = $this->altapayApiLogin();
		if ( ! $login || is_wp_error( $login ) ) {
			error_log( 'Could not connect to AltaPay!' );

			return;
		}

		try {
			$auth = $this->getAuth();
			$api  = new Payments( $auth );
			$api->setTransaction( $txnID );
			$payments = $api->call();

			if ( ! $payments ) {
				return;
			}
		} catch ( Exception $e ) {
			$this->saveCaptureFailedMessage(
				'Capture failed for order ' . $orderID . ': ' . $e->getMessage()
			);
			return;
		}
		$pay = $payments[0];
		if ( $pay->CapturedAmount == $pay->ReservedAmount ) {
			return;
		}

		if ( $pay->CapturedAmount > 0 ) {
			$this->saveCaptureWarning( 'Could not capture automatically. Manual capture is required for the order: ' . $orderID );
		} else { // Order wasn't captured and must be captured now.
			$amount = $pay->ReservedAmount; // Amount to capture.

			try {
				if ( $subscription === true ) {
					$api = new ChargeSubscription( $this->getAuth() );
				} else {
					$api = new CaptureReservation( $this->getAuth() );
				}

				$api->setAmount( round( $amount, 2 ) );
				$api->setTransaction( $txnID );

				$response = $api->call();
				if ( $response->Result !== 'Success' ) {
					$order->add_order_note(
						__(
							'Capture failed: ' . $response->MerchantErrorMessage,
							'Altapay'
						)
					); // log to history.
					$this->saveCaptureFailedMessage(
						'Capture failed for order ' . $orderID . ': ' . $response->MerchantErrorMessage
					);

					return;
				}

				$order->update_meta_data( '_captured', true );
				$order->add_order_note( __( 'Order captured: amount: ' . $amount, 'Altapay' ) );
				$order->save();

				$transactions       = json_decode( wp_json_encode( $response->Transactions ), true );
				$latest_transaction = $this->getLatestTransaction( $transactions, $authType );
				$transaction        = $transactions[ $latest_transaction ];
				$txn_id             = $transaction['TransactionId'];

				if ( $subscription === true ) {
					$order->set_transaction_id( $txn_id );
					$order->save();
				}

				$reconciliation = new Core\AltapayReconciliation();
				foreach ( $transaction['ReconciliationIdentifiers'] as $val ) {
					$reconciliation->saveReconciliationIdentifier( $orderID, $txn_id, $val['Id'], $val['Type'] );
				}
			} catch ( ResponseHeaderException | Exception $e ) {
				error_log( 'Exception ' . $e->getMessage() );
			}
		}
	}

	/**
	 * Starts the session
	 *
	 * @return void
	 */
	public function startSession() {
		if ( session_id() === '' ) {
			session_start();
		}
	}

	/**
	 * @param string $newMessage
	 * @return void
	 */
	public function saveCaptureFailedMessage( $newMessage ) {
		$message = get_transient( 'altapay_capture_failed' );
		if ( $message ) {
			$message .= '<br/>';
		}

		set_transient( 'altapay_capture_failed', $message . $newMessage, 30 );
	}

	/**
	 * @param string $newMessage
	 * @return void
	 */
	public function saveCaptureWarning( $newMessage ) {
		$message = get_transient( 'altapay_capture_warning' );

			// Ignore if the transient already contains the same message.
		if ( $message && strpos( $message, $newMessage ) !== false ) {
			return;
		}

		$message = $message ? $message . '<br/>' . $newMessage : $newMessage;
		set_transient( 'altapay_capture_warning', $message, 30 );
	}

	/**
	 * Displays login error message
	 *
	 * @return void
	 */
	public function loginError() {
		$this->showUserMessage( 'altapay_login_error', 'error', 'Could not login to the Merchant API: ' );
	}

	/**
	 * @param string $field
	 * @param string $type
	 * @param string $message
	 * @return void
	 */
	public function showUserMessage( $field, $type, $message = '' ) {

		$msg = get_transient( $field );
		if ( ! $msg ) {
			return;
		}

		echo "<div class='$type notice'> <p>$message $msg</p> </div>";

		delete_transient( $field );
	}

	/**
	 * Displays failed capture message
	 *
	 * @return void
	 */
	public function captureFailed() {
		$this->showUserMessage( 'altapay_capture_failed', 'error' );
	}

	/**
	 * Displays warning message against capture request
	 *
	 * @return void
	 */
	public function captureWarning() {
		$this->showUserMessage( 'altapay_capture_warning', 'notice-warning' );
	}

	/**
	 * Displays error message for terminals directory
	 *
	 * @return void
	 */
	public function terminals_directory_error() {

		$transient_value = get_transient( 'terminals_directory_error' );

		if ( 'show' === $transient_value ) {

			$plugin_data = get_plugin_data( ALTAPAY_PLUGIN_FILE );
			$plugin_name = $plugin_data['Name'];

			echo '<div class="error">';
			echo '<p>';
			echo __( '<strong>' . $plugin_name . ' </strong>: Unable to save the file to <strong>terminals</strong> directory. Check the terminals folder has the right permission', 'altapay' );
			echo '</p>';
			echo '</div>';

			delete_transient( 'terminals_directory_error' );
		}
	}

	/**
	 * @param array $links
	 * @return array
	 */
	public function addActionLinks( $links ) {
		$newLink = array(
			'<a href="' . admin_url( 'admin.php?page=altapay-settings' ) . '">Settings</a>',
		);

		return array_merge( $links, $newLink );
	}


	/**
	 * Add AltaPay settings option in plugins menu
	 *
	 * @return void
	 */
	public function altapaySettingsMenu() {
		add_submenu_page(
			'woocommerce',
			'AltaPay Settings',
			'AltaPay Settings',
			'manage_options',
			'altapay-settings',
			array( $this, 'altapaySettings' )
		);
	}

	/**
	 * Register AltaPay specific settings group including url and api login credentials
	 *
	 * @return void
	 */
	public function altapayRegisterSettings() {
		register_setting( 'altapay-settings-group', 'altapay_gateway_url' );
		register_setting( 'altapay-settings-group', 'altapay_username' );
		register_setting( 'altapay-settings-group', 'altapay_password' );
		register_setting( 'altapay-settings-group', 'altapay_fraud_detection' );
		register_setting( 'altapay-settings-group', 'altapay_fraud_detection_action' );
		register_setting( 'altapay-settings-group', 'altapay_payment_page' );
		register_setting( 'altapay-settings-group', 'altapay_cc_form_styling' );
		register_setting(
			'altapay-settings-group',
			'altapay_terminals_enabled',
			array( $this, 'encodeTerminalsData' )
		);
	}

	/**
	 * Encode the data before saving
	 *
	 * @param array $val
	 * @return string
	 */
	public function encodeTerminalsData( $val ) {
		if ( $val ) {
			$val = wp_json_encode( $val );
		}

		return $val;
	}


	/**
	 * AltaPay settings page with actions and controls
	 *
	 * @return void
	 * @throws Exception
	 */
	public function altapaySettings() {
		$terminals                      = false;
		$disabledTerminals              = array();
		$enabledTerminals               = array();
		$gatewayURL                     = esc_attr( get_option( 'altapay_gateway_url' ) );
		$username                       = get_option( 'altapay_username' );
		$password                       = get_option( 'altapay_password' );
		$paymentPage                    = esc_attr( get_option( 'altapay_payment_page' ) );
		$terminalDetails                = get_option( 'altapay_terminals' );
		$terminalsEnabled               = get_option( 'altapay_terminals_enabled' );
		$altapay_fraud_detection        = get_option( 'altapay_fraud_detection' );
		$altapay_fraud_detection_action = get_option( 'altapay_fraud_detection_action' );
		$cc_form_styling                = get_option( 'altapay_cc_form_styling' );

		if ( $terminalDetails ) {
			$terminals = json_decode( get_option( 'altapay_terminals' ) );
		}
		if ( $terminalsEnabled ) {
			$enabledTerminals = json_decode( get_option( 'altapay_terminals_enabled' ) );
		}
		$terminalInfo = json_decode( get_option( 'altapay_terminals' ) );

		if ( is_array( $terminalInfo ) ) {
			foreach ( $terminalInfo as $term ) {
				// The key is the terminal name
				if ( ! in_array( $term->key, $enabledTerminals ) ) {
					array_push( $disabledTerminals, $term->key );
				}
			}
		}

		$pluginDir = plugin_dir_path( __FILE__ );
		// Directory for the terminals
		$terminalDir = $pluginDir . '/../../terminals/';
		// Temp dir in case the one from above is not writable
		$tmpDir = sys_get_temp_dir();

		foreach ( $disabledTerminals as $disabledTerm ) {
			$disabledTerminalFileName = $disabledTerm . '.class.php';
			$path                     = $terminalDir . $disabledTerminalFileName;
			$tmpPath                  = $tmpDir . '/' . $disabledTerminalFileName;
			// Check if there is a terminal created so it can  be removed
			if ( file_exists( $path ) ) {
				unlink( $path );
			} elseif ( file_exists( $tmpPath ) ) {
				unlink( $tmpPath );
			}
		}

		if ( isset( $_REQUEST['settings-updated'] ) ) {
			$this->refreshTerminals();
		}
		?>

		<div class="wrap" style="margin-top:2%;">
			<h1><?php esc_html_e( 'AltaPay Settings', 'altapay' ); ?></h1>
			<?php
			if ( version_compare( PHP_VERSION, '5.4', '<' ) ) {
				wp_die( sprintf( 'AltaPay for WooCommerce requires PHP 5.4 or higher. You’re still on %s.', PHP_VERSION ) );
			} else {
				$blade = new Helpers\AltapayHelpers();
				echo $blade->loadBladeLibrary()->run(
					'forms.adminSettings',
					array(
						'gatewayURL'                     => $gatewayURL,
						'username'                       => $username,
						'password'                       => $password,
						'paymentPage'                    => $paymentPage,
						'terminals'                      => $terminals,
						'enabledTerminals'               => $enabledTerminals,
						'altapay_fraud_detection'        => $altapay_fraud_detection,
						'altapay_fraud_detection_action' => $altapay_fraud_detection_action,
						'cc_form_styling'                => $cc_form_styling,

					)
				);
				?>
				<script>
					jQuery(document).ready(function ($) {
						jQuery('#create_altapay_payment_page').unbind().on('click', function (e) {
							var data = {
								'action': 'create_altapay_payment_page',
							};
							// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
							jQuery.post(ajaxurl, data, function (response) {
								result = jQuery.parseJSON(response);
								if (result.status == 'ok') {
									jQuery('#altapay_payment_page').val(result.page_id);
									jQuery('#payment-page-msg').text(result.message);
									jQuery('#create_altapay_payment_page').attr('disabled', 'disabled');
								} else {
									jQuery('#payment-page-msg').text(result.message);
								}
							});

						});
					});
				</script>

				<?php
				if ( $gatewayURL && $username ) {
					$this->altapayRefreshConnectionForm();
					$this->altapaySynchronizeTerminalsForm();
				}
			}
			?>
		</div>
		<?php
	}

	/**
	 * Method for refreshing terminals on AltaPay settings page
	 *
	 * @return void
	 */
	function refreshTerminals() {
		$login = $this->altapayApiLogin();
		if ( ! $login || is_wp_error( $login ) ) {
			if ( is_wp_error( $login ) ) {
				echo '<div class="error"><p>' . wp_kses_post( $login->get_error_message() ) . '</p></div>';
			} else {
				echo '<div class="error"><p>' . __( 'Could not connect to AltaPay!', 'altapay' ) . '</p></div>';
			}
			// Delete terminals and enabled terminals from database
			update_option( 'altapay_terminals', '' );
			update_option( 'altapay_terminals_enabled', '' );
			?>
			<script>
				setTimeout("location.reload()", 1500);
			</script>
			<?php
			return;
		}

		echo "<div class='notice notice-success is-dismissible'> <p>" . __( 'Connection OK !', 'altapay' ) . '</p> </div>';

		$terminals = array();
		$auth      = $this->getAuth();
		$api       = new Terminals( $auth );
		$response  = $api->call();

		foreach ( $response->Terminals as $terminal ) {
			$terminals[] = array(
				'key'        => preg_replace( '/[^a-zA-Z0-9]/', '_', $terminal->Title ),
				'name'       => $terminal->Title,
				'nature'     => $terminal->Natures,
				'methods'    => $terminal->Methods,
				'identifier' => $terminal->PrimaryMethod->Identifier ?? '',
			);
		}

		update_option( 'altapay_terminals', wp_json_encode( $terminals ) );
		?>
		<script>
			setTimeout("location.reload()", 1500);
		</script>
		<?php
	}

	/**
	 * Form with refresh connection button on AltaPay Settings page
	 *
	 * @return void
	 */
	private function altapayRefreshConnectionForm() {
		$terminals = get_option( 'altapay_terminals' );
		if ( ! $terminals ) {
			?>
			<p><?php esc_html_e( 'Terminals missing, please click - Refresh connection', 'altapay' ); ?></p>
		<?php } else { ?>
			<p><?php esc_html_e( 'Click below to re-create terminal data', 'altapay' ); ?></p>
		<?php } ?>
		<form method="post" action="#refresh_connection">
			<input type="hidden" value="true" name="refresh_connection">
			<input type="submit" value="<?php esc_html_e( 'Refresh connection', 'altapay' ); ?>" name="refresh-connection"
				   class="button" style="color: #006064; border-color: #006064; margin: bottom 15px;">
		</form>
		<?php
		// TODO Make use of WordPress notice and error handling
		// Test connection
		if ( isset( $_POST['refresh_connection'] ) ) {
			$this->refreshTerminals();
		}
	}


	/**
	 * Form with synchronize payment methods button on AltaPay Settings page
	 *
	 * @return void
	 */
	function syncTerminals() {

		// return if terminals are configured already
		if ( get_option( 'altapay_terminals_enabled' ) ) {
			echo '<div id="message" class="notice notice-error"><p>Terminals are already configured, please select the checkboxes manually.</p></div>';
			return;
		}

		// return if terminals does not exist
		if ( ! get_option( 'altapay_terminals' ) ) {
			echo '<div id="message" class="notice notice-error"><p>Terminals are missing. Click "Refresh connection" button to re-create terminal data.</p></div>';
			return;
		}

		$terminals = array();
		$api       = new Terminals( $this->getAuth() );
		$response  = $api->call();
		$wcCountry = get_option( 'woocommerce_default_country' );

		foreach ( $response->Terminals as $key => $terminal ) {

			if ( $terminal->Country !== $wcCountry ) {
				continue;
			}

			$terminalTitle = preg_replace( '/[^a-zA-Z0-9]/', '_', $terminal->Title );
			$terminals[]   = $terminalTitle;

			$terminalSettings = array(
				'enabled'        => 'yes',
				'title'          => str_replace( '-', ' ', $terminal->Title ),
				'description'    => '',
				'payment_action' => 'authorize',
				'payment_icon'   => self::getPaymentMethodIcon( $terminal->PrimaryMethod->Identifier ?? '' ),
				'currency'       => get_option( 'woocommerce_currency' ),
			);

			update_option(
				'woocommerce_altapay_' . strtolower( $terminalTitle ) . '_settings',
				$terminalSettings,
				'yes'
			);
		}

		if ( $terminals ) {
			delete_option( 'altapay_terminals_enabled' );
			add_option( 'altapay_terminals_enabled', $terminals );
		}

		set_transient( 'altapay_sync_terminals', 'Payment methods synchronized successfully!', 30 );
		wp_redirect( admin_url( 'admin.php?page=altapay-settings' ) );
		exit;
	}

	/**
	 * Form with synchronize payment methods button on AltaPay Settings page
	 *
	 * @return void
	 */
	private function altapaySynchronizeTerminalsForm() {
		$this->showUserMessage( 'altapay_sync_terminals', 'notice-success' );
		?>
		<form method="post" action="">
			<p><?php esc_html_e( 'Click below to synchronize payment methods', 'altapay' ); ?></p>
			<input type="hidden" value="true" name="sync_terminals">
			<input type="submit" value="<?php esc_html_e( 'Synchronize payment methods', 'altapay' ); ?>"  name="sync-connection" class="button" style="color: #006064; border-color: #006064;">
		</form>
		<?php

		if ( isset( $_POST['sync_terminals'] ) ) {
			$this->syncTerminals();
		}
	}

	/**
	 * Register post type to record captures.
	 *
	 * @return void
	 */
	function altapayCapturesPostInit() {

		$args = array(
			'labels'          => __( 'Captures', 'altapay' ),
			'capability_type' => 'post',
			'public'          => false,
			'hierarchical'    => false,
			'supports'        => false,
			'rewrite'         => false,
			'query_var'       => false,
		);

		register_post_type( 'altapay_captures', $args );

		$callback_redirect_page = get_option( 'altapay_callback_redirect_page' );

		if ( empty( $callback_redirect_page ) ) {
			Core\AltapayPluginInstall::createCallbackRedirectPage();
		}
	}

	/**
	 * Recreate terminal data
	 *
	 * @param $self
	 *
	 * @return void
	 */
	static function recreateTerminalData( $self ) {

		$recreated_terminals = array();
		$gateway_username    = get_option( 'altapay_username' );
		$gateway_password    = get_option( 'altapay_password' );
		$gateway_url         = get_option( 'altapay_gateway_url' );

		if ( empty( $gateway_username ) || empty( $gateway_password ) || empty( $gateway_url ) ) {
			return;
		}

		$terminals = json_decode( get_option( 'altapay_terminals' ) );

		// return if terminals data already contains methods property
		if ( $terminals ) {
			foreach ( $terminals as $terminal ) {
				if ( isset( $terminal->methods ) ) {
					update_option( 'altapay_terminal_classes_recreated', true );
					return;
				}
			}
		}

		try {
			$auth     = $self->getAuth();
			$api      = new Terminals( $auth );
			$response = $api->call();

			foreach ( $response->Terminals as $terminal ) {
				$recreated_terminals[] = array(
					'key'        => preg_replace( '/[^a-zA-Z0-9]/', '_', $terminal->Title ),
					'name'       => $terminal->Title,
					'nature'     => $terminal->Natures,
					'methods'    => $terminal->Methods ?? array(),
					'identifier' => $terminal->PrimaryMethod->Identifier ?? '',
				);
			}

			update_option( 'altapay_terminals', wp_json_encode( $recreated_terminals ) );

			$enabledTerminals = array();
			$terminalsEnabled = get_option( 'altapay_terminals_enabled' );

			if ( $terminalsEnabled ) {
				$enabledTerminals = json_decode( get_option( 'altapay_terminals_enabled' ) );
			}
			$terminalInfo = json_decode( get_option( 'altapay_terminals' ) );
			$pluginDir    = plugin_dir_path( __FILE__ );
			// Directory for the terminals
			$terminalDir = $pluginDir . '/../../terminals/';
			// Temp dir in case the one from above is not writable
			$tmpDir = sys_get_temp_dir();
			if ( is_array( $terminalInfo ) ) {
				foreach ( $terminalInfo as $term ) {
					// The key is the terminal name
					if ( in_array( $term->key, $enabledTerminals ) ) {

						$disabledTerminalFileName = $term->key . '.class.php';
						$path                     = $terminalDir . $disabledTerminalFileName;
						$tmpPath                  = $tmpDir . '/' . $disabledTerminalFileName;
						// Check if there is a terminal created, so it can  be removed
						if ( file_exists( $path ) ) {
							unlink( $path );
						} elseif ( file_exists( $tmpPath ) ) {
							unlink( $tmpPath );
						}
					}
				}
			}

			update_option( 'altapay_terminal_classes_recreated', true );

		} catch ( ClientException $e ) {
			error_log( 'Error: ' . $e->getMessage() );
		} catch ( Exception $e ) {
			error_log( 'Error: ' . $e->getMessage() );
		}
	}

	/**
	 * Get Terminal logo based on payment method identifier
	 *
	 * @param string $identifier
	 * @return string
	 */
	static function getPaymentMethodIcon( $identifier = '' ) {
		$defaultValue = 'default';

		$paymentMethodIcons = array(
			'ApplePay'    => 'apple_pay.png',
			'Bancontact'  => 'bancontact.png',
			'BankPayment' => 'bank.png',
			'CreditCard'  => 'creditcard.png',
			'iDeal'       => 'ideal.png',
			'Invoice'     => 'invoice.png',
			'Klarna'      => 'klarna_pink.png',
			'MobilePay'   => 'mobilepay.png',
			'OpenBanking' => 'bank.png',
			'Payconiq'    => 'payconiq.png',
			'PayPal'      => 'paypal.png',
			'Przelewy24'  => 'przelewy24.png',
			'Sepa'        => 'sepa.png',
			'SwishSweden' => 'swish.png',
			'Trustly'     => 'trustly_primary.png',
			'Twint'       => 'twint.png',
			'ViaBill'     => 'viabill.png',
			'Vipps'       => 'vipps.png',
		);

		if ( isset( $paymentMethodIcons[ $identifier ] ) ) {
			return $paymentMethodIcons[ $identifier ];
		}

		return $defaultValue;
	}

}
