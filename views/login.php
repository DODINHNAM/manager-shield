<?php
// views/login.php
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>Đăng nhập - Cards Shield</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="assets/css/login.css">
</head>
<body>

  <div class="left">
    <h1>Cards Shield</h1>
    <p>Xử lý thanh toán an toàn và tự tin.</p>
    <div class="footer">© <?= date('Y') ?> Cards Shield. Đã đăng ký bản quyền.</div>
  </div>

  <div class="right">
    <form method="POST" action="index.php?action=login_post" class="login-box">
      <h2>Chào mừng đến với <strong>Cards Shield!</strong></h2>
      <input type="text" name="username" placeholder="Email" required>
      <input type="password" name="password" placeholder="Mật khẩu" required>
      <div class="remember">
        <label><input type="checkbox" name="remember"> Ghi nhớ đăng nhập</label>
      </div>
      <div class="mt-3">
        <button type="submit" class="btn btn-primary">Đăng nhập</button>
      </div>
    </form>
  </div>

</body>
</html>
