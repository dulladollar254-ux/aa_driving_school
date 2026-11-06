<?php
require 'config.php';
require_role(['Administrator', 'Receptionist']);
$page_title = 'Reports'; $active = 'reports.php';

$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-t');
$type = $_GET['type'] ?? 'payments';

function report_data($pdo, $type, $from, $to) {
    switch ($type) {
        case 'students':
            $q = $pdo->prepare("SELECT s.student_id 'ID', s.full_name 'Student', s.phone 'Phone', s.course 'Course', s.course_fee 'Fee', COALESCE((SELECT SUM(amount) FROM payments WHERE student_id=s.student_id),0) 'Paid', s.course_fee - COALESCE((SELECT SUM(amount) FROM payments WHERE student_id=s.student_id),0) 'Balance', s.reg_date 'Registered' FROM students s WHERE s.reg_date BETWEEN ? AND ? ORDER BY s.reg_date");
            $q->execute([$from, $to]); return [$q->fetchAll(), 'Registered students'];
        case 'outstanding':
            $q = $pdo->query("SELECT s.full_name 'Student', s.phone 'Phone', s.course_fee 'Fee', COALESCE(p.paid,0) 'Paid', s.course_fee - COALESCE(p.paid,0) 'Balance' FROM students s LEFT JOIN (SELECT student_id, SUM(amount) paid FROM payments GROUP BY student_id) p ON p.student_id=s.student_id WHERE s.course_fee - COALESCE(p.paid,0) > 0 ORDER BY 5 DESC");
            return [$q->fetchAll(), 'Outstanding fee balances'];
        case 'lessons':
            $q = $pdo->prepare("SELECT l.lesson_date 'Date', LEFT(l.lesson_time,5) 'Time', s.full_name 'Student', i.full_name 'Instructor', COALESCE(v.reg_no,'-') 'Vehicle', l.status 'Status' FROM lessons l JOIN students s USING(student_id) JOIN instructors i USING(instructor_id) LEFT JOIN vehicles v USING(vehicle_id) WHERE l.lesson_date BETWEEN ? AND ? ORDER BY l.lesson_date, l.lesson_time");
            $q->execute([$from, $to]); return [$q->fetchAll(), 'Lessons schedule'];
        case 'results':
            $q = $pdo->prepare("SELECT r.test_date 'Date', s.full_name 'Student', r.test_type 'Test', r.score 'Score', IF(r.score>=" . PASS_MARK . ",'Pass','Fail') 'Outcome' FROM test_results r JOIN students s USING(student_id) WHERE r.test_date BETWEEN ? AND ? ORDER BY r.test_date");
            $q->execute([$from, $to]); return [$q->fetchAll(), 'Driving test results'];
        case 'progress':
            $q = $pdo->query("SELECT s.full_name 'Student', s.course 'Course', (SELECT COUNT(*) FROM lessons WHERE student_id=s.student_id AND status='Completed') 'Lessons done', (SELECT COUNT(*) FROM lessons WHERE student_id=s.student_id AND status='Scheduled') 'Lessons booked', (SELECT MAX(score) FROM test_results WHERE student_id=s.student_id AND test_type='Theory') 'Best theory', (SELECT MAX(score) FROM test_results WHERE student_id=s.student_id AND test_type='Practical') 'Best practical' FROM students s ORDER BY s.full_name");
            return [$q->fetchAll(), 'Student progress'];
        default:
            $q = $pdo->prepare("SELECT p.payment_date 'Date', s.full_name 'Student', p.amount 'Amount', p.payment_method 'Method', COALESCE(p.reference,'') 'Reference' FROM payments p JOIN students s USING(student_id) WHERE p.payment_date BETWEEN ? AND ? ORDER BY p.payment_date");
            $q->execute([$from, $to]); return [$q->fetchAll(), 'Payments received'];
    }
}
[$rows, $title] = report_data($pdo, $type, $from, $to);

if (isset($_GET['csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $type . '_report_' . date('Ymd') . '.csv"');
    $o = fopen('php://output', 'w');
    if ($rows) { fputcsv($o, array_keys($rows[0])); foreach ($rows as $r) fputcsv($o, $r); }
    fclose($o); exit;
}
require 'includes/header.php';
$types = ['payments' => 'Payments', 'outstanding' => 'Outstanding fees', 'students' => 'Students', 'lessons' => 'Lessons', 'results' => 'Test results', 'progress' => 'Student progress'];
$money_cols = ['Fee', 'Paid', 'Balance', 'Amount'];
?>
<div class="panel no-print">
  <form class="grid">
    <label>Report <select name="type"><?php foreach ($types as $k => $v) echo '<option value="' . $k . '" ' . ($type === $k ? 'selected' : '') . '>' . $v . '</option>'; ?></select></label>
    <label>From <input type="date" name="from" value="<?= e($from) ?>"></label>
    <label>To <input type="date" name="to" value="<?= e($to) ?>"></label>
    <div><button class="btn">Generate</button> <a class="btn green" href="?<?= e(http_build_query(['type' => $type, 'from' => $from, 'to' => $to, 'csv' => 1])) ?>">Export CSV</a> <button type="button" class="btn grey" onclick="window.print()">Print</button></div>
  </form>
</div>
<div class="panel">
  <h3 style="margin-top:0"><?= e($title) ?> <span class="muted small"><?= in_array($type, ['outstanding', 'progress']) ? '' : e("$from to $to") ?></span></h3>
  <?php if ($rows): ?>
  <table><tr><?php foreach (array_keys($rows[0]) as $h) echo '<th>' . e($h) . '</th>'; ?></tr>
  <?php $totals = []; foreach ($rows as $r): echo '<tr>';
      foreach ($r as $k => $v) {
          if (in_array($k, $money_cols)) { $totals[$k] = ($totals[$k] ?? 0) + $v; $v = money($v); }
          echo '<td>' . e($v ?? '-') . '</td>';
      } echo '</tr>'; endforeach;
  if ($totals): echo '<tr>'; foreach (array_keys($rows[0]) as $k) echo '<th>' . (isset($totals[$k]) ? e(money($totals[$k])) : ($k === array_key_first($rows[0]) ? 'Total' : '')) . '</th>'; echo '</tr>'; endif; ?>
  </table>
  <p class="muted small"><?= count($rows) ?> record(s) · generated <?= date('d M Y H:i') ?> by <?= e(user()['full_name']) ?></p>
  <?php else: ?><p class="muted">No records for this selection.</p><?php endif; ?>
</div>
<?php require 'includes/footer.php'; ?>
