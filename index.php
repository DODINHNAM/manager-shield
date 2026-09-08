<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';

// load controllers and models
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/AdminController.php';
require_once __DIR__ . '/controllers/ManagerController.php';
require_once __DIR__ . '/controllers/PaymentController.php';
require_once __DIR__ . '/controllers/ManagerWhitelistController.php';
require_once __DIR__ . '/controllers/TransactionController.php';
require_once __DIR__ . '/controllers/EndpointRotationController.php';
require_once __DIR__ . '/controllers/ShieldRestrictionController.php';


require_once __DIR__ . '/models/User.php';
require_once __DIR__ . '/models/WebShield.php';
require_once __DIR__ . '/models/PaymentType.php';
require_once __DIR__ . '/models/WebShieldPayment.php';
require_once __DIR__ . '/models/PayPalConfig.php';
require_once __DIR__ . '/models/StripeConfig.php';
require_once __DIR__ . '/models/MomoConfig.php';
require_once __DIR__ . '/models/ManagerWhitelist.php';
require_once __DIR__ . '/models/EndpointRotationConfig.php';


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
    
        case 'admin_edit_webshield':
            requireLogin();
            requireRole('admin');
            $id = intval($_GET['id'] ?? 0);
            $webshield = WebShield::find($id);
            if (!$webshield) {
                echo "Web Shield not found.";
                exit;
            }
            $managers = User::allManagers();
            $payment_types = PaymentType::all();
            $attached_payments = WebShieldPayment::findByWebShield($id);
            $data = [
                'webshield' => $webshield,
                'managers' => $managers,
                'payment_types' => $payment_types,
                'attached_payments' => $attached_payments,
                'restriction_types' => ShieldRestriction::types(),
                'local_restrictions' => ShieldRestriction::listLocal($id),
            ];
            require __DIR__ . '/views/admin/edit_webshield.php';
            break;
    
        case 'admin_update_webshield':
            requireLogin();
            requireRole('admin');
            $id = intval($_POST['id'] ?? 0);
            $name = $_POST['name'] ?? '';
            $domain = $_POST['domain'] ?? '';
            $manager_id = !empty($_POST['manager_id']) ? intval($_POST['manager_id']) : null;
            WebShield::update($id, $name, $domain, $manager_id);
            header('Location: index.php?action=admin_edit_webshield&id=' . $id);
            break;
    
        case 'admin_attach_payment':
            requireLogin();
            requireRole('admin');
            $web_shield_id = intval($_POST['web_shield_id'] ?? 0);
            $payment_type_id = intval($_POST['payment_type_id'] ?? 0);
            AdminController::attachPayment($web_shield_id, $payment_type_id);
            header('Location: index.php?action=admin_edit_webshield&id=' . $web_shield_id);
            break;
    
        case 'admin_detach_payment':
            requireLogin();
            requireRole('admin');
            $id = intval($_GET['id'] ?? 0);
            $wsp = WebShieldPayment::findById($id);
            $web_shield_id = $wsp['web_shield_id'];
            AdminController::detachPayment($id);
            header('Location: index.php?action=admin_edit_webshield&id=' . $web_shield_id);
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

    case 'admin_manager_whitelist':
        requireLogin();
        requireRole('admin');
        $manager_id = intval($_GET['manager_id'] ?? 0);
        $manager = User::find($manager_id);
        if (!$manager || $manager['role'] !== 'manager') {
            echo "Manager not found.";
            exit;
        }
        $domains = WebShield::byManager($manager_id);
        $data = [
            'manager' => $manager,
            'domains' => $domains,
        ];
        require __DIR__ . '/views/admin/manager_whitelist.php';
        break;

    /* MANAGER */
    case 'manager_my_webshields':
        requireLogin();
        $user = currentUser();
        if ($user['role'] === 'admin') {
            $webshields = WebShield::all();
        } else {
            requireRole('manager');
            $webshields = ManagerController::myWebShields($user['id']);
        }
        $data = ['webshields'=>$webshields];
        require __DIR__ . '/views/manager/my_webshields.php';
        break;

    case 'manager_payments':
        requireLogin();
        $user = currentUser();
        $web_id = intval($_GET['web_id'] ?? 0);
        $w = WebShield::find($web_id);
        if ($user['role'] !== 'admin') {
            requireRole('manager');
            if (!$w || $w['manager_id'] != $user['id']) {
                echo "Không tìm thấy web shield hoặc bạn không có quyền.";
                exit;
            }
        }
        $payments = PaymentController::listForWeb($web_id);
        $data = ['webshield'=>$w,'payments'=>$payments];
        require __DIR__ . '/views/manager/payments.php';
        break;

    case 'manager_save_payment':
        requireLogin();
        $wsp_id = intval($_GET['wsp_id'] ?? 0);
        $wsp = WebShieldPayment::findById($wsp_id);
        $user = currentUser();
        $w = $wsp ? WebShield::find($wsp['web_shield_id']) : null;
        if ($user['role'] !== 'admin') {
            requireRole('manager');
            if (!$w || $w['manager_id'] != $user['id']) { echo "Không hợp lệ"; exit; }
        }
        if (!$wsp || !$w) { http_response_code(404); exit; }
        $typeCode = $wsp['payment_code'];
        $post = $_POST;
          if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
          if ($typeCode === 'stripe' && (empty($_SESSION['payment_csrf']) || !is_string($post['payment_csrf'] ?? null) || !hash_equals($_SESSION['payment_csrf'] ?? '', $post['payment_csrf']))) {
              http_response_code(403); exit('Invalid form token.');
          }
          try {
              ManagerController::savePayment($wsp_id, $typeCode, $post);
              $_SESSION['payment_notice'] = 'Đã lưu cấu hình thanh toán.';
          } catch (InvalidArgumentException $e) {
              $_SESSION['payment_notice'] = $e->getMessage();
          } catch (RuntimeException $e) {
              $_SESSION['payment_notice'] = 'Không thể lưu cấu hình. Kiểm tra migration và kết nối cơ sở dữ liệu rồi thử lại.';
          }
        if ($user['role'] === 'admin') {
            header('Location: index.php?action=admin_edit_webshield&id=' . $w['id']);
        } else {
            header('Location: index.php?action=manager_payments&web_id=' . $w['id']);
        }
        break;

    case 'manager_whitelist':
        ManagerWhitelistController::list();
        break;

    case 'transactions':
        TransactionController::index();
        break;

    case 'endpoint_rotation':
        EndpointRotationController::index();
        break;
    case 'endpoint_rotation_save':
        EndpointRotationController::save();
        break;
    case 'endpoint_rotation_delete':
        EndpointRotationController::delete();
        break;
    case 'endpoint_rotation_keys':
        EndpointRotationController::keys();
        break;
    case 'restrictions':
        ShieldRestrictionController::index();
        break;
    case 'restrictions_save_global':
        ShieldRestrictionController::saveGlobal();
        break;
    case 'restrictions_save_local':
        ShieldRestrictionController::saveLocal();
        break;

    case 'manager_webshield_whitelist':
    case 'admin_webshield_whitelist':
        ManagerWhitelistController::listForShield($_GET['web_id'] ?? 0);
        break;

    case 'manager_webshield_whitelist_add':
    case 'admin_webshield_whitelist_add':
        ManagerWhitelistController::add($_GET['web_id'] ?? 0);
        break;

    case 'manager_webshield_whitelist_delete':
    case 'admin_webshield_whitelist_delete':
        ManagerWhitelistController::delete($_GET['id'] ?? 0);
        break;
    
    case 'manager_whitelist_add':
        ManagerWhitelistController::add($_GET['web_id'] ?? 0);
        break;

    case 'manager_whitelist_delete':
        ManagerWhitelistController::delete($_GET['id'] ?? 0);
        break;

    default:
        http_response_code(404);
        echo "404 - Not found";
        break;
}
