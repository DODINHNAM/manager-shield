<?php
// controllers/PaymentController.php
require_once __DIR__ . '/../models/PaymentType.php';
require_once __DIR__ . '/../models/WebShieldPayment.php';
require_once __DIR__ . '/../models/PayPalConfig.php';
require_once __DIR__ . '/../models/StripeConfig.php';
require_once __DIR__ . '/../models/MomoConfig.php';

class PaymentController {
    public static function listForWeb($webShieldId) {
        return WebShieldPayment::findByWebShield($webShieldId);
    }

    public static function getConfig($wsp) {
        $code = $wsp['payment_code'];
        $id = $wsp['id'];
        switch ($code) {
            case 'paypal': return PayPalConfig::findByPayment($id);
            case 'stripe': return StripeConfig::findByPayment($id);
            case 'momo': return MomoConfig::findByPayment($id);
        }
        return null;
    }
}
