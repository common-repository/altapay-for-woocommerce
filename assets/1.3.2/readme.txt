=== AltaPay for WooCommerce ===
Contributors: altapayint
Donate link: http://www.altapay.com/
Tags: altapay, gateway, payment, woocommerce
Requires at least: 4.5.3
Tested up to: 4.7.3
Stable tag: 1.3.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

A plugin that integrates your WooCommerce web shop to the AltaPay payments gateway.

== Description ==

AltaPay has made it much easier for you as merchant/developer to receive secure payments in your WooCommerce web shop.
AltaPay is fully integrated with WooCommerce via a plug-in. All you have to do is to install the plug-in, which will only
take a few minutes to complete.

== Installation ==

The whole installation and configuration process is described in our [integration manual](https://github.com/AltaPay/AltaPay-and-WooCommerce-plug-in/blob/master/AltaPay%20%26%20WooCommerce%20Installation%20Guide.pdf).

== Screenshots ==

1. Plugin configuration for gateway access
2. Payment terminal configuration

== Changelog ==
= 1.3.2 =
* Bug fix in JavaScript

= 1.3.1 =
* Improvements: refund section
* Bug fixes:
    * captured amount shown in the view;
    * no value in the quantity input field from the order lines

= 1.3 =
* Added order lines for partial capture/refund
    * Added the sales_tax value calculated for partial capture;
    * Added refund functionality in the same code block as capture;
    * Added shipping details as part of the order lines; hence, the shipping can be refunded.

= 1.2.14 =
* Bug fix - sales_tax parameter not sent to the gateway

= 1.2.13 =
* Bug fix regarding languages

= 1.2.12 =
* Bug fix regarding refunds

= 1.2.11 =
* Correction for compatibility with WooCommerce 3.0

= 1.2.10 =
* Orders are captured when their statuses are changed to Completed

= 1.2.9 =
* Correction in the loading of templates

= 1.2.8 =
* Order lines were added to the partial refund operation

= 1.2.7 =
* Bugs correction.

= 1.2.6 =
* Security improvements.

= 1.2.5 =
* Support of alternative payment methods.

= 1.2.1 =
* First stable version.

== Support ==

Feel free to contact our support team (support@altapay.com) if you need any assistance
during the installation process or have questions regarding specific payment methods and functionalities.

== About AltaPay ==

AltaPay is a leading cross border payment processor that delivers payment management solutions to e-commerce businesses across Europe and the USA.
We help companies scale internationally by offering secure and reliable payment coverage across multiple sales channels, currencies and payment
methods. AltaPay specializes in fashion retailer and retail business, and serve major clients such as Boozt, BooHoo.com, Fat Face, Kate Spade,
Bang&Olufsen, Redcoon and many others.

== Upgrade Notice ==

= 1.2.11 =
[Review update best practices](https://docs.woocommerce.com/document/how-to-update-your-site) before upgrading.
