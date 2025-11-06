<?php require_once __DIR__ . '/../layout_header.php'; ?>

<div class="card">
    <h2>Danh sách Domain Whitelist</h2>

    <a href="index.php?action=manager_whitelist_add" class="btn btn-primary">+ Thêm domain</a>

    <table class="data-table" style="margin-top:10px;">
        <thead>
            <tr>
                <th>ID</th>
                <?php if (currentUser()['role'] === 'admin'): ?>
                    <th>Manager</th>
                <?php endif; ?>
                <th>Domain</th>
                <th>Trạng thái</th>
                <th>Thao tác</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($domains as $d): ?>
            <tr>
                <td><?= htmlspecialchars($d['id']) ?></td>
                <?php if (currentUser()['role'] === 'admin'): ?>
                    <td><?= htmlspecialchars($d['manager_name']) ?></td>
                <?php endif; ?>
                <td><?= htmlspecialchars($d['domain']) ?></td>
                <td><?= $d['active'] ? 'Hoạt động' : 'Tắt' ?></td>
                <td>
                    <a href="index.php?action=manager_whitelist_edit&id=<?= $d['id'] ?>" class="btn btn-sm btn-primary">Sửa</a>
                    <a href="index.php?action=manager_whitelist_delete&id=<?= $d['id'] ?>"
                       class="btn btn-sm btn-danger" onclick="return confirm('Xóa domain này?')">Xóa</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../layout_footer.php'; ?>
