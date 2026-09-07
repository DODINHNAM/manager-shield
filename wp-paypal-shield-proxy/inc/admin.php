<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action('admin_menu', 'wpme_com_paypal_proxy_admin_menu');

function wpme_com_paypal_proxy_admin_menu() {
    add_menu_page(
        'PayPal Shield Proxy Settings',
        'PayPal Proxy',
        'manage_options',
        'wpme-com-paypal-proxy-settings',
        'wpme_com_paypal_proxy_settings_page',
        'dashicons-shield-alt'
    );
}

function wpme_com_paypal_proxy_settings_page() {
    ?>
    <div class="wrap">
        <h1>PayPal Shield Proxy Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('wpme_com_paypal_proxy_options');
            do_settings_sections('wpme-com-paypal-proxy-settings');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

add_action('admin_init', 'wpme_com_paypal_proxy_admin_init');

function wpme_com_paypal_proxy_admin_init() {
    register_setting('wpme_com_paypal_proxy_options', 'wpme_com_paypal_proxy_environment');

    add_settings_section(
        'wpme_com_paypal_proxy_main_section',
        'Main Settings',
        null,
        'wpme-com-paypal-proxy-settings'
    );

    add_settings_field(
        'wpme_com_paypal_proxy_environment',
        'PayPal Environment',
        'wpme_com_paypal_proxy_environment_callback',
        'wpme-com-paypal-proxy-settings',
        'wpme_com_paypal_proxy_main_section'
    );
}

function wpme_com_paypal_proxy_environment_callback() {
    $environment = get_option('wpme_com_paypal_proxy_environment', 'sandbox');
    ?>
    <select name="wpme_com_paypal_proxy_environment" id="wpme_com_paypal_proxy_environment">
        <option value="sandbox" <?php selected($environment, 'sandbox'); ?>>Sandbox</option>
        <option value="live" <?php selected($environment, 'live'); ?>>Live</option>
    </select>
    <?php
}