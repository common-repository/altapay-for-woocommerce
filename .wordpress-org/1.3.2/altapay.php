<?php
/*
Plugin Name: AltaPay for WooCommerce - Payments less complicated
Plugin URI: http://www.altapay.com
Description: Payment Gateway to use with Wordpress WooCommerce
Version: 1.3.1
Author: AltaPay
Author URI: http://www.altapay.com
*/
 
if (!defined( 'ABSPATH')) {
	exit; // Exit if accessed directly
}
// Load Altapay API Library
require_once( __DIR__ . '/lib/PensioMerchantAPI.class.php' );
require(__DIR__.'/util.php');
// Altapay settings page
class Altapay_settings {

	private $plugin_options_key = 'altapay-settings';

	// Construct
	public function __construct() {
        // Load localization files
        add_action( 'init', array($this, 'altapay_localization_init') );
        // Add admin menu
        add_action( 'admin_menu', array($this, 'altapay_settings_menu'), 60);
        // Register settings
        add_action( 'admin_init', array($this, 'altapay_register_settings') );
        // Add settings link on plugin page
        add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_action_links') );
		// Order completed interceptor:
		add_action( 'woocommerce_order_status_completed', array($this, 'altapay_order_status_completed') );

		add_action('admin_notices', array($this, 'login_error'));
		add_action('admin_notices', array($this, 'capture_failed'));
		add_action('admin_notices', array($this, 'capture_warning'));
    }

	function altapay_order_status_completed ($order_id) {

		$this->startSession();

		// Load order
		$order = new WC_Order ($order_id);
		$txnid = $order->get_transaction_id();

		if (! $txnid) {
			return;
		}

		// Login to Altapay gateway:
		$api = new PensioMerchantAPI (esc_attr (get_option('altapay_gateway_url')), esc_attr (get_option('altapay_username')), esc_attr (get_option('altapay_password')), null);

		try {
			$api->login();
		}
		catch (Exception $e) {
			$_SESSION['altapay_login_error'] = $e->getMessage();
			return;
		}

		$payment = $api->getPayment($txnid);

		if (! $payment) {
			return;
		}

		$payments = $payment->getPayments();
		$pay = $payments[0];

		if ($pay->getCapturedAmount() == 0) { // Order wasn't captured and must be captured now

			$amount = $pay->getReservedAmount(); // Amount to capture
			$salesTax = $order->get_total_tax();
			//TODO: add orderLines, if is set to parse them
			$orderLines = array(array());
			$capture_result = $api->captureReservation($txnid, $amount, $orderLines, $salesTax);

			if(! $capture_result->wasSuccessful()) {

				$order->add_order_note (__('Capture failed: ' . $capture_result->getMerchantErrorMessage(), 'Altapay')); // log to history

				$this->save_capture_failed_message ('Capture failed for order ' . $order_id . ': ' . $capture_result->getMerchantErrorMessage());

				return;

			}
			else {

				update_post_meta ($order_id, '_captured', true);

				$order->add_order_note (__('Order captured: amount: ' . $amount, 'Altapay'));

			}

		}
		else {

			$this->save_capture_warning ('This order was already fully or partially captured: ' . $order_id);

		}

	}

	function save_capture_warning ($newMessage) {

		if (isset($_SESSION ['altapay_capture_warning'])) {
			$message = $_SESSION ['altapay_capture_warning'] . "<br/>";
		}
		else {
			$message = "";
		}

		$_SESSION ['altapay_capture_warning'] = $message . $newMessage;

	}

	function save_capture_failed_message ($newMessage) {

		if (isset($_SESSION ['altapay_capture_failed'])) {
			$message = $_SESSION ['altapay_capture_failed'] . "<br/>";
		} else {
			$message = "";
		}

		$_SESSION ['altapay_capture_failed'] = $message . $newMessage;

	}

	function show_user_message ($field, $type, $message = '') {

		$this->startSession();

		if (! isset($_SESSION [$field])) {
			return;
		}

		echo "<div class='$type notice'> <p>$message $_SESSION[$field]</p> </div>";

		unset($_SESSION [$field]);

	}

	function login_error () {

		$this->show_user_message('altapay_login_error', 'error', 'Could not login to the Merchant API: ');

	}

	function capture_failed () {

		$this->show_user_message('altapay_capture_failed', 'error');

	}

	function capture_warning () {

		$this->show_user_message('altapay_capture_warning', 'update-nag');

	}

	function startSession () {

		if (session_id() === '') {
			session_start();
		}

	}

    public function add_action_links ( $links ) {
		$newlink = array(
	 		'<a href="' . admin_url( 'admin.php?page=altapay-settings' ) . '">Settings</a>',
	 	);
		return array_merge( $links, $newlink );
	}

    public function altapay_localization_init() {
        load_plugin_textdomain('altapay', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }
    
    public function altapay_settings_menu() {
        add_submenu_page( 'woocommerce', 'Altapay Settings', 'Altapay Settings', 'manage_options', $this->plugin_options_key, array( $this, 'altapay_settings' ) );
    }
    
    public function altapay_register_settings() {
        register_setting( 'altapay-settings-group', 'altapay_gateway_url' );
		register_setting( 'altapay-settings-group', 'altapay_username' );
		register_setting( 'altapay-settings-group', 'altapay_password' );
		register_setting( 'altapay-settings-group', 'altapay_payment_page' );
		register_setting( 'altapay-settings-group', 'altapay_terminals_enabled', 'json_encode' );
    }
    
    public function altapay_settings() {    
    	$gateway_url = esc_attr( get_option('altapay_gateway_url') );
    	$username = esc_attr( get_option('altapay_username') );
    	$password = esc_attr( get_option('altapay_password') );
    	$payment_page = esc_attr( get_option('altapay_payment_page') );
    	$terminals = json_decode( get_option('altapay_terminals') );
    	$terminals_enabled = json_decode( get_option('altapay_terminals_enabled') );
    	if (!$terminals_enabled || $terminals_enabled == 'null') {
    		$terminals_enabled = array();
    	}
    	
    ?>
		<div class="wrap">
		<h2><?php echo __('Altapay Settings', 'altapay'); ?></h2>
		<?php 
		if ( version_compare(PHP_VERSION, '5.4', '<') ) {
			wp_die( sprintf( 'Altapay for WooCommerce requires PHP 5.4 or higher. You’re still on %s.',PHP_VERSION ) );
		} else {
		?>	
		<form method="post" action="options.php">
			<?php settings_fields( 'altapay-settings-group' ); ?>    
			<?php do_settings_sections( 'altapay-settings-group' ); ?>
			<table class="form-table">
		    <tr valign="top">
		    <th scope="row"><?php echo __('Gateway URL', 'altapay') ?></th>
		    <td><input class="input-text regular-input" type="text" name="altapay_gateway_url" value="<?php echo $gateway_url ?>" /></td>
		    </tr>         
		    <tr valign="top">
		    <th scope="row"><?php echo __('Username', 'altapay') ?></th>
		    <td><input class="input-text regular-input" type="text" name="altapay_username" value="<?php echo $username ?>" /></td>
		    </tr>       
		    <tr valign="top">
		    <th scope="row"><?php echo __('Password', 'altapay') ?></th>
		    <td><input class="input-text regular-input" type="password" name="altapay_password" value="<?php echo $password ?>" /></td>
		    </tr>
		    <tr valign="top">
		    <th scope="row"><?php echo __('Payment page', 'altapay') ?></th>
		    <td>
		    <?php 
		    // If payment page not set show create button
		    if (!$payment_page) { ?>
		    	<input type="button" id="create_altapay_payment_page" name="create_page" value="Create Page" class="button button-primary" />
		    	<span id="payment-page-msg"></span>
		    	<input type="hidden" name="altapay_payment_page" id="altapay_payment_page" value="">
		    <?php } else {
				// Validate if payment exists by looping trough the pages
				$pages = get_pages();
				foreach ( $pages as $page ) {
					if ($page->ID == $payment_page) {
						$exists = true;
						$page_title = $page->post_title;
						$page_id = $page->ID;
					}
				}
			
				if ($exists) {
				?>
				<input type="hidden" name="altapay_payment_page" value="<?php echo $payment_page; ?>"><?php echo $page_id; ?>: <?php echo $page_title; ?>
				<?php 
				} else { // Show text and create button ?>
					<p><?php echo __('Payment do not exists anymore, create new', 'altapay') ?></p>
					<input type="button" name="create_altapay_payment_page" value="Create Page" class="button button-primary" />
				<?php } ?>
			<?php } ?>		    
		    </td>
		    </tr>
		    <?php if ($terminals) { ?>
		    <tr valign="top">
		    <th scope="row" colspan="2"><h2><?php echo __('Terminals', 'altapay') ?></h2></th>
		    </tr>
		    <?php foreach ($terminals as $terminal) { ?>
			<tr valign="top">
			<th scope="row"><?php echo $terminal->name; ?></th>
			<td><input type="checkbox" name="altapay_terminals_enabled[]" value="<?php echo $terminal->key; ?>" <?php if (in_array($terminal->key, $terminals_enabled)) { ?>checked="checked"<?php } ?> /></td>
			</tr>
			<?php } ?>
		    <tr>
				<td>
					<a href="admin.php?page=wc-settings&amp;tab=checkout"><?= __('Go to WooCommerc payment methods', 'altapay') ?></a>
				</td>
			</tr>
		    <?php } ?>
	   	 	</table>  	 	
			<?php submit_button(); ?>
	  	</form>
	  	<script type="text/javascript">
			jQuery(document).ready(function($) {
				jQuery('#create_altapay_payment_page').unbind().on('click', function(e) {
					var data = {
						'action': 'create_altapay_payment_page',
					};

					// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
					jQuery.post(ajaxurl, data, function(response) {
						result = jQuery.parseJSON(response);
						if (result.status == 'ok') {
							jQuery('#altapay_payment_page').val(result.page_id);
							jQuery('#payment-page-msg').text(result.message);
							jQuery('#create_altapay_payment_page').attr('disabled','disabled');
						} else {
							jQuery('#payment-page-msg').text(result.message);
						}
					});
			
				});
			});
			</script> 	  	
		<?php 
	 		if ($gateway_url && $username) {
	 			$this->altapay_refresh_connection_form();
	 	 	} 
	 	 }
	 	?>   	 		
		</div>
    <?php
    }
    
    private function altapay_refresh_connection_form() {
    	$terminals = get_option('altapay_terminals');
    	if (!$terminals) { ?>
		<p><?php echo __('Terminals missing, please click - Refresh connection','altapay') ?></p>    
    <?php } else { ?>
    	<p><?php echo __('Click below to re-create terminal data','altapay') ?></p>
    <?php } ?>
    <form method="post" action="#refresh_connection">
   		<input type="hidden" value="true" name="refresh_connection">
        <input type="submit" value="<?php echo __('Refresh Connection', 'altapay') ?>" name="refresh-connection" class="button">
  	</form>
   	<?php
   		// TODO Make use of wordpress notice and error handling
   		
   		// Test connection
	   	if (isset($_POST['refresh_connection'])) {
	   		$api = new PensioMerchantAPI(esc_attr( get_option('altapay_gateway_url') ), esc_attr( get_option('altapay_username') ), esc_attr( get_option('altapay_password') ), null);
			$response = $api->login();
			if (!$response->wasSuccessful()) {
				echo '<p>'.__('Could not connect to Altapay', 'altapay').'</p>';	
			} else {
				echo '<p>'.__('Connection OK', 'altapay').'</p>';
				?>
				<script>
					setTimeout("location.reload()", 1500);
				</script>
				<?php
				
				// Get list of terminals information
				$terminal_info = $api->getTerminals();
				$terminals = array();
				$terms = $terminal_info->getTerminals();
				foreach ($terms as $terminal) {
					$terminals[] = array(
						'key' => str_replace(' ','_', $terminal->getTitle()),
						'name' => $terminal->getTitle(),
					);
				}		
				
				update_option('altapay_terminals', json_encode($terminals));			
			}
	   	}
    }
    
}

// Init altapay settings and	 gateway
function init_altapay_settings() {
	
	// Make sure WooCommerce and WooCommerce gateway is enabled and loaded
	if (!class_exists( 'WC_Payment_Gateway' )) { 
		return; 
	}
	
	$settings = new Altapay_settings();

	// Add Gateway to WooCommerce if enabled
    if ( json_decode( get_option('altapay_terminals_enabled') ) ) { 
		add_filter( 'woocommerce_payment_gateways', 'altapay_add_gateway' );
	}
	
	// Define default functions using traits
	trait AltapayMaster {
	
		public function init_form_fields() {
			// Define form setting fields
			$this->form_fields = array(
				'enabled' => array(
					'title' => __( 'Enable/Disable', 'woocommerce' ),
					'type' => 'checkbox',
					'label' => __( 'Enable AltaPay', 'altapay' ),
					'default' => 'yes'
				),
				'title' => array(
					'title' => __( 'Title', 'woocommerce' ),
					'type' => 'text',
					'description' => __( 'Title to show during checkout.', 'altapay' ),
					'default' => __( 'AltaPay', 'woocommerce' ),
					'desc_tip'      => true,
				),
				'description' => array(
					'title' => __( 'Message', 'woocommerce' ),
					'description' => __( 'Message to show during checkout.', 'altapay' ),
					'type' => 'textarea',
					'default' => '',
				),
				'payment_action' => array(
					'title' => __( 'Payment action', 'woocommerce' ),
					'description' => __( 'Make payment authorized or authorized and captured', 'altapay' ),
					'type' => 'select',
					'options' => array(
						'authorize' => __('Authorize Only', 'altapay'),
						'authorize_capture' => __('Authorize and Capture', 'altapay'),
					),
					'default' => '',
				),
				'currency' => array(
					'title' => __( 'Currency', 'altapay' ),
					'type' => 'select',
					'description' => __( 'Select the currency does this terminal work with' ),
					'options' => get_woocommerce_currencies(),
					'default' => $this->default_currency,
				),
			);
		}
		
		public function process_payment( $order_id ) {
			global $woocommerce;
			$order = new WC_Order( $order_id );
			
			// Return goto payment url
			return array(
				'result' => 'success',
				'redirect' => $order->get_checkout_payment_url( true ),
			);
		}
		
		public function api_login() {
			$api = new PensioMerchantAPI(esc_attr( get_option('altapay_gateway_url') ), esc_attr( get_option('altapay_username') ), esc_attr( get_option('altapay_password') ), null);
			$response = $api->login();
			if(!$response->wasSuccessful()) {
				return new WP_Error( 'error', "Could not login to the Merchant API: ".$response->getErrorMessage());	
			}
			return $api;
		}
		
		public function is_available() {
			// Check if payment method is enabled
			if ( 'yes' !== $this->enabled ) {
            	return false;
        	}
         	
        	//Check on payment currency
        	$active_currency = get_woocommerce_currency();
        	
        	if ($active_currency != $this->currency) {
        		return false;
        	}
        	
        	return true;
		}

		public function scheduled_subscription_payment( $amount, $renewal_order ) {
			try {
				if( $amount == 0 ) {
					$renewal_order->payment_complete();
					return;
				}

				$transaction_id = '';
				if ( wcs_order_contains_renewal( $renewal_order->id ) ) {
            		//$parent_order = wcs_get_subscriptions_for_renewal_order( $renewal_order->id ); 
            		$parent_order_id = WC_Subscriptions_Renewal_Order::get_parent_order_id( $renewal_order->id );
        		}        		

				$orderinfo = new WC_Order($parent_order_id);
				$transaction_id = $orderinfo->get_transaction_id();
			
				if( !$transaction_id ) {				
					// Set subscription payment as failure
					$renewal_order->update_status( 'failed', __( 'Altapay could not locate transaction ID', 'altapay' ) );
					return false;
				}

				$api = $this->api_login();
						
				$result = $api->chargeSubscription( $transaction_id, $amount );
				
				if ($result->wasSuccessful()) {
					$renewal_order->payment_complete();
				} else {
					$renewal_order->update_status( 'failed', sprintf( __( 'Altapay payment declined: %s', 'altapay' ), $result->getErrorMessage() ) );
				}
				
			} catch (Exception $e) {
				$renewal_order->update_status( 'failed', sprintf( __( 'Altapay payment declined: %s', 'altapay' ), $e->getMessage() ) );
			}
		}
		
		public function altapay_get_currency_code( $number ) {
			$codes = array(
				'004' => 'AFA','012' => 'DZD','020' => 'ADP','031' => 'AZM','032' => 'ARS','036' => 'AUD','044' => 'BSD','048' => 'BHD',
				'050' => 'BDT','051' => 'AMD','052' => 'BBD','060' => 'BMD','064' => 'BTN','068' => 'BOB','072' => 'BWP','084' => 'BZD',
				'096' => 'BND','100' => 'BGL','108' => 'BIF','124' => 'CAD','132' => 'CVE','152' => 'CLP','156' => 'CNY','170' => 'COP',
				'188' => 'CRC','191' => 'HRK','192' => 'CUP','196' => 'CYP','203' => 'CZK','208' => 'DKK','214' => 'DOP','218' => 'ECS',
				'230' => 'ETB','232' => 'ERN','233' => 'EEK','238' => 'FKP','242' => 'FJD','262' => 'DJF','270' => 'GMD','288' => 'GHC',
				'292' => 'GIP','320' => 'GTQ','324' => 'GNF','328' => 'GYD','340' => 'HNL','344' => 'HKD','532' => 'ANG','533' => 'AWG',
				'578' => 'NOK','624' => 'GWP','752' => 'SEK','756' => 'CHF','784' => 'AED','818' => 'EGP','826' => 'GBP','840' => 'USD',
				'973' => 'AOA','974' => 'BYR','975' => 'BGN','976' => 'CDF','977' => 'BAM','978' => 'EUR','981' => 'GEL','983' => 'ECV',
				'984' => 'BOV','986' => 'BRL','990' => 'CLF'
			);	
			return $codes[$number];
		}
		
	}
	
	// Add gateways to WooCommerce.
	function altapay_add_gateway($methods) {
		// Get enabled terminals
		$terminals = json_decode( get_option('altapay_terminals_enabled') );
		if ($terminals) {
			foreach ($terminals as $terminal) {
				// Load Terminal information
				$terminal_info = json_decode( get_option('altapay_terminals') );
				$terminal_name = $terminal;
				foreach ($terminal_info as $term) {
					if ($term->key == $terminal) {
						$terminal_name = $term->name;
					}
				}
				
				// Check if file exists
				$dir = plugin_dir_path( __FILE__ );
				$tmpdir = sys_get_temp_dir();
				$path = $dir.'terminals/'.$terminal.'.class.php';
				$tmppath = $tmpdir.'/'.$terminal.'.class.php';
				
				if (file_exists($path)) {
					require_once ($path);
					$methods[$terminal] = 'WC_Gateway_'.$terminal;	
				} elseif (file_exists($tmppath)) {
					require_once ($tmppath);
					$methods[$terminal] = 'WC_Gateway_'.$terminal;	
				} else {									
					// Create file
					$template = file_get_contents($dir.'templates/payment_class.tpl');
					
					// Replace patterns
					$content = str_replace(array('{key}','{name}'),array($terminal,$terminal_name), $template);
					$terminal_dir = $dir.'terminals';
					$filename = $dir.'terminals/'.$terminal.'.class.php';
					
					// Check if terminals folder is writable or use tmp as fallback
					if (is_writable($terminal_dir)) {
						file_put_contents($filename, $content);
					} else {						
						$filename = $tmpdir.'/'.$terminal.'.class.php';
						file_put_contents($filename, $content);
					}
				}
			}
		}	
		return $methods;
	}
	
	/// Make sure payment page form loads payment template	
	add_filter( 'template_include', 'altapay_page_template', 99 );
	function altapay_page_template( $template ) {
		// Get payment form page id
		$payment_form_page_id = esc_attr( get_option('altapay_payment_page') );
		if ( is_page( $payment_form_page_id ) && $payment_form_page_id !='') {
			// Make sure the template is only loaded from Altapay
			//if ($_SERVER['REMOTE_ADDR'] == '77.66.40.133') {
				// Load template override
				$template = locate_template( 'altapay-payment-form.php' );
	
				// If no template override load template from plugin
				if ( !$template ) {
					$template = dirname(__FILE__) . '/templates/altapay-payment-form.php';
				}
			//}
		}

		return $template;
	}

	// Capture function
	add_action( 'add_meta_boxes', 'altapay_add_meta_boxes' );
	add_action( 'wp_ajax_altapay_capture', 'altapay_capture_callback' );
	add_action( 'wp_ajax_altapay_refund', 'altapay_refund_callback' );
	add_action( 'admin_footer', 'altapay_action_javascript' );	
	// Create payment page function
	add_action( 'wp_ajax_create_altapay_payment_page', 'create_altapay_payment_page_callback' );
	
	
	function altapay_add_meta_boxes() {
		global $post, $woocommerce;

		if ($post->post_type != 'shop_order') {
			return true;
		}

		// Load order
		$order = new WC_Order($post->ID);		
		$payment_method = $order->payment_method;
	
		// Only show on altapay orders
		if (strpos($payment_method,'altapay') !== false) {
			add_meta_box( 
				'altapay-actions', 
				__( 'AltaPay actions', 'altapay' ), 
				'altapay_meta_box', 
				'shop_order', 
				'normal'
			);
		}
		return true;
	}	
	
	function altapay_meta_box( $post ) {
		global $woocommerce;
	
		// Load order
		$order = new WC_Order($post->ID);
		$orderItems = $order->get_items();
		$txnid = $order->get_transaction_id();

		if ($txnid) {
            // Validate is transactions exists in Altapay
            $api = new PensioMerchantAPI(esc_attr( get_option('altapay_gateway_url') ), esc_attr( get_option('altapay_username') ), esc_attr( get_option('altapay_password') ), null);
            $response = $api->login();
            if(!$response->wasSuccessful()) {
                return new WP_Error( 'error', "Could not login to the Merchant API: ".$response->getErrorMessage());
            }

            $payment = $api->getPayment($txnid);

            if ($payment) {
                $payments = $payment->getPayments();
                foreach ($payments as $pay) {
                    $reserved = $pay->getReservedAmount();
                    $captured = $pay->getCapturedAmount();
                    $refunded = $pay->getRefundedAmount();
                    $status = $pay->getCurrentStatus();

                    if ($status == 'released') {
                        echo '<br /><b>'.__('Payment released', 'altapay').'</b>';
                    } else {
                        $charge = $reserved - $captured - $refunded;
                        if ($charge <= 0) $charge = 0.00;
                        ?>

                        <div class="capture-status" style="margin-bottom:10px;"></div>
                        <div>Payment reserved: <span class="payment-reserved"><?php echo number_format($reserved, 2)?></span><?php echo $order->get_order_currency()?></div>
                        <div>Payment captured: <span class="payment-captured"><?php echo number_format($captured, 2)?></span><?php echo $order->get_order_currency()?></div>
                        <div>Payment refunded: <span class="payment-refunded"><?php echo number_format($refunded, 2)?></span><?php echo $order->get_order_currency()?></div>
                        <div>Payment chargeable: <span class="payment-chargeable"><?php echo number_format($charge, 2)?></span><?php echo $order->get_order_currency()?></div>

                        <br><br>
                            <div style="overflow-x:auto;">
                                <div class="table-responsive">
                                    <table style="border: 2px solid #cccccc;">
                                        <tbody>
                                            <tr style="font-weight: bold; border-collapse: collapse; padding: 15px;">
                                                <td>Product name</td>
                                                <td>Price with tax</td>
                                                <td>Price without tax</td>
                                                <td>Ordered</td>
                                                <td>Quantity</td>
                                                <td>Total amount</td>
                                            </tr>
                                        </tbody>
                            <?php
                                    foreach ($orderItems as $itemData) {
	                                    $product = wc_get_product($itemData['product_id']);
	                                    $qty = $itemData->get_quantity();
	                                    $priceExcTaxPerUnit = (float)number_format($itemData->get_total() / $qty, 2, '.', '');
	                                    $totalTaxPerUnit = (float)number_format($itemData->get_total_tax() / $qty, 2, '.', '');
	                                    $incTax = wc_price($priceExcTaxPerUnit + $totalTaxPerUnit);
	                                    $excTax = wc_price($priceExcTaxPerUnit);
	                                    $sku = $product->get_sku();
	                                    $totalIncTax = wc_price($itemData->get_total()+$itemData->get_total_tax());
                                        echo '<tr class="ap-orderlines">';
                                            echo '<td style="display:none"><input class="form-control ap-order-product-sku" name="productID" type="text" value="'.$sku.'"/></td>';
                                            echo '<td>' . $itemData->get_product()->get_name() . '</td>';
                                            echo '<td class="ap-orderline-unit-price">' . $incTax . '</td>';
                                            echo '<td>' . $excTax . '</td>';
                                            echo '<td class="ap-orderline-max-quantity">' . $qty . '</td>';
                                            echo '<td><input class="form-control ap-order-modify" name="qty" value="'.$qty.'" type="number"/></td>';
                                            echo '<td>' . $totalIncTax . '</td>';
                                        echo '</tr>';
                                    }
                                        // Shipping method
	                                if ($order->get_shipping_total() <> 0 || $order->get_shipping_tax() <> 0) {
		                                $order_shipping_methods = $order->get_shipping_methods();
		                                foreach ($order_shipping_methods as $ordershipping_key => $ordershippingmethods) {
			                                $shipping_id = $ordershippingmethods['method_id'];
		                                }
		                                $totalIncTax = wc_price((float)number_format($order->get_shipping_total() + $order->get_shipping_tax(), 2, '.', ''));
		                                $excTax = wc_price($order->get_shipping_total());
		                                echo '<tr class="ap-orderlines">';
                                            echo '<td style="display:none"><input class="form-control ap-order-product-sku" name="productID" type="text" value="'.$shipping_id.'"/></td>';
                                            echo '<td>' . $order->get_shipping_method(). '</td>';
                                            echo '<td class="ap-orderline-unit-price">' . $totalIncTax . '</td>';
                                            echo '<td>' . $excTax . '</td>';
                                            echo '<td class="ap-orderline-max-quantity">' . 1 . '</td>';
                                            echo '<td><input class="form-control ap-order-modify" name="qty" value="1" type="number"/></td>';
                                            echo '<td>' . $totalIncTax . '</td>';
		                                echo '</tr>';
                                    }
                            ?>
                                    </table>
                                </div>
                                <br />
                                <div class="row row-ap">
                                    <div class="col-lg-12">
                                        <label for="allow-orderlines" class="form-check-label">
                                            <input name="allow-orderlines" type="checkbox" id="ap-allow-orderlines" checked="checked">
                                            Send order lines
                                        </label>
                                    </div>
                                </div>
                            </div>
                        <?php
                        $toBeCaptured = (float)number_format($reserved - $captured, 2, '.', '');
                        $toBeRefunded = (float)number_format($captured - $refunded, 2, '.', '');
                        if ($charge <= 0) {
                            if ($reserved == $refunded) {
                                echo '<br /><b>'.__('Order refunded', 'altapay').'</b>';
                            } else {
	                            echo '<br /><input type="text" pattern="[0-9]+(\.[0-9]{0,2})?%?"  id="capture-amount" name="capture-amount" value="'.$toBeRefunded.'" placeholder="Amount" style="width: 75px; margin-right: 5px;" />';
	                            echo '<a id="altapay_refund" class="button button-primary">Refund</a>';
	                            echo '<span class="loader" style="display:none; margin-left:5px;"><img width="20px" src="'.plugins_url( 'images/ajax-loader.gif', __FILE__ ).'" /></span>';
                            }
                        } else if($captured > 0 && $captured < $reserved) {
	                        echo '<br><div>';
	                        echo '<input type="text" pattern="[0-9]+(\.[0-9]{0,2})?%?" id="capture-amount" name="capture-amount" value="'.($toBeRefunded > 0 ? $toBeRefunded : $toBeCaptured).'" placeholder="Amount" style="width: 75px; margin-right: 5px;" />';
	                        echo '<a id="altapay_capture" class="button button-primary">Capture</a>';
	                        echo '<span class="loader" style="display:none; margin-left:5px;"><img width="20px" src="'.plugins_url( 'images/ajax-loader.gif', __FILE__ ).'" /></span>';
	                        echo '<a id="altapay_refund" class="button button-primary">Refund</a>';
	                        echo '<span class="loader" style="display:none; margin-left:5px;"><img width="20px" src="'.plugins_url( 'images/ajax-loader.gif', __FILE__ ).'" /></span>';
	                        echo '</div>';
                        } else {
	                        echo '<br /><input type="text" pattern="[0-9]+(\.[0-9]{0,2})?%?"  id="capture-amount" name="capture-amount" value="'.$toBeCaptured.'" placeholder="Amount" style="width: 75px; margin-right: 5px;" />';
	                        echo '<a id="altapay_capture" class="button button-primary">Capture</a>';
	                        echo '<span class="loader" style="display:none; margin-left:5px;"><img width="20px" src="'.plugins_url( 'images/ajax-loader.gif', __FILE__ ).'" /></span>';
                        }
                    }
                }
            }
		} else {
			echo __('Order got no transaction', 'altapay');
		}
	}
		
	function altapay_action_javascript() { 
		global $post;
		if(isset($post->ID)) {
			// Check if woocommerce order
			if ($post->post_type == 'shop_order') {
				
			$order = new WC_Order($post->ID);
		?>
			<script type="text/javascript">
			jQuery(document).ready(function($) {
			    //Util
                var altapay = {
                    // Perform capture
                    capture: function(element){
                        var amount = parseFloat($('#capture-amount').val());
                        var productsArrData = [];
                        if($("#ap-allow-orderlines").attr("checked") === "checked")
                        {
                            $('.ap-orderlines:has(input)').each(function() {
                                var productArrData = [];
                                $('input', this).each(function() {
                                    if(this.getAttribute("name") == "productID") {
                                        if ($(this).val() == '') {
                                            alert("One of the products does not have SKU defined !");
                                            return;
                                        }
                                        productArrData.push({name: 'productId', value:$(this).val()});
                                    }else if(this.getAttribute("name") == "qty") {
                                        productArrData.push({name: 'productQty', value:$(this).val()});
                                    }
                                });
                                productsArrData.push(productArrData);
                            });
                        }
                        var data = {
                            'action': 'altapay_capture',
                            'order_id': <?php echo $post->ID; ?>,
                            'amount': amount,
                            'orderLines': productsArrData
                        };

                        // since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
                        jQuery.post(ajaxurl, data, function(response) {
                            result = jQuery.parseJSON(response);
                            if (result.status == 'ok') {
                                jQuery('#altapay-actions .inside .capture-status').html('<b>Payment captured.</b>')
                                jQuery('.payment-reserved').text(result.reserved);
                                jQuery('.payment-captured').text(result.captured);
                                jQuery('.payment-refunded').text(result.refunded);
                                jQuery('.payment-chargeable').text(result.chargeable);
                                jQuery('ul.order_notes').prepend(result.note);
                            } else {
                                jQuery('#altapay-actions .inside .capture-status').html('<b>Capture failed: '+result.message+'</b>');
                            }
                            jQuery('.loader').css('display','none');
                        });
                    },

                    // Perform refund
                    refund: function(element){
                        var namespace = this;
                        // by default GoodWill refund is disabled
                        var goodwillrefund = 'no';
                        var amount = parseFloat($('#capture-amount').val());
                        var productsArrData = [];
                        if($("#ap-allow-orderlines").attr("checked") === "checked")
                        {
                            $('.ap-orderlines:has(input)').each(function() {
                                var productArrData = [];
                                $('input', this).each(function() {
                                    if(this.getAttribute("name") == "productID") {
                                        if ($(this).val() == '') {
                                            alert("One of the products does not have SKU defined !");
                                            return;
                                        }
                                        productArrData.push({name: 'productId', value:$(this).val()});
                                    }else if(this.getAttribute("name") == "qty") {
                                        productArrData.push({name: 'productQty', value:$(this).val()});
                                    }
                                });
                                productsArrData.push(productArrData);
                            });
                        }
                        var data = {
                            'action': 'altapay_refund',
                            'order_id': <?php echo $post->ID; ?>,
                            'amount': amount,
                            'orderLines': productsArrData,
                            'goodwillrefund':goodwillrefund
                        };

                        // since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
                        jQuery.post(ajaxurl, data, function(response) {
                            result = jQuery.parseJSON(response);
                            if (result.status == 'ok') {
                                jQuery('#altapay-actions .inside .capture-status').html('<b>Payment refunded.</b>')
                                jQuery('.payment-reserved').text(result.reserved);
                                jQuery('.payment-captured').text(result.captured);
                                jQuery('.payment-refunded').text(result.refunded);
                                jQuery('.payment-chargeable').text(result.chargeable);
                                jQuery('ul.order_notes').prepend(result.note);
                            } else {
                                jQuery('#altapay-actions .inside .capture-status').html('<b>Refund failed: '+result.message+'</b>');
                            }
                            jQuery('.loader').css('display','none');
                        });
                    },
                    //Recalculation of the amount for capture/refund depending on selected order lines
                    recalculateAmount: function(element){
                        var sum = 0.0000;
                        $.each( $('tr.ap-orderlines'), function( key, value ) {
                            var ordered = parseInt($('.ap-orderline-max-quantity', value).text());
                            var unitprice = parseFloat($('.ap-orderline-unit-price', value).text());
                            var quantity = parseInt($('.ap-order-modify', value).val());
                            // Covering the case when there is no value in the quantity field
                            if (isNaN(quantity)) {
                                quantity = 0;
                            }
                            if((quantity > ordered) || quantity  < 0)
                            {
                                alert("Quantity cannot be negative or more than ordered!", 'AltaPay');
                                if(quantity > ordered)
                                {
                                    quantity = ordered;
                                }
                                else if(quantity < 0)
                                {
                                    quantity = 0;
                                }
                                $('.ap-order-modify', value).val(quantity);
                            }
                            sum = parseFloat((sum + (unitprice*quantity)).toFixed(4));
                        });

                        $('#capture-amount').val(sum);
                    }
                };
                // Handle the actions
				jQuery('#altapay_capture').on('click', function(e) {
                    e.preventDefault();
                    var amount = parseFloat($('#capture-amount').val());
                    if(isNaN(amount)) {
                        alert('The amount cannot be empty or text!');
                        return;
                    }
                    if(confirm('Are you sure you want to capture '+amount.toFixed(2)+' ?')){
                        altapay.capture(this);
                        return;
                    }
                    else{
                        return false;
                    }
				});
                // Refund action here
                jQuery('#altapay_refund').on('click', function(e) {
                    e.preventDefault();
                    var amount = parseFloat($('#capture-amount').val());
                    if(isNaN(amount)) {
                        alert('The amount cannot be empty or text!');
                        return;
                    }
                    if(confirm('Are you sure you want to refund '+amount.toFixed(2)+' ?')){
                        altapay.refund(this);
                        return;
                    }
                    else{
                        return false;
                    }
                });
                // Recalculate the full amount when quantity is changing
                $('.ap-order-modify').click(function(e){
                    e.preventDefault();
                    var element = this;
                    return altapay.recalculateAmount(element);
                });
			});
			</script> 
		<?php
			}
		
		}
	}	
		
	function create_altapay_payment_page_callback() {
		global $user_ID;
		
		// Create page data
		$page = array(
			'post_type' => 'page',
			'post_content' => '',
			'post_parent' => 0,
			'post_author' => $user_ID,
			'post_status' => 'publish',
			'post_title' => 'Altapay payment form',
		);
		
		// Create page
		$page_id = wp_insert_post($page);
		if ($page_id == 0) {  
			echo json_encode( array('status' => 'error', 'message' => __('Error creating page, try again','altapay')) );
		} else {	
			echo json_encode( array('status' => 'ok', 'message' => __('Payment page created','altapay'), 'page_id' => $page_id) );
		}
		wp_die();
	}	
		
	function altapay_capture_callback() {
		global $wpdb;
		$order_id = sanitize_text_field($_POST['order_id']);
		$amount = (float)$_POST['amount'];
	
		if (!$order_id || !$amount) {
			echo json_encode(array('status' => 'error', 'message' => 'error'));
			wp_die();
		} 
	
		// Load order
		$order = new WC_Order($order_id);
		$txnid = $order->get_transaction_id();
		if ($txnid) {
			$api = new PensioMerchantAPI(esc_attr( get_option('altapay_gateway_url') ), esc_attr( get_option('altapay_username') ), esc_attr( get_option('altapay_password') ), null);
			$response = $api->login();
			if(!$response->wasSuccessful()) {
				return new WP_Error( 'error', "Could not login to the Merchant API: ".$response->getErrorMessage());
			}
		$postOrderLines = $_POST['orderLines'];
		if(!empty($postOrderLines)) {
	            $selectedProducts = array(
                                        'skuList'=>array(),
                                        'skuQty'=>array()
                                    );
	            foreach ($postOrderLines as $productData){
	                if ($productData[1]['value'] > 0) {
		                $selectedProducts['skuList'][] = $productData[0]['value'];
		                $selectedProducts['skuQty'][$productData[0]['value']] = $productData[1]['value'];
                    }
	            }
                $orderLines = createOrderLines($order, $selectedProducts);
                $salesTax = 0;
                foreach ($orderLines as $orderLine) {
                    $salesTax += $orderLine['taxAmount'];
                }
            } else {
                $orderLines = array(array());
	            $salesTax = $order->get_total_tax();
            }

			// Capture amount
			$capture_result = $api->captureReservation($txnid, $amount, $orderLines, $salesTax);
			if(!$capture_result->wasSuccessful()) {
				// log to history
				$order->add_order_note( __('Capture failed: ' . $capture_result->getMerchantErrorMessage(), 'Altapay') );
				echo json_encode( array('status' => 'error', 'message' => $capture_result->getMerchantErrorMessage()) );
			} else {		
				// Get payment data
				$payment = $api->getPayment($txnid);
				if ($payment) {
					$payments = $payment->getPayments();
					foreach ($payments as $pay) {
						$reserved = $pay->getReservedAmount();
						$captured = $pay->getCapturedAmount();
						$refunded = $pay->getRefundedAmount();
						$charge = $reserved - $captured - $refunded;
						if ($charge <= 0) $charge = 0.00;
					}
				}
				
				update_post_meta( $order_id, '_captured', true );
				$order_note = __('Order captured: amount: '.$amount, 'Altapay'); 
				$order->add_order_note( $order_note );
				$note_html = '<li class="note system-note"><div class="note_content"><p>'.$order_note.'</p></div><p class="meta"><abbr class="exact-date">'.sprintf( __( 'added on %1$s at %2$s', 'woocommerce' ), date_i18n( wc_date_format(), time() ), date_i18n( wc_time_format(), time() ) ).'</abbr></p></li>';			
				
				echo json_encode( array('status' => 'ok', 'captured' => number_format($captured,2), 'reserved' => number_format($reserved,2), 'refunded' => number_format($refunded,2), 'chargeable' => number_format($charge,2), 'message' => $capture_result, 'note' => $note_html) );
			}
		}

		wp_die(); 
	}
	function altapay_refund_callback() {
		global $wpdb;
		$order_id = sanitize_text_field($_POST['order_id']);
		$amount = (float)$_POST['amount'];

		if (!$order_id || !$amount) {
			echo json_encode(array('status' => 'error', 'message' => 'error'));
			wp_die();
		}

		// Load order
		$order = new WC_Order($order_id);
		$txnid = $order->get_transaction_id();

		if ($txnid) {
			$api = new PensioMerchantAPI(esc_attr( get_option('altapay_gateway_url') ), esc_attr( get_option('altapay_username') ), esc_attr( get_option('altapay_password') ), null);
			$response = $api->login();
			if(!$response->wasSuccessful()) {
				return new WP_Error( 'error', "Could not login to the Merchant API: ".$response->getErrorMessage());
			}
			$postOrderLines = $_POST['orderLines'];
			if(!empty($postOrderLines)) {
				$selectedProducts = array(
					'skuList'=>array(),
					'skuQty'=>array()
				);
				foreach ($postOrderLines as $productData){
					if ($productData[1]['value'] > 0) {
						$selectedProducts['skuList'][] = $productData[0]['value'];
						$selectedProducts['skuQty'][$productData[0]['value']] = $productData[1]['value'];
					}
				}
				$orderLines = createOrderLines($order, $selectedProducts);
			} else {
				$orderLines = array(array());
			}
			// Refund the amount OR release if a refund is not possible
            $releaseFlag = false;
			if (get_post_meta($order_id, '_captured', true) || get_post_meta($order_id, '_refunded', true)) {
				$refund_result = $api->refundCapturedReservation($txnid, $amount, $orderLines);
				if($refund_result->wasSuccessful()) {
					update_post_meta( $order_id, '_refunded', true );
				}
			} else {
                if ($order->get_remaining_refund_amount() == 0) {
	                $refund_result = $api->releaseReservation($txnid);
	                if($refund_result->wasSuccessful()) {
		                $releaseFlag = true;
		                update_post_meta( $order_id, '_released', true );
	                }
                } else {
	                $refund_result = $api->refundCapturedReservation($txnid, $amount, $orderLines);
                    if($refund_result->wasSuccessful()) {
                        update_post_meta( $order_id, '_refunded', true );
                    }
                }
		    }

			if(!$refund_result->wasSuccessful()) {
				$order->add_order_note(__('Refund failed: ' . $refund_result->getMerchantErrorMessage(), 'altapay'));
				echo json_encode(array('status' => 'error', 'message' => $refund_result->getMerchantErrorMessage()));
			} else {
				// Get payment data
				$payment = $api->getPayment($txnid);
				if ($payment) {
					$payments = $payment->getPayments();
					foreach ($payments as $pay) {
						$reserved = $pay->getReservedAmount();
						$captured = $pay->getCapturedAmount();
						$refunded = $pay->getRefundedAmount();
						$charge = $reserved - $captured - $refunded;
						if ($charge <= 0) $charge = 0.00;
					}
				}
				if ($releaseFlag) {
					$order->add_order_note( __('Order released', 'altapay') );
					$orderNote = "The order has been released";
                }else {
					$order->add_order_note( __('Order refunded: amount '.$amount, 'altapay') );
					$orderNote = 'Order refunded: amount '.$amount;

				}
				$note_html = '<li class="note system-note"><div class="note_content"><p>'.$orderNote.'</p></div><p class="meta"><abbr class="exact-date">'.sprintf( __( 'added on %1$s at %2$s', 'woocommerce' ), date_i18n( wc_date_format(), time() ), date_i18n( wc_time_format(), time() ) ).'</abbr></p></li>';
				echo json_encode( array('status' => 'ok', 'captured' => number_format($captured,2), 'reserved' => number_format($reserved,2), 'refunded' => number_format($refunded,2), 'chargeable' => number_format($charge,2), 'message' => $refund_result, 'note' => $note_html) );
            }
		}

		wp_die();
	}
}

// Make sure plugins are loaded before running gateway
add_action( 'plugins_loaded', 'init_altapay_settings', 0 );


