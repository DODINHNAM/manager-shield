<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';

// load controllers and models
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/AdminController.php';
require_once __DIR__ . '/controllers/ManagerController.php';
require_once __DIR__ . '/controllers/PaymentController.php';

require_once __DIR__ . '/models/User.php';
require_once __DIR__ . '/models/WebShield.php';
require_once __DIR__ . '/models/PaymentType.php';
require_once __DIR__ . '/models/WebShieldPayment.php';
require_once __DIR__ . '/models/PayPalConfig.php';
require_once __DIR__ . '/models/StripeConfig.php';
require_once __DIR__ . '/models/MomoConfig.php';

$action = $_GET['action'] ?? 'home';

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
            $data = [];
            require __DIR__ . '/views/admin/dashboard.php';
        } else {
            require __DIR__ . '/views/manager/dashboard.php';
        }
        break;

    /* ADMIN */
    case 'admin_webshields':
        requireLogin();
        requireRole('admin');
        $webshields = WebShield::all();
        $managers = User::allManagers();
        $payment_types = PaymentType::all();
        $data = ['webshields'=>$webshields,'managers'=>$managers,'payment_types'=>$payment_types];
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
        WebShield::delete($id);
        header('Location: index.php?action=admin_webshields');
        break;
    case 'admin_attach_payment':
        requireLogin();
        requireRole('admin');
        $web_shield_id = intval($_POST['web_shield_id'] ?? 0);
        $payment_type_id = intval($_POST['payment_type_id'] ?? 0);
        AdminController::attachPayment($web_shield_id, $payment_type_id);
        header('Location: index.php?action=admin_webshields');
        break;
    case 'admin_detach_payment':
        requireLogin();
        requireRole('admin');
        $id = intval($_GET['id'] ?? 0);
        AdminController::detachPayment($id);
        header('Location: index.php?action=admin_webshields');
        break;

    case 'admin_users':
        requireLogin();
        requireRole('admin');
        $users = User::all();
        $data = ['users'=>$users];
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

    /* MANAGER */
    case 'manager_my_webshields':
        requireLogin();
        requireRole('manager');
        $user = currentUser();
        $webshields = ManagerController::myWebShields($user['id']);
        $data = ['webshields'=>$webshields];
        require __DIR__ . '/views/manager/my_webshields.php';
        break;

    case 'manager_payments':
        requireLogin();
        requireRole('manager');
        $user = currentUser();
        $web_id = intval($_GET['web_id'] ?? 0);
        $w = WebShield::find($web_id);
        if (!$w || $w['manager_id'] != $user['id']) {
            echo "Không tìm thấy web shield hoặc bạn không có quyền.";
            exit;
        }
        $payments = PaymentController::listForWeb($web_id);
        $data = ['webshield'=>$w,'payments'=>$payments];
        require __DIR__ . '/views/manager/payments.php';
        break;

    case 'manager_edit_payment':
        requireLogin();
        requireRole('manager');
        $wsp_id = intval($_GET['wsp_id'] ?? 0);
        $wsp = WebShieldPayment::findById($wsp_id);
        $user = currentUser();
        $w = WebShield::find($wsp['web_shield_id']);
        if (!$w || $w['manager_id'] != $user['id']) { echo "Không hợp lệ"; exit; }
        $config = PaymentController::getConfig($wsp);
        $data = ['wsp'=>$wsp,'config'=>$config];
        // include view based on payment code
        switch ($wsp['payment_code']) {
            case 'paypal': require __DIR__ . '/views/manager/payment_paypal.php'; break;
            case 'stripe': require __DIR__ . '/views/manager/payment_stripe.php'; break;
            case 'momo': require __DIR__ . '/views/manager/payment_momo.php'; break;
            default: echo "Unsupported"; break;
        }
        break;

    case 'manager_save_payment':
        requireLogin();
        requireRole('manager');
        $wsp_id = intval($_GET['wsp_id'] ?? 0);
        $wsp = WebShieldPayment::findById($wsp_id);
        $user = currentUser();
        $w = WebShield::find($wsp['web_shield_id']);
        if (!$w || $w['manager_id'] != $user['id']) { echo "Không hợp lệ"; exit; }
        $typeCode = $wsp['payment_code'];
        $post = $_POST;
        ManagerController::savePayment($wsp_id, $typeCode, $post);
        header('Location: index.php?action=manager_payments&web_id=' . $w['id']);
        break;

    default:
        http_response_code(404);
        echo "404 - Not found";
        break;
}
