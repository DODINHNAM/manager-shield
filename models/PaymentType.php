<?php
// models/PaymentType.php
require_once __DIR__ . '/../includes/db.php';

class PaymentType {
    public static function all() {
        return db_query("SELECT * FROM payment_types ORDER BY id");
    }

    public static function findById($id) {
        $rows = db_query("SELECT * FROM payment_types WHERE id = ?", [$id]);
        return $rows[0] ?? null;
    }

    public static function findByCode($code) {
        $rows = db_query("SELECT * FROM payment_types WHERE code = ?", [$code]);
        return $rows[0] ?? null;
    }

    public static function create($code, $name, $desc = null) {
        return db_execute("INSERT INTO payment_types (code, name, description) VALUES (?, ?, ?)", [$code, $name, $desc]);
    }
}
