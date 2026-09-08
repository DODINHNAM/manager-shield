<?php
require_once __DIR__ . '/../includes/db.php';

class StripeConfig {
    public static function findByPayment($wspId) {
        $rows = db_query("SELECT * FROM stripe_configs WHERE web_shield_payment_id = ?", [$wspId]);
        return $rows[0] ?? null;
    }

    public static function saveSettings($wspId, array $data) {
        $existing = self::findByPayment($wspId) ?? [];
        $environment = $data['environment'] ?? 'test';
        if (!in_array($environment, ['test', 'live'], true)) {
            throw new InvalidArgumentException('Stripe mode must be Test or Live.');
        }
        $settings = ['environment' => $environment];
        foreach (['payment_method_all_enable', 'enable_max_order_value', 'enable_random_order_no'] as $field) {
            $settings[$field] = empty($data[$field]) ? 0 : 1;
        }
        $amount = $data['max_order_value'] ?? ($existing['max_order_value'] ?? '100');
        if (!is_scalar($amount) || !preg_match('/^\d{1,16}(\.\d{1,2})?$/D', (string) $amount) || (float) $amount <= 0) {
            throw new InvalidArgumentException('Max Order Value must be positive with at most 2 decimal places.');
        }
        $settings['max_order_value'] = $amount;
        $length = filter_var($data['random_order_no_length'] ?? ($existing['random_order_no_length'] ?? 16), FILTER_VALIDATE_INT);
        if ($length === false || $length < 8 || $length > 64) {
            throw new InvalidArgumentException('Random Order No. Length must be between 8 and 64.');
        }
        $settings['random_order_no_length'] = $length;
        foreach (['test', 'live'] as $mode) {
            foreach (['publishable_key' => 'pk', 'secret_key' => 'sk'] as $name => $prefix) {
                $field = $mode . '_' . $name;
                $value = $data[$field] ?? '';
                if (!is_string($value)) throw new InvalidArgumentException('Invalid Stripe key.');
                $value = trim($value);
                if ($value === '') $value = $existing[$field] ?? '';
                if ($value !== '' && (strlen($value) > 255 || strpos($value, $prefix . '_' . $mode . '_') !== 0)) {
                    throw new InvalidArgumentException('Invalid ' . $field . '.');
                }
                $settings[$field] = $value;
            }
        }
        if ($settings[$environment . '_secret_key'] === '' || $settings[$environment . '_publishable_key'] === '') {
            throw new InvalidArgumentException('Enter both Stripe keys for the selected mode.');
        }
        $settings['api_key'] = $settings[$environment . '_secret_key'];
        $settings['publishable_key'] = $settings[$environment . '_publishable_key'];
        $fields = array_keys($settings);
        $values = array_values($settings);
        if ($existing) {
            $values[] = $wspId;
            return db_execute('UPDATE stripe_configs SET ' . implode(', ', array_map(static fn($field) => $field . ' = ?', $fields)) . ' WHERE web_shield_payment_id = ?', $values);
        }
        $fields[] = 'web_shield_payment_id';
        $values[] = $wspId;
        return db_execute('INSERT INTO stripe_configs (' . implode(', ', $fields) . ') VALUES (' . implode(', ', array_fill(0, count($fields), '?')) . ')', $values);
    }
}
