<?php
require_once __DIR__ . '/../includes/db.php';

class ManagerWhitelist {
    public static function listByManager($managerId) {
        return db_query("SELECT * FROM manager_whitelist_domains WHERE manager_id = ? ORDER BY id DESC", [$managerId]);
    }

    public static function find($id) {
        $rows = db_query("SELECT * FROM manager_whitelist_domains WHERE id = ?", [$id]);
        return $rows[0] ?? null;
    }

    public static function create($managerId, $domain, $active = 1) {
        return db_execute(
            "INSERT INTO manager_whitelist_domains (manager_id, domain, active) VALUES (?, ?, ?)",
            [$managerId, $domain, $active]
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
