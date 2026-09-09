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
            $partner_code = trim((string) $partner_code) !== '' ? $partner_code : $exists['partner_code'];
            $access_key = trim((string) $access_key) !== '' ? $access_key : $exists['access_key'];
            $secret_key = trim((string) $secret_key) !== '' ? $secret_key : $exists['secret_key'];
            return db_execute("UPDATE momo_configs SET partner_code=?, access_key=?, secret_key=?, environment=? WHERE web_shield_payment_id = ?",
                [$partner_code, $access_key, $secret_key, $env, $wspId]);
        } else {
            return db_execute("INSERT INTO momo_configs (web_shield_payment_id, partner_code, access_key, secret_key, environment) VALUES (?, ?, ?, ?, ?)",
                [$wspId, $partner_code, $access_key, $secret_key, $env]);
        }
    }
}
