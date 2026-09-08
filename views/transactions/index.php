<?php require_once __DIR__ . '/../layout_header.php';
$filters = $data['filters'] ?? [];
$summary = $data['summary'] ?? [];
?>
<div class="card">
    <h2>Payment reports</h2>
    <form method="get" class="transaction-filters">
        <input type="hidden" name="action" value="transactions">
        <div class="transaction-filter-field">
            <label class="transaction-filter-label" for="transaction-shield">Shield</label>
            <select id="transaction-shield" name="shield_id" class="form-control"><option value="">All shields</option><?php foreach (($data['webshields'] ?? []) as $shield): ?><option value="<?= $shield['id'] ?>" <?= ((string) $filters['shield_id'] === (string) $shield['id']) ? 'selected' : '' ?>><?= htmlspecialchars($shield['name']) ?></option><?php endforeach; ?></select>
        </div>
        <div class="transaction-filter-field">
            <label class="transaction-filter-label" for="transaction-provider">Provider</label>
            <select id="transaction-provider" name="provider" class="form-control"><option value="">All providers</option><?php foreach (['paypal', 'stripe', 'momo'] as $provider): ?><option value="<?= $provider ?>" <?= ($filters['provider'] === $provider) ? 'selected' : '' ?>><?= strtoupper($provider) ?></option><?php endforeach; ?></select>
        </div>
        <div class="transaction-filter-field">
            <label class="transaction-filter-label" for="transaction-status">Status</label>
            <select id="transaction-status" name="status" class="form-control"><option value="">All statuses</option><?php foreach (['COMPLETED','CAPTURED','AUTHORIZED','REFUNDED','VOIDED','UNKNOWN'] as $status): ?><option value="<?= $status ?>" <?= ($filters['status'] === $status) ? 'selected' : '' ?>><?= $status ?></option><?php endforeach; ?></select>
        </div>
        <div class="transaction-filter-field">
            <label class="transaction-filter-label" for="transaction-from">From</label>
            <input id="transaction-from" type="date" name="from" value="<?= htmlspecialchars($filters['from']) ?>" class="form-control">
        </div>
        <div class="transaction-filter-field">
            <label class="transaction-filter-label" for="transaction-to">To</label>
            <input id="transaction-to" type="date" name="to" value="<?= htmlspecialchars($filters['to']) ?>" class="form-control">
        </div>
        <div class="transaction-filter-actions">
            <button class="btn btn-primary" type="submit">Filter</button>
            <a class="btn transaction-filter-clear" href="index.php?action=transactions">Today</a>
        </div>
    </form>
</div>
<div class="card">
    <p>Total: <?= (int) ($summary['total_transactions'] ?? 0) ?> | Successful: <?= (int) ($summary['successful_transactions'] ?? 0) ?> | Amount: <?= number_format((float) ($summary['successful_amount'] ?? 0), 2) ?> | Provider fees: <?= number_format((float) ($summary['provider_fees'] ?? 0), 2) ?> | Payout: <?= number_format((float) ($summary['payout'] ?? 0), 2) ?></p>
    <table class="data-table"><thead><tr><th>Date</th><th>Provider</th><th>Shield</th><th>Merchant</th><th>Woo order</th><th>Provider transaction</th><th>Action</th><th>Status</th><th>Amount</th><th>Currency</th></tr></thead><tbody>
    <?php foreach (($data['transactions'] ?? []) as $transaction): ?><tr>
        <td><?= htmlspecialchars($transaction['last_occurred_at'] ?? '') ?></td><td><?= htmlspecialchars($transaction['payment_provider']) ?></td><td><?= htmlspecialchars($transaction['shield_name'] ?? '') ?></td><td><?= htmlspecialchars($transaction['merchant_domain']) ?></td><td><?= htmlspecialchars($transaction['wc_order_number'] ?: $transaction['wc_order_id']) ?></td><td><?= htmlspecialchars($transaction['provider_transaction_id'] ?? '') ?></td><td><?= htmlspecialchars($transaction['payment_action']) ?></td><td><?= htmlspecialchars($transaction['status']) ?></td><td><?= htmlspecialchars((string) $transaction['amount']) ?></td><td><?= htmlspecialchars($transaction['currency'] ?? '') ?></td>
    </tr><?php endforeach; ?></tbody></table>
</div>
<?php require_once __DIR__ . '/../layout_footer.php'; ?>
