<?php
/**
 * Plugin Name: CardsShield Gateway Stripe (legacy loader)
 * Description: Compatibility entry point. Loads the Lazy Stripe gateway for existing activations.
 * Version: 2.6.7
 * Author: CardsShield
 */
defined('ABSPATH') || exit;
if (!defined('LAZY_STRIPE_PLUGIN_FILE')) define('LAZY_STRIPE_PLUGIN_FILE', __FILE__);
require_once __DIR__ . '/m-lazy-gateway-stripe.php';
