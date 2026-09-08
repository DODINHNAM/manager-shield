jQuery(document).ready(function ($) {
    csPaypalProductTitleSwitch($('#woocommerce_lazy_paypal_product_title_setting').val());
    $('#woocommerce_lazy_paypal_product_title_setting').on('change', function () {
        csPaypalProductTitleSwitch($(this).val());
    })

    function csPaypalProductTitleSwitch(type) {
        if (type === 'user_define') {
            $('#woocommerce_lazy_paypal_user_define_product_title').closest('tr').show();
            $('#woocommerce_lazy_paypal_random_product_title_list').closest('tr').show();
        } else {
            $('#woocommerce_lazy_paypal_user_define_product_title').closest('tr').hide();
            $('#woocommerce_lazy_paypal_random_product_title_list').closest('tr').hide();
        }
    }
    $('#woocommerce_lazy_paypal_sslverify').closest('tr').hide();
    $('#woocommerce_lazy_paypal_custom_card_icon_css').closest('tr').hide();
    $('#pp_advance_setting_toggle').click(function () {
        $('#woocommerce_lazy_paypal_sslverify').closest('tr').toggle();
        $('#woocommerce_lazy_paypal_custom_card_icon_css').closest('tr').toggle();
    })
})
