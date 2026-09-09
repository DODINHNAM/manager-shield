<?php
defined('ABSPATH') || exit;

function wplazy_stripe_domain($value) {
    $value = strtolower(trim((string) $value));
    if ($value === '') return '';
    $parsed = parse_url(strpos($value, '://') === false ? 'https://' . $value : $value);
    $host = strtolower($parsed['host'] ?? preg_replace('/:\d+$/', '', $value));
    return preg_replace('/^www\./', '', $host);
}

function wplazy_stripe_get_webshield_config() {
    static $cache = null;
    if ($cache !== null) return $cache;
    if (!defined('WEBSHIELD_MANAGER_URL') || !defined('WEBSHIELD_ENCRYPTION_KEY') || !defined('WEBSHIELD_ENCRYPTION_IV')) {
        return $cache = new WP_Error('stripe_proxy_config', 'Stripe proxy manager constants are missing.');
    }
    $response = wp_remote_get(rtrim(WEBSHIELD_MANAGER_URL, '/') . '/api/get-webshield-config.php', [
        'timeout' => 20,
        'headers' => [
            'Origin' => rtrim(home_url('/'), '/'),
            'Cookie' => defined('WEBSHIELD_MANAGER_COOKIE') ? WEBSHIELD_MANAGER_COOKIE : '',
        ],
    ]);
    if (is_wp_error($response)) return $cache = $response;
    $data = json_decode(wp_remote_retrieve_body($response), true);
    if (!is_array($data) || empty($data['success'])) return $cache = new WP_Error('stripe_proxy_config', 'Invalid manager configuration response.');

    $stripe = null;
    foreach (($data['payment_configs'] ?? []) as $payment) {
        if (($payment['type'] ?? '') !== 'stripe' || empty($payment['config'])) continue;
        $ciphertext = base64_decode($payment['config'], true);
        $key = hex2bin(WEBSHIELD_ENCRYPTION_KEY);
        $iv = hex2bin(WEBSHIELD_ENCRYPTION_IV);
        $plain = ($ciphertext !== false && $key !== false && $iv !== false) ? openssl_decrypt($ciphertext, 'aes-256-cbc', $key, 0, $iv) : false;
        $stripe = $plain === false ? null : json_decode($plain, true);
        break;
    }
    if (!is_array($stripe) || empty($stripe['api_key']) || empty($stripe['publishable_key'])) {
        return $cache = new WP_Error('stripe_proxy_config', 'Stripe configuration is missing or invalid.');
    }
    return $cache = [
        'stripe_config' => $stripe,
        'whitelist' => array_values(array_filter(array_map('wplazy_stripe_domain', $data['whitelist'] ?? []))),
        'restrictions' => $data['restrictions'] ?? [],
    ];
}

function wplazy_stripe_origin_allowed($origin, array $whitelist) {
    $host = wplazy_stripe_domain($origin);
    return $host !== '' && in_array($host, $whitelist, true);
}

function wplazy_stripe_request_data() {
    $raw = file_get_contents('php://input');
    $json = json_decode($raw ?: '', true);
    return is_array($json) ? $json : [];
}

function wplazy_stripe_amount_minor($amount, $currency) {
    $currency = strtolower((string) $currency);
    $zero_decimal = ['bif', 'clp', 'djf', 'gnf', 'jpy', 'kmf', 'krw', 'mga', 'pyg', 'rwf', 'ugx', 'vnd', 'vuv', 'xaf', 'xof', 'xpf'];
    return absint(round((float) $amount * (in_array($currency, $zero_decimal, true) ? 1 : 100)));
}

function wplazy_stripe_restriction_denial($config, $params) {
    $all = [];
    foreach (['global', 'local'] as $scope) $all = array_merge($all, $config['restrictions'][$scope] ?? []);
    $email = strtolower(trim((string) ($params['customer_email'] ?? $params['email'] ?? '')));
    $zip = strtolower(trim((string) ($params['customer_zipcode'] ?? '')));
    $shipping = $params['shipping'] ?? [];
    $address = is_array($shipping) ? ($shipping['address'] ?? []) : [];
    $state = strtolower(trim((string) ($address['state'] ?? '')));
    $city = strtolower(trim((string) ($address['city'] ?? '')));
    $ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? ''));
    $matches = static function ($values, $value, $contains = false) {
        foreach ((array) $values as $item) {
            $item = strtolower(trim((string) $item));
            if ($item !== '' && ($contains ? strpos($value, $item) !== false : $value === $item)) return true;
        }
        return false;
    };
    if ($matches($all['blacklist_email'] ?? ($all['email'] ?? []), $email)) return 'customer_email_not_allow';
    if ($matches($all['blacklist_zipcode'] ?? ($all['zipcode'] ?? []), $zip, true)) return 'customer_zipcode_not_allow';
    if ($matches($all['blacklist_ip'] ?? ($all['ip'] ?? []), $ip)) return 'customer_ip_blacklisted';
    $states = $all['blacklist_state'] ?? ($all['state'] ?? []);
    $cities = $all['blacklist_city'] ?? ($all['city'] ?? []);
    if ($matches($states, $state) || $matches($cities, $city)) return 'states_cities_not_allow';
    return '';
}

function wplazy_stripe_api($method, $path, array $params = [], $config = null) {
    $config = $config ?: wplazy_stripe_get_webshield_config();
    if (is_wp_error($config)) return $config;
    $key = $config['stripe_config']['api_key'] ?? '';
    if (!$key) return new WP_Error('stripe_key', 'Stripe secret key is missing.');
    $args = ['method' => $method, 'timeout' => 60, 'headers' => ['Authorization' => 'Basic ' . base64_encode($key . ':')]];
    $url = 'https://api.stripe.com/v1' . $path;
    if ($method === 'GET') {
        if ($params) $url .= '?' . http_build_query($params, '', '&');
    } else {
        $args['headers']['Content-Type'] = 'application/x-www-form-urlencoded';
        $args['body'] = http_build_query($params, '', '&');
    }
    $response = wp_remote_request($url, $args);
    if (is_wp_error($response)) return $response;
    $body = json_decode(wp_remote_retrieve_body($response), true);
    if (!is_array($body)) return new WP_Error('stripe_response', 'Invalid Stripe response.');
    if (wp_remote_retrieve_response_code($response) >= 400 || isset($body['error'])) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[LazyShield Stripe Proxy] Stripe API error: ' . wp_json_encode([
                'path' => $path,
                'status' => wp_remote_retrieve_response_code($response),
                'body' => $body,
            ]));
        }
        return new WP_Error('stripe_api', $body['error']['message'] ?? 'Stripe request failed.', $body);
    }
    return $body;
}

function wplazy_stripe_json($data, $status = 200) {
    if (is_wp_error($data)) wp_send_json(['status' => 'failed', 'code' => $data->get_error_code(), 'message' => $data->get_error_message()], $status);
    wp_send_json($data, $status);
}

function wplazy_stripe_record_event($query, $intent, $action) {
    if (!defined('WEBSHIELD_MANAGER_URL') || !is_array($intent)) return;
    $providerId = (string) ($intent['id'] ?? '');
    $charge = $intent['latest_charge'] ?? [];
    $event = [
        'payment_provider' => 'stripe',
        'payment_action' => $action,
        'merchant_domain' => wplazy_stripe_domain($query['merchant_site'] ?? ''),
        'wc_order_id' => sanitize_text_field($query['order_id'] ?? ''),
        'provider_order_id' => $providerId,
        'provider_transaction_id' => is_array($charge) ? (string) ($charge['id'] ?? '') : (string) $charge,
        'status' => (string) ($intent['status'] ?? 'UNKNOWN'),
        'amount' => $intent['amount'] ?? null,
        'currency' => $intent['currency'] ?? '',
        'event_key' => hash('sha256', implode('|', [home_url('/'), $providerId, $action, $query['order_id'] ?? '', $intent['status'] ?? ''])),
        'occurred_at' => current_time('mysql', true),
    ];
    wp_remote_post(rtrim(WEBSHIELD_MANAGER_URL, '/') . '/api/record-payment-event.php', [
        'timeout' => 15,
        'blocking' => false,
        'headers' => ['Content-Type' => 'application/json', 'Origin' => rtrim(home_url('/'), '/')],
        'body' => wp_json_encode($event),
    ]);
}

function wplazy_stripe_payment_intent_params($query) {
    $amount = wplazy_stripe_amount_minor($query['amount'] ?? 0, $query['currency'] ?? 'usd');
    $currency = strtolower(sanitize_text_field($query['currency'] ?? 'usd'));
    $params = [
        'amount' => $amount,
        'currency' => $currency,
        'payment_method' => sanitize_text_field($query['payment_method_id'] ?? ''),
        'confirmation_method' => 'automatic',
        'confirm' => 'true',
        'capture_method' => in_array(($query['capture_method'] ?? 'automatic'), ['manual', 'automatic'], true) ? $query['capture_method'] : 'automatic',
        'metadata[order_id]' => sanitize_text_field($query['order_id'] ?? ''),
        'metadata[merchant_site]' => wplazy_stripe_domain($query['merchant_site'] ?? ''),
        'expand[0]' => 'latest_charge',
        'expand[1]' => 'latest_charge.balance_transaction',
    ];
    if (!empty($query['order_invoice'])) {
        $params['description'] = sanitize_text_field($query['order_invoice']);
        $params['metadata[invoice]'] = sanitize_text_field($query['order_invoice']);
    }
    if (!empty($query['order_items'])) {
        $items = is_array($query['order_items']) ? $query['order_items'] : [];
        $names = [];
        foreach ($items as $item) {
            if (!is_array($item) || empty($item['name'])) continue;
            $names[] = sanitize_text_field($item['name']) . (!empty($item['quantity']) ? ' x ' . absint($item['quantity']) : '');
        }
        $item_names = implode(', ', $names);
        if ($item_names !== '') {
            $params['description'] = substr($item_names, 0, 500);
            $params['metadata[item_names]'] = substr($item_names, 0, 500);
        }
    }
    if (!empty($query['customer_email'])) $params['receipt_email'] = sanitize_email($query['customer_email']);
    if (!empty($query['statement_descriptor'])) $params['statement_descriptor'] = sanitize_text_field($query['statement_descriptor']);
    $shipping = $query['shipping'] ?? [];
    if (is_array($shipping)) {
        foreach (['name', 'phone'] as $field) if (!empty($shipping[$field])) $params['shipping[' . $field . ']'] = sanitize_text_field($shipping[$field]);
        foreach (($shipping['address'] ?? []) as $field => $value) if ($value !== '') $params['shipping[address][' . $field . ']'] = sanitize_text_field($value);
    }
    return $params;
}

function wplazy_stripe_payment_response($intent) {
    if (!is_array($intent)) return $intent;
    return [
        'status' => 'success',
        'payment_intent' => $intent,
        'charge' => $intent['latest_charge'] ?? null,
    ];
}

add_action('init', function () {
    $actions = ['lazy-stripe-pe-v2-get-account-charge-status', 'lazy-stripe-pe-v2-make-payment', 'lazy-stripe-pe-v2-confirm-payment', 'lazy-stripe-pe-v2-get-payment-intent', 'lazy-stripe-pe-v2-capture-payment', 'lazy-stripe-pe-v2-cancel-payment', 'lazy-stripe-pe-v2-refund', 'cs-stripe-hosted-make-session', 'cs-stripe-hosted-verify-payment', 'cs-stripe-hosted-complete-payment', 'lazy-stripe-pe-v2-add-payment-history', 'lazy-stripe-pe-v2-add-order-detail', 'lazy-stripe-pe-v2-sync-tracking'];
    foreach ($actions as $action) if (isset($_GET[$action])) { wplazy_stripe_handle_action($action); exit; }
});

function wplazy_stripe_handle_action($action) {
    $config = wplazy_stripe_get_webshield_config();
    if (is_wp_error($config)) wplazy_stripe_json($config, 502);
    if ($action === 'lazy-stripe-pe-v2-get-account-charge-status') {
        $account = wplazy_stripe_api('GET', '/account', [], $config);
        wplazy_stripe_json(is_wp_error($account) ? ['status' => 'deactive'] : ['status' => 'active']);
    }
    $query = array_map(static fn($value) => is_array($value) ? $value : sanitize_text_field(wp_unslash($value)), $_GET);
    $merchant = wplazy_stripe_domain($query['merchant_site'] ?? '');
    $isPaymentAction = !in_array($action, ['lazy-stripe-pe-v2-add-payment-history', 'lazy-stripe-pe-v2-add-order-detail', 'lazy-stripe-pe-v2-sync-tracking'], true);
    if ($isPaymentAction && !wplazy_stripe_origin_allowed($merchant, $config['whitelist'] ?? [])) {
        wplazy_stripe_json(new WP_Error('domain_whitelist_not_allow', 'Merchant domain is not whitelisted.'), 403);
    }
    $denial = wplazy_stripe_restriction_denial($config, $query);
    if ($denial && in_array($action, ['lazy-stripe-pe-v2-make-payment', 'cs-stripe-hosted-make-session'], true)) wplazy_stripe_json(new WP_Error($denial, 'Payment is not allowed by the active restrictions.'), 403);
    $stripe_config = $config['stripe_config'] ?? [];
    if (!empty($stripe_config['enable_max_order_value']) && isset($stripe_config['max_order_value']) && (float) ($query['amount'] ?? 0) > (float) $stripe_config['max_order_value'] && in_array($action, ['lazy-stripe-pe-v2-make-payment', 'cs-stripe-hosted-make-session'], true)) {
        wplazy_stripe_json(new WP_Error('order_total_not_allow', 'Order value exceeds the configured Stripe limit.'), 403);
    }

    if ($action === 'lazy-stripe-pe-v2-make-payment') {
        $id = sanitize_text_field($query['payment_intent'] ?? '');
        $intent = $id ? wplazy_stripe_api('POST', '/payment_intents/' . rawurlencode($id), wplazy_stripe_payment_intent_params($query), $config) : wplazy_stripe_api('POST', '/payment_intents', wplazy_stripe_payment_intent_params($query), $config);
        if (!is_wp_error($intent)) wplazy_stripe_record_event($query, $intent, 'payment');
        wplazy_stripe_json(is_wp_error($intent) ? $intent : wplazy_stripe_payment_response($intent));
    }
    if ($action === 'lazy-stripe-pe-v2-get-payment-intent' || $action === 'lazy-stripe-pe-v2-confirm-payment') {
        $id = sanitize_text_field($query['payment_intent_id'] ?? '');
        $intent = wplazy_stripe_api('GET', '/payment_intents/' . rawurlencode($id), [], $config);
        wplazy_stripe_json(is_wp_error($intent) ? $intent : wplazy_stripe_payment_response($intent));
    }
    if ($action === 'lazy-stripe-pe-v2-capture-payment') {
        $intent = wplazy_stripe_api('POST', '/payment_intents/' . rawurlencode($query['payment_intent_id'] ?? '') . '/capture', [], $config);
        if (!is_wp_error($intent)) wplazy_stripe_record_event($query, $intent, 'capture');
        wplazy_stripe_json(is_wp_error($intent) ? $intent : wplazy_stripe_payment_response($intent));
    }
    if ($action === 'lazy-stripe-pe-v2-cancel-payment') {
        $intent = wplazy_stripe_api('POST', '/payment_intents/' . rawurlencode($query['payment_intent_id'] ?? '') . '/cancel', [], $config);
        if (!is_wp_error($intent)) wplazy_stripe_record_event($query, $intent, 'cancel');
        wplazy_stripe_json(is_wp_error($intent) ? $intent : wplazy_stripe_payment_response($intent));
    }
    if ($action === 'lazy-stripe-pe-v2-refund') {
        $refund = wplazy_stripe_api('POST', '/refunds', array_filter(['payment_intent' => $query['transaction_id'] ?? '', 'amount' => absint($query['amount'] ?? 0), 'reason' => in_array($query['reason'] ?? '', ['duplicate', 'fraudulent', 'requested_by_customer'], true) ? $query['reason'] : null]), $config);
        if (!is_wp_error($refund)) wplazy_stripe_record_event($query, $refund, 'refund');
        wplazy_stripe_json(is_wp_error($refund) ? $refund : ['status' => 'success', 'refund_obj' => $refund, 'charge_obj' => null]);
    }
    if ($action === 'cs-stripe-hosted-make-session') {
        $params = ['mode' => 'payment', 'success_url' => add_query_arg(['cs_handle_stripe_checkout_session_success' => 1, 'order_id' => $query['order_id'] ?? '', 'stripe_session_id' => '{CHECKOUT_SESSION_ID}'], home_url('/')), 'cancel_url' => add_query_arg(['cs_handle_stripe_checkout_session_cancelled' => 1, 'order_id' => $query['order_id'] ?? ''], home_url('/'))];
        if (!empty($query['order_invoice'])) $params['client_reference_id'] = sanitize_text_field($query['order_invoice']);
        $params['invoice_creation[enabled]'] = 'true';
        if (!empty($query['order_invoice'])) $params['invoice_creation[invoice_data][metadata][order_invoice]'] = sanitize_text_field($query['order_invoice']);
        if (!empty($query['capture_method']) && $query['capture_method'] === 'manual') $params['payment_intent_data[capture_method]'] = 'manual';
        if (!empty($query['merchant_site'])) $params['payment_intent_data[metadata][merchant_site]'] = wplazy_stripe_domain($query['merchant_site']);
        if (!empty($query['order_id'])) $params['payment_intent_data[metadata][order_id]'] = sanitize_text_field($query['order_id']);
        $params['line_items[0][price_data][currency]'] = strtolower($query['currency'] ?? 'usd');
        $params['line_items[0][price_data][unit_amount]'] = wplazy_stripe_amount_minor($query['amount'] ?? 0, $query['currency'] ?? 'usd');
        $params['line_items[0][price_data][product_data][name]'] = substr(sanitize_text_field($query['product_names'] ?? 'Order'), 0, 250);
        $params['line_items[0][quantity]'] = 1;
        if (!empty($query['customer_email'])) $params['customer_email'] = sanitize_email($query['customer_email']);
        $session = wplazy_stripe_api('POST', '/checkout/sessions', $params, $config);
        wplazy_stripe_json(is_wp_error($session) ? $session : ['status' => 'success', 'payment_session' => ['id' => $session['id'] ?? '', 'url' => $session['url'] ?? '']]);
    }
    if ($action === 'cs-stripe-hosted-verify-payment' || $action === 'cs-stripe-hosted-complete-payment') {
        $session = wplazy_stripe_api('GET', '/checkout/sessions/' . rawurlencode($query['stripe_session_id'] ?? '') . '?expand[]=payment_intent', [], $config);
        if (is_wp_error($session)) wplazy_stripe_json($session, 502);
        $intent = $session['payment_intent'] ?? [];
        wplazy_stripe_json(['status' => 'success', 'payment_intent' => $intent, 'session' => $session]);
    }
    wplazy_stripe_json(['status' => 'success']);
}
