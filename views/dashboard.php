<?php require_once __DIR__ . '/layout_header.php'; ?>
<div class="page-intro"><div class="eyebrow">TỔNG QUAN</div><h2>Chào mừng trở lại, <?= htmlspecialchars($user['username'] ?? '') ?>.</h2><p>Quản lý Web Shields và cấu hình thanh toán trong một không gian.</p></div>
<div class="card welcome-card">
  <div class="eyebrow">LAZYSHIELD</div><h2>Mọi kết nối, trong tầm kiểm soát.</h2>
  <p><?= $isAdmin ? 'Tổ chức hệ thống shield, phân quyền người quản lý và thiết lập phương thức thanh toán cho từng website.' : 'Theo dõi các shield được giao, cập nhật cấu hình thanh toán và quản lý website được phép kết nối.' ?></p>
  <a class="btn" href="index.php?action=<?= $isAdmin ? 'admin_webshields' : 'manager_my_webshields' ?>">Quản lý Web Shields &nbsp; →</a>
</div>
<div class="section-heading"><h3>Truy cập nhanh</h3><span class="muted">Không gian của bạn</span></div>
<div class="dashboard-grid">
  <a class="card dashboard-link" href="index.php?action=<?= $isAdmin ? 'admin_webshields' : 'manager_my_webshields' ?>"><span class="tile-icon"><?= uiIcon('shield') ?></span><span class="tile-arrow">↗</span><h3>Web Shields</h3><p><?= $isAdmin ? 'Quản lý website và phân công người phụ trách.' : 'Xem website và cấu hình phương thức thanh toán.' ?></p></a>
  <?php if ($isAdmin): ?>
  <a class="card dashboard-link" href="index.php?action=admin_users"><span class="tile-icon"><?= uiIcon('users') ?></span><span class="tile-arrow">↗</span><h3>Người dùng</h3><p>Quản lý tài khoản, vai trò và whitelist của từng manager.</p></a>
  <?php else: ?>
  <a class="card dashboard-link" href="index.php?action=manager_whitelist"><span class="tile-icon"><?= uiIcon('globe') ?></span><span class="tile-arrow">↗</span><h3>Domain được phép</h3><p>Thiết lập website được kết nối đến các shield của bạn.</p></a>
  <?php endif; ?>
  <a class="card dashboard-link" href="index.php?action=transactions"><span class="tile-icon"><?= uiIcon('globe') ?></span><span class="tile-arrow">&rarr;</span><h3>Payment reports</h3><p>Review payment history, statuses, fees and payouts.</p></a>
</div>
<?php require_once __DIR__ . '/layout_footer.php'; ?>
