<?php require_once __DIR__ . '/../layout_header.php';
$webshield = $data['webshield'] ?? null;
$managers = $data['managers'] ?? [];
$payment_types = $data['payment_types'] ?? [];
$attached_payments = $data['attached_payments'] ?? [];
?>

<div class="card">
    <h3>Chỉnh sửa Web Shield</h3>
    <form method="post" action="index.php?action=admin_update_webshield">
        <input type="hidden" name="id" value="<?= $webshield['id'] ?>">
        <div class="mb-3">
            <label class="form-label">Tên</label>
            <input name="name" class="form-control" value="<?= htmlspecialchars($webshield['name']) ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Domain</label>
            <input name="domain" class="form-control" value="<?= htmlspecialchars($webshield['domain']) ?>">
        </div>
        <div class="mb-3">
            <label class="form-label">Người quản lý</label>
            <select name="manager_id" class="form-control">
                <option value="">-- Chọn manager --</option>
                <?php foreach ($managers as $m): ?>
                    <option value="<?= $m['id'] ?>" <?= ($webshield['manager_id'] == $m['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($m['username']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mt-3">
          <button type="submit" class="btn btn-primary">Lưu</button>
        </div>
    </form>
</div>

<div class="card mt-3">
    <h3>Thanh toán</h3>
    <form method="post" action="index.php?action=admin_attach_payment">
        <input type="hidden" name="web_shield_id" value="<?= $webshield['id'] ?>">
        <div class="mb-3">
            <label class="form-label">Loại thanh toán</label>
            <select name="payment_type_id" class="form-control">
                <?php foreach ($payment_types as $pt): ?>
                    <option value="<?= $pt['id'] ?>"><?= htmlspecialchars($pt['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mt-3">
          <button type="submit" class="btn btn-primary">Đính kèm</button>
        </div>
    </form>
    <ul class="mt-2">
        <?php foreach ($attached_payments as $p): ?>
            <li>
                <?= htmlspecialchars($p['payment_name']) ?>
                <a href="index.php?action=admin_detach_payment&id=<?= $p['id'] ?>" class="btn btn-sm btn-danger ms-2">Gỡ bỏ</a>
                <?php
                // Display configuration if web shield has a manager
                if ($webshield['manager_id']) {
                    require_once __DIR__ . '/../../controllers/PaymentController.php';
                    require_once __DIR__ . '/../../models/PayPalConfig.php';
                    require_once __DIR__ . '/../../models/StripeConfig.php';
                    require_once __DIR__ . '/../../models/MomoConfig.php';

                    $config = PaymentController::getConfig($p);
                    if ($config) {
                        echo '<div class="ms-4 mt-2 p-2 border rounded">';
                        echo '<h5>Cấu hình:</h5>';
                        switch ($p['payment_code']) {
                            case 'paypal':
                                echo '<p>Môi trường: ' . htmlspecialchars($config['environment']) . '</p>';
                                echo '<p>Client ID: ' . htmlspecialchars($config['client_id']) . '</p>';
                                echo '<p>Secret ID: ' . htmlspecialchars($config['secret_id']) . '</p>';
                                break;
                            case 'stripe':
                                echo '<p>API Key: ' . htmlspecialchars($config['api_key']) . '</p>';
                                echo '<p>Publishable Key: ' . htmlspecialchars($config['publishable_key']) . '</p>';
                                break;
                            case 'momo':
                                echo '<p>Partner Code: ' . htmlspecialchars($config['partner_code']) . '</p>';
                                echo '<p>Access Key: ' . htmlspecialchars($config['access_key']) . '</p>';
                                echo '<p>Secret Key: ' . htmlspecialchars($config['secret_key']) . '</p>';
                                echo '<p>Môi trường: ' . htmlspecialchars($config['environment']) . '</p>';
                                break;
                        }
                        echo '</div>';
                    }
                }
                ?>
            </li>
        <?php endforeach; ?>
    </ul>
</div>

<?php require_once __DIR__ . '/../layout_footer.php'; ?>