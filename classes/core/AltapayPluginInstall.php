<?php
/**
 * AltaPay module for WooCommerce
 *
 * Copyright © 2020 AltaPay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Altapay\Classes\Core;

class AltapayPluginInstall {

	/**
	 * Create required tables at the time of plugin installation
	 *
	 * @return void
	 */
	public static function createPluginTables() {
		global $wpdb;
		$tableName      = $wpdb->prefix . 'altapayCreditCardDetails';
		$charsetCollate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $tableName (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            userID varchar(200) DEFAULT '' NOT NULL,
            cardBrand varchar(200) DEFAULT '' NOT NULL,
            creditCardNumber varchar(200) DEFAULT '' NOT NULL,
            cardExpiryDate varchar(200) DEFAULT '' NOT NULL,
            ccToken varchar(200) DEFAULT '' NOT NULL,
            PRIMARY KEY  (id)
        ) $charsetCollate;";

		require_once ABSPATH . '/wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Create table to store reconciliation identifiers
	 *
	 * @return void
	 */
	public static function createReconciliationDataTable() {
		global $wpdb;
		$tableName      = $wpdb->prefix . 'altapayReconciliationIdentifiers';
		$charsetCollate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $tableName (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            time datetime NULL default null,
            orderId BIGINT UNSIGNED NOT NULL,
            transactionId varchar(200) DEFAULT '' NOT NULL,
            identifier text DEFAULT '' NOT NULL,
            transactionType varchar(200) DEFAULT '' NOT NULL,
            PRIMARY KEY  (id)
        ) $charsetCollate;";

		require_once ABSPATH . '/wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'altapay_db_version', ALTAPAY_DB_VERSION );

	}

	/**
	 * Set the checkout design of the Credit Card form.
	 *
	 * @return void
	 */
	public static function setDefaultCheckoutFormStyle() {

		if ( empty( trim( get_option( 'altapay_username' ) ) ) and empty( trim( get_option( 'altapay_cc_form_styling' ) ) ) ) {
			update_option( 'altapay_cc_form_styling', 'checkout' );
		}

	}

	/**
	 * Set the checkout design of the Credit Card form.
	 *
	 * @return void
	 */
	public static function createCallbackRedirectPage() {

		if ( empty( trim( get_option( 'altapay_callback_redirect_page' ) ) ) ) {
			$page_data = array(
				'post_status'    => 'publish',
				'post_type'      => 'page',
				'post_author'    => 1,
				'post_title'     => 'AltaPay callback redirect',
				'post_content'   => '',
				'post_parent'    => 0,
				'comment_status' => 'closed',
			);

			$page_id = wp_insert_post( $page_data );

			update_option( 'altapay_callback_redirect_page', $page_id );
		}
	}
}

