<?php
// Shared by the manager and admin payment dialogs.
$stripe = $config ?? [];
$_SESSION['payment_csrf'] = $_SESSION['payment_csrf'] ?? bin2hex(random_bytes(32));
$stripeMode = $stripe['environment'] ?? 'test';
$isStripeAdmin = (($user['role'] ?? '') === 'admin');
$maskStripeKey = static function ($key) {
    $key = (string) $key;
    if ($key === '') return 'Not configured';
    if (strlen($key) <= 8) return str_repeat('*', strlen($key));
    return substr($key, 0, 4) . '...' . substr($key, -4);
};
?>
<input type="hidden" name="payment_csrf" value="<?= htmlspecialchars($_SESSION['payment_csrf']) ?>">
<div class="stripe-settings">
    <div class="mb-3"><label>Webhook</label>
        <button type="button" disabled class="btn btn-info">Create webhook</button>
        <small>Cần hoàn tất kết nối Stripe trên shield để tạo webhook.</small>
    </div>
    <label class="stripe-option">Payment Method All Enable:
        <input type="checkbox" name="payment_method_all_enable" value="1" <?= !empty($stripe['payment_method_all_enable']) ? 'checked' : '' ?>>
    </label>
    <div class="mb-3"><label>Mode</label><select name="environment" class="form-control" data-stripe-mode>
        <option value="test" <?= $stripeMode === 'test' ? 'selected' : '' ?>>TEST</option>
        <option value="live" <?= $stripeMode === 'live' ? 'selected' : '' ?>>LIVE</option>
    </select></div>
    <label class="stripe-option">Enable Max Order Value:
        <input type="checkbox" name="enable_max_order_value" value="1" data-stripe-toggle="max_order_value" <?= !empty($stripe['enable_max_order_value']) ? 'checked' : '' ?>>
    </label>
    <div class="mb-3"><label>Max Order Value</label>
        <input type="number" name="max_order_value" class="form-control" min="0.01" step="0.01" value="<?= htmlspecialchars($stripe['max_order_value'] ?? '100') ?>">
    </div>
    <label class="stripe-option">Enable Random Order No.:
        <input type="checkbox" name="enable_random_order_no" value="1" data-stripe-toggle="random_order_no_length" <?= !empty($stripe['enable_random_order_no']) ? 'checked' : '' ?>>
    </label>
    <div class="mb-3"><label>Random Order No. Length</label>
        <input type="number" name="random_order_no_length" class="form-control" min="8" max="64" step="1" value="<?= (int) ($stripe['random_order_no_length'] ?? 16) ?>">
    </div>
    <?php foreach (['test' => 'Test', 'live' => 'Live'] as $mode => $modeLabel): ?>
    <div data-stripe-keys="<?= $mode ?>" <?= $stripeMode !== $mode ? 'hidden' : '' ?>>
        <div class="mb-3"><label><?= $modeLabel ?> Publishable Key</label>
            <?php if ($isStripeAdmin): ?>
                <div class="stripe-key-preview"><code><?= htmlspecialchars($maskStripeKey($stripe[$mode . '_publishable_key'] ?? '')) ?></code></div>
                <input name="<?= $mode ?>_publishable_key" class="form-control" maxlength="255" placeholder="Leave blank to keep the current key">
            <?php else: ?>
                <input name="<?= $mode ?>_publishable_key" class="form-control" maxlength="255" placeholder="pk_<?= $mode ?>_..." value="<?= htmlspecialchars($stripe[$mode . '_publishable_key'] ?? '') ?>">
            <?php endif; ?>
        </div>
        <div class="mb-3"><label><?= $modeLabel ?> Secret Key</label>
            <?php if ($isStripeAdmin): ?>
                <div class="stripe-key-preview"><code><?= htmlspecialchars($maskStripeKey($stripe[$mode . '_secret_key'] ?? '')) ?></code></div>
            <?php endif; ?>
            <input type="password" name="<?= $mode ?>_secret_key" class="form-control" maxlength="255" autocomplete="new-password" placeholder="<?= !empty($stripe[$mode . '_secret_key']) ? 'Đã lưu — để trống để giữ key hiện tại' : 'sk_' . $mode . '_...' ?>">
        </div>
    </div>
    <?php endforeach; ?>
</div>
