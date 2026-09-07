<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Đăng nhập · Cards Shield</title>
  <link rel="stylesheet" href="assets/css/core.css?v=<?= filemtime(__DIR__ . '/../assets/css/core.css') ?>">
  <link rel="stylesheet" href="assets/css/form.css?v=<?= filemtime(__DIR__ . '/../assets/css/form.css') ?>">
  <link rel="stylesheet" href="assets/css/login.css?v=<?= filemtime(__DIR__ . '/../assets/css/login.css') ?>">
</head>
<body class="login-page">
  <section class="login-brand">
    <div class="sidebar-header"><div class="logo" aria-hidden="true">S</div><div class="brand-text">Cards Shield<span class="brand-caption">MANAGEMENT</span></div></div>
    <div class="login-pitch"><div class="eyebrow">KHÔNG GIAN QUẢN LÝ THANH TOÁN</div><h1>Kết nối tập trung.<br>Quản lý dễ dàng.</h1><p>Một nơi để quản lý Web Shields, cấu hình thanh toán và kết nối các website của bạn.</p><div class="login-illustration" aria-hidden="true"><div class="illustration-node">Website</div><span>─</span><div class="illustration-shield">S</div><span>─</span><div class="illustration-node">Thanh toán</div></div></div>
    <div class="login-copyright">© <?= date('Y') ?> Cards Shield</div>
  </section>
  <main class="login-main">
    <form method="post" action="index.php?action=login_post" class="login-box">
      <div class="eyebrow">CHÀO MỪNG TRỞ LẠI</div><h2>Đăng nhập tài khoản</h2><p class="muted">Tiếp tục đến không gian quản lý của bạn.</p>
      <?php if (!empty($error)): ?><div class="alert-error" role="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>
      <div class="mb-3"><label for="username">Tên đăng nhập</label><input id="username" name="username" class="form-control" autocomplete="username" placeholder="Nhập tên đăng nhập" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required autofocus></div>
      <div class="mb-3"><label for="password">Mật khẩu</label><input type="password" id="password" name="password" class="form-control" autocomplete="current-password" placeholder="Nhập mật khẩu" required></div>
      <button type="submit" class="btn">Đăng nhập &nbsp; →</button>
      <p class="login-note">Sử dụng tài khoản được cấp để truy cập hệ thống.</p>
    </form>
  </main>
</body>
</html>