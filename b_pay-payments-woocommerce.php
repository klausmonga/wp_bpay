<?php
/**
 * Plugin Name: b_pay_payment
 * Plugin URI: https://b_pay.com
 * Author: b_pay
 * Author URI: https://b_pay.com
 * Description: Mode de paiement via b_pay .
 * Version: 0.1.0
 * License: GPL2
 * License URL: http://www.gnu.org/licenses/gpl-2.0.txt
 * text-domain: b_pay
 * 
 * Class WC_Gateway_b_pay file.
 *
 * @package WooCommerce\Classes\Payment
 * @extend WC_Payment_Gateway
 * @version 0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) return;

add_action( 'plugins_loaded', 'b_pay_payment_init', 11 );
add_filter( 'woocommerce_currencies', 'techiepress_add_fr_currencies' );
add_filter( 'woocommerce_currency_symbol', 'techiepress_add_fr_currencies_symbol', 10, 2 );
add_filter( 'woocommerce_payment_gateways', 'add_to_woo_b_pay_payment_gateway');
add_filter( 'gettext', 'b_pay_change_woo_text', 100, 3 );

function b_pay_payment_init() {
    if( class_exists( 'WC_Payment_Gateway' ) ) {
		require_once plugin_dir_path( __FILE__ ) . '/includes/class-wc-payment-gateway-b_pay.php';
		require_once plugin_dir_path( __FILE__ ) . '/includes/b_pay-order-statuses.php';
		require_once plugin_dir_path( __FILE__ ) . '/includes/b_pay-checkout-description-fields.php';
		require_once plugin_dir_path( __FILE__ ) . '/includes/b_pay_api.php';
	}
}

function add_to_woo_b_pay_payment_gateway( $gateways ) {
    $gateways[] = 'WC_Gateway_b_pay';
    return $gateways;
}

function techiepress_add_fr_currencies( $currencies ) {
	$currencies['FC'] = __( 'Franc_congolais', 'b_pay-payments-woo' );
	return $currencies;
}

function techiepress_add_fr_currencies_symbol( $currency_symbol, $currency ) {
	switch ( $currency ) {
		case 'FC': 
			$currency_symbol = 'FC'; 
		break;
	}
	return $currency_symbol;
}




function b_pay_change_woo_text( $translated_string, $text_tring, $text_domain ) {

    switch( $translated_string ) {
        case 'Billing details':
            $translated_string = __( 'Informations du client', $text_domain );
        break;

        case 'Your order':
            $translated_string = __( 'Votre commande', $text_domain );
        break;
        
        case 'Additional information':
            $translated_string = __( 'Informations complémentaires', $text_domain );
        break;
    }

    return $translated_string;
}