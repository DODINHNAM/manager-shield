<?php
require_once __DIR__ . '/../models/EndpointRotationConfig.php';

header('Content-Type: application/json');

function endpointRotationJson($payload, $status = 200) {
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

function endpointRotationConfig() {
    $body = json_decode(file_get_contents('php://input'), true);
    $token = $body['ep_token'] ?? $_POST['ep_token'] ?? $_GET['ep_token'] ?? '';
    $config = EndpointRotationConfig::authenticateToken($token);
    if (!$config) endpointRotationJson(['status' => 'failed', 'code' => 'INVALID_ENDPOINT_TOKEN'], 401);
    $provider = endpointRotationProvider($body['payment_gateway'] ?? '');
    if ($provider !== '' && $provider !== $config['payment_provider']) {
        endpointRotationJson(['status' => 'failed', 'code' => 'PAYMENT_PROVIDER_MISMATCH'], 409);
    }
    return [$config, $body ?: []];
}

function endpointRotationProvider($value) {
    $value = strtolower(trim((string) $value));
    return ['1' => 'paypal', '2' => 'stripe', '3' => 'momo'][$value] ?? $value;
}

function endpointRotationMembers($configId, $merchantDomain = '') {
    $merchantDomain = endpointRotationDomain($merchantDomain);
    return array_values(array_filter(EndpointRotationConfig::members($configId), static function ($member) use ($merchantDomain) {
        if ($merchantDomain !== '' && !endpointRotationIsWhitelisted($member['web_shield_id'], $merchantDomain)) return false;
        return (int) $member['active'] === 1;
    }));
}

function endpointRotationShield($member) {
    $domain = strtolower(trim((string) ($member['shield_domain'] ?? '')));
    $parsed = parse_url(strpos($domain, '://') === false ? 'https://' . $domain : $domain);
    return [
        'id' => (int) $member['web_shield_id'],
        'shield_id' => (int) $member['web_shield_id'],
        'shield_name' => $member['shield_name'],
        'shield_domain' => strtolower($parsed['host'] ?? $domain),
    ];
}

function endpointRotationDomain($value) {
    $value = strtolower(trim((string) $value));
    if ($value === '') return '';
    $parsed = parse_url(strpos($value, '://') === false ? 'https://' . $value : $value);
    $host = strtolower($parsed['host'] ?? $value);
    return preg_replace('/^www\./', '', $host);
}

function endpointRotationIsWhitelisted($shieldId, $merchantDomain) {
    $rows = db_query("SELECT domain FROM manager_whitelist_domains WHERE web_shield_id = ? AND active = 1", [(int) $shieldId]);
    foreach ($rows as $row) {
        if (endpointRotationDomain($row['domain'] ?? '') === $merchantDomain) return true;
    }
    return false;
}

function endpointRotationResetDaily($members) {
    $today = date('Y-m-d');
    foreach ($members as $member) {
        if (($member['paid_date'] ?? null) !== $today) {
            db_execute("UPDATE endpoint_rotation_members SET paid_amount = 0, paid_date = ? WHERE id = ?", [$today, $member['id']]);
            $member['paid_amount'] = 0;
            $member['paid_date'] = $today;
        }
    }
    return $members;
}

function endpointRotationSelect($config, $members, $orderTotal) {
    if (!$members) endpointRotationJson(['status' => 'failed', 'code' => 'EMPTY_SHIELDS'], 404);
    $members = endpointRotationResetDaily($members);
    $currentId = (int) ($config['current_member_id'] ?? 0);
    $currentIndex = -1;
    foreach ($members as $index => $member) {
        if ((int) $member['id'] === $currentId) { $currentIndex = $index; break; }
    }

    if ($config['rotation_method'] === 'by_time') {
        if ($currentIndex < 0) {
            $selected = $members[0];
            db_execute("UPDATE endpoint_rotation_configs SET current_member_id = ?, current_rotation_at = NOW() WHERE id = ?", [$selected['id'], $config['id']]);
            return $selected;
        }
        $elapsed = time() - strtotime($config['current_rotation_at'] ?? 'now');
        if ($elapsed >= ((float) $members[$currentIndex]['rotation_value'] * 60)) {
            $selected = $members[($currentIndex + 1) % count($members)];
            db_execute("UPDATE endpoint_rotation_configs SET current_member_id = ?, current_rotation_at = NOW() WHERE id = ?", [$selected['id'], $config['id']]);
            return $selected;
        }
        return $members[$currentIndex];
    }

    for ($offset = 0; $offset < count($members); $offset++) {
        $member = $members[($currentIndex + 1 + $offset) % count($members)];
        if ((float) $member['paid_amount'] + (float) $orderTotal < (float) $member['rotation_value']) {
            db_execute("UPDATE endpoint_rotation_configs SET current_member_id = ? WHERE id = ?", [$member['id'], $config['id']]);
            return $member;
        }
    }
    endpointRotationJson(['status' => 'failed', 'code' => 'EMPTY_SHIELDS', 'message' => 'All shields reached their daily amount'], 409);
}

function endpointRotationGetShield() {
    [$config, $body] = endpointRotationConfig();
    $merchantDomain = endpointRotationDomain($body['merchant_site'] ?? '');
    if ($merchantDomain === '') endpointRotationJson(['status' => 'failed', 'code' => 'MERCHANT_DOMAIN_REQUIRED'], 400);
    $members = endpointRotationMembers($config['id'], $merchantDomain);
    if (!$members) endpointRotationJson(['status' => 'failed', 'code' => 'MERCHANT_NOT_WHITELISTED'], 403);
    $member = endpointRotationSelect($config, $members, (float) ($body['order_total'] ?? 0));
    endpointRotationJson(['status' => 'success', 'shield' => endpointRotationShield($member)]);
}

function endpointRotationPerform() {
    [$config, $body] = endpointRotationConfig();
    $processing = $body['shield_processing'] ?? [];
    $shieldId = (int) ($processing['id'] ?? $processing['shield_id'] ?? 0);
    $merchantDomain = endpointRotationDomain($body['merchant_site'] ?? '');
    $members = endpointRotationResetDaily(endpointRotationMembers($config['id'], $merchantDomain));
    $member = null;
    foreach ($members as $candidate) {
        if ((int) $candidate['web_shield_id'] === $shieldId || strtolower((string) $candidate['shield_domain']) === strtolower((string) ($processing['shield_domain'] ?? ''))) {
            $member = $candidate;
            break;
        }
    }
    if (!$member) endpointRotationJson(['status' => 'failed', 'code' => 'SHIELD_NOT_FOUND'], 404);
    $total = (float) ($body['order_total'] ?? 0);
    if ($config['rotation_method'] === 'by_amount') {
        db_execute("UPDATE endpoint_rotation_members SET paid_amount = paid_amount + ?, paid_date = ? WHERE id = ?", [$total, date('Y-m-d'), $member['id']]);
    }
    $fresh = EndpointRotationConfig::findForApi($config['id']);
    endpointRotationSelect($fresh, endpointRotationMembers($config['id'], $merchantDomain), 0);
    endpointRotationJson(['status' => 'success']);
}

function endpointRotationMoveUnused() {
    [$config, $body] = endpointRotationConfig();
    $domain = endpointRotationDomain($body['shield_domain'] ?? '');
    $members = endpointRotationMembers($config['id']);
    foreach ($members as $member) {
        if ((int) $member['web_shield_id'] === (int) ($body['shield_id'] ?? 0) || endpointRotationDomain($member['shield_domain']) === $domain) {
            db_execute("UPDATE endpoint_rotation_members SET active = 0 WHERE id = ?", [$member['id']]);
        }
    }
    db_execute("UPDATE endpoint_rotation_configs SET current_member_id = NULL, current_rotation_at = NULL WHERE id = ?", [$config['id']]);
    endpointRotationJson(['status' => 'success']);
}

switch ($_GET['endpoint'] ?? '') {
    case 'get-shield-process': endpointRotationGetShield(); break;
    case 'perform-rotate-shield-by-amount': endpointRotationPerform(); break;
    case 'set-next-shield': endpointRotationGetShield(); break;
    case 'move-to-unused-shield': endpointRotationMoveUnused(); break;
    default: endpointRotationJson(['status' => 'failed', 'code' => 'NOT_FOUND'], 404);
}
