# LazyShield Stripe Proxy

Install this plugin on the WordPress site used as the Stripe proxy endpoint.

The proxy reads the active Stripe configuration, whitelist, and restrictions from the manager API using these `wp-config.php` constants:

```php
define('WEBSHIELD_MANAGER_URL', 'https://manager.example.com');
define('WEBSHIELD_ENCRYPTION_KEY', '...');
define('WEBSHIELD_ENCRYPTION_IV', '...');
```

The Stripe secret key stays server-side. The checkout iframe receives only the publishable key. The gateway can call the proxy with its existing actions for embedded PaymentIntents, hosted Checkout Sessions, capture, cancel, refund, order status, tracking sync, and payment history.
