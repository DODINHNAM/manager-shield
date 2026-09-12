<?php
/** Redirect checkout support; isolated from the Smart Button routes. */
defined('ABSPATH') || exit;

function wplazy_standard_fail($message, $code = 'paypal_standard_failed') {
    wp_send_json(['status' => 'failed', 'success' => false, 'error_detail' => $code, 'message' => $message], 502);
}

add_action('init', function () {
    if (isset($_GET['lazy-standard-return'])) {
        $state = sanitize_text_field(wp_unslash($_GET['state'] ?? ''));
        $saved = get_transient('lazy_pp_standard_' . hash('sha256', $state));
        if (!$saved || !hash_equals($saved['paypal_id'], (string) ($_GET['token'] ?? ''))) {
            wp_die('Invalid or expired PayPal return.', 'PayPal', ['response' => 403]);
        }
        $url = add_query_arg([
            'woo-lazy-return' => 1, 'order_id' => $saved['order_id'],
            'key' => $saved['key'], 'paymentId' => $saved['paypal_id'],
            'token' => $saved['paypal_id'], 'PayerID' => sanitize_text_field(wp_unslash($_GET['PayerID'] ?? '')),
            'error' => 0, 'cancel' => empty($_GET['cancel']) ? 0 : 1,
        ], $saved['merchant']);
        wp_redirect($url);
        exit;
    }
    $create = isset($_GET['lazy-process']) && ($_GET['request_type'] ?? '') === 'get_redirect_url';
    $capture = isset($_GET['lazy-pp-capture-payment']);
    $authorize = isset($_GET['lazy-pp-authorize-payment']);
    if (!$create && !$capture && !$authorize) return;
    wplazy_paypal_require_allowed_merchant();
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        wp_send_json(['status' => 'failed', 'success' => false, 'message' => 'POST required.'], 405);
    }
    $merchant = esc_url_raw(wp_unslash($_GET['merchant_site'] ?? ''));
    $order_id = absint($_GET['order_id'] ?? 0);
    if (!$order_id || !in_array(wp_parse_url($merchant, PHP_URL_SCHEME), ['https', 'http'], true)) {
        wplazy_standard_fail('Invalid merchant order.');
    }
    if ($create) {
        $intent = strtoupper(sanitize_text_field(wp_unslash($_GET['intent'] ?? '')));
        $unit = wp_unslash($_GET['purchase_units'] ?? []);
        $key = sanitize_text_field(wp_unslash($_GET['order_key'] ?? ''));
        if (!in_array($intent, ['CAPTURE', 'AUTHORIZE'], true) || !is_array($unit) || empty($unit['amount']) || !$key) {
            wplazy_standard_fail('Invalid PayPal checkout request.');
        }
        $state = wp_generate_password(48, false, false);
        $callback = add_query_arg(['lazy-standard-return' => 1, 'state' => $state], home_url('/'));
        $result = call_paypal_api([
            'intent' => $intent, 'purchase_units' => [$unit],
            'payment_source' => ['paypal' => ['experience_context' => [
                'brand_name' => 'merchant', 'user_action' => 'PAY_NOW',
                'return_url' => $callback, 'cancel_url' => add_query_arg('cancel', 1, $callback),
            ]]],
        ], 'POST', '/v2/checkout/orders');
        if (is_wp_error($result) || !is_array($result) || empty($result['id']) || !empty($result['name'])) {
            wplazy_standard_fail('Unable to create PayPal order.', is_array($result) ? ($result['name'] ?? 'paypal_create_failed') : 'paypal_create_failed');
        }
        $approval = '';
        foreach ($result['links'] ?? [] as $link) {
            if (in_array($link['rel'] ?? '', ['approve', 'payer-action'], true)) $approval = $link['href'] ?? '';
        }
        if (!$approval || wp_parse_url($approval, PHP_URL_SCHEME) !== 'https' || !preg_match('/(^|\.)paypal\.com$/i', wp_parse_url($approval, PHP_URL_HOST) ?: '')) {
            wplazy_standard_fail('PayPal approval URL is missing.');
        }
        $saved = ['merchant' => $merchant, 'order_id' => $order_id, 'key' => $key, 'paypal_id' => $result['id'], 'intent' => $intent, 'amount' => $unit['amount']];
        set_transient('lazy_pp_standard_' . hash('sha256', $state), $saved, DAY_IN_SECONDS);
        set_transient('lazy_pp_standard_order_' . $result['id'], $saved, DAY_IN_SECONDS);
        wp_send_json(['status' => 'success', 'redirect_link' => $approval, 'paypal_order_id' => $result['id']]);
    }
    $id = sanitize_text_field(wp_unslash($_GET['payment_id'] ?? ''));
    $saved = get_transient('lazy_pp_standard_order_' . $id);
    $intent = $authorize ? 'AUTHORIZE' : 'CAPTURE';
    if (!$saved || $saved['order_id'] !== $order_id || $saved['merchant'] !== $merchant || $saved['intent'] !== $intent || !hash_equals($saved['key'], (string) ($_GET['order_key'] ?? ''))) {
        wplazy_standard_fail('PayPal order does not match this checkout.', 'paypal_order_mismatch');
    }
    $endpoint = '/v2/checkout/orders/' . rawurlencode($id);
    $current = call_paypal_api(null, 'GET', $endpoint);
    if (!is_array($current) || !empty($current['name']) || ($current['intent'] ?? '') !== $intent || ($current['purchase_units'][0]['amount'] ?? []) != $saved['amount']) {
        wplazy_standard_fail('Unable to verify PayPal order.');
    }
    $collection = $authorize ? 'authorizations' : 'captures';
    $payment = $current['purchase_units'][0]['payments'][$collection][0] ?? [];
    if (!$payment) {
        if (($current['status'] ?? '') !== 'APPROVED') wplazy_standard_fail('PayPal order has not been approved.');
        $result = call_paypal_api(null, 'POST', $endpoint . ($authorize ? '/authorize' : '/capture'), substr(hash('sha256', $id . ':' . $intent), 0, 32));
        // Read back after both success and timeout to avoid blindly retrying a charge.
        $current = call_paypal_api(null, 'GET', $endpoint);
        $payment = is_array($current) ? ($current['purchase_units'][0]['payments'][$collection][0] ?? []) : [];
    }
    if (empty($payment['id']) || ($payment['status'] ?? '') !== ($authorize ? 'CREATED' : 'COMPLETED')) {
        wplazy_standard_fail('PayPal payment is not completed. Please check the order before retrying.', 'paypal_payment_not_completed');
    }
    wplazy_record_payment_event(array_merge(wplazy_paypal_proxy_payment_data($current, $authorize ? 'authorize' : 'capture'), [
        'merchant_domain' => $merchant, 'wc_order_id' => $order_id, 'payment_action' => $authorize ? 'authorize' : 'capture',
    ]));
    wp_send_json(['success' => true, 'transaction_id' => $payment['id'], 'seller_receivable_breakdown' => $payment['seller_receivable_breakdown'] ?? null]);
});
