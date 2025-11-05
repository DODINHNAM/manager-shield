<?php
// models/WebShield.php
require_once __DIR__ . '/../includes/db.php';

class WebShield {
    public static function all() {
        return db_query("SELECT ws.*, u.username as manager_name FROM web_shields ws LEFT JOIN users u ON ws.manager_id = u.id ORDER BY ws.id DESC");
    }

    public static function byManager($managerId) {
        return db_query("SELECT * FROM web_shields WHERE manager_id = ? ORDER BY id DESC", [$managerId]);
    }

    public static function find($id) {
        $rows = db_query("SELECT * FROM web_shields WHERE id = ?", [$id]);
        return $rows[0] ?? null;
    }

    public static function create($name, $domain, $manager_id = null) {
        db_execute("INSERT INTO web_shields (name, domain, manager_id) VALUES (?, ?, ?)", [$name, $domain, $manager_id]);
        $rows = db_query("SELECT LAST_INSERT_ID() AS id");
        return $rows[0]['id'] ?? null;
    }

    public static function update($id, $name, $domain, $manager_id = null) {
        return db_execute("UPDATE web_shields SET name = ?, domain = ?, manager_id = ? WHERE id = ?", [$name, $domain, $manager_id, $id]);
    }

    public static function delete($id) {
        return db_execute("DELETE FROM web_shields WHERE id = ?", [$id]);
    }
}
