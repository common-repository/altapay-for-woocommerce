<?php
/**
 * AltaPay module for WooCommerce
 *
 * Copyright © 2020 AltaPay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Altapay\Classes\Core;

class AltapayReconciliation {

	/**
	 * Register required hooks
	 *
	 * @return void
	 */
	public function registerHooks() {
		add_action( 'manage_posts_extra_tablenav', array( $this, 'addReconciliationExportButton' ), 20, 1 );
		add_action( 'admin_init', array( $this, 'exportReconciliationCSV' ) );
	}

	/**
	 * Add button to Export Reconciliation Data on WooCommerce order list table.
	 *
	 * @param string $which The location of the extra table nav markup: 'top' or 'bottom'.
	 *
	 * @return void
	 */
	public function addReconciliationExportButton( $which ) {
		global $typenow;

		if ( 'shop_order' === $typenow && 'top' === $which ) {
			?>
			<div class="alignleft actions altapay-export-reconciliation-data">
				<button type="submit" name="export_reconciliation_data" class="button button-primary" value="1">
					<?php echo __( 'Export Reconciliation Data', 'altapay' ); ?>
				</button>
				<input type="hidden" name="orders_pagenum" value="<?php echo isset( $_REQUEST['paged'] ) ? absint( $_REQUEST['paged'] ) : 0; ?>>">
			</div>
			<?php
		}
	}

	/**
	 * Save the reconciliation identifier details.
	 *
	 * @param int    $orderId
	 * @param string $transactionId
	 * @param string $identifier
	 * @param string $type
	 *
	 * @return void
	 */
	public function saveReconciliationIdentifier( $orderId, $transactionId, $identifier, $type ) {
		global $wpdb;

		$record_exists = $wpdb->get_var(
			$wpdb->prepare( "SELECT id from {$wpdb->prefix}altapayReconciliationIdentifiers where orderId = %d and identifier = %s", $orderId, $identifier )
		);

		if ( $record_exists ) {
			return;
		}

		$wpdb->insert(
			$wpdb->prefix . 'altapayReconciliationIdentifiers',
			array(
				'orderId'         => $orderId,
				'time'            => current_time( 'mysql' ),
				'transactionId'   => $transactionId,
				'identifier'      => $identifier,
				'transactionType' => $type,
			),
			array( '%d', '%s', '%s', '%s', '%s' )
		);

	}

	/**
	 * @param int $orderId
	 *
	 * @return array|null
	 */
	public function getReconciliationData( $orderId ) {
		global $wpdb;

		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}altapayReconciliationIdentifiers WHERE orderId = %d", $orderId ), ARRAY_A );
	}

	/**
	 * Export Reconciliation CSV file.
	 *
	 * @return void
	 */
	public function exportReconciliationCSV() {
		if ( isset( $_REQUEST['export_reconciliation_data'] ) ) {
			global $wpdb;

			$output    = '';
			$file_name = 'reconciliation_data.csv';

			header( 'Content-Type: text/csv; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename=' . $file_name );
			header( 'Pragma: no-cache' );
			header( 'Expires: 0' );

			$perPage = (int) get_user_option( 'edit_shop_order_per_page', get_current_user_id() );

			if ( empty( $perPage ) || $perPage < 1 ) {
				$perPage = 20;
			}

			$paged = isset( $_REQUEST['orders_pagenum'] ) ? absint( $_REQUEST['orders_pagenum'] ) : 1;

			$args = array(
				'posts_per_page' => $perPage,
				'fields'         => 'ids',
				'post_type'      => 'shop_order',
				'post_status'    => array_keys( wc_get_order_statuses() ),
				'paged'          => $paged,
			);

			if ( ! empty( $_GET['_customer_user'] ) ) {
				$args['meta_query'] = array(
					array(
						'key'     => '_customer_user',
						'value'   => (int) sanitize_text_field( wp_unslash( $_GET['_customer_user'] ) ),
						'compare' => '=',
					),
				);
			}

			if ( ! empty( $_GET['post_status'] ) ) {
				$args['post_status'] = sanitize_text_field( wp_unslash( $_GET['post_status'] ) );
			}

			if ( ! empty( $_GET['orderby'] ) ) {
				$args['orderby'] = sanitize_text_field( wp_unslash( $_GET['orderby'] ) );
			}

			if ( ! empty( $_GET['order'] ) ) {
				$args['order'] = sanitize_text_field( wp_unslash( $_GET['order'] ) );
			}

			if ( ! empty( $_GET['m'] ) ) {
				$yearMonth = sanitize_text_field( wp_unslash( $_GET['m'] ) );

				if ( ! empty( $yearMonth ) && preg_match( '/^[0-9]{6}$/', $yearMonth ) ) {

					$year  = (int) substr( $yearMonth, 0, 4 );
					$month = (int) substr( $yearMonth, 4, 2 );

					$args['date_query'] = array(
						array(
							'year'  => $year,
							'month' => $month,
						),
					);
				}
			}

			$query = new \WP_Query( $args );

			if ( $query->have_posts() ) {

				$PostToSelect = substr( str_repeat( ',%d', count( $query->posts ) ), 1 );

				$reconciliation_data =
					$wpdb->get_results(
						$wpdb->prepare(
							"SELECT orderId, identifier, transactionType FROM {$wpdb->prefix}altapayReconciliationIdentifiers WHERE orderId IN ($PostToSelect) ",
							$query->posts
						),
						ARRAY_A
					);

				$output  = $output . 'Order ID,Date Created,Order Total,Currency,Transaction ID,Reconciliation Identifier,Type,Payment Method,Order Status';
				$output .= "\n";

				if ( ! empty( $reconciliation_data ) ) {
					foreach ( $reconciliation_data as $data ) {
						$order = wc_get_order( $data['orderId'] );

						if ( $order ) {
							$dateLocalised = ! is_null( $order->get_date_created() ) ? $order->get_date_created()->getOffsetTimestamp() : '';
							$createdDate   = esc_attr( date_i18n( 'Y-m-d', $dateLocalised ) );
							$paymentMethod = $order->get_payment_method_title();
							$total         = $order->get_total();
							$status        = $order->get_status();
							$currency      = $order->get_currency();
							$transactionId = $order->get_transaction_id();

							$output .= $data['orderId'] . ',' . $createdDate
								. ',' . $total . ',' . $currency . ','
								. $transactionId . ',' . $data['identifier']
								. ',' . $data['transactionType'] . ','
								. $paymentMethod . ',' . $status;
							$output .= "\n";
						}
					}
				}
			}
			echo $output;
			exit;
		}
	}
}
