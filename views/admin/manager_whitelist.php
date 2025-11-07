<?php require_once __DIR__ . '/../layout_header.php';
$manager = $data['manager'] ?? null;
$domains = $data['domains'] ?? [];
?>

<div class="card">
    <h3>Whitelist cho <?= htmlspecialchars($manager['username']) ?></h3>

    <div class="card mb-3 p-3">
        <h4>Thêm domain</h4>
        <form method="post" action="index.php?action=manager_whitelist_add">
            <input type="hidden" name="manager_id" value="<?= $manager['id'] ?>">
            <div class="mb-3">
                <label class="form-label">Domain</label>
                <input name="domain" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-success">Thêm</button>
        </form>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Domain</th>
                <th>Trạng thái</th>
                <th>Thao tác</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($domains as $d): ?>
            <tr>
                <td><?= htmlspecialchars($d['id']) ?></td>
                <td><?= htmlspecialchars($d['domain']) ?></td>
                <td><?= $d['active'] ? 'Hoạt động' : 'Tắt' ?></td>
                <td>
                    <a href="index.php?action=manager_whitelist_edit&id=<?= $d['id'] ?>" class="btn btn-sm btn-primary">Sửa</a>
                    <a href="index.php?action=manager_whitelist_delete&id=<?= $d['id'] ?>"
                       class="btn btn-sm btn-danger" onclick="return confirm('Bạn có chắc chắn muốn xóa domain này không?')">Xóa</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../layout_footer.php'; ?>