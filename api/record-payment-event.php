<?php
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

function payment_event_domain($value) {
    $value = strtolower(trim((string) $value));
    if ($value === '') {
        return '';
    }
    $parsed = parse_url(strpos($value, '://') === false ? 'https://' . $value : $value);
    return strtolower($parsed['host'] ?? preg_replace('/:\d+$/', '', $value));
}

$origin = $_SERVER['HTTP_ORIGIN'] ?? ($_SERVER['HTTP_REFERER'] ?? '');
$shieldDomain = payment_event_domain($origin);
$shieldDomain = preg_replace('/^www\\./', '', $shieldDomain);
if ($shieldDomain === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing shield origin']);
    exit;
}

$shields = db_query(
    "SELECT ws.id, ws.name, ws.domain, ws.manager_id, u.username AS manager_name
     FROM web_shields ws LEFT JOIN users u ON u.id = ws.manager_id
     WHERE LOWER(REPLACE(ws.domain, 'www.', '')) = ? LIMIT 1",
    [$shieldDomain]
);
$shield = $shields[0] ?? null;
if (!$shield) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Shield is not registered']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON payload']);
    exit;
}

$merchantDomain = payment_event_domain($payload['merchant_domain'] ?? '');
$merchantDomain = preg_replace('/^www\\./', '', $merchantDomain);
$provider = preg_replace('/[^a-z0-9_-]/i', '', (string) ($payload['payment_provider'] ?? 'paypal'));
$action = preg_replace('/[^a-z0-9_-]/i', '', (string) ($payload['payment_action'] ?? ''));
$status = preg_replace('/[^a-z0-9_-]/i', '', (string) ($payload['status'] ?? 'UNKNOWN'));
if ($merchantDomain === '' || $provider === '' || $action === '' || $status === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Missing transaction fields']);
    exit;
}

$allowed = db_query(
    "SELECT id FROM manager_whitelist_domains
     WHERE web_shield_id = ? AND active = 1
       AND LOWER(REPLACE(domain, 'www.', '')) = ? LIMIT 1",
    [$shield['id'], $merchantDomain]
);
if (!$allowed) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Merchant domain is not whitelisted']);
    exit;
}

$providerOrderId = (string) ($payload['provider_order_id'] ?? '');
$providerTransactionId = (string) ($payload['provider_transaction_id'] ?? '');
$wcOrderId = (string) ($payload['wc_order_id'] ?? '');
$transactionKey = hash('sha256', implode('|', [$shield['id'], $provider, $merchantDomain, $wcOrderId, $providerOrderId, $providerTransactionId, $action]));
$eventKey = preg_replace('/[^a-f0-9]/', '', strtolower((string) ($payload['event_key'] ?? '')));
if (strlen($eventKey) !== 64) {
    $eventKey = hash('sha256', implode('|', [$transactionKey, $status, (string) ($payload['occurred_at'] ?? '')]));
}

$occurredAt = !empty($payload['occurred_at']) ? date('Y-m-d H:i:s', strtotime($payload['occurred_at'])) : date('Y-m-d H:i:s');
$amount = is_numeric($payload['amount'] ?? null) ? (float) $payload['amount'] : null;
$fee = is_numeric($payload['fee'] ?? ($payload['provider_fee'] ?? null)) ? (float) ($payload['fee'] ?? $payload['provider_fee']) : null;
$payout = is_numeric($payload['payout'] ?? null) ? (float) $payload['payout'] : null;
$currency = strtoupper(substr((string) ($payload['currency'] ?? ''), 0, 3)) ?: null;
$safePayload = $payload;
unset($safePayload['client_secret'], $safePayload['secret'], $safePayload['access_token']);

// Insert the event first. Retries with the same event key are acknowledged without duplication.
db_execute(
    "INSERT IGNORE INTO payment_events
        (event_key, transaction_key, web_shield_id, shield_domain, manager_id, manager_name,
         merchant_domain, payment_provider, payment_action, status, payload, occurred_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
    [$eventKey, $transactionKey, $shield['id'], $shield['domain'], $shield['manager_id'], $shield['manager_name'],
     $merchantDomain, $provider, $action, $status, json_encode($safePayload), $occurredAt]
);

db_execute(
    "INSERT INTO payment_transactions
        (transaction_key, web_shield_id, shield_name, shield_domain, manager_id, manager_name,
         merchant_domain, wc_order_id, wc_order_number, payment_provider, provider_order_id,
         provider_transaction_id, payment_action, status, amount, currency, provider_fee, payout,
         error_message, first_occurred_at, last_occurred_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE
         status = VALUES(status), amount = COALESCE(VALUES(amount), amount),
         currency = COALESCE(VALUES(currency), currency), provider_fee = COALESCE(VALUES(provider_fee), provider_fee),
         payout = COALESCE(VALUES(payout), payout), payment_action = VALUES(payment_action),
         error_message = VALUES(error_message), last_occurred_at = VALUES(last_occurred_at)",
    [$transactionKey, $shield['id'], $shield['name'], $shield['domain'], $shield['manager_id'], $shield['manager_name'],
     $merchantDomain, $wcOrderId, (string) ($payload['wc_order_number'] ?? $wcOrderId), $provider,
     $providerOrderId, $providerTransactionId, $action, $status, $amount, $currency, $fee, $payout,
     substr((string) ($payload['error_message'] ?? ''), 0, 65535), $occurredAt, $occurredAt]
);

echo json_encode(['success' => true]);
