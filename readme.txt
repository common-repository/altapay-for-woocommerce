===  AltaPay for WooCommerce ===
Contributors: altapay_integrations
Tags: AltaPay, Gateway, Payments, WooCommerce, Payment Card Industry
Requires PHP: 7.4
Requires at least: 5.0
Tested up to: 6.6.2
Stable tag: 3.7.3
License: MIT
WC requires at least: 3.9.0
WC tested up to: 9.3.3
License URI: http://www.gnu.org/licenses/gpl-2.0.html

A plugin that integrates your WooCommerce web shop to the AltaPay payments gateway.

== Description ==

AltaPay has made it much easier for you as merchant/developer to receive secure payments in your WooCommerce web shop.
AltaPay is fully integrated with WooCommerce via a plug-in. All you have to do is to install the plug-in, which will only take a few minutes to complete.

== Installation ==

The whole installation and configuration process is described in our [integration manual](https://github.com/AltaPay/plugin-wordpress/wiki).

== Screenshots ==

1. Plugin configuration for gateway access
2. Payment terminal configuration
3. Checkout page
4. AltaPay Payment Actions

== Support ==

Feel free to contact our support team (support@altapay.com) if you need any assistance during the installation process or have questions regarding specific payment methods and functionalities.

== About AltaPay ==

AltaPay supports major acquiring banks, global payment methods and over 50 preferred local schemes like Dankort in Denmark, Vipps and Bank Axept in Norway, Swish in Sweden etc., across multiple sales channels (in-store and terminals & eCommerce), geographies and currencies. This includes credit and debit card acquiring, bank transfer networks, direct debit, wallets, mobile payment types, online invoicing, prepaid and gift card networks. With offices in Denmark, AltaPay serves Pan European and Global customers including JD Sports, Sports Direct, Paul Smith, Laura Ashley, DFDS Seaways, ZARA, ECCO and Stokke.
AltaPay's Payment Gateway for WooCommerce provides merchants with access to a full set of business-ready international payment and accounting functionality. With this extension, merchants are able to receive payments through Visa, Mastercard, Dankort, iDeal, PayPal, MobilePay, Klarna and ViaBill. To use the extension an account for AltaPay's payment gateway is needed. Once the account is set, the merchant receives API credentials which will link the extension to the payment gateway.

== Changelog ==

= 3.7.3 =
* Fix: Payment charged multiple times for subscription order.

= 3.7.2 =
* Add support for free trials for subscription orders.

= 3.7.1 =
* Fix: Memory issues encountered during checkout for logged-in users.

= 3.7.0 =
* Fix compatibility issues with subscription payments using PHP 8.1.
* Fix issues with MobilePay subscriptions.
* Fix Klarna order line amount mismatch in case of certain tax configurations.

= 3.6.9 =
* Support multiple payment method logos for checkout page.

= 3.6.8 =
* Add translation support for the AltaPay Payment Actions grid in the following WordPress-supported languages:
Danish, German, Estonian, Finnish, French, Czech, German (Austria), German (Switzerland, informal), German (formal), German (Switzerland), French (Belgium), French (Canada), Italian, Lithuanian, Dutch, Dutch (Belgium), Dutch (Formal), Norwegian Nynorsk, Polish, Romanian, Swedish.

= 3.6.7 =
* Extend support to include all languages that the gateway supports.
  Supported languages: https://documentation.altapay.com/Content/Ecom/Reference/Supported%20languages.htm

= 3.6.6 =
* Fix MobilePay Subscription Payment.

= 3.6.5 =
* Improve UI for AltaPay Payment Actions grid and move it to the top right side for easy access.

= 3.6.4 =
* Fix: "Parameter customer_info[billing_country] was not a valid country ('')" error during checkout.
* Fix: Payment method logos appeared too large in some themes.

= 3.6.3 =
* Configure terminal logo automatically.

= 3.6.2 =
* Add support for PHP 8.2

= 3.6.1 =
* Fix: Error occurred when performing checkout with PHP 7.4
* Fix: Round off unit price in order line to 3 decimal digits.

= 3.6.0 =
* Add support for the Trustly payment method.
* Mark the order as successful if the reservation amount is greater than 0 when evaluating the callback response.
* Fix: Duplicate messages are displayed when the order status is changed to completed.

= 3.5.9 =
* Handle callback exception caused by invalid XML.
* Add support for the SEPA payment method.

= 3.5.8 =
* Add support for WooCommerce Checkout Blocks.

= 3.5.7 =
* Minor bug fixes & improve error handling.

= 3.5.6 =
* Isolate vendor dependencies to resolve conflicts with other plugins.
* Fix: Error on the payment page due to an invalid order.

= 3.5.5 =
* Fix: Apple Pay payment mismatch issue with multi-shipping case.

= 3.5.4 =
* Add support for WooCommerce Composite Products
* Add support for WooCommerce Product Bundles

= 3.5.3 =
* Fix: Duplicate transactions sent to the gateway with the WPML plugin.
* Fix: Order not releasing on "canceled" status change.
* Fix: Purchase summary shows 2 Apple Pay buttons with a multi-currency site.
* Save reconciliation identifier when order is captured via status change.

= 3.5.2 =
* Add support for WooCommerce High Performance Order Storage (HPOS).
* Add support for WPML multilingual plugin.

= 3.5.1 =
* Add support for subscriptions via MobilePay

= 3.5.0 =
* Add support for Open Banking (Using Finshark).
* Update minimum PHP supported version to 7.4

= 3.4.9 =
* Add terminal logos for Bancontact & Bank payments.
* Add Klarna's new main logo (pink).
* Add horizontal variation for MobilePay & Swish terminal logos.
* Updated and resized the checkout terminal logos.
* Fix: AltaPay order grid missing due to cache.

= 3.4.8 =
* Make the checkout form style option available to all payment forms.

= 3.4.7 =
* Fix: Refund duplicate payments against the same order in case auto-capture is enabled.

= 3.4.6 =
* Set the checkout design of the Credit Card form by default for new installations.

= 3.4.5 =
* Add new design option with modern look for Credit Card form.

= 3.4.4 =
* Add WooCommerce order number in transaction info to make it searchable on Gateway.
* Show gateway order id in order notes.

= 3.4.3 =
* Add checksum validation functionality

= 3.4.2 =
* Update minimum PHP supported version to 7.3
* Update dependencies to resolve security vulnerabilities
* Fix: Recreate terminal data issue on new plugin install

= 3.4.1 =
* Fix plugin upgrade issue

= 3.4.0 =
* Add support for subscriptions via Vipps

= 3.3.9 =
* Add support for fraud detection service
* Supports API changes from 20230412

= 3.3.8 =
* Add support for Apple Pay

= 3.3.6 =
* Fix payment page styling issues

= 3.3.5 =
* Add option to export reconciliation data in CSV

= 3.3.4 =
* Add support for payment reconciliation.
* Add support for WooCommerce Subscriptions with credit card terminal.

= 3.3.3 =
* Maintain record for partial/full captures in the AltaPay Payment Actions grid.

= 3.3.2 =
* Save credit card token based on "Save my card details" checkbox on credit card payment form.

= 3.3.1 =
* Add support for WooCommerce default refund button

= 3.3.0 =
* Update minimum PHP supported version to 7.2
* Fix: inventory not updating when doing refund from AltaPay Payment Actions grid
* Fix: Refund response issue with PHP 8.1

= 3.2.9 =
* Enable possibility to synchronize terminals based on store country with a Button in WooCommerce.
* Add support for WordPress 5.9, WooCommerce 6.2.0
* Fix: Token Control field not showing for payment method configuration page
* Fix: Redirection issue in the saved credit card section on my account page

= 3.2.8 =
* Fix: Auto release payments issue with MobilePay payment method

= 3.2.7 =
* Fix: Issue in AltaPay login when password had special character slash '/'

= 3.2.6 =
* Fix: Issue when switching the payment method on the same order id

= 3.2.5 =
* Update minimum PHP supported version to 7.0
* Fix: Capture and refund amount calculation issue on quantity change in order grid
* Fix: Error appeared during capture and refund functionality

= 3.2.4 =
* Update supported version for WooCommerce to 5.4.1 and WordPress version to 5.7.2
* Update synch button label to 'Synchronize payment methods'

= 3.2.2 =
* Support provided for Woocommerce version 5.0.0

= 3.2.1 =
* Fix some notification errors

= 3.2.0 =
* Fixed the overlapping notification bar issue
* Code improvement

= 3.1.1 =
* Added fix for payment page CSS

= 3.1.0 =
* Rebranding from Valitor to AltaPay
* Added payment methods logo selection functionality
* Support provided for WordPress version 5.5
* Support provided for WooCommerce version 4.3.2

= 3.0.1 =
* Fix - saved credit card deletion

= 3.0.0 =
* Added plugin disclaimer
* Added support for WooCommerce version 3.9.2 and WordPress version 5.3.2
* Added support for autofill credit card details when using credit card token
* Major refactoring for improving the source code quality
* Added support for Klarna Payments (Klarna reintegration)
* Added release payment functionality, by:
	** using release payment button from the actions panel
	** changing order status to canceled state
* Added design improvements: settings page and action panel
* Refactored payment form template to render appropriate order information

= 2.5.0 =
* Added support for:
    ** multiple tax rates with compound configurations
    ** multiple coupon discounts for variable products
* Source code refactoring according to PSR-2

= 2.4.0 =
* Added support for bundle products
* Improved the partial captures on orderlines

= 2.3.0 =
* Added support for various coupon types and variation products
* Improvements when dealing with tax included/excluded amounts
* Fix - failed partial captures and refunds when Klarna used

= 2.2.0 =
* Compatibility with the latest WooCommerce version 3.7.0
* Added unit tests
* Improved error handling
* Fix: - tax calculation and price rules getting wrong amounts in certain situations

= 2.1.1 =
* Fix - unit price not fetched correctly when price including taxes

= 2.1.0 =
* Added support for coupons
* Cart rules are parsed as a separate order line to the payment gateway
* Fix - unit price without taxes, regardless the setting from the backend

= 2.0.0 =
* Strengthen solution for the virtual products in relation to the shipping information
* Fix - error when fetching the plugin information
* Fix - error log spammed with error messages due to the wrong autoloader implementation

= 1.9.0 =
* SDK rebranding from AltaPay to Valitor
* Added support for WooCommerce 3.6.3 and WordPress 5.2.0

= 1.8.0 =
* Platform and plugin versioning information sent to the payment gateway

= 1.7.2 =
* Fix - Error message shown if create payment call fails
* Fix - Payment gateway password with special characters parsed correctly

= 1.7.1 =
* Fix - Small cosmetic fixes after rebranding

= 1.7.0 =
* Rebranding from AltaPay to Valitor
* Update the WordPress and WooCommerce supported versions
* Fix - extension update

= 1.6.3 =
* Fix - Rename the PHP SDK package and update the references

= 1.6.2 =
* Improvements - Refund operation updates the stock with the refunded products, if order lines are sent

= 1.6.1 =
* Add new tags for WooCommerce required version and tested up to
* Fix - compatibility with WooCommerce up to 3.3.3
* Improvements - PHP SDK

= 1.6.0 =
* PHP SDK update.

= 1.5.1 =
* Fix - Capture and Release buttons.
* Perform tests with latest WordPress version.

= 1.5.0 =
* Include Valitor PHP SDK through Composer.
* Upgrade the build package script.

= 1.4.0 =
* Show cart info in the payment page.

= 1.3.4 =
* Fix - connection to the payment gateway.

= 1.3.3 =
* Fix - Valitor terminals are not visible if connection to the API is not established.

= 1.3.2 =
* Fix - JavaScript code.

= 1.3.1 =
* Improve the refund section.
* Fix - captured amount shown in the view.
* Fix - no value in the quantity input field from the order lines.

= 1.3.0 =
* Add order lines for partial capture/refund.
* Add the sales_tax value, calculated for partial capture.
* Add refund functionality in the same code block as capture.
* Add shipping details as part of the order lines; hence, the shipping can be refunded.

= 1.2.14 =
* Fix - sales_tax parameter not sent to the payment gateway.

= 1.2.13 =
* Fix - regarding languages.

= 1.2.12 =
* Fix - regarding refunds.

= 1.2.11 =
* Correction for compatibility with WooCommerce 3.0.
    ** Upgrade Notice - [Review update best practices](https://docs.woocommerce.com/document/how-to-update-your-site) before upgrading.

= 1.2.10 =
* Orders are captured when their statuses are changed to Completed.

= 1.2.9 =
* Correction in templates loading.

= 1.2.8 =
* Add order lines to partial refunds.

= 1.2.7 =
* Several fixes.

= 1.2.6 =
* Security improvements.

= 1.2.5 =
* Add support for alternative payment methods.

= 1.2.1 =
* First stable version.
