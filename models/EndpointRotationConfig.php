<?php
require_once __DIR__ . '/../includes/db.php';

class EndpointRotationConfig {
    public static function listForUser($user) {
        if (($user['role'] ?? '') === 'admin') {
            return db_query("SELECT c.*, u.username AS creator_name,
                (SELECT COUNT(*) FROM endpoint_rotation_members m WHERE m.config_id = c.id AND m.active = 1) AS member_count
                FROM endpoint_rotation_configs c LEFT JOIN users u ON u.id = c.created_by ORDER BY c.id DESC");
        }
        return db_query("SELECT c.*, u.username AS creator_name,
                (SELECT COUNT(*) FROM endpoint_rotation_members m WHERE m.config_id = c.id AND m.active = 1) AS member_count
                FROM endpoint_rotation_configs c LEFT JOIN users u ON u.id = c.created_by
                WHERE c.created_by = ? OR EXISTS (
                    SELECT 1 FROM endpoint_rotation_members m JOIN web_shields w ON w.id = m.web_shield_id
                    WHERE m.config_id = c.id AND w.manager_id = ?
                ) ORDER BY c.id DESC", [$user['id'], $user['id']]);
    }

    public static function findForUser($id, $user) {
        foreach (self::listForUser($user) as $config) {
            if ((int) $config['id'] === (int) $id) return $config;
        }
        return null;
    }

    public static function members($configId) {
        return db_query("SELECT m.*, w.name AS shield_name, w.domain AS shield_domain
            FROM endpoint_rotation_members m JOIN web_shields w ON w.id = m.web_shield_id
            WHERE m.config_id = ? ORDER BY m.position ASC, m.id ASC", [$configId]);
    }

    public static function create($name, $provider, $method, $userId, $shieldRows) {
        $token = bin2hex(random_bytes(24));
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $gatewayDomain = $host !== '' ? $scheme . '://' . $host : rtrim(BASE_URL, '/');
        $secretAlphabet = 'ZQXJKVWPY ./-:?=&%# 123456789ABCDEFGHILMNORSTUabcdefghijklmnopqrstuvwxyz';
        $plainAlphabet = './-:?=&%# ZQXJKVWPY abcdefghijklmnopqrstuvwxyz123456789ABCDEFGHILMNORSTU';
        $secret = base64_encode(strtr(base64_encode(rtrim($gatewayDomain, '/') . $token), $plainAlphabet, $secretAlphabet));
        db_execute("INSERT INTO endpoint_rotation_configs (name, token_hash, token_preview, payment_provider, rotation_method, created_by)
            VALUES (?, ?, ?, ?, ?, ?)", [$name, hash('sha256', $token), substr($token, 0, 12), $provider, $method, $userId]);
        $id = (int) db_query("SELECT LAST_INSERT_ID() AS id")[0]['id'];
        self::replaceMembers($id, $shieldRows);
        return ['id' => $id, 'token' => $token, 'secret' => $secret, 'gateway_domain' => $gatewayDomain];
    }

    public static function replaceMembers($configId, $shieldRows) {
        db_execute("DELETE FROM endpoint_rotation_members WHERE config_id = ?", [$configId]);
        $position = 0;
        foreach ($shieldRows as $row) {
            db_execute("INSERT INTO endpoint_rotation_members
                (config_id, web_shield_id, position, rotation_value, paid_amount, paid_date)
                VALUES (?, ?, ?, ?, 0, NULL)", [$configId, (int) $row['web_shield_id'], $position++, (float) $row['rotation_value']]);
        }
        db_execute("UPDATE endpoint_rotation_configs SET current_member_id = NULL, current_rotation_at = NULL WHERE id = ?", [$configId]);
    }

    public static function updateMembers($configId, $shieldRows) {
        self::replaceMembers($configId, $shieldRows);
    }

    public static function delete($id) {
        return db_execute("DELETE FROM endpoint_rotation_configs WHERE id = ?", [$id]);
    }

    public static function authenticateToken($token) {
        $hash = hash('sha256', trim((string) $token));
        $rows = db_query("SELECT * FROM endpoint_rotation_configs WHERE token_hash = ? AND active = 1 LIMIT 1", [$hash]);
        return $rows[0] ?? null;
    }

    public static function findForApi($id) {
        $rows = db_query("SELECT * FROM endpoint_rotation_configs WHERE id = ? AND active = 1 LIMIT 1", [(int) $id]);
        return $rows[0] ?? null;
    }
}
