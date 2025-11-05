<?php
// models/MomoConfig.php
require_once __DIR__ . '/../includes/db.php';

class MomoConfig {
    public static function findByPayment($wspId) {
        $rows = db_query("SELECT * FROM momo_configs WHERE web_shield_payment_id = ?", [$wspId]);
        return $rows[0] ?? null;
    }

    public static function updateByPayment($wspId, $partner_code, $access_key, $secret_key, $env) {
        $exists = self::findByPayment($wspId);
        if ($exists) {
            return db_execute("UPDATE momo_configs SET partner_code=?, access_key=?, secret_key=?, environment=? WHERE web_shield_payment_id = ?",
                [$partner_code, $access_key, $secret_key, $env, $wspId]);
        } else {
            return db_execute("INSERT INTO momo_configs (web_shield_payment_id, partner_code, access_key, secret_key, environment) VALUES (?, ?, ?, ?, ?)",
                [$wspId, $partner_code, $access_key, $secret_key, $env]);
        }
    }
}
