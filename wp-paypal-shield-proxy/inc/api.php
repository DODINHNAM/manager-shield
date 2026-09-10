<?php
/**
 * API functions for the PayPal integration.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

defined('ABSPATH') || exit;

function wplazy_paypal_proxy_normalize_legacy_request_keys( &$request ) {
    foreach ( array_keys( $request ) as $key ) {
        $lazy_key = str_replace( 'me' . 'com', 'lazy', $key );
        if ( $lazy_key !== $key && ! isset( $request[ $lazy_key ] ) ) {
            $request[ $lazy_key ] = $request[ $key ];
        }
    }
}

wplazy_paypal_proxy_normalize_legacy_request_keys( $_GET );
wplazy_paypal_proxy_normalize_legacy_request_keys( $_POST );

/**
 * Create a PayPal order.
 */
function create_paypal_order( WP_REST_Request $request ) {
    $params = $request->get_json_params(); // handles application/json
    // if ( empty( $params['order'] ) || empty( $params['merchant_token'] ) ) {
    if ( empty( $params['order'] ) ) {

        return new WP_REST_Response( 'Invalid request', 400 );
    }

    $order_data = $params['order'];
    $intent = strtoupper( sanitize_text_field( $order_data['intent'] ?? '' ) );
    if ( ! in_array( $intent, [ 'CAPTURE', 'AUTHORIZE' ], true ) ) {
        return new WP_REST_Response([
            'code' => 'invalid_paypal_intent',
            'message' => 'PayPal order intent must be CAPTURE or AUTHORIZE.',
        ], 400);
    }
    $order_data['intent'] = $intent;
    // $merchant_token = sanitize_text_field( $params['merchant_token'] );

    // Validate merchant token if needed

    $response = call_paypal_api( $order_data, 'POST', '/v2/checkout/orders' );

    if ( is_wp_error( $response ) ) {
        return new WP_REST_Response( $response->get_error_message(), 500 );
    }

    if ( ! empty( $response['name'] ) || ! empty( $response['error'] ) ) {
        return new WP_REST_Response([
            'status' => 'failed',
            'code' => $response['name'] ?? 'paypal_order_create_failed',
            'message' => $response['message'] ?? 'PayPal order creation failed.',
            'details' => array_values(array_filter($response['details'] ?? [], 'is_array')),
            'debug_id' => $response['debug_id'] ?? null,
        ], 502);
    }

    // Nếu PayPal không trả id, ghi log debug để kiểm tra nội dung trả về
    if ( empty( $response['id'] ) ) {
        // Ghi vào debug.log (WP_DEBUG_LOG phải bật)
        return new WP_REST_Response( 'Invalid PayPal response', 502 );
    }
    return rest_ensure_response( [ 'order_id' => $response['id'] ] );
}

/**
 * Call the PayPal API.
 *
 * @param array $order_data The order data.
 * @return array|WP_Error The response from the PayPal API or WP_Error on failure.
 */
function call_paypal_api($order_data, $method = 'POST', $endpoint = '/v2/checkout/orders') {
    // Get PayPal credentials from Webshield config API
    $webshield_config = get_webshield_config();

    if ( is_wp_error( $webshield_config ) ) {
        error_log( '[wp-paypal] Error fetching Webshield config for PayPal credentials: ' . $webshield_config->get_error_message() );
        return new WP_Error( 'paypal_config_error', 'Failed to retrieve PayPal configuration.', array( 'details' => $webshield_config->get_error_message() ) );
    }

    if ( empty( $webshield_config['paypal_config'] ) ) {
        error_log( '[wp-paypal] PayPal configuration not found in Webshield config.' );
        return new WP_Error( 'paypal_config_missing', 'PayPal configuration is missing from Webshield config.' );
    }

    $paypal_credentials = $webshield_config['paypal_config'];

    $environment = $paypal_credentials['environment'];
    $client_id = $paypal_credentials['client_id'];
    $secret = $paypal_credentials['secret_id']; // Note: The API returns 'secret_id', not 'secret'

    // Set API base URL based on environment from API
    $base = ($environment === 'live') ? 'https://api.paypal.com' : 'https://api.sandbox.paypal.com';

    // The check for empty client_id or secret is now implicitly handled by get_webshield_config's validation
    // but a final check here is harmless.
    if ( empty( $client_id ) || empty( $secret ) ) {
        error_log( '[wp-paypal] Missing PayPal credentials after fetching from Webshield config.' );
        return new WP_Error( 'missing_credentials_api', 'Missing PayPal credentials from API config.' );
    }


    // 1) Lấy access token
    $token_args = [
        'method'      => 'POST',
        'headers'     => [
            'Authorization' => 'Basic ' . base64_encode( $client_id . ':' . $secret ),
            'Content-Type'  => 'application/x-www-form-urlencoded',
        ],
        'body'        => 'grant_type=client_credentials',
        'timeout'     => 20,
    ];

    $token_resp = wp_remote_post( $base . '/v1/oauth2/token', $token_args );

    if ( is_wp_error( $token_resp ) ) {
        return $token_resp;
    }

    $token_code = wp_remote_retrieve_response_code( $token_resp );
    $token_body = wp_remote_retrieve_body( $token_resp );

    $token_decoded = json_decode( $token_body, true );
    if ( empty( $token_decoded['access_token'] ) ) {
        return new WP_Error( 'token_failed', 'Failed to obtain PayPal access token', $token_decoded );
    }
    $access_token = $token_decoded['access_token'];

    // 2) Call PayPal API with Bearer token
    $url = $base . $endpoint;
    $args = [
        'method'  => $method,
        'headers' => [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $access_token,
        ],
        'timeout' => 30,
    ];

    if ($order_data) {
        $args['body'] = wp_json_encode($order_data);
    }

    $response = wp_remote_request( $url, $args );

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    $code = wp_remote_retrieve_response_code( $response );
    $body = wp_remote_retrieve_body( $response );

    $decoded = json_decode( $body, true );
    return $decoded;
}

function wplazy_paypal_proxy_domain($value) {
    $value = strtolower(trim((string) $value));
    if ($value === '') {
        return '';
    }
    $parsed = parse_url(strpos($value, '://') === false ? 'https://' . $value : $value);
    return strtolower($parsed['host'] ?? preg_replace('/:\d+$/', '', $value));
}

function wplazy_paypal_proxy_payment_data($response, $action) {
    if (!is_array($response)) {
        return [
            'provider_order_id' => '',
            'provider_transaction_id' => '',
            'status' => 'UNKNOWN',
            'amount' => null,
            'currency' => '',
            'paypal_fee' => null,
            'payout' => null,
        ];
    }
    $units = $response['purchase_units'][0] ?? [];
    $payments = $units['payments'] ?? [];
    $payment = $action === 'authorize'
        ? ($payments['authorizations'][0] ?? [])
        : ($payments['captures'][0] ?? []);
    $breakdown = $payment['seller_receivable_breakdown'] ?? ($response['seller_receivable_breakdown'] ?? []);

    return [
        'provider_order_id' => $response['id'] ?? '',
        'provider_transaction_id' => $payment['id'] ?? '',
        'status' => $payment['status'] ?? ($response['status'] ?? 'UNKNOWN'),
        'amount' => $payment['amount']['value'] ?? ($units['amount']['value'] ?? null),
        'currency' => $payment['amount']['currency_code'] ?? ($units['amount']['currency_code'] ?? ''),
        'fee' => $breakdown['paypal_fee']['value'] ?? null,
        'payout' => $breakdown['net_amount']['value'] ?? null,
    ];
}

function wplazy_record_payment_event($event) {
    if (!defined('WEBSHIELD_MANAGER_URL')) {
        return;
    }

    $merchantDomain = wplazy_paypal_proxy_domain($event['merchant_domain'] ?? '');
    $event['payment_provider'] = $event['payment_provider'] ?? 'paypal';
    $action = (string) ($event['payment_action'] ?? '');
    $providerOrderId = (string) ($event['provider_order_id'] ?? '');
    $providerTransactionId = (string) ($event['provider_transaction_id'] ?? '');
    $status = (string) ($event['status'] ?? 'UNKNOWN');
    $event['event_key'] = hash('sha256', implode('|', [home_url('/'), $merchantDomain, $event['wc_order_id'] ?? '', $providerOrderId, $providerTransactionId, $action, $status]));
    $event['merchant_domain'] = $merchantDomain;
    $event['occurred_at'] = current_time('mysql', true);

    $response = wp_remote_post(WEBSHIELD_MANAGER_URL . '/api/record-payment-event.php', [
        'timeout' => 15,
        'blocking' => false,
        'headers' => [
            'Content-Type' => 'application/json',
            'Origin' => rtrim(home_url('/'), '/'),
        ],
        'body' => wp_json_encode($event),
    ]);
    if (is_wp_error($response) && defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[wp-paypal] Payment event record failed: ' . $response->get_error_message());
    }
}

add_action('init', function() {
    $actions = [
        'lazy-paypal-get-order',
        'lazy-paypal-capture-order',
        'lazy-paypal-authorize-order',
        'lazy-pp-refund',
        'lazy-pp-capture-authorization-payment',
        'lazy-pp-cancel-authorization-payment',
        'lazy-pp-reauthorize-authorization-payment',
    ];
    foreach ($actions as $action) {
        if (isset($_GET[$action])) {
            wplazy_paypal_require_allowed_merchant();
            break;
        }
    }
    if (isset($_GET['lazy-paypal-get-order'])) {
        handle_get_order();
    }
    if (isset($_GET['lazy-paypal-capture-order'])) {
        handle_capture_order();
    }
    if (isset($_GET['lazy-paypal-authorize-order'])) {
        handle_authorize_order();
    }
    if (isset($_GET['lazy-pp-refund'])) {
        handle_refund();
    }
    if (isset($_GET['lazy-pp-capture-authorization-payment'])) {
        handle_capture_authorization_payment();
    }
    if (isset($_GET['lazy-pp-cancel-authorization-payment'])) {
        handle_cancel_authorization_payment();
    }
    if (isset($_GET['lazy-pp-reauthorize-authorization-payment'])) {
        handle_reauthorize_authorization_payment();
    }
});

function handle_get_order() {
    // Logic to handle 'lazy-paypal-get-order' will be added here.
    // This will involve getting the order details from PayPal.
    $order_id = json_decode(file_get_contents('php://input'))->order_id;
    $response = call_paypal_api(null, 'GET', '/v2/checkout/orders/' . $order_id);
    wp_send_json(['status' => 'success', 'order' => $response]);
}

function handle_capture_order() {
    $order_id = $_GET['pp_order_id'];
    $payload = json_decode(file_get_contents('php://input'), true);
    $update = wplazy_update_paypal_order($order_id, $payload['purchase_units'] ?? []);
    if (is_wp_error($update)) {
        wp_send_json([
            'status' => 'failed',
            'code' => $update->get_error_code() ?: 'paypal_order_update_failed',
            'message' => 'Unable to update PayPal order before capture.',
        ], 502);
    }
    $capture = call_paypal_api(null, 'POST', '/v2/checkout/orders/' . rawurlencode($order_id) . '/capture');
    if (is_wp_error($capture) || !is_array($capture) || !empty($capture['name']) || !empty($capture['error'])) {
        wp_send_json([
            'status' => 'failed',
            'code' => is_array($capture) ? ($capture['name'] ?? 'paypal_capture_failed') : 'paypal_capture_failed',
            'message' => is_array($capture) ? ($capture['message'] ?? 'PayPal capture failed.') : $capture->get_error_message(),
            'details' => is_array($capture) ? $capture : [],
        ], 502);
    }
    $response = call_paypal_api(null, 'GET', '/v2/checkout/orders/' . $order_id);
    if (is_wp_error($response) || !is_array($response) || !empty($response['name']) || !empty($response['error'])) {
        wp_send_json([
            'status' => 'failed',
            'code' => is_array($response) ? ($response['name'] ?? 'paypal_order_fetch_failed') : 'paypal_order_fetch_failed',
            'message' => is_array($response) ? ($response['message'] ?? 'Unable to retrieve the PayPal order.') : $response->get_error_message(),
            'details' => is_array($response) ? $response : [],
        ], 502);
    }
    $paymentData = wplazy_paypal_proxy_payment_data($response, 'capture');
    if (empty($paymentData['provider_transaction_id']) || strtoupper((string) ($paymentData['status'] ?? '')) !== 'COMPLETED') {
        wp_send_json([
            'status' => 'failed',
            'code' => 'paypal_capture_not_completed',
            'message' => 'PayPal did not return a completed capture.',
            'details' => $response,
        ], 502);
    }
    wplazy_record_payment_event(array_merge($paymentData, [
        'merchant_domain' => $_GET['merchant_site'] ?? '',
        'wc_order_id' => $_GET['order_id'] ?? '',
        'payment_action' => 'capture',
    ]));
    wp_send_json(['status' => 'success', 'order' => $response]);
}

function handle_authorize_order() {
    $order_id = $_GET['pp_order_id'];
    $payload = json_decode(file_get_contents('php://input'), true);
    $update = wplazy_update_paypal_order($order_id, $payload['purchase_units'] ?? []);
    if (is_wp_error($update)) {
        wp_send_json([
            'status' => 'failed',
            'code' => $update->get_error_code() ?: 'paypal_order_update_failed',
            'message' => 'Unable to update PayPal order before authorization.',
        ], 502);
    }
    $current = call_paypal_api(null, 'GET', '/v2/checkout/orders/' . rawurlencode($order_id));
    if (is_wp_error($current) || !is_array($current) || !empty($current['name']) || !empty($current['error'])) {
        wp_send_json([
            'status' => 'failed',
            'code' => is_array($current) ? ($current['name'] ?? 'paypal_order_fetch_failed') : 'paypal_order_fetch_failed',
            'message' => is_array($current) ? ($current['message'] ?? 'Unable to retrieve the PayPal order.') : $current->get_error_message(),
            'details' => is_array($current) ? $current : [],
        ], 502);
    }
    if (strtoupper((string) ($current['intent'] ?? '')) !== 'AUTHORIZE') {
        wp_send_json([
            'status' => 'failed',
            'code' => 'paypal_order_intent_mismatch',
            'message' => 'The PayPal order was not created with AUTHORIZE intent.',
            'details' => [
                'order_status' => $current['status'] ?? '',
                'order_intent' => $current['intent'] ?? '',
            ],
        ], 409);
    }
    $authorization = call_paypal_api(null, 'POST', '/v2/checkout/orders/' . rawurlencode($order_id) . '/authorize');
    if (is_wp_error($authorization) || !is_array($authorization) || !empty($authorization['name']) || !empty($authorization['error'])) {
        wp_send_json([
            'status' => 'failed',
            'code' => is_array($authorization) ? ($authorization['name'] ?? 'paypal_authorize_failed') : 'paypal_authorize_failed',
            'message' => is_array($authorization) ? ($authorization['message'] ?? 'PayPal authorization failed.') : $authorization->get_error_message(),
            'details' => is_array($authorization) ? $authorization : [],
        ], 502);
    }
    $response = call_paypal_api(null, 'GET', '/v2/checkout/orders/' . $order_id);
    if (is_wp_error($response) || !is_array($response) || !empty($response['name']) || !empty($response['error'])) {
        wp_send_json([
            'status' => 'failed',
            'code' => is_array($response) ? ($response['name'] ?? 'paypal_order_fetch_failed') : 'paypal_order_fetch_failed',
            'message' => is_array($response) ? ($response['message'] ?? 'Unable to retrieve the PayPal order.') : $response->get_error_message(),
            'details' => is_array($response) ? $response : [],
        ], 502);
    }
    $paymentData = wplazy_paypal_proxy_payment_data($response, 'authorize');
    if (empty($paymentData['provider_transaction_id']) || strtoupper((string) ($paymentData['status'] ?? '')) !== 'CREATED') {
        wp_send_json([
            'status' => 'failed',
            'code' => 'paypal_authorization_not_created',
            'message' => 'PayPal did not return a completed authorization.',
            'details' => $response,
        ], 502);
    }
    wplazy_record_payment_event(array_merge($paymentData, [
        'merchant_domain' => $_GET['merchant_site'] ?? '',
        'wc_order_id' => $_GET['order_id'] ?? '',
        'payment_action' => 'authorize',
    ]));
    wp_send_json(['status' => 'success', 'order' => $response]);
}

function wplazy_update_paypal_order($order_id, $purchase_units) {
    $unit = is_array($purchase_units) && isset($purchase_units[0])
        ? $purchase_units[0]
        : (is_array($purchase_units) ? $purchase_units : []);
    if (!$order_id || !$unit) {
        return true;
    }

    $current = call_paypal_api(null, 'GET', '/v2/checkout/orders/' . rawurlencode($order_id));
    if (is_wp_error($current)) {
        return $current;
    }
    $current_unit = $current['purchase_units'][0] ?? [];
    $patches = [];

    if (!empty($unit['invoice_id'])) {
        $patches[] = [
            'op' => array_key_exists('invoice_id', $current_unit) ? 'replace' : 'add',
            // PayPal addresses purchase units by reference_id for PATCH requests.
            // A single unit without an explicit reference_id is named "default".
            'path' => "/purchase_units/@reference_id=='default'/invoice_id",
            'value' => (string) $unit['invoice_id'],
        ];
    }
    if (!$patches) {
        return true;
    }

    $updated = call_paypal_api($patches, 'PATCH', '/v2/checkout/orders/' . rawurlencode($order_id));
    if (is_wp_error($updated)) {
        return $updated;
    }
    if (isset($updated['name']) && isset($updated['message'])) {
        return new WP_Error('paypal_order_update_failed', $updated['message'], $updated);
    }
    return true;
}

function handle_refund() {
    $transaction_id = $_GET['TRANSACTIONID'];
    $response = call_paypal_api(null, 'POST', '/v2/payments/captures/' . $transaction_id . '/refund');
    if (!is_array($response)) { $response = []; }
    wplazy_record_payment_event([
        'merchant_domain' => $_GET['merchant_site'] ?? '',
        'wc_order_id' => $_GET['order_id'] ?? '',
        'provider_transaction_id' => $transaction_id,
        'payment_provider' => 'paypal',
        'payment_action' => 'refund',
        'status' => $response['status'] ?? 'UNKNOWN',
        'amount' => $response['amount']['value'] ?? null,
        'currency' => $response['amount']['currency_code'] ?? '',
        'error_message' => $response['message'] ?? '',
    ]);
    wp_send_json(['status' => 'success', 'data' => $response]);
}

function handle_capture_authorization_payment() {
    $payment_id = $_GET['payment_id'];
    $response = call_paypal_api(null, 'POST', '/v2/payments/authorizations/' . $payment_id . '/capture');
    if (!is_array($response)) { $response = []; }
    wplazy_record_payment_event([
        'merchant_domain' => $_GET['merchant_site'] ?? '',
        'wc_order_id' => $_GET['order_id'] ?? '',
        'provider_transaction_id' => $response['id'] ?? $payment_id,
        'payment_provider' => 'paypal',
        'payment_action' => 'capture_authorization',
        'status' => $response['status'] ?? 'UNKNOWN',
        'amount' => $response['amount']['value'] ?? null,
        'currency' => $response['amount']['currency_code'] ?? '',
        'error_message' => $response['message'] ?? '',
    ]);
    wp_send_json(['status' => 'success', 'data' => $response]);
}

function handle_cancel_authorization_payment() {
    $payment_id = $_GET['payment_id'];
    $response = call_paypal_api(null, 'POST', '/v2/payments/authorizations/' . $payment_id . '/void');
    if (!is_array($response)) { $response = []; }
    wplazy_record_payment_event([
        'merchant_domain' => $_GET['merchant_site'] ?? '',
        'wc_order_id' => $_GET['order_id'] ?? '',
        'provider_transaction_id' => $payment_id,
        'payment_provider' => 'paypal',
        'payment_action' => 'void_authorization',
        'status' => $response['status'] ?? 'UNKNOWN',
        'error_message' => $response['message'] ?? '',
    ]);
    wp_send_json(['status' => 'success', 'data' => $response]);
}

function handle_reauthorize_authorization_payment() {
    $payment_id = $_GET['payment_id'];
    $response = call_paypal_api(null, 'POST', '/v2/payments/authorizations/' . $payment_id . '/reauthorize');
    if (!is_array($response)) { $response = []; }
    wplazy_record_payment_event([
        'merchant_domain' => $_GET['merchant_site'] ?? '',
        'wc_order_id' => $_GET['order_id'] ?? '',
        'provider_transaction_id' => $response['id'] ?? $payment_id,
        'payment_provider' => 'paypal',
        'payment_action' => 'reauthorize',
        'status' => $response['status'] ?? 'UNKNOWN',
        'amount' => $response['amount']['value'] ?? null,
        'currency' => $response['amount']['currency_code'] ?? '',
        'error_message' => $response['message'] ?? '',
    ]);
    wp_send_json(['status' => 'success', 'data' => $response]);
}

// Hook for the REST API endpoint
add_action('rest_api_init', function () {
    register_rest_route('cs', '/create-paypal-order', [
        'methods' => 'POST',
        'callback' => 'create_paypal_order',
        'permission_callback' => 'wplazy_paypal_create_order_permission_check',
    ]);
});
add_action( 'rest_api_init', function() {
    register_rest_route( 'cs', '/woo-paypal-get-form', array(
        'methods'  => WP_REST_Server::READABLE,
        'callback' => 'wplazy_paypal_proxy_rest_get_form',
        // Cho phép truy cập công khai; thay đổi nếu cần kiểm quyền
        'permission_callback' => '__return_true',
    ) );
} );

function wplazy_paypal_proxy_rest_get_form( WP_REST_Request $request ) {
    // Lấy và sanitize tham số
    $paypal_checkout = $request->get_param( 'paypal_checkout' );
    $intent = $request->get_param( 'intent' );
    $currency = $request->get_param( 'currency' );

    if ( empty( $paypal_checkout ) ) {
        return new WP_REST_Response( 'Missing paypal_checkout', 400 );
    }

    // Đặt biến toàn cục để template có thể dùng nếu cần
    $GLOBALS['wpme_paypal_params'] = array(
        'paypal_checkout' => sanitize_text_field( $paypal_checkout ),
        'intent'          => sanitize_text_field( $intent ),
        'currency'        => sanitize_text_field( $currency ),
    );

    // Render cùng output như shortcode [paypal_checkout]
    ob_start();
    echo do_shortcode( '[paypal_checkout]' );
    $html = ob_get_clean();

    // Trả về raw HTML (bypass JSON serialization của REST API)
    status_header(200);
    header( 'Content-Type: text/html; charset=utf-8' );
    echo $html;
    exit;
}

/**
 * Calls the Webshield Whitelist API (now via get_webshield_config).
 * Caches the result for the duration of the request.
 *
 * @return array|WP_Error The decoded JSON response or WP_Error on failure.
 */
function get_webshield_whitelist() {
    static $cached_whitelist = null; // Static variable for caching

    if ( $cached_whitelist !== null ) {
        return $cached_whitelist; // Return cached value if available
    }

    $full_config = get_webshield_config();

    if ( is_wp_error( $full_config ) ) {
        $cached_whitelist = $full_config;
        return $cached_whitelist;
    }

    $cached_whitelist = isset($full_config['whitelist']) ? $full_config['whitelist'] : [];
    return $cached_whitelist;
}

/**
 * Fetches and processes the entire Webshield configuration (including decrypted PayPal config and whitelist).
 * Caches the result for the duration of the request.
 *
 * @return array|WP_Error|null The full Webshield config, WP_Error on failure, or null if not found.
 *                              The returned array will contain 'whitelist' and 'paypal_config' keys.
 */
function get_webshield_config() {
    static $cached_full_config = null; // Static variable for caching

    if ( $cached_full_config !== null ) {
        return $cached_full_config; // Return cached value if available
    }

    if ( ! defined('WEBSHIELD_MANAGER_URL') ) {
        error_log( '[Webshield Config] WEBSHIELD_MANAGER_URL is not defined in wp-config.php.' );
        $cached_full_config = new WP_Error( 'missing_config', 'Webshield Manager URL is not configured.' );
        return $cached_full_config;
    }
    $url = WEBSHIELD_MANAGER_URL . '/api/get-webshield-config.php'; // Call the config endpoint
    $args = array(
        'method'  => 'GET',
        'headers' => array(
            'Origin' => rtrim(home_url('/'), '/'),
        ),
        'timeout' => 15,
    );

    $response = wp_remote_get( $url, $args );

    if ( is_wp_error( $response ) ) {
        error_log( '[Webshield Config] Error fetching config: ' . $response->get_error_message() );
        $cached_full_config = $response;
        return $cached_full_config;
    }

    $body = wp_remote_retrieve_body( $response );
    $data = json_decode( $body, true );

    if ( json_last_error() !== JSON_ERROR_NONE ) {
        error_log( '[Webshield Config] JSON decode error: ' . json_last_error_msg() );
        $cached_full_config = new WP_Error( 'json_decode_error', 'Failed to decode JSON response from Webshield Config API.' );
        return $cached_full_config;
    }

    if ( empty( $data['success'] ) ) {
        error_log( '[Webshield Config] Invalid API response structure (success=false).' );
        $cached_full_config = new WP_Error( 'invalid_response', 'Invalid API response structure from Webshield Config API.' );
        return $cached_full_config;
    }

    $processed_config = [
        'web_shield' => isset($data['web_shield']) ? $data['web_shield'] : [],
        'whitelist' => isset($data['whitelist']) ? $data['whitelist'] : [],
        'paypal_config' => null, // Initialize PayPal config as null
    ];

    // Process payment_configs if available
    if ( ! empty( $data['payment_configs'] ) ) {
        $encrypted_config = null;
        foreach ( $data['payment_configs'] as $config ) {
            if ( isset( $config['type'] ) && $config['type'] === 'paypal' && isset( $config['config'] ) ) {
                $encrypted_config = $config['config'];
                break;
            }
        }

        if ( $encrypted_config ) {
            // Decryption logic
            if ( ! defined('WEBSHIELD_ENCRYPTION_KEY') || ! defined('WEBSHIELD_ENCRYPTION_IV') ) {
                error_log( '[Webshield Config] Encryption key or IV is not defined in wp-config.php.' );
                $cached_full_config = new WP_Error( 'missing_encryption_config', 'Encryption key or IV is not configured.' );
                return $cached_full_config;
            }

            $encryption_key = hex2bin(WEBSHIELD_ENCRYPTION_KEY);
            $encryption_iv = hex2bin(WEBSHIELD_ENCRYPTION_IV);

            $decoded_data = base64_decode( $encrypted_config );
            $decrypted = openssl_decrypt( $decoded_data, 'aes-256-cbc', $encryption_key, 0, $encryption_iv );

            if ( $decrypted === false ) {
                error_log( '[Webshield Config] Failed to decrypt PayPal config.' );
                $cached_full_config = new WP_Error( 'decryption_failed', 'Failed to decrypt PayPal config.' );
                return $cached_full_config;
            }

            $decrypted_paypal_config = json_decode( $decrypted, true );

            if ( json_last_error() !== JSON_ERROR_NONE ) {
                error_log( '[Webshield Config] Failed to decode decrypted PayPal config JSON.' );
                $cached_full_config = new WP_Error( 'decrypted_json_decode_error', 'Failed to decode decrypted PayPal config JSON.' );
                return $cached_full_config;
            }

            // Validate required fields for PayPal payment
            if ( ! isset( $decrypted_paypal_config['environment'] ) ||
                 ! isset( $decrypted_paypal_config['client_id'] ) ||
                 ! isset( $decrypted_paypal_config['secret_id'] ) ) {
                error_log( '[Webshield Config] Decrypted PayPal config is missing required fields (environment, client_id, or secret_id).' );
                $cached_full_config = new WP_Error( 'invalid_paypal_config', 'Decrypted PayPal configuration is incomplete or invalid.' );
                return $cached_full_config;
            }
            $processed_config['paypal_config'] = $decrypted_paypal_config;
        }
    }

    $cached_full_config = $processed_config; // Cache the successful result
    return $processed_config;
}

/**
 * Permission callback to check that the request origin is allowed for this shield.
 *
 * @param WP_REST_Request $request The current REST API request.
 * @return bool|WP_Error True if the origin is whitelisted, WP_Error otherwise.
 */
function wplazy_paypal_proxy_whitelist_permission_check( WP_REST_Request $request ) {
    $origin = $request->get_header( 'Origin' );

    if ( empty( $origin ) ) {
        // If no Origin header, deny by default for security
        return new WP_Error( 'rest_forbidden', 'Origin header is missing.', array( 'status' => 403 ) );
    }

    // Extract domain from Origin URL
    $origin_host = strtolower( (string) parse_url( $origin, PHP_URL_HOST ) );
    if ( empty( $origin_host ) ) {
        return new WP_Error( 'rest_forbidden', 'Invalid Origin header.', array( 'status' => 403 ) );
    }

    $whitelist_data = get_webshield_config(); // Call the unified config function

    if ( is_wp_error( $whitelist_data ) ) {
        // Log the error but don't expose internal details to the client
        error_log( '[Webshield Whitelist] Error fetching whitelist for permission check: ' . $whitelist_data->get_error_message() );
        return new WP_Error( 'rest_forbidden', 'Could not verify request origin.', array( 'status' => 403 ) );
    }

    $whitelist = isset( $whitelist_data['whitelist'] ) ? $whitelist_data['whitelist'] : [];
    if ( empty( $whitelist ) || ! is_array( $whitelist ) ) {
        error_log( '[Webshield Whitelist] Whitelist data is empty or invalid.' );
        return new WP_Error( 'rest_forbidden', 'Could not verify request origin (whitelist empty).', array( 'status' => 403 ) );
    }

    $whitelist = array_map( static function ( $domain ) {
        $domain = strtolower( trim( (string) $domain ) );
        $parsed = parse_url( strpos( $domain, '://' ) === false ? 'https://' . $domain : $domain );
        return strtolower( $parsed['host'] ?? preg_replace( '/:\d+$/', '', $domain ) );
    }, $whitelist );

    if ( in_array( $origin_host, $whitelist, true ) ) {
        return true;
    }

    return new WP_Error( 'rest_forbidden', 'Request origin is not whitelisted.', array( 'status' => 403 ) );
}

function wplazy_paypal_create_order_permission_check( WP_REST_Request $request ) {
    $params = $request->get_json_params();
    $merchant = wplazy_paypal_proxy_domain($params['merchant_site'] ?? '');
    $config = get_webshield_config();
    $whitelist = is_array($config) ? ($config['whitelist'] ?? []) : [];
    $whitelist = array_map('wplazy_paypal_proxy_domain', (array) $whitelist);

    if ($merchant !== '' && in_array($merchant, $whitelist, true)) {
        return true;
    }

    return new WP_Error('rest_forbidden', 'Merchant domain is not whitelisted.', ['status' => 403]);
}

function wplazy_paypal_require_allowed_merchant() {
    $merchant = wplazy_paypal_proxy_domain($_GET['merchant_site'] ?? '');
    $config = get_webshield_config();
    if ($merchant === '' || is_wp_error($config) || !in_array($merchant, $config['whitelist'] ?? [], true)) {
        wp_send_json(['status' => 'failed', 'code' => 'domain_whitelist_not_allow'], 403);
    }
}
