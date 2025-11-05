<?php
$user = isset($_SESSION['user']) ? $_SESSION['user'] : null;
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>PHP Shield Manager v2</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light bg-light mb-4">
  <div class="container">
    <a class="navbar-brand" href="index.php">ShieldApp v2</a>
    <?php if($user): ?>
      <div class="ms-auto">
        <span class="me-3">Hi, <?=htmlspecialchars($user['username'])?> (<?= $user['role'] ?>)</span>
        <a href="index.php?action=logout" class="btn btn-outline-secondary btn-sm">Logout</a>
      </div>
    <?php endif; ?>
  </div>
</nav>
<div class="container">
