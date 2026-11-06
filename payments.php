<?php
require 'config.php';
require_role(['Administrator', 'Receptionist', 'Student']);
$page_title = 'Payments'; $active = 'payments.php';
$manager = has_role('Administrator', 'Receptionist');
$sid = has_role('Student') ? my_student_id() : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        if (!$manager) throw new Exception('Not allowed.');
        if (($_POST['action'] ?? '') === 'delete') {
            if (!has_role('Administrator')) throw new Exception('Only the administrator can delete payments.');
            $pdo->prepare('DELETE FROM payments WHERE payment_id=?')->execute([(int)$_POST['id']]);
            flash('Payment deleted.');
        } else {
            $stu = (int)$_POST['student_id']; $amt = (float)$_POST['amount'];
            if (!$stu || $amt <= 0) throw new Exception('Select a student and enter an amount greater than zero.');
            if (!in_array($_POST['payment_method'], ['Cash', 'M-Pesa', 'Bank'], true)) throw new Exception('Bad payment method.');
            $pdo->prepare('INSERT INTO payments (student_id,amount,payment_date,payment_method,reference,received_by) VALUES (?,?,?,?,?,?)')
                ->execute([$stu, $amt, $_POST['payment_date'] ?: date('Y-m-d'), $_POST['payment_method'], trim($_POST['reference']), user()['user_id']]);
            $newId = $pdo->lastInsertId();
            flash('Payment recorded. Balance now ' . money(max(student_balance($stu), 0)) . '.');
            redirect('receipt.php?id=' . $newId);
        }
    } catch (Exception $ex) { flash($ex->getMessage(), 'err'); }
    redirect('payments.php');
}

if ($sid) {
    $st = $pdo->prepare('SELECT p.*, s.full_name student FROM payments p JOIN students s USING(student_id) WHERE p.student_id=? ORDER BY payment_date DESC, payment_id DESC');
    $st->execute([$sid]);
} else {
    $st = $pdo->query('SELECT p.*, s.full_name student FROM payments p JOIN students s USING(student_id) ORDER BY payment_date DESC, payment_id DESC LIMIT 200');
}
$rows = $st->fetchAll();
$studentsL = $manager ? $pdo->query('SELECT s.student_id, s.full_name, s.course_fee - COALESCE((SELECT SUM(amount) FROM payments WHERE student_id=s.student_id),0) bal FROM students s ORDER BY full_name')->fetchAll() : [];
require 'includes/header.php';
?>
<?php if ($manager): ?>
<div class="panel">
  <h3 style="margin-top:0">Record a payment</h3>
  <form method="post" class="grid"><?= csrf_field() ?>
    <label>Student <select name="student_id" required><option value="">— select —</option><?php foreach ($studentsL as $o) echo '<option value="' . $o['student_id'] . '">' . e($o['full_name']) . ' (due ' . money(max($o['bal'], 0)) . ')</option>'; ?></select></label>
    <label>Amount (KES) <input type="number" step="0.01" min="1" name="amount" required></label>
    <label>Date <input type="date" name="payment_date" value="<?= date('Y-m-d') ?>"></label>
    <label>Method <select name="payment_method"><option>Cash</option><option>M-Pesa</option><option>Bank</option></select></label>
    <label>Reference (M-Pesa code / bank slip) <input name="reference"></label>
    <div><button class="btn">Save &amp; print receipt</button></div>
  </form>
</div>
<?php endif; ?>
<div class="panel"><table><tr><th>Receipt</th><th>Date</th><th>Student</th><th>Amount</th><th>Method</th><th>Reference</th><th></th></tr>
<?php foreach ($rows as $r): ?>
  <tr><td>#<?= str_pad($r['payment_id'], 5, '0', STR_PAD_LEFT) ?></td><td><?= e($r['payment_date']) ?></td><td><?= e($r['student']) ?></td><td><?= money($r['amount']) ?></td><td><?= e($r['payment_method']) ?></td><td><?= e($r['reference']) ?></td>
  <td><a class="btn sm" href="receipt.php?id=<?= $r['payment_id'] ?>">Receipt</a>
  <?php if (has_role('Administrator')): ?><form method="post" class="inline" onsubmit="return confirm('Delete this payment?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['payment_id'] ?>"><button class="btn sm red">Delete</button></form><?php endif; ?></td></tr>
<?php endforeach; if (!$rows) echo '<tr><td colspan="7" class="muted">No payments yet.</td></tr>'; ?>
</table></div>
<?php require 'includes/footer.php'; ?>
