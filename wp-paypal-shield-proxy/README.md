# LazyShield PayPal Proxy

## Overview
The LazyShield PayPal Proxy is a WordPress plugin that integrates PayPal's credit payment form into your WooCommerce checkout process. This plugin allows users to make payments seamlessly using PayPal, enhancing the checkout experience.

## Features
- Integration of PayPal's credit payment form.
- Customizable payment buttons.
- Validation for merchant sites, zip codes, emails, and city/state combinations.
- Responsive design for mobile and desktop users.

## Installation
1. Download the plugin files.
2. Upload the `wp-paypal-shield-proxy` folder to the `/wp-content/plugins/` directory of your WordPress installation.
3. Activate the plugin through the 'Plugins' menu in WordPress.

## Usage
Once activated, the plugin will automatically add PayPal payment options to your WooCommerce checkout page. You can customize the appearance and behavior of the PayPal buttons through the provided CSS and JavaScript files.

## Files
- **assets/js/paypal-credit-payment-form.js**: Contains the JavaScript code for the PayPal credit payment form integration.
- **assets/css/paypal-credit-payment-form.css**: Contains the CSS styles for the PayPal credit payment form.
- **inc/api.php**: Handles API requests and responses for PayPal integration.
- **templates/checkout.php**: Template for the checkout page where PayPal buttons are rendered.
- **wp-lazy-paypal-proxy.php**: Main plugin file that initializes the plugin and registers hooks.
- **package.json**: Configuration file for npm, listing dependencies and scripts.
- **composer.json**: Configuration file for Composer, specifying PHP dependencies and autoloading settings.

## Contributing
Contributions are welcome! Please submit a pull request or open an issue for any enhancements or bug fixes.

## License
This project is licensed under the MIT License. See the LICENSE file for details.
