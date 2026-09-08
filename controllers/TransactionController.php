<?php
require_once __DIR__ . '/../models/PaymentTransaction.php';
require_once __DIR__ . '/../models/WebShield.php';

class TransactionController {
    public static function index() {
        requireLogin();
        $user = currentUser();
        $filters = [
            'shield_id' => $_GET['shield_id'] ?? '',
            'provider' => $_GET['provider'] ?? '',
            'status' => $_GET['status'] ?? '',
            'from' => $_GET['from'] ?? date('Y-m-d'),
            'to' => $_GET['to'] ?? date('Y-m-d'),
        ];
        $managerId = $user['role'] === 'admin' ? null : $user['id'];
        $data = [
            'transactions' => PaymentTransaction::all($managerId, $filters),
            'summary' => PaymentTransaction::summary($managerId, $filters),
            'webshields' => $managerId === null ? WebShield::all() : WebShield::byManager($managerId),
            'filters' => $filters,
        ];
        require __DIR__ . '/../views/transactions/index.php';
    }
}
