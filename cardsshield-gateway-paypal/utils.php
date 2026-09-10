<?php

const OPT_LAZY_PAYPAL_VERSION                     = '2.10.11';
const OPT_LAZY_PAYPAL_PROXIES                     = 'OPT_LAZY_PAYPAL_PROXIES';
const OPT_LAZY_PAYPAL_UNUSED_PROXIES              = 'OPT_LAZY_PAYPAL_UNUSED_PROXIES';
const OPT_LAZY_PAYPAL_ACTIVATED_PROXY             = 'OPT_LAZY_PAYPAL_ACTIVATED_PROXY';
const OPT_LAZY_PAYPAL_CURRENT_ROTATION_VALUE      = 'OPT_LAZY_PAYPAL_CURRENT_ROTATION_VALUE';
const OPT_LAZY_PAYPAL_ROTATION_METHOD             = 'OPT_LAZY_PAYPAL_ROTATION_METHOD';
const OPT_LAZY_PAYPAL_CONNECTION_MODE            = 'OPT_LAZY_PAYPAL_CONNECTION_MODE';
const OPT_LAZY_PAYPAL_LAST_TIME_RESET_PAID_AMOUNT = 'OPT_LAZY_PAYPAL_LAST_TIME_RESET_PAID_AMOUNT';

const METAKEY_PAYPAL_PROXY_URL          = '_lazy_paypal_proxy_url';
const METAKEY_PAYPAL_PROXY_ID          = '_lazy_paypal_proxy_id';
const METAKEY_PAYPAL_SYNC_TRACKING_INFO = '_lazy_paypal_sync_tracking_info';
const METAKEY_PAYPAL_PROCESSING_ORDER_KEY = '_METAKEY_PAYPAL_PROCESSING_ORDER_KEY';

const METAKEY_CS_PAYPAL_FEE    = '_cs_paypal_fee';
const METAKEY_CS_PAYPAL_PAYOUT = '_cs_paypal_payout';
const METAKEY_CS_PAYPAL_CURRENCY  = '_cs_paypal_currency';
const METAKEY_CS_PAYPAL_INTENT  = '_cs_paypal_intent';
const METAKEY_CS_PAYPAL_CAPTURED  = '_cs_paypal_captured';

const OPT_CS_PAYPAL_NOT_SYNCED = 1;
const OPT_CS_PAYPAL_SYNCED     = 2;
const OPT_CS_PAYPAL_SYNC_ERROR = 99;

const OPT_CS_PAYPAL_BY_TIME   = "by_time";
const OPT_CS_PAYPAL_BY_AMOUNT = "by_amount";
const OPT_CS_PAYPAL_ENDPOINT_TOKEN = "OPT_CS_PAYPAL_ENDPOINT_TOKEN";
const OPT_CS_PAYPAL_ENDPOINT_SECRET= "OPT_CS_PAYPAL_ENDPOINT_SECRET";

const OPT_CS_PAYPAL_CONNECTION_MODE_SHIELD_DOMAINS   = "shield_domains";
const OPT_CS_PAYPAL_CONNECTION_MODE_ENDPOINT_TOKEN = "endpoint_token";

const OPT_CS_PAYPAL_AUTHORIZE = "AUTHORIZE";
const OPT_CS_PAYPAL_CAPTURE   = "CAPTURE";

const OPT_CS_PAYPAL_SETTING_STANDARD = "PAYPAL_STANDARD";
const OPT_CS_PAYPAL_SETTING_CHECKOUT   = "PAYPAL_CHECKOUT"; 

/**
 * Normalize a shield address entered as either a hostname or a full URL.
 */
function csNormalizeShieldUrl($value) {
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    if (!preg_match('#^https?://#i', $value)) {
        $value = 'https://' . $value;
    }

    $parts = parse_url($value);
    if (empty($parts['host']) || !empty($parts['user']) || !empty($parts['pass'])) {
        return null;
    }

    $scheme = strtolower($parts['scheme'] ?? 'https');
    if (!in_array($scheme, ['http', 'https'], true)) {
        return null;
    }

    $url = $scheme . '://' . strtolower($parts['host']);
    if (!empty($parts['port'])) {
        $url .= ':' . (int) $parts['port'];
    }
    if (!empty($parts['path']) && $parts['path'] !== '/') {
        $url .= '/' . ltrim($parts['path'], '/');
    }

    return rtrim($url, '/');
}

/**
 * Copy settings from the previous plugin identifiers once after the rename.
 */
function cs_lazy_migrate_legacy_options() {
    static $migrated = false;

    if ( $migrated ) {
        return;
    }

    $migrated = true;
    $legacy_options = [
        OPT_LAZY_PAYPAL_PROXIES                => 'OPT_M' . 'ECOM_PAYPAL_PROXIES',
        OPT_LAZY_PAYPAL_UNUSED_PROXIES         => 'OPT_M' . 'ECOM_PAYPAL_UNUSED_PROXIES',
        OPT_LAZY_PAYPAL_ACTIVATED_PROXY        => 'OPT_M' . 'ECOM_PAYPAL_ACTIVATED_PROXY',
        OPT_LAZY_PAYPAL_CURRENT_ROTATION_VALUE  => 'OPT_M' . 'ECOM_PAYPAL_CURRENT_ROTATION_VALUE',
        OPT_LAZY_PAYPAL_ROTATION_METHOD         => 'OPT_M' . 'ECOM_PAYPAL_ROTATION_METHOD',
        OPT_LAZY_PAYPAL_CONNECTION_MODE         => 'OPT_M' . 'ECOM_PAYPAL_CONNECTION_MODE',
        OPT_LAZY_PAYPAL_LAST_TIME_RESET_PAID_AMOUNT => 'OPT_M' . 'ECOM_PAYPAL_LAST_TIME_RESET_PAID_AMOUNT',
    ];

    foreach ( $legacy_options as $new_key => $legacy_key ) {
        if ( false === get_option( $new_key, false ) ) {
            $legacy_value = get_option( $legacy_key, false );
            if ( false !== $legacy_value ) {
                update_option( $new_key, $legacy_value, true );
            }
        }
    }

    $new_wc_settings    = 'woocommerce_lazy_paypal_settings';
    $legacy_wc_settings = 'woocommerce_m' . 'ecom_paypal_settings';
    if ( false === get_option( $new_wc_settings, false ) ) {
        $legacy_value = get_option( $legacy_wc_settings, false );
        if ( false !== $legacy_value ) {
            update_option( $new_wc_settings, $legacy_value, true );
        }
    }
}

cs_lazy_migrate_legacy_options();

const OPT_CS_TRACKING_SYNC_PLUGIN_ADVANCED_SHIPMENT_TRACKING='opt_cs_tracking_sync_plugin_advanced_shipment_tracking';
const OPT_CS_TRACKING_SYNC_PLUGIN_ORDERS_TRACKING='opt_cs_tracking_sync_plugin_orders_tracking';
const OPT_CS_TRACKING_SYNC_PLUGIN_DIANXIAOMI='opt_cs_tracking_sync_plugin_dianxiaomi';
const OPT_CS_PAYMENT_GATEWAY_TYPE_PAYPAL = 1;
const OPT_CS_SEND_REQUEST_USER_AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36';

function logRotation($rotationMethod, $proxy, $type)
{
    $lazy_log_dir = wp_get_upload_dir()["basedir"] . '/lazy';
    if (!is_dir($lazy_log_dir)) {
        mkdir($lazy_log_dir, 0777, true);
    }
    $logFilePath = $lazy_log_dir . '/' . getLogFileName($rotationMethod);
    $currentDateTime             = date('Y-m-d H:i:s');
    $rotationValue               = $rotationMethod === OPT_CS_PAYPAL_BY_TIME ? $proxy['timestamp'] : ( $proxy['paid_amount'] . '/' . $proxy['amount']);
    $content                     = "{$currentDateTime} - {$proxy['url']} , {$rotationValue} - {$type}\n";

    $fp = fopen($logFilePath, 'a');
    fwrite($fp, $content);
    fclose($fp);
}

function loadLogs($rotationMethod)
{
    $lazyLogDir = wp_get_upload_dir()["basedir"] . '/lazy';
    $logFilePath = $lazyLogDir . '/' . getLogFileName($rotationMethod);
    if (!file_exists($logFilePath)) {
        return [];
    }
    $logArr = explode("\n", file_get_contents($logFilePath));
    // reverse order
    $logArr = array_reverse($logArr);
    $result = [];
    foreach($logArr as $log) {
        if (empty($log)) continue;
        list($time, $proxy, $trigger) = explode(" - ", $log);
        list($proxyUrl, $rotationValue) = explode(" , ", $proxy);
        $result[] = [
            'time' => $time,
            'proxyUrl' => $proxyUrl,
            'rotationValue' => $rotationValue,
            'trigger' => $trigger
        ];
    }
    return $result;

}

function clearLogs($rotationMethod)
{
    $lazyLogDir = wp_get_upload_dir()["basedir"] . '/lazy';
    if (!is_dir($lazyLogDir)) {
        mkdir($lazyLogDir, 0777, true);
    }

    $logFilePath = $lazyLogDir . '/' . getLogFileName($rotationMethod);
    $fp = fopen($logFilePath, 'w');
    fwrite($fp, "");
    fclose($fp);
    return empty(file_get_contents($logFilePath));
}

function getLogFileName($rotationMethod) {
    if ( $rotationMethod === OPT_CS_PAYPAL_BY_TIME) {
        return 'lazy_paypal_rotation_time_log.txt';
    } else if ( $rotationMethod === OPT_CS_PAYPAL_BY_AMOUNT) {
        return 'lazy_paypal_rotation_amount_log.txt';
    }
}

function isEnabledAmountRotation() {
    return OPT_CS_PAYPAL_BY_AMOUNT === get_option(OPT_LAZY_PAYPAL_ROTATION_METHOD, OPT_CS_PAYPAL_BY_TIME);
}

function resetPaidAmountIfNeed() {
    $lastTimeReset = get_option(OPT_LAZY_PAYPAL_LAST_TIME_RESET_PAID_AMOUNT, null);
    $proxies = get_option(OPT_LAZY_PAYPAL_PROXIES, []);
    if (empty($proxies)) {
        return [];
    }
    // Reset
    if (empty($lastTimeReset) || date('Y-m-d') > $lastTimeReset) {
        return resetPaidAmount($proxies);
    }
    return $proxies;
}

function resetPaidAmount($proxies = null)
{
    if (empty($proxies)) {
        $proxies = get_option( OPT_LAZY_PAYPAL_PROXIES, [] );
    }
    if (empty($proxies)) return [];
    // Reset
    $newProxies = array_map(function ($proxy) {
        $proxy['paid_amount'] = 0;
        return $proxy;
    }, $proxies);
    $unusedProxies = get_option(OPT_LAZY_PAYPAL_UNUSED_PROXIES, []);
    if (empty($unusedProxies)) {
        $newUnusedProxies = [];
    } else {
        $newUnusedProxies = array_map(function ($unusedProxy) {
            $unusedProxy['paid_amount'] = 0;
            return $unusedProxy;
        }, $unusedProxies);
    }
    update_option(OPT_LAZY_PAYPAL_PROXIES, $newProxies, true);
    update_option(OPT_LAZY_PAYPAL_UNUSED_PROXIES, $newUnusedProxies, true);
    update_option(OPT_LAZY_PAYPAL_LAST_TIME_RESET_PAID_AMOUNT, date('Y-m-d'), true);
    return $newProxies;
}

function findActivatedProxyDataById($proxies, $activatedProxyId) {
    foreach ($proxies as $proxy) {
        if ($proxy['id'] == $activatedProxyId) {
            return $proxy;
        }
    }
    return null;
}

function getNextProxy($currentProxyId) {
    $proxies = get_option( OPT_LAZY_PAYPAL_PROXIES, [] );
    if (empty($proxies)) return null;
    if ($currentProxyId == end($proxies)['id']) {
        return $proxies[0];
    }

    foreach ($proxies as $key => $proxy) {
        if ($proxy['id'] == $currentProxyId) {
            return $proxies[$key + 1];
        }
    }
    return null;
}

function getNextProxyAmountRotation($activatedProxy, $orderTotal) {
    $proxies = resetPaidAmountIfNeed();
    if ( empty( $proxies ) ) {
        return null;
    }
    $isCurrentProxyMatched = false;
    foreach ( $proxies as $proxy ) {
        if ( $activatedProxy['id'] == $proxy['id'] && !$isCurrentProxyMatched) {
            $isCurrentProxyMatched = true;
            continue;
        }
        if ($isCurrentProxyMatched && isPayableProxy( $proxy, $orderTotal ) ) {
            return $proxy;
        }
    }
    foreach ( $proxies as $proxy ) {
        if ( isPayableProxy( $proxy, $orderTotal ) ) {
            return $proxy;
        }
    }

    return null;
}

function performProxyAmountRotation($activatedProxy, $orderTotal) {
    $proxies = resetPaidAmountIfNeed();
    if ( empty( $proxies ) ) {
        return null;
    }
    $isCurrentProxyMatched = false;
    foreach ( $proxies as $proxy ) {
        if ( $activatedProxy['id'] == $proxy['id']  && !$isCurrentProxyMatched) {
            $isCurrentProxyMatched = true;
            continue;
        }
        if ($isCurrentProxyMatched && isPayableProxy( $proxy, $orderTotal ) ) {
            $activatedProxy = $proxy;
            update_option( OPT_LAZY_PAYPAL_ACTIVATED_PROXY, $activatedProxy, true );
            logRotation( OPT_CS_PAYPAL_BY_AMOUNT, $activatedProxy, "Auto" );

            return $activatedProxy;
        }
    }
    foreach ( $proxies as $proxy ) {
        if ( isPayableProxy( $proxy, $orderTotal ) ) {
            $activatedProxy = $proxy;
            update_option( OPT_LAZY_PAYPAL_ACTIVATED_PROXY, $activatedProxy, true );
            logRotation( OPT_CS_PAYPAL_BY_AMOUNT, $activatedProxy, "Auto" );

            return $activatedProxy;
        }
    }

    return null;
}

function performProxyByTimeRotation($activatedProxy) {
    $proxies = get_option( OPT_LAZY_PAYPAL_PROXIES, [] );
    if ( empty( $proxies ) ) {
        return null;
    }
    $isCurrentProxyMatched = false;
    foreach ( $proxies as $proxy ) {
        if ( $activatedProxy['id'] == $proxy['id']  && !$isCurrentProxyMatched) {
            $isCurrentProxyMatched = true;
            continue;
        }
        if ($isCurrentProxyMatched) {
            $activatedProxy = $proxy;
            update_option( OPT_LAZY_PAYPAL_ACTIVATED_PROXY, $activatedProxy, true );
            logRotation( OPT_CS_PAYPAL_BY_TIME, $activatedProxy, "Auto" );

            return $activatedProxy;
        }
    }
    $activatedProxy = $proxies[0];
    update_option( OPT_LAZY_PAYPAL_ACTIVATED_PROXY, $activatedProxy, true );
    logRotation( OPT_CS_PAYPAL_BY_TIME, $activatedProxy, "Auto" );
    return $activatedProxy;
}

function isPayableProxy($proxy, $orderTotal) {
    return doubleval( $proxy['paid_amount'] ) + doubleval( $orderTotal ) < doubleval($proxy['amount']);
}

function hasPayableProxy($orderTotal) {
    $proxies = resetPaidAmountIfNeed();
    if (empty($proxies)) {
        return false;
    }

    foreach ($proxies as $proxy) {
        if(isPayableProxy($proxy, $orderTotal)) {
            return true;
        }
    }
    return false;
}

function updateRotationAmount($proxyId, $orderTotal) {
    $activatedProxy = get_option( OPT_LAZY_PAYPAL_ACTIVATED_PROXY, null );
    if ($activatedProxy['id'] == $proxyId) {
        $activatedProxy['paid_amount'] = doubleval($activatedProxy['paid_amount']) + doubleval( $orderTotal );
        update_option(OPT_LAZY_PAYPAL_ACTIVATED_PROXY, $activatedProxy, true);
    }
    $proxies = get_option(OPT_LAZY_PAYPAL_PROXIES, []);
    foreach ($proxies as $key => $proxy) {
        if ($proxy['id'] === $proxyId) {
            $proxies[$key]['paid_amount'] = doubleval($proxy['paid_amount']) + doubleval( $orderTotal );
            break;
        }
    }
    return update_option(OPT_LAZY_PAYPAL_PROXIES, $proxies, true);
}

function getEnabledPaymentGateways() {
    $gateways        = WC()->payment_gateways->get_available_payment_gateways();
    $enabledGateways = [];
    if ( $gateways ) {
        foreach ( $gateways as $gateway ) {

            if ( $gateway->enabled == 'yes' ) {

                $enabledGateways[] = $gateway;

            }
        }
    }
    return $enabledGateways;
}

function getBrowserFingerprint() {
    $browserData = (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '' ) . '|' . 
                   (isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : '') . '|' .
                   (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : '');
    return sha1($browserData);
}

function csLog($error, $message = '') {
    $logger = wc_get_logger();
    $error = 'PayPal log: ' . csPaypalSafeLogMessage($message);
    if (empty($logger)) {
        return;
    } else {
        $logger->debug( $error, [ 'source' => 'cardsshield-gateway-paypal' ] );
    }

}

function moveToUnusedProxyIds($proxyIds) {
    $proxies        = get_option( OPT_LAZY_PAYPAL_PROXIES, [] );
    if (empty($proxies)) {
        $proxies = [];
    }
    $unusedProxies  = get_option( OPT_LAZY_PAYPAL_UNUSED_PROXIES, [] );
    if (empty($unusedProxies)) {
        $unusedProxies = [];
    }
    foreach ( $proxies as $key => $proxy ) {
        if ( in_array( $proxy['id'], $proxyIds ) ) {
            $unusedProxies[] = $proxy;
            unset( $proxies[ $key ] );
        }
    }
    $isSuccess1 = update_option( OPT_LAZY_PAYPAL_PROXIES, array_values($proxies), true );
    $isSuccess2 = update_option( OPT_LAZY_PAYPAL_UNUSED_PROXIES, $unusedProxies, true );
    $proxies        = get_option( OPT_LAZY_PAYPAL_PROXIES, [] );
    if(isset($proxies[0])) {
        update_option( OPT_LAZY_PAYPAL_ACTIVATED_PROXY, $proxies[0], true );
    }
    return $isSuccess1 && $isSuccess2;
}

function moveToUnusedProxyIdsRestrictAccount($proxyIds) {
    $proxies        = get_option( OPT_LAZY_PAYPAL_PROXIES, [] );
    if (empty($proxies)) {
        $proxies = [];
    }
    $unusedProxies  = get_option( OPT_LAZY_PAYPAL_UNUSED_PROXIES, [] );
    if (empty($unusedProxies)) {
        $unusedProxies = [];
    }
    foreach ( $proxies as $key => $proxy ) {
        if ( in_array( $proxy['id'], $proxyIds ) ) {
            $unusedProxies[] = $proxy;
            unset( $proxies[ $key ] );
        }
    }
    $isSuccess1 = update_option( OPT_LAZY_PAYPAL_PROXIES, array_values($proxies), true );
    $isSuccess2 = update_option( OPT_LAZY_PAYPAL_UNUSED_PROXIES, $unusedProxies, true );
    return $isSuccess1 && $isSuccess2;
}

function countOrderNeedSync() {
    if (get_option('woocommerce_custom_orders_table_enabled') === 'yes') {
        $results = queryOrderNeedSyncPaypalHPOS('count');                    
    } else {
        $results = queryOrderNeedSyncPaypal('count');                    
    }
    return $results[0]['count'] ?? 0;
}

require_once(plugin_dir_path(__FILE__) . 'cs-woocommerce-orders-tracking/includes/admin/paypal.php');
require_once(plugin_dir_path(__FILE__) . 'cs-woocommerce-orders-tracking/includes/data.php');
require_once(plugin_dir_path(__FILE__) . 'cs-advance-shipment-tracking/cs-advance-shipping-tracking-provider.php');

function syncTrackingInfo() {
    $csPayPalGw = WC()->payment_gateways->payment_gateways()['lazy_paypal'];
    $trackingSyncPlugin = $csPayPalGw->get_option('sync_tracking_plugin');
    if (get_option('woocommerce_custom_orders_table_enabled') === 'yes') {
        $orders = queryOrderNeedSyncPaypalHPOS('get');
    } else {
        $orders = queryOrderNeedSyncPaypal('get');
    }
    $hasError = false;
    $errorOrderIdList = [];
    $shippingData = [];

    foreach ($orders as $order) {
        $orderId = $order['order_id'];
        $order = wc_get_order($orderId);
        $orderPostalCode = $order->get_shipping_postcode();
        $trackingNumberArr = [];

        $processedProxyUrl = $order->get_meta( METAKEY_PAYPAL_PROXY_URL );
        if ( empty( $processedProxyUrl ) ) {
            csPaypalErrorLog('Sync error: Empty proxy url, order_id: ' . $orderId);
            $hasError       = true;
            $errorOrderIdList[] = $orderId;
            $order->update_meta_data( METAKEY_PAYPAL_SYNC_TRACKING_INFO, OPT_CS_PAYPAL_SYNC_ERROR );
            $order->save_meta_data();
            continue;
        }
        if ($order->get_status() === 'on-hold') {
            continue;
        }

        if ($trackingSyncPlugin == OPT_CS_TRACKING_SYNC_PLUGIN_ADVANCED_SHIPMENT_TRACKING) {
            $trackingItems = $order->get_meta('_wc_shipment_tracking_items');
            if (empty($trackingItems)) {
                continue;
            }
            foreach ($trackingItems as $trackingItem) {
                if (empty($trackingItem['tracking_number'])) {
                    continue;
                }
                if (in_array($trackingItem['tracking_number'], $trackingNumberArr)) {
                    continue;
                }
                $trackingNumberArr[] = $trackingItem['tracking_number'];
                $ppProvider = CS_ADVANCE_SHIPPING_TRACKING_PROVIDER::get_instance()->getPaypalProvider($trackingItem['tracking_provider']);
                $trackingData = [
                    'order_id' => $orderId,
                    'transaction_id' => csPaypalGetTransactionId($order),
                    'tracking_number' => $trackingItem['tracking_number'],
                    'status' => 'SHIPPED',
                    'carrier' => $ppProvider->paypal_slug,
                ];
                if (isset($ppProvider->paypal_slug) && '' != $ppProvider->paypal_slug) {
                    $trackingData['carrier'] = $ppProvider->paypal_slug;
                    if (isset($ppProvider->provider_url) && '' != $ppProvider->provider_url) {
                        $trackingData['tracking_url'] = str_replace('%number%', $trackingItem['tracking_number'], $ppProvider->provider_url);
                    }
                } else {
                    $trackingData['carrier'] = 'OTHER';
                    $trackingData['carrier_name_other'] = $trackingItem['tracking_provider'];
                }			
                $shippingData[$processedProxyUrl][] = $trackingData;
            }
        } else if ($trackingSyncPlugin == OPT_CS_TRACKING_SYNC_PLUGIN_ORDERS_TRACKING) {
            if($trackingDataByOrder = $order->get_meta('_wot_tracking_number')) {
                $carrierSlug = $order->get_meta('_wot_tracking_carrier');
                $carrierPaypalName = CS_VI_WOOCOMMERCE_ORDERS_TRACKING_ADMIN_PAYPAL::get_paypal_carrier_by_slug($carrierSlug);
                $trackingData = [
                    'order_id' => $orderId,
                    'transaction_id' => csPaypalGetTransactionId($order),
                    'tracking_number' => $trackingDataByOrder,
                    'status' => 'SHIPPED',
                    'carrier' => $carrierPaypalName,
                ];
                $trackingUrl = null;
                try {
                    $itemTrackingData = CS_VI_WOOCOMMERCE_ORDERS_TRACKING_DATA::get_instance()->get_item_tracking_number($orderId);
                    $trackingUrl = $itemTrackingData['tracking_url_show'] ?? null;
                    csPaypalDebugLog($itemTrackingData, 'Sync get tracking URL  $itemTrackingData');
                } catch (\Exception $e) {
                    csPaypalDebugLog($e->getMessage(), 'Sync get tracking URL exception!');
                }
                if (!empty($trackingUrl)) {
                    $trackingData['tracking_url'] = $trackingUrl;
                }
                if ('OTHER' === $carrierPaypalName) {
                    $trackingData['carrier_name_other'] = $carrierSlug;
                }
                $shippingData[$processedProxyUrl][] = $trackingData;
            } else {
                foreach ( $order->get_items() as $item_id => $item_value ) {
                    $item_tracking_data    = wc_get_order_item_meta( $item_id, '_vi_wot_order_item_tracking_data', true );
                    if ( empty($item_tracking_data )) {
                        continue;
                    }
                    $item_tracking_data    = json_decode( $item_tracking_data, true );
                    $current_tracking_data = array_pop( $item_tracking_data );
                    if (in_array($current_tracking_data['tracking_number'], $trackingNumberArr)) {
                        continue;
                    }
                    $trackingNumberArr[] = $current_tracking_data['tracking_number'];
                    $carrierName = $current_tracking_data['carrier_name'];
                    $carrierPaypalName = CS_VI_WOOCOMMERCE_ORDERS_TRACKING_ADMIN_PAYPAL::get_carrier($carrierName);
                    $trackingData = [
                        'order_id'           => $orderId,
                        'transaction_id'     => csPaypalGetTransactionId($order),
                        'tracking_number'    => $current_tracking_data['tracking_number'],
                        'status'             => 'SHIPPED',
                        'carrier'            => $carrierPaypalName,
                    ];
                    if ('OTHER' === $carrierPaypalName) {
                        $trackingData['carrier_name_other'] = $carrierName;
                    }
                    $trackingUrl = null;
                    try {
                        $itemTrackingData = CS_VI_WOOCOMMERCE_ORDERS_TRACKING_DATA::get_instance()->get_item_tracking_number($orderId, $item_id);
                        $trackingUrl = $itemTrackingData['tracking_url_show'] ?? null;
                        csPaypalDebugLog($itemTrackingData, 'Sync get tracking URL  $itemTrackingData');
                    } catch (\Exception $e) {
                        csPaypalDebugLog($e->getMessage(), 'Sync get tracking URL exception!');
                    }
                    if (!empty($trackingUrl)) {
                        $trackingData['tracking_url'] = $trackingUrl;
                    }
                    $shippingData[$processedProxyUrl][] = $trackingData;
                }   
            }
        } else if ($trackingSyncPlugin == OPT_CS_TRACKING_SYNC_PLUGIN_DIANXIAOMI) {
            $trackingProvider = $order->get_meta('_dianxiaomi_tracking_provider_name');
            $trackingNumber = $order->get_meta('_dianxiaomi_tracking_number');
            if ( empty( $trackingProvider ) || empty($trackingNumber) ) {
                continue;
            }
            $shippingData[$processedProxyUrl][] = [
                'order_id'           => $orderId,
                'transaction_id'     => csPaypalGetTransactionId($order),
                'tracking_number'    => $trackingNumber,
                'status'             => 'SHIPPED',
                'carrier'            => 'OTHER',
                'carrier_name_other' => $trackingProvider,
            ];
        }
    }
    csPaypalDebugLog($shippingData, 'Sync data $shippingData INFO');
    foreach ($shippingData as $proxyUrl => $shippingDataBatch) {
        if (isset($shippingDataBatch) && count($shippingDataBatch)) {
            $shippingDataParts = array_chunk($shippingDataBatch, 20);
        } else {
            $shippingDataParts = [];
        }
        
        foreach($shippingDataParts as $shippingDataPart) {
            $orderIds = array_unique(array_map(function ($data) {
                return $data['order_id'];
            }, $shippingDataPart));
            
            // Remove order_id field to add PayPal track
            $shippingDataPush = array_map(function ($data) {
                unset($data['order_id']);
                return $data;
            }, $shippingDataPart);
            $requestUrl = $proxyUrl . "?lazy-paypal-sync-tracking=1&" . http_build_query( [
                'data-track' => $shippingDataPush
            ]);
            $response = wp_remote_get($requestUrl, [
                'timeout' => 5 * 60,
            ]);
            
            if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
                $errorOrderIdList = array_unique(array_merge($errorOrderIdList, $orderIds));
                csPaypalErrorLog([$response, $requestUrl, $orderIds], "Sync data error![1]");
                foreach ($orderIds as $orderId) {
                    $subOrder = wc_get_order($orderId);
                    $subOrder->update_meta_data( METAKEY_PAYPAL_SYNC_TRACKING_INFO, OPT_CS_PAYPAL_SYNC_ERROR );
                    $subOrder->save_meta_data();
                }
                $hasError = true;
                continue;
            }
            $data = json_decode( wp_remote_retrieve_body( $response ) );
            if ( ! $data->success ) {
                $errorOrderIdList = array_unique(array_merge($errorOrderIdList, $orderIds));
                csPaypalErrorLog([$response, $requestUrl, $orderIds], "Sync data error![2]");
                foreach ($orderIds as $orderId) {
                    $subOrder = wc_get_order($orderId);
                    $subOrder->update_meta_data( METAKEY_PAYPAL_SYNC_TRACKING_INFO, OPT_CS_PAYPAL_SYNC_ERROR );
                    $subOrder->save_meta_data();
                }
                $hasError = true;
            } else {
                foreach ($orderIds as $orderId) {
                    $subOrder = wc_get_order($orderId);
                    $subOrder->update_meta_data( METAKEY_PAYPAL_SYNC_TRACKING_INFO, OPT_CS_PAYPAL_SYNCED );
                    $subOrder->save_meta_data();
                }
            }
        }
    }
   
    if ($hasError) {
        echo json_encode( [
            'success' => false,
            'error'   => 'Fail synced order_id: ' . implode(', ', array_unique($errorOrderIdList)),
        ] );
    } else {
        echo json_encode( [
            'success' => true,
            'count'   => countOrderNeedSync()
        ] );
    }
}
require_once(plugin_dir_path(__FILE__) . 'vendor/autoload.php');
$bootstrapLazyPaypalModules = require_once(plugin_dir_path(__FILE__) . 'modules/ppcp-api-client/bootstrap.php');
$appContainerLazyPaypalModules = $bootstrapLazyPaypalModules( plugin_dir_path(__FILE__) . 'modules/ppcp-api-client');
    
function csPaypalGetCartInvoiceId(WC_Cart $cart) {
    $settings = get_option('woocommerce_lazy_paypal_settings', []);
    $prefix = sanitize_text_field($settings['invoice_prefix'] ?? 'WC-');
    $prefix = preg_replace('/[^A-Za-z0-9._-]/', '', $prefix);
    $prefix = $prefix !== '' ? $prefix : 'WC-';
    $temporaryId = wp_generate_password(16, false, false);

    return substr($prefix . $temporaryId, 0, 127);
}

function get_purchase_unit_from_cart(WC_Cart $cart) {
    global $appContainerLazyPaypalModules;
    $purchaseUnits = $appContainerLazyPaypalModules->get('api.factory.purchase-unit')->from_wc_cart($cart)->to_array();
    $result = [];
    foreach (['amount', 'items'] as $attrToUse) {
        if (!array_key_exists($attrToUse, $purchaseUnits)) {
            continue;
        }
        $result[$attrToUse] = $purchaseUnits[$attrToUse];
    }
    $result['invoice_id'] = csPaypalGetCartInvoiceId($cart);
    
    return $result;
}

function get_purchase_unit_from_order(WC_Order $order) {
    global $appContainerLazyPaypalModules;
    $purchaseUnits = $appContainerLazyPaypalModules->get('api.factory.purchase-unit')->from_wc_order($order)->to_array();
    $result = [];
    foreach (['amount', 'items', 'invoice_id'] as $attrToUse) {
        if (!array_key_exists($attrToUse, $purchaseUnits)) {
            continue;
        }
        $result[$attrToUse] = $purchaseUnits[$attrToUse];
    }
    
    return $result;
}

function csPaypalErrorLog($data, $message = '')
{
    $dataLogString = 'PayPal error: ' . csPaypalSafeLogMessage($message);
    if (!$logger = wc_get_logger()) {
        return;
    } else {
        $logger->error($dataLogString, ['source' => 'cardshield-gateway-paypal-ERROR']);
    }
}

function csPaypalDebugLog($data, $message = '')
{
    $dataLogString = 'PayPal debug: ' . csPaypalSafeLogMessage($message);
    if (!$logger = wc_get_logger()) {
        return;
    } else {
        $logger->debug($dataLogString, ['source' => 'cardshield-gateway-paypal-INFO']);
    }
}

function csPaypalSafeLogMessage($message) {
    $message = preg_replace('/https?:\/\/\S+/i', '[url]', (string) $message);
    $message = preg_replace('/\b(token|secret|password|cookie|authorization)\b[^\s:]*(\s*[:=]\s*)?\S*/i', '[redacted]', $message);
    $message = preg_replace('/\border[_ -]?id\s*[:=]\s*\S+/i', 'order_id:[redacted]', $message);
    return substr(preg_replace('/\s+/', ' ', trim($message)), 0, 240);
}

function getCsPaypalOrderDetailFromWcOrder(WC_Order $order) {
    // Shipping
    $shippingName     = $order->get_shipping_first_name() . " " . $order->get_shipping_last_name();
    $shippingAddress1 = $order->get_shipping_address_1();
    $shippingAddress2 = $order->get_shipping_address_2();
    $shippingCity     = $order->get_shipping_city();
    $shippingCountry  = $order->get_shipping_country();
    $shippingPostCode = $order->get_shipping_postcode();
    $shippingState    = $order->get_shipping_state();

    // Billing
    $billingName     = $order->get_billing_first_name() . " " . $order->get_billing_last_name();
    $billingAddress1 = $order->get_billing_address_1();
    $billingAddress2 = $order->get_billing_address_2();
    $billingCity     = $order->get_billing_city();
    $billingCountry  = $order->get_billing_country();
    $billingPostCode = $order->get_billing_postcode();
    $billingState    = $order->get_billing_state();

    $shippingName     = ( empty( $order->get_shipping_first_name() ) && empty( $order->get_shipping_last_name() ) ) ? $billingName : $shippingName;
    $shippingAddress1 = empty( $shippingAddress1 ) ? $billingAddress1 : $shippingAddress1;
    $shippingAddress2 = empty( $shippingAddress2 ) ? $billingAddress2 : $shippingAddress2;
    $shippingCity     = empty( $shippingCity ) ? $billingCity : $shippingCity;
    $shippingCountry  = empty( $shippingCountry ) ? $billingCountry : $shippingCountry;
    $shippingPostCode = empty( $shippingPostCode ) ? $billingPostCode : $shippingPostCode;
    $shippingState    = empty( $shippingState ) ? $billingState : $shippingState;
    $csPaypalGw = WC()->payment_gateways->payment_gateways()['lazy_paypal'];
    $trackingSyncPlugin = $csPaypalGw->get_option('transaction_logs_enable');
    if ($trackingSyncPlugin && $trackingSyncPlugin === 'yes') {
        return [
            'order_id' => $order->get_id(),
            'platform' => 'woocommerce',
            'endpoint_token' => get_option(OPT_CS_PAYPAL_ENDPOINT_TOKEN, null),
            'created_at' => date('Y/m/d H:i:s'),
            'amount_total' => $order->get_total(),
            'currency' => $order->get_currency(),
            'store_domain' => get_home_url(),
            'customer_name' => $shippingName,
            'customer_email' => $order->get_billing_email(),
            'customer_phone' => $order->get_billing_phone(),
            'customer_ip' => $order->get_customer_ip_address(),
            'shipping_address_company' => $order->get_billing_company(),
            'shipping_address_line1' => $shippingAddress1,
            'shipping_address_line2' => $shippingAddress2,
            'shipping_address_city' => $shippingCity,
            'shipping_address_state' => $shippingState,
            'shipping_address_country' => $shippingCountry,
            'shipping_address_postal_code' => $shippingPostCode,
        ];
    } else {
        return [
            'SETTING_DISABLE_TRANSACTION_LOG' => true,
        ];
    }
}

function csPaypalSaveTransactionId(WC_Order $order, $transactionId) {
    $order->set_transaction_id($transactionId);
    $order->update_meta_data('METAKEY_CS_TRANSACTION_ID', $transactionId);
    $order->save();
}

function csPaypalGetTransactionId(WC_Order $order) {
    $id = $order->get_transaction_id();
    if (empty($id)) {
        $id = $order->get_meta('METAKEY_CS_TRANSACTION_ID');
    }
    return $id;
}

function queryOrderNeedSyncPaypal($mode = 'count') {
    global $wpdb;

    $csPayPalGw = WC()->payment_gateways->payment_gateways()['lazy_paypal'];
    $trackingSyncPlugin = $csPayPalGw->get_option('sync_tracking_plugin');
    $selectStatement = 'COUNT(DISTINCT(posts.id)) as count';
    if ($mode == 'get') {
        $selectStatement = 'DISTINCT(posts.id) as order_id';
    }
    switch ( $trackingSyncPlugin ) {
        case OPT_CS_TRACKING_SYNC_PLUGIN_ADVANCED_SHIPMENT_TRACKING:
            return $wpdb->get_results( $wpdb->prepare( "
                SELECT $selectStatement FROM {$wpdb->prefix}posts AS posts
                LEFT JOIN {$wpdb->prefix}postmeta AS post_meta1 ON posts.id = post_meta1.post_id AND post_meta1.meta_key = %s
                LEFT JOIN {$wpdb->prefix}postmeta AS post_meta2 ON posts.id = post_meta2.post_id AND post_meta2.meta_key = '_wc_shipment_tracking_items'
                LEFT JOIN {$wpdb->prefix}postmeta AS post_meta3 ON posts.id = post_meta3.post_id AND post_meta3.meta_key = %s
                WHERE posts.post_type = 'shop_order' AND post_meta1.meta_value = %d AND post_meta2.meta_value IS NOT NULL AND (post_meta3.meta_value IS NULL OR post_meta3.meta_value = 'true');
            ", METAKEY_PAYPAL_SYNC_TRACKING_INFO , METAKEY_CS_PAYPAL_CAPTURED, OPT_CS_PAYPAL_NOT_SYNCED), ARRAY_A );

        case OPT_CS_TRACKING_SYNC_PLUGIN_ORDERS_TRACKING:
            return $wpdb->get_results( $wpdb->prepare( "
                SELECT $selectStatement FROM {$wpdb->prefix}posts AS posts
                LEFT JOIN {$wpdb->prefix}postmeta AS post_meta1 ON posts.id = post_meta1.post_id AND post_meta1.meta_key = %s
                LEFT JOIN {$wpdb->prefix}postmeta AS post_meta2 ON posts.id = post_meta2.post_id AND post_meta2.meta_key = %s 
                LEFT JOIN {$wpdb->prefix}postmeta AS post_meta3 ON posts.id = post_meta3.post_id AND post_meta3.meta_key = '_wot_tracking_number'
                LEFT JOIN {$wpdb->prefix}woocommerce_order_items AS order_items 
                    ON posts.id = order_items.order_id AND order_items.order_item_type = 'line_item'
                LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS order_itemmeta 
                    ON order_items.order_item_id = order_itemmeta.order_item_id AND order_itemmeta.meta_key = '_vi_wot_order_item_tracking_data'
                WHERE posts.post_type = 'shop_order' 
                  AND post_meta1.meta_value = %d 
                  AND (
                        (order_itemmeta.meta_value IS NOT NULL AND (post_meta2.meta_value IS NULL OR post_meta2.meta_value = 'true'))   
                        OR 
                        post_meta3.meta_value IS NOT NULL
                      );
            ", METAKEY_PAYPAL_SYNC_TRACKING_INFO, METAKEY_CS_PAYPAL_CAPTURED, OPT_CS_PAYPAL_NOT_SYNCED ), ARRAY_A );
        
        case OPT_CS_TRACKING_SYNC_PLUGIN_DIANXIAOMI:
            return $wpdb->get_results( $wpdb->prepare( "
                SELECT $selectStatement FROM {$wpdb->prefix}posts AS posts
                LEFT JOIN {$wpdb->prefix}postmeta AS post_meta1 ON posts.id = post_meta1.post_id AND post_meta1.meta_key = %s
                LEFT JOIN {$wpdb->prefix}postmeta AS post_meta2 ON posts.id = post_meta2.post_id AND post_meta2.meta_key = '_dianxiaomi_tracking_provider_name'
                LEFT JOIN {$wpdb->prefix}postmeta AS post_meta3 ON posts.id = post_meta3.post_id AND post_meta3.meta_key = %s
                LEFT JOIN {$wpdb->prefix}postmeta AS post_meta4 ON posts.id = post_meta4.post_id AND post_meta4.meta_key = '_dianxiaomi_tracking_number'
                WHERE posts.post_type = 'shop_order' AND post_meta1.meta_value = %d AND post_meta2.meta_value IS NOT NULL 
                    AND (post_meta3.meta_value IS NULL OR post_meta3.meta_value = 'true')
                    AND post_meta4.meta_value IS NOT NULL;
            ", METAKEY_PAYPAL_SYNC_TRACKING_INFO , METAKEY_CS_PAYPAL_CAPTURED, OPT_CS_PAYPAL_NOT_SYNCED), ARRAY_A );

    }
}

function queryOrderNeedSyncPaypalHPOS($mode = 'count')
{
    global $wpdb;

    $csPayPalGw = WC()->payment_gateways->payment_gateways()['lazy_paypal'];
    $trackingSyncPlugin = $csPayPalGw->get_option('sync_tracking_plugin');
    $selectStatement = 'COUNT(DISTINCT(orders.id)) as count';
    if ($mode == 'get') {
        $selectStatement = 'DISTINCT(orders.id) as order_id';
    }
    switch ($trackingSyncPlugin) {
        case OPT_CS_TRACKING_SYNC_PLUGIN_ADVANCED_SHIPMENT_TRACKING:
            return $wpdb->get_results($wpdb->prepare("
                SELECT $selectStatement FROM {$wpdb->prefix}wc_orders AS orders
                LEFT JOIN {$wpdb->prefix}wc_orders_meta AS order_meta1 ON orders.id = order_meta1.order_id AND order_meta1.meta_key = %s
                LEFT JOIN {$wpdb->prefix}wc_orders_meta AS order_meta2 ON orders.id = order_meta2.order_id AND order_meta2.meta_key = '_wc_shipment_tracking_items'
                LEFT JOIN {$wpdb->prefix}wc_orders_meta AS order_meta3 ON orders.id = order_meta3.order_id AND order_meta3.meta_key = %s
                WHERE order_meta1.meta_value = %d AND order_meta2.meta_value IS NOT NULL AND (order_meta3.meta_value IS NULL OR order_meta3.meta_value = 'true');
            ", METAKEY_PAYPAL_SYNC_TRACKING_INFO, METAKEY_CS_PAYPAL_CAPTURED, OPT_CS_PAYPAL_NOT_SYNCED), ARRAY_A);

        case OPT_CS_TRACKING_SYNC_PLUGIN_ORDERS_TRACKING:
            return $wpdb->get_results($wpdb->prepare("
                SELECT $selectStatement FROM {$wpdb->prefix}wc_orders AS orders
                LEFT JOIN {$wpdb->prefix}wc_orders_meta AS order_meta1 ON orders.id = order_meta1.order_id AND order_meta1.meta_key = %s
                LEFT JOIN {$wpdb->prefix}wc_orders_meta AS order_meta2 ON orders.id = order_meta2.order_id AND order_meta2.meta_key = %s 
                LEFT JOIN {$wpdb->prefix}wc_orders_meta AS order_meta3 ON orders.id = order_meta3.order_id AND order_meta3.meta_key = '_wot_tracking_number'
                LEFT JOIN {$wpdb->prefix}woocommerce_order_items AS order_items 
                    ON orders.id = order_items.order_id AND order_items.order_item_type = 'line_item'
                LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS order_itemmeta 
                    ON order_items.order_item_id = order_itemmeta.order_item_id AND order_itemmeta.meta_key = '_vi_wot_order_item_tracking_data'
                WHERE order_meta1.meta_value = %d 
                  AND (
                        (order_itemmeta.meta_value IS NOT NULL AND (order_meta2.meta_value IS NULL OR order_meta2.meta_value = 'true'))   
                        OR 
                        order_meta3.meta_value IS NOT NULL
                      );
            ", METAKEY_PAYPAL_SYNC_TRACKING_INFO, METAKEY_CS_PAYPAL_CAPTURED, OPT_CS_PAYPAL_NOT_SYNCED), ARRAY_A);

        case OPT_CS_TRACKING_SYNC_PLUGIN_DIANXIAOMI:
            return $wpdb->get_results($wpdb->prepare("
                SELECT $selectStatement FROM {$wpdb->prefix}wc_orders AS orders
                LEFT JOIN {$wpdb->prefix}wc_orders_meta AS order_meta1 ON orders.id = order_meta1.order_id AND order_meta1.meta_key = %s
                LEFT JOIN {$wpdb->prefix}wc_orders_meta AS order_meta2 ON orders.id = order_meta2.order_id AND order_meta2.meta_key = '_dianxiaomi_tracking_provider_name'
                LEFT JOIN {$wpdb->prefix}wc_orders_meta AS order_meta3 ON orders.id = order_meta3.order_id AND order_meta3.meta_key = %s
                LEFT JOIN {$wpdb->prefix}wc_orders_meta AS order_meta4 ON orders.id = order_meta4.order_id AND order_meta4.meta_key = '_dianxiaomi_tracking_number'
                WHERE order_meta1.meta_value = %d AND order_meta2.meta_value IS NOT NULL 
                    AND (order_meta3.meta_value IS NULL OR order_meta3.meta_value = 'true')
                    AND order_meta4.meta_value IS NOT NULL;
            ", METAKEY_PAYPAL_SYNC_TRACKING_INFO, METAKEY_CS_PAYPAL_CAPTURED, OPT_CS_PAYPAL_NOT_SYNCED), ARRAY_A);

    }
}

function isPaypalShieldReachAmount($orderTotal) {
    if (!isEnabledAmountRotation()) {
        return false;
    }
    $proxies = get_option(OPT_LAZY_PAYPAL_PROXIES, []);
    if (empty($proxies)) {
        return false;
    }
    foreach ($proxies as $proxy) {
        if(doubleval( $proxy['paid_amount'] ) + doubleval( $orderTotal ) < doubleval($proxy['amount'])) {
            return false;
        }
    }
    return true;
}

function csPaypalSendMailShieldReachAmount() {
    $csPaypalGw = WC()->payment_gateways->payment_gateways()['lazy_paypal'];
    if($csPaypalGw->get_option('send_email_notice_to_admin') === 'no') {
        return false;
    }
    $lastSent = strtotime(get_option('CS_PAYPAL_LAST_SEND_EMAIL_SHIELD_REACH_AMOUNT'));
    $now = strtotime(date( 'Y-m-d H:i:s'));
    if (($now - $lastSent)/60 > 360) { //Delay send by 360 minute
        update_option('CS_PAYPAL_LAST_SEND_EMAIL_SHIELD_REACH_AMOUNT', date( 'Y-m-d H:i:s'));
    } else {
        return false;
    }
    try {
        $headers[] = 'Content-type: text/html; charset=utf-8';
        add_filter( 'wp_mail_from_name', function () {
            return 'CardsShield';
        });
        $siteDomain = parse_url(get_home_url())['host'];
        ob_start();
		?>
            <p>
                Your website [<?=$siteDomain?>] cannot affort new orders due to shield amount has been reached.
                Please go to settings and increase the shield amount immediately!
            </p>
        <?php
		$body = ob_get_clean();
        $body = csPaypalBuildBodyEmail("Paypal shield amount has been reached", $body);
        wp_mail(csPaypalGetAdminEmails(), "[$siteDomain]: Paypal shield amount has been reached", $body, $headers);
    } catch (\Exception $e) {
        csPaypalErrorLog($e->getMessage(), 'csPaypalSendMailShieldReachAmount failed!');
    }
}

function csPaypalSendMailShieldDie($shieldUrl) {
    $csPaypalGw = WC()->payment_gateways->payment_gateways()['lazy_paypal'];
    if($csPaypalGw->get_option('send_email_notice_to_admin') === 'no') {
        return false;
    }
    try {
        $headers[] = 'Content-type: text/html; charset=utf-8';
        add_filter( 'wp_mail_from_name', function () {
            return 'CardsShield';
        });
        $siteDomain = parse_url(get_home_url())['host'];
        ob_start();
		?>
            <p>
                A Paypal shield <?= $shieldUrl ?> has been moved to unused list due to unchargable problem.
                Please check your payment account immediately!
            </p>
        <?php
		$body = ob_get_clean();
        $body = csPaypalBuildBodyEmail("A Paypal shield has been moved to unused list", $body);
        wp_mail(csPaypalGetAdminEmails(), "[$siteDomain]: A Paypal shield $shieldUrl has been moved to unused list", $body, $headers);
    } catch (\Exception $e) {
        csPaypalErrorLog($e->getMessage(), 'csPaypalSendMailShieldDie failed!');
    }
}

function csPaypalSendMailOrderBlacklisted($orderId) {
    $csPaypalGw = WC()->payment_gateways->payment_gateways()['lazy_paypal'];
    if($csPaypalGw->get_option('send_email_notice_to_admin') === 'no') {
        return false;
    }
    $order = wc_get_order($orderId);
    try {
        $headers[] = 'Content-type: text/html; charset=utf-8';
        add_filter( 'wp_mail_from_name', function () {
            return 'CardsShield';
        });
        $siteDomain = parse_url(get_home_url())['host'];
        ob_start();
		?>
            <?php
                wc_get_template( 'emails/email-order-details.php', array(
                    'order'         => $order,
                    'sent_to_admin' => true,
                    'plain_text'    => false,
                    'email'         => '',
                ));
                wc_get_template( 'emails/email-addresses.php', array(
                    'order'         => $order,
                    'sent_to_admin' => true,
                    'plain_text'    => false,
                    'email'         => '',
                ));
            ?>   
        <?php
		$body = ob_get_clean();
        $body = csPaypalBuildBodyEmail("Order Blacklisted: #{$order->get_order_number()}", $body);
        wp_mail(csPaypalGetAdminEmails(), "[$siteDomain]: Order #{$order->get_order_number()} has been BLACKLISTED", $body, $headers);
    } catch (\Exception $e) {
        csPaypalErrorLog($e->getMessage(), 'csPaypalSendMailShieldDie failed!');
    }
}

function csPaypalBuildBodyEmail($heading, $body) {
    add_filter( 'woocommerce_email_footer_text', function () {
        return "<br><b>Cards Shield Team</b><br/>support@cardsshield.com";
    } );
    $mailer = WC()->mailer();
    $wrapped_message = $mailer->wrap_message($heading, $body);
    $wc_email = new WC_Email;
    return $wc_email->style_inline($wrapped_message);
}

function csPaypalGetAdminEmails() {
    $blogusers = get_users('role=Administrator');
    $emails = [];
    foreach ($blogusers as $user) {
        $emails[] = $user->user_email;
    }
    if (!empty($emails)) {
        return implode(',', $emails);
    }
    return false;
}

function csPaypalGetRestrictionCustomerData() {
    $data = [
        'customer_email' => sanitize_email(wp_unslash($_POST['billing_email'] ?? '')),
        'billing_city' => sanitize_text_field(wp_unslash($_POST['billing_city'] ?? '')),
        'billing_state' => sanitize_text_field(wp_unslash($_POST['billing_state'] ?? '')),
        'billing_postcode' => sanitize_text_field(wp_unslash($_POST['billing_postcode'] ?? '')),
    ];
    if (function_exists('WC') && WC()->checkout()) {
        foreach (['billing_email' => 'customer_email', 'billing_city' => 'billing_city', 'billing_state' => 'billing_state', 'billing_postcode' => 'billing_postcode'] as $checkoutKey => $dataKey) {
            if ($data[$dataKey] === '') {
                $data[$dataKey] = sanitize_text_field((string) WC()->checkout()->get_value($checkoutKey));
            }
        }
    }
    return $data;
}

function csPaypalGetOrderRestrictionData(WC_Order $order) {
    return [
        'customer_email' => sanitize_email($order->get_billing_email()),
        'billing_city' => sanitize_text_field($order->get_billing_city()),
        'billing_state' => sanitize_text_field($order->get_billing_state()),
        'billing_postcode' => sanitize_text_field($order->get_billing_postcode()),
    ];
}

function csEndpointGetShieldPaypalToProcess($csOrderKey, $orderTotal, $customerData = []) {
    $now = strtotime(date( 'Y-m-d H:i:s'));
    if ($now - strtotime(get_option('CS_ENDPOINT_LAST_TIME_GET_SHIELD_PAYPAL_TO_PROCESS_FAILED')) < 60) { //cache 60s when call failed
        csPaypalDebugLog('cache get shield failed');
        return null;
    }
    $sessionShield = WC()->session->get("csEndpointGetShieldPaypalToProcessValue_$csOrderKey");
    if ($sessionShield) {
        $shield = json_decode($sessionShield);
        if (is_object($shield) && !empty($shield->shield_domain)) {
            return 'https://' . $shield->shield_domain;
        }
    }
    if (!$gwDomain = csGetGatewayDomain()) {
        csPaypalErrorLog('Endpoint token/secret could not resolve the Manager domain', 'csEndpointGetShieldPaypalToProcess config error');
        return null;
    }
    wc_get_logger()->debug('request csEndpointGetShieldPaypalToProcess', ['source' => 'cardshield-gateway-paypal-INFO']);
    $requestBody = array_merge([
        'cs_order_key' => $csOrderKey,
        'order_total' => $orderTotal,
        'ep_token' => get_option(OPT_CS_PAYPAL_ENDPOINT_TOKEN, null),
        'merchant_site' => get_home_url(),
        'payment_gateway' => OPT_CS_PAYMENT_GATEWAY_TYPE_PAYPAL,
    ], empty($customerData) ? csPaypalGetRestrictionCustomerData() : $customerData);
    $request = wp_remote_post($gwDomain . '/api/endpoint-rotation.php?endpoint=get-shield-process', [
        'sslverify' => csPaypalGetSSLVerifyStatus(),
        'timeout' => 300,
        'headers' => [
            'Content-Type' => 'application/json',
        ],
        'body' => json_encode($requestBody)
    ]);
    if (is_wp_error($request)) {
        csPaypalErrorLog($request, "csEndpointGetShieldPaypalToProcess error");
        return null;
    }
    $responseBody = wp_remote_retrieve_body($request);
    $data = json_decode($responseBody);
    csPaypalDebugLog($data, 'csEndpointGetShieldPaypalToProcess response');
    $responseCode = wp_remote_retrieve_response_code($request);
    if (is_object($data) && $data->status === 'success' && !empty($data->shield->shield_domain)) {
        $shieldUrl = 'https://' . $data->shield->shield_domain;
        // Global cache shield Url 30s
        WC()->session->set("csEndpointGetShieldPaypalToProcessValue_$csOrderKey", json_encode($data->shield));
        update_option('CS_ENDPOINT_LAST_TIME_GET_SHIELD_PAYPAL_TO_PROCESS', date( 'Y-m-d H:i:s'));
        return $shieldUrl;
    } else if (is_object($data) && in_array($data->code ?? '', ['EMPTY_SHIELDS', 'SHIELD_NOT_FOUND', 'MERCHANT_NOT_WHITELISTED', 'CUSTOMER_RESTRICTED'], true)) {
        update_option('CS_ENDPOINT_LAST_TIME_GET_SHIELD_PAYPAL_TO_PROCESS_FAILED', date( 'Y-m-d H:i:s'));
        csPaypalErrorLog($data, "csEndpointGetShieldPaypalToProcess rejected request");
        return null;
    } else {
        csPaypalErrorLog(['http_code' => $responseCode, 'body' => $responseBody], "csEndpointGetShieldPaypalToProcess invalid response");
        return null;
    }
}

function csEndpointPerformShieldRotateByAmount(WC_Order $order) {
    if (!$gwDomain = csGetGatewayDomain()) {
        return null;
    }
    $csOrderKey = $order->get_meta(METAKEY_PAYPAL_PROCESSING_ORDER_KEY);
    $body = [
        'cs_order_key' => $csOrderKey,
        'order_total' => $order->get_total(),
        'order_currency' => $order->get_currency(),
        'merchant_site' => get_home_url(),
        'ep_token' => get_option(OPT_CS_PAYPAL_ENDPOINT_TOKEN, null),
        'payment_gateway' => OPT_CS_PAYMENT_GATEWAY_TYPE_PAYPAL,
        'shield_processing' => json_decode(WC()->session->get("csEndpointGetShieldPaypalToProcessValue_$csOrderKey"), true),
    ];
    $body = array_merge($body, csPaypalGetOrderRestrictionData($order));
    $request = wp_remote_post($gwDomain . '/api/endpoint-rotation.php?endpoint=perform-rotate-shield-by-amount', [
        'sslverify' => csPaypalGetSSLVerifyStatus(),
        'timeout' => 300,
        'headers' => [
            'Content-Type' => 'application/json',
        ],
        'body' => json_encode($body)
    ]);
    csPaypalDebugLog(['request' => $body, 'response' => $request], "csEndpointPerformShieldRotateByAmount response");
    if (is_wp_error($request)) {
        csPaypalErrorLog($request, "csEndpointPerformShieldRotateByAmount error");
        return null;
    }
}

function csEndpointSetNextShield(WC_Order $order) {
    if (!$gwDomain = csGetGatewayDomain()) {
        return null;
    }
    $csOrderKey = $order->get_meta(METAKEY_PAYPAL_PROCESSING_ORDER_KEY);
    $request = wp_remote_post($gwDomain . '/api/endpoint-rotation.php?endpoint=set-next-shield', [
        'sslverify' => csPaypalGetSSLVerifyStatus(),
        'timeout' => 300,
        'headers' => [
            'Content-Type' => 'application/json',
        ],
        'body' => json_encode(array_merge([
            'cs_order_key' => $csOrderKey,
            'order_total' => $order->get_total(),
            'order_currency' => $order->get_currency(),
            'ep_token' => get_option(OPT_CS_PAYPAL_ENDPOINT_TOKEN, null),
            'payment_gateway' => OPT_CS_PAYMENT_GATEWAY_TYPE_PAYPAL,
            'shield_processing' => json_decode(WC()->session->get("csEndpointGetShieldPaypalToProcessValue_$csOrderKey"), true),
        ], csPaypalGetOrderRestrictionData($order)))
    ]);
    csPaypalDebugLog($request, "csEndpointSetNextShield response");
    if (is_wp_error($request)) {
        csPaypalErrorLog($request, "csEndpointSetNextShield error");
        return null;
    }
}

function csEndpointMoveToUnusedShield($shieldDomain) {
    if (!$gwDomain = csGetGatewayDomain()) {
        return null;
    }
    $request = wp_remote_post($gwDomain . '/api/endpoint-rotation.php?endpoint=move-to-unused-shield', [
        'sslverify' => csPaypalGetSSLVerifyStatus(),
        'timeout' => 300,
        'headers' => [
            'Content-Type' => 'application/json',
        ],
        'body' => json_encode([
            'ep_token' => get_option(OPT_CS_PAYPAL_ENDPOINT_TOKEN, null),
            'payment_gateway' => OPT_CS_PAYMENT_GATEWAY_TYPE_PAYPAL,
            'shield_domain' => $shieldDomain,
        ])
    ]);
    csPaypalDebugLog($request, "csEndpointMoveToUnusedShield response");
    if (is_wp_error($request)) {
        csPaypalErrorLog($request, "csEndpointMoveToUnusedShield error");
        return null;
    }
}

function isCsPaypalEnableEndpointMode() {
    return get_option(OPT_LAZY_PAYPAL_CONNECTION_MODE, null) == OPT_CS_PAYPAL_CONNECTION_MODE_ENDPOINT_TOKEN;
}

function csGetGatewayDomain()
{
    try {
        $endpointToken = get_option(OPT_CS_PAYPAL_ENDPOINT_TOKEN, null);
        $endpointSecret = get_option(OPT_CS_PAYPAL_ENDPOINT_SECRET, null);
        if (empty($endpointToken) || empty($endpointSecret)) {
            return null;
        }
        $decrypt = base64_decode(strtr(base64_decode($endpointSecret),
            './-:?=&%# ZQXJKVWPY abcdefghijklmnopqrstuvwxyz123456789ABCDEFGHILMNORSTU',
            'ZQXJKVWPY ./-:?=&%# 123456789ABCDEFGHILMNORSTUabcdefghijklmnopqrstuvwxyz'));
        $domain = str_replace($endpointToken, '', (string) $decrypt);
        return rtrim(trim($domain), '/');
    } catch (\Exception $e) {
        csPaypalErrorLog($e->getMessage(), 'csGetGatewayDomain failed!');
        return null;
    }
}

function csPaypalGetClientIP()
{
    foreach (array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR') as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);

                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? null;
}

function csMerchantSiteEncode($merchantSite)
{
    return base64_encode(strtr(base64_encode($merchantSite),
        './-:?=&%# ZQXJKVWPY abcdefghijklmnopqrstuvwxyz123456789ABCDEFGHILMNORSTU',
        'ZQXJKVWPY ./-:?=&%# 123456789ABCDEFGHILMNORSTUabcdefghijklmnopqrstuvwxyz'));
}

function csPaypalGetSSLVerifyStatus()
{
    try {
        if (WC_Lazy_Gateway::load()->sslverify === 'yes') {
            return true;
        }
    } catch (\Exception $e) {
        csPaypalErrorLog($e->getMessage(), 'csPaypalGetSSLVerifyStatus failed');
    }
    return false;
}

function csPpGenerateRandomString($length = 16)
{
    $length = $length - 1;
    $characters = '012345678901234567890123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return rand(0, 9) . $randomString;
}
