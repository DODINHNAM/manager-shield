<h2>Danh sách Domain Whitelist của bạn</h2>

<a href="index.php?action=manager_whitelist_add" class="btn btn-primary">+ Thêm domain</a>

<table border="1" cellpadding="8" cellspacing="0" style="margin-top:10px;">
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
                <a href="index.php?action=manager_whitelist_edit&id=<?= $d['id'] ?>">Sửa</a> |
                <a href="index.php?action=manager_whitelist_delete&id=<?= $d['id'] ?>"
                   onclick="return confirm('Xóa domain này?')">Xóa</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
