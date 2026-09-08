<?php
require_once __DIR__ . '/../includes/db.php';

class ShieldRestriction {
    public static function types() {
        return [
            'whitelist_domain' => 'Whitelist domains',
            'blacklist_email' => 'Blacklist emails',
            'blacklist_city' => 'Blacklist cities',
            'blacklist_state' => 'Blacklist states',
            'blacklist_zipcode' => 'Blacklist ZIP codes',
        ];
    }

    public static function listGlobal($managerId) {
        return db_query("SELECT * FROM shield_restrictions WHERE scope = 'global' AND manager_id = ? ORDER BY rule_type, rule_value", [(int) $managerId]);
    }

    public static function listLocal($shieldId) {
        return db_query("SELECT * FROM shield_restrictions WHERE scope = 'local' AND web_shield_id = ? ORDER BY rule_type, rule_value", [(int) $shieldId]);
    }

    public static function replace($scope, $managerId, $shieldId, $rules, $enabled = []) {
        if ($scope === 'global') {
            db_execute("DELETE FROM shield_restrictions WHERE scope = 'global' AND manager_id " . ($managerId === null ? 'IS NULL' : '= ?'), $managerId === null ? [] : [$managerId]);
        } else {
            db_execute("DELETE FROM shield_restrictions WHERE scope = 'local' AND web_shield_id = ?", [(int) $shieldId]);
        }
        foreach ($rules as $type => $values) {
            if (!array_key_exists($type, self::types())) continue;
            foreach ($values as $value) {
                $params = [$scope, $managerId, $scope === 'local' ? (int) $shieldId : null, $type, $value];
                db_execute("INSERT INTO shield_restrictions (scope, manager_id, web_shield_id, rule_type, rule_value, active)
                    VALUES (?, ?, ?, ?, ?, ?)", array_merge($params, [!empty($enabled[$type]) ? 1 : 0]));
            }
        }
    }

    private static function normalize($value, $type) {
        $value = strtolower(trim((string) $value));
        if ($type === 'whitelist_domain') {
            $parsed = parse_url(strpos($value, '://') === false ? 'https://' . $value : $value);
            $value = strtolower($parsed['host'] ?? $value);
            $value = preg_replace('/^www\./', '', $value);
        }
        return $value;
    }

    private static function valuesForShield($shieldId, $type) {
        $shieldRows = db_query("SELECT manager_id FROM web_shields WHERE id = ? LIMIT 1", [(int) $shieldId]);
        $managerId = $shieldRows[0]['manager_id'] ?? null;
        $rows = db_query("SELECT rule_type, rule_value FROM shield_restrictions
            WHERE active = 1 AND rule_type = ? AND (
                (scope = 'local' AND web_shield_id = ?)
                OR (scope = 'global' AND manager_id = ?)
            )", [$type, (int) $shieldId, $managerId]);
        return array_map(static function ($row) use ($type) {
            return self::normalize($row['rule_value'], $type);
        }, $rows);
    }

    public static function isWhitelisted($shieldId, $domain) {
        $domain = self::normalize($domain, 'whitelist_domain');
        if ($domain === '') return false;
        $legacy = db_query("SELECT domain FROM manager_whitelist_domains WHERE web_shield_id = ? AND active = 1", [(int) $shieldId]);
        foreach ($legacy as $row) {
            if (self::normalize($row['domain'], 'whitelist_domain') === $domain) return true;
        }
        return in_array($domain, self::valuesForShield($shieldId, 'whitelist_domain'), true);
    }

    public static function isBlocked($shieldId, $data) {
        $fields = [
            'blacklist_email' => $data['customer_email'] ?? '',
            'blacklist_city' => $data['billing_city'] ?? '',
            'blacklist_state' => $data['billing_state'] ?? '',
            'blacklist_zipcode' => $data['billing_postcode'] ?? '',
        ];
        foreach ($fields as $type => $value) {
            $value = self::normalize($value, $type);
            if ($value !== '' && in_array($value, self::valuesForShield($shieldId, $type), true)) return true;
        }
        return false;
    }
}
