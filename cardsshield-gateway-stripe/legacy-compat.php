<?php
/**
 * Compatibility boundary for installations upgraded from the mecom Stripe gateway.
 * Business code uses lazy identifiers; legacy names are confined to this file.
 */
defined('ABSPATH') || exit;

function lazy_stripe_legacy_meta_keys() {
    return [
        '_mecom_stripe_proxy_url' => '_lazy_stripe_proxy_url',
        '_mecom_stripe_proxy_id' => '_lazy_stripe_proxy_id',
        '_mecom_stripe_sync_tracking_info' => '_lazy_stripe_sync_tracking_info',
        '_METAKEY_MECOM_STRIPE_INTENT_CAPTURED' => '_METAKEY_LAZY_STRIPE_INTENT_CAPTURED',
        'METAKEY_MECOM_STRIPE_CAPTURED' => 'METAKEY_LAZY_STRIPE_CAPTURED',
    ];
}

function lazy_stripe_migrate_options() {
    if (get_option('lazy_stripe_options_version', 0) === '1') return;
    $missing = new stdClass();
    $legacySettings = get_option('woocommerce_mecom_stripe_settings', $missing);
    $map = [
        'woocommerce_mecom_stripe_settings' => 'woocommerce_lazy_stripe_settings',
        'Opt_Mecom_Stripe_Proxies' => 'Opt_Lazy_Stripe_Proxies',
        'Opt_Mecom_Stripe_Activated_Proxy' => 'Opt_Lazy_Stripe_Activated_Proxy',
        'OPT_MECOM_STRIPE_ROTATION_METHOD' => 'OPT_LAZY_STRIPE_ROTATION_METHOD',
        'OPT_MECOM_STRIPE_UNUSED_PROXIES' => 'OPT_LAZY_STRIPE_UNUSED_PROXIES',
        'OPT_MECOM_STRIPE_CURRENT_ROTATION_VALUE' => 'OPT_LAZY_STRIPE_CURRENT_ROTATION_VALUE',
        'OPT_MECOM_STRIPE_LAST_TIME_RESET_PAID_AMOUNT' => 'OPT_LAZY_STRIPE_LAST_TIME_RESET_PAID_AMOUNT',
        'OPT_MECOM_STRIPE_CONNECTION_MODE' => 'OPT_LAZY_STRIPE_CONNECTION_MODE',
    ];
    foreach ($map as $old => $new) {
        if (get_option($new, $missing) !== $missing) continue;
        $value = get_option($old, $missing);
        if ($value === $missing) continue;
        if ($old === 'woocommerce_mecom_stripe_settings' && is_array($value)) {
            $intents = ['OPT_MECOM_STRIPE_INTENT_CAPTURE' => 'OPT_LAZY_STRIPE_INTENT_CAPTURE',
                'OPT_MECOM_STRIPE_INTENT_AUTHORIZE' => 'OPT_LAZY_STRIPE_INTENT_AUTHORIZE'];
            if (isset($value['intent'], $intents[$value['intent']])) $value['intent'] = $intents[$value['intent']];
        }
        if (!add_option($new, $value, '', false) && get_option($new, $missing) === $missing) return;
    }
    // An upgraded installation keeps its existing shield wire protocol until switched.
    $hasLegacy = $legacySettings !== $missing || get_option('Opt_Mecom_Stripe_Proxies', $missing) !== $missing;
    add_option('lazy_stripe_shield_protocol', $hasLegacy ? 'mecom' : 'lazy', '', false);
    $order = get_option('woocommerce_gateway_order', []);
    if (is_array($order) && array_key_exists('mecom_stripe', $order)) {
        if (!array_key_exists('lazy_stripe', $order)) $order['lazy_stripe'] = $order['mecom_stripe'];
        unset($order['mecom_stripe']);
        update_option('woocommerce_gateway_order', $order);
    }
    update_option('lazy_stripe_options_version', '1', false);
}

/** Copy legacy metadata without overwriting new values or deleting the old rows.
 * Both CPT and HPOS tables are checked, including sites that changed order storage.
 * Interrupted runs can be repeated safely.
 */
function lazy_stripe_migrate_order_metadata() {
    global $wpdb;
    if (get_option('lazy_stripe_metadata_version', 0) === '1') return true;
    $lock = get_option('lazy_stripe_metadata_lock', 0);
    if ($lock && (int) $lock < time() - 300) delete_option('lazy_stripe_metadata_lock');
    if (!add_option('lazy_stripe_metadata_lock', time(), '', false)) return false;
    try {
        foreach ([$wpdb->postmeta => 'post_id', $wpdb->prefix . 'wc_orders_meta' => 'order_id'] as $table => $idColumn) {
            $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)));
            if ($wpdb->last_error) return false;
            if ($exists !== $table) continue;
            foreach (lazy_stripe_legacy_meta_keys() as $old => $new) {
                $sql = $wpdb->prepare(
                    "INSERT INTO `$table` (`$idColumn`, meta_key, meta_value)
                     SELECT legacy.`$idColumn`, %s, legacy.meta_value FROM `$table` legacy
                     LEFT JOIN `$table` current ON current.`$idColumn` = legacy.`$idColumn` AND current.meta_key = %s
                     WHERE legacy.meta_key = %s AND current.`$idColumn` IS NULL",
                    $new, $new, $old
                );
                if ($wpdb->query($sql) === false) return false;
            }
        }
        // Raw SQL bypasses both WordPress and WooCommerce metadata caches.
        foreach ([$wpdb->postmeta => 'post_id', $wpdb->prefix . 'wc_orders_meta' => 'order_id'] as $table => $idColumn) {
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) !== $table) continue;
            $keys = array_keys(lazy_stripe_legacy_meta_keys());
            $placeholders = implode(', ', array_fill(0, count($keys), '%s'));
            $ids = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT `$idColumn` FROM `$table` WHERE meta_key IN ($placeholders)", ...$keys));
            if ($wpdb->last_error) return false;
            foreach ($ids as $id) wp_cache_delete((int) $id, 'post_meta');
        }
        if (class_exists('WC_Cache_Helper')) WC_Cache_Helper::invalidate_cache_group('orders');
        update_option('lazy_stripe_metadata_version', '1', false);
        return true;
    } finally {
        delete_option('lazy_stripe_metadata_lock');
    }
}

function lazy_stripe_normalize_request(array $request) {
    foreach (array_keys($request) as $key) {
        if (preg_match('/^mecom[-_]stripe/', $key)) {
            $new = preg_replace('/^mecom/', 'lazy', $key);
            if (!array_key_exists($new, $request)) $request[$new] = $request[$key];
        }
    }
    foreach (['payment_method', 'section'] as $key) {
        if (($request[$key] ?? null) === 'mecom_stripe') $request[$key] = 'lazy_stripe';
    }
    if (($request['page'] ?? null) === 'mecom-gateway-stripe') $request['page'] = 'lazy-gateway-stripe';
    return $request;
}

function lazy_stripe_migrate_session() {
    if (!function_exists('WC') || !WC()->session) return;
    $session = WC()->session;
    // Null is a meaningful cleared value; use all data to distinguish it from absence.
    $data = $session->get_session_data();
    foreach (['proxy-active-id', 'proxy-active-url', 'processing-order-key'] as $suffix) {
        $old = 'mecom-stripe-' . $suffix;
        $new = 'lazy-stripe-' . $suffix;
        if (!array_key_exists($new, $data) && array_key_exists($old, $data)) $session->set($new, $session->get($old));
        $session->__unset($old);
    }
    if ($session->get('chosen_payment_method') === 'mecom_stripe') $session->set('chosen_payment_method', 'lazy_stripe');
}

function lazy_stripe_migrate_cron() {
    foreach (['rotation', 'daily', 'cron_auto_sync'] as $suffix) {
        $old = 'mecom_gateway_stripe_' . $suffix;
        $new = 'lazy_gateway_stripe_' . $suffix;
        $event = wp_get_scheduled_event($old);
        if (!$event) continue;
        if (!wp_next_scheduled($new, $event->args)) {
            $result = wp_schedule_event($event->timestamp, $event->schedule, $new, $event->args);
            if (!$result || is_wp_error($result)) continue;
        }
        wp_clear_scheduled_hook($old, $event->args);
    }
}

function lazy_stripe_wire_params(array $params) {
    $protocol = lazy_stripe_shield_protocol();
    if ($protocol !== 'mecom') return $params;
    $result = [];
    foreach ($params as $key => $value) {
        $result[preg_replace('/^lazy-stripe-/', 'mecom-stripe-', $key)] = $value;
    }
    return $result;
}

function lazy_stripe_legacy_payment_method($method) {
    return $method === 'mecom_stripe' ? 'lazy_stripe' : $method;
}

function lazy_stripe_compat_boot() {
    lazy_stripe_migrate_options();
    $_GET = lazy_stripe_normalize_request($_GET);
    $_POST = lazy_stripe_normalize_request($_POST);
    $_REQUEST = lazy_stripe_normalize_request($_REQUEST);
    add_filter('woocommerce_order_get_payment_method', 'lazy_stripe_legacy_payment_method');
    add_filter('woocommerce_order_refund_get_payment_method', 'lazy_stripe_legacy_payment_method');
    add_action('woocommerce_init', 'lazy_stripe_migrate_session', 20);
    add_action('woocommerce_load_cart_from_session', 'lazy_stripe_migrate_session');
    add_action('init', 'lazy_stripe_migrate_order_metadata', 1);
    add_filter('woocommerce_available_payment_gateways', function ($gateways) {
        if (get_option('lazy_stripe_metadata_version', 0) !== '1') unset($gateways['lazy_stripe']);
        return $gateways;
    }, 100);
    add_action('wp_ajax_mecom_gateway_stripe_action', function () {
        do_action('wp_ajax_lazy_gateway_stripe_action');
    });
    add_action('admin_notices', function () {
        if (get_option('lazy_stripe_metadata_version', 0) !== '1') {
            echo '<div class="notice notice-error"><p>Stripe: legacy order metadata migration is incomplete. Check database permissions and reload before processing Stripe orders.</p></div>';
        }
    });
    // Existing order action forms and in-flight cron requests continue to dispatch.
    foreach (['rotation', 'daily', 'cron_auto_sync'] as $suffix) {
        add_action('mecom_gateway_stripe_' . $suffix, function () use ($suffix) {
            do_action('lazy_gateway_stripe_' . $suffix);
        });
    }
    foreach (['capture', 'cancel'] as $verb) {
        add_action('woocommerce_order_action_mecom_stripe_' . $verb . '_authorization_order', function ($order) use ($verb) {
            do_action('woocommerce_order_action_lazy_stripe_' . $verb . '_authorization_order', $order);
        });
    }
    add_action('plugins_loaded', function () {
        foreach (['WC_Lazy_Stripe' => 'WC_MEcom_Stripe', 'WC_Lazy_Gateway_Stripe' => 'WC_MEcom_Gateway_Stripe'] as $new => $old) {
            if (class_exists($new) && !class_exists($old, false)) class_alias($new, $old);
        }
    }, 20);
}

function lazy_stripe_clear_legacy_cron() {
    foreach (['rotation', 'daily', 'cron_auto_sync'] as $suffix) wp_clear_scheduled_hook('mecom_gateway_stripe_' . $suffix);
}

function lazy_stripe_shield_protocol() {
    $settings = get_option('woocommerce_lazy_stripe_settings', []);
    $protocol = $settings['shield_protocol'] ?? get_option('lazy_stripe_shield_protocol', 'lazy');
    $protocol = apply_filters('lazy_stripe_shield_protocol', $protocol);
    return $protocol === 'legacy' || $protocol === 'mecom' ? 'mecom' : 'lazy';
}

function lazy_stripe_protocol_field() {
    return [
        'title' => 'Shield protocol',
        'type' => 'select',
        'default' => lazy_stripe_shield_protocol() === 'mecom' ? 'legacy' : 'lazy',
        'description' => 'Use Lazy for updated shields. Keep Legacy when connecting to an older shield.',
        'desc_tip' => true,
        'options' => ['lazy' => 'Lazy', 'legacy' => 'Legacy (compatible with existing shields)'],
    ];
}
