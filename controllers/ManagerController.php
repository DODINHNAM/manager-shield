<?php
// controllers/ManagerController.php
require_once __DIR__ . '/../models/WebShield.php';
require_once __DIR__ . '/../models/PaymentConfig.php';

class ManagerController {
    public static function myWebShields($managerId) {
        return WebShield::byManager($managerId);
    }

    public static function savePaymentConfig($webShieldId, $type, $env, $client, $secret) {
        return PaymentConfig::createOrUpdate($webShieldId, $type, $env, $client, $secret);
    }
}
