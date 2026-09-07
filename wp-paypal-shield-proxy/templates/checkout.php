<?php
/**
 * Checkout template for PayPal integration.
 */

defined( 'ABSPATH' ) || exit;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>PayPal Checkout</title>
    <?php
    // Path to the JS file for filemtime cache-busting
    $js_file = dirname( __DIR__ ) . '/assets/css/paypal-credit-payment-form.css';
    $ver = file_exists( $js_file ) ? filemtime( $js_file ) : time();

    // Build URL relative to plugin root (use plugin main file as reference)
    $plugin_main_file = dirname( __DIR__ ) . '/wp-mecom-paypal-proxy.php';
    $css_url = esc_url( plugins_url( 'assets/css/paypal-credit-payment-form.css', $plugin_main_file ) ) . '?v=' . $ver;
    ?>
    <link rel="stylesheet" data-build="<?php echo esc_attr( $ver ); ?>" href="<?php echo $css_url; ?>" />
</head>
<body style=" margin: 0px; ">
    <?php
    $webshield_full_config = get_webshield_config();
    $paypal_config = ( ! is_wp_error( $webshield_full_config ) && isset( $webshield_full_config['paypal_config'] ) ) ? $webshield_full_config['paypal_config'] : null;

    $paypal_client_id = '';
    $paypal_environment = 'sandbox'; // Default to sandbox
    if ( $paypal_config ) {
        $paypal_client_id = $paypal_config['client_id'];
        $paypal_environment = $paypal_config['environment'];
    }
    // Determine PayPal SDK base URL
    $paypal_sdk_base_url = ($paypal_environment === 'live') ? 'https://www.paypal.com/sdk/js' : 'https://www.sandbox.paypal.com/sdk/js';

    // Prepare data for JavaScript
    $mecom_proxy_site_url = esc_url( home_url('/') );
    ?>
    <script>
        window.mecomProxySite = "<?php echo esc_url( rtrim(home_url('/'), '/') ); ?>";
        window.mecomDomainWhiteList = undefined;
        window.mecomZipcodeGlobalBlacklist = undefined;
        window.mecomZipcodeLocalBlacklist = undefined;
        window.mecomEmailGlobalBlacklist = undefined;
        window.mecomEmailLocalBlacklist = undefined;
        window.mecomGlobalStatesBlacklist = undefined;
        window.mecomGlobalCitiesStatesBlacklist = undefined;
        window.mecomLocalStatesBlacklist = ["d0cf1ef21f0ce65584e2453a3fb427f6591adca8","53c1d6afa31aaad85a6930481133ca5da8e088ce"];
        window.mecomLocalCitiesStatesBlacklist = undefined;
        window.csDisablePaypalButton = false;
    </script>
    <div id="paypal-button-container"></div>

    <script src="<?php echo esc_url( $paypal_sdk_base_url ); ?>?client-id=<?php echo esc_attr( $paypal_client_id ); ?>&currency=USD"></script>
    <?php
    // Path to the JS file for filemtime cache-busting
    $js_file = dirname( __DIR__ ) . '/assets/js/paypal-credit-payment-form.js';
    $ver = file_exists( $js_file ) ? filemtime( $js_file ) : time();

    // Build URL relative to plugin root (use plugin main file as reference)
    $plugin_main_file = dirname( __DIR__ ) . '/wp-mecom-paypal-proxy.php';
    $js_url = esc_url( plugins_url( 'assets/js/paypal-credit-payment-form.js', $plugin_main_file ) ) . '?v=' . $ver;
    ?>
    <script id="mecom-paypal-js" data-build="<?php echo esc_attr( $ver ); ?>" src="<?php echo $js_url; ?>"></script>
</body>
</html>