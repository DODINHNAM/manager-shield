<?php defined('ABSPATH') || exit; ?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Stripe checkout</title>
<style>html,body{margin:0;padding:0}body{font:14px -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#172033}#stripe-form{box-sizing:border-box;width:100%;padding:8px}#stripe-card{box-sizing:border-box;min-height:56px;padding:17px 14px;border:1px solid #d9e0ea;border-radius:10px;background:#fff}#stripe-error{color:#b42318;margin-top:8px;line-height:20px;overflow-wrap:anywhere}#stripe-error:empty{display:none}</style>
</head><body>
<div id="stripe-form"><div id="stripe-card"></div><div id="stripe-error" class="stripe-error" role="alert"></div></div>
<script>window.lazyStripeProxy={key:<?php echo wp_json_encode($data['publishable_key']); ?>,currency:<?php echo wp_json_encode($data['currency']); ?>};</script>
<script src="https://js.stripe.com/v3/"></script>
<script src="<?php echo esc_url(WPLAZY_STRIPE_PROXY_URL . 'assets/js/stripe-payment-form.js?v=' . WPLAZY_STRIPE_PROXY_VERSION . '-card-layout-2'); ?>"></script>
</body></html>
