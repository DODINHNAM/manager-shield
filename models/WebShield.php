<?php
// models/WebShield.php
require_once __DIR__ . '/../includes/db.php';

class WebShield {
    public static function all() {
        $pdo = getPDO();
        $stmt = $pdo->query("SELECT ws.*, u.username as manager_name FROM web_shields ws LEFT JOIN users u ON ws.manager_id = u.id ORDER BY ws.id DESC");
        return $stmt->fetchAll();
    }

    public static function byManager($managerId) {
        $pdo = getPDO();
        $stmt = $pdo->prepare("SELECT * FROM web_shields WHERE manager_id = ? ORDER BY id DESC");
        $stmt->execute([$managerId]);
        return $stmt->fetchAll();
    }

    public static function find($id) {
        $pdo = getPDO();
        $stmt = $pdo->prepare("SELECT * FROM web_shields WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public static function create($name, $domain, $manager_id = null) {
        $pdo = getPDO();
        $stmt = $pdo->prepare("INSERT INTO web_shields (name, domain, manager_id) VALUES (?, ?, ?)");
        $stmt->execute([$name, $domain, $manager_id]);
        return $pdo->lastInsertId();
    }

    public static function update($id, $name, $domain, $manager_id = null) {
        $pdo = getPDO();
        $stmt = $pdo->prepare("UPDATE web_shields SET name = ?, domain = ?, manager_id = ? WHERE id = ?");
        return $stmt->execute([$name, $domain, $manager_id, $id]);
    }

    public static function delete($id) {
        $pdo = getPDO();
        $stmt = $pdo->prepare("DELETE FROM web_shields WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
