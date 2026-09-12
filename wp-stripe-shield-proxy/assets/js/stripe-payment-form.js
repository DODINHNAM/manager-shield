(function () {
    'use strict';
    var stripe = Stripe(window.lazyStripeProxy.key);
    var elements = stripe.elements();
    var card = elements.create('card', {hidePostalCode: true});
    card.mount('#stripe-card');
    card.on('change', function (event) { document.getElementById('stripe-error').textContent = event.error ? event.error.message : ''; });
    parent.postMessage('lazy-loadedPaymentFormStripe', '*');
    var form = document.getElementById('stripe-form');
    var lastHeight = 0;
    function resizeForm() {
        var height = Math.ceil(form.getBoundingClientRect().height);
        if (height > 0 && height !== lastHeight) {
            lastHeight = height;
            parent.postMessage({name: 'lazy-stripeBodyResizeCreditForm', value: height}, '*');
        }
    }
    card.on('ready', resizeForm);
    card.on('change', resizeForm);
    window.addEventListener('resize', resizeForm);
    if (window.ResizeObserver) new ResizeObserver(resizeForm).observe(form);
    resizeForm();
    window.addEventListener('message', function (event) {
        if (!event.data || !event.data.name) return;
        if (event.data.name === 'lazy-submitFormStripe') {
            parent.postMessage('lazy-startSubmitPaymentStripe', '*');
            stripe.createPaymentMethod({type: 'card', card: card, billing_details: event.data.value.billing_details}).then(function (result) {
                if (result.error) throw result.error;
                parent.postMessage({name: 'lazy-paymentMethodIdStripe', value: result.paymentMethod.id}, '*');
                parent.postMessage('lazy-paymentFormCompletedStripe', '*');
            }).catch(function (error) {
                parent.postMessage({name: 'lazy-errorSubmitPaymentStripe', value: error.message || 'Stripe card error'}, '*');
                parent.postMessage('lazy-paymentFormFailStripe', '*');
            });
        }
        if (event.data.name === 'lazy-confirmPaymentIntentStripe') {
            stripe.confirmCardPayment(event.data.value.clientSecret).then(function (result) {
                if (result.error) parent.postMessage({name: 'lazy-confirmPaymentIntentStripe', value: 'failed', error: result.error}, '*');
                else parent.postMessage({name: 'lazy-confirmPaymentIntentStripe', value: 'success'}, '*');
            });
        }
    });
})();
