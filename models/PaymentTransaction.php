<?php
require_once __DIR__ . '/../includes/db.php';

class PaymentTransaction {
    private static function filters($managerId, $filters, &$params) {
        $where = ['1=1'];
        if ($managerId !== null) {
            $where[] = 'pt.manager_id = ?';
            $params[] = $managerId;
        }
        if (!empty($filters['shield_id'])) {
            $where[] = 'pt.web_shield_id = ?';
            $params[] = (int) $filters['shield_id'];
        }
        if (!empty($filters['provider'])) {
            $where[] = 'pt.payment_provider = ?';
            $params[] = preg_replace('/[^a-z0-9_-]/i', '', $filters['provider']);
        }
        if (!empty($filters['status'])) {
            $where[] = 'pt.status = ?';
            $params[] = preg_replace('/[^a-z0-9_-]/i', '', $filters['status']);
        }
        if (!empty($filters['from'])) {
            $where[] = 'pt.last_occurred_at >= ?';
            $params[] = $filters['from'] . ' 00:00:00';
        }
        if (!empty($filters['to'])) {
            $where[] = 'pt.last_occurred_at <= ?';
            $params[] = $filters['to'] . ' 23:59:59';
        }
        return implode(' AND ', $where);
    }

    public static function all($managerId = null, $filters = []) {
        $params = [];
        $where = self::filters($managerId, $filters, $params);
        return db_query("SELECT pt.* FROM payment_transactions pt
            WHERE $where ORDER BY pt.last_occurred_at DESC, pt.id DESC LIMIT 500", $params);
    }

    public static function summary($managerId = null, $filters = []) {
        $params = [];
        $where = self::filters($managerId, $filters, $params);
        $rows = db_query("SELECT COUNT(*) AS total_transactions,
                COALESCE(SUM(CASE WHEN pt.status IN ('COMPLETED','CAPTURED') THEN 1 ELSE 0 END), 0) AS successful_transactions,
                COALESCE(SUM(CASE WHEN pt.status IN ('COMPLETED','CAPTURED') THEN pt.amount ELSE 0 END), 0) AS successful_amount,
                COALESCE(SUM(pt.provider_fee), 0) AS provider_fees,
                COALESCE(SUM(pt.payout), 0) AS payout
            FROM payment_transactions pt
            WHERE $where", $params);
        return $rows[0] ?? [];
    }
}
