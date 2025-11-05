<?php
// controllers/ManagerController.php
require_once __DIR__ . '/../models/WebShield.php';
require_once __DIR__ . '/../models/WebShieldPayment.php';
require_once __DIR__ . '/../models/PayPalConfig.php';
require_once __DIR__ . '/../models/StripeConfig.php';
require_once __DIR__ . '/../models/MomoConfig.php';
require_once __DIR__ . '/../models/PaymentType.php';

class ManagerController {
    public static function myWebShields($managerId) {
        return WebShield::byManager($managerId);
    }

    public static function savePayment($wspId, $typeCode, $data) {
        switch ($typeCode) {
            case 'paypal':
                return PayPalConfig::updateByPayment($wspId, $data['environment'] ?? 'sandbox', $data['client_id'] ?? null, $data['secret_id'] ?? null);
            case 'stripe':
                return StripeConfig::updateByPayment($wspId, $data['api_key'] ?? null, $data['publishable_key'] ?? null);
            case 'momo':
                return MomoConfig::updateByPayment($wspId, $data['partner_code'] ?? null, $data['access_key'] ?? null, $data['secret_key'] ?? null, $data['environment'] ?? 'sandbox');
        }
        return false;
    }
}
