<?php
require_once __DIR__ . '/../models/ManagerWhitelist.php';
require_once __DIR__ . '/../models/User.php';

class ManagerWhitelistController {

    public static function list() {
        requireLogin();
        $user = currentUser();

        if ($user['role'] === 'admin') {
            $domains = db_query("SELECT mwd.*, u.username as manager_name FROM manager_whitelist_domains mwd JOIN users u ON mwd.manager_id = u.id ORDER BY mwd.id DESC");
        } else {
            requireRole('manager');
            $domains = ManagerWhitelist::listByManager($user['id']);
        }
        
        include __DIR__ . '/../views/manager/whitelist_list.php';
    }

    public static function add() {
        requireLogin();
        requireRole('manager');
        $user = currentUser();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $domain = trim($_POST['domain']);
            $active = isset($_POST['active']) ? 1 : 0;
            if ($domain !== '') {
                ManagerWhitelist::create($user['id'], $domain, $active);
                header("Location: index.php?action=manager_whitelist");
                exit;
            }
        }
        include __DIR__ . '/../views/manager/whitelist_form.php';
    }

    public static function edit($id) {
        requireLogin();
        requireRole('manager');
        $user = currentUser();

        $item = ManagerWhitelist::find($id);
        if (!$item || $item['manager_id'] != $user['id']) {
            echo "Không có quyền chỉnh sửa domain này.";
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $domain = trim($_POST['domain']);
            $active = isset($_POST['active']) ? 1 : 0;
            ManagerWhitelist::update($id, $domain, $active);
            header("Location: index.php?action=manager_whitelist");
            exit;
        }

        include __DIR__ . '/../views/manager/whitelist_form.php';
    }

    public static function delete($id) {
        requireLogin();
        requireRole('manager');
        $user = currentUser();

        $item = ManagerWhitelist::find($id);
        if (!$item || $item['manager_id'] != $user['id']) {
            echo "Không có quyền xóa domain này.";
            return;
        }

        ManagerWhitelist::delete($id);
        header("Location: index.php?action=manager_whitelist");
        exit;
    }
}
