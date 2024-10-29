<?php
/**
 * Plugin Name: AltaPay for WooCommerce - Payments less complicated
 * Plugin URI: https://documentation.altapay.com/Content/Plugins/Plugins.htm
 * Description: Payment Gateway to use with WordPress WooCommerce
 * Author: AltaPay
 * Author URI: https://altapay.com
 * Text Domain: altapay
 * Domain Path: /languages
 * Version: 3.7.3
 * Name: SDM_Altapay
 * WC requires at least: 3.9.0
 * WC tested up to: 9.3.3
 *
 * @package Altapay
 */

use Altapay\Classes\Core;
use Altapay\Classes\Util;
use Altapay\Helpers;
use Altapay\Api\Payments\CaptureReservation;
use Altapay\Exceptions\ResponseHeaderException;
use Altapay\Api\Payments\RefundCapturedReservation;
use Altapay\Api\Payments\ReleaseReservation;
use Altapay\Response\ReleaseReservationResponse;
use Altapay\Api\Others\Payments;
use Altapay\Api\Subscription\ChargeSubscription;
use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;
use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! defined( 'ALTAPAY_PLUGIN_FILE' ) ) {
	define( 'ALTAPAY_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'ALTAPAY_DB_VERSION' ) ) {
	define( 'ALTAPAY_DB_VERSION', '336' );
}

if ( ! defined( 'ALTAPAY_PLUGIN_VERSION' ) ) {
	define( 'ALTAPAY_PLUGIN_VERSION', '3.7.3' );
}

// Include the autoloader, so we can dynamically include the rest of the classes.
require_once __DIR__ . DIRECTORY_SEPARATOR . 'vendor/autoload.php';

/**
 * Init AltaPay settings and gateway
 *
 * @return void
 */
function init_altapay_settings() {
	// Make sure WooCommerce and WooCommerce gateway is enabled and loaded
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	$settings = new Core\AltapaySettings();

	$objTokenControl = new Core\AltapayTokenControl();
	$objTokenControl->registerHooks();

	$objOrderStatus = new Core\AltapayOrderStatus();
	$objOrderStatus->registerHooks();

	$objReconciliationData = new Core\AltapayReconciliation();
	$objReconciliationData->registerHooks();

	$objApplePay = new Core\ApplePay();
	$objApplePay->registerHooks();

	$altapayDbVersion = get_site_option( 'altapay_db_version' );

	if ( empty( $altapayDbVersion ) || $altapayDbVersion !== ALTAPAY_DB_VERSION ) {
		Core\AltapayPluginInstall::createReconciliationDataTable();
	}

	$altapay_terminal_classes_recreated = get_site_option( 'altapay_terminal_classes_recreated' );

	if ( empty( $altapay_terminal_classes_recreated ) ) {
		Core\AltapaySettings::recreateTerminalData( $settings );
	}

	load_plugin_textdomain( 'altapay', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

}

/**
 * Add the Gateway to WooCommerce.
 *
 * @param array<int, string> $methods
 *
 * @return array<int, string>
 */
function altapay_add_gateway( $methods ) {
	// Get enabled terminals
	$terminals = json_decode( get_option( 'altapay_terminals_enabled' ) );

	if ( empty( $terminals ) ) {
		return $methods;
	}

	$helper = new Helpers\AltapayHelpers();

	$pluginDir = plugin_dir_path( __FILE__ );
	// Directory for the terminals
	$terminalDir = $pluginDir . 'terminals/';
	// Temp dir in case the one from above is not writable
	$tmpDir = sys_get_temp_dir();
	// Load Terminal information
	$terminalInfo = json_decode( get_option( 'altapay_terminals' ) );
	if ( $terminals ) {
		foreach ( $terminals as $terminal ) {
			$tokenStatus   = '';
			$subscriptions = false;
			$terminalName  = $terminal;
			foreach ( $terminalInfo as $term ) {
				if ( $term->key === $terminal ) {
					$terminalName    = $term->name;
					$natures         = array_column( json_decode( json_encode( $term->nature ), true ), 'Nature' );
					$gateway_methods = array();
					if ( ! empty( $term->methods ) ) {
						$gateway_methods = array_column( json_decode( json_encode( $term->methods ), true ), 'Method' );
					}
					if ( ! count( array_diff( $natures, array( 'CreditCard' ) ) )
						 or ( in_array( 'MobilePayAcquirer', $gateway_methods ) or in_array( 'MobilePayOnlineAcquirer', $gateway_methods ) )
						 or in_array( 'VippsAcquirer', $gateway_methods )
					) {
						$subscriptions = true;
						$tokenStatus   = 'CreditCard';
					} elseif ( in_array( 'CreditCard', $natures, true ) ) {
						$tokenStatus = 'CreditCard';
					}
					$current_settings = get_option( 'woocommerce_altapay_' . $terminal . '_settings', array() );
					if ( empty( $current_settings ) ) {
						// Set default terminal logo
						$terminalSettings = array(
							'enabled'        => 'yes',
							'title'          => str_replace( '-', ' ', $term->name ),
							'description'    => '',
							'payment_action' => 'authorize',
							'payment_icon'   => Core\AltapaySettings::getPaymentMethodIcon( $term->identifier ?? '' ),
							'currency'       => get_option( 'woocommerce_currency' ),
						);

						// Update option with configuration settings
						update_option(
							'woocommerce_altapay_' . $terminal . '_settings',
							$terminalSettings,
							'yes'
						);

					}
				}
			}

			$terminal_file_blocks = $terminalDir . $terminal . '.blocks.class.php';
			$helper->create_file_from_tpl(
				$terminal_file_blocks,
				$pluginDir . 'views/paymentClassBlocks.tpl',
				array(
					'{key}'         => $terminal,
					'{terminal_id}' => strtolower( $terminal ),
				)
			);

			$terminal_js_file_blocks = $terminalDir . strtolower( $terminal ) . '.blocks.js';
			$helper->create_file_from_tpl(
				$terminal_js_file_blocks,
				$pluginDir . 'views/blocksJs.tpl',
				array(
					'{key}'         => $terminal,
					'{name}'        => $terminalName,
					'{terminal_id}' => strtolower( $terminal ),
				)
			);

			// Check if file exists
			$terminal_class_file     = $terminalDir . $terminal . '.class.php';
			$terminal_class_file_tmp = $tmpDir . '/' . $terminal . ALTAPAY_PLUGIN_VERSION . '.class.php';

			if ( file_exists( $terminal_class_file ) ) {
				require_once $terminal_class_file;
				$methods[] = 'WC_Gateway_' . $terminal;
			} elseif ( file_exists( $terminal_class_file_tmp ) ) {
				require_once $terminal_class_file_tmp;
				$methods[] = 'WC_Gateway_' . $terminal;
				// Create file
				$template = file_get_contents( $pluginDir . 'views/paymentClass.tpl' );
				// Replace patterns
				$content = str_replace( array( '{key}', '{name}', '{tokenStatus}', '{supportSubscriptions}' ), array( $terminal, $terminalName, $tokenStatus, $subscriptions ), $template );

				$ok = @file_put_contents( $terminal_class_file, $content );
				if ( $ok === false ) {
					set_transient( 'terminals_directory_error', 'show' );
				}
			} else {
				// Create file
				$template = file_get_contents( $pluginDir . 'views/paymentClass.tpl' );
				// Replace patterns
				$content = str_replace( array( '{key}', '{name}', '{tokenStatus}', '{supportSubscriptions}' ), array( $terminal, $terminalName, $tokenStatus, $subscriptions ), $template );

				$ok = @file_put_contents( $terminal_class_file, $content );
				// Check if terminals folder is writable or use tmp as fallback
				if ( $ok === false ) {
					set_transient( 'terminals_directory_error', 'show' );
				} else {
					file_put_contents( $terminal_class_file_tmp, $content );
				}
			}
		}
	}
	return $methods;
}

/**
 * Load payment template
 *
 * @param string $template Template to load.
 *
 * @return string
 */
function altapay_page_template( $template ) {
	$callbackPages = array(
		'altapay_payment_page'           => 'altapay-payment-form.php',
		'altapay_callback_redirect_page' => 'altapay-callback-redirect.php',
	);

	foreach ( $callbackPages as $optionKey => $templateFile ) {
		// Get payment form page id
		$pageId = esc_attr( get_option( $optionKey ) );

		if ( $pageId && is_page( $pageId ) ) {
			// Make sure the template is only loaded from AltaPay.
			// Load template override
			$locatedTemplate = locate_template( $templateFile );
			// If no template override load template from plugin
			$template = $locatedTemplate ?: __DIR__ . '/views/' . $templateFile;
			break;
		}
	}

	return $template;
}

/**
 * Register meta box for order details page
 *
 * @return void
 */
function altapayAddMetaBoxes() {
	$screen = wc_get_container()->get( CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled()
		? wc_get_page_screen_id( 'shop-order' )
		: 'shop_order';

	add_meta_box(
		'altapay-actions-side',
		__( 'AltaPay Payment Actions', 'altapay' ),
		'altapay_meta_box_side',
		$screen,
		'side',
		'high'
	);

	add_meta_box(
		'altapay-order-reconciliation-identifier',
		__( 'Reconciliation Details', 'altapay' ),
		'altapay_order_reconciliation_identifier_meta_box',
		$screen,
		'normal'
	);
}

/**
 * Meta box display callback
 *
 * @param WP_Post $post Current post object.
 * @return void
 */
function altapay_meta_box_side( $post_or_order_object ) {
	global $post;
	$order_post = $post;

	$order = ( $post_or_order_object instanceof WP_Post ) ? wc_get_order( $post_or_order_object->ID ) : $post_or_order_object;

	if ( ! $order ) {
		return;
	}

	$paymentMethod = $order->get_payment_method();

	if ( strpos( $paymentMethod, 'altapay' ) === false && strpos( $paymentMethod, 'valitor' ) === false ) {
		return;
	}

	$txnID        = $order->get_transaction_id();
	$agreement_id = $order->get_meta( '_agreement_id' );

	if ( $txnID || $agreement_id ) {
		$settings = new Core\AltapaySettings();
		$login    = $settings->altapayApiLogin();

		if ( ! $login || is_wp_error( $login ) ) {
			echo '<p><b>' . __( 'Could not connect to AltaPay!', 'altapay' ) . '</b></p>';
			return;
		}

		if ( ! $txnID ) {
			$txnID = $agreement_id;
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
		$args     = array(
			'posts_per_page' => -1,
			'post_type'      => 'altapay_captures',
			'post_status'    => 'captured',
			'meta_query'     => array(
				array(
					'key'     => 'qty_captured',
					'compare' => 'EXISTS',
				),
				array(
					'key'     => 'item_id',
					'compare' => 'EXISTS',
				),
				array(
					'key'     => 'wc_order_id',
					'value'   => $order->get_id(),
					'compare' => '=',
				),
			),
		);
		$captures = new WP_Query( $args );

		$itemsCaptured = array();

		if ( $captures->have_posts() ) {
			while ( $captures->have_posts() ) {
				$captures->the_post();
				if ( isset( $itemsCaptured[ get_post_meta( get_the_ID(), 'item_id', true ) ] ) ) {
					$itemsCaptured[ get_post_meta( get_the_ID(), 'item_id', true ) ] += get_post_meta( get_the_ID(), 'qty_captured', true );
				} else {
					$itemsCaptured[ get_post_meta( get_the_ID(), 'item_id', true ) ] = get_post_meta( get_the_ID(), 'qty_captured', true );
				}
			}

			wp_reset_postdata();
		}

		$post = $order_post;

		if ( $payments ) {
			foreach ( $payments as $pay ) {
				$reserved = $pay->ReservedAmount;
				$captured = $pay->CapturedAmount;
				$refunded = $pay->RefundedAmount;
				$status   = $pay->TransactionStatus;
				$type     = $pay->AuthType;

				if ( $status === 'released' ) {
					echo '<strong>' . __( 'Payment Released.', 'altapay' ) . '</strong>';
				} else {
					$charge = $reserved - $captured - $refunded;
					if ( $charge <= 0 ) {
						$charge = 0.00;
					}
					$blade = new Helpers\AltapayHelpers();
					echo $blade->loadBladeLibrary()->run(
						'tables.index',
						array(
							'reserved'           => $reserved,
							'captured'           => $captured,
							'charge'             => $charge,
							'refunded'           => $refunded,
							'order'              => $order,
							'items_captured'     => $itemsCaptured,
							'transaction_status' => $status,
							'transaction_type'   => $type,
							'transaction_id'     => $txnID,
						)
					);
				}
			}
		}
	} else {
		esc_html_e( 'Order got no transaction', 'altapay' );
	}
}

/**
 * Meta box display callback for AltaPay reconciliation identifier
 *
 * @param WP_Post $post Current post object.
 * @return void
 */
function altapay_order_reconciliation_identifier_meta_box( $post_or_order_object ) {
	$order = ( $post_or_order_object instanceof WP_Post ) ? wc_get_order( $post_or_order_object->ID ) : $post_or_order_object;

	if ( ! $order ) {
		return;
	}

	$paymentMethod = $order->get_payment_method();

	if ( strpos( $paymentMethod, 'altapay' ) === false && strpos( $paymentMethod, 'valitor' ) === false ) {
		return;
	}

	$reconciliation             = new Core\AltapayReconciliation();
	$reconciliation_identifiers = $reconciliation->getReconciliationData( (int) $order->get_id() );

	if ( ! empty( $reconciliation_identifiers ) ) {
		?>
		<table width="100%" cellspacing="0" cellpadding="10">
		   <thead>
		   <tr>
			   <th align="left" width="40%">Reconciliation Identifier</th>
			   <th align="left" width="60%">Type</th>
		   </tr>
		   </thead>
			<tbody>
				<?php
				foreach ( $reconciliation_identifiers  as $identifier ) {
					?>
						<tr>
							<td><?php echo $identifier['identifier']; ?></td>
							<td><?php echo $identifier['transactionType']; ?></td>
						</tr>
						<?php
				}
				?>
			</tbody>
		</table>
		<?php
	}
}

/**
 * Add scripts for the order details page
 *
 * @return void
 */
function altapayActionJavascript() {
	$screen    = get_current_screen();
	$screen_id = $screen ? $screen->id : '';

	// Check if WooCommerce order
	if ( $screen_id === wc_get_page_screen_id( 'shop-order' ) ) {
		$order   = wc_get_order();
		$post_id = ! empty( $order ) ? $order->get_id() : '';
		?>
			<script type="text/javascript">
				let Globals = <?php echo wp_json_encode( array( 'postId' => $post_id ) ); ?>;
			</script>
			<?php
			wp_enqueue_script(
				'captureScript',
				plugin_dir_url( __FILE__ ) . 'assets/js/capture.js',
				array( 'jquery' ),
				'1.1.0',
				true
			);
			wp_enqueue_script(
				'refundScript',
				plugin_dir_url( __FILE__ ) . 'assets/js/refund.js',
				array( 'jquery' ),
				'1.1.0',
				true
			);
			wp_enqueue_script(
				'releaseScript',
				plugin_dir_url( __FILE__ ) . 'assets/js/release.js',
				array( 'jquery' ),
				'1.1.0',
				true
			);
	}
}

/**
 * Method for creating payment page on call back
 *
 * @return void
 */
function createAltapayPaymentPageCallback() {
	global $userID;

	// Create page data
	$page = array(
		'post_type'    => 'page',
		'post_content' => '',
		'post_parent'  => 0,
		'post_author'  => $userID,
		'post_status'  => 'publish',
		'post_title'   => 'AltaPay payment form',
	);

	// Create page
	$pageID = wp_insert_post( $page );
	if ( $pageID == 0 ) {
		echo wp_json_encode(
			array(
				'status'  => 'error',
				'message' => __(
					'Error creating page, try again',
					'altapay'
				),
			)
		);
	} else {
		echo wp_json_encode(
			array(
				'status'  => 'ok',
				'message' => __( 'Payment page created', 'altapay' ),
				'page_id' => $pageID,
			)
		);
	}
	wp_die();
}

/**
 * Method for handling capture action and call back
 *
 * @return void
 */
function altapayCaptureCallback() {
	$utilMethods  = new Util\UtilMethods();
	$settings     = new Core\AltapaySettings();
	$orderID      = isset( $_POST['order_id'] ) ? sanitize_text_field( wp_unslash( $_POST['order_id'] ) ) : '';
	$amount       = isset( $_POST['amount'] ) ? (float) wp_unslash( $_POST['amount'] ) : '';
	$subscription = false;
	if ( ! $orderID || ! $amount ) {
		wp_send_json_error( array( 'error' => 'error' ) );
	}

	// Load order
	$order            = wc_get_order( $orderID );
	$txnID            = $order->get_transaction_id();
	$reconciliationId = wp_generate_uuid4();

	if ( class_exists( 'WC_Subscriptions_Order' ) && ( wcs_order_contains_subscription( $orderID, 'parent' ) || wcs_order_contains_subscription( $orderID, 'renewal' ) ) ) {
		$txnID        = $order->get_meta( '_agreement_id' );
		$subscription = true;
	}

	if ( $txnID ) {

		$login = $settings->altapayApiLogin();
		if ( ! $login ) {
			wp_send_json_error( array( 'error' => 'Could not login to the Merchant API:' ) );
		} elseif ( is_wp_error( $login ) ) {
			wp_send_json_error( array( 'error' => wp_kses_post( $login->get_error_message() ) ) );
		}

		$postOrderLines = isset( $_POST['orderLines'] ) ? wp_unslash( $_POST['orderLines'] ) : '';

		$orderLines       = array();
		$selectedProducts = array(
			'itemList' => array(),
			'itemQty'  => array(),
		);
		if ( $postOrderLines ) {
			foreach ( $postOrderLines as $productData ) {
				if ( $productData[1]['value'] > 0 ) {
					$selectedProducts['itemList'][]                          = intval( $productData[0]['value'] );
					$selectedProducts['itemQty'][ $productData[0]['value'] ] = $productData[1]['value'];
				}
			}

			$orderLines = $utilMethods->createOrderLines( $order, $selectedProducts );
		}

		$response    = null;
		$rawResponse = null;
		try {
			if ( $subscription === true ) {
				$api = new ChargeSubscription( $settings->getAuth() );
			} else {
				$api = new CaptureReservation( $settings->getAuth() );
			}

			$api->setOrderLines( $orderLines );
			$api->setAmount( round( $amount, 2 ) );
			$api->setTransaction( $txnID );
			$api->setReconciliationIdentifier( $reconciliationId );
			$response    = $api->call();
			$rawResponse = $api->getRawResponse();
		} catch ( InvalidArgumentException $e ) {
			error_log( 'InvalidArgumentException ' . $e->getMessage() );
			wp_send_json_error( array( 'error' => 'InvalidArgumentException: ' . $e->getMessage() ) );
		} catch ( ResponseHeaderException $e ) {
			error_log( 'ResponseHeaderException ' . $e->getMessage() );
			wp_send_json_error( array( 'error' => 'ResponseHeaderException: ' . $e->getMessage() ) );
		} catch ( \Exception $e ) {
			error_log( 'Exception ' . $e->getMessage() );
			wp_send_json_error( array( 'error' => 'Error: ' . $e->getMessage() ) );
		}

		if ( $response && ! in_array( $response->Result, array( 'Success', 'Open' ), true ) ) {
			wp_send_json_error( array( 'error' => __( 'Could not capture reservation' ) ) );
		}

		$charge   = 0;
		$reserved = 0;
		$captured = 0;
		$refunded = 0;

		if ( $rawResponse ) {
			$body = $rawResponse->getBody();
			// Update comments if capture fail
			$xml = new SimpleXMLElement( $body );
			if ( (string) $xml->Body->Result === 'Error' || (string) $xml->Body->Result === 'Failed' ) {
				// log to history
				$order->add_order_note( __( 'Capture failed: ' . (string) $xml->Body->MerchantErrorMessage, 'Altapay' ) );
				wp_send_json_error( array( 'error' => (string) $xml->Body->MerchantErrorMessage ) );
			}

			if ( $subscription === true ) {
				$xmlToJson          = wp_json_encode( $xml->Body->Transactions );
				$jsonToArray        = json_decode( $xmlToJson, true );
				$latest_transaction = $settings->getLatestTransaction( $jsonToArray['Transaction'], 'subscription_payment' );
				$transaction        = $jsonToArray['Transaction'][ $latest_transaction ];
				$transaction_id     = $transaction['TransactionId'];
				$order->set_transaction_id( $transaction_id );
			} else {
				$xmlToJson      = wp_json_encode( $xml->Body->Transactions->Transaction );
				$transaction    = json_decode( $xmlToJson, true );
				$transaction_id = $transaction['TransactionId'];
			}

			if ( $response->Result === 'Success' ) {
				$reconciliation = new Core\AltapayReconciliation();

				$identifier = $transaction['ReconciliationIdentifiers']['ReconciliationIdentifier'];

				if ( count( $identifier ) == count( $identifier, COUNT_RECURSIVE ) ) {
					$reconciliation->saveReconciliationIdentifier(
						(int) $orderID,
						$transaction_id,
						$identifier['Id'],
						$identifier['Type']
					);
				} else {
					foreach ( $identifier as $val ) {
						$reconciliation->saveReconciliationIdentifier(
							(int) $orderID,
							$transaction_id,
							$val['Id'],
							$val['Type']
						);
					}
				}
			}

			$reserved = (float) $transaction['ReservedAmount'];
			$captured = (float) $transaction['CapturedAmount'];
			$refunded = (float) $transaction['RefundedAmount'];
			$charge   = $reserved - $captured - $refunded;
		}

		if ( $charge <= 0 ) {
			$charge = 0.00;
		}

		if ( $response->Result === 'Success' ) {
			foreach ( $selectedProducts['itemQty'] as $itemId => $qty ) {
				$args = array(
					'post_type'   => 'altapay_captures',
					'post_status' => 'captured',
					'meta_input'  => array(
						'wc_order_id' => $orderID,
					),
				);

				$post_id = wp_insert_post( $args );

				if ( ! is_wp_error( $post_id ) ) {
					update_post_meta( $post_id, 'qty_captured', $qty );
					update_post_meta( $post_id, 'item_id', $itemId );
				}
			}

			$order->update_meta_data( '_captured', true );
			$order->save();
			$orderNote = __( 'Order captured: amount: ' . $amount, 'Altapay' );
			$order->add_order_note( $orderNote );
		} elseif ( $response->Result === 'Open' ) {
			$orderNote = 'The payment is pending an update from the payment provider.';
			$order->update_status( 'on-hold', $orderNote );
		}

		$noteHtml = '<li class="note system-note"><div class="note_content"><p>' . $orderNote . '</p></div><p class="meta"><abbr class="exact-date">' . sprintf(
			__(
				'added on %1$s at %2$s',
				'woocommerce'
			),
			date_i18n( wc_date_format(), time() ),
			date_i18n( wc_time_format(), time() )
		) . '</abbr></p></li>';

		wp_send_json_success(
			array(
				'captured'   => $captured,
				'reserved'   => $reserved,
				'refunded'   => $refunded,
				'chargeable' => round( $charge, 2 ),
				'note'       => $noteHtml,
				'message'    => __( 'Payment Captured.', 'altapay' ),
			)
		);
	}

	wp_die();
}

/**
 * Method for handling refund action and call back
 *
 * @return void
 */
function altapayRefundCallback() {
	$orderID = sanitize_text_field( wp_unslash( $_POST['order_id'] ) );
	$amount  = isset( $_POST['amount'] ) ? (float) wp_unslash( $_POST['amount'] ) : 0;

	$refund = altapayRefundPayment( $orderID, $amount, null, true );

	if ( $refund['success'] === true ) {
		wp_send_json_success( $refund );
	} else {
		$error = $refund['error'] ?? 'Error in the refund operation.';
		wp_send_json_error( array( 'error' => __( $error, 'altapay' ) ) );
	}

	wp_die();
}

/**
 * @param int        $orderID Order ID.
 * @param float|null $amount Refund amount.
 * @param string     $reason Refund reason.
 * @param boolean    $isAjax
 * @return array
 */
function altapayRefundPayment( $orderID, $amount, $reason, $isAjax ) {

	$utilMethods        = new Util\UtilMethods();
	$settings           = new Core\AltapaySettings();
	$orderLines         = array();
	$wcRefundOrderLines = array();

	if ( ! $orderID || ! $amount ) {
		return array( 'error' => 'Invalid order' );
	}

	// Load order
	$order = wc_get_order( $orderID );
	$txnID = $order->get_transaction_id();
	if ( ! $txnID ) {
		return array( 'error' => 'Invalid order' );
	}

	$login = $settings->altapayApiLogin();
	if ( ! $login ) {
		return array( 'error' => 'Could not login to the Merchant API:' );
	} elseif ( is_wp_error( $login ) ) {
		return array( 'error' => wp_kses_post( $login->get_error_message() ) );
	}

	$postOrderLines = isset( $_POST['orderLines'] ) ? wp_unslash( $_POST['orderLines'] ) : '';
	if ( $postOrderLines ) {
		$selectedProducts = array(
			'itemList' => array(),
			'itemQty'  => array(),
		);
		foreach ( $postOrderLines as $productData ) {
			if ( $productData[1]['value'] > 0 ) {
				$selectedProducts['itemList'][]                          = intval( $productData[0]['value'] );
				$selectedProducts['itemQty'][ $productData[0]['value'] ] = $productData[1]['value'];
			}
		}
		$orderLines         = $utilMethods->createOrderLines( $order, $selectedProducts );
		$wcRefundOrderLines = $utilMethods->createOrderLines( $order, $selectedProducts, true );
	}

	// Refund the amount OR release if a refund is not possible
	$releaseFlag      = false;
	$refundFlag       = false;
	$auth             = $settings->getAuth();
	$error            = '';
	$reconciliationId = wp_generate_uuid4();

	if ( $order->get_meta( '_captured' ) || $order->get_meta( '_refunded' ) || $order->get_remaining_refund_amount() > 0 ) {
		$api = new RefundCapturedReservation( $auth );
		$api->setAmount( round( $amount, 2 ) );
		$api->setOrderLines( $orderLines );
		$api->setTransaction( $txnID );
		$api->setReconciliationIdentifier( $reconciliationId );

		try {
			$response = $api->call();
			if ( $response->Result === 'Success' ) {
				// Create refund in WooCommerce
				if ( $isAjax ) {
					// Restock the items
					$refundOperation = wc_create_refund(
						array(
							'amount'         => $amount,
							'reason'         => $reason,
							'order_id'       => $orderID,
							'line_items'     => $wcRefundOrderLines,
							'refund_payment' => false,
							'restock_items'  => true,
						)
					);

					if ( $refundOperation instanceof WP_Error ) {
						$order->add_order_note( __( $refundOperation->get_error_message(), 'altapay' ) );
					} else {
						$order->add_order_note( __( 'Refunded products have been re-added to the inventory', 'altapay' ) );
					}
				}
				$order->update_meta_data( '_refunded', true );
				$order->save();
				$refundFlag = true;

				$transaction = json_decode( wp_json_encode( $response->Transactions ), true );
				$transaction = reset( $transaction );

				$reconciliation = new Core\AltapayReconciliation();
				$reconciliation->saveReconciliationIdentifier( (int) $orderID, $transaction['TransactionId'], $reconciliationId, 'refunded' );
			} elseif ( strtolower( $response->Result ) === 'open' ) {
				$note = __( 'Payment refund is in progress.', 'altapay' );
				$order->add_order_note( $note );
				return array(
					'message' => $note,
					'success' => true,
				);
			} else {
				$error = $response->MerchantErrorMessage;
			}
		} catch ( ResponseHeaderException $e ) {
			$error = 'Response header exception ' . $e->getMessage();
		} catch ( \Exception $e ) {
			$error = 'Response header exception ' . $e->getMessage();
		}
	} elseif ( $order->get_remaining_refund_amount() == 0 ) {

		try {
			$api = new ReleaseReservation( $auth );
			$api->setTransaction( $txnID );
			/** @var ReleaseReservationResponse $response */
			$response = $api->call();
			if ( $response->Result === 'Success' ) {
				$releaseFlag = true;
				$refundFlag  = true;
				$order->update_meta_data( '_released', true );
				$order->save();
			} else {
				$error = $response->MerchantErrorMessage;
			}
		} catch ( ResponseHeaderException $e ) {
			$error = 'Response header exception ' . $e->getMessage();
		}
	}

	if ( ! $refundFlag ) {
		$order->add_order_note( __( 'Refund failed: ' . $error, 'altapay' ) );
		return array( 'error' => $error );
	} else {
		$reserved = 0;
		$captured = 0;
		$refunded = 0;
		try {
			$api = new Payments( $auth );
			$api->setTransaction( $txnID );
			$payments = $api->call();
		} catch ( Exception $e ) {
			return array( 'error' => 'Response exception ' . $e->getMessage() );
		}
		if ( $payments ) {
			foreach ( $payments as $pay ) {
				$reserved += $pay->ReservedAmount;
				$captured += $pay->CapturedAmount;
				$refunded += $pay->RefundedAmount;
			}
		}

		$charge = $reserved - $captured - $refunded;
		if ( $charge <= 0 ) {
			$charge = 0.00;
		}

		if ( $releaseFlag ) {
			$order->add_order_note( __( 'Order released', 'altapay' ) );
			$orderNote = 'The order has been released';
		} else {
			$order->add_order_note( __( 'Order refunded: amount ' . $amount, 'altapay' ) );
			$orderNote = 'Order refunded: amount ' . $amount;
		}
		$noteHtml = '<li class="note system-note"><div class="note_content"><p>' . $orderNote . '</p></div><p class="meta"><abbr class="exact-date">' . sprintf(
			__(
				'added on %1$s at %2$s',
				'woocommerce'
			),
			date_i18n( wc_date_format(), time() ),
			date_i18n( wc_time_format(), time() )
		) . '</abbr></p></li>';

		return array(
			'captured'   => $captured,
			'reserved'   => $reserved,
			'refunded'   => $refunded,
			'chargeable' => round( $charge, 2 ),
			'note'       => $noteHtml,
			'message'    => __( 'Payment Refunded.', 'altapay' ),
			'success'    => true,
		);
	}
}

/**
 * Method for handling release action and call back
 *
 * @return void
 */
function altapayReleasePayment() {
	$orderID  = sanitize_text_field( wp_unslash( $_POST['order_id'] ) );
	$order    = new WC_Order( $orderID );
	$txnID    = $order->get_transaction_id();
	$settings = new Core\AltapaySettings();
	$captured = 0;
	$reserved = 0;
	$refunded = 0;

	$login = $settings->altapayApiLogin();
	if ( ! $login ) {
		wp_send_json_error( array( 'error' => 'Could not login to the Merchant API:' ) );
	} elseif ( is_wp_error( $login ) ) {
		wp_send_json_error( array( 'error' => wp_kses_post( $login->get_error_message() ) ) );
	}
	try {
		$auth = $settings->getAuth();
		$api  = new Payments( $auth );
		$api->setTransaction( $txnID );

		$payments = $api->call();
		foreach ( $payments as $pay ) {
			$reserved += $pay->ReservedAmount;
			$captured += $pay->CapturedAmount;
			$refunded += $pay->RefundedAmount;
		}

		if ( ! $captured && ! $refunded ) {
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
				wp_send_json_success( array( 'message' => __( 'Payment Released.', 'altapay' ) ) );
			}
		} else {
			$order->add_order_note( __( 'Release failed: ' . $response->MerchantErrorMessage, 'altapay' ) );
			wp_send_json_error( array( 'error' => $response->MerchantErrorMessage ) );
		}
	} catch ( Exception $e ) {
		wp_send_json_error( array( 'error' => 'Could not login to the Merchant API: ' . $e->getMessage() ) );
	}
	wp_die();
}

/**
 * Perform functionality required during plugin activation
 */
function altapayPluginActivation() {
	Core\AltapayPluginInstall::createPluginTables();
	Core\AltapayPluginInstall::setDefaultCheckoutFormStyle();
	Core\AltapayPluginInstall::createCallbackRedirectPage();
}

/**
 * Halt the AltaPay callback form request if checksum not valid
 */
function validate_checksum_altapay_callback_form() {

	if ( is_page( get_option( 'altapay_payment_page' ) ) ) {
		$checksum = isset( $_POST['checksum'] ) ? sanitize_text_field( wp_unslash( $_POST['checksum'] ) ) : '';

		$altapay_helper = new Helpers\AltapayHelpers();
		$secret         = wc_get_payment_gateway_by_order( $_POST['shop_orderid'] )->secret;
		if ( ! empty( $checksum ) and ! empty( $secret ) and $altapay_helper->calculateChecksum( $_POST, $secret ) !== $checksum ) {
			error_log( 'checksum validation failed' );
			exit;
		}
	}
}

// Declare plugin compatibility with WooCommerce High Performance Order Storage (HPOS)
add_action(
	'before_woocommerce_init',
	function() {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

/**
 * Registers WooCommerce Blocks integration.
 *
 * @return void
 */
function altapay_wc_checkout_block_support() {

	if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
		// Get enabled terminals
		$terminals = json_decode( get_option( 'altapay_terminals_enabled' ) );

		if ( empty( $terminals ) ) {
			return;
		}

		foreach ( $terminals as $terminal ) {
			$terminal_file_blocks = plugin_dir_path( __FILE__ ) . 'terminals/' . $terminal . '.blocks.class.php';

			if ( file_exists( $terminal_file_blocks ) ) {
				require_once $terminal_file_blocks;
				add_action(
					'woocommerce_blocks_payment_method_type_registration',
					function( PaymentMethodRegistry $payment_method_registry ) use ( $terminal ) {
						$terminal_class_name = 'WC_Gateway_' . $terminal . '_Blocks_Support';
						$payment_method_registry->register( new $terminal_class_name() );
					}
				);
			}
		}
	}
}

/**
 * Add custom class to the body
 *
 * @param $classes
 *
 * @return array
 */
function altapay_add_custom_class_to_body( $classes ) {
	if ( basename( get_permalink() ) === 'altapay-payment-form' ) {
		$classes[] = 'altapay-checkout-page';
	}

	return $classes;
}

add_filter( 'body_class', 'altapay_add_custom_class_to_body', 10, 1 );

/**
 * Enqueue styles for checkout blocks
 *
 * @return void
 */
function altapay_checkout_blocks_style() {
	wp_enqueue_style(
		'altapay-block-style',
		plugin_dir_url( __FILE__ ) . 'assets/css/blocks.css',
	);
}

add_action( 'wp_enqueue_scripts', 'altapay_checkout_blocks_style' );
add_action( 'woocommerce_blocks_loaded', 'altapay_wc_checkout_block_support' );
add_filter( 'woocommerce_payment_gateways', 'altapay_add_gateway' );
register_activation_hook( __FILE__, 'altapayPluginActivation' );
add_action( 'add_meta_boxes', 'altapayAddMetaBoxes' );
add_action( 'wp_ajax_altapay_capture', 'altapayCaptureCallback' );
add_action( 'wp_ajax_altapay_refund', 'altapayRefundCallback' );
add_action( 'wp_ajax_altapay_release_payment', 'altapayReleasePayment' );
add_action( 'admin_enqueue_scripts', 'altapayActionJavascript' );
add_action( 'altapay_checkout_order_review', 'woocommerceOrderReview' );
add_action( 'wp_ajax_create_altapay_payment_page', 'createAltapayPaymentPageCallback' );
add_filter( 'template_include', 'altapay_page_template', 99 );
add_action( 'plugins_loaded', 'init_altapay_settings', 0 );
add_action( 'template_redirect', 'validate_checksum_altapay_callback_form' );
