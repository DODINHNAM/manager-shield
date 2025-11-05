<?php
// models/WebShieldPayment.php
require_once __DIR__ . '/../includes/db.php';

class WebShieldPayment {
    public static function findByWebShield($webShieldId) {
        return db_query("SELECT wsp.*, pt.code as payment_code, pt.name as payment_name
                         FROM web_shield_payments wsp
                         JOIN payment_types pt ON pt.id = wsp.payment_type_id
                         WHERE wsp.web_shield_id = ?
                         ORDER BY wsp.id", [$webShieldId]);
    }

    public static function findById($id) {
        $rows = db_query("
            SELECT wsp.*, pt.code AS payment_code, pt.name AS payment_name
            FROM web_shield_payments wsp
            JOIN payment_types pt ON pt.id = wsp.payment_type_id
            WHERE wsp.id = ?
        ", [$id]);
        return $rows[0] ?? null;
    }
    

    public static function attach($webShieldId, $paymentTypeId) {
        return db_execute("INSERT INTO web_shield_payments (web_shield_id, payment_type_id, active) VALUES (?, ?, 1)", [$webShieldId, $paymentTypeId]);
    }

    public static function detach($id) {
        return db_execute("DELETE FROM web_shield_payments WHERE id = ?", [$id]);
    }
}
