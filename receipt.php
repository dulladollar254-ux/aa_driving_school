<?php
require 'config.php';
require_role(['Administrator', 'Receptionist', 'Student']);
$st = $pdo->prepare('SELECT p.*, s.full_name student, s.course, s.course_fee, s.student_id sid, u.full_name cashier FROM payments p JOIN students s USING(student_id) LEFT JOIN users u ON u.user_id=p.received_by WHERE p.payment_id=?');
$st->execute([(int)($_GET['id'] ?? 0)]);
$p = $st->fetch();
if (!$p) die('Receipt not found.');
if (has_role('Student') && $p['sid'] != my_student_id()) { http_response_code(403); die('Access denied.'); }
$page_title = 'Receipt'; $active = 'payments.php';
require 'includes/header.php';
?>
<div class="receipt">
  <h2><?= APP_NAME ?></h2><p>Mombasa · Official Payment Receipt</p><hr>
  <p style="text-align:left"><b>Receipt no:</b> #<?= str_pad($p['payment_id'], 5, '0', STR_PAD_LEFT) ?><br>
  <b>Date:</b> <?= e($p['payment_date']) ?><br>
  <b>Student:</b> <?= e($p['student']) ?><br>
  <b>Course:</b> <?= e($p['course']) ?><br>
  <b>Amount paid:</b> <?= money($p['amount']) ?><br>
  <b>Method:</b> <?= e($p['payment_method']) ?><?= $p['reference'] ? ' (' . e($p['reference']) . ')' : '' ?><br>
  <b>Balance remaining:</b> <?= money(max(student_balance($p['sid']), 0)) ?><br>
  <b>Received by:</b> <?= e($p['cashier']) ?></p><hr><p class="small muted">Thank you for choosing <?= APP_NAME ?>.</p>
</div>
<p style="text-align:center" class="no-print"><button class="btn" onclick="window.print()">Print</button> <a class="btn grey" href="payments.php">Back</a></p>
<?php require 'includes/footer.php'; ?>
