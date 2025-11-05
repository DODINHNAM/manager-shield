<?php
// models/PayPalConfig.php
require_once __DIR__ . '/../includes/db.php';

class PayPalConfig {
    public static function findByPayment($wspId) {
        $rows = db_query("SELECT * FROM paypal_configs WHERE web_shield_payment_id = ?", [$wspId]);
        return $rows[0] ?? null;
    }

    public static function create($wspId, $env, $client, $secret) {
        return db_execute("INSERT INTO paypal_configs (web_shield_payment_id, environment, client_id, secret_id) VALUES (?, ?, ?, ?)",
            [$wspId, $env, $client, $secret]);
    }

    public static function updateByPayment($wspId, $env, $client, $secret) {
        $exists = self::findByPayment($wspId);
        if ($exists) {
            return db_execute("UPDATE paypal_configs SET environment=?, client_id=?, secret_id=? WHERE web_shield_payment_id = ?",
                [$env, $client, $secret, $wspId]);
        } else {
            return self::create($wspId, $env, $client, $secret);
        }
    }
}
