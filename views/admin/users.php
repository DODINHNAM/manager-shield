<?php require_once __DIR__ . '/../layout_header.php';
$users = $data['users'] ?? [];
?>
<h3>Users</h3>

<div class="card mb-3 p-3">
  <form method="post" action="index.php?action=admin_create_user">
    <div class="row g-2">
      <div class="col-md-4"><input name="username" placeholder="username" class="form-control" required></div>
      <div class="col-md-3"><input name="password" placeholder="password" class="form-control" required></div>
      <div class="col-md-3">
        <select name="role" class="form-control">
          <option value="manager">manager</option>
          <option value="admin">admin</option>
        </select>
      </div>
      <div class="col-md-2"><button class="btn btn-success">Tạo</button></div>
    </div>
  </form>
</div>

<div class="card">
  <table class="data-table">
    <thead><tr><th>ID</th><th>Username</th><th>Role</th><th>Created</th><th>Action</th></tr></thead>
    <tbody>
      <?php foreach($users as $u): ?>
        <tr>
          <td><?=$u['id']?></td>
          <td><?=htmlspecialchars($u['username'])?></td>
          <td><?=$u['role']?></td>
          <td><?=$u['created_at']?></td>
          <td>
            <a href="index.php?action=admin_delete_user&id=<?=$u['id']?>" class="btn btn-sm btn-danger" onclick="return confirm('Xác nhận xóa?')">Xóa</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/../layout_footer.php'; ?>
