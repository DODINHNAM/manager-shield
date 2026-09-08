<?php
/**
 * API functions for the PayPal integration.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

defined('ABSPATH') || exit;

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
    // $merchant_token = sanitize_text_field( $params['merchant_token'] );

    // Validate merchant token if needed

    $response = call_paypal_api( $order_data, 'POST', '/v2/checkout/orders' );

    if ( is_wp_error( $response ) ) {
        return new WP_REST_Response( $response->get_error_message(), 500 );
    }

    // Nếu PayPal không trả id, ghi log debug để kiểm tra nội dung trả về
    if ( empty( $response['id'] ) ) {
        // Ghi vào debug.log (WP_DEBUG_LOG phải bật)
        if ( defined('WP_DEBUG') && WP_DEBUG ) {
            error_log( '[wp-paypal] Missing order id in PayPal response: ' . print_r( $response, true ) );
        }
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
    // DEBUG toggle: đặt true khi debug, false khi production
    $debug = true;

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

    if ( $debug && defined('WP_DEBUG') && WP_DEBUG ) {
        $log_token_args = $token_args;
        $log_token_args['headers']['Authorization'] = 'REDACTED';
        error_log( '[wp-paypal] Token request: ' . $base . '/v1/oauth2/token -- args: ' . print_r( $log_token_args, true ) );
    }

    $token_resp = wp_remote_post( $base . '/v1/oauth2/token', $token_args );

    if ( is_wp_error( $token_resp ) ) {
        if ( $debug && defined('WP_DEBUG') && WP_DEBUG ) {
            error_log( '[wp-paypal] Token request error: ' . $token_resp->get_error_message() );
        }
        return $token_resp;
    }

    $token_code = wp_remote_retrieve_response_code( $token_resp );
    $token_body = wp_remote_retrieve_body( $token_resp );

    if ( $debug && defined('WP_DEBUG') && WP_DEBUG ) {
        error_log( "[wp-paypal] Token response code: $token_code" );
        error_log( "[wp-paypal] Token response body: $token_body" );
    }

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

    if ( $debug && defined('WP_DEBUG') && WP_DEBUG ) {
        $log_args = $args;
        $log_args['headers']['Authorization'] = 'REDACTED';
        error_log( '[wp-paypal] Request to ' . $url . ' -- args: ' . print_r( $log_args, true ) );
    }

    $response = wp_remote_request( $url, $args );

    if ( is_wp_error( $response ) ) {
        if ( $debug && defined('WP_DEBUG') && WP_DEBUG ) {
            error_log( '[wp-paypal] wp_remote_request error: ' . $response->get_error_message() );
        }
        return $response;
    }

    $code = wp_remote_retrieve_response_code( $response );
    $body = wp_remote_retrieve_body( $response );

    if ( $debug && defined('WP_DEBUG') && WP_DEBUG ) {
        error_log( "[wp-paypal] Response code: $code" );
        error_log( "[wp-paypal] Response body: $body" );
    }

    $decoded = json_decode( $body, true );
    return $decoded;
}

add_action('init', function() {
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
    // Logic to handle 'lazy-paypal-capture-order' will be added here.
    // This will involve capturing the payment for a PayPal order.
    $order_id = $_GET['pp_order_id'];
    call_paypal_api(null, 'POST', '/v2/checkout/orders/' . $order_id . '/capture');
    $response = call_paypal_api(null, 'GET', '/v2/checkout/orders/' . $order_id);
    wp_send_json(['status' => 'success', 'order' => $response]);
}

function handle_authorize_order() {
    $order_id = $_GET['pp_order_id'];
    call_paypal_api(null, 'POST', '/v2/checkout/orders/' . $order_id . '/authorize');
    $response = call_paypal_api(null, 'GET', '/v2/checkout/orders/' . $order_id);
    wp_send_json(['status' => 'success', 'order' => $response]);
}

function handle_refund() {
    $transaction_id = $_GET['TRANSACTIONID'];
    $response = call_paypal_api(null, 'POST', '/v2/payments/captures/' . $transaction_id . '/refund');
    wp_send_json(['status' => 'success', 'data' => $response]);
}

function handle_capture_authorization_payment() {
    $payment_id = $_GET['payment_id'];
    $response = call_paypal_api(null, 'POST', '/v2/payments/authorizations/' . $payment_id . '/capture');
    wp_send_json(['status' => 'success', 'data' => $response]);
}

function handle_cancel_authorization_payment() {
    $payment_id = $_GET['payment_id'];
    $response = call_paypal_api(null, 'POST', '/v2/payments/authorizations/' . $payment_id . '/void');
    wp_send_json(['status' => 'success', 'data' => $response]);
}

function handle_reauthorize_authorization_payment() {
    $payment_id = $_GET['payment_id'];
    $response = call_paypal_api(null, 'POST', '/v2/payments/authorizations/' . $payment_id . '/reauthorize');
    wp_send_json(['status' => 'success', 'data' => $response]);
}

// Hook for the REST API endpoint
add_action('rest_api_init', function () {
    register_rest_route('cs', '/create-paypal-order', [
        'methods' => 'POST',
        'callback' => 'create_paypal_order',
    ]);
});
add_action( 'rest_api_init', function() {
    register_rest_route( 'cs', '/woo-paypal-get-form', array(
        'methods'  => WP_REST_Server::READABLE,
        'callback' => 'wplazy_paypal_proxy_rest_get_form',
        // Cho phép truy cập công khai; thay đổi nếu cần kiểm quyền
        'permission_callback' => 'wplazy_paypal_proxy_whitelist_permission_check',
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
            'Cookie' => 'PHPSESSID=4fl0qkahdsle3u4rkfb7pksbnb',
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
 * Permission callback to check if the request's Origin is in the Webshield whitelist.
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
    $origin_host = parse_url( $origin, PHP_URL_HOST );
    if ( empty( $origin_host ) ) {
        return new WP_Error( 'rest_forbidden', 'Invalid Origin header.', array( 'status' => 403 ) );
    }

    $whitelist_data = get_webshield_config(); // Call the unified config function

    if ( is_wp_error( $whitelist_data ) ) {
        // Log the error but don't expose internal details to the client
        error_log( '[Webshield Whitelist] Error fetching whitelist for permission check: ' . $whitelist_data->get_error_message() );
        return new WP_Error( 'rest_forbidden', 'Could not verify request origin.', array( 'status' => 403 ) );
    }

    if ( empty( $whitelist_data['whitelist'] ) || ! is_array( $whitelist_data['whitelist'] ) ) {
        error_log( '[Webshield Whitelist] Whitelist data is empty or invalid.' );
        return new WP_Error( 'rest_forbidden', 'Could not verify request origin (whitelist empty).', array( 'status' => 403 ) );
    }

    // Check if the origin host is in the whitelist
    if ( in_array( $origin_host, $whitelist_data['whitelist'] ) ) {
        return true; // Origin is whitelisted
    }

    // Origin not in whitelist
    return new WP_Error( 'rest_forbidden', 'Request origin is not whitelisted.', array( 'status' => 403 ) );
}
