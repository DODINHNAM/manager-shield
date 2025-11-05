<h2><?= isset($item) ? 'Chỉnh sửa' : 'Thêm mới' ?> Domain Whitelist</h2>

<form method="POST">
    <label>Domain:</label><br>
    <input type="text" name="domain" required
           value="<?= htmlspecialchars($item['domain'] ?? '') ?>"><br><br>

    <label>
        <input type="checkbox" name="active" <?= (!isset($item) || $item['active']) ? 'checked' : '' ?>>
        Kích hoạt
    </label><br><br>

    <button type="submit">Lưu</button>
    <a href="index.php?action=manager_whitelist">Quay lại</a>
</form>
