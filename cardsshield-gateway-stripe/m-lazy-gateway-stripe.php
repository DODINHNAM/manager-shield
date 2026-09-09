<?php
/*
 * Plugin Name: LazyShield Gateway Stripe
 * Plugin URI:
 * Description: LazyShield Gateway Stripe
 * Author: LazyShield
 * Author URI: https://lazyshield.com
 * Version: 2.6.7
 *
 /*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */
if ( !in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    return;
}

if (!defined('ABSPATH')) {
    exit;
}
if (!defined('LAZY_STRIPE_PLUGIN_FILE')) define('LAZY_STRIPE_PLUGIN_FILE', __FILE__);
register_activation_hook(LAZY_STRIPE_PLUGIN_FILE, 'lazy_gateway_stripe_install');

require_once(plugin_dir_path(__FILE__) . 'utils.php');

$lazyStripeSettings = get_option('woocommerce_lazy_stripe_settings');
add_action('lazy_gateway_stripe_cron_auto_sync', 'lazy_gateway_stripe_cron_auto_sync_process');

add_action('update_option_woocommerce_lazy_stripe_settings', function ($_old, $new) {
    if (($new['sync_tracking_automatic'] ?? '') !== 'yes') {
        $timestamp = wp_next_scheduled('lazy_gateway_stripe_cron_auto_sync');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'lazy_gateway_stripe_cron_auto_sync');
        }
    }
}, 10, 2);

add_action('template_redirect', function() {
    if (is_order_received_page() && isset($_GET['key'])) {
        add_filter('woocommerce_order_get_status', function($status, $order) {
            if ($order instanceof WC_Order && $order->get_meta('_shield_payment_method') === 'stripe') {
                return 'processing';
            }
            return $status;
        }, 20, 2);
    }
});

function lazy_gateway_stripe_cron_auto_sync_process()
{
    syncTrackingInfoStripe();                
}
add_action('init', 'register_cs_custom_order_status');
function register_cs_custom_order_status()
{
    register_post_status('wc-cs-hidden', array(
        'public' => false,
        'exclude_from_search' => true,
        'show_in_admin_status_list' => false,
        'show_in_admin_all_list' => false,
    ));
}
function add_cs_hidden_to_order_statuses( $order_statuses ) {
    $order_statuses['wc-cs-hidden'] = 'Pending Payment';
    return $order_statuses;
}
add_filter( 'wc_order_statuses', 'add_cs_hidden_to_order_statuses' );
add_filter('woocommerce_valid_order_statuses_for_payment_complete', 'custom_valid_order_statuses_for_payment_complete');
function custom_valid_order_statuses_for_payment_complete($statuses) {
    $statuses[] = 'cs-hidden';
    return $statuses;
}
if (!class_exists('CSStripeUpdateChecker') && is_admin()) {

    class CSStripeUpdateChecker
    {

        public $plugin_slug;
        public $version;
        public $cache_key;
        public $cache_allowed;

        public function __construct()
        {

            $this->plugin_slug = plugin_basename(__DIR__);
            $this->version = OPT_LAZY_STRIPE_VERSION;
            $this->cache_key = 'cs_stripe_update_checker';
            $this->cache_allowed = true;

            add_filter('plugins_api', [$this, 'info'], 20, 3);
            add_filter('site_transient_update_plugins', [$this, 'update']);
            add_action('upgrader_process_complete', [$this, 'purge'], 10, 2);

        }

        public function request()
        {

            $remote = get_transient($this->cache_key);

            if (false === $remote || !$this->cache_allowed) {

                $remote = wp_remote_get(
                    'https://update.cardsshield.com/cs_stripe/info.php?site=' . urlencode(get_site_url()),
                    [
                        'timeout' => 10,
                        'headers' => [
                            'Accept' => 'application/json'
                        ]
                    ]
                );

                if (
                    is_wp_error($remote)
                    || 200 !== wp_remote_retrieve_response_code($remote)
                    || empty(wp_remote_retrieve_body($remote))
                ) {
                    return false;
                }

                set_transient($this->cache_key, $remote, HOUR_IN_SECONDS);

            }

            $remote = json_decode(wp_remote_retrieve_body($remote));

            return $remote;

        }


        function info($res, $action, $args)
        {

            // do nothing if you're not getting plugin information right now
            if ('plugin_information' !== $action) {
                return $res;
            }

            // do nothing if it is not our plugin
            if ($this->plugin_slug !== $args->slug) {
                return $res;
            }

            // get updates
            $remote = $this->request();

            if (!$remote) {
                return $res;
            }

            $res = new stdClass();

            $res->name = $remote->name;
            $res->slug = $remote->slug;
            $res->version = $remote->version;
            $res->tested = $remote->tested;
            $res->requires = $remote->requires;
            $res->author = $remote->author;
            $res->author_profile = $remote->author_profile;
            $res->download_link = $remote->download_url;
            $res->trunk = $remote->download_url;
            $res->requires_php = $remote->requires_php;
            $res->last_updated = $remote->last_updated;

            $res->sections = [
                'description' => $remote->sections->description,
                'installation' => $remote->sections->installation,
                'changelog' => $remote->sections->changelog
            ];

            if (!empty($remote->banners)) {
                $res->banners = [
                    'low' => $remote->banners->low,
                    'high' => $remote->banners->high
                ];
            }

            return $res;

        }

        public function update($transient)
        {

            if (empty($transient->checked)) {
                return $transient;
            }

            $remote = $this->request();

            if (
                $remote
                && version_compare($this->version, $remote->version, '<')
                && version_compare($remote->requires, get_bloginfo('version'), '<')
                && version_compare($remote->requires_php, PHP_VERSION, '<')
            ) {
                $res = new stdClass();
                $res->slug = $this->plugin_slug;
                $res->plugin = plugin_basename(LAZY_STRIPE_PLUGIN_FILE); // misha-update-plugin/misha-update-plugin.php
                $res->new_version = $remote->version;
                $res->tested = $remote->tested;
                $res->package = $remote->download_url;

                $transient->response[$res->plugin] = $res;

            }

            return $transient;

        }

        public function purge($upgrader_object, $options)
        {

            if (
                $this->cache_allowed
                && 'update' === $options['action']
                && 'plugin' === $options['type']
            ) {
                // just clean the cache when new plugin version is installed
                delete_transient($this->cache_key);
            }

        }


    }

    new CSStripeUpdateChecker();

}

//Cron
add_filter('cron_schedules', 'lazy_add_stripe_cron_interval');
if (isset($lazyStripeSettings['sync_tracking_automatic']) && $lazyStripeSettings['sync_tracking_automatic'] === 'yes' && !wp_next_scheduled('lazy_gateway_stripe_cron_auto_sync')) {
    wp_schedule_event(time(), 'hourly', 'lazy_gateway_stripe_cron_auto_sync');
}

if (!wp_next_scheduled('lazy_gateway_stripe_rotation')) {
    wp_schedule_event(time(), 'one_minute', 'lazy_gateway_stripe_rotation');
}

add_action('lazy_gateway_stripe_rotation', 'lazy_stripe_rotation_checker');
if (!wp_next_scheduled('lazy_gateway_stripe_daily')) {
    try {
        $timezone = new DateTimeZone(wp_timezone_string());
        $dt = new DateTime('tomorrow 00:00:01', $timezone);
        $firstRun = $dt->getTimestamp();
    } catch (Exception $e) {
        $firstRun = strtotime('tomorrow midnight') + 1;
    }
    wp_schedule_event($firstRun, 'daily', 'lazy_gateway_stripe_daily');
}
add_action('lazy_gateway_stripe_daily', 'lazy_gateway_stripe_daily_process');

function lazy_gateway_stripe_daily_process()
{
    // Reset paid amount
    $rotationMethod = get_option(OPT_LAZY_STRIPE_ROTATION_METHOD, LAZY_STRIPE_BY_TIME);
    if ($rotationMethod === LAZY_STRIPE_BY_AMOUNT) {
        resetPaidAmountStripe();
    }
}

add_action('plugins_loaded', 'lazy_add_gateway_stripe_init');
add_action('wp_head', function() {
    global $CS_wp_head;
    global $CS_get_header;
    $CS_wp_head = true;
    if (!isset($CS_get_header) || $CS_get_header !== true) {
        handle_route();
    }
}, 9999);
add_action('get_header', function() {
    global $CS_wp_head;
    global $CS_get_header;
    $CS_get_header = true;
    if (!isset($CS_wp_head) || $CS_wp_head !== true) {
        handle_route();
    }
}, 9999);
add_action('woocommerce_admin_order_totals_after_total', 'action_woocommerce_admin_order_totals_after_total_stripe', 10, 1);

function lazy_add_stripe_cron_interval($schedules)
{
    $schedules['one_minute'] = array(
        'interval' => 60,
        'display' => esc_html__('Every minute'),
    );

    return $schedules;
}

function lazy_gateway_stripe_install()
{
//    delete_option('lazy_gateway_stripe_version');
//    add_option('lazy_gateway_stripe_version', uniqid());
}

function renderMoneyRowStripe($title, $tooltip, $value, $currency, $negative = false)
{
    /**
     * Bad type hint in WC phpdoc.
     *
     * @psalm-suppress InvalidScalarArgument
     */
    return '
            <tr>
                <td class="label">' . wc_help_tip($tooltip) . ' ' . esc_html($title) . '
                </td>
                <td width="1%"></td>
                <td class="total">
                    ' .
        ($negative ? ' - ' : '') .
        wc_price($value, array('currency' => $currency)) . '
                </td>
            </tr>';
}

function action_woocommerce_admin_order_totals_after_total_stripe($order_get_id)
{

    $wc_order = wc_get_order($order_get_id);
    if (!$wc_order instanceof WC_Order) {
        return;
    }

    if ($wc_order->get_payment_method() !== 'lazy_stripe') {
        return;
    }
    $stripeFee = $wc_order->get_meta(METAKEY_CS_STRIPE_FEE);
    $stripeCurrency = $wc_order->get_meta(METAKEY_CS_STRIPE_CURRENCY);
    $stripePayout = $wc_order->get_meta(METAKEY_CS_STRIPE_PAYOUT);

    $html = '';

    if (isset($stripeFee) && isset($stripeCurrency)) {
        $html .= renderMoneyRowStripe('Stripe Fee:', 'The fee Stripe collects for the transaction.',
            $stripeFee,
            $stripeCurrency,
            true
        );
    }

    if (isset($stripePayout) && isset($stripeCurrency)) {
        $html .= renderMoneyRowStripe(
            'Stripe Payout:',
            'The net total that will be credited to your Stripe account.',
            $stripePayout,
            $stripeCurrency
        );
    }

    echo $html;
}

function lazy_stripe_rotation_checker()
{

    $rotationMethod = get_option(OPT_LAZY_STRIPE_ROTATION_METHOD, LAZY_STRIPE_BY_TIME);
    if ($rotationMethod != LAZY_STRIPE_BY_TIME) {
        return;
    }
    // Auto Switching Proxy
    $proxies = get_option(Opt_Lazy_Stripe_Proxies, []);
    if (empty($proxies)) {
        return;
    }
    $activatedProxy = get_option(Opt_Lazy_Stripe_Activated_Proxy, null);
    // if only has 1 proxy, don't rotate
    if (count($proxies) === 1 && isset($activatedProxy['id']) && $activatedProxy['id'] === $proxies[0]['id']) {
        return;
    }
    $lastActivatedTimestamp = get_option(OPT_LAZY_STRIPE_CURRENT_ROTATION_VALUE, 0);

    $hasDeletedActivateProxy = false;

    if ($lastActivatedTimestamp != 0 && !empty($activatedProxy)) {
        $currentTimestamp = time();
        $timeStampSinceLastActivated = $currentTimestamp - $lastActivatedTimestamp;
        // How many minutes?
        $minutes = $timeStampSinceLastActivated / 60;

        // Need to rotate
        if ($minutes > floatval($activatedProxy["timestamp"])) {
            $hasDeletedActivateProxy = true;
            for ($i = 0; $i < count($proxies); $i++) {
                $proxy = $proxies[$i];
                if ($proxy["id"] == $activatedProxy["id"]) {
                    // The last one => active the proxy at 0 index
                    if ($i + 1 == count($proxies)) {
                        $needToActivateProxy = $proxies[0];
                    } else {
                        // Active the next one
                        $needToActivateProxy = $proxies[$i + 1];
                    }
                    update_option(Opt_Lazy_Stripe_Activated_Proxy, $needToActivateProxy, true);
                    update_option(OPT_LAZY_STRIPE_CURRENT_ROTATION_VALUE, time(), true);
                    logStripeRotation(LAZY_STRIPE_BY_TIME, $needToActivateProxy, "Auto");
                    $hasDeletedActivateProxy = false;
                    break;
                }
            }
        }
    }

    if (empty($activatedProxy) || $hasDeletedActivateProxy) {
        update_option(Opt_Lazy_Stripe_Activated_Proxy, $proxies[0], true);
        update_option(OPT_LAZY_STRIPE_CURRENT_ROTATION_VALUE, time(), true);
        logStripeRotation(LAZY_STRIPE_BY_TIME, $proxies[0], "Auto");
    }
}

function handle_route()
{
    if (isset($_GET['lazy_stripe_return_result']) && isset($_GET['order_id'])) {
        $order = wc_get_order($_GET['order_id']);
        csStripeDebugLog($_GET, 'lazy_stripe_return_result info [1]');
        if (!$order) {
            csStripeErrorLog('lazy_stripe_return_result ERROR [1]');
            wc_add_notice('We cannot process your payment right now, please try another payment method.[2]', 'error');
            return wp_redirect(wc_get_checkout_url());
        }
        $activeProxyId = $order->get_meta( METAKEY_STRIPE_PROXY_ID);
        $activeProxyUrl = $order->get_meta( METAKEY_STRIPE_PROXY_URL);
        $activatedProxy = [
            'id' => $activeProxyId,
            'url' => $activeProxyUrl,
        ];
        if (!isset($activatedProxy['url'])) {
            csStripeErrorLog("Can't find activated proxy!\n");
            wc_add_notice('We cannot process your payment right now, please try another payment method.[3]', 'error');
            return wp_redirect(wc_get_checkout_url());
        }
        $response = wp_remote_post($activatedProxy['url'] . '?' . csStripeBuildQuery([
                'lazy-stripe-pe-v2-confirm-payment' => uniqid(),
                'payment_intent_id' => csStripeGetTransactionId($order),
                'amount' => $order->get_total(),
                'currency' => $order->get_currency(),
                'merchant_site' => get_home_url(),
            ]), [
            'sslverify' => csStripeGetSSLVerifyStatus(),
            'timeout' => 5 * 60,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'cs_order_detail' => getCsStripeOrderDetailFromWcOrder($order),
            ])
        ]);
        $order->update_meta_data( METAKEY_STRIPE_PROXY_URL, $activatedProxy['url']);
        $order->update_meta_data( '_shield_payment_method', 'stripe');
        $order->update_meta_data( '_shield_payment_url', $activatedProxy['url']);
        $order->update_meta_data( METAKEY_STRIPE_PROXY_ID, $activatedProxy['id']);
        $order->save_meta_data();
        if (is_wp_error($response)) {
            csStripeErrorLog($response, 'Stripe return result error');
            wc_add_notice('We cannot process your payment right now, please try another payment method.[4]', 'error');
            return wp_redirect(wc_get_checkout_url());
        }
        $body = wp_remote_retrieve_body($response);
        $body = json_decode($body);
        $paymentStripeIntent = get_option('woocommerce_lazy_stripe_settings')['intent'];

        if ($body->status === 'success') {
            $paymentIntent = $body->payment_intent;
            $order->update_meta_data( METAKEY_CS_STRIPE_RAND_ORDER_ID, $paymentIntent->metadata->order_id ?? null);
            if ($paymentStripeIntent === OPT_LAZY_STRIPE_INTENT_AUTHORIZE) {
                $order->add_order_note(sprintf(__('Stripe authorize by proxy %s, (Payment Intent ID: %s)', 'lazy'), $activatedProxy['url'], $paymentIntent->id));
                $order->update_status('on-hold', 'Payment can be captured.');
                $order->update_meta_data( METAKEY_LAZY_STRIPE_INTENT_AUTHORIZED, 'true');
            } else {
                if ($paymentIntent->status === 'processing') {
                    $order->update_meta_data( METAKEY_CS_STRIPE_PAYMENT_PROCESSING_STATUS, 'true');
                    $order->update_status('on-hold', 'Payment processing.');
                    $order->add_order_note(sprintf(__('Stripe processing by proxy %s', 'lazy'), $activatedProxy['url']), 0, false);
                    $order->add_order_note(sprintf(__('Stripe Checkout processing (Payment Intent ID: %s)', 'lazy'), $paymentIntent->id));
                } else {
                    $order->update_meta_data( METAKEY_LAZY_STRIPE_CAPTURED, 'true');
                    if (!$order->is_paid()) {
                        $order->payment_complete();
                        $order->add_order_note(sprintf(__('Stripe charged by proxy %s', 'lazy'), $activatedProxy['url']), 0, false);
                        $order->add_order_note(sprintf(__('Stripe Checkout charge complete (Payment Intent ID: %s)', 'lazy'), $paymentIntent->id));
                    } else {
                        csStripeErrorLog('lazy_stripe_return_result already paid!');
                    }
                }
            }
            $order->reduce_order_stock();

            $isEnableEndpointMode = isCsStripeEnableEndpointMode();
            if ($isEnableEndpointMode) {
                csStripeEndpointPerformShieldRotateByAmount($order);
            } else {
                if (isEnabledAmountRotationStripe()) {
                    performProxyAmountRotationStripe($order->get_total());
                    updateRotationAmountStripe($activatedProxy['id'], $order->get_total());
                }
            }

            csStripeSaveTransactionId($order, $paymentIntent->id);
            $order->update_meta_data( METAKEY_STRIPE_SYNC_TRACKING_INFO, OPT_CS_STRIPE_NOT_SYNCED);
            $order->save_meta_data();
            updateFeeNetOrderStripe($body->charge, $order);
            // Empty cart
            WC()->cart->empty_cart();
            return wp_redirect($order->get_checkout_order_received_url());
        } else {
            csStripeErrorLog($response, 'Stripe confirm payment error');
            // Empty cart
            $order->update_status('failed');
            $err = $body->err;
            $paymentIntentId = csStripeGetTransactionId($order);
            if (isset($err->payment_intent)) {
                $paymentIntentId = $err->payment_intent->id;
                csStripeSaveTransactionId($order, $paymentIntentId);
                $order->update_meta_data( METAKEY_STRIPE_SYNC_TRACKING_INFO, OPT_CS_STRIPE_NOT_SYNCED);
                $order->update_meta_data( METAKEY_CS_STRIPE_RAND_ORDER_ID, $err->payment_intent->metadata->order_id ?? null);
                $order->save_meta_data();
            }
            $order->add_order_note(sprintf(__('Stripe charged ERROR by proxy %s, ERROR message: %s, Payment Intent ID: %s', 'lazy'),
                $activatedProxy['url'],
                is_string($err) ? $err : $err->message,
                $paymentIntentId
            ));
            wc_add_notice('We cannot process your payment right now, please try another payment method.[5]', 'error');
            return wp_redirect(wc_get_checkout_url());
        }
    }
    
    if (isset($_GET['cs-stripe-notify-payment-status']) && isset($_GET['order_id'])) {
        csStripeDebugLog($_GET, 'cs-stripe-notify-payment-status request');
        $order = wc_get_order($_GET['order_id']);
        if (!$order || $order->is_paid()) {
            if ($order) {
                csStripeErrorLog([$order->get_status(), $order->get_meta( METAKEY_CS_STRIPE_PAYMENT_PROCESSING_STATUS)], 'cs-stripe-notify-payment-status ERROR [1]');
            } else {
                csStripeErrorLog('cs-stripe-notify-payment-status ERROR [2]');
            }
            echo 'OK'; exit();
        }
        $activeProxyId = $order->get_meta( METAKEY_STRIPE_PROXY_ID);
        $activeProxyUrl = $order->get_meta( METAKEY_STRIPE_PROXY_URL);
        $activatedProxy = [
            'id' => $activeProxyId,
            'url' => $activeProxyUrl,
        ];
        if (!isset($activatedProxy['url'])) {
            csStripeErrorLog("Can't find activated proxy!\n");
            echo 'OK'; exit();
        }
        $response = wp_remote_get($activatedProxy['url'] . '?' . csStripeBuildQuery([
                'lazy-stripe-pe-v2-get-payment-intent' => uniqid(),
                'payment_intent_id' => csStripeGetTransactionId($order),
                'merchant_site' => get_home_url(),
            ]), [
            'sslverify' => csStripeGetSSLVerifyStatus(),
            'timeout' => 5 * 60,
        ]);

        if (is_wp_error($response)) {
            csStripeErrorLog($response, 'Stripe get notify payment status error');
            status_header(500);
            echo 'FAILED'; exit();
        }
        $body = wp_remote_retrieve_body($response);
        $body = json_decode($body);
        $transactionStatus = null;
        if ($body->status === 'success' && isset($body->payment_intent->status)) {
            if ($body->payment_intent->status === 'succeeded') {
                if (!$order->is_paid()) {
                    $order->payment_complete();
                    $order->add_order_note(sprintf(__('Stripe charged by proxy %s', 'lazy'), $activatedProxy['url']), 0, false);
                    $order->add_order_note(sprintf(__('Stripe Checkout charge complete (Payment Intent ID: %s)', 'lazy'), $body->payment_intent->id));
                    $order->update_meta_data( METAKEY_CS_STRIPE_RAND_ORDER_ID, $body->payment_intent->metadata->order_id ?? null);
                    $order->save_meta_data();
                } else {
                    csStripeErrorLog('cs-stripe-notify-payment-status already paid!');
                }
                $transactionStatus = 'successful';
                wp_remote_get($activatedProxy['url'] . '?' . csStripeBuildQuery([
                        'lazy-stripe-pe-v2-add-payment-history' => uniqid(),
                        'payment_intent_id' => csStripeGetTransactionId($order),
                        'merchant_site' => get_home_url(),
                    ]), [
                    'sslverify' => csStripeGetSSLVerifyStatus(), 
                    'timeout' => 300,
                ]);
            }
            if ($body->charge->status === 'failed') {
                $order->update_status('failed');
                $order->add_order_note(sprintf(__('Stripe charged ERROR by proxy %s, ERROR message: %s, Payment Intent ID: %s', 'lazy'), 
                    $activatedProxy['url'], 
                    $body->payment_intent->failure_message ?? null, 
                    $body->payment_intent->id));
                $order->update_meta_data( METAKEY_CS_STRIPE_RAND_ORDER_ID, $body->payment_intent->metadata->order_id ?? null);
                $order->save_meta_data();
                $transactionStatus = 'failed';
            }
            wp_remote_post($activatedProxy['url'] . '?' . csStripeBuildQuery([
                'lazy-stripe-pe-v2-add-order-detail' => uniqid(),
                'transaction_id' => csStripeGetTransactionId($order),
                'transaction_status' => $transactionStatus,
                'notes' => $_POST['note'],
            ]), [
                'sslverify' => csStripeGetSSLVerifyStatus(), 
                'timeout' => 300,
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode([
                    'cs_order_detail' => getCsStripeOrderDetailFromWcOrder($order),
                ])
            ]);  
        }
        echo 'OK'; exit();
    }
    
    if (isset($_GET['cs_handle_stripe_checkout_session_success']) && isset($_GET['order_id']) && isset($_GET['stripe_session_id'])) {
        $order = wc_get_order($_GET['order_id']);
        csStripeDebugLog($_GET, 'cs_handle_stripe_checkout_session_success');
        if (!$order) {
            csStripeErrorLog('cs_handle_stripe_checkout_session_success ERROR [1]');
            wc_add_notice('We cannot process your payment right now, please try another payment method.[6]', 'error');
            return wp_redirect(wc_get_checkout_url());
        }
        $activeProxyId = $order->get_meta( METAKEY_STRIPE_PROXY_ID);
        $activeProxyUrl = $order->get_meta( METAKEY_STRIPE_PROXY_URL);
        $activatedProxy = [
            'id' => $activeProxyId,
            'url' => $activeProxyUrl,
        ];
        if (!isset($activatedProxy['url'])) {
            csStripeErrorLog("Can't find activated proxy!\n");
            wc_add_notice('We cannot process your payment right now, please try another payment method.[7]', 'error');
            return wp_redirect(wc_get_checkout_url());
        }
        $response = wp_remote_post($activatedProxy['url'] . '?' . csStripeBuildQuery([
                'cs-stripe-hosted-verify-payment' => uniqid(),
                'stripe_session_id' => $_GET['stripe_session_id'],
                'merchant_site' => get_home_url(),
            ]), [
            'sslverify' => csStripeGetSSLVerifyStatus(),
            'timeout' => 5 * 60,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'cs_order_detail' => getCsStripeOrderDetailFromWcOrder($order),
            ])
        ]);

        if (is_wp_error($response)) {
            csStripeErrorLog($response, 'Stripe complete checkout session error');
            wc_add_notice('We cannot process your payment right now, please try another payment method.[8]', 'error');
            return wp_redirect(wc_get_checkout_url());
        }
        $body = wp_remote_retrieve_body($response);
        $body = json_decode($body);

        if ($body->status === 'success') {
            $isEnableEndpointMode = isCsStripeEnableEndpointMode();
            if ($isEnableEndpointMode) {
                csStripeEndpointPerformShieldRotateByAmount($order);
            } else {
                if (isEnabledAmountRotationStripe()) {
                    performProxyAmountRotationStripe($order->get_total());
                    updateRotationAmountStripe($activatedProxy['id'], $order->get_total());
                }
            }
            // Empty cart
            WC()->cart->empty_cart();
            return wp_redirect($order->get_checkout_order_received_url());
        } else {
            csStripeErrorLog($response, 'Stripe confirm checkout session error');
            wc_add_notice('We cannot process your payment right now, please try another payment method.[9]', 'error');
            return wp_redirect(wc_get_checkout_url());
        }
    }
    
    if (isset($_GET['handle_scs_notice_success']) && isset($_GET['oid']) && isset($_GET['ssi'])) {
        $order = wc_get_order($_GET['oid']);
        csStripeDebugLog($_GET, 'cs_handle_stripe_checkout_session_notice_success');
        if (!$order) {
            csStripeErrorLog('cs_handle_stripe_checkout_session_notice_success ERROR [1]');
            wc_add_notice('We cannot process your payment right now, please try another payment method.[10]', 'error');
            echo 'OK'; exit();
        }
        $activeProxyId = $order->get_meta( METAKEY_STRIPE_PROXY_ID);
        $activeProxyUrl = $order->get_meta( METAKEY_STRIPE_PROXY_URL);
        $activatedProxy = [
            'id' => $activeProxyId,
            'url' => $activeProxyUrl,
        ];
        if (!isset($activatedProxy['url'])) {
            csStripeErrorLog("Can't find activated proxy!\n");
            wc_add_notice('We cannot process your payment right now, please try another payment method.[11]', 'error');
            echo 'OK'; exit();
        }
        $response = wp_remote_post($activatedProxy['url'] . '?' . csStripeBuildQuery([
                'cs-stripe-hosted-complete-payment' => uniqid(),
                'stripe_session_id' => $_GET['ssi'],
                'merchant_site' => get_home_url(),
            ]), [
            'sslverify' => csStripeGetSSLVerifyStatus(),
            'timeout' => 5 * 60,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'cs_order_detail' => getCsStripeOrderDetailFromWcOrder($order),
            ])
        ]);

        $order->update_meta_data( METAKEY_STRIPE_PROXY_URL, $activatedProxy['url']);
        $order->update_meta_data( '_shield_payment_method', 'stripe');
        $order->update_meta_data( '_shield_payment_url', $activatedProxy['url']);
        $order->update_meta_data( METAKEY_STRIPE_PROXY_ID, $activatedProxy['id']);
        $order->save_meta_data();
        if (is_wp_error($response)) {
            csStripeErrorLog($response, 'Stripe complete checkout session error');
            wc_add_notice('We cannot process your payment right now, please try another payment method.[12]', 'error');
            status_header(500);
            echo 'FAILED'; exit();
        }
        $body = wp_remote_retrieve_body($response);
        $body = json_decode($body);
        $paymentStripeIntent = get_option('woocommerce_lazy_stripe_settings')['intent'];

        if ($body->status === 'success') {
            $paymentIntent = $body->payment_intent;
            $order->update_meta_data( METAKEY_CS_STRIPE_RAND_ORDER_ID, $paymentIntent->metadata->order_id ?? null);
            if ($paymentStripeIntent === OPT_LAZY_STRIPE_INTENT_AUTHORIZE) {
                $order->add_order_note(sprintf(__('Stripe authorize by proxy %s, (Checkout Session ID: %s, Payment Intent ID: %s)', 'lazy'), $activatedProxy['url'], $_GET['ssi'], $paymentIntent->id));
                $order->update_status('on-hold', 'Payment can be captured.');
                $order->update_meta_data( METAKEY_LAZY_STRIPE_INTENT_AUTHORIZED, 'true');
            } else {
                if ($paymentIntent->status === 'processing') {
                    $order->update_meta_data( METAKEY_CS_STRIPE_PAYMENT_PROCESSING_STATUS, 'true');
                    $order->update_status('on-hold', 'Payment processing.');
                    $order->add_order_note(sprintf(__('Stripe processing by proxy %s', 'lazy'), $activatedProxy['url']), 0, false);
                    $order->add_order_note(sprintf(__('Stripe Checkout processing (Checkout Session ID: %s, Payment Intent ID: %s)', 'lazy'), $_GET['ssi'], $paymentIntent->id));
                } else {
                    $order->update_meta_data( METAKEY_LAZY_STRIPE_CAPTURED, 'true');
                    $order->payment_complete();
                    $order->add_order_note(sprintf(__('Stripe charged by proxy %s', 'lazy'), $activatedProxy['url']), 0, false);
                    $order->add_order_note(sprintf(__('Stripe Checkout charge complete (Checkout Session ID: %s, Payment Intent ID: %s)', 'lazy'), $_GET['ssi'], $paymentIntent->id));
                }
            }
            $order->reduce_order_stock();

            $isEnableEndpointMode = isCsStripeEnableEndpointMode();
            if ($isEnableEndpointMode) {
                csStripeEndpointPerformShieldRotateByAmount($order);
            } else {
                if (isEnabledAmountRotationStripe()) {
                    performProxyAmountRotationStripe($order->get_total());
                    updateRotationAmountStripe($activatedProxy['id'], $order->get_total());
                }
            }

            csStripeSaveTransactionId($order, $paymentIntent->id);
            $order->update_meta_data( METAKEY_STRIPE_SYNC_TRACKING_INFO, OPT_CS_STRIPE_NOT_SYNCED);
            $order->save_meta_data();
            updateFeeNetOrderStripe($body->charge, $order);
        } else {
            csStripeErrorLog($response, 'Stripe confirm checkout session error');
            // Empty cart
            $order->update_status('failed');
            $err = $body->err;
            $order->add_order_note(sprintf(__('Stripe charged ERROR by proxy %s, ERROR message: %s, Checkout Session ID: %s', 'lazy'),
                $activatedProxy['url'],
                is_string($err) ? $err : $err->message,
                $_GET['ssi']
            ));
            wc_add_notice('We cannot process your payment right now, please try another payment method.[13]', 'error');
        }
        echo 'OK'; exit();
    }

    if (isset($_GET['handle_scs_notice_failed']) && isset($_GET['h']) && isset($_GET['oid']) && isset($_GET['ssi'])) {
        if(!hash_equals(hash_hmac('sha256', json_encode([
            'ssi' => $_GET['ssi'],
            'pi' => $_GET['pi']
        ]), $_GET['oid']), $_GET['h'])) {
            echo 'OK'; exit();
        }
        $order = wc_get_order($_GET['oid']);
        if (!$order || $order->is_paid()) {
            echo 'OK'; exit();
        }
        $activeProxyId = $order->get_meta( METAKEY_STRIPE_PROXY_ID);
        $activeProxyUrl = $order->get_meta( METAKEY_STRIPE_PROXY_URL);
        $activatedProxy = [
            'id' => $activeProxyId,
            'url' => $activeProxyUrl,
        ];
        if (!isset($activatedProxy['url'])) {
            csStripeErrorLog("Can't find activated proxy [3]!\n");
            echo 'OK'; exit();
        }

        $order->update_status('failed');
        $order->add_order_note(sprintf(__('Stripe charged ERROR by proxy %s, ERROR message: %s, Checkout Session ID: %s, Payment Intent ID: %s', 'lazy'),
            $activatedProxy['url'],
            $_GET['failure_message'],
            $_GET['ssi'],
            $_GET['pi']
        ));
        echo 'OK'; exit();
    }
    
    if (isset($_GET['cs_handle_stripe_checkout_session_cancelled']) && isset($_GET['order_id']) && isset($_GET['stripe_session_id'])) {
        return wp_redirect(wc_get_checkout_url());
    }
}

function lazy_add_gateway_stripe_init()
{

    if (is_admin()) {
        require_once __DIR__ . "/m-lazy-stripe-options.php";
    }

    if (!class_exists('WC_Lazy_Stripe')) :

        class WC_Lazy_Stripe
        {

            /**
             * @var Singleton The reference the *Singleton* instance of this class
             */
            private static $instance;

            /**
             * Returns the *Singleton* instance of this class.
             *
             * @return Singleton The *Singleton* instance.
             */
            public static function get_instance()
            {
                if (null === self::$instance) {
                    self::$instance = new self();
                }
                return self::$instance;
            }

            /**
             * Private clone method to prevent cloning of the instance of the
             * *Singleton* instance.
             *
             * @return void
             */
            private function __clone()
            {
            }

            /**
             * Private unserialize method to prevent unserializing of the *Singleton*
             * instance.
             *
             * @return void
             */
            public function __wakeup()
            {
            }

            /**
             * Protected constructor to prevent creating a new instance of the
             * *Singleton* via the `new` operator from outside of this class.
             */
            private function __construct()
            {
                $this->init();
            }

            private function init()
            {
                add_filter('woocommerce_payment_gateways', array($this, 'add_gateways'));
                if (is_admin()) {
                    add_filter(
                        'plugin_action_links_' . plugin_basename(LAZY_STRIPE_PLUGIN_FILE),
                        array($this, 'add_settings_link')
                    );
                }
                add_filter('woocommerce_available_payment_gateways', array($this, 'check_cs_stripe_payment_gateways'), 10, 1);
                add_filter('woocommerce_order_actions', [$this, 'add_lazy_stripe_order_actions'], 10, 1);
                add_action('woocommerce_order_action_lazy_stripe_capture_authorization_order', [$this, 'lazy_stripe_capture_authorization_order']);
                add_action('woocommerce_order_action_lazy_stripe_cancel_authorization_order', [$this, 'lazy_stripe_cancel_authorization_order']);
            }

            public function check_cs_stripe_payment_gateways($gateways)
            {
                if (!is_checkout() && !defined('WOOCOMMERCE_CHECKOUT')) {
                    return $gateways;
                }
                if (WC()->cart) {
                    $carTotal = WC()->cart->get_total(false);
                } else {
                    $carTotal = 0;
                }
                $isEnableEndpointMode = isCsStripeEnableEndpointMode();
                if ($isEnableEndpointMode) {
                    $csOrderKey = csStripeGenerateProcessingOrderKey();
                    $shieldUrl = csEndpointGetShieldStripeToProcess($csOrderKey, $carTotal);
                    if (!$shieldUrl) {
                        unset($gateways['lazy_stripe']);
                    }
                } else {
                    $rotationMethod = get_option(OPT_LAZY_STRIPE_ROTATION_METHOD, LAZY_STRIPE_BY_TIME);
                    global $woocommerce;
                    $activeProxy = get_option(Opt_Lazy_Stripe_Activated_Proxy, null);

                    if ($rotationMethod == LAZY_STRIPE_BY_AMOUNT) {
                        $carTotal = WC()->cart->get_total(false);
                        if (!hasPayableProxyStripe($carTotal)) {
                            if (isStripeShieldReachAmount(WC()->cart->get_total(false))) {
                                csStripeSendMailShieldReachAmount();
                            }
                            unset($gateways['lazy_stripe']);
                        }
                    }

                    if (empty(get_option(Opt_Lazy_Stripe_Proxies, []))) {
                        unset($gateways['lazy_stripe']);
                    }
                }
                return $gateways;
            }

            public function add_lazy_stripe_order_actions($order_actions)
            {
                global $theorder;

                if (!is_a($theorder, WC_Order::class)) {
                    return $order_actions;
                }
                if (!$this->should_render_for_action($theorder)) {
                    return $order_actions;
                }

                $order_actions['lazy_stripe_capture_authorization_order'] = 'Capture authorized Stripe payment';
                $order_actions['lazy_stripe_cancel_authorization_order'] = 'Void authorized Stripe payment';

                return $order_actions;
            }

            public function should_render_for_action(\WC_Order $order)
            {
                $status = $order->get_status();
                $not_allowed_statuses = array('refunded', 'cancelled', 'failed');
                return $this->is_authorized($order) &&
                    !in_array($status, $not_allowed_statuses, true);
            }

            public function is_authorized(\WC_Order $wc_order)
            {
                return $wc_order->get_meta(METAKEY_LAZY_STRIPE_INTENT_AUTHORIZED) === 'true';
            }

            function lazy_stripe_capture_authorization_order(WC_Order $wc_order)
            {
                if ($wc_order->get_status() == 'cancelled') {
                    return false;
                }
                if (!$this->is_authorized($wc_order)) {
                    return false;
                }

                $proxyUrl = $wc_order->get_meta(METAKEY_STRIPE_PROXY_URL);

                if (empty($proxyUrl)) {
                    $wc_order->add_order_note("Can't found proxy url!");
                    return false;
                }
                $paymentIntentId = csStripeGetTransactionId($wc_order);
                $params = [];
                $params["merchant_site"] = get_home_url();
                $params["payment_intent_id"] = $paymentIntentId;
                $capturePaymentUrl = $proxyUrl . '?' . csStripeBuildQuery(['lazy-stripe-pe-v2-capture-payment' => 1] + $params);

                $request = wp_remote_post($capturePaymentUrl, [
                    'sslverify' => csStripeGetSSLVerifyStatus(), 
                    'timeout' => 300,
                    'headers' => [
                        'Content-Type' => 'application/json',
                    ],
                    'body' => json_encode([
                        'cs_order_detail' => getCsStripeOrderDetailFromWcOrder($wc_order),
                    ])
                ]);
                if (is_wp_error($request)) {
                    csStripeErrorLog($request, "Capture request error!");
                    $wc_order->add_order_note("Capture request error!");
                    $wc_order->update_status('failed', 'Order capture failed');
                    return false;
                }
                $responseBody = wp_remote_retrieve_body($request);
                $data = json_decode($responseBody);
                if (empty($data)) {
                    csStripeErrorLog($responseBody, "Capture error! Empty response");
                    $wc_order->add_order_note("Capture error! Empty response");
                    $wc_order->update_status('failed', 'Order capture failed');
                    return false;
                }

                if ($data->status !== 'success') {
                    $wc_order->add_order_note($data->message);
                    $wc_order->update_status('failed', 'Order capture failed');
                    return false;
                }
                $paymentIntent = $data->payment_intent;
                csStripeSaveTransactionId($wc_order, $paymentIntent->id);
                $wc_order->update_meta_data(METAKEY_STRIPE_SYNC_TRACKING_INFO, OPT_CS_STRIPE_NOT_SYNCED);
                updateFeeNetOrderStripe($data->charge, $wc_order);
                $wc_order->add_order_note(sprintf(__('Stripe Capture complete (Payment Intent ID: %s)', 'lazy'), $paymentIntent->id));
                $wc_order->update_meta_data(METAKEY_LAZY_STRIPE_INTENT_AUTHORIZED, 'false');
                $wc_order->update_meta_data(METAKEY_LAZY_STRIPE_CAPTURED, 'true');
                $wc_order->save();
                $wc_order->payment_complete();
                return true;
            }

            function lazy_stripe_cancel_authorization_order(WC_Order $wc_order)
            {
                if ($wc_order->get_status() == 'cancelled') {
                    return false;
                }
                if (!$this->is_authorized($wc_order)) {
                    return false;
                }

                $proxyUrl = $wc_order->get_meta( METAKEY_STRIPE_PROXY_URL);

                if (empty($proxyUrl)) {
                    $wc_order->add_order_note("Can't found proxy url!");
                    return false;
                }
                $paymentIntentId = csStripeGetTransactionId($wc_order);
                $params = [];
                $params["merchant_site"] = get_home_url();
                $params["payment_intent_id"] = $paymentIntentId;
                $cancelPaymentUrl = $proxyUrl . '?' . csStripeBuildQuery(['lazy-stripe-pe-v2-cancel-payment' => 1] + $params);

                $request = wp_remote_post($cancelPaymentUrl, [
                    'sslverify' => csStripeGetSSLVerifyStatus(), 
                    'timeout' => 300,
                    'headers' => [
                        'Content-Type' => 'application/json',
                    ],
                    'body' => json_encode([
                        'cs_order_detail' => getCsStripeOrderDetailFromWcOrder($wc_order),
                    ])
                ]);
                if (is_wp_error($request)) {
                    csStripeErrorLog($request, "Void order request error!");
                    $wc_order->add_order_note("Void order request error!");
                    $wc_order->update_status('failed', 'Order void failed');
                    return false;
                }
                $responseBody = wp_remote_retrieve_body($request);
                $data = json_decode($responseBody);
                if (empty($data)) {
                    csStripeErrorLog($responseBody, "Void order error! Empty response");
                    $wc_order->add_order_note("Void order error! Empty response");
                    return false;
                }

                if ($data->status !== 'success') {
                    $wc_order->add_order_note($data->message);
                    return false;
                }
                $paymentIntent = $data->payment_intent;
                csStripeSaveTransactionId($wc_order, $paymentIntent->id);
                $wc_order->update_meta_data(METAKEY_STRIPE_SYNC_TRACKING_INFO, OPT_CS_STRIPE_NOT_SYNCED);
                updateFeeNetOrderStripe($data->charge, $wc_order);
                $wc_order->add_order_note(sprintf(__('Stripe Void complete (Payment Intent ID: %s)', 'lazy'), $paymentIntent->id));
                $wc_order->update_meta_data(METAKEY_LAZY_STRIPE_INTENT_AUTHORIZED, 'false');
                $wc_order->update_meta_data(METAKEY_LAZY_STRIPE_CAPTURED, 'true');
                $wc_order->save();
                $wc_order->update_status('cancelled', 'Order Cancelled');
                return true;
            }

            /**
             * Add the gateways to WooCommerce.
             *
             * @since 1.0.0
             * @version 4.0.0
             */
            public function add_gateways($gateways)
            {
                $gateways[] = 'WC_Lazy_Gateway_Stripe'; // your class name is here
                return $gateways;
            }

            public function add_settings_link($links)
            {
                $settings = array(
                    'settings' => sprintf(
                        '<a href="%s">%s</a>',
                        admin_url('admin.php?page=wc-settings&tab=checkout&section=lazy_stripe'),
                        'Settings'
                    )
                );
                return array_merge($settings, $links);
            }
        }

        WC_Lazy_Stripe::get_instance();

        if (!class_exists('WC_Payment_Gateway')) {
            return;
        }

        class WC_Lazy_Gateway_Stripe extends WC_Payment_Gateway
        {
            /**
             * Whether or not logging is enabled
             *`
             * @var bool
             */
            public static $log_enabled = false;
            public static $lazy_stripe_is_inited = false;

            /**
             * Logger instance
             *
             * @var WC_Logger
             */
            public static $log = false;

            /**
             * @var string
             */
            public $productTitleSetting = 'last_word';
            public $userDefineProductTitle = 'ME';
            public $randomProductTitleList = '';

            /**
             * @var string
             */
            public $invoice_prefix;

            /**
             * Class constructor, more about it in Step 3
             */
            
            /**
             * @var Singleton The reference the *Singleton* instance of this class
             */
            private static $instance;

            /**
             * Returns the *Singleton* instance of this class.
             *
             * @return Singleton The *Singleton* instance.
             */
            public static function get_instance()
            {
                if (null === self::$instance) {
                    self::$instance = new self();
                }
                return self::$instance;
            }
            
            public function __construct()
            {
                $this->id = 'lazy_stripe'; // payment gateway plugin ID
                $this->icon = ''; // URL of the icon that will be displayed on checkout page near your gateway name
                $this->has_fields = true; // in case you need a custom credit card form
                $this->payment_mode = $this->get_option('payment_mode');
                $this->method_title = 'CardsShield Gateway Stripe';
                $this->order_button_text = __('Place order', 'woocommerce');
                $this->method_description = 'CardsShield Gateway Stripe'; // will be displayed on the options page
                $this->invoice_prefix = $this->get_option('invoice_prefix');
                $this->productTitleSetting    = $this->get_option( 'product_title_setting' );
                $this->userDefineProductTitle = $this->get_option( 'user_define_product_title' );
                $this->randomProductTitleList = $this->get_option( 'random_product_title_list' );
                $this->sslverify = $this->get_option( 'sslverify' );

                // gateways can support subscriptions, refunds, saved payment methods,
                // but in this tutorial we begin with simple payments
                $this->supports = array(
                    'products',
                    'refunds',
                );

                // Load the settings.
                $this->init_form_fields();
                $this->init_settings();

                // Define user set variables.
                $this->enabled = $this->get_option('enabled');
                $this->title = $this->get_option('title');
                $this->description = $this->get_option('description');
                $this->payment_notes = $this->get_option('payment_notes');
                $this->description .= ' ' . $this->payment_notes;
                $this->description = trim($this->description);
                if (!self::$lazy_stripe_is_inited) {
                    self::$lazy_stripe_is_inited = true;
                    add_filter('manage_edit-shop_order_columns', [$this, 'add_lazy_shield_url'], 10, 1);
                    add_filter('manage_shop_order_posts_custom_column', [$this, 'add_lazy_order_values'], 10, 2);
                    if (get_option('woocommerce_custom_orders_table_enabled') === 'yes') {
                        add_filter( 'woocommerce_shop_order_list_table_columns', [ $this, 'add_lazy_shield_url' ], 10, 1 );
                        add_action('woocommerce_shop_order_list_table_custom_column', function ($column, $wc_order) {
                            $this->add_lazy_order_values($column, $wc_order->get_id());
                        }, 10, 2);
                    }
                }
                add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
                add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));

                // process admin CardsShield Gateway Stripe
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
                // add_action( 'woocommerce_order_status_on-hold_to_processing', array( $this, 'capture_payment' ) );
                // add_action( 'woocommerce_order_status_on-hold_to_completed', array( $this, 'capture_payment' ) );
                // add_action( 'woocommerce_api_lazy-process-payment', array( $this, 'webhook' ) );

                if (!$this->is_valid_for_use()) {
                    $this->enabled = 'no';
                }
            }

            public function add_lazy_shield_url($columns)
            {
                $statusColumnPos = array_search('order_status', array_keys($columns), true);
                $insertPos = false === $statusColumnPos ? count($columns) : $statusColumnPos + 1;

                return array_merge(
                    array_slice($columns, 0, $insertPos),
                    [
                        'lazy_shield_url' => 'Shield URL',
                    ],
                    array_slice($columns, $insertPos)
                );
            }

            public function add_lazy_order_values($column, $wc_order_id)
            {
                $this->add_lazy_shield_url_value($column, $wc_order_id);
            }

            public function add_lazy_shield_url_value($column, $wc_order_id)
            {
                if ('lazy_shield_url' != $column) {
                    return;
                }
                $wc_order = wc_get_order($wc_order_id);

                if (!is_a($wc_order, \WC_Order::class)) {
                    return;
                }
                if ($wc_order->get_payment_method() === 'lazy_stripe') {
                    echo '[Stripe] ' . $wc_order->get_meta( METAKEY_STRIPE_PROXY_URL);
                }
            }

            /**
             * Plugin options, we deal with it in Step 3 too
             */
            public function init_form_fields()
            {
                $this->form_fields = array(
                    'shield_protocol' => lazy_stripe_protocol_field(),
                    'enabled' => array(
                        'title' => 'Enable/Disable',
                        'label' => 'Enable CardsShield Gateway',
                        'type' => 'checkbox',
                        'description' => '',
                        'default' => 'no'
                    ),
                    'title' => array(
                        'title' => 'Title',
                        'type' => 'text',
                        'description' => '--------------------------------------------------------------',
                        'default' => 'Card',
                        'desc_tip' => false,
                    ),
                    'intent' => [
                        'title' => 'Payment Intent',
                        'type' => 'select',
                        'class' => [],
                        'input_class' => ['wc-enhanced-select'],
                        'default' => 'capture',
                        'desc_tip' => true,
                        'description' => 'The intent to either capture payment immediately or authorize a payment for an order after order creation.',
                        'options' => [
                            OPT_LAZY_STRIPE_INTENT_CAPTURE => 'Capture',
                            OPT_LAZY_STRIPE_INTENT_AUTHORIZE => 'Authorize',
                        ],
                    ],
                    'card_icons' => array(
                        'type' => 'multiselect',
                        'title' => 'Show payment icons',
                        'class' => 'wc-enhanced-select',
                        'default' => array('visa', 'mastercard', 'american_express', 'discover', 'diners', 'jcb'),
                        'options' => array(
                            'visa' => 'Visa',
                            'paypal' => 'Paypal',
                            'mastercard' => 'MasterCard',
                            'jcb' => 'JCB',
                            'discover' => 'Discover',
                            'diners' => 'Diners Club',
                            'american_express' => 'American Express',
                        ),
                        'desc_tip' => true,
                        'description' => 'The selected icons will show customers which credit card brands you accept.',
                    ),
                    'statement_descriptor' => array(
                        'title' => 'Statement desc. suffix',
                        'type' => 'text',
                        'description' => __('Statement descriptors are limited to 22 characters, cannot use the special characters >, <, ", \, \', *, and must not consist solely of numbers. This will appear on your customer\'s statement in capital letters.', 'woocommerce-gateway-stripe'),
                        'default' => '',
                        'desc_tip' => true,
                        'required' => true,
                    ),
                    'invoice_prefix' => array(
                        'title' => __('Invoice Prefix', 'woocommerce-gateway-stripe-express-checkout'),
                        'type' => 'text',
                        'description' => __('Please enter a prefix for your invoice numbers.', 'woocommerce-gateway-stripe-express-checkout'),
                        'default' => 'WC-',
                        'desc_tip' => true,
                        'required' => true,
                    ),
                    'payment_mode' => [
                        'title' => 'Payment Flow',
                        'type' => 'select',
                        'class' => [],
                        'input_class' => ['wc-enhanced-select'],
                        'default' => LAZY_STRIPE_PAYMENT_MODE_HOSTED,
                        'description' => '<b>- Stripe Checkout (highly recommended)</b>: Redirects users to Stripe hosted checkout page and supports Apple Pay, Google Pay, and many more payment methods, including Express Checkout.
<br/><b>- Card Form (not recommended)</b>: Takes credit card details directly on the checkout page and does not support Apple Pay or Google Pay.',
                        'options' => [
                            LAZY_STRIPE_PAYMENT_MODE_HOSTED => 'Stripe Checkout (highly recommended)',
                            LAZY_STRIPE_PAYMENT_MODE_EMBEDDED => 'Card Form',
                        ],
                    ],
                    'checkout_button_design' => array(
                        'type' => 'select',
                        'title' => 'Checkout button design',
                        'class' => 'wc-enhanced-select',
                        'default' => 'modern_design',
                        'options' => array(
                            'modern_design' => 'Modern design',
                            'modern_design_2' => 'Modern design (without redirection icon)',
                            'inherit_design' => 'Inherit design',
                            'inherit_design_2' => 'Inherit design (without redirection icon)',
                        ),
                    ),
                    'checkout_button_text' => array(
                        'title' => 'Checkout button text',
                        'type' => 'text',
                        'default' => 'Pay with',
                    ),
                    'checkout_button_text_inherit' => array(
                        'title' => 'Checkout button text',
                        'type' => 'text',
                        'default' => 'Continue to payment',
                    ),
                    'payment_option_desc' => array(
                        'title' => 'Payment option desc.',
                        'type' => 'textarea',
                        'default' => 'After clicking "Pay with Stripe", you will be redirected to Stripe to complete your purchase securely.',
                    ),
                    'payment_button_color' => array(
                        'title' => 'Payment button color',
                        'type' => 'text',
                        'default' => '#0374D4',
                    ),
                    'payment_button_hover_color' => array(
                        'title' => 'Payment button hover color',
                        'type' => 'text',
                        'default' => '#0374D4',
                    ),
                    'do_not_send_address' => array(
                        'title' => 'Check this if you are selling DIGITAL products',
                        'label' => 'Do not send billing & shipping address to Stripe',
                        'type' => 'checkbox',
                        'description' => '',
                        'default' => 'no'
                    ),
                    'product_title_setting'     => [
                        'title'       => 'Overwrite product title',
                        'type'        => 'select',
                        'description' => '',
                        'default'     => 'last_word',
                        'desc_tip'    => false,
                        'options'     => [
                            'last_word'     => 'Use the last word',
                            'user_define'   => 'User define',
                            'keep_original' => 'Keep the original (Not recommended)'
                        ]
                    ],
                    'user_define_product_title' => [
                        'title' => 'User define title',
                        'type' => 'text',
                        'description' => 'This will be appeared on Stripe transaction as product title, when overwrite product title is "User define" <br/> You can define title with <b>[order_id]</b> or <b>[last_word]</b>  or <b>[rand_title_from_list]</b>  and <b>[rand_N]</b> (random a N length string, N is a number > 1 ) shortcode. <br/>For example: Order #[order_id] or [rand_10] product or [last_word] product.',
                        'default' => 'Order #[order_id][rand_5][last_word] item',
                    ],
                    'random_product_title_list' => [
                        'title' => 'Random title list',
                        'type' => 'textarea',
                        'description' => 'Please enter a list of titles to randomize, separated by commas. For example: T-Shirt, Personalized Hoodie, Gift for dad',
                        'default' => "Vintage Design, Birthday Gift, Personalized, New Collection, Original Design, Original Custom, Custom Design, Custom Lover Gift, Make Your Own, New Arrival, Custom Made, Attractive Color, Stand-out from crowd, Retro Style, Classic Design, Classic Style, Customized for You, Today's Pick, Vibrant Designer, Premium Color, Vacation Mood Style, Unisex Men and Women, Timeless Charm, Celebration Ready, Unique Touch, Fresh Drop, One-of-a-Kind, Tailored Creation, Bold Hue, Eye-Catching Look, Nostalgic Vibe, Elegant Craft, Modern Twist, Made for You, Daily Highlight, Bright Accent, Getaway Inspired, All-Gender Fit, Handcrafted Feel, Seasonal Favorite, Statement Piece, Chic Appeal, Everyday Essential, Artful Blend, Casual Cool, Distinctive Edge, Playful Design, Sleek Finish, Curated Style, Freshly Designed, Personal Flair, Trendy Pick, Classic Vibes, Vibrant Touch, Effortless Style, Special Edition, Creative Spin, Standout Piece, Colorful Charm, Modern Classic, Bespoke Beauty, Fun Find, Signature Look, Relaxed Elegance, Inspired Craft, Everyday Luxury, Unique Blend, Festive Flair, Simple Sophistication, Bold Creation, Retro Charm, Fresh Perspective, Custom Vibes, Lively Design, Timeless Pick, Crafted Comfort, Striking Style, Thoughtful Gift, New Wave, Subtle Shine, Original Twist, Easygoing Look, Vibrant Craft, Classic Touch, Made to Shine, Today’s Treasure, Dynamic Hue, Vacation Vibe, Universal Appeal, Handmade Charm, Seasonal Style, Eye-Popping Design, Cool Classic, Personal Pick, Fresh Craft, Sleek Design, Creative Hue, Retro Inspired, Bold Accent, Everyday Chic, Unique Craft, Festive Touch, Modern Edge, Tailored Look, Standout Hue, Playful Vibe, Timeless Craft, Vibrant Pick, Casual Charm, Artful Touch, New Spark, Subtle Elegance, Original Hue, Relaxed Style, Inspired Look, Daily Craft, Bright Style, Classic Find, Custom Edge, Fresh Charm, Bold Twist, Unique Vibe"
                    ],
                    'payment_notes' => array(
                        'title' => 'Payment notes',
                        'type' => 'textarea',
                        'description' => __('Payment notes are limited to 100 characters, cannot use the special characters.', 'woocommerce-gateway-stripe'),
                        'default' => '',
                        'desc_tip' => true,
                    ),
                    
                    'config_proxies_button' => [
                        'id' => 'config_proxies_button',
                        'type' => 'config_proxies_button',
                        'title' => __('Config Shields', 'custom_stripe'),
                    ],
                    'sync_tracking_plugin' => [
                        'title'       => 'Sync tracking plugin',
                        'type'        => 'select',
                        'class'       => [],
                        'input_class' => [ 'wc-enhanced-select' ],
                        'default'     => OPT_CS_STRIPE_TRACKING_SYNC_PLUGIN_ADVANCED_SHIPMENT_TRACKING,
                        'desc_tip'    => true,
                        'description' => '',
                        'options'     => [
                            OPT_CS_STRIPE_TRACKING_SYNC_PLUGIN_ADVANCED_SHIPMENT_TRACKING => '1. Advanced Shipment Tracking for WooCommerce',
                            OPT_CS_STRIPE_TRACKING_SYNC_PLUGIN_ORDERS_TRACKING            => '2. Orders Tracking for WooCommerce',
                            OPT_CS_STRIPE_TRACKING_SYNC_PLUGIN_DIANXIAOMI                 => '3. Dianxiaomi - WooCommerce ERP',
                        ],
                    ],
                    'sync_tracking_automatic' => [
                        'title'       => 'Sync tracking automatically',
                        'label'       => 'Enable',
                        'type'        => 'checkbox',
                        'description' => '',
                        'default'     => 'no'
                    ],
                    'transaction_logs_enable' => [
                        'title'       => 'Transaction logs',
                        'label'       => 'Enable',
                        'type'        => 'checkbox',
                        'description' => '',
                        'default'     => 'no'
                    ],
                    'send_email_notice_to_admin' => [
                        'title' => 'Send email notification to admins',
                        'label' => 'Enable',
                        'type' => 'checkbox',
                        'description' => '',
                        'default' => 'yes'
                    ],
                    'stripe_advance_settings' => [
                        'title' => 'Advance Settings',
                        'type' => 'title',
                        'description' => '<button type="button" id="stripe_advance_setting_toggle" class="button">Show/Hide</button>',
                    ],
                    'sslverify' => [
                        'title' => 'SSL verify',
                        'label' => 'Enable',
                        'type' => 'checkbox',
                        'default' => 'no'
                    ],
                    'custom_card_icon_css' => [
                        'title' => 'Custom Stripe icon css',
                        'type' => 'textarea',
                        'default' => '/*
.lazy-stripe-payment-icon {
    width: 50px;
}
*/',
                        'css' => 'width: 400px; min-height: 110px; resize: both;',
                    ]
                );
            }

            /**
             * Screen button Field
             */
            public function generate_config_proxies_button_html($key, $value)
            {
                ?>
                <tr valign="top">
                    <td colspan="2" class="forminp forminp-<?php echo sanitize_title($value['type']) ?>">
                        <a href="<?php echo admin_url('admin.php?page=lazy-gateway-stripe'); ?>"
                           class="button"><?php _e('Config Shields', 'custom_stripe'); ?></a>
                    </td>
                </tr>
                <?php
            }


            /**
             * Check if this gateway is enabled and available in the user's country.
             *
             * @return bool
             */
            public function is_valid_for_use()
            {
                return true;
            }

            /**
             * Get_icon function.
             *
             * @return string
             * @version 4.0.0
             * @since 1.0.0
             */
            public function get_icon()
            {
                $icons = $this->get_option('card_icons');
                $icons_str = '';
                if (is_array($icons)) {
                    foreach ($icons as $index => $icon) {
                        if ($index > 3) break;
                        $icons_str = '<img class="lazy-stripe-payment-icon" src="' . cs_stripe_icon_src('/assets/images/icons/' . $icon . '.svg', __FILE__, OPT_LAZY_STRIPE_VERSION) . '" style="float: right; border-radius: 2px; max-height: 25px;padding-top: 2px; margin-right: 4px"/>' . $icons_str;
                    }
                    if (count($icons) > 4) {
                        $icons_str = '<img class="lazy-stripe-payment-icon" src="' . cs_stripe_icon_src('/assets/images/icons/' . (count($icons) - 4) . '.svg', __FILE__, OPT_LAZY_STRIPE_VERSION) . '" style="float: right; border-radius: 2px; max-height: 25px;padding-top: 2px; margin-right: 4px"/>' . $icons_str;
                    }
                }

                $icons_str .= '<style type="text/css">' . $this->get_option('custom_card_icon_css') . '</style>';
                return apply_filters('woocommerce_gateway_icon', $icons_str, $this->id);
            }

            /**
             * Load admin scripts.
             *
             * @since 3.3.0
             */
            public function admin_scripts()
            {
                $screen = get_current_screen();
                $screen_id = $screen ? $screen->id : '';

                if ('woocommerce_page_wc-settings' !== $screen_id) {
                    return;
                }
                wp_enqueue_script( 'woocommerce_stripe_admin', plugins_url('assets/js/payment_settings.js', __FILE__), array(), OPT_LAZY_STRIPE_VERSION, true );
            }

            function lazy_stripe_add_button_hosted_checkout()
            {
                $nextProxyUrl = WC()->session->get('lazy-stripe-proxy-active-url');
                if ($nextProxyUrl && $this->get_option('payment_mode') === LAZY_STRIPE_PAYMENT_MODE_HOSTED) {
                            if (in_array($this->get_option('checkout_button_design'), ['modern_design', 'modern_design_2'])) {
                                ?>
                                <style>
                                    .SubmitButton--complete:hover {
                                        background-color: <?= ($this->get_option('payment_button_hover_color') ?: 'rgb(0, 94, 187)') . '!important' . ';' ?>
                                    }
                                    .SubmitButton {
                                        background-color: <?= ($this->get_option('payment_button_color') ?: 'rgb(0, 116, 212)') . '!important' . ';' ?>
                                    }
                                </style>
                                <div style="display: none" id="cs-stripe-use-payment-hosted-modern-design"></div>
                                <div id="cs-stripe-button-container">
                                    <button class="SubmitButton SubmitButton--complete" type="submit"
                                            data-testid="hosted-payment-submit-button"
                                            style="color: rgb(255, 255, 255); width: 100%; min-width: 200px">
                                        <div class="SubmitButton-Shimmer SubmitButton--complete-Shimmer"
                                             style="background: linear-gradient(to right, rgba(0, 116, 212, 0) 0%, rgb(58, 139, 238) 50%, rgba(0, 116, 212, 0) 100%);"></div>
                                        <div class="SubmitButton-TextContainer"><span
                                                    class="SubmitButton-Text SubmitButton-Text--current Text Text-color--default Text-fontWeight--500 Text--truncate"
                                                    aria-hidden="false">
                                            <svg height="15px" viewBox="0 0 25 27" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
                                                <title>lock-solid</title>
                                                <g id="Page-1" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                                    <g id="Artboard" transform="translate(-835.000000, -365.000000)" fill="#FFFFFF" fill-rule="nonzero">
                                                        <g id="lock-solid" transform="translate(835.000000, 365.000000)">
                                                            <path d="M12.5,0 C16.9363839,0 20.5357143,3.40136719 20.5357143,7.59375 L20.5357143,10.125 L21.4285714,10.125 C23.3984375,10.125 25,11.6384766 25,13.5 L25,23.625 C25,25.4865234 23.3984375,27 21.4285714,27 L3.57142857,27 C1.6015625,27 0,25.4865234 0,23.625 L0,13.5 C0,11.6384766 1.6015625,10.125 3.57142857,10.125 L4.46428571,10.125 L4.46428571,7.59375 C4.46428571,3.40136719 8.06361607,0 12.5,0 Z M13.1666667,15.1875 L11.8333333,15.1875 C11.2810486,15.1875 10.8333333,15.6352153 10.8333333,16.1875 L10.8333333,16.1875 L10.8333333,20.9375 C10.8333333,21.4897847 11.2810486,21.9375 11.8333333,21.9375 L11.8333333,21.9375 L13.1666667,21.9375 C13.7189514,21.9375 14.1666667,21.4897847 14.1666667,20.9375 L14.1666667,20.9375 L14.1666667,16.1875 C14.1666667,15.6352153 13.7189514,15.1875 13.1666667,15.1875 L13.1666667,15.1875 Z M12.5,3.375 C10.0334821,3.375 8.03571429,5.26289062 8.03571429,7.59375 L8.03571429,10.125 L16.9642857,10.125 L16.9642857,7.59375 C16.9642857,5.26289062 14.9665179,3.375 12.5,3.375 Z" id="Shape"></path>
                                                        </g>
                                                    </g>
                                                </g>
                                            </svg>
                                                <?= $this->get_option('checkout_button_text'); ?>
                                           <svg style="transform: translateY(6px)" width="60px" viewBox="0 0 96 40"
                                                version="1.1" xmlns="http://www.w3.org/2000/svg"
                                                xmlns:xlink="http://www.w3.org/1999/xlink">
                                                <title>Stripe wordmark - white</title>
                                                <g id="Page-1" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                                    <g id="Artboard" transform="translate(-245.000000, -358.000000)" fill="#FFFFFF">
                                                        <g id="Stripe-wordmark---white" transform="translate(245.000000, 358.000000)">
                                                            <path d="M96,20.6675567 C96,13.8317757 92.6933333,8.43791722 86.3733333,8.43791722 C80.0266667,8.43791722 76.1866667,13.8317757 76.1866667,20.6141522 C76.1866667,28.6515354 80.72,32.7102804 87.2266667,32.7102804 C90.4,32.7102804 92.8,31.9893191 94.6133333,30.9746328 L94.6133333,25.6341789 C92.8,26.5420561 90.72,27.1028037 88.08,27.1028037 C85.4933333,27.1028037 83.2,26.1949266 82.9066667,23.0440587 L95.9466667,23.0440587 C95.9466667,22.6969292 96,21.3084112 96,20.6675567 L96,20.6675567 Z M82.8266667,18.1308411 C82.8266667,15.1134846 84.6666667,13.858478 86.3466667,13.858478 C87.9733333,13.858478 89.7066667,15.1134846 89.7066667,18.1308411 L82.8266667,18.1308411 Z M65.8933333,8.43791722 C63.28,8.43791722 61.6,9.66622163 60.6666667,10.5206943 L60.32,8.86515354 L54.4533333,8.86515354 L54.4533333,40 L61.12,38.5847797 L61.1466667,31.0280374 C62.1066667,31.7222964 63.52,32.7102804 65.8666667,32.7102804 C70.64,32.7102804 74.9866667,28.8651535 74.9866667,20.400534 C74.96,12.6568758 70.56,8.43791722 65.8933333,8.43791722 L65.8933333,8.43791722 Z M64.2933333,26.835781 C62.72,26.835781 61.7866667,26.2750334 61.1466667,25.5807744 L61.12,15.6742323 C61.8133333,14.8998665 62.7733333,14.3658211 64.2933333,14.3658211 C66.72,14.3658211 68.4,17.0894526 68.4,20.5874499 C68.4,24.1655541 66.7466667,26.835781 64.2933333,26.835781 L64.2933333,26.835781 Z M45.28,6.86248331 L51.9733333,5.42056075 L51.9733333,0 L45.28,1.41522029 L45.28,6.86248331 Z M45.28,8.89185581 L51.9733333,8.89185581 L51.9733333,32.2563418 L45.28,32.2563418 L45.28,8.89185581 Z M38.1066667,10.8678238 L37.68,8.89185581 L31.92,8.89185581 L31.92,32.2563418 L38.5866667,32.2563418 L38.5866667,16.4218959 C40.16,14.3658211 42.8266667,14.7396529 43.6533333,15.0333778 L43.6533333,8.89185581 C42.8,8.57142857 39.68,7.98397864 38.1066667,10.8678238 Z M24.7733333,3.09746328 L18.2666667,4.48598131 L18.24,25.8744993 C18.24,29.8264352 21.2,32.7369826 25.1466667,32.7369826 C27.3333333,32.7369826 28.9333333,32.3364486 29.8133333,31.8558077 L29.8133333,26.435247 C28.96,26.7823765 24.7466667,28.0106809 24.7466667,24.058745 L24.7466667,14.5794393 L29.8133333,14.5794393 L29.8133333,8.89185581 L24.7466667,8.89185581 L24.7733333,3.09746328 Z M6.74666667,15.6742323 C6.74666667,14.6328438 7.6,14.2323097 9.01333333,14.2323097 C11.04,14.2323097 13.6,14.8464619 15.6266667,15.941255 L15.6266667,9.66622163 C13.4133333,8.78504673 11.2266667,8.43791722 9.01333333,8.43791722 C3.6,8.43791722 0,11.2683578 0,15.9946595 C0,23.364486 10.1333333,22.1895861 10.1333333,25.3671562 C10.1333333,26.5954606 9.06666667,26.9959947 7.57333333,26.9959947 C5.36,26.9959947 2.53333333,26.0881175 0.293333333,24.8598131 L0.293333333,31.2149533 C2.77333333,32.2830441 5.28,32.7369826 7.57333333,32.7369826 C13.12,32.7369826 16.9333333,29.9866489 16.9333333,25.2069426 C16.9066667,17.2496662 6.74666667,18.6648865 6.74666667,15.6742323 Z"
                                                                  id="Shape"></path>
                                                        </g>
                                                    </g>
                                                </g>
                                            </svg>
                                            </span>
                                        </div>
                                    </button>
                                </div>
                                <?php
                            } else {
                                ?>
                                    <div style="display: none" id="cs-stripe-use-payment-hosted-inherit-design"></div>
                                    <div style="display: none;" id="cs-stripe-checkout-inherit-btn-text" data-value="<?= $this->get_option('checkout_button_text_inherit'); ?>"> </div> 
                                <?php
                            }
                }
            }

            /**
             * You will need it if you want your custom credit card form, Step 4 is about it
             */
            public function payment_fields()
            {
                global $woocommerce;
                if (isCsStripeEnableEndpointMode()) {
                    findAndSetNextProxy();
                    $nextProxy = ['id' => WC()->session->get('lazy-stripe-proxy-active-id'), 'url' => WC()->session->get('lazy-stripe-proxy-active-url')];
                } else {
                    $nextProxy = stripeGetShieldUrl();
                }
                if (!isset($nextProxy['url'])) {
                    if (!isCsStripeEnableEndpointMode()) {
                        if (isStripeShieldReachAmount(WC()->cart->get_total(false))) {
                            csStripeSendMailShieldReachAmount();
                        }
                    }
                    
                    ?>
                        <div>We cannot accept any payments right now. Please comeback to try tomorrow or select other
                            payment methods if available [3].
                        </div>
                    <?php
                } else if ($this->get_option('payment_mode') === LAZY_STRIPE_PAYMENT_MODE_EMBEDDED) {
                    // ok, let's display some description before the payment form
                    if ($this->description) {
                        // display the description with <p> tags etc.
                        echo wpautop(wp_kses_post($this->description));
                    }
                    if (isset($_GET['pay_for_order']) && get_query_var('order-pay')) {
                        lazy_stripe_generate_input_order();
                    }
                    $params = [
                        'token' => generateRandomString(25),
                        "need-decide-testmode" => 1,
                        "lang" => csGetCurrentLanguage(),
                        "amount" => WC()->cart->get_total(false) * 100,
                        "currency" => get_woocommerce_currency(),
                    ]
                    ?>
                    <input style="display:none;" name="lazy-stripe-payment-method-id"/>
                    <iframe class="cs_stripe_element" id="payment-stripe-area" referrerpolicy="no-referrer"
                            sandbox="allow-downloads allow-downloads-without-user-activation allow-forms allow-modals allow-popups allow-presentation allow-same-origin allow-scripts allow-storage-access-by-user-activation allow-top-navigation allow-top-navigation-by-user-activation allow-top-navigation-to-custom-protocols"
                            src="<?= $nextProxy['url'] . '/checkout?' . csStripeBuildQuery($params) ?>"
                            height="200" frameBorder="0" style="width: 100%"></iframe>
                    <iframe class="cs_stripe_element" style="width: 100%; display: none; position: fixed; top: 0; left: 0; z-index: 99999; height: 100vh"
                            sandbox="allow-downloads allow-downloads-without-user-activation allow-forms allow-modals allow-popups allow-presentation allow-same-origin allow-scripts allow-storage-access-by-user-activation allow-top-navigation allow-top-navigation-by-user-activation allow-top-navigation-to-custom-protocols"
                            id="payment-area-stripe-to-confirm" referrerpolicy="no-referrer"
                            src="<?= $nextProxy['url'] . '/checkout?token=' . generateRandomString(26) ?>"
                            height="70" frameBorder="0"></iframe>
                    <?php
                } else if ($this->get_option('payment_mode') === LAZY_STRIPE_PAYMENT_MODE_HOSTED) {
                    if (isset($_GET['pay_for_order']) && get_query_var('order-pay')) {
                        lazy_stripe_generate_input_order();
                    }
                    if (in_array($this->get_option('checkout_button_design'), ['modern_design'])) {
                        ?>
                        <div style="width: 100%; display: flex; justify-content: center; align-content: center; padding: 20px 0">
                            <img src="<?= plugins_url('/assets/images/hosted-checkout-page/redirect.svg', __FILE__) ?>"
                                 style="width: 100%; max-height: 130px; margin-left: 20px"/>
                        </div>
                        <?php
                    }
                    if (in_array($this->get_option('checkout_button_design'), ['inherit_design'])) {
                        ?>
                        <div style="width: 100%; display: flex; justify-content: center; align-content: center; padding: 20px 0">
                            <img src="<?= plugins_url('/assets/images/hosted-checkout-page/redirect-2.svg', __FILE__) ?>"
                                 style="width: 100%; max-height: 130px; margin-left: 20px"/>
                        </div>
                        <?php
                    }
                    ?>
                        <div style="width: 100%; margin: 10px auto; font-size: inherit; text-align: center">
                            <?= $this->get_option('payment_option_desc') ?>
                        </div>
                        <?php
                        // Render the hosted Stripe button inside this gateway's payment box.
                        $this->lazy_stripe_add_button_hosted_checkout();
                        ?>
                    <?php
                }
                add_stripe_loader_ui();

            }

            /*
                * Custom CSS and JS, in most cases required only when you decided to go with a custom credit card form
                */
            public function payment_scripts()
            {
                // we need JavaScript to process a token only on cart/checkout pages, right?
                if (!is_cart() && !is_checkout()) {
                    return;
                }

                // if our payment gateway is disabled, we do not have to enqueue JS too
                if ('no' === $this->enabled) {
                    return;
                }
                wp_register_style('lazy_stripe_styles', plugins_url('assets/css/styles.css', __FILE__), [], OPT_LAZY_STRIPE_VERSION);
                wp_enqueue_style('lazy_stripe_styles');

                wp_register_script('lazy_stripe_js', plugins_url('assets/js/checkout_hook.js', __FILE__), array('jquery'), OPT_LAZY_STRIPE_VERSION, true);
                wp_enqueue_script('lazy_stripe_js');
                wp_localize_script('lazy_stripe_js', 'ajax_object', [
                    'cs_add_order_note_nonce' => wp_create_nonce('cs_add_order_note')
                ]);
            }

            /*
                * Fields validation, more in Step 5
                */
            public function validate_fields()
            {
                return true;
            }

            private function getActivateProxyUrl()
            {
                $activatedProxy = get_option(Opt_Lazy_Stripe_Activated_Proxy, null);
                return empty($activatedProxy["url"]) ? null : $activatedProxy["url"] . '/index.php';
            }


            public function getProductTitle($productTitle, $orderId)
            {
                switch ($this->productTitleSetting) {
                    case 'user_define':
                        $title = $this->userDefineProductTitle;
                        $title = str_replace('[order_id]', strval($orderId), $title);
                        $randomTitle = '';
                        if (!empty($this->randomProductTitleList)) {
                            $explodeList = explode(',', $this->randomProductTitleList);
                            if (!empty($explodeList)) {
                                $randomTitle = trim($explodeList[array_rand($explodeList)]);
                            }
                        }
                        $title = str_replace('[rand_title_from_list]', $randomTitle, $title);

                        $explode = explode(' ', $productTitle);
                        $title = str_replace('[last_word]', end($explode), $title);
                        preg_match_all('/\[rand_\d+\]/', $title, $matchRandStrings);
                        if (is_array($matchRandStrings) && count($matchRandStrings)) {
                            foreach ($matchRandStrings[0] as $matchRandString) {
                                $numberOfStringRand = preg_replace('/[^0-9]/', '', $matchRandString);
                                $stringRandom = $this->generateRandomString((int)$numberOfStringRand);
                                $title = str_replace($matchRandString, $stringRandom, $title);
                            }
                        }
                        return $title;
                    case 'keep_original':
                        return $productTitle;
                    case 'last_word':
                    default:
                        $explode = explode(' ', $productTitle);
                        return end($explode);
                }
            }

            public function generateRandomString($length = 10)
            {
                $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
                $charactersLength = strlen($characters);
                $randomString = '';
                for ($i = 0; $i < $length; $i++) {
                    $randomString .= $characters[random_int(0, $charactersLength - 1)];
                }
                return $randomString;
            }

            public function get_number_of_decimal_digits()
            {
                return $this->is_currency_supports_zero_decimal() ? 0 : 2;
            }

            public function is_currency_supports_zero_decimal()
            {
                return in_array(get_woocommerce_currency(), array('HUF', 'JPY', 'TWD'));
            }

            public function process_payment($order_id)
            {
                if ($this->get_option('payment_mode') === LAZY_STRIPE_PAYMENT_MODE_HOSTED) {
                    return $this->process_hosted_payment($order_id);
                }
                global $woocommerce;
                // we need it to get any order details
                $order = wc_get_order($order_id);
                csStripeStoreCustomerIp($order);
                if ($order->is_paid()) {
                    echo json_encode([
                        'result' => 'success',
                        'redirect' => $order->get_checkout_order_received_url(),
                    ]);
                }
                $paymentStripeIntent = $this->get_option('intent');
                $activeProxyId = WC()->session->get('lazy-stripe-proxy-active-id');
                $activeProxyUrl = WC()->session->get('lazy-stripe-proxy-active-url');
                $activatedProxy = [
                    'id' => $activeProxyId,
                    'url' => $activeProxyUrl,
                ];

                if (!isset($activatedProxy['url'])) {
                    csStripeErrorLog($activeProxyId, "Can't find activated proxy!\n");
                    wc_add_notice('We cannot process your payment right now, please try another payment method.[14]', 'error');
                    return [
                        'result' => 'failure',
                        'reload' => true
                    ];
                }

                $shippingName = $order->get_shipping_first_name() . " " . $order->get_shipping_last_name();
                $shippingAddress1 = $order->get_shipping_address_1();
                $shippingAddress2 = $order->get_shipping_address_2();
                $shippingCity = $order->get_shipping_city();
                $shippingCountry = $order->get_shipping_country();
                $shippingPostCode = $order->get_shipping_postcode();
                $shippingState = $order->get_shipping_state();

                // Billing
                $billingName = $order->get_billing_first_name() . " " . $order->get_billing_last_name();
                $billingAddress1 = $order->get_billing_address_1();
                $billingAddress2 = $order->get_billing_address_2();
                $billingCity = $order->get_billing_city();
                $billingCountry = $order->get_billing_country();
                $billingPostCode = $order->get_billing_postcode();
                $billingState = $order->get_billing_state();

                $shippingName = (empty($order->get_shipping_first_name()) && empty($order->get_shipping_last_name())) ? $billingName : $shippingName;
                $shippingAddress1 = empty($shippingAddress1) ? $billingAddress1 : $shippingAddress1;
                $shippingAddress2 = empty($shippingAddress2) ? $billingAddress2 : $shippingAddress2;
                $shippingCity = empty($shippingCity) ? $billingCity : $shippingCity;
                $shippingCountry = empty($shippingCountry) ? $billingCountry : $shippingCountry;
                $shippingPostCode = empty($shippingPostCode) ? $billingPostCode : $shippingPostCode;
                $shippingState = empty($shippingState) ? $billingState : $shippingState;


                // Log processing proxyUrl
                $order->add_order_note(sprintf(__('Starting checkout with Stripe proxy %s', 'lazy'), $activatedProxy['url']), 0, false);

                $items = [];

                $order_items = $order->get_items();
                foreach ($order_items as $it) {
                    $product = wc_get_product($it->get_product_id());
                    //$product_name = $product->get_name(); // Get the product name
                    $product_name = $this->getProductTitle($product->get_name(), $order_id);

                    $item_quantity = $it->get_quantity(); // Get the item quantity

                    $amount = round($it['line_subtotal'] / $it['qty'], $this->get_number_of_decimal_digits());

                    $items[] = [
                        "name" => $product_name,
                        "quantity" => $item_quantity,
                        "total" => $amount
                    ];
                }
                $paymentIntentIdRequest = null;
                if (!empty(csStripeGetTransactionId($order))) {
                    $proxyProcessingUrl = $order->get_meta( METAKEY_STRIPE_PROXY_URL);
                    if ($proxyProcessingUrl) {
                        if (WC()->session->get('lazy-stripe-proxy-active-url') == $proxyProcessingUrl) {
                            $paymentIntentIdRequest = csStripeGetTransactionId($order);
                        }
                    } else {
                        $paymentIntentIdRequest = csStripeGetTransactionId($order);
                    }
                }
                $response = wp_remote_post($activatedProxy['url'] . '?' . csStripeBuildQuery([
                        'lazy-stripe-pe-v2-make-payment' => uniqid(),
                        'capture_method' => $paymentStripeIntent === OPT_LAZY_STRIPE_INTENT_AUTHORIZE ? 'manual' : 'automatic',
                        'payment_intent' => $paymentIntentIdRequest,
                        'payment_method_id' => $_POST['lazy-stripe-payment-method-id'],
                        'order_id' => $order->get_id(),
                        'order_invoice' => $this->invoice_prefix . $order->get_order_number(),
                        'order_invoice_prefix' => $this->invoice_prefix,
                        'order_items' => $items,
                        'statement_descriptor' => $this->get_option('statement_descriptor'),
                        'merchant_site' => get_home_url(),
                        'amount' => $order->get_total(),
                        'customer_zipcode' => $billingPostCode,
                        'customer_email' => $order->get_billing_email(),
                        'currency' => $order->get_currency(),
                        'customer_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                        'email' => $order->get_billing_email(),
                        'shipping' => [
                            'name' => $shippingName,
                            'phone' => method_exists($order, 'get_shipping_phone') && $order->get_shipping_phone() ? $order->get_shipping_phone() : $order->get_billing_phone(),
                            'address' => [
                                'city' => $shippingCity,
                                'country' => $shippingCountry,
                                'line1' => $shippingAddress1,
                                'line2' => $shippingAddress2,
                                'postal_code' => $shippingPostCode,
                                'state' => $shippingState,
                            ],
                        ]
                    ]), [
                    'sslverify' => csStripeGetSSLVerifyStatus(),
                    'timeout' => 5 * 60,
                    'headers' => [
                        'Content-Type' => 'application/json',
                    ],
                    'body' => json_encode([
                        'cs_order_detail' => getCsStripeOrderDetailFromWcOrder($order),
                    ])
                ]);
                $order->update_meta_data( METAKEY_STRIPE_PROCESSING_ORDER_KEY, WC()->session->get('lazy-stripe-processing-order-key'));
                $order->update_meta_data( METAKEY_STRIPE_PROXY_URL, $activatedProxy['url']);
                $order->update_meta_data( '_shield_payment_method', 'stripe');
                $order->update_meta_data( '_shield_payment_url', $activatedProxy['url']);
                $order->update_meta_data( METAKEY_STRIPE_PROXY_ID, $activatedProxy['id']);
                $order->save_meta_data();
                if (is_wp_error($response)) {
                    csStripeErrorLog($response, 'Stripe request error');
                    wc_add_notice('We cannot process your payment right now, please try another payment method.[15]', 'error');
                    return false;
                }
                $body = wp_remote_retrieve_body($response);
                $body = json_decode($body);
                if (!is_object($body)) {
                    csStripeErrorLog(wp_remote_retrieve_body($response), 'Stripe request session returned invalid JSON');
                    $order->update_status('failed');
                    wc_add_notice('We cannot process your payment right now, please try another payment method.[28]', 'error');
                    return false;
                }
                if ($body->status === 'success') {
                    $paymentIntent = $body->payment_intent;
                    if (isset($body->payment_intent)) {
                        $paymentIntentId = $body->payment_intent->id;
                        csStripeSaveTransactionId($order, $paymentIntentId);
                        csStripeDebugLog($body, "save transaction ID: $paymentIntentId to order ");
                        $order->update_meta_data( METAKEY_STRIPE_SYNC_TRACKING_INFO, OPT_CS_STRIPE_NOT_SYNCED);
                        $order->update_meta_data( METAKEY_CS_STRIPE_RAND_ORDER_ID, $body->payment_intent->metadata->order_id ?? null);
                        $order->save_meta_data();
                        $order->add_order_note(sprintf(__('Stripe confirm Payment Intent by proxy %s, Payment Intent ID: %s', 'lazy'),
                            $activatedProxy['url'],
                            $paymentIntentId
                        ));
                    }
                    if (isset($_GET['pay_for_order'])) {
                        echo json_encode([
                            'result' => 'success',
                            'redirect' => sprintf('#cs-confirm-pi-%s:%s:%s:%s', $paymentIntent->client_secret, $order_id, $paymentIntent->id, uniqid()),
                        ]);
                        exit();
                    }
                    return [
                        'result' => 'success',
                        'redirect' => sprintf('#cs-confirm-pi-%s:%s:%s:%s', $paymentIntent->client_secret, $order_id, $paymentIntent->id, uniqid()),
                    ];
                } else {
                    csStripeErrorLog($response, 'Stripe request payment error');
                    // Empty cart
                    $order->update_status('failed');
                    if ($body->code === 'domain_whitelist_not_allow') {
                        $order->add_order_note(sprintf(__('Stripe charged ERROR by proxy %s, ERROR message: %s', 'lazy'),
                            $activatedProxy['url'],
                            'Domain whitelist is required'
                        ));
                    } else if ($body->code === 'customer_zipcode_not_allow') {
                        csStripeSendMailOrderBlacklisted($order->get_id());
                        $order->add_order_note(sprintf(__('Stripe charged ERROR by proxy %s, ERROR message: %s', 'lazy'),
                            $activatedProxy['url'],
                            "Customer's zipcode is blacklisted"
                        ));
                        wc_add_notice('We cannot process your payment right now, please try another payment method.[16]', 'error');
                        return false;
                    } else if ($body->code === 'customer_email_not_allow') {
                        csStripeSendMailOrderBlacklisted($order->get_id());
                        $order->add_order_note(sprintf(__('Stripe charged ERROR by proxy %s, ERROR message: %s', 'lazy'),
                            $activatedProxy['url'],
                            "Customer's email is blacklisted"
                        ));
                        wc_add_notice('We cannot process your payment right now, please try another payment method.[17]', 'error');
                        return false;
                    } else if ($body->code === 'states_cities_not_allow') {
                        csStripeSendMailOrderBlacklisted($order->get_id());
                        $order->add_order_note(sprintf(__('Stripe charged ERROR by proxy %s, ERROR message: %s', 'lazy'),
                            $activatedProxy['url'],
                            "Customer's State and City is blacklisted"
                        ));
                        wc_add_notice('We cannot process your payment right now, please try another payment method.[18]', 'error');
                        return false;
                    } else if ($body->code === 'order_total_not_allow') {
                        $order->add_order_note(sprintf(__('Stripe charged ERROR by proxy %s, ERROR message: %s', 'lazy'),
                            $activatedProxy['url'],
                            "Order value exceeds Stripe capability"
                        ));
                        wc_add_notice('We cannot process your payment right now, please try another payment method.[19]', 'error');
                        return false;
                    } else {
                        $err = $body->err;
                        $paymentIntentId = csStripeGetTransactionId($order);
                        if (isset($err->payment_intent)) {
                            $paymentIntentId = $err->payment_intent->id;
                            csStripeSaveTransactionId($order, $paymentIntentId);
                            $order->update_meta_data( METAKEY_STRIPE_SYNC_TRACKING_INFO, OPT_CS_STRIPE_NOT_SYNCED);
                            $order->update_meta_data( METAKEY_CS_STRIPE_RAND_ORDER_ID, $err->payment_intent->metadata->order_id ?? null);
                            $order->save_meta_data();
                        }
                        $order->add_order_note(sprintf(__('Stripe charged ERROR by proxy %s, ERROR message: %s, Payment Intent ID: %s', 'lazy'),
                            $activatedProxy['url'],
                            is_string($err) ? $err : $err->message,
                            $paymentIntentId
                        ));
                    }
                    wc_add_notice('We cannot process your payment right now, please try another payment method.[20]', 'error');
                    return false;
                }
            }

            public function process_hosted_payment($order_id)
            {
                global $woocommerce;
                // we need it to get any order details
                $order = wc_get_order($order_id);
                csStripeStoreCustomerIp($order);
                if ($order->is_paid()) {
                    echo json_encode([
                        'result' => 'success',
                        'redirect' => $order->get_checkout_order_received_url(),
                    ]);
                }
                $paymentStripeIntent = $this->get_option('intent');
                $activeProxyId = WC()->session->get('lazy-stripe-proxy-active-id');
                $activeProxyUrl = WC()->session->get('lazy-stripe-proxy-active-url');
                $activatedProxy = [
                    'id' => $activeProxyId,
                    'url' => $activeProxyUrl,
                ];
                if (!isset($activatedProxy['url'])) {
                    csStripeErrorLog($activeProxyId, "Can't find activated proxy!\n");
                    wc_add_notice('We cannot process your payment right now, please try another payment method.[21]', 'error');
                    return [
                        'result' => 'failure',
                        'reload' => true
                    ];
                }

                $shippingName = $order->get_shipping_first_name() . " " . $order->get_shipping_last_name();
                $shippingAddress1 = $order->get_shipping_address_1();
                $shippingAddress2 = $order->get_shipping_address_2();
                $shippingCity = $order->get_shipping_city();
                $shippingCountry = $order->get_shipping_country();
                $shippingPostCode = $order->get_shipping_postcode();
                $shippingState = $order->get_shipping_state();

                // Billing
                $billingName = $order->get_billing_first_name() . " " . $order->get_billing_last_name();
                $billingAddress1 = $order->get_billing_address_1();
                $billingAddress2 = $order->get_billing_address_2();
                $billingCity = $order->get_billing_city();
                $billingCountry = $order->get_billing_country();
                $billingPostCode = $order->get_billing_postcode();
                $billingState = $order->get_billing_state();

                $shippingName = (empty($order->get_shipping_first_name()) && empty($order->get_shipping_last_name())) ? $billingName : $shippingName;
                $shippingAddress1 = empty($shippingAddress1) ? $billingAddress1 : $shippingAddress1;
                $shippingAddress2 = empty($shippingAddress2) ? $billingAddress2 : $shippingAddress2;
                $shippingCity = empty($shippingCity) ? $billingCity : $shippingCity;
                $shippingCountry = empty($shippingCountry) ? $billingCountry : $shippingCountry;
                $shippingPostCode = empty($shippingPostCode) ? $billingPostCode : $shippingPostCode;
                $shippingState = empty($shippingState) ? $billingState : $shippingState;


                // Log processing proxyUrl
                $order->add_order_note(sprintf(__('Starting checkout with Stripe proxy %s', 'lazy'), $activatedProxy['url']), 0, false);

                $items = [];

                $order_items = $order->get_items();
                $productNameArr = [];
                foreach ($order_items as $it) {
                    $product = wc_get_product($it->get_product_id());
                    //$product_name = $product->get_name(); // Get the product name
                    $product_name = $this->getProductTitle($product->get_title(), $order->get_id());
                    $item_quantity = $it->get_quantity(); // Get the item quantity

                    $productNameArr[] = $product_name . ' x ' . $item_quantity;
                }
                $productNames = implode(', ', $productNameArr);

                $response = wp_remote_get($activatedProxy['url'] . '?' . csStripeBuildQuery([
                        'lazy-stripe-hosted-make-session' => uniqid(),
                        "lang" => csGetCurrentLanguage(),
                        'capture_method' => $paymentStripeIntent === OPT_LAZY_STRIPE_INTENT_AUTHORIZE ? 'manual' : 'automatic',
                        'order_id' => $order->get_id(),
                        'customer_ip' => csStripeGetClientIP(),
                        'order_invoice' => $this->invoice_prefix . $order->get_order_number(),
                        'order_invoice_prefix' => $this->invoice_prefix,
                        'order_items' => $items,
                        'statement_descriptor' => $this->get_option('statement_descriptor'),
                        'merchant_site' => get_home_url(),
                        'amount' => $order->get_total(),
                        'product_names' => $productNames,
                        'customer_zipcode' => $billingPostCode,
                        'customer_email' => $order->get_billing_email(),
                        'currency' => $order->get_currency(),
                        'customer_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                        'email' => $order->get_billing_email(),
                        'shipping' => [
                            'name' => $shippingName,
                            'phone' => method_exists($order, 'get_shipping_phone') && $order->get_shipping_phone() ? $order->get_shipping_phone() : $order->get_billing_phone(),
                            'address' => [
                                'city' => $shippingCity,
                                'country' => $shippingCountry,
                                'line1' => $shippingAddress1,
                                'line2' => $shippingAddress2,
                                'postal_code' => $shippingPostCode,
                                'state' => $shippingState,
                            ],
                        ]
                    ]), [
                    'sslverify' => csStripeGetSSLVerifyStatus(),
                    'timeout' => 5 * 60,
                    'headers' => [
                        'Content-Type' => 'application/json',
                    ],
                ]);
                $order->update_meta_data( METAKEY_STRIPE_PROCESSING_ORDER_KEY, WC()->session->get('lazy-stripe-processing-order-key'));
                $order->update_meta_data( METAKEY_STRIPE_PROXY_URL, $activatedProxy['url']);
                $order->update_meta_data( '_shield_payment_method', 'stripe');
                $order->update_meta_data( '_shield_payment_url', $activatedProxy['url']);
                $order->update_meta_data( METAKEY_STRIPE_PROXY_ID, $activatedProxy['id']);
                $order->save_meta_data();
                if (is_wp_error($response)) {
                    csStripeErrorLog($response, 'Stripe request error');
                    wc_add_notice('We cannot process your payment right now, please try another payment method.[22]', 'error');
                    return false;
                }
                $body = wp_remote_retrieve_body($response);
                $body = json_decode($body);
                if (!is_object($body)) {
                    csStripeErrorLog(wp_remote_retrieve_body($response), 'Stripe hosted session returned invalid JSON');
                    $order->update_status('failed');
                    wc_add_notice('We cannot process your payment right now, please try another payment method.[28]', 'error');
                    return false;
                }
                if ($body->status === 'success') {
                    $paymentSession = $body->payment_session;
                    $order->add_order_note(sprintf(__('Start redirect to checkout %s, Session ID: %s', 'lazy'),
                        $activatedProxy['url'],
                        $paymentSession->id
                    ));
                    header("Referrer-Policy: no-referrer");
                    return [
                        'result' => 'success',
                        'redirect' => $paymentSession->url,
                    ];
                } else {
                    csStripeErrorLog($response, 'Stripe request session error');
                    // Empty cart
                    $order->update_status('failed');
                    if ($body->code === 'domain_whitelist_not_allow') {
                        $order->add_order_note(sprintf(__('Stripe charged ERROR by proxy %s, ERROR message: %s', 'lazy'),
                            $activatedProxy['url'],
                            'Domain whitelist is required'
                        ));
                    } else if ($body->code === 'customer_zipcode_not_allow') {
                        csStripeSendMailOrderBlacklisted($order->get_id());
                        $order->add_order_note(sprintf(__('Stripe charged ERROR by proxy %s, ERROR message: %s', 'lazy'),
                            $activatedProxy['url'],
                            "Customer's zipcode is blacklisted"
                        ));
                        wc_add_notice('We cannot process your payment right now, please try another payment method.[23]', 'error');
                        return false;
                    } else if ($body->code === 'customer_email_not_allow') {
                        csStripeSendMailOrderBlacklisted($order->get_id());
                        $order->add_order_note(sprintf(__('Stripe charged ERROR by proxy %s, ERROR message: %s', 'lazy'),
                            $activatedProxy['url'],
                            "Customer's email is blacklisted"
                        ));
                        wc_add_notice('We cannot process your payment right now, please try another payment method.[24]', 'error');
                        return false;
                    } else if ($body->code === 'states_cities_not_allow') {
                        csStripeSendMailOrderBlacklisted($order->get_id());
                        $order->add_order_note(sprintf(__('Stripe charged ERROR by proxy %s, ERROR message: %s', 'lazy'),
                            $activatedProxy['url'],
                            "Customer's State and City is blacklisted"
                        ));
                        wc_add_notice('We cannot process your payment right now, please try another payment method.[25]', 'error');
                        return false;
                    } else if ($body->code === 'order_total_not_allow') {
                        $order->add_order_note(sprintf(__('Stripe charged ERROR by proxy %s, ERROR message: %s', 'lazy'),
                            $activatedProxy['url'],
                            "Order value exceeds Stripe capability"
                        ));
                        wc_add_notice('We cannot process your payment right now, please try another payment method.[26]', 'error');
                        return false;
                    } else if ($body->code === 'customer_ip_blacklisted') {
                        $order->add_order_note(sprintf(__('Stripe charged ERROR by proxy %s, ERROR message: %s', 'lazy'),
                            $activatedProxy['url'],
                            "Smart Shield+ blocked this payment due to high risk exposure."
                        ));
                        wc_add_notice('We cannot process your payment right now, please try another payment method.[27]', 'error');
                        return false;
                    } else {
                        $err = $body->err ?? ($body->message ?? 'Stripe Checkout Session request failed.');
                        $order->add_order_note(sprintf(__('Stripe Create Payment Session ERROR by proxy %s, ERROR message: %s', 'lazy'),
                            $activatedProxy['url'],
                            is_string($err) ? $err : ($err->message ?? wp_json_encode($err))
                        ));
                    }
                    wc_add_notice('We cannot process your payment right now, please try another payment method.[28]', 'error');
                    return false;
                }
            }
            /**
             * Process refund.
             *
             * @param int $order_id Order ID
             * @param float $amount Order amount
             * @param string $reason Refund reason
             *
             * @return boolean True or false based on success, or a WP_Error object.
             */
            public function process_refund($order_id, $amount = null, $reason = '')
            {
                $order = wc_get_order($order_id);
                if (0 == $amount || null == $amount) {
                    return new WP_Error('stripe_refund_error', __('Refund Error: You need to specify a refund amount.', 'lazy-stripe-gateway'));
                }

                try {
                    $result = $this->refund_order($order, $order_id, $amount, "", $reason);
                    $charge = $result['charge_obj'];
                    $order->add_order_note(sprintf(__('Stripe refund completed; transaction ID = %s', 'lazy-stripe-gateway'), csStripeGetTransactionId($order)));
                    updateFeeNetOrderStripe($charge, $order);
                    return true;
                } catch (Exception $e) {
                    csStripeErrorLog($e->getMessage(), 'Stripe process_refund error');
                    return new WP_Error('stripe_refund_error', $e->getMessage());
                }
            }

            private function refund_order($order, $order_id, $amount, $refundType, $reason)
            {
                $proxyUrl = $order->get_meta( MetaKey_Stripe_Proxy_Url);

                // do API call
                $url = $proxyUrl . "?" . csStripeBuildQuery([
                        'lazy-stripe-pe-v2-refund' => uniqid(),
                        'order_id' => $order_id,
                        'transaction_id' => csStripeGetTransactionId($order),
                        'amount' => $this->get_stripe_amount($amount, $order->get_currency()),
                        'reason' => $reason,
                        'merchant_site' => get_home_url(),
                        'currency' => $order->get_currency()
                    ]);

                $request = wp_remote_post($url, [
                    'sslverify' => csStripeGetSSLVerifyStatus(), 
                    'timeout' => 300,
                    'headers' => [
                        'Content-Type' => 'application/json',
                    ],
                    'body' => json_encode([
                        'cs_order_detail' => getCsStripeOrderDetailFromWcOrder($order),
                    ])
                ]);

                $notice = 'We cannot process your payment right now, please try another payment method.[29]';
                if (is_wp_error($request)) {
                    wc_add_notice($notice, 'error');
                    $order->add_order_note(sprintf(__('Failed refund by Stripe! Debug proxy %s', 'lazy-stripe-gateway'), $url));
                    throw new Exception($notice);
                }

                $body = wp_remote_retrieve_body($request);
                $result = json_decode($body);

                if (isset($result->refund_obj) && isset($result->refund_obj->status) && $result->refund_obj->status == "succeeded") {
                    return [
                        'refund_obj' => $result->refund_obj,
                        'charge_obj' => $result->charge_obj,
                    ];
                } else {
                    $order->add_order_note(sprintf(__('Failed refund by Stripe! Debug proxy %s', 'lazy-stripe-gateway'), $url));
                    throw new Exception($notice);
                }
            }

            /**
             * Get Stripe amount to pay
             *
             * @param float $total Amount due.
             * @param string $currency Accepted currency.
             *
             * @return float|int
             */
            public function get_stripe_amount($total, $currency = '')
            {
                if (!$currency) {
                    $currency = get_woocommerce_currency();
                }

                if (in_array(strtolower($currency), $this->no_decimal_currencies())) {
                    return absint($total);
                } else {
                    return absint(wc_format_decimal(((float)$total * 100), wc_get_price_decimals())); // In cents.
                }
            }

            /**
             * List of currencies supported by Stripe that has no decimals.
             *
             * @return array $currencies
             */
            public function no_decimal_currencies()
            {
                return array(
                    'bif', // Burundian Franc
                    'djf', // Djiboutian Franc
                    'jpy', // Japanese Yen
                    'krw', // South Korean Won
                    'pyg', // Paraguayan Guaraní
                    'vnd', // Vietnamese Đồng
                    'xaf', // Central African Cfa Franc
                    'xpf', // Cfp Franc
                    'clp', // Chilean Peso
                    'gnf', // Guinean Franc
                    'kmf', // Comorian Franc
                    'mga', // Malagasy Ariary
                    'rwf', // Rwandan Franc
                    'vuv', // Vanuatu Vatu
                    'xof', // West African Cfa Franc
                );
            }

        }
        
        WC_Lazy_Gateway_Stripe::get_instance();
    endif;
}

register_deactivation_hook(LAZY_STRIPE_PLUGIN_FILE, 'cs_stripe_plugin_deactivation');
add_action('woocommerce_update_option', function ($event) {
    if($event['id'] === 'woocommerce_lazy_stripe_settings') {
        wp_clear_scheduled_hook( 'lazy_gateway_stripe_cron_auto_sync' );
    }
});

function cs_stripe_plugin_deactivation()
{
    wp_clear_scheduled_hook( 'lazy_gateway_stripe_cron_auto_sync' );
    wp_clear_scheduled_hook('lazy_gateway_stripe_daily');
    wp_clear_scheduled_hook('lazy_gateway_stripe_rotation');
}

function updateFeeNetOrderStripe($charge, $order)
{
    if (isset($charge->balance_transaction) && is_object($charge->balance_transaction)) {
        $display_order_currency = LAZY_STRIPE_FEE_DISPLAY_ORDER_CURRENCY;
        $balance_transaction = $charge->balance_transaction;
        $exchange_rate = $balance_transaction->exchange_rate === null ? 1 : $balance_transaction->exchange_rate;
        $amount_refunded = $display_order_currency ? $charge->amount_refunded : $charge->amount_refunded * $exchange_rate;
        $net = $display_order_currency ? $balance_transaction->net / $exchange_rate : $balance_transaction->net;
        $net = $net - $amount_refunded;
        $fee = $display_order_currency ? $balance_transaction->fee / $exchange_rate : $balance_transaction->fee;
        $currency = $display_order_currency ? $order->get_currency() : strtoupper($balance_transaction->currency);
        $payment_balance = [];
        $payment_balance['currency'] = $currency;
        $payment_balance['fee'] = $fee;
        $payment_balance['net'] = $net;
        if (count($charge->refunds->data) > 0) {
            foreach ($charge->refunds->data as $refund) {
                if (is_object($refund->balance_transaction)) {
                    $balance_transaction = $refund->balance_transaction;
                    $exchange_rate = $balance_transaction->exchange_rate === null ? 1 : $balance_transaction->exchange_rate;
                    $fee = $display_order_currency ? $balance_transaction->fee / $exchange_rate : $balance_transaction->fee;
                    $payment_balance['net'] = $payment_balance['net'] - $fee;
                    $payment_balance['fee'] = $payment_balance['fee'] + $fee;
                }
            }
        }
        $payment_balance['fee'] = wc_format_decimal($payment_balance['fee'] / 100, 4);
        $payment_balance['net'] = wc_format_decimal($payment_balance['net'] / 100, 4);
        $order->update_meta_data( METAKEY_CS_STRIPE_FEE, $payment_balance['fee']);
        $order->update_meta_data( METAKEY_CS_STRIPE_PAYOUT, $payment_balance['net']);
        $order->update_meta_data( METAKEY_CS_STRIPE_CURRENCY, $payment_balance['currency']);
        $order->save_meta_data();
    }
}

function add_stripe_loader_ui()
{
    echo '<div id="cs-stripe-loader">
                  <div class="cs-stripe-spinnerWithLockIcon cs-stripe-spinner" aria-busy="true">
                      <p>We\'re processing your payment...<br/>Please <b>DO NOT</b> close this page!</p>
                  </div>
            </div>';
}

add_action('wp_head', 'action_stripe_wp_head', 10, 1);

add_action('wc_ajax_cs_add_order_note', 'cs_add_order_note', 10, 1);

function action_stripe_wp_head()
{
    if (is_checkout()) {
        $gateways = WC()->payment_gateways->get_available_payment_gateways();
        if (isset($gateways['lazy_stripe']->enabled) && $gateways['lazy_stripe']->enabled == 'yes') {
            findAndSetNextProxy();
            echo '<link class="cs_stripe_element" rel="preload" href="' . WC()->session->get('lazy-stripe-proxy-active-url') . '?' . csStripeBuildQuery(['lazy-stripe-pe-v2-get-payment-form' => 1]) . '" as="document">';
        }
        if (isset($gateways['lazy_stripe']->enabled) && $gateways['lazy_stripe']->payment_mode == LAZY_STRIPE_PAYMENT_MODE_HOSTED) {
            echo '<meta name="referrer" content="no-referrer">';
        }
    }
}


function findAndSetNextProxy() {
    $isPayForOrder = isset($_GET['pay_for_order']) && get_query_var('order-pay');
    $isEnableEndpointMode = isCsStripeEnableEndpointMode();
    if ($isPayForOrder && $isEnableEndpointMode) {
        $payForOrderObj = wc_get_order(get_query_var('order-pay'));
        if ($payForOrderObj instanceof WC_Order) {
            $proxyUrl = $payForOrderObj->get_meta(METAKEY_STRIPE_PROXY_URL);
            if (!empty($proxyUrl)) {
                $storedCsOrderKey = $payForOrderObj->get_meta(METAKEY_STRIPE_PROCESSING_ORDER_KEY);
                if (!empty($storedCsOrderKey)) {
                    WC()->session->set('lazy-stripe-processing-order-key', $storedCsOrderKey);
                }
            } else {
                $csOrderKey = $payForOrderObj->get_meta(METAKEY_STRIPE_PROCESSING_ORDER_KEY);
                if (empty($csOrderKey)) {
                    $csOrderKey = csStripeGenerateProcessingOrderKey();
                }
                WC()->session->set('lazy-stripe-processing-order-key', $csOrderKey);
                $proxyUrl = csEndpointGetShieldStripeToProcess($csOrderKey, (float)$payForOrderObj->get_total());
                if (!empty($proxyUrl)) {
                    $payForOrderObj->update_meta_data(METAKEY_STRIPE_PROCESSING_ORDER_KEY, $csOrderKey);
                    $payForOrderObj->update_meta_data(METAKEY_STRIPE_PROXY_URL, $proxyUrl);
                    $payForOrderObj->save_meta_data();
                }
            }
            if (!empty($proxyUrl)) {
                csStripeDebugLog(['id' => null, 'url' => $proxyUrl], '$proxyProcessing pay_for_order');
                WC()->session->set('lazy-stripe-proxy-active-id', null);
                WC()->session->set('lazy-stripe-proxy-active-url', $proxyUrl);
                return;
            }
        }
    }
    if ($isPayForOrder) {
        $orderIdProcessing = get_query_var('order-pay');
    } else {
        $orderIdProcessing = WC()->session->get('order_awaiting_payment');
    }
    if (WC()->cart) {
        $cartTotal = WC()->cart->get_total(false);
    } else {
        $cartTotal = 0;
    }
    if (!empty($orderIdProcessing) ) {
        $orderProcessing = wc_get_order($orderIdProcessing);
        if ($isEnableEndpointMode) {
            $proxyUrl = $orderProcessing->get_meta(METAKEY_STRIPE_PROXY_URL);
            if (empty($proxyUrl)) {
                $csOrderKey = csStripeGenerateProcessingOrderKey();
                WC()->session->set('lazy-stripe-processing-order-key', $csOrderKey);
                $proxyUrl = csEndpointGetShieldStripeToProcess($csOrderKey, $cartTotal);
            }
            $proxyProcessing = ['id' => null, 'url' => $proxyUrl];
        } else {
            if ($orderProcessing instanceof WC_Order) {
                $proxyProcessingId = $orderProcessing->get_meta(METAKEY_STRIPE_PROXY_ID);
                $proxyProcessing = findActivatedProxyDataByIdStripe(get_option(Opt_Lazy_Stripe_Proxies, []), $proxyProcessingId);
            }
        }
        if (isset($proxyProcessing)) {
            csStripeDebugLog($proxyProcessing, '$proxyProcessing');
            WC()->session->set('lazy-stripe-proxy-active-id', $proxyProcessing['id']);
            WC()->session->set('lazy-stripe-proxy-active-url', $proxyProcessing['url']);
            return;
        }
    }
    if ($isEnableEndpointMode) {
        $csOrderKey = csStripeGenerateProcessingOrderKey();
        WC()->session->set('lazy-stripe-processing-order-key', $csOrderKey);
        $nextProxy = ['id' => null, 'url' => csEndpointGetShieldStripeToProcess($csOrderKey, $cartTotal)];
    } else {
        if (isEnabledAmountRotationStripe()) {
            $nextProxy = getNextProxyAmountRotationStripe(WC()->cart->get_total(false));
        } else {
            $nextProxy = get_option(Opt_Lazy_Stripe_Activated_Proxy, null);
        }
    }
    
    if (empty($nextProxy)) {
        WC()->session->set('lazy-stripe-proxy-active-id', null);
        WC()->session->set('lazy-stripe-proxy-active-url', null);
        return;
    }
    WC()->session->set('lazy-stripe-proxy-active-id', $nextProxy['id']);
    WC()->session->set('lazy-stripe-proxy-active-url', $nextProxy['url']);
}

function cs_add_order_note()
{
    if (!wp_verify_nonce($_POST['security'], 'cs_add_order_note') ||
        empty($_POST['order_id']) || empty($_POST['note'])) {
        die ('Wrong nonce!');
    }
    $order = wc_get_order($_POST['order_id']);
    $response = wp_remote_get(WC()->session->get('lazy-stripe-proxy-active-url') . '?' . csStripeBuildQuery([
            'lazy-stripe-pe-v2-get-payment-intent' => uniqid(),
            'payment_intent_id' => csStripeGetTransactionId($order),
            'merchant_site' => get_home_url(),
        ]), [
        'sslverify' => csStripeGetSSLVerifyStatus(),
        'timeout' => 5 * 60,
    ]);
    $decideUpdateOrderFail = true;
    if (is_wp_error($response)) {
        csStripeErrorLog($response, 'Stripe get payment intent error');
    } else {
        $body = wp_remote_retrieve_body($response);
        $body = json_decode($body);
        if (isset($body->payment_intent->status) && in_array($body->payment_intent->status, ['requires_capture', 'succeeded'])) {
            $decideUpdateOrderFail = false;
        }
    }
    if ($decideUpdateOrderFail) {
        csStripeErrorLog($response, 'Stripe add note error: ' . $_POST['note']);
        $order->add_order_note($_POST['note']);
        $order->update_status('failed');
        $activeProxyId = WC()->session->get('lazy-stripe-proxy-active-id');
        $activatedProxy = findActivatedProxyDataByIdStripe(get_option(Opt_Lazy_Stripe_Proxies, []), $activeProxyId);
        wp_remote_post($activatedProxy['url'] . '?' . csStripeBuildQuery([
            'lazy-stripe-pe-v2-add-order-detail' => uniqid(),
            'transaction_id' => csStripeGetTransactionId($order),
            'transaction_status' => 'failed',
            'notes' => $_POST['note'],
        ]), [
            'sslverify' => csStripeGetSSLVerifyStatus(), 
            'timeout' => 300,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'cs_order_detail' => getCsStripeOrderDetailFromWcOrder($order),
            ])
        ]);    
    }
//    $order->save();
    echo json_encode([
        'success' => true
    ]);
    wp_die();
}

function lazy_stripe_generate_input_order()
{
    $order = wc_get_order(get_query_var('order-pay'));
    $billingFirstName = $order->get_billing_first_name();
    $billingLastName = $order->get_billing_last_name();
    $billingAddress1 = $order->get_billing_address_1();
    $billingAddress2 = $order->get_billing_address_2();
    $billingCity = $order->get_billing_city();
    $billingCountry = $order->get_billing_country();
    $billingPostCode = $order->get_billing_postcode();
    $billingState = $order->get_billing_state();
    ?>
    <input id="billing_first_name" value="<?= $billingFirstName ?>" style="display: none"/>
    <input id="billing_last_name" value="<?= $billingLastName ?>" style="display: none"/>
    <input id="billing_address_1" value="<?= $billingAddress1 ?>" style="display: none"/>
    <input id="billing_address_2" value="<?= $billingAddress2 ?>" style="display: none"/>
    <input id="billing_city" value="<?= $billingCity ?>" style="display: none"/>
    <input id="billing_country" value="<?= $billingCountry ?>" style="display: none"/>
    <input id="billing_postcode" value="<?= $billingPostCode ?>" style="display: none"/>
    <input id="billing_state" value="<?= $billingState ?>" style="display: none"/>
    <div id="lazy_stripe_pay_for_order_page"></div>
    <?php
}

function stripeGetChargeStatusByLoop($nextProxyUrl, $nextProxyId) {
    $loopRetry = 0;
    while (true) {
        $loopRetry++;
        $loopAccountStatus = stripeCheckChargeStatus($nextProxyUrl, $nextProxyId);
        if ($loopAccountStatus === 'active') {
            return true;
        } else if ($loopAccountStatus === CONST_CS_STRIPE_GET_CHARGE_STATUS_DEACTIVE) {
            csStripeErrorLog([$nextProxyUrl,$nextProxyId], 'Proxy move to unused because check charge status deactive [2]!');
            stripeMoveToUnusedProxyIds([WC()->session->get('lazy-stripe-proxy-active-id')]);
            csStripeSendMailShieldDie(WC()->session->get('lazy-stripe-proxy-active-url'));
            findAndSetNextProxy();
        } else if($loopRetry >= 3) {
            findAndSetNextProxy();
            return false;
        }
    }
    return false;
}

function stripeCheckChargeStatus ($nextProxyUrl, $nextProxyId) {
    $response = wp_remote_get($nextProxyUrl . '?' . csStripeBuildQuery([
            'lazy-stripe-pe-v2-get-account-charge-status' => uniqid(),
        ]), [
        'sslverify' => csStripeGetSSLVerifyStatus(),
        'timeout' => 20 * 60,
    ]);

    if (is_wp_error($response)) {
        csStripeErrorLog([$nextProxyUrl,$nextProxyId,$response], 'stripeCheckChargeStatus API check charge fail [2]!');
        return 'unknown';
    } else {
        $bodyResponse = wp_remote_retrieve_body($response);
        $body = json_decode($bodyResponse);
        if ($body->status === 'deactive') {
            csStripeErrorLog([$nextProxyUrl,$nextProxyId,$bodyResponse], 'stripeCheckChargeStatus return status deactive [2]!');
            return 'deactive';
        } else if ($body->status === 'active') {
            return 'active';
        } else {
            csStripeErrorLog([$nextProxyUrl,$nextProxyId,$bodyResponse], 'stripeCheckChargeStatus return status unknown [2]!');
            return 'unknown';
        }
    }
}

function stripeGetShieldUrl()
{
    $loopCheckShield = 0;
    $hasShield = false;
    if (empty(WC()->session->get('lazy-stripe-proxy-active-id'))) {
        findAndSetNextProxy();
    }
    $firstProxyId = WC()->session->get('lazy-stripe-proxy-active-id');
    while (true) {
        $nextProxyUrl = WC()->session->get('lazy-stripe-proxy-active-url');
        $nextProxyId = WC()->session->get('lazy-stripe-proxy-active-id');
        if (!$nextProxyUrl || ($loopCheckShield > 0 && $firstProxyId === $nextProxyId)) {
            break;
        }
        $stripeGetChargeStatusFromProxy = getStripeChargeStatusFromProxy($nextProxyId, $nextProxyUrl);
        if ($stripeGetChargeStatusFromProxy === CONST_CS_STRIPE_GET_CHARGE_STATUS_503) {
            csStripeSendMailShieldDie(WC()->session->get('lazy-stripe-proxy-active-url'));
            findAndSetNextProxy();
        } else if ($stripeGetChargeStatusFromProxy === CONST_CS_STRIPE_GET_CHARGE_STATUS_ERROR) {
            $stripeGetChargeStatusByLoop = stripeGetChargeStatusByLoop($nextProxyUrl, $nextProxyId);
            if ($stripeGetChargeStatusByLoop) {
                break;
            }
        } else if ($stripeGetChargeStatusFromProxy === CONST_CS_STRIPE_GET_CHARGE_STATUS_DEACTIVE) {
            stripeMoveToUnusedProxyIds([WC()->session->get('lazy-stripe-proxy-active-id')]);
            csStripeSendMailShieldDie(WC()->session->get('lazy-stripe-proxy-active-url'));
            findAndSetNextProxy();
        } else if ($stripeGetChargeStatusFromProxy === CONST_CS_STRIPE_GET_CHARGE_STATUS_ACTIVE) {
            $hasShield = true;
            break;
        } else {
            $stripeGetChargeStatusByLoop = stripeGetChargeStatusByLoop($nextProxyUrl, $nextProxyId);
            if ($stripeGetChargeStatusByLoop) {
                break;
            }
        }

        $loopCheckShield++;
        if ($loopCheckShield > 100) {
            csStripeErrorLog('$loopCheckShield over 100 tries');
            break;
        }
    }
    if ($hasShield) {
        return [
            'id' => $nextProxyId,
            'url' => $nextProxyUrl,
        ];
    }
    return null;
}

function updateOrderCustomerInfoByChargeObj(WC_Order $order, $paymentIntentObj, $chargeObj)
{
    $order->set_billing_first_name($chargeObj->billing_details->name ?? null);
    $order->set_billing_city($chargeObj->billing_details->address->city ?? null);
    $order->set_billing_country($chargeObj->billing_details->address->country ?? null);
    $order->set_billing_address_1($chargeObj->billing_details->address->line1 ?? null);
    $order->set_billing_address_2($chargeObj->billing_details->address->line2 ?? null);
    $order->set_billing_postcode($chargeObj->billing_details->address->postal_code ?? null);
    $order->set_billing_state($chargeObj->billing_details->address->state ?? null);

    $order->set_shipping_first_name($paymentIntentObj->shipping->name ?? null);
    $order->set_shipping_city($paymentIntentObj->shipping->address->city ?? null);
    $order->set_shipping_country($paymentIntentObj->shipping->address->country ?? null);
    $order->set_shipping_address_1($paymentIntentObj->shipping->address->line1 ?? null);
    $order->set_shipping_address_2($paymentIntentObj->shipping->address->line2 ?? null);
    $order->set_shipping_postcode($paymentIntentObj->shipping->address->postal_code ?? null);
    $order->set_shipping_state($paymentIntentObj->shipping->address->state ?? null);
}
