<?php
// controllers/AdminController.php
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/WebShield.php';

class AdminController {
    public static function createUser($username, $password, $role='manager') {
        return User::create($username, $password, $role);
    }

    public static function updateUser($id, $username, $role) {
        return User::update($id, $username, $role);
    }

    public static function deleteUser($id) {
        return User::delete($id);
    }

    public static function createWebShield($name, $domain, $manager_id = null) {
        return WebShield::create($name, $domain, $manager_id);
    }

    public static function updateWebShield($id, $name, $domain, $manager_id=null) {
        return WebShield::update($id, $name, $domain, $manager_id);
    }

    public static function deleteWebShield($id) {
        return WebShield::delete($id);
    }
}
