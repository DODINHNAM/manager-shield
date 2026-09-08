<?php
// php -d extension=pdo_sqlite test/legacy-compat.php
define('ABSPATH', __DIR__);
require dirname(__DIR__) . '/legacy-compat.php';
$opts = [];
$hooks = [];
$events = [];
$cacheDeletes = [];
function get_option($key, $default = false) { global $opts; return array_key_exists($key, $opts) ? $opts[$key] : $default; }
function add_option($key, $value, ...$unused) { global $opts; if (array_key_exists($key, $opts)) return false; $opts[$key] = $value; return true; }
function update_option($key, $value, ...$unused) { global $opts; $opts[$key] = $value; return true; }
function delete_option($key) { global $opts; unset($opts[$key]); }
function add_action($name, $callback, $priority = 10, $args = 1) { global $hooks; $hooks[$name][$priority][] = $callback; }
function add_filter(...$args) { add_action(...$args); }
function apply_filters($name, $value, ...$args) { global $hooks; $callbacks = $hooks[$name] ?? []; ksort($callbacks); foreach ($callbacks as $group) foreach ($group as $fn) $value = $fn($value, ...$args); return $value; }
function do_action($name, ...$args) { global $hooks; $callbacks = $hooks[$name] ?? []; ksort($callbacks); foreach ($callbacks as $group) foreach ($group as $fn) $fn(...$args); }
function wp_cache_delete($id, $group) { global $cacheDeletes; $cacheDeletes[] = [$id, $group]; }
class WC_Cache_Helper { public static $invalidations = 0; public static function invalidate_cache_group($group) { self::$invalidations++; } }
function is_wp_error($value) { return false; }
function wp_get_scheduled_event($key) { global $events; return $events[$key] ?? false; }
function wp_next_scheduled($key, $args = []) { global $events; return isset($events[$key]) ? $events[$key]->timestamp : false; }
function wp_schedule_event($timestamp, $schedule, $key, $args = []) { global $events; $events[$key] = (object) compact('timestamp', 'schedule', 'args'); return true; }
function wp_clear_scheduled_hook($key, $args = []) { global $events; unset($events[$key]); }
class TestSession {
    public $data = [];
    public function get($key) { return $this->data[$key] ?? null; }
    public function set($key, $value) { $this->data[$key] = $value; }
    public function get_session_data() { return $this->data; }
    public function __unset($key) { unset($this->data[$key]); }
}
$wc = (object) ['session' => new TestSession()];
function WC() { global $wc; return $wc; }
function check($condition, $message) { if (!$condition) throw new RuntimeException($message); }

class TestWpdb {
    public $prefix = 'wp_';
    public $postmeta = 'wp_postmeta';
    public $last_error = '';
    public $failAfter = null;
    public $pdo;
    public function __construct() { $this->pdo = new PDO('sqlite::memory:'); $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); }
    public function prepare($sql, ...$args) {
        foreach ($args as $arg) $sql = preg_replace('/%s/', str_replace('$', '\\$', $this->pdo->quote($arg)), $sql, 1);
        return $sql;
    }
    public function esc_like($value) { return addcslashes($value, '_%'); }
    public function get_var($sql) {
        $this->last_error = '';
        if (preg_match("/^SHOW TABLES LIKE '([^']+)'$/", $sql, $m)) {
            $table = stripslashes($m[1]);
            return $this->pdo->query("SELECT name FROM sqlite_master WHERE name = " . $this->pdo->quote($table))->fetchColumn() ?: null;
        }
        return $this->pdo->query($sql)->fetchColumn();
    }
    public function get_col($sql) { $this->last_error = ''; return $this->pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN); }
    public function query($sql) {
        $this->last_error = '';
        if ($this->failAfter !== null && $this->failAfter-- === 0) { $this->last_error = 'Simulated interruption'; return false; }
        return $this->pdo->exec($sql);
    }
}

$opts = [
    'woocommerce_mecom_stripe_settings' => ['intent' => 'OPT_MECOM_STRIPE_INTENT_AUTHORIZE', 'title' => 'mecom in user text must stay'],
    'Opt_Mecom_Stripe_Proxies' => [['id' => 7, 'url' => 'https://shield.example', 'paid_amount' => 35]],
    'OPT_MECOM_STRIPE_CURRENT_ROTATION_VALUE' => 1234,
    'OPT_LAZY_STRIPE_CURRENT_ROTATION_VALUE' => 9999,
    'woocommerce_gateway_order' => ['paypal' => 1, 'mecom_stripe' => 2],
];
lazy_stripe_migrate_options();
check($opts['woocommerce_lazy_stripe_settings']['intent'] === 'OPT_LAZY_STRIPE_INTENT_AUTHORIZE', 'Intent was not migrated');
check($opts['woocommerce_lazy_stripe_settings']['title'] === 'mecom in user text must stay', 'User text was altered');
check($opts['Opt_Lazy_Stripe_Proxies'][0]['paid_amount'] === 35, 'Rotation balance lost');
check($opts['OPT_LAZY_STRIPE_CURRENT_ROTATION_VALUE'] === 9999, 'New config overwritten');
check($opts['woocommerce_gateway_order'] === ['paypal' => 1, 'lazy_stripe' => 2], 'Gateway order mismatch');
check(lazy_stripe_shield_protocol() === 'mecom', 'Existing shields must retain their protocol');
$opts['woocommerce_lazy_stripe_settings']['shield_protocol'] = 'lazy';
check(lazy_stripe_wire_params(['lazy-stripe-pe-v2-refund' => 1, 'amount' => 12]) === ['lazy-stripe-pe-v2-refund' => 1, 'amount' => 12], 'Lazy command mismatch');
$opts['woocommerce_lazy_stripe_settings']['shield_protocol'] = 'legacy';
$wire = lazy_stripe_wire_params(['lazy-stripe-pe-v2-refund' => 1, 'amount' => 12]);
check($wire === ['mecom-stripe-pe-v2-refund' => 1, 'amount' => 12], 'Legacy wire command must be sent once');
$before = $opts;
lazy_stripe_migrate_options();
check($before === $opts, 'Option migration not idempotent');
check(isset($opts['woocommerce_mecom_stripe_settings']), 'Original config removed');
echo "PASS: options, enum conversion, preserved balances, idempotency, one-command protocol\n";

foreach ([false, true] as $hpos) {
    $wpdb = new TestWpdb();
    unset($opts['lazy_stripe_metadata_version']);
    $tables = ['wp_postmeta' => 'post_id'];
    if ($hpos) $tables['wp_wc_orders_meta'] = 'order_id';
    foreach ($tables as $table => $column) {
        $wpdb->pdo->exec("CREATE TABLE $table (meta_id INTEGER PRIMARY KEY AUTOINCREMENT, $column INTEGER, meta_key TEXT, meta_value TEXT)");
        $wpdb->pdo->exec("INSERT INTO $table ($column, meta_key, meta_value) VALUES
            (10, '_mecom_stripe_proxy_url', 'https://old.example'),
            (10, '_lazy_stripe_proxy_url', 'https://new.example'),
            (20, '_mecom_stripe_proxy_id', '42'),
            (20, 'METAKEY_MECOM_STRIPE_CAPTURED', 'false'),
            (20, '_mecom_stripe_sync_tracking_info', '1'),
            (30, '_paypal_meta', 'unchanged')");
    }
    $wpdb->failAfter = 2;
    check(lazy_stripe_migrate_order_metadata() === false, 'Failed query acknowledged as success');
    check(!isset($opts['lazy_stripe_metadata_version'], $opts['lazy_stripe_metadata_lock']), 'Failure not retryable');
    $wpdb->failAfter = null;
    check(lazy_stripe_migrate_order_metadata(), 'Retry failed');
    foreach ($tables as $table => $column) {
        check($wpdb->get_var("SELECT meta_value FROM $table WHERE $column=10 AND meta_key='_lazy_stripe_proxy_url'") === 'https://new.example', 'Existing metadata overwritten');
        check($wpdb->get_var("SELECT meta_value FROM $table WHERE $column=20 AND meta_key='_lazy_stripe_proxy_id'") === '42', 'Order lost proxy ID');
        check($wpdb->get_var("SELECT meta_value FROM $table WHERE $column=20 AND meta_key='METAKEY_LAZY_STRIPE_CAPTURED'") === 'false', 'Capture state lost');
        check($wpdb->get_var("SELECT COUNT(*) FROM $table WHERE meta_key='_mecom_stripe_proxy_url'") === 1, 'Old metadata deleted');
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        unset($opts['lazy_stripe_metadata_version']); // Force retry, including after a previously successful run.
        check(lazy_stripe_migrate_order_metadata(), 'Repeated migration failed');
        check($wpdb->get_var("SELECT COUNT(*) FROM $table") === $count, 'Retry duplicated metadata');
    }
}
check(WC_Cache_Helper::$invalidations > 0 && count($cacheDeletes) > 0, 'Metadata cache not invalidated');
echo "PASS: CPT and HPOS SQL copy, preserve new values, retry interruption, no duplicates, cache invalidation\n";

$normalized = lazy_stripe_normalize_request([
    'mecom_stripe_return_result' => 1, 'mecom-stripe-payment-method-id' => 'old',
    'lazy-stripe-payment-method-id' => 'new', 'payment_method' => 'mecom_stripe',
    'page' => 'mecom-gateway-stripe', 'email' => 'mecom@example.test',
]);
check($normalized['lazy_stripe_return_result'] === 1 && $normalized['payment_method'] === 'lazy_stripe', 'Legacy request not accepted');
check($normalized['lazy-stripe-payment-method-id'] === 'new', 'New request overwritten');
check($normalized['email'] === 'mecom@example.test', 'Request payload altered');
$wc->session->data = ['mecom-stripe-proxy-active-id' => 5, 'mecom-stripe-proxy-active-url' => 'old',
    'lazy-stripe-proxy-active-url' => null, 'chosen_payment_method' => 'mecom_stripe'];
lazy_stripe_migrate_session();
check($wc->session->get('lazy-stripe-proxy-active-id') === 5, 'Session proxy lost');
check($wc->session->get('lazy-stripe-proxy-active-url') === null, 'Cleared session resurrected');
check(!array_key_exists('mecom-stripe-proxy-active-id', $wc->session->data), 'Old session key not retired');
check($wc->session->get('chosen_payment_method') === 'lazy_stripe', 'Chosen method not migrated');
check(lazy_stripe_legacy_payment_method('mecom_stripe') === 'lazy_stripe', 'Legacy orders do not resolve new gateway');
check(lazy_stripe_legacy_payment_method('lazy_paypal') === 'lazy_paypal', 'PayPal changed');

wp_schedule_event(123456, 'one_minute', 'mecom_gateway_stripe_rotation', [7]);
lazy_stripe_migrate_cron();
check(!isset($events['mecom_gateway_stripe_rotation']), 'Old cron duplicated');
check($events['lazy_gateway_stripe_rotation']->timestamp === 123456 && $events['lazy_gateway_stripe_rotation']->args === [7], 'Cron time or arguments changed');
echo "PASS: old callbacks, session continuation, order gateway lookup, cron migration\n";

$opts = [];
lazy_stripe_migrate_options();
check(lazy_stripe_shield_protocol() === 'lazy', 'New installs must use Lazy');
echo "PASS: fresh installation defaults to Lazy\n";

/* Load the actual gateway with minimal WordPress/WooCommerce stand-ins. */
function is_admin() { return false; }
function register_activation_hook($file, $callback) { $GLOBALS['activationFile'] = $file; }
function register_deactivation_hook($file, $callback) { $GLOBALS['deactivationFile'] = $file; }
function plugin_dir_path($file) { return dirname($file) . '/'; }
function plugin_basename($file) { return basename(dirname($file)) . '/' . basename($file); }
function wp_timezone_string() { return 'UTC'; }
function __($text, ...$unused) { return $text; }
function esc_html__($text, ...$unused) { return $text; }
function get_woocommerce_currency() { return 'USD'; }
function admin_url($path = '') { return 'https://shop.example/wp-admin/' . $path; }
#[AllowDynamicProperties]
class WC_Payment_Gateway {
    public function get_option($key, $default = '') {
        $settings = get_option('woocommerce_' . $this->id . '_settings', []);
        return $settings[$key] ?? ($this->form_fields[$key]['default'] ?? $default);
    }
    public function init_settings() {}
    public function process_admin_options() {}
}
$opts['active_plugins'] = ['woocommerce/woocommerce.php'];
$opts['woocommerce_lazy_stripe_settings'] = ['enabled' => 'yes', 'payment_mode' => 'embedded', 'intent' => 'OPT_LAZY_STRIPE_INTENT_AUTHORIZE'];
$entry = in_array('--canonical', $argv, true) ? 'm-lazy-gateway-stripe.php' : 'm-ecom-gateway-stripe.php';
require_once dirname(__DIR__) . '/' . $entry;
require_once dirname(__DIR__) . '/m-ecom-gateway-stripe.php';
require_once dirname(__DIR__) . '/m-lazy-gateway-stripe.php';
do_action('plugins_loaded');
check(basename($GLOBALS['activationFile']) === $entry && basename($GLOBALS['deactivationFile']) === $entry, 'Activation/deactivation hooks use wrong entry point');
$gateways = apply_filters('woocommerce_payment_gateways', []);
check(count(array_filter($gateways, static fn($class) => $class === 'WC_Lazy_Gateway_Stripe')) === 1, 'Gateway registered twice');
check(WC_Lazy_Gateway_Stripe::get_instance()->id === 'lazy_stripe', 'Gateway ID not renamed');
check(WC_MEcom_Gateway_Stripe::get_instance() === WC_Lazy_Gateway_Stripe::get_instance(), 'Legacy class does not share new singleton');
check(isset($hooks['woocommerce_order_action_lazy_stripe_capture_authorization_order']), 'New capture action missing');
check(isset($hooks['woocommerce_order_action_mecom_stripe_capture_authorization_order']), 'Old capture action missing');
check(isset($hooks['woocommerce_update_options_payment_gateways_lazy_stripe']), 'Settings save hook missing');
check(strpos(csStripeBuildQuery(['lazy-stripe-pe-v2-make-payment' => 1]), 'lazy-stripe-pe-v2-make-payment=1') === 0, 'Canonical query builder broken');
$opts['woocommerce_lazy_stripe_settings']['shield_protocol'] = 'legacy';
check(strpos(csStripeBuildQuery(['lazy-stripe-pe-v2-capture-payment' => 1]), 'mecom-stripe-pe-v2-capture-payment=1') === 0, 'Legacy query builder broken');
echo "PASS: real plugin boot via $entry, singleton, gateway registration, hooks and wire query builder\n";
