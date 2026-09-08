<?php
require_once __DIR__ . '/../includes/db.php';

class ManagerWhitelist {
    public static function listByShield($shieldId) {
        return db_query("SELECT * FROM manager_whitelist_domains WHERE web_shield_id = ? ORDER BY id DESC", [$shieldId]);
    }

    public static function find($id) {
        $rows = db_query("SELECT * FROM manager_whitelist_domains WHERE id = ?", [$id]);
        return $rows[0] ?? null;
    }

    public static function create($shieldId, $domain, $active = 1) {
        return db_execute(
            "INSERT INTO manager_whitelist_domains (web_shield_id, domain, active) VALUES (?, ?, ?)",
            [$shieldId, $domain, $active]
        );
    }

    public static function update($id, $domain, $active) {
        return db_execute(
            "UPDATE manager_whitelist_domains SET domain = ?, active = ? WHERE id = ?",
            [$domain, $active, $id]
        );
    }

    public static function delete($id) {
        return db_execute("DELETE FROM manager_whitelist_domains WHERE id = ?", [$id]);
    }
}
