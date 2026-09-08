<?php
require_once __DIR__ . '/../models/EndpointRotationConfig.php';
require_once __DIR__ . '/../models/WebShield.php';

class EndpointRotationController {
    public static function index() {
        requireLogin();
        $user = currentUser();
        $data = [
            'configs' => EndpointRotationConfig::listForUser($user),
            'webshields' => $user['role'] === 'admin' ? WebShield::all() : WebShield::byManager($user['id']),
        ];
        require __DIR__ . '/../views/manager/endpoint_rotation.php';
    }

    public static function save() {
        requireLogin();
        $user = currentUser();
        $name = trim($_POST['name'] ?? '');
        $provider = in_array($_POST['payment_provider'] ?? '', ['paypal', 'stripe', 'momo'], true) ? $_POST['payment_provider'] : 'paypal';
        $method = in_array($_POST['rotation_method'] ?? '', ['by_time', 'by_amount'], true) ? $_POST['rotation_method'] : 'by_time';
        $shieldIds = $_POST['shield_id'] ?? [];
        $values = $_POST['rotation_value'] ?? [];
        if ($name === '') exit('Config name is required.');
        $rows = [];
        foreach ((array) $shieldIds as $shieldId) {
            $shieldId = (int) $shieldId;
            $shield = WebShield::find($shieldId);
            if (!$shield || ($user['role'] !== 'admin' && (int) $shield['manager_id'] !== (int) $user['id'])) continue;
            $value = (float) ($values[$shieldId] ?? 0);
            if ($value <= 0) continue;
            $rows[] = ['web_shield_id' => $shieldId, 'rotation_value' => $value];
        }
        if (!$rows) exit('Select at least one shield with a rotation value.');
        $created = EndpointRotationConfig::create($name, $provider, $method, $user['id'], $rows);
        $_SESSION['endpoint_rotation_token'] = $created['token'];
        $_SESSION['endpoint_rotation_secret'] = $created['secret'];
        $_SESSION['endpoint_rotation_gateway'] = $created['gateway_domain'];
        header('Location: index.php?action=endpoint_rotation');
        exit;
    }

    public static function delete() {
        requireLogin();
        $config = EndpointRotationConfig::findForUser((int) ($_GET['id'] ?? 0), currentUser());
        if (!$config) { http_response_code(403); exit('Config not found or access denied.'); }
        EndpointRotationConfig::delete($config['id']);
        header('Location: index.php?action=endpoint_rotation');
        exit;
    }

    public static function keys() {
        requireLogin();
        $user = currentUser();
        if (($user['role'] ?? '') !== 'admin') {
            http_response_code(403);
            exit('Admin only.');
        }
        $config = EndpointRotationConfig::findForUser((int) ($_GET['id'] ?? 0), $user);
        if (!$config) {
            http_response_code(404);
            exit('Config not found.');
        }
        $data = [
            'config' => $config,
            'credentials' => EndpointRotationConfig::credentials($config['id']),
        ];
        require __DIR__ . '/../views/manager/endpoint_rotation_keys.php';
    }
}
