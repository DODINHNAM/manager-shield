<?php

// update_option(Opt_Lazy_Proxies, array(), true);
if ( !in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    return;
}

require_once(plugin_dir_path(__FILE__) . 'utils.php');

class Lazy_Stripe_Paygate_Option
{
    public function __construct()
    {
        add_action('admin_menu', [$this, 'add_lazy_stripe_paygate_menu']);
        add_action('wp_ajax_lazy_gateway_stripe_action', [$this, 'lazy_gateway_stripe_action']);
        add_action('restrict_manage_posts', function () {
            global $typenow;
            $this->cs_stripe_filter_orders_by_sync_status($typenow);
        },
            20, 1);
        if (get_option('woocommerce_custom_orders_table_enabled') === 'yes') {
            add_action( 'woocommerce_order_list_table_extra_tablenav', [$this, 'cs_stripe_filter_orders_by_sync_status'], 20 );
        }
        add_filter( 'request', [$this, 'filter_orders_by_sync_status_query' ]);
        add_filter( 'bulk_actions-edit-shop_order', [$this, 'cs_register_tracking_sync_bulk_action'] );
        if (get_option('woocommerce_custom_orders_table_enabled') === 'yes') {
            add_filter('bulk_actions-woocommerce_page_wc-orders', [$this, 'cs_register_tracking_sync_bulk_action'] );
        }
        add_action( 'handle_bulk_actions-edit-shop_order', [$this, 'cs_bulk_process_stripe_tracking_status'], 20, 3 );
        if (get_option('woocommerce_custom_orders_table_enabled') === 'yes') {
            add_action( 'handle_bulk_actions-woocommerce_page_wc-orders', [$this, 'cs_bulk_process_stripe_tracking_status'], 20, 3 );
        }
        add_action( 'admin_notices', [$this, 'cs_process_stripe_tracking_status_notices'] );
        add_filter( 'woocommerce_order_data_store_cpt_get_orders_query', [$this, 'handle_tracking_info'], 10, 2 );
        add_action('manage_posts_extra_tablenav',
            function ($which) {
                global $typenow;
                $this->cs_stripe_admin_order_list_top_bar_button($typenow, $which);
            },
            20, 1);
        if (get_option('woocommerce_custom_orders_table_enabled') === 'yes') {
            add_action( 'woocommerce_order_list_table_extra_tablenav', [$this, 'cs_stripe_admin_order_list_top_bar_button'], 20, 2 );
        }
    }


    function add_lazy_stripe_paygate_menu()
    {
        $mypage = add_menu_page('CardsShield Gateway Stripe Settings', 'CardsShield Stripe', 'manage_options', 'lazy-gateway-stripe', [$this, 'lazy_page_init']);
        add_action('load-' . $mypage, [$this, 'enqueue_scripts_front_end']);
    }


    function enqueue_scripts_front_end()
    {
        // css
        wp_register_style('lazy_stripe_bs_css', "https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css");
        wp_enqueue_style('lazy_stripe_bs_css');

        wp_register_style("lazy_stripe_settings_css", plugins_url('assets/css/settings.css', __FILE__), [], filemtime(__DIR__ . '/assets/css/settings.css'));
        wp_enqueue_style('lazy_stripe_settings_css');


        // js
        wp_register_script("lazy_stripe_swal2", plugins_url('assets/js/sweetalert2.all.min.js', __FILE__), [], OPT_LAZY_STRIPE_VERSION);
        wp_enqueue_script("lazy_stripe_swal2");

        wp_register_script("lazy_stripe_bs_js", "https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js");
        wp_enqueue_script("lazy_stripe_bs_js");

        wp_register_script("lazy_stripe_settings", plugins_url('assets/js/settings.js', __FILE__), ['jquery', 'lazy_stripe_swal2'], filemtime(__DIR__ . '/assets/js/settings.js'));
        wp_enqueue_script("lazy_stripe_settings");


        // in JavaScript, object properties are accessed as ajax_object.ajax_url, ajax_object.we_value
        wp_localize_script('lazy_stripe_settings', 'ajax_object', ['ajax_url' => admin_url('admin-ajax.php'), 'we_value' => 1234]);
    }

    function cs_stripe_admin_order_list_top_bar_button( $type, $which ) {
        if ( 'shop_order' === $type && 'top' === $which ) {
            wp_register_style("lazy_stripe_woo_css", plugins_url('assets/css/woo_styles.css', __FILE__), [], OPT_LAZY_STRIPE_VERSION);
            wp_enqueue_style('lazy_stripe_woo_css');

            wp_register_script("lazy_stripe_woo_scripts", plugins_url('assets/js/woo_scripts.js', __FILE__), ['jquery'], filemtime(__DIR__ . '/assets/js/woo_scripts.js'));
            wp_enqueue_script("lazy_stripe_woo_scripts");
            wp_localize_script('lazy_stripe_woo_scripts', 'cs_ajax_object', [ 'ajax_url' => admin_url('admin-ajax.php'), 'we_value' => 1234 ] );

            $countOrderNeedSync = countOrderNeedSyncStripe();
            ?>
            <div class="alignleft actions custom">
                <input id="sync-count-stripe" type="hidden" value="<?= $countOrderNeedSync ?>"/>
                <button type="button" class="button button-primary" id="sync-tracking-info-btn-stripe">Sync tracking to Stripe: <?= $countOrderNeedSync ?><span class="load loading"></button>
            </div>
            <?php
        }
    }

    function cs_stripe_filter_orders_by_sync_status($type) {
        if ( 'shop_order' === $type ) {
            ?>
            <select name="_shop_stripe_order_sync_status" id="dropdown_shop_stripe_order_sync_status">
                <option value="">Filter by Stripe tracking sync status</option>
                <option value="<?= OPT_CS_STRIPE_NOT_SYNCED ?>" <?php echo esc_attr( isset( $_GET['_shop_stripe_order_sync_status'] ) ? selected( OPT_CS_STRIPE_NOT_SYNCED, wc_clean( $_GET['_shop_stripe_order_sync_status'] ), false ) : '' ); ?>>
                    Unsynced
                </option>
                <option value="<?= OPT_CS_STRIPE_SYNCED ?>" <?php echo esc_attr( isset( $_GET['_shop_stripe_order_sync_status'] ) ? selected( OPT_CS_STRIPE_SYNCED, wc_clean( $_GET['_shop_stripe_order_sync_status'] ), false ) : '' ); ?>>
                    Synced
                </option>
                <option value="<?= OPT_CS_STRIPE_SYNC_ERROR ?>" <?php echo esc_attr( isset( $_GET['_shop_stripe_order_sync_status'] ) ? selected( OPT_CS_STRIPE_SYNC_ERROR, wc_clean( $_GET['_shop_stripe_order_sync_status'] ), false ) : '' ); ?>>
                    Sync error
                </option>
            </select>
            <?php
        }
    }

    function filter_orders_by_sync_status_query( $vars ) {
        global $typenow;
        if ( 'shop_order' === $typenow && isset( $_GET['_shop_stripe_order_sync_status'] ) && '' != $_GET['_shop_stripe_order_sync_status'] ) {
            $vars['meta_query'][] = array(
                'key'       => '_lazy_stripe_sync_tracking_info',
                'value'     => wc_clean( $_GET['_shop_stripe_order_sync_status'] ),
                'compare'   => 'LIKE'
            );
        }

        return $vars;
    }

    function cs_register_tracking_sync_bulk_action( $bulk_actions ) {

        $bulk_actions[ 'cs_change_stripe_sync_status_to_synced' ] = 'Change Stripe sync status to synced';
        $bulk_actions[ 'cs_change_stripe_sync_status_to_unsynced' ] = 'Change Stripe sync status to unsynced';
        return $bulk_actions;

    }

    function cs_bulk_process_stripe_tracking_status( $redirect, $doaction, $object_ids ) {

        if( 'cs_change_stripe_sync_status_to_synced' === $doaction ) {

            // change status of every selected order
            foreach ( $object_ids as $order_id ) {
                $subOrder = wc_get_order($order_id);
                $subOrder->update_meta_data(METAKEY_STRIPE_SYNC_TRACKING_INFO, OPT_CS_STRIPE_SYNCED );
                $subOrder->save_meta_data();
            }

            // do not forget to add query args to URL because we will show notices later
            $redirect = add_query_arg(
                array(
                    'bulk_action' => 'cs_change_stripe_sync_status_to_synced',
                    'changed' => count( $object_ids ),
                ),
                $redirect
            );

        }

        if( 'cs_change_stripe_sync_status_to_unsynced' === $doaction ) {

            // change status of every selected order
            foreach ( $object_ids as $order_id ) {
                $subOrder = wc_get_order($order_id);
                $subOrder->update_meta_data( METAKEY_STRIPE_SYNC_TRACKING_INFO, OPT_CS_STRIPE_NOT_SYNCED );
                $subOrder->save_meta_data();
            }

            // do not forget to add query args to URL because we will show notices later
            $redirect = add_query_arg(
                array(
                    'bulk_action' => 'cs_change_stripe_sync_status_to_synced',
                    'changed' => count( $object_ids ),
                ),
                $redirect
            );

        }

        return $redirect;
    }

    function cs_process_stripe_tracking_status_notices() {

        if(
            isset( $_REQUEST[ 'bulk_action' ] )
            && 'cs_change_stripe_sync_status_to_synced' == $_REQUEST[ 'bulk_action' ]
            && isset( $_REQUEST[ 'changed' ] )
            && $_REQUEST[ 'changed' ]
        ) {

            // displaying the message
            printf(
                '<div id="message" class="updated notice is-dismissible"><p>' . _n( '%d order Stripe tracking sync status changed.', '%d order Stripe tracking sync statuses changed.', $_REQUEST[ 'changed' ] ) . '</p></div>',
                $_REQUEST[ 'changed' ]
            );

        }

    }


    /**
     * Handle a custom 'customvar' query var to get orders with the 'customvar' meta.
     * @param array $query - Args for WP_Query.
     * @param array $query_vars - Query vars from WC_Order_Query.
     * @return array modified $query
     */
    function handle_tracking_info( $query, $query_vars ) {
        if ( ! empty( $query_vars[ METAKEY_STRIPE_SYNC_TRACKING_INFO ] ) ) {
            $csStripeGw = WC()->payment_gateways->payment_gateways()['lazy_stripe'];
            $trackingSyncPlugin = $csStripeGw->get_option('sync_tracking_plugin');

            $metaQuery = [
                'relation' => 'AND',
                [
                    'key'   => METAKEY_STRIPE_SYNC_TRACKING_INFO,
                    'value' => esc_attr( $query_vars[ METAKEY_STRIPE_SYNC_TRACKING_INFO ] ),
                ],
                [
                    'relation' => 'OR',
                    [
                        'key'   => METAKEY_LAZY_STRIPE_CAPTURED,
                        'compare' => 'NOT EXISTS'
                    ],
                    [
                        'key'   => METAKEY_LAZY_STRIPE_CAPTURED,
                        'value' => 'true'
                    ]
                ]
            ];

            switch ( $trackingSyncPlugin ) {
                case OPT_CS_STRIPE_TRACKING_SYNC_PLUGIN_ORDERS_TRACKING:
                    break;
                case OPT_CS_STRIPE_TRACKING_SYNC_PLUGIN_DIANXIAOMI:
                    $metaQuery[] = [
                        'key'     => '_dianxiaomi_tracking_provider_name',
                        'compare' => '!=',
                        'value'   => null,
                    ];
                    $metaQuery[] = [
                        'key'     => '_dianxiaomi_tracking_number',
                        'compare' => '!=',
                        'value'   => null,
                    ];
                    break;
                case OPT_CS_STRIPE_TRACKING_SYNC_PLUGIN_ADVANCED_SHIPMENT_TRACKING:
                default:
                    $metaQuery[] = [
                        'key'     => '_wc_shipment_tracking_items',
                        'compare' => '!=',
                        'value'   => null,
                    ];
                    break;

            }
            $query['meta_query'][] = $metaQuery;
        }

        return $query;
    }

    function lazy_gateway_stripe_action()
    {
        switch ($_POST['command']) {
            case 'changeRotationMethod':
                $this->changeRotationMethod();
                break;
            case 'addNewProxy':
                $this->addNewProxy();
                break;
            case 'deleteProxy':
                $this->deleteProxy();
                break;
            case 'activateProxy':
                $this->activateProxy();
                break;
            case 'moveToUnusedProxies':
                $this->moveToUnusedProxies();
                break;
            case 'saveProxies':
                $this->saveProxies();
                break;
            case 'moveBackProxies':
                $this->moveBackProxies();
                break;
            case 'syncTrackingInfoStripe':
                syncTrackingInfoStripe();
                break;
            case 'changeConnectionMode':
                $this->changeConnectionMode();
                break;
            case 'saveEndpointSettings':
                $this->saveEndpointSettings();
                break;
            case 'getEndpointRemainingAmount':
                if (isCsStripeEnableEndpointMode()) {
                    $remainingAmount = csStripeEndpointGetAmountRemaining();
                    echo json_encode([
                        'value' => $remainingAmount == -1 ? 'Unlimited' : (number_format($remainingAmount) . ' (USD)')
                    ]);
                } else {
                    echo json_encode(['value' => 'None']);
                }
                break;
            default:
                break;
        }

        wp_die(); // this is required to terminate immediately and return a proper response
    }

    function changeConnectionMode() {
        $connectionMode = $_POST["connectionMode"];
        $isSuccess1 = update_option(OPT_LAZY_STRIPE_CONNECTION_MODE, $connectionMode, true);
        echo json_encode(["success" => $isSuccess1]);
    }

    function saveEndpointSettings()
    {
        $endpointToken = $_POST["endpointToken"];
        $endpointSecret = $_POST["endpointSecret"];
        update_option(OPT_CS_STRIPE_ENDPOINT_TOKEN, $endpointToken, true);
        update_option(OPT_CS_STRIPE_ENDPOINT_SECRET, $endpointSecret, true);
        echo json_encode(["success" => true]);
    }

    function changeRotationMethod()
    {
        $isSuccess = update_option(OPT_LAZY_STRIPE_ROTATION_METHOD, $_POST['rotationMethod'], true);
        echo json_encode([
            'success' => $isSuccess
        ]);
    }

    function activateProxy()
    {
        $rotationMethod = $_POST["rotationMethod"];
        $proxyID = $_POST["proxyID"];

        $proxies = get_option(Opt_Lazy_Stripe_Proxies, []);
        foreach ($proxies as $proxy) {
            if ($proxy["id"] == $proxyID) {
                // Active
                update_option(Opt_Lazy_Stripe_Activated_Proxy, $proxy, true);
                if ($rotationMethod === LAZY_STRIPE_BY_TIME) {
                    update_option(OPT_LAZY_STRIPE_CURRENT_ROTATION_VALUE, time(), true);
                }
                logStripeRotation($rotationMethod, $proxy, "Force");
                echo json_encode([
                    'success' => true
                ]);
                return;
            }
        }
        echo json_encode([
            'success' => false
        ]);
    }

    function deleteProxy()
    {
        $deleteProxyIds = $_POST["deleteProxyIds"];
        $proxies = get_option(OPT_LAZY_STRIPE_UNUSED_PROXIES, []);
        foreach ($proxies as $key => $proxy) {
            if (in_array($proxy['id'], $deleteProxyIds)) {
                unset($proxies[$key]);
            }
        }
        $isSuccess = update_option(OPT_LAZY_STRIPE_UNUSED_PROXIES, array_values($proxies), true);
        echo json_encode([
            'success' => $isSuccess
        ]);
    }

    function addNewProxy()
    {
        $rotationMethod = $_POST["rotationMethod"];
        $proxyUrl = $_POST["proxyUrl"];
        $rotationValue = $_POST["rotationValue"];

        // Get current proxies
        $proxies = get_option(Opt_Lazy_Stripe_Proxies, []);
        if (empty($proxies)) {
            $proxies = [];
        }
        // Add the new one
        $proxy = [
            'id' => uniqid(),
            'url' => $proxyUrl,
            'paid_amount' => 0
        ];
        if ($rotationMethod === LAZY_STRIPE_BY_TIME) {
            $proxy['timestamp'] = $rotationValue;
            $proxy['amount'] = 0;
        } else if ($rotationMethod === LAZY_STRIPE_BY_AMOUNT) {
            $proxy['timestamp'] = 0;
            $proxy['amount'] = $rotationValue;
        }
        $proxies[] = $proxy;
        // Save
        $isSuccess = update_option(Opt_Lazy_Stripe_Proxies, $proxies, true);

        $activatedProxy = get_option(Opt_Lazy_Stripe_Activated_Proxy, null);
        if (empty($activatedProxy)) {
            update_option(Opt_Lazy_Stripe_Activated_Proxy, $proxies[0], true);
            update_option(OPT_LAZY_STRIPE_CURRENT_ROTATION_VALUE, time(), true);
            update_option(OPT_LAZY_STRIPE_ROTATION_METHOD, $rotationMethod, true);
        }

        echo json_encode([
            'success' => $isSuccess,
            'addedProxy' => $proxy
        ]);
    }

    function moveToUnusedProxies()
    {
        $proxyIds = $_POST["proxyIds"];

        $proxies = get_option(Opt_Lazy_Stripe_Proxies, []);
        if (empty($proxies)) {
            $proxies = [];
        }
        $unusedProxies = get_option(OPT_LAZY_STRIPE_UNUSED_PROXIES, []);
        if (empty($unusedProxies)) {
            $unusedProxies = [];
        }
        $activatedProxy = get_option(Opt_Lazy_Stripe_Activated_Proxy, null);
        if (isset($activatedProxy) && in_array($activatedProxy['id'], $proxyIds)) {
            echo json_encode([
                "success" => false,
                "error" => "Can't move activated proxy to unused list!"
            ]);
            return;
        }
        foreach ($proxies as $key => $proxy) {
            if (in_array($proxy['id'], $proxyIds)) {
                $unusedProxies[] = $proxy;
                unset($proxies[$key]);
            }
        }
        $isSuccess1 = update_option(Opt_Lazy_Stripe_Proxies, array_values($proxies), true);
        $isSuccess2 = update_option(OPT_LAZY_STRIPE_UNUSED_PROXIES, $unusedProxies, true);
        echo json_encode([
            "success" => $isSuccess1 && $isSuccess2
        ]);

    }

    function saveProxies()
    {
        $rotationMethod = $_POST["rotationMethod"];
        $newProxies = $_POST["proxies"];

        $proxies = get_option(Opt_Lazy_Stripe_Proxies, []);
        $activatedProxy = get_option(Opt_Lazy_Stripe_Activated_Proxy, null);
        foreach ($proxies as $key => $proxy) {
            if ($proxy['id'] !== $newProxies[$key]['id']) {
                continue;
            }
            $proxies[$key]['url'] = $newProxies[$key]['url'];
            $proxies[$key]['timestamp'] = $rotationMethod === LAZY_STRIPE_BY_TIME ? $newProxies[$key]['rotationValue'] : $proxies[$key]['timestamp'];
            $proxies[$key]['amount'] = $rotationMethod === LAZY_STRIPE_BY_AMOUNT ? $newProxies[$key]['rotationValue'] : $proxies[$key]['amount'];

            // Update activated proxy
            if (isset($activatedProxy) && $activatedProxy['id'] === $proxy['id']) {
                update_option(Opt_Lazy_Stripe_Activated_Proxy, $proxies[$key], true);
            }
        }
        update_option(Opt_Lazy_Stripe_Proxies, $proxies, true);
        echo json_encode([
            "success" => true
        ]);
    }

    function moveBackProxies()
    {
        $moveBackProxyIds = $_POST["moveBackProxyIds"];

        $proxies = get_option(Opt_Lazy_Stripe_Proxies, []);
        $needActiveFirstProxy = false;
        if (count($proxies) == 0) {
            $needActiveFirstProxy = true;
        }
        $unusedProxies = get_option(OPT_LAZY_STRIPE_UNUSED_PROXIES, []);
        foreach ($unusedProxies as $key => $proxy) {
            if (in_array($proxy['id'], $moveBackProxyIds)) {
                update_option('CS_OPTION_SHIELD_STRIPE_ACCOUNT_CHARGE_STATUS' . $proxy['id'], null);
                update_option('CS_OPTION_SHIELD_STRIPE_ACCOUNT_CHARGE_STATUS_LAST_UPDATE_AT' . $proxy['id'], null);
                $proxies[] = $proxy;
                unset($unusedProxies[$key]);
            }
        }
        $isSuccess1 = update_option(Opt_Lazy_Stripe_Proxies, $proxies, true);
        $isSuccess2 = update_option(OPT_LAZY_STRIPE_UNUSED_PROXIES, array_values($unusedProxies), true);
        if ($needActiveFirstProxy) {
            update_option( Opt_Lazy_Stripe_Activated_Proxy, isset($proxies[0]) ? $proxies[0] : null, true );
        }
        echo json_encode(["success" => $isSuccess1 && $isSuccess2]);
    }

    function countOrderNeedSyncStripe() {
    global $wpdb;

    $csStripeGw = WC()->payment_gateways->payment_gateways()['lazy_stripe'];
    $trackingSyncPlugin = $csStripeGw->get_option('sync_tracking_plugin');

    switch ( $trackingSyncPlugin ) {
        case OPT_CS_STRIPE_TRACKING_SYNC_PLUGIN_ADVANCED_SHIPMENT_TRACKING:
            return $wpdb->get_var( $wpdb->prepare( "
                SELECT COUNT(DISTINCT(posts.id)) FROM {$wpdb->prefix}posts AS posts
                LEFT JOIN {$wpdb->prefix}postmeta AS post_meta1 ON posts.id = post_meta1.post_id AND post_meta1.meta_key = %s
                LEFT JOIN {$wpdb->prefix}postmeta AS post_meta2 ON posts.id = post_meta2.post_id AND post_meta2.meta_key = '_wc_shipment_tracking_items'
                LEFT JOIN {$wpdb->prefix}postmeta AS post_meta3 ON posts.id = post_meta3.post_id AND post_meta3.meta_key = %s
                WHERE posts.post_type = 'shop_order' AND post_meta1.meta_value = %d AND post_meta2.meta_value IS NOT NULL AND (post_meta3.meta_value IS NULL OR post_meta3.meta_value = 'true');
            ", METAKEY_STRIPE_SYNC_TRACKING_INFO , METAKEY_LAZY_STRIPE_CAPTURED, OPT_CS_STRIPE_NOT_SYNCED) );

        case OPT_CS_STRIPE_TRACKING_SYNC_PLUGIN_ORDERS_TRACKING:
            return $wpdb->get_var( $wpdb->prepare( "
                SELECT COUNT(DISTINCT(posts.id)) FROM {$wpdb->prefix}posts AS posts
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
            ", METAKEY_STRIPE_SYNC_TRACKING_INFO, METAKEY_LAZY_STRIPE_CAPTURED, OPT_CS_STRIPE_NOT_SYNCED ) );

        case OPT_CS_STRIPE_TRACKING_SYNC_PLUGIN_DIANXIAOMI:
            return $wpdb->get_var( $wpdb->prepare( "
                SELECT COUNT(DISTINCT(posts.id)) FROM {$wpdb->prefix}posts AS posts
                LEFT JOIN {$wpdb->prefix}postmeta AS post_meta1 ON posts.id = post_meta1.post_id AND post_meta1.meta_key = %s
                LEFT JOIN {$wpdb->prefix}postmeta AS post_meta2 ON posts.id = post_meta2.post_id AND post_meta2.meta_key = '_dianxiaomi_tracking_provider_name'
                LEFT JOIN {$wpdb->prefix}postmeta AS post_meta3 ON posts.id = post_meta3.post_id AND post_meta3.meta_key = %s
                LEFT JOIN {$wpdb->prefix}postmeta AS post_meta4 ON posts.id = post_meta4.post_id AND post_meta4.meta_key = '_dianxiaomi_tracking_number'
                WHERE posts.post_type = 'shop_order' AND post_meta1.meta_value = %d AND post_meta2.meta_value IS NOT NULL 
                    AND (post_meta3.meta_value IS NULL OR post_meta3.meta_value = 'true')
                    AND post_meta4.meta_value IS NOT NULL;
            ", METAKEY_STRIPE_SYNC_TRACKING_INFO , METAKEY_LAZY_STRIPE_CAPTURED, OPT_CS_STRIPE_NOT_SYNCED) );

    }
}

    /**
     * Lazy Stripe Gateway
     */

    function lazy_page_init()
    {
        $connectionMode = get_option(OPT_LAZY_STRIPE_CONNECTION_MODE, OPT_CS_STRIPE_CONNECTION_MODE_SHIELD_DOMAINS);
        $rotationMethod = get_option(OPT_LAZY_STRIPE_ROTATION_METHOD, LAZY_STRIPE_BY_TIME);
        if (empty($rotationMethod)) {
            $rotationMethod = LAZY_STRIPE_BY_TIME;
            update_option(OPT_LAZY_STRIPE_ROTATION_METHOD, LAZY_STRIPE_BY_TIME, true);
        }
        $endpointToken = get_option(OPT_CS_STRIPE_ENDPOINT_TOKEN, null);
        $endpointSecret = get_option(OPT_CS_STRIPE_ENDPOINT_SECRET, null);
        $proxies = get_option(Opt_Lazy_Stripe_Proxies, []);
        $unusedProxies = get_option(OPT_LAZY_STRIPE_UNUSED_PROXIES, []);
        $activatedProxy = get_option(Opt_Lazy_Stripe_Activated_Proxy, null);
        $countOrderNeedSync = countOrderNeedSyncStripe();

        $currency = get_woocommerce_currency();
        ?>
        <style>
            .by-time {
            <?= $rotationMethod !== LAZY_STRIPE_BY_TIME ? 'display: none' : '' ?>;
            }

            .by-amount {
            <?= $rotationMethod !== LAZY_STRIPE_BY_AMOUNT ? 'display: none' : '' ?>;
            }
        </style>
        <br/>
        <div class="container lazy-stripe-settings">
            <div class="lazy-settings-hero"><h3>CardsShield Stripe</h3></div>
            <br/>
            <section class="lazy-settings-card lazy-sync-card"><div class="lazy-section-heading"><h5>Sync tracking info</h5></div>
            <div class="sync-tracking-info">
                <button type="button" id="sync-tracking-info-btn-stripe" class="btn btn-primary">
                    <span id="sync-spinner" class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                    Sync tracking info
                </button>
                <div class="sync-info">Unsynced orders: <?= $countOrderNeedSync ?></div>
                <input id="sync-count-stripe" type="hidden" value="<?= $countOrderNeedSync ?>"/>
            </div>
            <hr style="border-top: 1px solid #333"/>
            </section><section class="lazy-settings-card lazy-connection-card"><div class="lazy-section-heading"><h5>Connection mode</h5></div>
            <div class="row">
                <div class="col-sm">
                    <div class="form-group rotation-method-wrapper">
                        <div class="custom-control custom-radio">
                            <input type="radio" id="connectionModeStripe1" name="connectionModeStripe"
                                   value="<?= OPT_CS_STRIPE_CONNECTION_MODE_SHIELD_DOMAINS ?>"
                                   class="custom-control-input" <?= $connectionMode === OPT_CS_STRIPE_CONNECTION_MODE_SHIELD_DOMAINS ? 'checked' : '' ?>>
                            <label class="custom-control-label" for="connectionModeStripe1">Shield domains</label>
                        </div>


                        <div class="custom-control custom-radio">
                            <input type="radio" id="connectionModeStripe2" name="connectionModeStripe"
                                   value="<?= OPT_CS_STRIPE_CONNECTION_MODE_ENDPOINT_TOKEN ?>"
                                   class="custom-control-input" <?= $connectionMode === OPT_CS_STRIPE_CONNECTION_MODE_ENDPOINT_TOKEN ? 'checked' : '' ?>>
                            <label class="custom-control-label" for="connectionModeStripe2">Endpoint token</label>
                        </div>

                    </div>
                </div>
            </div>
            <div id="connection_mode_shield_domains_area" class="lazy-settings-panel"
                 style="<?= $connectionMode == OPT_CS_STRIPE_CONNECTION_MODE_SHIELD_DOMAINS ? '' : 'display: none;' ?>">
                <hr style="border-top: 1px solid #333"/>
                <div class="lazy-panel-heading"><h5>Rotation settings</h5><span class="lazy-status-pill">Direct mode</span></div>
                <div class="row">
                    <div class="col-sm">
                        <div class="form-group form-inline rotation-method-wrapper">
                            <label class="rotation-method-label" style="justify-content: left" for="rotation-type">Rotation
                                method: </label>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="rotationMethod" id="rotationByTime"
                                       value="by_time" <?= $rotationMethod === LAZY_STRIPE_BY_TIME ? 'checked' : '' ?> >
                                <label class="form-check-label" for="rotationByTime">Time</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="rotationMethod" id="rotationByAmount"
                                       value="by_amount" <?= $rotationMethod === LAZY_STRIPE_BY_AMOUNT ? 'checked' : '' ?> >
                                <label class="form-check-label" for="rotationByAmount">Amount (per day)</label>
                            </div>
                            <div class="by-amount" style="font-size: 0.85rem; color: gray; width: 100%;">
                                *The gateway will not show if cannot affort order total amount.
                            </div>
                        </div>

                    </div>
                </div>
                <div class="row">
                    <div class="col-sm">
                        <table class="table table-proxy table-hover table-borderless">
                            <thead>
                            <tr>
                                <th scope="col" class="checkbox-col"></th>
                                <th scope="col" class="proxy-url-col">Shield URL</th>
                                <th scope="col" class="rotation-value-col">
                                    <span class="by-time">Time(min)</span>
                                    <span class="by-amount">Amount(<?= $currency ?>/day)</span>
                                </th>
                                <th scope="col" class="control-button-col"></th>
                            </tr>
                            <tr>
                                <th></th>
                                <th>
                                    <input type="text" class="form-control proxy-url" id="new-proxy-url">
                                </th>
                                <th>
                                    <input type="number" class="form-control proxy-rotation-value"
                                           id="new-rotation-value">
                                </th>
                                <th>
                                    <button id="btn-add-proxy" class="btn btn-info" type="button">Add</button>
                                </th>
                            </tr>
                            <tr style="border-top: 1px solid rgba(0,0,0,.1)">
                                <th></th>
                                <th>
                                    <b>Rotation List</b>
                                </th>
                                <th></th>
                                <th class="today-paid-amount"><span class="by-amount">Rev.</span></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php
                            foreach ($proxies as $proxy) {
                                $proxy["rotationValue"] = $rotationMethod === LAZY_STRIPE_BY_TIME ? $proxy["timestamp"] : $proxy["amount"];
                                $className = isset($activatedProxy['id']) && $activatedProxy['id'] === $proxy['id'] ? 'activated-proxy' : '';
                                echo "
                                <tr class='proxy {$className}'>
                                    <td>
                                        <input type='checkbox' class='form-control proxy-id' value='{$proxy["id"]}'>
                                    </td>
                                    <td>
                                        <input type='text' class='form-control proxy-url' value='{$proxy["url"]}'>
                                    </td>
                                    <td>
                                        <input type='number' class='form-control proxy-rotation-value' value='{$proxy["rotationValue"]}'>
                                    </td>
                                    <td class='today-paid-amount'>
                                        <span class='by-amount'>{$proxy['paid_amount']}</span>
                                    </td>
                                </tr>
                                ";
                            }
                            ?>
                            </tbody>
                        </table>
                        <div class="control-button">
                            <button id="btn-save" class="btn btn-success mr-4" type="button">Save all</button>
                            <button id="btn-force-active" class="btn btn-primary mr-4" type="button">Force active
                            </button>
                            <button id="btn-move-unused" class="btn btn-danger" type="button">Move to unused</button>
                        </div>

                        <table class="table table-unused table-hover table-borderless">
                            <thead>
                            <tr style="border-top: 1px solid rgba(0,0,0,.1)">
                                <th scope="col" class="checkbox-col"></th>
                                <th scope="col" class="proxy-url-col">
                                    <b>Unused List</b>
                                </th>
                                <th scope="col" class="rotation-value-col">
                                <th scope="col" class="control-button-col"></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php
                            foreach ($unusedProxies as $proxy) {
                                $proxy["rotationValue"] = $rotationMethod === LAZY_STRIPE_BY_TIME ? $proxy["timestamp"] : $proxy["amount"];
                                echo "
                                <tr class='proxy'>
                                    <td>
                                        <input type='checkbox' class='form-control proxy-id' value='{$proxy["id"]}'>
                                    </td>
                                    <td>
                                        <input type='text' class='form-control proxy-url' value='{$proxy["url"]}'>
                                    </td>
                                    <td>
                                        <input type='number' class='form-control proxy-rotation-value' value='{$proxy["rotationValue"]}'>
                                    </td>
                                    <td></td>
                                </tr>
                            ";
                            }
                            ?>
                            </tbody>
                        </table>
                        <div class="control-button">
                            <button id="btn-move-back" class="btn btn-primary mr-4" type="button">Move back</button>
                            <button id="btn-delete" class="btn btn-danger" type="button">Delete</button>
                        </div>

                    </div>
                </div>
            </div>
            <div id="connection_mode_endpoint_token_area" class="lazy-settings-panel lazy-endpoint-panel"
                 style="<?= $connectionMode == OPT_CS_STRIPE_CONNECTION_MODE_ENDPOINT_TOKEN ? '' : 'display: none;' ?>">
                <hr style="border-top: 1px solid #333"/>
                <div class="lazy-panel-heading"><h5>Endpoint settings</h5><span class="lazy-status-pill lazy-status-endpoint">Manager mode</span></div>
                <div class="row">
                    <div class="col-sm">
                        <div class="form-group rotation-method-wrapper">
                            <div class="row form-group">
                                <div class="col-md-3">
                                    <label>Stripe token</label>
                                </div>
                                <div class="col-md-9">
                                    <input class="form-control" name="endpointToken" value="<?= esc_attr($endpointToken) ?>">
                                </div>
                            </div>
                            <div class="row form-group">
                                <div class="col-md-3">
                                    <label>Secret Key</label>
                                </div>
                                <div class="col-md-9">
                                    <input type="password" class="form-control" name="endpointSecret"
                                           value="<?= esc_attr($endpointSecret) ?>">
                                </div>
                            </div>
                            <div class="row form-group">
                                <div class="col-md-3">
                                    <label>Remaining balance today</label>
                                </div>
                                <div class="col-md-9">
                                    <label id="stripe-ep-amount-remain">None</label>
                                </div>
                            </div>
                            <div class="control-button">
                                <button id="btn-save-endpoint-settings" class="btn btn-primary mr-4" type="button">
                                    Save
                                </button>
                                <button id="btn-save-endpoint-cancel" class="btn btn-danger" type="button">Cancel
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section></div>
        <?php
    }
}

new Lazy_Stripe_Paygate_Option();
