<?php require_once __DIR__ . '/../layout_header.php';
$webshield = $data['webshield'] ?? null;
$attached_payments = $data['payments'] ?? [];
?>

<div class="card">
    <h3>Cấu hình thanh toán cho <?= htmlspecialchars($webshield['name']) ?></h3>
    <table class="data-table mt-3">
        <thead>
            <tr>
                <th>Loại thanh toán</th>
                <th>Thao tác</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($attached_payments as $p): ?>
                <tr>
                    <td><?= htmlspecialchars($p['payment_name']) ?></td>
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