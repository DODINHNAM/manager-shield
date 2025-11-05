<?php
// controllers/AdminController.php
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/WebShield.php';
require_once __DIR__ . '/../models/PaymentType.php';
require_once __DIR__ . '/../models/WebShieldPayment.php';

class AdminController {
    public static function createUser($username, $password, $role='manager') {
        return User::create($username, $password, $role);
    }

    public static function deleteUser($id) {
        return User::delete($id);
    }

    public static function createWebShield($name, $domain, $manager_id = null) {
        return WebShield::create($name, $domain, $manager_id);
    }

    public static function attachPayment($webShieldId, $paymentTypeId) {
        return WebShieldPayment::attach($webShieldId, $paymentTypeId);
    }

    public static function detachPayment($id) {
        return WebShieldPayment::detach($id);
    }
}
