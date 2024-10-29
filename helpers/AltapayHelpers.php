<?php
/**
 * AltaPay module for WooCommerce
 *
 * Copyright © 2020 AltaPay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Altapay\Helpers;

use DateTime;
use AltaPay\vendor\eftec\bladeone;
use WP_Error;

class AltapayHelpers {

	/**
	 * Generates CMS related transaction information.
	 *
	 * @return array list of CMS related information e.g. platform version, plugin version and other info etc.
	 */
	public function transactionInfo() {
		$altapayPluginData = get_file_data(
			dirname( __DIR__, 1 ) . DIRECTORY_SEPARATOR . 'altapay.php',
			array(
				'Version' => 'Version',
				'Name'    => 'Name',
			),
			'altapay'
		);

		$woocommercePluginData = get_file_data(
			dirname( __DIR__, 2 ) . DIRECTORY_SEPARATOR . 'woocommerce' . DIRECTORY_SEPARATOR . 'woocommerce.php',
			array(
				'Version' => 'Version',
			),
			'woocommerce'
		);

		$transactionInfo = array(
			'ecomPlatform'      => 'WordPress',
			'ecomVersion'       => get_bloginfo( 'version' ),
			'ecomPluginName'    => $altapayPluginData['Name'],
			'ecomPluginVersion' => $altapayPluginData['Version'],
			'otherInfo'         => 'storeName-' . get_bloginfo( 'name' ) . ',' . 'wooComm-' . $woocommercePluginData['Version'],
		);
		return $transactionInfo;
	}

	/**
	 * Sets the billing address as shipping address.
	 *
	 * @param  array $customerInfo
	 * @return array|WP_Error
	 */
	public function setShippingAddress( $customerInfo ) {
		$shippingCountry = $customerInfo['shipping_country'];
		$shippingCity    = $customerInfo['shipping_city'];
		$shippingAddress = $customerInfo['shipping_address'];
		$shippingPostal  = $customerInfo['shipping_postal'];
		// Use billing address in case one of the shipping parameters is missing
		if ( ! $shippingCountry || ! $shippingCity || ! $shippingAddress || ! $shippingPostal ) {
			if ( empty( $customerInfo['billing_country'] ) ) {
				// Throw error since the payment cannot be made without shipping country, even for virtual products
				return new WP_Error( 'error', __( 'Shipping country is required', 'altapay' ) );
			}
			$customerInfo['shipping_country'] = $customerInfo['billing_country'];
			$customerInfo['shipping_address'] = $customerInfo['billing_address'];
			$customerInfo['shipping_city']    = $customerInfo['billing_city'];
			$customerInfo['shipping_region']  = $customerInfo['billing_region'];
			$customerInfo['shipping_postal']  = $customerInfo['billing_postal'];
		}

		return $customerInfo;
	}

	/**
	 * Convert date format
	 *
	 * @param string $date
	 * @return string
	 * @throws Exception
	 */
	public function convertDateTimeFormat( $date ) {
		$dateTime = new DateTime( $date );
		return $dateTime->format( 'Y-m-d' );
	}

	/**
	 * Load blade templating library
	 *
	 * @return bladeone\BladeOne
	 */
	public function loadBladeLibrary() {
		$views = dirname( __DIR__, 1 ) . DIRECTORY_SEPARATOR . 'views'; // it uses the folder /views to read the templates
		$cache = dirname( __DIR__, 1 ) . DIRECTORY_SEPARATOR . 'cache'; // it uses the folder /cache to compile the result.

		return new bladeone\BladeOne( $views, $cache, bladeone\BladeOne::MODE_SLOW );
	}

	/**
	 * @param $input_data
	 * @param $shared_secret
	 *
	 * @return string
	 */
	public function calculateChecksum( $input_data, $shared_secret ) {
		$checksum_data = array(
			'amount'       => sanitize_text_field( wp_unslash( $input_data['amount'] ) ),
			'currency'     => sanitize_text_field( wp_unslash( $input_data['currency'] ) ),
			'shop_orderid' => sanitize_text_field( wp_unslash( $input_data['shop_orderid'] ) ),
			'secret'       => $shared_secret,
		);

		ksort( $checksum_data );
		$data = array();
		foreach ( $checksum_data as $name => $value ) {
			$data[] = $name . '=' . $value;
		}

		return md5( join( ',', $data ) );
	}

	/**
	 * @return void
	 */
	public function create_file_from_tpl( $file_path, $template_path, $placeholders ) {
		if ( ! file_exists( $file_path ) ) {
			// Create file
			$template = file_get_contents( $template_path );
			// Replace patterns
			$content = str_replace( array_keys( $placeholders ), array_values( $placeholders ), $template );

			$ok = @file_put_contents( $file_path, $content );

			if ( $ok === false ) {
				set_transient( 'terminals_directory_error', 'show' );
			}
		}
	}
}
