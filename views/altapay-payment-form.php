<?php
/**
 * AltaPay module for WooCommerce
 *
 * Copyright © 2020 AltaPay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * The template for displaying AltaPay's payment form
 *
 * @package Altapay
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

$order_id = isset( $_POST['shop_orderid'] ) ? wp_unslash( $_POST['shop_orderid'] ) : 0;
$order    = wc_get_order( $order_id );
if ( $order ) {
	$wpml_language = $order->get_meta( 'wpml_language' );
	if ( ! empty( $wpml_language ) ) {
		global $sitepress;
		// Check if the WPML plugin is active
		if ( defined( 'ICL_SITEPRESS_VERSION' ) && is_object( $sitepress ) ) {
			// Switch the language
			$sitepress->switch_lang( $wpml_language );
		}
	}
}
get_header();
?>
<style>
<?php
 $container_class = '';
 $cc_form_styling = get_option( 'altapay_cc_form_styling' );

if ( $cc_form_styling == 'custom' ) {
	?>
	<!--Add custom styling here-->

	<?php
} elseif ( $cc_form_styling == 'checkout' ) {
	 $container_class = 'altapay_content';
	?>
.altapay_page_main{
	width: 100%;
}
.altapay_content {
	text-align: left;
	margin-left: auto;
	margin-right: auto;
	background-color: white;
	border: 1px solid rgba(0, 0, 0, 0.16);
	padding: 20px 25px 25px 25px;
	box-sizing: border-box;
	border-radius: 10px;
	position: relative;
	box-shadow: rgba(50, 50, 93, 0.25) 0 2px 5px -1px;
}

.payment-title {
	margin: 0;
}

form {
	margin: 0;
}

.payment-headline {
	margin-block-start: 0;
}

.pensio_payment_form_card-number {
	position: relative;
}

.pensio_payment_form_card-number, .pensio_payment_form_cardholder,
.pensio_payment_form-cvc-input {
	margin-top: 4px;
}

.pensio_payment_form_card-number input, .pensio_payment_form_cardholder input,
.altapay-payment-form-cnt input#organisationNumber, .pensio_payment_form_input_cell input  {
	padding: 12px 14px;
	width: 100%;
	border-radius: 3px;
	border: 1px solid rgba(0, 0, 0, 0.16);
	cursor: pointer;
	font-size: 16px;
	box-sizing: border-box;
	color: #666;
	background-color: white;
}

.pensio_payment_form_card-number input,
.pensio_payment_form_cardholder input:focus,
input[type=tel]:focus {
	background-color: white;
}

.pensioCreditCardInput {
	color: #666;
}

.pensio_payment_form_month select,
.pensio_payment_form_year select,
#idealIssuer,
.altapay-payment-form-cnt select#birthdateDay,
.altapay-payment-form-cnt select#birthdateMonth,
.altapay-payment-form-cnt select#birthdateYear{
	-webkit-appearance: none;
	-moz-appearance: none;
	background-image: linear-gradient(45deg, transparent 50%, black 50%),
	linear-gradient(135deg, black 50%, transparent 50%);
	background-position: calc(100% - 20px) calc(20px + 2px),
	calc(100% - 15px) calc(20px + 2px), 100% 0;
	background-size: 5px 5px, 5px 5px, 40px 40px;
	background-repeat: no-repeat;
	cursor: pointer;
}

.pensio_payment_form_month select,
.pensio_payment_form_year select,
#idealIssuer,
.altapay-payment-form-cnt select#birthdateDay,
.altapay-payment-form-cnt select#birthdateMonth,
.altapay-payment-form-cnt input#cancelPayment,
.altapay-payment-form-cnt input#enableAccount,
.altapay-payment-form-cnt input#acceptTerms,
.altapay-payment-form-cnt input#phoneNumber,
.altapay-payment-form-cnt select#birthdateYear{
	margin-top: 4px;
	padding: 12px 14px;
	width: 100%;
	border-radius: 3px;
	border: 1px solid rgba(0, 0, 0, 0.16);
	background-color: white;
	font-size: 16px;
}

.pensio_payment_form-cvc-input input {
	padding: 12px 14px;
	width: 100%;
	border-radius: 3px;
	border: 1px solid rgba(0, 0, 0, 0.16);
	cursor: pointer;
	font-size: 16px;
	background-color: white;
}

.pensio_payment_form_expiration {
	display: flex;
	width: 100%;
	gap: 0 10px;
}

.pensio_payment_form_month {
	width: 30%;

}

.pensio_payment_form_year {
	width: 30%;

}

.pensio_payment_form_cvc {
	width: 40%;
}

.pensio_payment_form-cvc-input {
	display: flex;
	position: relative;
}

.cvc-icon {
	width: 30px;
	position: absolute;
	top: 16px;
	right: 16px;
	align-items: center;
}

.credit-card-visa-icon {
	position: absolute;
	top: 0;
	right: 0;
	display: flex;
	padding-right: 7px;
	padding-top: 14px;
	align-items: center;
}

.credit-card-mastercard-icon {
	position: absolute;
	top: 0;
	right: 0;
	display: flex;
	padding-right: 50px;
	padding-top: 14px;
	align-items: center;
}

.credit-card-maestro-icon {
	position: absolute;
	top: 0;
	right: 0;
	display: flex;
	padding-right: 90px;
	padding-top: 14px;
	align-items: center;
}

#creditCardTypeIcon {
	height: 40%;
	width: auto;
	position: absolute;
	display: flex;
	right: 0;
	top: 0;
	bottom: 0;
	margin: auto 1rem auto auto;
}

#creditCardTypeSecondIcon {
	height: 40%;
	width: auto;
	position: absolute;
	display: flex;
	right: 0;
	top: 0;
	bottom: 0;
	margin: auto 4rem auto auto;
}

#selectCardLabel {
	position: absolute;
	right: 0;
	bottom: 0;
	margin: 0 2rem 2px 0;
	font-size: 10px;
	opacity: 0.7;
}

.pensio_payment_form_cvc-info-text {
	font-size: 10px;
	line-height: normal;
}

.pensio_payment_form_label_cell {
	font-size: 16px
}

.expiry_row {
	margin-top: 10px;
}

.cardnumber_row {
	margin-bottom: 20px;
}

.expiry_row {
	display: flex;
	width: 100%;
	gap: 0 10px;
}

.submit_row {
	margin-top: 20px;
}

input[type="submit"].AltaPaySubmitButton,
input#submitbutton,
#EPayment button[type="submit"]{
	outline: none;
	padding: 15px 16px;
	color: white;
	border-radius: 3px;
	width: 100%;
	border: none;
	cursor: pointer;
	box-shadow: rgba(0, 0, 0, 0.16) 0 1px 4px;
	font-weight: bold;
	font-size: 17px;
}

input[type="submit"].AltaPaySubmitButton,
#EPayment button[type="submit"] {
	background-color: #31C37E !important;
}

input[type="submit"].AltaPaySubmitButton:hover,
#EPayment button[type="submit"]:hover {
	background-color: #16b36e !important;
}

input[type="submit"].AltaPaySubmitButton:disabled,
input#submitbutton,
#EPayment button[type="submit"]:disabled {
	background-color: black !important;
	opacity: 1 !important;
}

input[type="submit"].AltaPaySubmitButton:disabled:hover,
#EPayment button[type="submit"]:disabled:hover{
	background-color: black !important;
	color: white;
}

input#showKlarnaPage {
	margin-bottom: 15px;
}

/*errors*/

.pensio_required_field_indicator, #invalid_amex_cvc, #invalid_cvc, #invalid_cardholdername {
	color: red;
	font-size: 12px;
	margin-top: 4px;
	line-height: normal;
}

.pensio_payment_form_invalid-cvc-input, .pensio_payment_form_invalid-cardholder-input {
	color: red;
}

.PensioCloseButton, .CustomAltaPayCloseButton {
	width: 40px;
	height: 20px;
	font-size: 18px;
	background-color: red;
	color: white;
	cursor: pointer;
	padding: 4px;
	position: absolute;
	right: 0;
	top: 0;
}

.PensioRadioButton {
	border: none;
	background-color: transparent;
	cursor: pointer;
}

div.PensioMultiformContainer form {
	display: none;
}

#PensioJavascriptDisabledSurchargeNotice {
	color: red;
	background-color: white;
}

#iDealPayment table {
	width: 100%;
}

#iDealPayment #pensioPaymentIdealSubmitButton {
	margin-top: 20px;
}

#idealIssuer select {
	color: #666;
}

.PensioRadioButton {
	border: none;
	background-color: transparent;
	cursor: pointer;
}

div.PensioMultiformContainer form {
	display: none;
}

#PensioJavascriptDisabledSurchargeNotice {
	color: red;
	background-color: white;
}

.altapay-page-wrapper .altapay-order-details {
	padding: 15px 0;
}

.altapay-payment-form-cnt select#birthdateDay,
.altapay-payment-form-cnt select#birthdateMonth,
.altapay-payment-form-cnt input#cancelPayment,
.altapay-payment-form-cnt input#enableAccount,
.altapay-payment-form-cnt input#acceptTerms,
.altapay-payment-form-cnt input#phoneNumber {
	margin-bottom: 10px;
}

.altapay-payment-form-cnt div.PensioMultiformContainer form {
	position: relative;
	border: none;
	background-color: white;
	padding: 0;
	margin: 0;
	border-radius: 0;
	top: 0;
	width: 100%;
}

.altapay-payment-form-cnt input#CreditCardButton {
	left: 0px;
}

.altapay-payment-form-cnt input#GiftCardButton {
	left: 100px;
}

.altapay-payment-form-cnt div.PensioMultiformContainer .FormTypeButton {
	position: absolute;
	top: -40px;
	height: 40px;
	margin-left: 25px;
	border: 1px solid rgba(0, 0, 0, 0.16);
}

.altapay-payment-form-cnt div.PensioMultiformContainer {
	position: initial;
}

input#giftcard_account_identifier {
	background-color: white;
	border-radius: 3px;
	color: #666;
	border: 1px solid rgba(0, 0, 0, 0.16);
}

.altapay-payment-form-cnt #Invoice td.pensio_payment_form_label_cell {
	vertical-align: middle;
}

.PensioMultiformContainer input#giftcard_account_identifier {
	width: 100%;
}

.altapay-payment-form-cnt table.pensio_payment_form_table {
	margin-bottom: 0;
}

#klarna_options {
   padding-top: 20px;
   padding-bottom: 20px;
}

#EPayment .IbanPopup img {
	display: block;
}

#EPayment .pensio_payment_form_label_cell,
#iDealPayment .pensio_payment_form_label_cell,
#Mobile .pensio_payment_form_label_cell,
#GiftCard .pensio_payment_form_label_cell {
	display: block;
	padding: 0.25em 0;
}

#EPayment .pensio_payment_form_input_cell,
#iDealPayment .pensio_payment_form_input_cell,
#Mobile .pensio_payment_form_input_cell,
#GiftCard .pensio_payment_form_input_cell {
	padding: 0 0 1em;
	display: block;
}

#GiftCard tr:nth-child(2) td {
	padding-left: 0;
	padding-right: 0;
}

#iDealPayment td.pensio_payment_form_submit_cell,
#iDealPayment .pensio_payment_form_input_cell,
#GiftCard .pensio_payment_form_input_cell{
	padding: 0;
}

.altapay-page-wrapper {
	width: 100%;
	padding-top: 50px;
}

@media screen and (min-width:992px){
	.altapay-page-wrapper {
		display: flex;
		column-gap: 30px;
		align-items: flex-start;
		padding-top: 50px;
	}
	.theme-storefront .altapay-page-wrapper {
		padding-top: 0;
	}
	.altapay-page-wrapper .altapay-payment-form-cnt, .altapay-page-wrapper .altapay-order-details {
		flex: 1;
	}

	.altapay-page-wrapper .altapay-order-details {
		padding: 15px;
	}
}

<?php } else { ?>
	.pensio_payment_form_cvc_cell img {
		max-width: 60px;
	}
	.pensio_payment_form_row {
		margin-bottom: 15px;
	}
	.pensio_payment_form_input_cell img {
		display: inline-block;
		margin-left: 5px;
		vertical-align: middle;
	}
	.altapay-page-wrapper {
		width: 100%;
	}
	.altapay-page-wrapper .altapay-payment-form-cnt, .altapay-page-wrapper .altapay-order-details {
		padding: 15px;
	}
	.altapay-page-wrapper .altapay-payment-form-cnt {
		padding-top: 50px;
	}
	input#creditCardNumberInput, input#cardholderNameInput {
		width: 100%;
		max-width: 300px;
	}
	input#cvcInput {
		min-width: 100px;
		max-width: 140px;
	}
	select#emonth, select#eyear {
		max-width: 100px;
	}
	.site-main {
		width: 100%;
	}
	.woocommerce-page .col2-set .col-1, .woocommerce-column--shipping-address.col-2 {
		padding: 0;
	}
	@media screen and (min-width:769px){
		.altapay-page-wrapper {
			display: flex;
		}
		.altapay-page-wrapper .altapay-payment-form-cnt, .altapay-page-wrapper .altapay-order-details {
			flex: 1;
		}
	}
<?php } ?>

/* Hide 'Show Klarna Page' button if hidden attribute exists */
input#showKlarnaPage[hidden] {
	display: none;
}
</style>
<main id="main" class="site-main woocommerce-page altapay_page_main" role="main">
	<div class="container">
		<div class="row">
			<div class="altapay-page-wrapper">
				<div class="altapay-payment-form-cnt <?php echo $container_class; ?>">
					<form id="PensioPaymentForm"></form>
				</div>
				<div class="altapay-order-details woocommerce">
				<?php
					woocommerce_order_details_table( $order_id );
				?>
				</div>
			</div>
		</div>
	</div>
</main>
<?php
get_footer();
