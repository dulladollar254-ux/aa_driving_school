<?php
// Shared page header + role-based sidebar. Set $page_title and $active before including.
require_login();
$role = user()['role'];
$menu = [
    'dashboard.php'   => ['Dashboard',   ['Administrator','Receptionist','Instructor','Student']],
    'students.php'    => ['Students',    ['Administrator','Receptionist']],
    'instructors.php' => ['Instructors', ['Administrator','Receptionist']],
    'vehicles.php'    => ['Vehicles',    ['Administrator','Receptionist']],
    'lessons.php'     => ['Lessons',     ['Administrator','Receptionist','Instructor','Student']],
    'payments.php'    => ['Payments',    ['Administrator','Receptionist','Student']],
    'results.php'     => ['Test Results',['Administrator','Instructor','Student']],
    'reports.php'     => ['Reports',     ['Administrator','Receptionist']],
    'users.php'       => ['Users',       ['Administrator']],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($page_title ?? 'Dashboard') ?> · <?= APP_NAME ?></title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<aside class="sidebar">
  <div class="brand">🚗 <?= APP_NAME ?><small>Mombasa</small></div>
  <nav>
    <?php foreach ($menu as $file => [$label, $roles]): if (!in_array($role, $roles, true)) continue; ?>
      <a href="<?= $file ?>" class="<?= ($active ?? '') === $file ? 'on' : '' ?>"><?= $label ?></a>
    <?php endforeach; ?>
  </nav>
  <div class="who"><?= e(user()['full_name']) ?><br><small><?= e($role) ?></small>
    <a href="logout.php" class="logout">Log out</a></div>
</aside>
<main class="content">
<h1><?= e($page_title ?? '') ?></h1>
<?php if (!empty($_SESSION['flash'])): [$m, $t] = $_SESSION['flash']; unset($_SESSION['flash']); ?>
  <div class="flash <?= $t ?>"><?= e($m) ?></div>
<?php endif; ?>
