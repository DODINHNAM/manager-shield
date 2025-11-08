<?php
// api/get-webshield-config.php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/encrypt.php';
require_once __DIR__ . '/../models/WebShieldPayment.php';
require_once __DIR__ . '/../models/MomoConfig.php';
require_once __DIR__ . '/../models/PayPalConfig.php';
require_once __DIR__ . '/../models/StripeConfig.php';

header('Content-Type: application/json');

// 1️⃣ Xác định domain gọi đến
$origin = $_SERVER['HTTP_ORIGIN'] ?? ($_SERVER['HTTP_REFERER'] ?? '');
if ($origin) {
    $parsed = parse_url($origin);
    $domain = strtolower($parsed['host'] ?? '');
} else {
    $domain = $_SERVER['SERVER_NAME'] ?? '';
}
$domain = preg_replace('/^www\./', '', $domain);

if (!$domain) {
    http_response_code(400);
    echo json_encode(['error' => 'Cannot detect calling domain']);
    exit;
}


// 2️⃣ Tìm Web Shield tương ứng domain này
$webShield = db_query("SELECT * FROM web_shields WHERE domain = ? LIMIT 1", [$domain]);
if (!$webShield) {
    http_response_code(404);
    echo json_encode(['error' => 'Web Shield not found for domain', 'domain' => $domain]);
    exit;
}
$webShield = $webShield[0];

// 3️⃣ Xác định manager_id
$managerId = $webShield['manager_id'] ?? null;
if (!$managerId) {
    http_response_code(200);
    echo json_encode([
        'success' => false,
        'message' => 'Web Shield has not been assigned to any manager yet.',
        'web_shield' => [
            'id' => $webShield['id'],
            'domain' => $webShield['domain'],
            'name' => $webShield['name']
        ]
    ]);
    exit;
}

// 4️⃣ Lấy danh sách domain whitelist (chỉ active)
$rows = db_query("
    SELECT domain 
    FROM manager_whitelist_domains 
    WHERE manager_id = ? AND active = 1
    ORDER BY domain ASC
", [$managerId]);

$whitelist = array_map(fn($r) => $r['domain'], $rows);

// 5️⃣ Lấy và mã hóa cấu hình thanh toán
$payment_configs = [];
$payments = WebShieldPayment::findByWebShield($webShield['id']);

foreach ($payments as $payment) {
    if (!$payment['active']) continue;

    $config = null;
    switch ($payment['payment_code']) {
        case 'momo':
            $config = MomoConfig::findByPayment($payment['id']);
            break;
        case 'paypal':
            $config = PayPalConfig::findByPayment($payment['id']);
            break;
        case 'stripe':
            $config = StripeConfig::findByPayment($payment['id']);
            break;
    }

    if ($config) {
        // Xóa các khóa không cần thiết trước khi mã hóa
        unset($config['id'], $config['web_shield_payment_id']);
        
        $payment_configs[] = [
            'type' => $payment['payment_code'],
            'config' => encrypt_data($config)
        ];
    }
}


// 6️⃣ Trả JSON response
echo json_encode([
    'success' => true,
    'web_shield' => [
        'id' => $webShield['id'],
        'domain' => $webShield['domain'],
        'name' => $webShield['name'],
        'manager_id' => $managerId
    ],
    'whitelist' => $whitelist,
    'payment_configs' => $payment_configs
]);
