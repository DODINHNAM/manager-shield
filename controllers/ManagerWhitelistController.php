<?php
require_once __DIR__ . '/../models/ManagerWhitelist.php';
require_once __DIR__ . '/../models/User.php';

class ManagerWhitelistController {

    public static function list() {
        requireLogin();
        $user = currentUser();

        if ($user['role'] === 'admin') {
            $domains_by_manager = [];
            $rows = db_query("SELECT mwd.*, u.username as manager_name FROM manager_whitelist_domains mwd JOIN users u ON mwd.manager_id = u.id ORDER BY u.username, mwd.domain");
            foreach ($rows as $row) {
                $domains_by_manager[$row['manager_name']][] = $row;
            }
            $data = ['domains_by_manager' => $domains_by_manager];
        } else {
            requireRole('manager');
            $domains = ManagerWhitelist::listByManager($user['id']);
            $data = ['domains' => $domains];
        }
        
        include __DIR__ . '/../views/manager/whitelist_list.php';
    }

    public static function add() {
        requireLogin();
        $user = currentUser();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $domain = trim($_POST['domain']);
            $active = isset($_POST['active']) ? 1 : 0;
            
            $manager_id = $user['id'];
            if ($user['role'] === 'admin' && isset($_POST['manager_id'])) {
                $manager_id = intval($_POST['manager_id']);
            }

            if ($domain !== '') {
                ManagerWhitelist::create($manager_id, $domain, $active);
                if ($user['role'] === 'admin') {
                    header("Location: index.php?action=admin_manager_whitelist&manager_id=" . $manager_id);
                } else {
                    header("Location: index.php?action=manager_whitelist");
                }
                exit;
            }
        }
        
        if ($user['role'] === 'admin') {
            // Admins should use the form in manager_whitelist.php
            header("Location: index.php?action=admin_users");
            exit;
        } else {
            include __DIR__ . '/../views/manager/whitelist_form.php';
        }
    }

    public static function edit($id) {
        requireLogin();
        requireRole('manager');
        $user = currentUser();

        $item = ManagerWhitelist::find($id);
        if (!$item || ($item['manager_id'] != $user['id'] && $user['role'] !== 'admin')) {
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
        if (!$item || ($item['manager_id'] != $user['id'] && $user['role'] !== 'admin')) {
            echo "Không có quyền xóa domain này.";
            return;
        }

        ManagerWhitelist::delete($id);
        header("Location: index.php?action=manager_whitelist");
        exit;
    }
}
