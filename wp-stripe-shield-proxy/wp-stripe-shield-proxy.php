<?php
/**
 * Plugin Name: LazyShield Stripe Proxy
 * Description: Server-side Stripe proxy for LazyShield Gateway Stripe.
 * Version: 1.0.4
 * Author: LazyShield
 */

defined('ABSPATH') || exit;

define('WPLAZY_STRIPE_PROXY_VERSION', '1.0.4');
define('WPLAZY_STRIPE_PROXY_DIR', plugin_dir_path(__FILE__));
define('WPLAZY_STRIPE_PROXY_URL', plugin_dir_url(__FILE__));

require_once WPLAZY_STRIPE_PROXY_DIR . 'inc/api.php';
require_once WPLAZY_STRIPE_PROXY_DIR . 'inc/admin.php';

add_action('template_redirect', 'wplazy_stripe_proxy_render_checkout');

function wplazy_stripe_proxy_render_checkout() {
    if (empty($_GET['checkout']) || sanitize_text_field(wp_unslash($_GET['checkout'])) !== 'yes') {
        return;
    }

    $config = wplazy_stripe_get_webshield_config();
    if (is_wp_error($config)) {
        wp_die('Access denied: Stripe configuration could not be verified.', 'Access denied', ['response' => 403]);
    }
    // The gateway iframe uses no-referrer, so a same-site proxy may have no browser origin.
    $origin = $_GET['Origin'] ?? ($_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? home_url('/'));
    if (!wplazy_stripe_origin_allowed($origin, $config['whitelist'] ?? [])) {
        wp_die('Access denied: request origin is not whitelisted.', 'Access denied', ['response' => 403]);
    }
    $stripe = $config['stripe_config'] ?? [];
    if (empty($stripe['publishable_key'])) {
        wp_die('Stripe publishable key is not configured.', 'Stripe configuration', ['response' => 500]);
    }

    $data = [
        'publishable_key' => $stripe['publishable_key'],
        'currency' => sanitize_text_field(wp_unslash($_GET['currency'] ?? 'usd')),
        'amount' => absint($_GET['amount'] ?? 0),
    ];
    include WPLAZY_STRIPE_PROXY_DIR . 'templates/checkout.php';
    exit;
}
