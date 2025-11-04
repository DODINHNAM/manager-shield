<?php
// models/PaymentConfig.php
require_once __DIR__ . '/../includes/db.php';

class PaymentConfig {
    public static function findByWebShield($webShieldId) {
        $pdo = getPDO();
        $stmt = $pdo->prepare("SELECT * FROM payment_configs WHERE web_shield_id = ?");
        $stmt->execute([$webShieldId]);
        return $stmt->fetch();
    }

    public static function createOrUpdate($webShieldId, $type, $env, $client, $secret) {
        $pdo = getPDO();
        $exists = self::findByWebShield($webShieldId);
        if ($exists) {
            $stmt = $pdo->prepare("UPDATE payment_configs SET type=?, environment=?, client_id=?, secret_id=? WHERE web_shield_id = ?");
            return $stmt->execute([$type, $env, $client, $secret, $webShieldId]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO payment_configs (web_shield_id, type, environment, client_id, secret_id) VALUES (?, ?, ?, ?, ?)");
            return $stmt->execute([$webShieldId, $type, $env, $client, $secret]);
        }
    }
}
