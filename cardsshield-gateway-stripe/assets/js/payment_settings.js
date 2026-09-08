jQuery(function ($) {
    csStripeAdminSettingByPaymentMode($('#woocommerce_lazy_stripe_payment_mode').val());
    $('#woocommerce_lazy_stripe_payment_mode').on('change', function () {
        csStripeAdminSettingByPaymentMode($(this).val());
    })

    function csStripeAdminSettingByPaymentMode(type) {
        if (type === 'embedded') {
            $('#woocommerce_lazy_stripe_checkout_button_text').closest('tr').hide();
            $('#woocommerce_lazy_stripe_payment_option_desc').closest('tr').hide();
            $('#woocommerce_lazy_stripe_payment_button_color').closest('tr').hide();
            $('#woocommerce_lazy_stripe_payment_button_hover_color').closest('tr').hide();
            $('#woocommerce_lazy_stripe_product_title_setting').closest('tr').hide();
            $('#woocommerce_lazy_stripe_user_define_product_title').closest('tr').hide();
            $('#woocommerce_lazy_stripe_payment_notes').closest('tr').show();
            $('#woocommerce_lazy_stripe_do_not_send_address').closest('tr').hide();
        } else {
            $('#woocommerce_lazy_stripe_checkout_button_text').closest('tr').show();
            $('#woocommerce_lazy_stripe_payment_option_desc').closest('tr').show();
            $('#woocommerce_lazy_stripe_payment_button_color').closest('tr').show();
            $('#woocommerce_lazy_stripe_payment_button_hover_color').closest('tr').show();
            $('#woocommerce_lazy_stripe_product_title_setting').closest('tr').show();
            $('#woocommerce_lazy_stripe_user_define_product_title').closest('tr').show();
            $('#woocommerce_lazy_stripe_payment_notes').closest('tr').hide();
            $('#woocommerce_lazy_stripe_do_not_send_address').closest('tr').hide();
        }
    }

    csStripeAdminSettingByOverrideProductTitle($('#woocommerce_lazy_stripe_product_title_setting').val());
    $('#woocommerce_lazy_stripe_product_title_setting').on('change', function () {
        csStripeAdminSettingByOverrideProductTitle($(this).val());
    })

    function csStripeAdminSettingByOverrideProductTitle(type) {
        if (type === 'user_define') {
            $('#woocommerce_lazy_stripe_user_define_product_title').closest('tr').show();
            $('#woocommerce_lazy_stripe_random_product_title_list').closest('tr').show();
        } else {
            $('#woocommerce_lazy_stripe_user_define_product_title').closest('tr').hide();
            $('#woocommerce_lazy_stripe_random_product_title_list').closest('tr').hide();
        }
    }

    csStripeAdminSettingCheckoutButtonDesign($('#woocommerce_lazy_stripe_checkout_button_design').val());
    $('#woocommerce_lazy_stripe_checkout_button_design').on('change', function () {
        csStripeAdminSettingCheckoutButtonDesign($(this).val());
    })

    function csStripeAdminSettingCheckoutButtonDesign(type) {
        if (type === 'modern_design' || type === 'modern_design_2') {
            $('#woocommerce_lazy_stripe_checkout_button_text').closest('tr').show();
            $('#woocommerce_lazy_stripe_checkout_button_text_inherit').closest('tr').hide();
            $('#woocommerce_lazy_stripe_payment_button_color').closest('tr').show();
            $('#woocommerce_lazy_stripe_payment_button_hover_color').closest('tr').show();
        } else {
            $('#woocommerce_lazy_stripe_checkout_button_text').closest('tr').hide();
            $('#woocommerce_lazy_stripe_checkout_button_text_inherit').closest('tr').show();
            $('#woocommerce_lazy_stripe_payment_button_color').closest('tr').hide();
            $('#woocommerce_lazy_stripe_payment_button_hover_color').closest('tr').hide();
        }
    }
    
    $('#woocommerce_lazy_stripe_sslverify').closest('tr').hide();
    $('#woocommerce_lazy_stripe_custom_card_icon_css').closest('tr').hide();
    $('#stripe_advance_setting_toggle').click(function () {
        $('#woocommerce_lazy_stripe_sslverify').closest('tr').toggle();
        $('#woocommerce_lazy_stripe_custom_card_icon_css').closest('tr').toggle();
    })
});
