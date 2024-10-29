<?php
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * AltaPay Payments Blocks integration
 */
final class WC_Gateway_{key}_Blocks_Support extends AbstractPaymentMethodType {

    /**
     * The gateway instance.
     *
     * @var WC_Gateway_{key}
     */
    private $gateway;

    /**
     * Payment method name/id/slug.
     *
     * @var string
     */
    protected $name = 'altapay_{terminal_id}';

    /**
     * Initializes the payment method type.
     */
    public function initialize() {
        $this->settings = get_option( 'woocommerce_altapay_{terminal_id}_settings', [] );
        $gateways       = WC()->payment_gateways->payment_gateways();
        $this->gateway  = $gateways[ $this->name ];
    }

    /**
     * Returns if this payment method should be active. If false, the scripts will not be enqueued.
     *
     * @return boolean
     */
    public function is_active() {
        return $this->gateway->is_available();
    }

    /**
     * Returns an array of scripts/handles to be registered for this payment method.
     *
     * @return array
     */
    public function get_payment_method_script_handles() {
        wp_register_script(
            'wc-altapay_{terminal_id}-payments-blocks',
            plugin_dir_url( ALTAPAY_PLUGIN_FILE ) . 'terminals/{terminal_id}.blocks.js',
            array( 'react', 'wc-blocks-data-store', 'wc-blocks-registry', 'wc-settings', 'wp-data', 'wp-html-entities', 'wp-i18n' ),
            '1.0.0',
            true
        );

        return [ 'wc-altapay_{terminal_id}-payments-blocks' ];
    }

    /**
     * Returns an array of key=>value pairs of data made available to the payment methods script.
     *
     * @return array
     */
    public function get_payment_method_data() {

		$payment_method_data = array(
			'title'                   => $this->get_setting( 'title' ),
			'description'             => $this->get_setting( 'description' ),
			'supports'                => array_filter( $this->gateway->supports, array( $this->gateway, 'supports' ) ),
			'icon'                    => $this->get_icons_data(),
			'is_apple_pay'            => $this->gateway->is_apple_pay,
			'applepay_payment_method' => $this->gateway->is_apple_pay === 'yes' ? $this->gateway->id : '',
		);

		if ( $this->gateway->is_apple_pay === 'yes' ) {
			$additional_params = array(
				'ajax_url'                     => admin_url( 'admin-ajax.php' ),
				'nonce'                        => wp_create_nonce( 'apple-pay' ),
				'currency'                     => get_woocommerce_currency(),
				'country'                      => get_option( 'woocommerce_default_country' ),
				'terminal'                     => $this->gateway->terminal,
				'apply_pay_label'              => $this->gateway->apple_pay_label,
				'apple_pay_supported_networks' => $this->gateway->get_option( 'apple_pay_supported_networks' ),
			);

			return array_merge( $payment_method_data, $additional_params );
		}

		return $payment_method_data;
	}

    /**
     * Gets the payment method's icons.
     *
     * @return array The icons array.
     */
    public function get_icons_data() {
        $icons_arr = [];
        $icons = $this->gateway->get_option('payment_icon');
        if ( ! empty( $icons ) and is_array( $icons ) ) {
            foreach ( $icons as $icon ) {
                if ( ! empty( $icon ) and $icon !== 'default' ) {
                    $icons_arr[] = untrailingslashit( plugins_url( '/assets/images/payment_icons/' . $icon, ALTAPAY_PLUGIN_FILE ) );
                }
            }
        } elseif ( ! empty( $icons ) and $icons !== 'default' ) {
            $icons_arr = untrailingslashit( plugins_url( '/assets/images/payment_icons/' . $icons, ALTAPAY_PLUGIN_FILE ) );
        }

        return $icons_arr;
    }
}
