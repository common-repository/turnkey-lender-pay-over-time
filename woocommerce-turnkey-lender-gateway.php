<?php
/**
 * Plugin Name: WooCommerce TurnKey Lender Payment Gateway
 * Description: Take credit payments on your store.
 * Author: TurnKey Lender
 * Version: 1.0.3
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

add_filter( 'woocommerce_payment_gateways', 'turnkey_lender_add_gateway_class' );
function turnkey_lender_add_gateway_class( $gateways ) {
    $gateways[] = 'TKL_WC_Payment_Gateway';
    return $gateways;
}

define('WOO_TKL_PATH', dirname(__FILE__));
define('WOO_TKL_URL', plugin_dir_url(__FILE__));

add_action( 'plugins_loaded', 'turnkey_lender_init_gateway_class' );
function turnkey_lender_init_gateway_class() {
    require_once 'includes/TKL_WC_Payment_Gateway.php';
    require_once 'includes/TKL_LoanEstimations.php';
}
