<?php require_once __DIR__ . '/../layout_header.php';
$users = $data['users'] ?? [];
?>
<h3>Người dùng</h3>

<div class="card mb-3 p-3">
  <h4>Thêm người dùng</h4>
  <form method="post" action="index.php?action=admin_create_user">
    <div class="mb-3">
      <label class="form-label">Tên đăng nhập</label>
      <input name="username" placeholder="username" class="form-control" required>
    </div>
    <div class="mb-3">
      <label class="form-label">Mật khẩu</label>
      <input name="password" placeholder="password" class="form-control" required>
    </div>
    <div class="mb-3">
      <label class="form-label">Vai trò</label>
      <select name="role" class="form-control">
        <option value="manager">manager</option>
        <option value="admin">admin</option>
      </select>
    </div>
    <div class="mt-3">
      <button type="submit" class="btn btn-success">Tạo</button>
    </div>
  </form>
</div>

<div class="card">
  <table class="data-table">
    <thead><tr><th>ID</th><th>Tên đăng nhập</th><th>Vai trò</th><th>Ngày tạo</th><th>Thao tác</th></tr></thead>
    <tbody>
      <?php foreach($users as $u): ?>
        <tr>
          <td><?=$u['id']?></td>
          <td><?=htmlspecialchars($u['username'])?></td>
          <td><?=$u['role']?></td>
          <td><?=$u['created_at']?></td>
          <td>
            <?php if ($u['role'] === 'manager'): ?>
                <a href="index.php?action=admin_manager_whitelist&manager_id=<?=$u['id']?>" class="btn btn-sm btn-info">Whitelist</a>
            <?php endif; ?>
            <a href="index.php?action=admin_delete_user&id=<?=$u['id']?>" class="btn btn-sm btn-danger" onclick="return confirm('Xác nhận xóa?')">Xóa</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/../layout_footer.php'; ?>
