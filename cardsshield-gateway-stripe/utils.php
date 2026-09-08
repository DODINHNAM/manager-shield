<?php

if ( !in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    return;
}

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

const OPT_LAZY_STRIPE_VERSION = '2.6.7';
const Opt_Lazy_Stripe_Proxies = 'Opt_Lazy_Stripe_Proxies';
const Opt_Lazy_Stripe_Activated_Proxy = 'Opt_Lazy_Stripe_Activated_Proxy';
const OPT_LAZY_STRIPE_ROTATION_METHOD =  'OPT_LAZY_STRIPE_ROTATION_METHOD';
const OPT_LAZY_STRIPE_UNUSED_PROXIES = 'OPT_LAZY_STRIPE_UNUSED_PROXIES';
const OPT_LAZY_STRIPE_CURRENT_ROTATION_VALUE = 'OPT_LAZY_STRIPE_CURRENT_ROTATION_VALUE';
const MetaKey_Stripe_Proxy_Url = '_lazy_stripe_proxy_url';
const OPT_LAZY_STRIPE_LAST_TIME_RESET_PAID_AMOUNT = 'OPT_LAZY_STRIPE_LAST_TIME_RESET_PAID_AMOUNT';
const OPT_LAZY_STRIPE_CONNECTION_MODE            = 'OPT_LAZY_STRIPE_CONNECTION_MODE';
const OPT_CS_STRIPE_CONNECTION_MODE_SHIELD_DOMAINS   = "shield_domains";
const OPT_CS_STRIPE_CONNECTION_MODE_ENDPOINT_TOKEN = "endpoint_token";
const OPT_CS_STRIPE_ENDPOINT_TOKEN = "OPT_CS_STRIPE_ENDPOINT_TOKEN";
const OPT_CS_STRIPE_ENDPOINT_SECRET= "OPT_CS_STRIPE_ENDPOINT_SECRET";
const OPT_CS_PAYMENT_GATEWAY_TYPE_STRIPE = 2;

const OPT_LAZY_STRIPE_INTENT_CAPTURE = 'OPT_LAZY_STRIPE_INTENT_CAPTURE';
const OPT_LAZY_STRIPE_INTENT_AUTHORIZE = 'OPT_LAZY_STRIPE_INTENT_AUTHORIZE';
const LAZY_STRIPE_PAYMENT_MODE_HOSTED = 'hosted';
const LAZY_STRIPE_PAYMENT_MODE_EMBEDDED = 'embedded';

const METAKEY_LAZY_STRIPE_INTENT_AUTHORIZED = '_METAKEY_LAZY_STRIPE_INTENT_CAPTURED';
const METAKEY_LAZY_STRIPE_CAPTURED = 'METAKEY_LAZY_STRIPE_CAPTURED';
const METAKEY_STRIPE_PROXY_URL          = '_lazy_stripe_proxy_url';
const METAKEY_STRIPE_PROXY_ID          = '_lazy_stripe_proxy_id';
const LAZY_STRIPE_BY_TIME                       = "by_time";
const LAZY_STRIPE_BY_AMOUNT                     = "by_amount";

const METAKEY_STRIPE_PROCESSING_ORDER_KEY = '_METAKEY_STRIPE_PROCESSING_ORDER_KEY';

const METAKEY_CS_STRIPE_FEE      = '_cs_stripe_fee';
const METAKEY_CS_STRIPE_PAYOUT   = '_cs_stripe_payout';
const METAKEY_CS_STRIPE_CURRENCY = '_cs_stripe_currency';
const METAKEY_CS_STRIPE_RAND_ORDER_ID = '_cs_stripe_rand_order_id';
const METAKEY_CS_STRIPE_PAYMENT_PROCESSING_STATUS = 'METAKEY_CS_STRIPE_PAYMENT_PROCESSING_STATUS';

const OPT_CS_STRIPE_NOT_SYNCED = 1;
const OPT_CS_STRIPE_SYNCED     = 2;
const OPT_CS_STRIPE_SYNC_ERROR = 99;
const OPT_CS_STRIPE_TRACKING_SYNC_PLUGIN_ADVANCED_SHIPMENT_TRACKING='OPT_CS_STRIPE_TRACKING_SYNC_PLUGIN_ADVANCED_SHIPMENT_TRACKING';
const OPT_CS_STRIPE_TRACKING_SYNC_PLUGIN_ORDERS_TRACKING='OPT_CS_STRIPE_TRACKING_SYNC_PLUGIN_ORDERS_TRACKING';
const OPT_CS_STRIPE_TRACKING_SYNC_PLUGIN_DIANXIAOMI='OPT_CS_STRIPE_TRACKING_SYNC_PLUGIN_DIANXIAOMI';
const METAKEY_STRIPE_SYNC_TRACKING_INFO = '_lazy_stripe_sync_tracking_info';

const CONST_CS_STRIPE_GET_CHARGE_STATUS_503 = 'CONST_CS_STRIPE_GET_CHARGE_STATUS_503';
const CONST_CS_STRIPE_GET_CHARGE_STATUS_ERROR = 'CONST_CS_STRIPE_GET_CHARGE_STATUS_FAILED';
const CONST_CS_STRIPE_GET_CHARGE_STATUS_UNKNOWN = 'CONST_CS_STRIPE_GET_CHARGE_STATUS_UNKNOWN';
const CONST_CS_STRIPE_GET_CHARGE_STATUS_ACTIVE = 'CONST_CS_STRIPE_GET_CHARGE_STATUS_ACTIVE';
const CONST_CS_STRIPE_GET_CHARGE_STATUS_DEACTIVE = 'CONST_CS_STRIPE_GET_CHARGE_STATUS_DEACTIVE';

// true: order currency
// false: stripe currency
const LAZY_STRIPE_FEE_DISPLAY_ORDER_CURRENCY = true;

function resetPaidAmountIfNeedStripe() {
    $lastTimeReset = get_option(OPT_LAZY_STRIPE_LAST_TIME_RESET_PAID_AMOUNT, null);
    $proxies = get_option(Opt_Lazy_Stripe_Proxies, []);
    if (empty($proxies)) {
        return [];
    }
    // Reset
    if (empty($lastTimeReset) || date('Y-m-d') > $lastTimeReset) {
        return resetPaidAmountStripe($proxies);
    }
    return $proxies;
}

function findActivatedProxyDataByIdStripe($proxies, $activatedProxyId) {
    foreach ($proxies as $proxy) {
        if ($proxy['id'] === $activatedProxyId) {
            return $proxy;
        }
    }
    return null;
}

function getNextProxyAmountRotationStripe($orderTotal) {
    $proxies = get_option(Opt_Lazy_Stripe_Proxies, []);
    if (empty($proxies)) {
        return null;
    }
    $activatedProxy = get_option( Opt_Lazy_Stripe_Activated_Proxy, null );
    if (empty($activatedProxy)) {
        csStripeErrorLog("Activated proxy not found! Use the first proxy of rotation list");
        $activatedProxy = $proxies[0];
    }
    $activatedProxy = findActivatedProxyDataByIdStripe($proxies, $activatedProxy['id']);

    // Need to rotate proxy
    $isCurrentProxyMatched = false;
    foreach ($proxies as $proxy) {
        if($activatedProxy['id'] == $proxy['id'] && !$isCurrentProxyMatched) {
            $isCurrentProxyMatched = true;
            continue;
        }
        if($isCurrentProxyMatched && doubleval( $proxy['paid_amount'] ) + doubleval( $orderTotal ) < doubleval($proxy['amount'])) {
            return $proxy;
        }
    }
    foreach ($proxies as $proxy) {
        if(doubleval( $proxy['paid_amount'] ) + doubleval( $orderTotal ) < doubleval($proxy['amount'])) {
            return $proxy;
        }
    }
    return null;
}

function performProxyAmountRotationStripe($orderTotal) {
    $proxies = resetPaidAmountIfNeedStripe();
    if (empty($proxies)) {
        return null;
    }
    $activatedProxy = get_option( Opt_Lazy_Stripe_Activated_Proxy, null );
    if (empty($activatedProxy)) {
        csStripeErrorLog("Activated proxy not found! Use the first proxy of rotation list");
        $activatedProxy = $proxies[0];
        update_option(Opt_Lazy_Stripe_Activated_Proxy, $activatedProxy, true);
        logStripeRotation(LAZY_STRIPE_BY_AMOUNT, $activatedProxy, "Auto");
    }
    $activatedProxy = findActivatedProxyDataByIdStripe($proxies, $activatedProxy['id']);

    // Need to rotate proxy
    $isCurrentProxyMatched = false;
    foreach ($proxies as $proxy) {
        if($activatedProxy['id'] == $proxy['id'] && !$isCurrentProxyMatched) {
            $isCurrentProxyMatched = true;
            continue;
        }
        if($isCurrentProxyMatched && doubleval( $proxy['paid_amount'] ) + doubleval( $orderTotal ) < doubleval($proxy['amount'])) {
            $activatedProxy = $proxy;
            update_option(Opt_Lazy_Stripe_Activated_Proxy, $activatedProxy, true);
            logStripeRotation(LAZY_STRIPE_BY_AMOUNT, $activatedProxy, "Auto");
            return $activatedProxy;
        }
    }
    foreach ($proxies as $proxy) {
        if(doubleval( $proxy['paid_amount'] ) + doubleval( $orderTotal ) < doubleval($proxy['amount'])) {
            $activatedProxy = $proxy;
            update_option(Opt_Lazy_Stripe_Activated_Proxy, $activatedProxy, true);
            logStripeRotation(LAZY_STRIPE_BY_AMOUNT, $activatedProxy, "Auto");
            return $activatedProxy;
        }
    }
    return null;
}

function resetPaidAmountStripe($proxies = null)
{
    if (empty($proxies)) {
        $proxies = get_option( Opt_Lazy_Stripe_Proxies, [] );
    }
    if (empty($proxies)) return [];
    // Reset
    $newProxies = array_map(function ($proxy) {
        $proxy['paid_amount'] = 0;
        return $proxy;
    }, $proxies);
    $unusedProxies = get_option(OPT_LAZY_STRIPE_UNUSED_PROXIES, []);
    if (empty($unusedProxies)) {
        $newUnusedProxies = [];
    } else {
        $newUnusedProxies = array_map(function ($unusedProxy) {
            $unusedProxy['paid_amount'] = 0;
            return $unusedProxy;
        }, $unusedProxies);
    }
    update_option(Opt_Lazy_Stripe_Proxies, $newProxies, true);
    update_option(OPT_LAZY_STRIPE_UNUSED_PROXIES, $newUnusedProxies, true);
    update_option(OPT_LAZY_STRIPE_LAST_TIME_RESET_PAID_AMOUNT, date('Y-m-d'), true);
    return $newProxies;
}

function logStripeRotation($rotationMethod, $proxy, $type)
{
    $methodLabel = $rotationMethod === LAZY_STRIPE_BY_TIME ? 'BY_TIME' : 'BY_AMOUNT';
    $rotationValue = $rotationMethod === LAZY_STRIPE_BY_TIME
        ? $proxy['timestamp']
        : ($proxy['paid_amount'] . '/' . $proxy['amount']);
    $message = "[{$methodLabel}] {$proxy['url']} , {$rotationValue} - {$type}";

    if (function_exists('wc_get_logger') && ($logger = wc_get_logger())) {
        $logger->info($message, ['source' => 'cardsshield-stripe-rotation']);
    } else {
        error_log('[cardsshield-stripe-rotation] ' . $message);
    }
}

function isEnabledAmountRotationStripe() {
    return LAZY_STRIPE_BY_AMOUNT === get_option(OPT_LAZY_STRIPE_ROTATION_METHOD, LAZY_STRIPE_BY_TIME);
}

function updateRotationAmountStripe($processedProxyId, $orderTotal) {
    $proxies = get_option(Opt_Lazy_Stripe_Proxies, []);
    foreach ($proxies as $key => $proxy) {
        if ($proxy['id'] === $processedProxyId) {
            $proxies[$key]['paid_amount'] = doubleval($proxy['paid_amount']) + doubleval( $orderTotal );
            break;
        }
    }
    return update_option(Opt_Lazy_Stripe_Proxies, $proxies, true);
}

function hasPayableProxyStripe($cartTotal) {
    $proxies = resetPaidAmountIfNeedStripe();
    if (empty($proxies)) {
        return false;
    }

    foreach ($proxies as $proxy) {
        if(doubleval( $proxy['paid_amount'] ) + doubleval( $cartTotal ) < doubleval($proxy['amount'])) {
            return true;
        }
    }
    return false;
}

function csStripeErrorLog($data, $message = '')
{
    $trace = debug_backtrace();
    $dataLogString = csStripeHandleDataLog($data, $message) 
        . print_r([$trace[1]['class'] ?? null, $trace[1]['function'] ?? null, $trace[1]['args'] ?? null], true);
    if (!$logger = wc_get_logger()) {
        error_log($dataLogString);
    } else {
        $logger->debug($dataLogString, ['source' => 'cardshield-gateway-stripe-ERROR']);
    }
}

function csStripeDebugLog($data, $message = '')
{
    $trace = debug_backtrace();
    $dataLogString = csStripeHandleDataLog($data, $message) 
        . print_r([$trace[1]['class'] ?? null, $trace[1]['function'] ?? null, $trace[1]['args'] ?? null], true);
    if (!$logger = wc_get_logger()) {
        error_log($dataLogString);
    } else {
        $logger->debug($dataLogString, ['source' => 'cardshield-gateway-stripe-INFO']);
    }
}

function csStripeHandleDataLog($data, $message = '') {
    try {
        if (is_array($data) || is_object($data)) {
            $dataLog = print_r($data, true);
        } else {
            $dataLog = (string)$data;
        }
    } catch (\Exception $e) {
        $dataLog = 'csStripeLog ERROR: ' . $e->getMessage();
    }
    return '\n--------------------- ' . $message . ' ---------------------\n'
        . $dataLog;
}

function stripeMoveToUnusedProxyIds($proxyIds) {
    $proxies        = get_option( Opt_Lazy_Stripe_Proxies, [] );
    if (empty($proxies)) {
        $proxies = [];
    }
    $unusedProxies  = get_option( OPT_LAZY_STRIPE_UNUSED_PROXIES, [] );
    if (empty($unusedProxies)) {
        $unusedProxies = [];
    }
    foreach ( $proxies as $key => $proxy ) {
        if ( in_array( $proxy['id'], $proxyIds ) ) {
            $unusedProxies[] = $proxy;
            unset( $proxies[ $key ] );
        }
    }
    $isSuccess1 = update_option( Opt_Lazy_Stripe_Proxies, array_values($proxies), true );
    $isSuccess2 = update_option( OPT_LAZY_STRIPE_UNUSED_PROXIES, $unusedProxies, true );
    $proxies        = get_option( Opt_Lazy_Stripe_Proxies, [] );
    update_option( Opt_Lazy_Stripe_Activated_Proxy, isset($proxies[0]) ? $proxies[0] : null, true );
    return $isSuccess1 && $isSuccess2;
}

function setNextProxyByTimeRotation() {
    $proxies = get_option( Opt_Lazy_Stripe_Proxies, [] );
    $activatedProxy = get_option( Opt_Lazy_Stripe_Activated_Proxy, null );
    if (empty($activatedProxy)) {
        csStripeErrorLog("Activated proxy not found! Use the first proxy of rotation list[2]");
        $activatedProxy = $proxies[0];
        update_option(Opt_Lazy_Stripe_Activated_Proxy, $activatedProxy, true);
    }
    $activatedProxy = findActivatedProxyDataByIdStripe($proxies, $activatedProxy['id']);

    // Need to rotate proxy
    $isCurrentProxyMatched = false;
    foreach ($proxies as $proxy) {
        if($activatedProxy['id'] == $proxy['id'] && !$isCurrentProxyMatched) {
            $isCurrentProxyMatched = true;
            continue;
        }
        if($isCurrentProxyMatched) {
            $activatedProxy = $proxy;
            update_option(Opt_Lazy_Stripe_Activated_Proxy, $activatedProxy, true);
            return $activatedProxy;
        }
    }
    return isset($proxies[0]) ? $proxies[0] : null;
}

function getCsStripeOrderDetailFromWcOrder(WC_Order $order) {
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
    $csStripeGw = WC()->payment_gateways->payment_gateways()['lazy_stripe'];
    $trackingSyncPlugin = $csStripeGw->get_option('transaction_logs_enable');
    if ($trackingSyncPlugin && $trackingSyncPlugin === 'yes') {
        $products = [];
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            $variationParts = [];
            foreach ($item->get_formatted_meta_data('_', true) as $meta) {
                $variationParts[] = html_entity_decode(wp_strip_all_tags($meta->display_key . ': ' . $meta->display_value), ENT_QUOTES, 'UTF-8');
            }
            $products[] = [
                'name' => $item->get_name(),
                'sku' => $product ? $product->get_sku() : '',
                'variation' => implode(' | ', $variationParts),
                'quantity' => $item->get_quantity(),
                'total' => $item->get_total(),
            ];
        }
        $orderFees = [];
        foreach ($order->get_fees() as $fee) {
            $orderFees[] = [
                'name' => $fee->get_name(),
                'amount' => $fee->get_total(),
            ];
        }
        $gatewayFee = $order->get_meta(METAKEY_CS_STRIPE_FEE);
        $hasGatewayFee = ($gatewayFee !== '' && $gatewayFee !== null);
        return [
            'order_id' => $order->get_id(),
            'platform' => 'woocommerce',
            'created_at' => date('Y/m/d H:i:s'),
            'amount_total' => $order->get_total(),
            'currency' => $order->get_currency(),
            'store_domain' => get_home_url(),
            'customer_name' => $shippingName,
            'customer_email' => $order->get_billing_email(),
            'customer_phone' => $order->get_billing_phone(),
            'customer_ip' => $order->get_meta('_cs_customer_ip') ?: $order->get_customer_ip_address(),
            'shipping_address_company' => $order->get_billing_company(),
            'shipping_address_line1' => $shippingAddress1,
            'shipping_address_line2' => $shippingAddress2,
            'shipping_address_city' => $shippingCity,
            'shipping_address_state' => $shippingState,
            'shipping_address_country' => $shippingCountry,
            'shipping_address_postal_code' => $shippingPostCode,
            'products' => $products,
            'amount_subtotal' => $order->get_subtotal(),
            'amount_discount' => $order->get_total_discount(),
            'amount_shipping' => $order->get_shipping_total(),
            'amount_tax' => $order->get_total_tax(),
            'order_fees' => $orderFees,
            'gateway_fee' => $hasGatewayFee ? $gatewayFee : null,
            'gateway_net' => $hasGatewayFee ? $order->get_total() - $gatewayFee : null,
        ];
    } else {
        return [
            'SETTING_DISABLE_TRANSACTION_LOG' => true,
        ];
    }
    
}

function syncTrackingInfoStripe() {
    $csStripeGw = WC()->payment_gateways->payment_gateways()['lazy_stripe'];
    $trackingSyncPlugin = $csStripeGw->get_option('sync_tracking_plugin');
    if (get_option('woocommerce_custom_orders_table_enabled') === 'yes') {
        $orders = queryOrderNeedSyncStripeHPOS('get');
    } else {
        $orders = queryOrderNeedSyncStripe('get');
    }
    $hasError = false;
    $errorOrderIdList = [];
    $shippingData = [];

    foreach ($orders as $order) {
        $orderId = $order['order_id'];
        $order = wc_get_order($orderId);
        $trackingNumberArr = [];

        $processedProxyUrl = $order->get_meta( METAKEY_STRIPE_PROXY_URL );
        if ( empty( $processedProxyUrl ) ) {
            csStripeErrorLog('Sync error: Empty proxy url, order_id: ' . $orderId);
            $hasError       = true;
            $errorOrderIdList[] = $orderId;
            $order->update_meta_data(METAKEY_STRIPE_SYNC_TRACKING_INFO, OPT_CS_STRIPE_SYNC_ERROR );
            $order->save_meta_data();
            continue;
        }
        if ($order->get_status() === 'on-hold') {
            continue;
        }

        if ($trackingSyncPlugin == OPT_CS_STRIPE_TRACKING_SYNC_PLUGIN_ADVANCED_SHIPMENT_TRACKING) {
            $trackingItems = $order->get_meta( '_wc_shipment_tracking_items' );
            if ( empty( $trackingItems ) ) {
                continue;
            }
            foreach ($trackingItems as $trackingItem) {
                if ( empty( $trackingItem['tracking_number'] ) ) {
                    continue;
                }
                if (in_array($trackingItem['tracking_number'], $trackingNumberArr)) {
                    continue;
                }
                $trackingNumberArr[] = $trackingItem['tracking_number'];
                $shippingData[$processedProxyUrl][] = [
                    'order_id'           => $orderId,
                    'transaction_id'     => csStripeGetTransactionId($order),
                    'tracking_number'    => $trackingItem['tracking_number'],
                    'carrier_name_other' => $trackingItem['tracking_provider'],
                ];
            }

        } else if ($trackingSyncPlugin == OPT_CS_STRIPE_TRACKING_SYNC_PLUGIN_ORDERS_TRACKING) {
            if($trackingDataByOrder = $order->get_meta('_wot_tracking_number')) {
                $shippingData[$processedProxyUrl][] = [
                    'order_id'           => $orderId,
                    'transaction_id'     => csStripeGetTransactionId($order),
                    'tracking_number'    => $trackingDataByOrder,
                    'carrier_name_other' => $order->get_meta('_wot_tracking_carrier'),
                ];
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
                    $shippingData[$processedProxyUrl][] = [
                        'order_id'           => $orderId,
                        'transaction_id'     => csStripeGetTransactionId($order),
                        'tracking_number'    => $current_tracking_data['tracking_number'],
                        'carrier_name_other' => $current_tracking_data['carrier_name'],
                    ];
                }   
            }
        } else if ($trackingSyncPlugin == OPT_CS_STRIPE_TRACKING_SYNC_PLUGIN_DIANXIAOMI) {
            $trackingProvider = $order->get_meta('_dianxiaomi_tracking_provider_name');
            $trackingNumber = $order->get_meta('_dianxiaomi_tracking_number');
            if ( empty( $trackingProvider ) || empty($trackingNumber) ) {
                continue;
            }
            $shippingData[$processedProxyUrl][] = [
                'order_id'           => $orderId,
                'transaction_id'     => csStripeGetTransactionId($order),
                'tracking_number'    => $trackingNumber,
                'status'             => 'SHIPPED',
                'carrier'            => 'OTHER',
                'carrier_name_other' => $trackingProvider,
            ];
        }
    }
    csStripeDebugLog($shippingData, 'Sync data $shippingData INFO');
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
            
            // Remove order_id field to add Stripe track
            $shippingDataPush = array_map(function ($data) {
                unset($data['order_id']);
                return $data;
            }, $shippingDataPart);
            $requestUrl = $proxyUrl . '?' . csStripeBuildQuery( ['lazy-stripe-pe-v2-sync-tracking' => 1,
                'data-track' => $shippingDataPush
            ]);
            $response = wp_remote_get($requestUrl, [
                'timeout' => 5 * 60,
            ]);
            
            if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
                $errorOrderIdList = array_unique(array_merge($errorOrderIdList, $orderIds));
                csStripeErrorLog([$response, $requestUrl, $orderIds], "Sync data error![1]");
                foreach ($orderIds as $orderId) {
                    $subOrder = wc_get_order($orderId);
                    $subOrder->update_meta_data( METAKEY_STRIPE_SYNC_TRACKING_INFO, OPT_CS_STRIPE_SYNC_ERROR );
                    $subOrder->save_meta_data();
                }
                $hasError = true;
                continue;
            }
            $data = json_decode( wp_remote_retrieve_body( $response ) );
            if ( ! $data->success ) {
                $errorOrderIdList = array_unique(array_merge($errorOrderIdList, $orderIds));
                csStripeErrorLog([$response, $requestUrl, $orderIds], "Sync data error![2]");
                foreach ($orderIds as $orderId) {
                    $subOrder = wc_get_order($orderId);
                    $subOrder->update_meta_data( METAKEY_STRIPE_SYNC_TRACKING_INFO, OPT_CS_STRIPE_SYNC_ERROR );
                    $subOrder->save_meta_data();
                }
                $hasError = true;
            } else {
                foreach ($orderIds as $orderId) {
                    $subOrder = wc_get_order($orderId);
                    $subOrder->update_meta_data( METAKEY_STRIPE_SYNC_TRACKING_INFO, OPT_CS_STRIPE_SYNCED );
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
            'count'   => countOrderNeedSyncStripe()
        ] );
    }
}

function isStripeShieldReachAmount($orderTotal) {
    if (!isEnabledAmountRotationStripe()) {
        return false;
    }
    $proxies = get_option(Opt_Lazy_Stripe_Proxies, []);
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

function csStripeSendMailShieldReachAmount() {
    $csStripeGw = WC()->payment_gateways->payment_gateways()['lazy_stripe'];
    if($csStripeGw->get_option('send_email_notice_to_admin') === 'no'){
        return false;
    }
    $lastSent = strtotime(get_option('CS_STRIPE_LAST_SEND_EMAIL_SHIELD_REACH_AMOUNT'));
    $now = strtotime(date( 'Y-m-d H:i:s'));
    if (($now - $lastSent)/60 > 360) { //Delay send by 360 minute
        update_option('CS_STRIPE_LAST_SEND_EMAIL_SHIELD_REACH_AMOUNT', date( 'Y-m-d H:i:s'));
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
        $body = csStripeBuildBodyEmail("Stripe shield amount has been reached", $body);
        wp_mail(csStripeGetAdminEmails(), "[$siteDomain]: Stripe shield amount has been reached", $body, $headers);
    } catch (\Exception $e) {
        csStripeErrorLog($e->getMessage(), 'csStripeSendMailShieldReachAmount failed!');
    }
}

function csStripeSendMailShieldDie($shieldUrl) {
    $csStripeGw = WC()->payment_gateways->payment_gateways()['lazy_stripe'];
    if($csStripeGw->get_option('send_email_notice_to_admin') === 'no'){
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
                A Stripe shield <?= $shieldUrl ?> has been moved to unused list due to unchargable problem.
                Please check your payment account immediately!
            </p>
        <?php
		$body = ob_get_clean();
        $body = csStripeBuildBodyEmail("A Stripe shield has been moved to unused list", $body);
        wp_mail(csStripeGetAdminEmails(), "[$siteDomain]: A Stripe shield $shieldUrl has been moved to unused list", $body, $headers);
    } catch (\Exception $e) {
        csStripeErrorLog($e->getMessage(), 'csStripeSendMailShieldDie failed!');
    }
}

function csStripeSendMailOrderBlacklisted($orderId) {
    $csStripeGw = WC()->payment_gateways->payment_gateways()['lazy_stripe'];
    if($csStripeGw->get_option('send_email_notice_to_admin') === 'no'){
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
        $body = csStripeBuildBodyEmail("Order Blacklisted: #{$order->get_order_number()}", $body);
        wp_mail(csStripeGetAdminEmails(), "[$siteDomain]: Order #{$order->get_order_number()} has BLACKLISTED", $body, $headers);
    } catch (\Exception $e) {
        csStripeErrorLog($e->getMessage(), 'csStripeSendMailShieldDie failed!');
    }
}

function csStripeBuildBodyEmail($heading, $body) {
    add_filter( 'woocommerce_email_footer_text', function () {
        return <<<OED
            <br>Cards Shield Team</b><br/>
            support@cardsshield.com
        OED;
    } );
    $mailer = WC()->mailer();
    $wrapped_message = $mailer->wrap_message($heading, $body);
    $wc_email = new WC_Email;
    return $wc_email->style_inline($wrapped_message);
}

function csStripeGetAdminEmails() {
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

function countOrderNeedSyncStripe() {
    if (get_option('woocommerce_custom_orders_table_enabled') === 'yes') {
        $results = queryOrderNeedSyncStripeHPOS('count');
    } else {
        $results = queryOrderNeedSyncStripe('count');
    }
    return $results[0]['count'] ?? 0;
}

/**
 * @param string $mode: count, get
 * @return string|null
 */
function queryOrderNeedSyncStripe($mode = 'count') {
    global $wpdb;

    $csStripeGw = WC()->payment_gateways->payment_gateways()['lazy_stripe'];
    $trackingSyncPlugin = $csStripeGw->get_option('sync_tracking_plugin');
    $selectStatement = 'COUNT(DISTINCT(posts.id)) as count';
    if ($mode == 'get') {
        $selectStatement = 'DISTINCT(posts.id) as order_id';
    }
    
    switch ( $trackingSyncPlugin ) {
        case OPT_CS_STRIPE_TRACKING_SYNC_PLUGIN_ADVANCED_SHIPMENT_TRACKING:
            return $wpdb->get_results( $wpdb->prepare( "
                SELECT $selectStatement FROM {$wpdb->prefix}posts AS posts
                LEFT JOIN {$wpdb->prefix}postmeta AS post_meta1 ON posts.id = post_meta1.post_id AND post_meta1.meta_key = %s
                LEFT JOIN {$wpdb->prefix}postmeta AS post_meta2 ON posts.id = post_meta2.post_id AND post_meta2.meta_key = '_wc_shipment_tracking_items'
                LEFT JOIN {$wpdb->prefix}postmeta AS post_meta3 ON posts.id = post_meta3.post_id AND post_meta3.meta_key = %s
                WHERE posts.post_type = 'shop_order' AND post_meta1.meta_value = %d AND post_meta2.meta_value IS NOT NULL AND (post_meta3.meta_value IS NULL OR post_meta3.meta_value = 'true');
            ", METAKEY_STRIPE_SYNC_TRACKING_INFO , METAKEY_LAZY_STRIPE_CAPTURED, OPT_CS_STRIPE_NOT_SYNCED) , ARRAY_A);

        case OPT_CS_STRIPE_TRACKING_SYNC_PLUGIN_ORDERS_TRACKING:
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
            ", METAKEY_STRIPE_SYNC_TRACKING_INFO, METAKEY_LAZY_STRIPE_CAPTURED, OPT_CS_STRIPE_NOT_SYNCED ) , ARRAY_A);
        
        case OPT_CS_STRIPE_TRACKING_SYNC_PLUGIN_DIANXIAOMI:
            return $wpdb->get_results( $wpdb->prepare( "
                SELECT $selectStatement FROM {$wpdb->prefix}posts AS posts
                LEFT JOIN {$wpdb->prefix}postmeta AS post_meta1 ON posts.id = post_meta1.post_id AND post_meta1.meta_key = %s
                LEFT JOIN {$wpdb->prefix}postmeta AS post_meta2 ON posts.id = post_meta2.post_id AND post_meta2.meta_key = '_dianxiaomi_tracking_provider_name'
                LEFT JOIN {$wpdb->prefix}postmeta AS post_meta3 ON posts.id = post_meta3.post_id AND post_meta3.meta_key = %s
                LEFT JOIN {$wpdb->prefix}postmeta AS post_meta4 ON posts.id = post_meta4.post_id AND post_meta4.meta_key = '_dianxiaomi_tracking_number'
                WHERE posts.post_type = 'shop_order' AND post_meta1.meta_value = %d AND post_meta2.meta_value IS NOT NULL 
                    AND (post_meta3.meta_value IS NULL OR post_meta3.meta_value = 'true')
                    AND post_meta4.meta_value IS NOT NULL;
            ", METAKEY_STRIPE_SYNC_TRACKING_INFO , METAKEY_LAZY_STRIPE_CAPTURED, OPT_CS_STRIPE_NOT_SYNCED) , ARRAY_A);

    }
}

function queryOrderNeedSyncStripeHPOS($mode = 'count') {
    global $wpdb;

    $csStripeGw = WC()->payment_gateways->payment_gateways()['lazy_stripe'];
    $trackingSyncPlugin = $csStripeGw->get_option('sync_tracking_plugin');
    $selectStatement = 'COUNT(DISTINCT(orders.id)) as count';
    if ($mode == 'get') {
        $selectStatement = 'DISTINCT(orders.id) as order_id';
    }
    
    switch ( $trackingSyncPlugin ) {
        case OPT_CS_STRIPE_TRACKING_SYNC_PLUGIN_ADVANCED_SHIPMENT_TRACKING:
            return $wpdb->get_results( $wpdb->prepare( "
                SELECT $selectStatement FROM {$wpdb->prefix}wc_orders AS orders
                LEFT JOIN {$wpdb->prefix}wc_orders_meta AS order_meta1 ON orders.id = order_meta1.order_id AND order_meta1.meta_key = %s
                LEFT JOIN {$wpdb->prefix}wc_orders_meta AS order_meta2 ON orders.id = order_meta2.order_id AND order_meta2.meta_key = '_wc_shipment_tracking_items'
                LEFT JOIN {$wpdb->prefix}wc_orders_meta AS order_meta3 ON orders.id = order_meta3.order_id AND order_meta3.meta_key = %s
                WHERE order_meta1.meta_value = %d AND order_meta2.meta_value IS NOT NULL AND (order_meta3.meta_value IS NULL OR order_meta3.meta_value = 'true');
            ", METAKEY_STRIPE_SYNC_TRACKING_INFO , METAKEY_LAZY_STRIPE_CAPTURED, OPT_CS_STRIPE_NOT_SYNCED) , ARRAY_A);

        case OPT_CS_STRIPE_TRACKING_SYNC_PLUGIN_ORDERS_TRACKING:
            return $wpdb->get_results( $wpdb->prepare( "
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
            ", METAKEY_STRIPE_SYNC_TRACKING_INFO, METAKEY_LAZY_STRIPE_CAPTURED, OPT_CS_STRIPE_NOT_SYNCED ) , ARRAY_A);
        
        case OPT_CS_STRIPE_TRACKING_SYNC_PLUGIN_DIANXIAOMI:
            return $wpdb->get_results( $wpdb->prepare( "
                SELECT $selectStatement FROM {$wpdb->prefix}wc_orders AS orders
                LEFT JOIN {$wpdb->prefix}wc_orders_meta AS order_meta1 ON orders.id = order_meta1.order_id AND order_meta1.meta_key = %s
                LEFT JOIN {$wpdb->prefix}wc_orders_meta AS order_meta2 ON orders.id = order_meta2.order_id AND order_meta2.meta_key = '_dianxiaomi_tracking_provider_name'
                LEFT JOIN {$wpdb->prefix}wc_orders_meta AS order_meta3 ON orders.id = order_meta3.order_id AND order_meta3.meta_key = %s
                LEFT JOIN {$wpdb->prefix}wc_orders_meta AS order_meta4 ON orders.id = order_meta4.order_id AND order_meta4.meta_key = '_dianxiaomi_tracking_number'
                WHERE order_meta1.meta_value = %d AND order_meta2.meta_value IS NOT NULL 
                    AND (order_meta3.meta_value IS NULL OR order_meta3.meta_value = 'true')
                    AND order_meta4.meta_value IS NOT NULL;
            ", METAKEY_STRIPE_SYNC_TRACKING_INFO , METAKEY_LAZY_STRIPE_CAPTURED, OPT_CS_STRIPE_NOT_SYNCED) , ARRAY_A);

    }
}


function csStripeSaveTransactionId(WC_Order $order, $transactionId) {
    $order->set_transaction_id($transactionId);
    $order->update_meta_data( 'METAKEY_CS_TRANSACTION_ID', $transactionId);
    $order->save();
}

function csStripeGetTransactionId(WC_Order $order) {
    $id = $order->get_transaction_id();
    if (empty($id)) {
        $id = $order->get_meta( 'METAKEY_CS_TRANSACTION_ID');
    }
    return $id;
}

function getStripeChargeStatusFromProxy($nextProxyId, $nextProxyUrl) {
    $accountStatus = get_option('CS_OPTION_SHIELD_STRIPE_ACCOUNT_CHARGE_STATUS' . $nextProxyId);
    $accountStatusLastUpdateAt = get_option('CS_OPTION_SHIELD_STRIPE_ACCOUNT_CHARGE_STATUS_LAST_UPDATE_AT' . $nextProxyId);
    if ($accountStatus && $accountStatusLastUpdateAt) {
        $now = new \DateTime();
        $accountStatusLastUpdateAt = new \DateTime($accountStatusLastUpdateAt);
        $intervalDate = $now->diff($accountStatusLastUpdateAt);
        $minuteDateDiff = ($intervalDate->days * 24 * 60) + ($intervalDate->h * 60) + $intervalDate->i;
        if ($minuteDateDiff < 5) {
            csStripeDebugLog($accountStatus, 'get charge status from Cache: ' . __LINE__);
            if ($accountStatus === 'deactive') {
                return CONST_CS_STRIPE_GET_CHARGE_STATUS_DEACTIVE;
            } else if ($accountStatus === 'active') {
                return CONST_CS_STRIPE_GET_CHARGE_STATUS_ACTIVE;
            } else {
                return CONST_CS_STRIPE_GET_CHARGE_STATUS_UNKNOWN;
            }
        }
    }
    $response = wp_remote_get($nextProxyUrl . '?' . csStripeBuildQuery([
            'lazy-stripe-pe-v2-get-account-charge-status' => uniqid(),
        ]), [
        'sslverify' => csStripeGetSSLVerifyStatus(),
        'timeout' => 20 * 60,
    ]);
    if (is_wp_error($response)) {
        $response_code = wp_remote_retrieve_response_code($response);
        csStripeErrorLog([$nextProxyUrl, $nextProxyId, $response], 'API check charge fail!');
        if ($response_code == 503) {
            return CONST_CS_STRIPE_GET_CHARGE_STATUS_503;
        }
        return CONST_CS_STRIPE_GET_CHARGE_STATUS_ERROR;
    } else {
        $bodyResponse = wp_remote_retrieve_body($response);
        $body = json_decode($bodyResponse);
        if ($body->status === 'deactive') {
            csStripeErrorLog([$nextProxyUrl, $nextProxyId, $bodyResponse], 'Proxy move to unused because check charge status deactive!');
            update_option('CS_OPTION_SHIELD_STRIPE_ACCOUNT_CHARGE_STATUS' . $nextProxyId, 'deactive');
            update_option('CS_OPTION_SHIELD_STRIPE_ACCOUNT_CHARGE_STATUS_LAST_UPDATE_AT' . $nextProxyId, date("Y-m-d H:i:s"));
            return CONST_CS_STRIPE_GET_CHARGE_STATUS_DEACTIVE;
        } else if ($body->status === 'active') {
            update_option('CS_OPTION_SHIELD_STRIPE_ACCOUNT_CHARGE_STATUS' . $nextProxyId, 'active');
            update_option('CS_OPTION_SHIELD_STRIPE_ACCOUNT_CHARGE_STATUS_LAST_UPDATE_AT' . $nextProxyId, date("Y-m-d H:i:s"));
            return CONST_CS_STRIPE_GET_CHARGE_STATUS_ACTIVE;
        } else {
            csStripeErrorLog([$nextProxyUrl, $nextProxyId, $bodyResponse], 'account status unknown!');
            update_option('CS_OPTION_SHIELD_STRIPE_ACCOUNT_CHARGE_STATUS' . $nextProxyId, null);
            update_option('CS_OPTION_SHIELD_STRIPE_ACCOUNT_CHARGE_STATUS_LAST_UPDATE_AT' . $nextProxyId, null);
            return CONST_CS_STRIPE_GET_CHARGE_STATUS_UNKNOWN;
        }
    }
}

function csStripeStoreCustomerIp($order)
{
    if (!($order instanceof WC_Order)) {
        return;
    }
    $ip = csStripeGetClientIP();
    if (!empty($ip)) {
        $order->update_meta_data('_cs_customer_ip', $ip);
        $order->save_meta_data();
    }
}

function csStripeGetClientIP()
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

function csGetCurrentLanguage() {
    if (function_exists('pll_current_language')) {
        return pll_current_language();
    }
    if (function_exists('wpml_get_current_language')) {
        return wpml_get_current_language();
    }
    return substr(get_locale() ?: 'en', 0, 2);
}

function generateRandomString($length = 16)
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

function csStripeGetSSLVerifyStatus()
{
    try {
        if (WC_Lazy_Gateway_Stripe::get_instance()->sslverify === 'yes') {
            return true;
        }
    } catch (\Exception $e) {
        csStripeErrorLog($e->getMessage(), 'csStripeGetSSLVerifyStatus failed');
    }
    return false;
}

function csStripeGenerateProcessingOrderKey() {
    return md5(get_option(OPT_CS_STRIPE_ENDPOINT_TOKEN, null)) . '_' . md5(uniqid(rand(), true));
}

function csEndpointGetShieldStripeToProcess($csOrderKey, $orderTotal) {
    $lastTimeGetShield = strtotime(get_option('CS_ENDPOINT_LAST_TIME_GET_SHIELD_STRIPE_TO_PROCESS'));
    $shield = get_option('CS_ENDPOINT_SHIELD_STRIPE_TO_PROCESS', null);
    $now = strtotime(date( 'Y-m-d H:i:s'));
    if ($now - strtotime(get_option('CS_ENDPOINT_LAST_TIME_GET_SHIELD_STRIPE_TO_PROCESS_FAILED')) < 60) { //cache 60s when call failed
        csStripeDebugLog('cache get shield failed');
        return null;
    }
    if ($shield && ($now - $lastTimeGetShield) < 10) { //Cached 10s
        WC()->session->set("csEndpointGetShieldStripeToProcessValue_$csOrderKey", $shield);
        $shield = json_decode($shield);
        return 'https://' . $shield->shield_domain;
    }
    if (!$gwDomain = csStripeGetGatewayDomain()) {
        return null;
    }
    wc_get_logger()->debug('request csEndpointGetShieldStripeToProcess', ['source' => 'cardshield-gateway-stripe-INFO']);
    $request = wp_remote_post($gwDomain . '/woo/get-shield-process', [
        'sslverify' => csStripeGetSSLVerifyStatus(),
        'timeout' => 300,
        'headers' => [
            'Content-Type' => 'application/json',
        ],
        'body' => json_encode([
            'cs_order_key' => $csOrderKey,
            'order_total' => $orderTotal,
            'ep_token' => get_option(OPT_CS_STRIPE_ENDPOINT_TOKEN, null),
            'merchant_site' => get_home_url(),
            'payment_gateway' => OPT_CS_PAYMENT_GATEWAY_TYPE_STRIPE,
        ])
    ]);
    if (is_wp_error($request)) {
        csStripeErrorLog($request, "csEndpointGetShieldStripeToProcess error");
        return null;
    }
    $responseBody = wp_remote_retrieve_body($request);
    $data = json_decode($responseBody);
    csStripeDebugLog($data, 'csEndpointGetShieldStripeToProcess response');
    if ($data->status == 'success') {
        $shieldUrl = 'https://' . $data->shield->shield_domain;
        // Global cache shield Url 30s
        WC()->session->set("csEndpointGetShieldStripeToProcessValue_$csOrderKey", json_encode($data->shield));
        update_option('CS_ENDPOINT_SHIELD_STRIPE_TO_PROCESS', json_encode($data->shield));
        update_option('CS_ENDPOINT_LAST_TIME_GET_SHIELD_STRIPE_TO_PROCESS', date( 'Y-m-d H:i:s'));
        csStripeDebugLog($shieldUrl, '111111111');
        return $shieldUrl;
    } else if ($data->code === 'EMPTY_SHIELDS' || $data->code === 'SHIELD_NOT_FOUND') {
        update_option('CS_ENDPOINT_LAST_TIME_GET_SHIELD_STRIPE_TO_PROCESS_FAILED', date( 'Y-m-d H:i:s'));
        csStripeErrorLog($request, "csEndpointGetShieldStripeToProcess error[2]");
        return null;
    } else {
        csStripeErrorLog($request, "csEndpointGetShieldStripeToProcess error[3]");
        return null;
    }
}

function csStripeEndpointPerformShieldRotateByAmount(WC_Order $order) {
    if (!$gwDomain = csStripeGetGatewayDomain()) {
        return null;
    }
    $csOrderKey = $order->get_meta(METAKEY_STRIPE_PROCESSING_ORDER_KEY);
    $body = [
        'cs_order_key' => $csOrderKey,
        'order_total' => $order->get_total(),
        'order_currency' => $order->get_currency(),
        'ep_token' => get_option(OPT_CS_STRIPE_ENDPOINT_TOKEN, null),
        'shield_processing' => json_decode(WC()->session->get("csEndpointGetShieldStripeToProcessValue_$csOrderKey"), true),
    ];
    $request = wp_remote_post($gwDomain . '/woo/perform-rotate-shield-by-amount', [
        'sslverify' => csStripeGetSSLVerifyStatus(),
        'timeout' => 300,
        'headers' => [
            'Content-Type' => 'application/json',
        ],
        'body' => json_encode($body)
    ]);
    csStripeDebugLog(['request' => $body, 'response' => $request], "csEndpointPerformShieldRotateByAmount response");
    if (is_wp_error($request)) {
        csStripeErrorLog($request, "csEndpointPerformShieldRotateByAmount error");
        return null;
    }
}

function csStripeEndpointMoveToUnusedShield($shieldDomain) {
    if (!$gwDomain = csStripeGetGatewayDomain()) {
        return null;
    }
    $request = wp_remote_post($gwDomain . '/woo/move-to-unused-shield', [
        'sslverify' => csStripeGetSSLVerifyStatus(),
        'timeout' => 300,
        'headers' => [
            'Content-Type' => 'application/json',
        ],
        'body' => json_encode([
            'ep_token' => get_option(OPT_CS_STRIPE_ENDPOINT_TOKEN, null),
            'shield_domain' => $shieldDomain,
        ])
    ]);
    csStripeDebugLog($request, "csEndpointMoveToUnusedShield response");
    if (is_wp_error($request)) {
        csStripeErrorLog($request, "csEndpointMoveToUnusedShield error");
        return null;
    }
}

function isCsStripeEnableEndpointMode() {
    return get_option(OPT_LAZY_STRIPE_CONNECTION_MODE, null) == OPT_CS_STRIPE_CONNECTION_MODE_ENDPOINT_TOKEN;
}

function csStripeGetGatewayDomain()
{
    try {
        $endpointToken = get_option(OPT_CS_STRIPE_ENDPOINT_TOKEN, null);
        $endpointSecret = get_option(OPT_CS_STRIPE_ENDPOINT_SECRET, null);
        $decrypt = base64_decode(strtr(base64_decode($endpointSecret),
            './-:?=&%# ZQXJKVWPY abcdefghijklmnopqrstuvwxyz123456789ABCDEFGHILMNORSTU',
            'ZQXJKVWPY ./-:?=&%# 123456789ABCDEFGHILMNORSTUabcdefghijklmnopqrstuvwxyz'));
        return str_replace($endpointToken, '', $decrypt);
    } catch (\Exception $e) {
        csStripeErrorLog($e->getMessage(), 'csStripeGetGatewayDomain failed!');
        return null;
    }
}

function csStripeEndpointGetAmountRemaining() {
    if (!$gwDomain = csStripeGetGatewayDomain()) {
        return null;
    }
    if (!$epToken = get_option(OPT_CS_STRIPE_ENDPOINT_TOKEN, null)) {
        return -1;
    }
    $request = wp_remote_post($gwDomain . '/woo/get-remaining-amount', [
        'sslverify' => csStripeGetSSLVerifyStatus(),
        'timeout' => 300,
        'headers' => [
            'Content-Type' => 'application/json',
        ],
        'body' => json_encode([
            'ep_token' => $epToken,
        ])
    ]);
    if (is_wp_error($request)) {
        csStripeErrorLog($request, "csStripeEndpointGetAmountRemaining error");
        return null;
    }
    $responseBody = wp_remote_retrieve_body($request);
    $data = json_decode($responseBody);
    csStripeDebugLog($data, 'csStripeEndpointGetAmountRemaining response');
    if ($data->status == 'success') {
        return $data->value;
    }
    return -1;
}

function csStripeBuildQuery($params)
{
    return http_build_query(lazy_stripe_wire_params($params), '', '&', PHP_QUERY_RFC3986);
}


// Plugin icon URL with a version query so released icon changes bust CDN/browser cache.
function cs_stripe_icon_src( $rel_path, $plugin_file, $ver = '1' ) {
    return plugins_url( $rel_path, $plugin_file ) . '?v=' . $ver;
}
