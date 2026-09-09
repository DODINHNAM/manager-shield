<?php
defined('ABSPATH') || exit;

add_action('admin_menu', function () {
    add_options_page('Stripe Shield Proxy', 'Stripe Proxy', 'manage_options', 'wplazy-stripe-proxy', function () {
        echo '<div class="wrap"><h1>Stripe Shield Proxy</h1><p>Stripe credentials are managed by LazyShield Manager and are never stored in this proxy.</p></div>';
    });
});
