<?php
/**
 * Plugin Name: LazyShield PayPal Proxy
 * Description: A WordPress plugin to integrate PayPal's credit payment form.
 * Version: 1.0.22
 * Author: LazyShield
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'WPLAZY_PAYPAL_PROXY_VERSION', '1.0.22' );
define( 'WPLAZY_PAYPAL_PROXY_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPLAZY_PAYPAL_PROXY_URL', plugin_dir_url( __FILE__ ) );

// Include necessary files
require_once WPLAZY_PAYPAL_PROXY_DIR . 'inc/api.php';

// Enqueue scripts and styles
function wplazy_paypal_proxy_enqueue_scripts() {
    $webshield_full_config = get_webshield_config();
    $paypal_config = ( ! is_wp_error( $webshield_full_config ) && isset( $webshield_full_config['paypal_config'] ) ) ? $webshield_full_config['paypal_config'] : null;

    $paypal_client_id = '';
    $paypal_environment = 'sandbox'; // Default to sandbox
    if ( $paypal_config ) {
        $paypal_client_id = $paypal_config['client_id'];
        $paypal_environment = $paypal_config['environment'];
    }
    // Determine PayPal SDK base URL
    $paypal_sdk_base_url = ($paypal_environment === 'live') ? 'https://www.paypal.com/sdk/js' : 'https://www.sandbox.paypal.com/sdk/js';

    // Enqueue PayPal SDK with dynamic client ID
    wp_enqueue_script( 'paypal-sdk', esc_url( $paypal_sdk_base_url . '?client-id=' . $paypal_client_id . '&currency=USD' ), array(), null, true );

    wp_enqueue_script( 'paypal-credit-payment-form', WPLAZY_PAYPAL_PROXY_URL . 'assets/js/paypal-credit-payment-form.js', array( 'paypal-sdk' ), null, true );
    wp_enqueue_style( 'paypal-credit-payment-form', WPLAZY_PAYPAL_PROXY_URL . 'assets/css/paypal-credit-payment-form.css' );
}
add_action( 'wp_enqueue_scripts', 'wplazy_paypal_proxy_enqueue_scripts' );

// Register shortcode for checkout
function wplazy_paypal_proxy_checkout_shortcode() {
    ob_start();
    include WPLAZY_PAYPAL_PROXY_DIR . 'templates/checkout.php';
    return ob_get_clean();
}
add_shortcode( 'paypal_checkout', 'wplazy_paypal_proxy_checkout_shortcode' );

// Activation hook
function wplazy_paypal_proxy_activate() {
    // Code to run on plugin activation
}
register_activation_hook( __FILE__, 'wplazy_paypal_proxy_activate' );

// Deactivation hook
function wplazy_paypal_proxy_deactivate() {
    // Code to run on plugin deactivation
}
register_deactivation_hook( __FILE__, 'wplazy_paypal_proxy_deactivate' );

// Redirect to checkout template if URL has ?checkout=yes
add_action( 'template_redirect', function() {
    if ( isset( $_GET['checkout'] ) && sanitize_text_field( $_GET['checkout'] ) === 'yes' ) {
        $origin = '';
        if ( isset( $_GET['Origin'] ) && ! empty( $_GET['Origin'] ) ) {
            $origin = sanitize_text_field( wp_unslash( $_GET['Origin'] ) );
        } elseif ( isset( $_SERVER['HTTP_ORIGIN'] ) ) {
            $origin = $_SERVER['HTTP_ORIGIN'];
        }

        if ( empty( $origin ) ) {
            // If no Origin header, deny by default for security
            wp_die( 'Access Denied: Origin header is missing.', 'Access Denied', array( 'response' => 403 ) );
        }

        $origin_host = strtolower( (string) parse_url( $origin, PHP_URL_HOST ) );
        if ( empty( $origin_host ) ) {
            wp_die( 'Access Denied: Invalid Origin header.', 'Access Denied', array( 'response' => 403 ) );
        }

        $webshield_full_config = get_webshield_config();

        if ( is_wp_error( $webshield_full_config ) ) {
            error_log( '[Webshield Whitelist] Error fetching whitelist for template_redirect: ' . $webshield_full_config->get_error_message() );
            wp_die( 'Access Denied: Could not verify request origin.', 'Access Denied', array( 'response' => 403 ) );
        }

        $whitelist_array = isset($webshield_full_config['whitelist']) ? $webshield_full_config['whitelist'] : [];

        if ( empty( $whitelist_array ) || ! is_array( $whitelist_array ) ) {
            error_log( '[Webshield Whitelist] Whitelist data is empty or invalid for template_redirect.' );
            wp_die( 'Access Denied: Could not verify request origin (whitelist empty).', 'Access Denied', array( 'response' => 403 ) );
        }

        $whitelist_array = array_map( static function ( $domain ) {
            $domain = strtolower( trim( (string) $domain ) );
            $parsed = parse_url( strpos( $domain, '://' ) === false ? 'https://' . $domain : $domain );
            return strtolower( $parsed['host'] ?? preg_replace( '/:\d+$/', '', $domain ) );
        }, $whitelist_array );

        if ( ! in_array( $origin_host, $whitelist_array, true ) ) {
            wp_die( 'Access Denied: Request origin is not whitelisted.', 'Access Denied', array( 'response' => 403 ) );
        }

        // If all checks pass, include the template
        include WPLAZY_PAYPAL_PROXY_DIR . 'templates/checkout.php';
        exit;
    }
} );
