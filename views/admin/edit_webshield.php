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
                    <a href="index.php?action=admin_detach_payment&id=<?= $p['id'] ?>" class="btn btn-sm btn-danger ms-2">Gỡ bỏ</a>
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