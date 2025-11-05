<?php
// views/login.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Login - Cards Shield</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="assets/css/login.css">
</head>
<body>

  <div class="left">
    <h1>Cards Shield</h1>
    <p>Processing payments safely and confidently.</p>
    <div class="footer">© <?= date('Y') ?> Cards Shield. All rights reserved.</div>
  </div>

  <div class="right">
    <form method="POST" action="index.php?action=login_post" class="login-box">
      <h2>Welcome to <strong>Cards Shield!</strong></h2>
      <input type="text" name="username" placeholder="Email" required>
      <input type="password" name="password" placeholder="Password" required>
      <div class="remember">
        <label><input type="checkbox" name="remember"> Remember me</label>
      </div>
      <button type="submit">Login</button>
    </form>
  </div>

</body>
</html>
