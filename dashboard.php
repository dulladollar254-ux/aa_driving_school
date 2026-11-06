<?php
require 'config.php';
require_login();
$page_title = 'Dashboard'; $active = 'dashboard.php';
$role = user()['role'];
require 'includes/header.php';

if (in_array($role, ['Administrator', 'Receptionist'])) {
    $c = fn($sql) => $pdo->query($sql)->fetchColumn();
    $students = $c('SELECT COUNT(*) FROM students');
    $instructors = $c('SELECT COUNT(*) FROM instructors');
    $today = $c("SELECT COUNT(*) FROM lessons WHERE lesson_date=CURDATE() AND status='Scheduled'");
    $revenue = $c("SELECT COALESCE(SUM(amount),0) FROM payments WHERE MONTH(payment_date)=MONTH(CURDATE()) AND YEAR(payment_date)=YEAR(CURDATE())");
    $outstanding = $c('SELECT COALESCE(SUM(GREATEST(s.course_fee - COALESCE(p.paid,0),0)),0) FROM students s LEFT JOIN (SELECT student_id,SUM(amount) paid FROM payments GROUP BY student_id) p ON p.student_id=s.student_id');
    $upcoming = $pdo->query("SELECT l.*, s.full_name student, i.full_name instructor FROM lessons l JOIN students s USING(student_id) JOIN instructors i USING(instructor_id) WHERE l.lesson_date>=CURDATE() AND l.status='Scheduled' ORDER BY lesson_date, lesson_time LIMIT 8")->fetchAll();
    ?>
    <div class="cards">
      <div class="card"><b><?= $students ?></b><span>Students</span></div>
      <div class="card"><b><?= $instructors ?></b><span>Instructors</span></div>
      <div class="card"><b><?= $today ?></b><span>Lessons today</span></div>
      <div class="card"><b><?= money($revenue) ?></b><span>Revenue this month</span></div>
      <div class="card"><b><?= money($outstanding) ?></b><span>Outstanding fees</span></div>
    </div>
    <div class="panel"><h3 style="margin-top:0">Upcoming lessons</h3>
      <table><tr><th>Date</th><th>Time</th><th>Student</th><th>Instructor</th><th>Topic</th></tr>
      <?php foreach ($upcoming as $l): ?>
        <tr><td><?= e($l['lesson_date']) ?></td><td><?= e(substr($l['lesson_time'], 0, 5)) ?></td><td><?= e($l['student']) ?></td><td><?= e($l['instructor']) ?></td><td><?= e($l['topic']) ?></td></tr>
      <?php endforeach; if (!$upcoming) echo '<tr><td colspan="5" class="muted">No upcoming lessons.</td></tr>'; ?>
      </table></div>
    <?php
} elseif ($role === 'Instructor') {
    $iid = my_instructor_id();
    if (!$iid) { echo '<div class="panel">Your account is not linked to an instructor profile yet. Please contact the front desk.</div>'; require 'includes/footer.php'; exit; }
    $st = $pdo->prepare("SELECT l.*, s.full_name student FROM lessons l JOIN students s USING(student_id) WHERE l.instructor_id=? AND l.lesson_date>=CURDATE() AND l.status='Scheduled' ORDER BY lesson_date, lesson_time LIMIT 10");
    $st->execute([$iid]);
    $rows = $st->fetchAll(); ?>
    <div class="panel"><h3 style="margin-top:0">My upcoming lessons</h3>
      <table><tr><th>Date</th><th>Time</th><th>Student</th><th>Topic</th></tr>
      <?php foreach ($rows as $l): ?>
        <tr><td><?= e($l['lesson_date']) ?></td><td><?= e(substr($l['lesson_time'], 0, 5)) ?></td><td><?= e($l['student']) ?></td><td><?= e($l['topic']) ?></td></tr>
      <?php endforeach; if (!$rows) echo '<tr><td colspan="4" class="muted">No upcoming lessons.</td></tr>'; ?>
      </table></div>
    <?php
} else { // Student
    $sid = my_student_id();
    if (!$sid) { echo '<div class="panel">Your account is not linked to a student record yet. Please contact the front desk.</div>'; require 'includes/footer.php'; exit; }
    $s = $pdo->prepare('SELECT * FROM students WHERE student_id=?'); $s->execute([$sid]); $stu = $s->fetch();
    $done = $pdo->prepare("SELECT COUNT(*) FROM lessons WHERE student_id=? AND status='Completed'"); $done->execute([$sid]);
    $next = $pdo->prepare("SELECT l.*, i.full_name instructor FROM lessons l JOIN instructors i USING(instructor_id) WHERE student_id=? AND lesson_date>=CURDATE() AND status='Scheduled' ORDER BY lesson_date, lesson_time LIMIT 5"); $next->execute([$sid]);
    $paid = $stu['course_fee'] - student_balance($sid); ?>
    <div class="cards">
      <div class="card"><b><?= e($stu['course']) ?></b><span>My course</span></div>
      <div class="card"><b><?= $done->fetchColumn() ?></b><span>Lessons completed</span></div>
      <div class="card"><b><?= money($paid) ?></b><span>Paid of <?= money($stu['course_fee']) ?></span></div>
      <div class="card"><b><?= money(max(student_balance($sid), 0)) ?></b><span>Balance due</span></div>
    </div>
    <div class="panel"><h3 style="margin-top:0">My next lessons</h3>
      <table><tr><th>Date</th><th>Time</th><th>Instructor</th><th>Topic</th></tr>
      <?php foreach ($next as $l): ?>
        <tr><td><?= e($l['lesson_date']) ?></td><td><?= e(substr($l['lesson_time'], 0, 5)) ?></td><td><?= e($l['instructor']) ?></td><td><?= e($l['topic']) ?></td></tr>
      <?php endforeach; ?></table></div>
    <?php
}
require 'includes/footer.php';
