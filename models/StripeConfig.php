<?php
// models/StripeConfig.php
require_once __DIR__ . '/../includes/db.php';

class StripeConfig {
    public static function findByPayment($wspId) {
        $rows = db_query("SELECT * FROM stripe_configs WHERE web_shield_payment_id = ?", [$wspId]);
        return $rows[0] ?? null;
    }

    public static function updateByPayment($wspId, $api_key, $publishable_key) {
        $exists = self::findByPayment($wspId);
        if ($exists) {
            return db_execute("UPDATE stripe_configs SET api_key = ?, publishable_key = ? WHERE web_shield_payment_id = ?",
                [$api_key, $publishable_key, $wspId]);
        } else {
            return db_execute("INSERT INTO stripe_configs (web_shield_payment_id, api_key, publishable_key) VALUES (?, ?, ?)",
                [$wspId, $api_key, $publishable_key]);
        }
    }
}
