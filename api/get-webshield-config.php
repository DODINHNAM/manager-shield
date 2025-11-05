<?php
// api/get-webshield-whitelist.php
require_once __DIR__ . '/../includes/db.php';

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

// 5️⃣ Trả JSON response
echo json_encode([
    'success' => true,
    'web_shield' => [
        'id' => $webShield['id'],
        'domain' => $webShield['domain'],
        'name' => $webShield['name'],
        'manager_id' => $managerId
    ],
    'whitelist' => $whitelist
]);
