<?php
// index.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';

// load controllers
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/AdminController.php';
require_once __DIR__ . '/controllers/ManagerController.php';
require_once __DIR__ . '/models/User.php';
require_once __DIR__ . '/models/WebShield.php';
require_once __DIR__ . '/models/PaymentConfig.php';

// route
$action = $_GET['action'] ?? 'home';

// simple dispatcher
switch($action) {
    case 'login':
        $error = $_GET['error'] ?? '';
        require __DIR__ . '/views/login.php';
        break;

    case 'login_post':
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $err = AuthController::login($username, $password);
        if ($err) {
            $error = $err;
            require __DIR__ . '/views/login.php';
        }
        break;

    case 'logout':
        AuthController::logout();
        break;

    case 'home':
        requireLogin();
        $user = currentUser();
        if ($user['role'] === 'admin') {
            require __DIR__ . '/views/admin/dashboard.php';
        } else {
            require __DIR__ . '/views/manager/dashboard.php';
        }
        break;

    /* ADMIN PAGES */
    case 'admin_users':
        requireLogin();
        requireRole('admin');
        $users = User::all();
        require __DIR__ . '/views/admin/users.php';
        break;

    case 'admin_create_user':
        requireLogin();
        requireRole('admin');
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'manager';
        AdminController::createUser($username, $password, $role);
        header('Location: index.php?action=admin_users');
        break;

    case 'admin_delete_user':
        requireLogin();
        requireRole('admin');
        $id = intval($_GET['id'] ?? 0);
        AdminController::deleteUser($id);
        header('Location: index.php?action=admin_users');
        break;

    case 'admin_webshields':
        requireLogin();
        requireRole('admin');
        $webshields = WebShield::all();
        $managers = User::allManagers();
        require __DIR__ . '/views/admin/webshields.php';
        break;

    case 'admin_create_webshield':
        requireLogin();
        requireRole('admin');
        $name = $_POST['name'] ?? '';
        $domain = $_POST['domain'] ?? '';
        $manager_id = !empty($_POST['manager_id']) ? intval($_POST['manager_id']) : null;
        AdminController::createWebShield($name, $domain, $manager_id);
        header('Location: index.php?action=admin_webshields');
        break;

    case 'admin_delete_webshield':
        requireLogin();
        requireRole('admin');
        $id = intval($_GET['id'] ?? 0);
        AdminController::deleteWebShield($id);
        header('Location: index.php?action=admin_webshields');
        break;

    /* MANAGER PAGES */
    case 'manager_my_webshields':
        requireLogin();
        requireRole('manager');
        $user = currentUser();
        $webshields = ManagerController::myWebShields($user['id']);
        // show a simple list with links to edit payment
        require __DIR__ . '/views/layout_header.php';
        echo "<h3>My Web Shields</h3><ul class='list-group'>";
        foreach($webshields as $w) {
            echo "<li class='list-group-item'><strong>".htmlspecialchars($w['name'])."</strong> - ".htmlspecialchars($w['domain'] ?? '')." <a class='btn btn-sm btn-primary float-end' href='index.php?action=manager_payment&web_id={$w['id']}'>Payment</a></li>";
        }
        echo "</ul>";
        require __DIR__ . '/views/layout_footer.php';
        break;

    case 'manager_payment':
        requireLogin();
        requireRole('manager');
        $user = currentUser();
        $web_id = intval($_GET['web_id'] ?? 0);
        $w = WebShield::find($web_id);
        if (!$w || $w['manager_id'] != $user['id']) {
            echo "Không tìm thấy web shield hoặc bạn không có quyền.";
            exit;
        }
        $config = PaymentConfig::findByWebShield($web_id);
        $data = ['webshield' => $w, 'config' => $config];
        require __DIR__ . '/views/manager/payment_config.php';
        break;

    case 'manager_save_payment':
        requireLogin();
        requireRole('manager');
        $user = currentUser();
        $web_id = intval($_POST['web_shield_id'] ?? 0);
        $w = WebShield::find($web_id);
        if (!$w || $w['manager_id'] != $user['id']) {
            echo "Không hợp lệ.";
            exit;
        }
        $type = $_POST['type'] ?? 'paypal';
        $env = $_POST['environment'] ?? 'sandbox';
        $client = $_POST['client_id'] ?? null;
        $secret = $_POST['secret_id'] ?? null;
        ManagerController::savePaymentConfig($web_id, $type, $env, $client, $secret);
        header('Location: index.php?action=manager_payment&web_id=' . $web_id);
        break;

    default:
        http_response_code(404);
        echo "404 - Not found";
        break;
}
