<?php
require_once __DIR__ . '/layout_header.php';
?>
<div class="row justify-content-center">
  <div class="col-md-5">
    <h3>Đăng nhập</h3>
    <?php if(!empty($error)): ?>
      <div class="alert alert-danger"><?=htmlspecialchars($error)?></div>
    <?php endif; ?>
    <form method="post" action="index.php?action=login_post">
      <div class="mb-3">
        <label class="form-label">Username</label>
        <input name="username" class="form-control" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Password</label>
        <input name="password" type="password" class="form-control" required>
      </div>
      <button class="btn btn-primary">Login</button>
    </form>
    <p class="mt-3"><strong>Test admin:</strong> username <code>admin</code> / password <code>Admin@123</code></p>
  </div>
</div>
<?php require_once __DIR__ . '/layout_footer.php'; ?>
