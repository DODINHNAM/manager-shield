<?php
require_once __DIR__ . '/../models/ShieldRestriction.php';
require_once __DIR__ . '/../models/WebShield.php';
require_once __DIR__ . '/../models/User.php';

class ShieldRestrictionController {
    public static function index() {
        requireLogin();
        $user = currentUser();
        $shields = $user['role'] === 'admin' ? WebShield::all() : WebShield::byManager($user['id']);
        $selectedId = (int) ($_GET['shield_id'] ?? ($shields[0]['id'] ?? 0));
        $selected = null;
        foreach ($shields as $shield) {
            if ((int) $shield['id'] === $selectedId) { $selected = $shield; break; }
        }
        $managers = $user['role'] === 'admin' ? User::allManagers() : [$user];
        $managerId = (int) ($_GET['manager_id'] ?? ($selected['manager_id'] ?? ($managers[0]['id'] ?? 0)));
        $managerExists = false;
        foreach ($managers as $manager) {
            if ((int) $manager['id'] === $managerId) { $managerExists = true; break; }
        }
        if (!$managerExists) $managerId = (int) ($managers[0]['id'] ?? 0);
        $data = [
            'webshields' => $shields,
            'managers' => $managers,
            'manager_id' => $managerId,
            'selected' => $selected,
            'global' => ShieldRestriction::listGlobal($managerId),
            'local' => $selected ? ShieldRestriction::listLocal($selected['id']) : [],
            'types' => ShieldRestriction::types(),
        ];
        require __DIR__ . '/../views/manager/restrictions.php';
    }

    private static function shieldForUser($id, $user) {
        $shield = WebShield::find((int) $id);
        if (!$shield || ($user['role'] !== 'admin' && (int) $shield['manager_id'] !== (int) $user['id'])) return null;
        return $shield;
    }

    private static function values($name, $type) {
        $raw = (string) ($_POST[$name] ?? '');
        $items = preg_split('/[\r\n,]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);
        $items = array_map('trim', $items);
        $items = array_filter($items, static function ($value) use ($type) {
            if ($type === 'whitelist_domain') return (bool) preg_match('/^(?:https?:\/\/)?[^\s\/]+(?:\/.*)?$/i', $value);
            return $value !== '';
        });
        return array_values(array_unique(array_map('strtolower', $items)));
    }

    private static function rules($prefix) {
        $rules = [];
        foreach (array_keys(ShieldRestriction::types()) as $type) {
            $rules[$type] = self::values($prefix . $type, $type);
        }
        return $rules;
    }

    private static function enabled($prefix) {
        $enabled = $_POST[$prefix . 'enabled'] ?? [];
        return is_array($enabled) ? $enabled : [];
    }

    public static function saveGlobal() {
        requireLogin();
        $user = currentUser();
        $managerId = $user['role'] === 'admin' ? (int) ($_POST['manager_id'] ?? 0) : (int) $user['id'];
        $managers = $user['role'] === 'admin' ? User::allManagers() : [$user];
        $allowed = array_filter($managers, static function ($manager) use ($managerId) { return (int) $manager['id'] === $managerId; });
        if (!$managerId || !$allowed) { http_response_code(403); exit('Manager not found or access denied.'); }
        ShieldRestriction::replace('global', $managerId, null, self::rules('global_'), self::enabled('global_'));
        header('Location: index.php?action=restrictions&manager_id=' . $managerId);
        exit;
    }

    public static function saveLocal() {
        requireLogin();
        $user = currentUser();
        $shield = self::shieldForUser($_GET['shield_id'] ?? 0, $user);
        if (!$shield) { http_response_code(403); exit('Web Shield not found or access denied.'); }
        $ownerManagerId = !empty($shield['manager_id']) ? (int) $shield['manager_id'] : null;
        ShieldRestriction::replace('local', $ownerManagerId, $shield['id'], self::rules('local_'), self::enabled('local_'));
        header('Location: index.php?action=restrictions&shield_id=' . (int) $shield['id']);
        exit;
    }
}
