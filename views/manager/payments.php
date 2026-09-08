<?php require_once __DIR__ . '/../layout_header.php';
$webshield = $data['webshield'] ?? null;
$attached_payments = $data['payments'] ?? [];
?>

<div class="page-intro-actions"><a class="btn btn-info" href="index.php?action=restrictions&shield_id=<?= (int) $webshield['id'] ?>">Restrictions</a></div>

<div class="page-intro"><div class="eyebrow">PHƯƠNG THỨC THANH TOÁN</div><h2><?= htmlspecialchars($webshield['name']) ?></h2><p>Quản lý thông tin kết nối và môi trường thanh toán cho website này.</p></div>
<div class="card">
    <div class="section-heading"><h3>Phương thức đã kết nối</h3><span class="badge neutral"><?= count($attached_payments) ?> phương thức</span></div>
    <table class="data-table mt-3">
        <thead>
            <tr>
                <th>Loại thanh toán</th>
                <th>Trạng thái</th><th>Thao tác</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($attached_payments as $p): ?>
                <tr>
                    <td><div class="payment-heading"><span class="payment-logo" aria-hidden="true"><?= htmlspecialchars(strtoupper(substr($p['payment_name'], 0, 1))) ?></span><div><strong><?= htmlspecialchars($p['payment_name']) ?></strong><small>Cấu hình kết nối thanh toán</small></div></div></td>
                    <td><span class="badge <?= $p['active'] ? '' : 'neutral' ?>"><?= $p['active'] ? 'Đang bật' : 'Đã tắt' ?></span></td>
                    <td>
                        <button class="btn btn-sm btn-info" data-toggle="modal" data-target="#config-form-<?= $p['id'] ?>" data-title="Cấu hình <?= htmlspecialchars($p['payment_name']) ?>">Cấu hình</button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="hidden-forms" style="display: none;">
        <?php foreach ($attached_payments as $p): ?>
            <div id="config-form-<?= $p['id'] ?>">
                <?php
                $config = PaymentController::getConfig($p);
                ?>
                <form method="post" action="index.php?action=manager_save_payment&wsp_id=<?= $p['id'] ?>">
                    <?php if ($p['payment_code'] === 'paypal'): ?>
                        <div class="mb-3">
                            <label>Môi trường</label>
                            <select name="environment" class="form-control">
                                <option value="sandbox" <?= ($config && $config['environment'] == 'sandbox') ? 'selected' : '' ?>>Sandbox</option>
                                <option value="live" <?= ($config && $config['environment'] == 'live') ? 'selected' : '' ?>>Live</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label>Client ID</label>
                            <input name="client_id" class="form-control" value="<?= htmlspecialchars($config['client_id'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label>Secret ID</label>
                            <input name="secret_id" class="form-control" value="<?= htmlspecialchars($config['secret_id'] ?? '') ?>">
                        </div>
                    <?php elseif ($p['payment_code'] === 'stripe'): ?>
                        <div class="mb-3"><label>API Key</label><input name="api_key" class="form-control" value="<?= htmlspecialchars($config['api_key'] ?? '') ?>"></div>
                        <div class="mb-3"><label>Publishable Key</label><input name="publishable_key" class="form-control" value="<?= htmlspecialchars($config['publishable_key'] ?? '') ?>"></div>
                    <?php elseif ($p['payment_code'] === 'momo'): ?>
                        <div class="mb-3"><label>Partner Code</label><input name="partner_code" class="form-control" value="<?= htmlspecialchars($config['partner_code'] ?? '') ?>"></div>
                        <div class="mb-3"><label>Access Key</label><input name="access_key" class="form-control" value="<?= htmlspecialchars($config['access_key'] ?? '') ?>"></div>
                        <div class="mb-3"><label>Secret Key</label><input name="secret_key" class="form-control" value="<?= htmlspecialchars($config['secret_key'] ?? '') ?>"></div>
                        <div class="mb-3"><label>Môi trường</label><select name="environment" class="form-control"><option value="sandbox">Sandbox</option><option value="production">Production</option></select></div>
                    <?php endif; ?>
                    <div class="mt-3">
                        <button type="submit" class="btn btn-primary">Lưu</button>
                    </div>
                </form>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- The Modal -->
    <div id="configModal" class="modal">
        <!-- Modal content -->
        <div class="modal-content">
            <div class="modal-header">
                <span class="close">&times;</span>
                <h4 id="modalTitle"></h4>
            </div>
            <div class="modal-body" id="modalBody">
                <!-- Config form will be injected here -->
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../layout_footer.php'; ?>
