<?php
$user = currentUser();
$action = $_GET['action'] ?? 'home';
$isAdmin = ($user['role'] ?? '') === 'admin';
$titles = [
    'home' => 'Tổng quan', 'admin_webshields' => 'Quản lý Web Shields',
    'admin_edit_webshield' => 'Chi tiết Web Shield', 'admin_users' => 'Người dùng',
    'admin_manager_whitelist' => 'Domain được phép', 'manager_my_webshields' => 'Web Shields của tôi',
    'manager_payments' => 'Cấu hình thanh toán', 'manager_whitelist' => 'Domain được phép',
    'manager_whitelist_add' => 'Thêm domain', 'manager_whitelist_edit' => 'Chỉnh sửa domain',
];
$titles['transactions'] = 'Payment reports';
$page_title = $page_title ?? ($titles[$action] ?? 'Quản lý LazyShield');
$roleLabel = $isAdmin ? 'Quản trị viên' : 'Người quản lý';
function uiIcon($name) {
    $paths = [
        'home' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'shield' => '<path d="M12 3 3 7v5c0 5 9 10 9 10s9-5 9-10V7Z"/><path d="m8 12 3 3 5-6"/>',
        'users' => '<circle cx="9" cy="8" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3M16 5a3 3 0 0 1 0 6m2 4a5 5 0 0 1 3 5"/>',
        'globe' => '<circle cx="12" cy="12" r="9"/><ellipse cx="12" cy="12" rx="4" ry="9"/><path d="M3 12h18"/>',
    ];
    return '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($paths[$name] ?? $paths['shield']) . '</svg>';
}
$navigation = $isAdmin
    ? [['home','Tổng quan','home'],['admin_webshields','Web Shields','shield'],['admin_users','Người dùng','users']]
    : [['home','Tổng quan','home'],['manager_my_webshields','Web Shields của tôi','shield'],['manager_whitelist','Domain được phép','globe']];
$navigation[] = ['transactions', 'Payment reports', 'globe'];
$activeNav = $action;
if ($action === 'admin_edit_webshield') $activeNav = 'admin_webshields';
if ($action === 'manager_payments') $activeNav = $isAdmin ? 'admin_webshields' : 'manager_my_webshields';
if (strpos($action, 'transaction') !== false) $activeNav = 'transactions';
if (in_array($action, ['manager_whitelist_add','manager_whitelist_edit','admin_manager_whitelist'], true)) $activeNav = $isAdmin ? 'admin_users' : 'manager_whitelist';
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($page_title) ?> · LazyShield</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php foreach (['core','table','form'] as $stylesheet): ?>
  <link rel="stylesheet" href="/assets/css/<?= $stylesheet ?>.css?v=<?= filemtime(__DIR__ . '/../assets/css/' . $stylesheet . '.css') ?>">
  <?php endforeach; ?>
</head>
<body>
<div class="app-wrapper">
  <button class="sidebar-backdrop" aria-label="Đóng menu" tabindex="-1"></button>
  <aside class="sidebar" id="sidebar" aria-label="Điều hướng chính">
    <div>
      <div class="sidebar-header"><div class="logo" aria-hidden="true">L</div><div class="brand-text">LazyShield<span class="brand-caption">MANAGEMENT</span></div></div>
      <div class="nav-label">KHÔNG GIAN LÀM VIỆC</div>
      <nav class="sidebar-nav">
        <?php foreach ($navigation as [$route,$label,$icon]): ?>
        <a class="nav-item <?= $activeNav === $route ? 'active' : '' ?>" href="index.php?action=<?= $route ?>" <?= $activeNav === $route ? 'aria-current="page"' : '' ?>><?= uiIcon($icon) ?><?= $label ?></a>
        <?php endforeach; ?>
      </nav>
    </div>
    <div class="sidebar-footer">
      <div class="user-line"><?= htmlspecialchars($user['username'] ?? '') ?></div>
      <div class="role-label"><?= $roleLabel ?></div>
      <a href="index.php?action=logout" class="logout">Đăng xuất &nbsp; ↗</a>
    </div>
  </aside>
  <main class="app-main">
    <header class="header">
      <div class="header-left">
        <button id="menuToggle" class="menu-toggle" aria-label="Mở menu" aria-controls="sidebar" aria-expanded="false">☰</button>
        <div><div class="breadcrumb">Không gian làm việc / <?= $roleLabel ?></div><h1 class="page-title"><?= htmlspecialchars($page_title) ?></h1></div>
      </div>
      <div class="header-right"><div class="header-account"><?= htmlspecialchars($user['username'] ?? '') ?><small><?= $roleLabel ?></small></div><span class="avatar" aria-hidden="true"><?= htmlspecialchars(strtoupper(substr($user['username'] ?? 'S', 0, 1))) ?></span></div>
    </header>
    <section class="page-body">
