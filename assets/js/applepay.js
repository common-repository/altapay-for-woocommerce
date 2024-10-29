jQuery(function ($) {
	let session = '';
	if ( typeof altapay_applepay_obj === 'undefined' ) {
		return false;
	}

	$('form.checkout').on('checkout_place_order_' + altapay_applepay_obj.applepay_payment_method, function (event, wc_checkout_form) {
			var $form = $( 'form.checkout' );
			$form.addClass( 'processing' );

			wc_checkout_form.blockOnSubmit( $form );

			// Attach event to block reloading the page when the form has been submitted
			wc_checkout_form.attachUnloadEventsOnSubmit();

			// ajaxSetup is global, but we use it to ensure JSON is valid once returned.
			$.ajaxSetup( {
				dataFilter: function( raw_response, dataType ) {
					// We only want to work with JSON
					if ( 'json' !== dataType ) {
						return raw_response;
					}

					if ( wc_checkout_form.is_valid_json( raw_response ) ) {
						return raw_response;
					} else {
						// Attempt to fix the malformed JSON
						var maybe_valid_json = raw_response.match( /{"result.*}/ );

						if ( null === maybe_valid_json ) {
							console.log( 'Unable to fix malformed JSON' );
						} else if ( wc_checkout_form.is_valid_json( maybe_valid_json[0] ) ) {
							console.log( 'Fixed malformed JSON. Original:' );
							console.log( raw_response );
							raw_response = maybe_valid_json[0];
						} else {
							console.log( 'Unable to fix malformed JSON' );
						}
					}

					return raw_response;
				}
			} );

			$.ajax({
				type:		'POST',
				url:		wc_checkout_params.checkout_url,
				data:		$form.serialize(),
				dataType:   'json',
				success:	function( result ) {
					// Detach the unload handler that prevents a reload / redirect
					wc_checkout_form.detachUnloadEventsOnSubmit();

					try {
						if ( 'success' === result.result && $form.triggerHandler( 'checkout_place_order_success', [ result, wc_checkout_form ] ) !== false ) {
							if(result.order_total){
								altapay_applepay_obj.subtotal = result.order_total;
							}
							onApplePayButtonClicked( altapay_applepay_obj, false, wc_checkout_form, result.order_id );
						} else if ( 'failure' === result.result ) {
							console.log( 'failure' );
							throw 'Result failure';
						} else {
							throw 'Invalid response';
						}
					} catch( err ) {
						// Reload page
						if ( true === result.reload ) {
							window.location.reload();
							return;
						}

						// Trigger update in case we need a fresh nonce
						if ( true === result.refresh ) {
							$( document.body ).trigger( 'update_checkout' );
						}

						// Add new errors
						if ( result.messages ) {
							wc_checkout_form.submit_error( result.messages );
						} else {
							wc_checkout_form.submit_error( '<div class="woocommerce-error">' + wc_checkout_params.i18n_checkout_error + '</div>' ); // eslint-disable-line max-len
						}
					}
				},
				error: function( jqXHR, textStatus, errorThrown ) {
					// Detach the unload handler that prevents a reload / redirect
					wc_checkout_form.detachUnloadEventsOnSubmit();

					wc_checkout_form.submit_error(
						'<div class="woocommerce-error">' +
						( errorThrown || wc_checkout_params.i18n_checkout_error ) +
						'</div>'
					);
				}
			});

			return false;
	});

	$(document).on("click", "#place_order", function(){
		if ($('#payment_method_' + altapay_applepay_obj.applepay_payment_method).is(':checked')) {
			onApplePayButtonClicked(altapay_applepay_obj, true, false);
		}
	});
});

function onApplePayButtonClicked(applepay_obj, createSession, wc_checkout_form, order_id) {

	if ( ! ApplePaySession) {
		return;
	}

	// Define ApplePayPaymentRequest
	const request = {
		"countryCode": applepay_obj.country,
		"currencyCode": applepay_obj.currency,
		"merchantCapabilities": [
			"supports3DS"
		],
		"supportedNetworks": applepay_obj.apple_pay_supported_networks,
		"total": {
			"label": applepay_obj.apply_pay_label,
			"type": "final",
			"amount": applepay_obj.subtotal
		}
	};

	// Create ApplePaySession
	if (createSession) {
		session = new ApplePaySession( 3, request );
	}

	session.onvalidatemerchant = async event => {
		jQuery.post(
			applepay_obj.ajax_url,
			{
				ajax_nonce: applepay_obj.nonce,
				action: 'validate_merchant',
				validation_url: event.validationURL,
				terminal: applepay_obj.terminal
			},
			function (res) {
				if (res.success === true) {
					const merchantSession = jQuery.parseJSON( res.data );
					session.completeMerchantValidation( merchantSession );
				} else if (res.data.redirect) {
					window.location = res.data.redirect;
				}
			}
		);
	};

	session.onpaymentmethodselected = event => {
		// Define ApplePayPaymentMethodUpdate based on the selected payment method.
		let total = {
			"label": applepay_obj.apply_pay_label,
			"type": "final",
			"amount": applepay_obj.subtotal
		}

		const update = { "newTotal": total };
		session.completePaymentMethodSelection( update );
	};

	session.onshippingmethodselected = event => {
		// Define ApplePayShippingMethodUpdate based on the selected shipping method.
		// No updates or errors are needed, pass an empty object.
		const update = {};
		session.completeShippingMethodSelection( update );
	};

	session.onshippingcontactselected = event => {
		// Define ApplePayShippingContactUpdate based on the selected shipping contact.
		const update = {};
		session.completeShippingContactSelection( update );
	};

	session.onpaymentauthorized = event => {
		// Define ApplePayPaymentAuthorizationResult
		jQuery.post(
			applepay_obj.ajax_url,
			{
				ajax_nonce: applepay_obj.nonce,
				action: 'card_wallet_authorize',
				provider_data: JSON.stringify( event.payment.token ),
				terminal: applepay_obj.terminal,
				order_id: order_id
			},
			function (res) {
				let status;
				if (res.success === true) {
					status = ApplePaySession.STATUS_SUCCESS;
					session.completePayment( status );
				} else {
					status = ApplePaySession.STATUS_FAILURE;
					session.completePayment( status );
				}

				if (wc_checkout_form) {
					wc_checkout_form.$checkout_form.removeClass( 'processing' ).unblock();
				}

				if (res.data.redirect) {
					window.location = res.data.redirect;
				}
			}
		);
	};

	session.oncouponcodechanged = event => {
		// Define ApplePayCouponCodeUpdate
		const newTotal           = calculateNewTotal( event.couponCode );
		const newLineItems       = calculateNewLineItems( event.couponCode );
		const newShippingMethods = calculateNewShippingMethods( event.couponCode );
		const errors             = calculateErrors( event.couponCode );

		session.completeCouponCodeChange(
			{
				newTotal: newTotal,
				newLineItems: newLineItems,
				newShippingMethods: newShippingMethods,
				errors: errors,
			}
		);
	};

	session.oncancel = event => {
		// Payment cancelled by WebKit
		if(wc_checkout_form){
			wc_checkout_form.$checkout_form.removeClass( 'processing' ).unblock();
		}else{
			location.reload();
		}
	};

	if (!createSession) {
		session.begin();
	}
}
