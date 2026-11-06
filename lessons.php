<?php
require 'config.php';
require_role(['Administrator', 'Receptionist', 'Instructor', 'Student']);
$page_title = 'Lessons'; $active = 'lessons.php';
$manager = has_role('Administrator', 'Receptionist');
$iid = has_role('Instructor') ? my_instructor_id() : null;
$sid = has_role('Student') ? my_student_id() : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['action'] ?? '';
    try {
        if ($act === 'save' && $manager) {
            $stu = request_int($_POST['student_id'] ?? null);
            $ins = request_int($_POST['instructor_id'] ?? null);
            $vehicleValue = $_POST['vehicle_id'] ?? '';
            $veh = $vehicleValue === '' ? null : request_int($vehicleValue);
            $date = $_POST['lesson_date'] ?? null;
            $time = $_POST['lesson_time'] ?? null;
            $id = request_int($_POST['id'] ?? '0');
            if ($stu === false || $stu < 1 || $ins === false || $ins < 1 || ($veh !== null && ($veh === false || $veh < 1)) || $id === false || $id < 0 || !valid_date($date) || !is_string($time) || !preg_match('/^\d{2}:\d{2}$/', $time)) throw new Exception('Select a student and instructor, and enter a valid date and time.');
            // conflict detection: same slot for the instructor, vehicle or student
            $c = $pdo->prepare("SELECT COUNT(*) FROM lessons WHERE lesson_date=? AND lesson_time=? AND status<>'Cancelled' AND schedule_id<>? AND (instructor_id=? OR student_id=? OR (vehicle_id IS NOT NULL AND vehicle_id=?))");
            $c->execute([$date, $time, $id, $ins, $stu, $veh]);
            if ($c->fetchColumn() > 0) throw new Exception('Scheduling conflict: the instructor, student or vehicle is already booked at that date and time.');
            if ($veh) {
                $v = $pdo->prepare('SELECT status FROM vehicles WHERE vehicle_id=?'); $v->execute([$veh]);
                if ($v->fetchColumn() === 'Maintenance') throw new Exception('That vehicle is under maintenance.');
            }
            $topic = $_POST['topic'] ?? '';
            if (!is_string($topic)) throw new Exception('Enter a valid lesson topic.');
            $status = $_POST['status'] ?? 'Scheduled';
            if (!in_array($status, ['Scheduled', 'Completed', 'Cancelled'], true)) throw new Exception('Bad status.');
            $d = [$stu, $ins, $veh, $date, $time, trim($topic), $status];
            if ($id) {
                $pdo->prepare('UPDATE lessons SET student_id=?,instructor_id=?,vehicle_id=?,lesson_date=?,lesson_time=?,topic=?,status=? WHERE schedule_id=?')->execute([...$d, $id]);
                flash('Lesson updated.');
            } else {
                $pdo->prepare('INSERT INTO lessons (student_id,instructor_id,vehicle_id,lesson_date,lesson_time,topic,status) VALUES (?,?,?,?,?,?,?)')->execute($d);
                flash('Lesson scheduled.');
            }
        } elseif ($act === 'status') {
            $id = request_int($_POST['id'] ?? null);
            $new = $_POST['status'] ?? '';
            if ($id === false || $id < 1) throw new Exception('Invalid lesson ID.');
            if (!in_array($new, ['Scheduled', 'Completed', 'Cancelled'], true)) throw new Exception('Bad status.');
            if ($iid) { // instructors may only update their own lessons
                if (!in_array($new, ['Completed', 'Cancelled'], true)) throw new Exception('Instructors may only complete or cancel lessons.');
                $notes = $_POST['notes'] ?? '';
                if (!is_string($notes)) throw new Exception('Enter valid lesson notes.');
                $pdo->prepare('UPDATE lessons SET status=?, notes=? WHERE schedule_id=? AND instructor_id=?')->execute([$new, trim($notes), $id, $iid]);
            } elseif ($manager) {
                $pdo->prepare('UPDATE lessons SET status=? WHERE schedule_id=?')->execute([$new, $id]);
            } else throw new Exception('Not allowed.');
            flash('Lesson marked ' . $new . '.');
        } elseif ($act === 'delete' && has_role('Administrator')) {
            $id = request_int($_POST['id'] ?? null);
            if ($id === false || $id < 1) throw new Exception('Invalid lesson ID.');
            $pdo->prepare('DELETE FROM lessons WHERE schedule_id=?')->execute([$id]);
            flash('Lesson deleted.');
        }
    } catch (PDOException $ex) {
        error_log('Lesson update failed: ' . $ex->getMessage());
        flash('Unable to save the lesson because of a database error.', 'err');
    } catch (Exception $ex) { flash($ex->getMessage(), 'err'); }
    redirect('lessons.php');
}

$edit = null;
if ($manager && isset($_GET['edit'])) {
    $editId = request_int($_GET['edit']);
    if ($editId === false || $editId < 1) { http_response_code(400); exit('Invalid lesson ID.'); }
    $s = $pdo->prepare('SELECT * FROM lessons WHERE schedule_id=?'); $s->execute([$editId]); $edit = $s->fetch();
}

$where = '1=1'; $params = [];
if (has_role('Instructor')) {
    if ($iid) { $where = 'l.instructor_id=?'; $params[] = $iid; }
    else $where = '1=0';
}
if (has_role('Student')) {
    if ($sid) { $where = 'l.student_id=?'; $params[] = $sid; }
    else $where = '1=0';
}
$from = $_GET['from'] ?? '';
if ($from !== '') {
    if (!valid_date($from)) { http_response_code(400); exit('Choose a valid start date.'); }
    $where .= ' AND l.lesson_date>=?'; $params[] = $from;
}
$list = $pdo->prepare("SELECT l.*, s.full_name student, i.full_name instructor, v.reg_no FROM lessons l JOIN students s USING(student_id) JOIN instructors i USING(instructor_id) LEFT JOIN vehicles v USING(vehicle_id) WHERE $where ORDER BY l.lesson_date DESC, l.lesson_time DESC LIMIT 200");
$list->execute($params); $rows = $list->fetchAll();
if ($manager) {
    $studentsL = $pdo->query('SELECT student_id, full_name FROM students ORDER BY full_name')->fetchAll();
    $instrL = $pdo->query('SELECT instructor_id, full_name FROM instructors ORDER BY full_name')->fetchAll();
    $vehL = $pdo->query("SELECT vehicle_id, reg_no, model FROM vehicles WHERE status='Available' ORDER BY reg_no")->fetchAll();
}
require 'includes/header.php';
$badge = ['Scheduled' => 'b-info', 'Completed' => 'b-ok', 'Cancelled' => 'b-err'];
?>
<?php if ($manager): ?>
<div class="panel">
  <h3 style="margin-top:0"><?= $edit ? 'Edit lesson' : 'Schedule a lesson' ?></h3>
  <form method="post" class="grid"><?= csrf_field() ?>
    <input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= $edit['schedule_id'] ?? 0 ?>">
    <label>Student <select name="student_id" required><option value="">— select —</option><?php foreach ($studentsL as $o) echo '<option value="' . $o['student_id'] . '" ' . (($edit['student_id'] ?? 0) == $o['student_id'] ? 'selected' : '') . '>' . e($o['full_name']) . '</option>'; ?></select></label>
    <label>Instructor <select name="instructor_id" required><option value="">— select —</option><?php foreach ($instrL as $o) echo '<option value="' . $o['instructor_id'] . '" ' . (($edit['instructor_id'] ?? 0) == $o['instructor_id'] ? 'selected' : '') . '>' . e($o['full_name']) . '</option>'; ?></select></label>
    <label>Vehicle <select name="vehicle_id"><option value="">— none —</option><?php foreach ($vehL as $o) echo '<option value="' . $o['vehicle_id'] . '" ' . (($edit['vehicle_id'] ?? 0) == $o['vehicle_id'] ? 'selected' : '') . '>' . e($o['reg_no'] . ' · ' . $o['model']) . '</option>'; ?></select></label>
    <label>Date <input type="date" name="lesson_date" required value="<?= e($edit['lesson_date'] ?? '') ?>"></label>
    <label>Time <input type="time" name="lesson_time" required value="<?= e(isset($edit['lesson_time']) ? substr($edit['lesson_time'], 0, 5) : '') ?>"></label>
    <label>Topic <input name="topic" value="<?= e($edit['topic'] ?? '') ?>"></label>
    <?php if ($edit): ?><label>Status <select name="status"><?php foreach (array_keys($badge) as $b) echo '<option ' . ($edit['status'] === $b ? 'selected' : '') . '>' . $b . '</option>'; ?></select></label><?php endif; ?>
    <div><button class="btn"><?= $edit ? 'Update' : 'Schedule' ?></button> <?php if ($edit) echo '<a class="btn grey" href="lessons.php">Cancel</a>'; ?></div>
  </form>
</div>
<?php endif; ?>
<?php if ((has_role('Instructor') && !$iid) || (has_role('Student') && !$sid)): ?>
<div class="panel">Your account is not linked to a profile yet. Please contact the front desk.</div>
<?php endif; ?>
<div class="panel">
  <form class="toolbar"><label style="display:flex;gap:8px;align-items:center">From <input type="date" name="from" value="<?= e($from) ?>"></label><button class="btn sm">Filter</button></form>
  <table><tr><th>Date</th><th>Time</th><th>Student</th><th>Instructor</th><th>Vehicle</th><th>Topic</th><th>Status</th><th></th></tr>
  <?php foreach ($rows as $r): ?>
    <tr><td><?= e($r['lesson_date']) ?></td><td><?= e(substr($r['lesson_time'], 0, 5)) ?></td><td><?= e($r['student']) ?></td><td><?= e($r['instructor']) ?></td><td><?= e($r['reg_no']) ?></td><td><?= e($r['topic']) ?></td>
    <td><span class="badge <?= $badge[$r['status']] ?>"><?= $r['status'] ?></span></td>
    <td>
      <?php if (($manager || $iid) && $r['status'] === 'Scheduled'): ?>
        <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= $r['schedule_id'] ?>"><input type="hidden" name="status" value="Completed"><button class="btn sm green">Complete</button></form>
        <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= $r['schedule_id'] ?>"><input type="hidden" name="status" value="Cancelled"><button class="btn sm grey">Cancel</button></form>
      <?php endif; ?>
      <?php if ($manager): ?><a class="btn sm" href="lessons.php?edit=<?= $r['schedule_id'] ?>">Edit</a><?php endif; ?>
      <?php if (has_role('Administrator')): ?><form method="post" class="inline" onsubmit="return confirm('Delete this lesson?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['schedule_id'] ?>"><button class="btn sm red">Delete</button></form><?php endif; ?>
    </td></tr>
  <?php endforeach; if (!$rows) echo '<tr><td colspan="8" class="muted">No lessons found.</td></tr>'; ?>
  </table>
</div>
<?php require 'includes/footer.php'; ?>
