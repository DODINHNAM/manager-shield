jQuery(document).ready(function ($) {
    if ($('#cs_pay_for_order_page').length) {
        var lazy_checkout_form = $('#order_review');
    } else {
        var lazy_checkout_form = $('form.checkout');
    }
    var OPT_CS_PAYPAL_SETTING_CHECKOUT = 'PAYPAL_CHECKOUT';

    lazy_checkout_form.on('checkout_place_order', function () {
        if ($('input[name="payment_method"]:checked').val() === 'lazy_paypal') {
            var paypalPaymentOrderIdEl = lazy_checkout_form.find('[name="lazy-paypal-payment-order-id"]');
            if (validateFormCheckoutPaypal() && paypalPaymentOrderIdEl.length && paypalPaymentOrderIdEl.val().length == 0) {
                csPaypalClientLog({
                    'note': 'can not submit case [name="lazy-paypal-payment-order-id"] not have data',
                    'email': lazyGetUserField('email')
                });
                if(confirm('An error occurred. Please try again!')) {
                    location.reload();
                }
                return false;
            }
            setTimeout(function () {
                if (!window.lazy_paypal_checkout_error) {
                    $('.blockUI').hide();
                    $('#cs-pp-loader').show();
                    setTimeout((function () {
                        $('#cs-pp-loader').hide();
                    }), 30000);
                }
            }, 1000)
        }
    });

    $(document).on('checkout_error', function () {
        if ($('input[name="payment_method"]:checked').val() == 'lazy_paypal') {
            $('#cs-pp-loader').hide();
            $('#cs-pp-loader-credit').hide();
            window.lazy_paypal_checkout_error = true;
        }
    })

    setInterval(function () {
        handleShowHidePaypalButton();
    }, 200) // fix monlesacx.com

    $('body').on('updated_checkout', function () {
        handleShowHidePaypalButton();
    });

    $(document).on('payment_method_selected', function () {
        handleShowHidePaypalButton();
    })

    if (window.addEventListener) {
        window.addEventListener("message", listenerPaypal);
    } else {
        window.attachEvent("onmessage", listenerPaypal);
    }

    function handleShowHidePaypalButton() {
        if ($('input[name="payment_method"]:checked').val() == 'lazy_paypal' && $('#lazy-paypal-button-setting').data('value') === OPT_CS_PAYPAL_SETTING_CHECKOUT) {
            $('#lazy-paypal-credit-form-container').show();
            $('#place_order').addClass('important-hide')
        } else {
            $('#lazy-paypal-credit-form-container').hide();
            $('#place_order').removeClass('important-hide')
        }
    }

    function listenerPaypal(event) {
        if (event.data === "lazy-paypalRequestFromBlacklist") {
            setInterval(function () {
                $('#payment-paypal-area').remove();
                $('.cs_pp_element').remove();
                $('.wc_payment_method.payment_method_lazy_paypal').hide();
            }, 100)
        }
        if (event.data === "lazy-paypalOpenCreditForm") {
            validateFormCheckoutPaypal();
            csPaypalClientLog({
                'note': 'lazy-paypalOpenCreditForm',
                'email': lazyGetUserField('email'),
                'validateFormCheckoutPaypal': window.cs_validateFormCheckoutPaypal_debug_string
            });
            $('#payment-paypal-area').attr('height', 400);
        }
        if (event.data === "lazy-paypalOpenCreditFormReject") {
            if (!validateFormCheckoutPaypal()) {
                var msg = '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout"><div role="alert"><ul class="woocommerce-error" tabindex="-1">';
                window.cs_validateFormCheckoutPaypal_msg.forEach(function (value, index) {
                    var infoMsg = 'is a required field.'
                    if (value.custom_msg && value.custom_msg !== '') {
                        infoMsg = value.custom_msg;
                    }
                    msg += '<li data-id="' + value.id + '"><a href="#' + value.id + '"><strong>' + value.field_label + '</strong> ' + infoMsg + '</a></li>';
                })
                msg += '</ul></div></div>';
                var existsNotice = $('.woocommerce-NoticeGroup-updateOrderReview, .woocommerce-NoticeGroup-checkout');
                if (existsNotice.length) {
                    existsNotice.remove();
                }
                lazy_checkout_form.prepend(msg);
                lazy_checkout_form.find('.input-text, select, input:checkbox').trigger('validate').trigger('blur');
                var scrollElement = $('.woocommerce-NoticeGroup-updateOrderReview, .woocommerce-NoticeGroup-checkout');
                if (!scrollElement.length) {
                    scrollElement = lazy_checkout_form;
                }
                $.scroll_to_notices(scrollElement);
                csPaypalClientLog({
                    'note': 'lazy-paypalOpenCreditFormReject',
                    'email': lazyGetUserField('email'),
                    'validateFormCheckoutPaypal': window.cs_validateFormCheckoutPaypal_debug_string
                });
            }

            // lazy_checkout_form.submit();
        }
        if (event.data === "lazy-paypalCloseCreditForm") {
            $('#payment-paypal-area').attr('height', 120);
        }
        if (event.data === "lazy-paypalMakeFullIframeCreditForm") {
            $('#payment-paypal-area').addClass('full_screen_iframe_paypal_checkout')
        }
        if (event.data === "lazy-paypalMakeIframeCreditFormNormal") {
            $('#payment-paypal-area').removeClass('full_screen_iframe_paypal_checkout')
        }
        if ((typeof event.data === 'object') && event.data.name === 'lazy-paypalBodyResizeCreditForm') {
            if (event.data.value >= 130) {
                $('#payment-paypal-area').attr('height', event.data.value + 10);
            }
        }
        if ((typeof event.data === 'object') && event.data.name === 'lazy-paypalOpenCreditFormFail') {
            csPaypalClientLog({
                'note': 'lazy-paypalOpenCreditFormFail',
                'email': lazyGetUserField('email')
            });
            checkout_error_paypal(event.data.value)
        }
        if ((typeof event.data === 'object') && event.data.name === 'lazy-paypalOpenCreditFormError') {
            $.ajax({
                url: '/?lazy-paypal-button-create-order=1',
                method: 'POST',
                data: {
                    'cs_order': event.data.value,
                    'current_proxy_id': $('#lazy_express_paypal_current_proxy_id').data('value'),
                    'current_proxy_url': $('#lazy_express_paypal_current_proxy_url').data('value')
                }
            })
        }
        if ((typeof event.data === 'object') && event.data.name === 'lazy-paypalApprovedOrder') {
            var orderId = event.data.value.order_id;
            csPaypalClientLog({
                'note': 'lazy-paypalApprovedOrder',
                'pp_order_id': orderId,
                'email': lazyGetUserField('email')
            });
            lazy_checkout_form.find('[name="lazy-paypal-payment-order-id"]').val(orderId);
            lazy_checkout_form.removeClass('processing').unblock();
            lazy_checkout_form.submit();
            if (validateFormCheckoutPaypal()) {
                setTimeout(function () {
                    if (!window.lazy_paypal_checkout_error) {
                        $('.blockUI').hide();
                        $('#cs-pp-loader-credit').show();
                        setTimeout((function () {
                            $('#cs-pp-loader-credit').hide();
                        }), 30000);
                    }
                }, 1000)
            }
        }
    }

    if ($('#lazy_enable_paypal_card_payment').length) {
        setInterval(function () {
            if ($('input[name="payment_method"]:checked').val() == 'lazy_paypal'
                && $('#lazy-paypal-button-setting').data('value') === OPT_CS_PAYPAL_SETTING_CHECKOUT
                && $('#payment-paypal-area')[0]) {
                if (validateFormCheckoutPaypal()) {
                    var whitelistPostalCode = null;
                    var whitelistEmail = null;
                    var whitelistState = null;
                    var whitelistCity = null;
                    if (typeof $('#billing_postcode').val() === 'string' && $('#billing_postcode').val().trim().length > 0) {
                        whitelistPostalCode = Sha1.hash($('#billing_postcode').val())
                    }
                    if (typeof $('#billing_email').val() === 'string' && $('#billing_email').val().trim().length > 0) {
                        whitelistEmail = Sha1.hash($('#billing_email').val())
                    }
                    if (typeof $('#billing_state').val() === 'string' && $('#billing_state').val().trim().length > 0) {
                        whitelistState = Sha1.hash($('#billing_state').val().toLowerCase())
                    }
                    if (typeof $('#billing_city').val() === 'string' && $('#billing_city').val().trim().length > 0) {
                        whitelistCity = Sha1.hash($('#billing_city').val().toLowerCase())
                    }
                    var merchantSite = $('#lazy_merchant_site_url').data('value');
                    if (merchantSite.endsWith("/")) {
                      merchantSite = merchantSite.slice(0, -1);
                    }
                    var shippingAddObj = null;
                    if ($('input[name="ship_to_different_address"]').is(':checked')) {
                        shippingAddObj = {
                            name: lazyGetUserFieldShipping('first_name') + ' ' + lazyGetUserFieldShipping('last_name'),
                            city: lazyGetUserFieldShipping('city'),
                            country: lazyGetUserFieldShipping('country'),
                            line1: lazyGetUserFieldShipping('address_1'),
                            line2: lazyGetUserFieldShipping('address_2'),
                            postal_code: lazyGetUserFieldShipping('postcode'),
                            state: lazyGetUserFieldShipping('state'),
                        }
                    }
                    $('#payment-paypal-area')[0].contentWindow.postMessage({
                        name: 'lazy-paypalSendOrderInfo',
                        value: {
                            whitelist_obj: {
                                merchant_site: Sha1.hash(merchantSite),
                                postal_code: whitelistPostalCode,
                                email: whitelistEmail,
                                state: whitelistState,
                                city: whitelistCity,
                            },
                            merchant_token: $('#lazy_merchant_site_encode').data('value'),
                            isNotSendAddress: $('#cs_not_send_bill_address_to_paypal').length,
                            purchase_units: window.lazy_paypal_checkout_purchase_units,
                            orderIntent: $('#lazy-paypal-order-intent').data('value'),
                            last_name: lazyGetUserField('last_name'),
                            first_name: lazyGetUserField('first_name'),
                            email: lazyGetUserField('email'),
                            address: {
                                city: lazyGetUserField('city'),
                                country: lazyGetUserField('country'),
                                line1: lazyGetUserField('address_1'),
                                line2: lazyGetUserField('address_2'),
                                postal_code: lazyGetUserField('postcode'),
                                state: lazyGetUserField('state'),
                            },
                            shipping_address: shippingAddObj,
                            phone: lazyGetUserField('phone'),
                        }
                    }, '*')
                } else {
                    $('#payment-paypal-area')[0].contentWindow.postMessage({
                        name: 'lazy-paypalSendOrderInfo',
                        value: null
                    }, '*')
                }
            }
        }, 100);
    }

    function checkFieldValidatedPaypal(target) {
        if (target.length === 0) {
            return true;
        }
        var targetId = target.attr('id');
        window.cs_validateFormCheckoutPaypal_debug_string[targetId] = {
            "1": target.closest('.form-row').length,
            "11": target.closest('.form-row').prop('outerHTML'),
            "2": typeof target.val(),
            "3": target.val(),
            "4": target.val()? target.val().length : 0,
            "5": target.is(':hidden'),
            "6": target.prop('outerHTML'),
        }
        if (target.is(':hidden')) {
            return true;
        }
        var isNotInvalid = !target.closest('.form-row').hasClass('woocommerce-invalid');
        var isNotEmpty = true;
        if (target.closest('.form-row').hasClass('validate-required')) {
            isNotEmpty = (typeof target.val() == 'string') ? target.val().length : false;
        }
        window.cs_validateFormCheckoutPaypal_debug_string[targetId]["isNotInvalid"] = isNotInvalid;
        window.cs_validateFormCheckoutPaypal_debug_string[targetId]["isNotEmpty"] = isNotEmpty;
        if (!isNotInvalid || !isNotEmpty) {
            var label_field = targetId;
            var custom_msg = null;
            if ($('#' + targetId + '_field label')) {
                label_field = $('#' + targetId + '_field label').text().replace(/\s*\*$/, '').trim();
            }
            if (targetId.includes('email') && target.val() && target.val() !== '') {
                custom_msg = 'is invalid.'
            }
            if (targetId.includes('postcode') && target.val() && target.val() !== '') {
                custom_msg = 'is invalid.'
            }
            if (targetId.includes('phone') && target.val() && target.val() !== '') {
                custom_msg = 'is invalid.'
            }
            window.cs_validateFormCheckoutPaypal_msg.push({
                'id': targetId,
                'field_label': label_field,
                'custom_msg': custom_msg
            })
        }
        return isNotInvalid && isNotEmpty;
    }

    function validateFormCheckoutPaypal() {
        var requiredFields = $('form.woocommerce-checkout .validate-required:visible :input');
        requiredFields.each((i, input) => {
            $(input).trigger('validate');
        });
        window.cs_validateFormCheckoutPaypal_debug_string = {};
        window.cs_validateFormCheckoutPaypal_msg = [];
        window.cs_validateFormCheckoutPaypal_debug_string["$('#shipping_city').val()"] = $('#shipping_city').val();
        if ($('#shipping_city').val()) {
            window.cs_validateFormCheckoutPaypal_debug_string["$('#shipping_city').val().toString()"] = $('#shipping_city').val().toString();
        }
        window.cs_validateFormCheckoutPaypal_debug_string["$('#shipping_postcode').val()"] = $('#shipping_postcode').val();
        if ($('#shipping_postcode').val()) {
            window.cs_validateFormCheckoutPaypal_debug_string["$('#shipping_postcode').val().toString()"] = $('#shipping_postcode').val().toString();
        }

        var valid = true;
        valid &&= checkFieldValidatedPaypal($('#billing_first_name'));
        valid &&= checkFieldValidatedPaypal($('#billing_last_name'));
        valid &&= checkFieldValidatedPaypal($('#billing_email'));
        valid &&= ($('#shipping_city').val() && $('#shipping_city').val().toString().length || checkFieldValidatedPaypal($('#billing_city')));
        valid &&= checkFieldValidatedPaypal($('#billing_country'));
        valid &&= ($('#shipping_postcode').val() && $('#shipping_postcode').val().toString().length || checkFieldValidatedPaypal($('#billing_postcode')));
        valid &&= checkFieldValidatedPaypal($('#billing_state'));
        valid &&= checkFieldValidatedPaypal($('#billing_address_1'));
        valid &&= checkFieldValidatedPaypal($('#billing_address_2'));
        valid &&= checkFieldValidatedPaypal($('#billing_phone'));
        valid &&= checkFieldValidatedPaypal($('#shipping_first_name'));
        valid &&= checkFieldValidatedPaypal($('#shipping_last_name'));
        valid &&= checkFieldValidatedPaypal($('#shipping_city'));
        valid &&= checkFieldValidatedPaypal($('#shipping_country'));
        valid &&= checkFieldValidatedPaypal($('#shipping_postcode'));
        valid &&= checkFieldValidatedPaypal($('#shipping_state'));
        valid &&= checkFieldValidatedPaypal($('#shipping_address_1'));
        valid &&= checkFieldValidatedPaypal($('#shipping_address_2'));
        return valid;
    }

    function checkout_error_paypal(error_message) {
        $('.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message').remove();
        lazy_checkout_form.prepend('<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout">' +
            '<ul class="woocommerce-error">' +
            '<li data-id="billing_last_name">' + error_message + '' +
            '</li>' +
            '</ul>' +
            '</div>'); // eslint-disable-line max-len
        lazy_checkout_form.removeClass('processing').unblock();
        lazy_checkout_form.find('.input-text, select, input:checkbox').trigger('validate').trigger('blur');
        var scrollElement = $('.woocommerce-NoticeGroup-updateOrderReview, .woocommerce-NoticeGroup-checkout');
        if (!scrollElement.length) {
            scrollElement = lazy_checkout_form;
        }
        $.scroll_to_notices(scrollElement);
        $(document.body).trigger('checkout_error', [error_message]);
    }

    function lazyGetUserField(fieldName) {
        if ($('#billing_' + fieldName).val() && $('#billing_' + fieldName).val().length > 0) {
            return $('#billing_' + fieldName).val();
        }
        return $('#shipping_' + fieldName).val()
    }
    
    function lazyGetUserFieldShipping(fieldName) {
        if ($('#shipping_' + fieldName).val() && $('#shipping_' + fieldName).val().length > 0) {
            return $('#shipping_' + fieldName).val();
        }
        return $('#billing_' + fieldName).val()
    }
    
    function csPaypalClientLog(data) {
        $.ajax({
            url: '/?lazy-paypal-note-debug=1',
            method: 'POST',
    contentType: 'application/json',
            data: JSON.stringify(data)
        })
    }
});
