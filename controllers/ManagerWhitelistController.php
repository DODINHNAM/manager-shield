<?php
require_once __DIR__ . '/../models/ManagerWhitelist.php';
require_once __DIR__ . '/../models/WebShield.php';

class ManagerWhitelistController {
    public static function list() {
        requireLogin();
        $user = currentUser();
        $data = ['webshields' => $user['role'] === 'admin' ? WebShield::all() : WebShield::byManager($user['id'])];
        require __DIR__ . '/../views/manager/whitelist_list.php';
    }

    private static function shieldForUser($shieldId, $user) {
        $shield = WebShield::find((int) $shieldId);
        if (!$shield || ($user['role'] !== 'admin' && (int) $shield['manager_id'] !== (int) $user['id'])) {
            return null;
        }
        return $shield;
    }

    public static function listForShield($shieldId) {
        requireLogin();
        $user = currentUser();
        $shield = self::shieldForUser($shieldId, $user);
        if (!$shield) { http_response_code(403); exit('Web Shield not found or access denied.'); }
        $data = ['webshield' => $shield, 'domains' => ManagerWhitelist::listByShield($shield['id']), 'is_admin' => $user['role'] === 'admin'];
        require __DIR__ . '/../views/manager/webshield_whitelist.php';
    }

    public static function add($shieldId) {
        requireLogin();
        $user = currentUser();
        $shield = self::shieldForUser($shieldId, $user);
        if (!$shield) { http_response_code(403); exit('Web Shield not found or access denied.'); }
        $domain = strtolower(trim($_POST['domain'] ?? ''));
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $domain !== '') {
            ManagerWhitelist::create($shield['id'], $domain, 1);
        }
        $action = $user['role'] === 'admin' ? 'admin_webshield_whitelist' : 'manager_webshield_whitelist';
        header('Location: index.php?action=' . $action . '&web_id=' . $shield['id']);
        exit;
    }

    public static function delete($id) {
        requireLogin();
        $user = currentUser();
        $item = ManagerWhitelist::find($id);
        $shield = $item ? self::shieldForUser($item['web_shield_id'], $user) : null;
        if (!$item || !$shield) { http_response_code(403); exit('Whitelist entry not found or access denied.'); }
        ManagerWhitelist::delete($id);
        $action = $user['role'] === 'admin' ? 'admin_webshield_whitelist' : 'manager_webshield_whitelist';
        header('Location: index.php?action=' . $action . '&web_id=' . $shield['id']);
        exit;
    }
}
