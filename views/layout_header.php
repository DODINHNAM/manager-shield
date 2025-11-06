<?php
$user = isset($_SESSION['user']) ? $_SESSION['user'] : null;
function isActive($action){ $a = $_GET['action'] ?? 'dashboard'; return $a==$action ? 'active' : ''; }
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Cards Shield Manager</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/assets/css/core.css">
  <link rel="stylesheet" href="/assets/css/table.css">
  <link rel="stylesheet" href="/assets/css/form.css">
  <?php if(basename($_SERVER['PHP_SELF']) == 'login.php'): ?>
    <link rel="stylesheet" href="/assets/css/login.css">
  <?php endif; ?>
</head>
<body>
<div class="app-wrapper">
  <aside class="sidebar">
    <div>
      <div class="sidebar-header">
        <div class="logo">🛡️</div>
        <div class="brand-text">Cards Shield</div>
      </div>
      <nav class="sidebar-nav">
        <a class="nav-item <?= isActive('home') ?>" href="index.php?action=home">Dashboard</a>
        <?php if($user && $user['role']==='admin'): ?>
          <a class="nav-item <?= isActive('admin_webshields') ?>" href="index.php?action=admin_webshields">Web Shields</a>
          <a class="nav-item <?= isActive('admin_users') ?>" href="index.php?action=admin_users">Users</a>
        <?php elseif($user && $user['role']==='manager'): ?>
          <a class="nav-item <?= isActive('manager_my_webshields') ?>" href="index.php?action=manager_my_webshields">My WebShields</a>
          <a class="nav-item <?= isActive('manager_whitelist') ?>" href="index.php?action=manager_whitelist">Whitelist</a>
        <?php endif; ?>
      </nav>
    </div>
    
    <div class="sidebar-footer">
      <?php if($user): ?>
        <div class="user-line"><?= htmlspecialchars($user['username']) ?> (<?= $user['role'] ?>)</div>
        <a href="index.php?action=logout" class="logout">Logout</a>
      <?php endif; ?>
    </div>
  </aside>

  <main class="app-main">
    <div>
      <header class="header">
        <div class="header-left">
          <button id="menuToggle" class="menu-toggle">☰</button>
          <h1 class="page-title"><?= htmlspecialchars($page_title ?? 'Dashboard') ?></h1>
        </div>
        <div class="header-right">
          <img src="/assets/images/avatar.png" class="avatar" alt="avatar">
        </div>
      </header>

      <section class="page-body">
