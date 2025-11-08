<?php require_once __DIR__ . '/../layout_header.php'; ?>

<div class="card">
    <h2><?= isset($item) ? 'Chỉnh sửa' : 'Thêm mới' ?> Domain Whitelist</h2>

    <form method="POST">
        <div class="mb-3">
            <label class="form-label">Domain:</label>
            <input type="text" name="domain" class="form-control" required
                   value="<?= htmlspecialchars($item['domain'] ?? '') ?>">
        </div>

        <div class="mb-3">
            <input type="checkbox" name="active" class="form-check-input" <?= (!isset($item) || $item['active']) ? 'checked' : '' ?>>
            <label class="form-check-label">Kích hoạt</label>
        </div>

        <button type="submit" class="btn btn-primary">Lưu</button>
    </form>
</div>

<?php require_once __DIR__ . '/../layout_footer.php'; ?>
