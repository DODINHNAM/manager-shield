<?php defined('ABSPATH') || exit; ?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Stripe checkout</title>
<style>body{margin:0;padding:12px;font:14px -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#172033}#stripe-card{padding:12px;border:1px solid #d9e0ea;border-radius:6px;background:#fff}.stripe-error{color:#b42318;margin-top:8px}</style>
</head><body>
<div id="stripe-card"></div><div id="stripe-error" class="stripe-error" role="alert"></div>
<script>window.lazyStripeProxy={key:<?php echo wp_json_encode($data['publishable_key']); ?>,currency:<?php echo wp_json_encode($data['currency']); ?>};</script>
<script src="https://js.stripe.com/v3/"></script>
<script src="<?php echo esc_url(WPLAZY_STRIPE_PROXY_URL . 'assets/js/stripe-payment-form.js?v=' . WPLAZY_STRIPE_PROXY_VERSION); ?>"></script>
</body></html>
