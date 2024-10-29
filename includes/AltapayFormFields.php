<?php
/**
 * AltaPay module for WooCommerce
 *
 * Copyright © 2020 AltaPay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Adds the form fields for the payment gateway.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$files = array( 'default' => __( 'Default - No logo', 'altapay' ) );
foreach ( new DirectoryIterator( dirname( __DIR__, 1 ) . '/assets/images/payment_icons' ) as $fileInfo ) {
	if ( $fileInfo->isDot() || ! $fileInfo->isFile() ) {
		continue;
	}
	$files[ $fileInfo->getFilename() ] = $fileInfo->getFilename();
}

$formSettings = array(
	'enabled'        => array(
		'title'   => __( 'Enable/Disable', 'woocommerce' ),
		'type'    => 'checkbox',
		'default' => 'yes',
		'label'   => __( ' ', 'altapay' ),
	),
	'title'          => array(
		'title'       => __( 'Title', 'woocommerce' ),
		'type'        => 'text',
		'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
		'default'     => __( 'AltaPay', 'woocommerce' ),
		'desc_tip'    => true,
	),
	'description'    => array(
		'title'       => __( 'Description', 'woocommerce' ),
		'description' => __( 'Payment method description that the customer will see on your checkout.', 'woocommerce' ),
		'type'        => 'textarea',
		'default'     => '',
		'desc_tip'    => true,
	),
	'payment_action' => array(
		'title'       => __( 'Payment action', 'altapay' ),
		'description' => __( 'Make payment authorized or authorized and captured', 'altapay' ),
		'type'        => 'select',
		'options'     => array(
			'authorize'         => __( 'Authorize Only', 'altapay' ),
			'authorize_capture' => __( 'Authorize and Capture', 'altapay' ),
		),
		'default'     => '',
		'desc_tip'    => true,
	),
	'payment_icon'   => array(
		'title'       => __( 'Icon', 'altapay' ),
		'description' => __( 'Select image icons to display on checkout page', 'altapay' ),
		'type'        => 'multiselect',
		'options'     => $files,
		'default'     => '',
		'desc_tip'    => true,
	),
	'secret' => array(
		'title'       => __( 'Secret', 'altapay' ),
		'type'        => 'text',
		'description' => __( 'Add the payment method secret as defined in the AltaPay payment gateway.', 'altapay' ),
		'default'     => '',
		'desc_tip'    => true,
	),
	'is_apple_pay'   => array(
		'title'    => __( 'Is Apple Pay?', 'altapay' ),
		'type'     => 'checkbox',
		'label'    => __( 'Check if the terminal is for Apple Pay payments', 'altapay' ),
		'default'  => 'no',
		'desc_tip' => true,
	),
	'apple_pay_label'	=> array(
		'title'       => __( 'Apple Pay form label', 'altapay' ),
		'type'        => 'text',
		'description' => __( 'This controls the label shown on Apple Pay popup window', 'woocommerce' ),
		'default'     => __( 'AltaPay', 'woocommerce' ),
		'desc_tip'    => true,
	),
	'apple_pay_supported_networks'	=> array(
		'title'       => __( 'Apple Pay Supported Networks', 'altapay' ),
		'description' => __( 'The payment networks the merchant supports.', 'altapay' ),
		'type'        => 'multiselect',
		'options'     => array(
			'visa'       => __( 'Visa', 'altapay' ),
			'masterCard' => __( 'Mastercard', 'altapay' ),
			'amex'       => __( 'Amex', 'altapay' ),
		),
		'default'     => array( 'visa', 'masterCard', 'amex' ),
		'desc_tip'    => true,
	),
);

if ( $tokenStatus === 'CreditCard' ) {
	$formSettings['token_control'] = array(
		'title'    => __( 'Token Control', 'altapay' ),
		'type'     => 'checkbox',
		'label'    => __( 'Enable Customer Token Control', 'altapay' ),
		'default'  => 'no',
		'desc_tip' => true,
	);
}

$formSettings = apply_filters_deprecated( 'altapay_gateway_payments_settings_with_token_control', array( $formSettings ), '3.3.8', 'altapay_gateway_payments_settings' );

return apply_filters( 'altapay_gateway_payments_settings', $formSettings );
