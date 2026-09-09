jQuery(function ($) {
    if ($('#lazy_stripe_pay_for_order_page').length) {
        var lazy_checkout_form = $('#order_review');
    } else {
        var lazy_checkout_form = $('form.checkout');
    }
    var oldPlaceOrderText = $('#place_order').text();
    $('body').on('updated_checkout', function () {
        handleUIStripePaymentCheckoutButton();
    });

    $(document).on('payment_method_selected', function () {
        handleUIStripePaymentCheckoutButton();
    })

    function handleUIStripePaymentCheckoutButton() {
        if ($('input[name="payment_method"]:checked').val() == 'lazy_stripe') {
            $('#place_order').text($('#cs-stripe-checkout-inherit-btn-text').data('value'))
        } else if (!/lazy_/.test($('input[name="payment_method"]:checked').val())) {
            $('#place_order').text(oldPlaceOrderText)
        }
    }
    
    function loadPaymentProcess() {
        setTimeout(function () {
            if (!window.lazy_stripe_checkout_error) {
                lazy_checkout_form.removeClass('processing').unblock();
                $('#cs-stripe-loader').show();
                setTimeout((function () {
                    $('#cs-stripe-loader').hide();
                }), 30000);
            }
        }, 1000)
    }

    $(document).on('checkout_error', function () {
        if ($('input[name="payment_method"]:checked').val() == 'lazy_stripe') {
            $('#cs-stripe-loader').hide();
            window.lazy_stripe_checkout_error = true;
        }
    })
    $('body').on('click', '#place_order,.link_checkout', function (e) {
        if ($('input[name="payment_method"]:checked').val() == 'lazy_stripe') {
            window.lazy_stripe_checkout_error = false;
            e.preventDefault();
            if ($('#cs-stripe-use-payment-hosted-inherit-design').length) {
                lazy_checkout_form.submit()
            } else if (validateFormCheckout()) {
                $('#payment-stripe-area')[0].contentWindow.postMessage({
                    name: 'lazy-submitFormStripe',
                    value: {
                        billing_details: {
                            name: stripeGetFormCheckoutVal('#billing_first_name') + ' ' + stripeGetFormCheckoutVal('#billing_last_name'),
                            phone: stripeGetFormCheckoutVal('#billing_phone'),
                            email: stripeGetFormCheckoutVal('#billing_email'),
                            address: {
                                city: stripeGetFormCheckoutVal('#billing_city'),
                                country: stripeGetFormCheckoutVal('#billing_country'),
                                line1: stripeGetFormCheckoutVal('#billing_address_1'),
                                line2: stripeGetFormCheckoutVal('#billing_address_2'),
                                postal_code: stripeGetFormCheckoutVal('#billing_postcode'),
                                state: stripeGetFormCheckoutVal('#billing_state'),
                            },
                        }
                    }
                }, '*')
            } else {
                lazy_checkout_form.submit()
            }
        }
    })
    if (lazy_checkout_form.find('[name="lazy-stripe-payment-method-id"]').length) {
        $(document.body).on('updated_checkout', function (data) {
            if (!window.loadedPaymentFormStripe && $('input[name="payment_method"]:checked').val() == 'lazy_stripe') {
                $('.woocommerce-checkout-payment').block({
                    message: null,
                    overlayCSS: {
                        background: '#fff',
                        opacity: 0.6
                    }
                });
            }
        });
    }
    /*
    event from proxy iframe
     */
    if (window.addEventListener) {
        window.addEventListener("message", listener);
    } else {
        window.attachEvent("onmessage", listener);
    }

    function blockOnSubmit(form) {
        var isBlocked = form.data('blockUI.isBlocked');

        if (1 !== isBlocked) {
            form.block({
                message: null,
                overlayCSS: {
                    background: '#fff',
                    opacity: 0.6
                }
            });
        }
    }

    $('body').on('updated_checkout', function () {
        handleShowHideStripeButton();
    });

    $(document).on('payment_method_selected', function () {
        handleShowHideStripeButton();
    })

    function handleShowHideStripeButton() {
        if ($('input[name="payment_method"]:checked').val() == 'lazy_stripe' && $('#cs-stripe-use-payment-hosted-modern-design').length) {
            $('#cs-stripe-button-container').show();
            $('#place_order').addClass('important-hide-stripe')
        } else {
            $('#cs-stripe-button-container').hide();
            $('#place_order').removeClass('important-hide-stripe')
        }
    }

    function listener(event) {
        var stripeFrames = [
            document.getElementById('payment-stripe-area'),
            document.getElementById('payment-area-stripe-to-confirm')
        ];
        var isStripeFrame = stripeFrames.some(function (frame) {
            return frame && event.source === frame.contentWindow;
        });
        if (!isStripeFrame) return;

         if (event.data === "lazy-stripeRequestFromBlacklist") {
            setInterval(function () {
                $('.cs_stripe_element').remove();
                $('.wc_payment_method.payment_method_lazy_stripe').hide();
                 window.loadedPaymentFormStripe = true;
                $('.woocommerce-checkout-payment').unblock();
            }, 100)
        }
        if (event.data === "lazy-startSubmitPaymentStripe") {
            blockOnSubmit(lazy_checkout_form);
            lazy_checkout_form.addClass('processing')
        }
        if (event.data === "lazy-endSubmitPaymentStripe") {
            lazy_checkout_form.removeClass('processing').unblock();
        }
        if (event.data === 'lazy-loadedPaymentFormStripe') {
            window.loadedPaymentFormStripe = true;
            $('.woocommerce-checkout-payment').unblock();
        }
        if (event.data === 'lazy-paymentFormCompletedStripe') {
            window.paymentFormCompletedStripe = true;
        }

        if (event.data === 'lazy-paymentFormFailStripe') {
            window.paymentFormCompletedStripe = false;
        }
        if ((typeof event.data === 'object') && event.data.name === 'lazy-errorSubmitPaymentStripe') {
            // console.log(event.data); 
            lazy_checkout_form.removeClass('processing').unblock();
            checkout_error('We cannot process your payment right now [' + event.data.value + ']');
        }
        if ((typeof event.data === 'object') && event.data.name === 'lazy-stripeBodyResizeCreditForm') {
            $('#payment-stripe-area').attr('height', event.data.value + 30);
        }
        if ((typeof event.data === 'object') && event.data.name === 'lazy-paymentMethodIdStripe') {
            var paymentMethodId = event.data.value;
            lazy_checkout_form.find('[name="lazy-stripe-payment-method-id"]').val(paymentMethodId);
            lazy_checkout_form.removeClass('processing').unblock();
            if ($('#lazy_stripe_pay_for_order_page').length) {
                $.ajax({
                    url: lazy_checkout_form.attr('action') || window.location.href,
                    type: lazy_checkout_form.attr('method'),
                    data: lazy_checkout_form.serialize(),
                    success: function (data) {
                        var res = JSON.parse(data);
                        window.location.hash = res.redirect;
                    },
                    error: function (jXHR, textStatus, errorThrown) {
                        alert(errorThrown);
                    }
                });
            } else {
                lazy_checkout_form.submit();
            }

            if (validateFormCheckout()) {
                loadPaymentProcess();
            }
        }
        if ((typeof event.data === 'object') && event.data.name === 'lazy-confirmPaymentIntentStripe') {
            if (event.data.value === 'success') {
                loadPaymentProcess();
                $('#payment-area-stripe-to-confirm').hide();
                window.location.href = '/?lazy_stripe_return_result=1&order_id=' + window.lazy_stripe_order_id; //relative to domain
            } else {
                var error = event.data.error;
                if (error) {
                    $.post('/?wc-ajax=cs_add_order_note', {
                        order_id: window.lazy_stripe_order_id,
                        note: 'Stripe checkout error! Error code: ' + error.code + ', Message: ' + error.message,
                        security: ajax_object.cs_add_order_note_nonce
                    }).done(function (result) {
                        console.log('done: ' + result);
                    }).fail(function () {
                        console.log("Can't add order note");
                    });
                }
                $('#cs-stripe-loader').hide();
                $('#payment-area-stripe-to-confirm').hide();
                checkout_error('We cannot process your payment right now, please try another payment method.[10]');
            }
        }
    }

    function checkout_error(error_message) {
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

    function checkFieldValidated(target) {
        var isNotInvalid = !target.closest('.form-row').hasClass('woocommerce-invalid');
        var isNotEmpty = true;
        if (target.closest('.form-row').hasClass('validate-required')) {
            isNotEmpty = (typeof target.val() == 'string') ? target.val().length : false;
        }
        return isNotInvalid && isNotEmpty;
    }

    function validateFormCheckout() {
        return checkFieldValidated($('#billing_first_name')) &&
            checkFieldValidated($('#billing_last_name')) &&
            checkFieldValidated($('#billing_email')) &&
            checkFieldValidated($('#billing_city')) &&
            checkFieldValidated($('#billing_country')) &&
            checkFieldValidated($('#billing_postcode')) &&
            checkFieldValidated($('#billing_address_1')) &&
            checkFieldValidated($('#billing_address_2')) &&
            checkFieldValidated($('#billing_phone')) &&
            $('input[name="payment_method"]:checked').val() == 'lazy_stripe';
    }

    window.addEventListener('hashchange', onHashChange);

    function onHashChange() {
        var partials = window.location.hash.match(/^#?cs-confirm-pi-([^:]+):(.+):(.+):(.+)$/);
        if (!partials || 5 > partials.length) {
            return;
        }

        var intentClientSecret = partials[1];
        window.lazy_stripe_order_id = partials[2];

        // Cleanup the URL
        window.location.hash = '';

        $('#payment-area-stripe-to-confirm').show();
        $('#payment-area-stripe-to-confirm')[0].contentWindow.postMessage({
            name: 'lazy-confirmPaymentIntentStripe',
            value: {
                clientSecret: intentClientSecret,
                orderId: window.lazy_stripe_order_id,
                paymentIntentId: partials[3]
            }
        }, '*');
    }

    function stripeGetFormCheckoutVal(selector) {
        if ($(selector).val()) {
            return $(selector).val();
        }
        return null;
    }
});
