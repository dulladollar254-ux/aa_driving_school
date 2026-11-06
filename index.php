<?php
require 'config.php';
if (user()) redirect('dashboard.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    if (is_string($username) && is_string($password)) {
        $st = $pdo->prepare('SELECT * FROM users WHERE username=?');
        $st->execute([trim($username)]);
        $u = $st->fetch();
        if ($u && password_verify($password, $u['password'])) {
            session_regenerate_id(true);
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
            $_SESSION['user'] = ['user_id' => $u['user_id'], 'username' => $u['username'], 'full_name' => $u['full_name'], 'role' => $u['role']];
            redirect('dashboard.php');
        }
    }
    $error = 'Invalid username or password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login · <?= APP_NAME ?></title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body class="login-page">
<form method="post" class="login-card">
  <h2>🚗 <?= APP_NAME ?></h2>
  <p class="muted">Online Driving School Management System · Mombasa</p>
  <?php if ($error): ?><div class="flash err"><?= e($error) ?></div><?php endif; ?>
  <?= csrf_field() ?>
  <label>Username <input name="username" required autofocus></label>
  <label>Password <input type="password" name="password" required></label>
  <button class="btn">Log in</button>
  <p class="muted small">Demo: admin / admin123 · reception, instructor1, student1 / pass1234</p>
</form>
</body>
</html>
