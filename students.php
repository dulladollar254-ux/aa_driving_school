<?php
require 'config.php';
require_role(['Administrator', 'Receptionist']);
$page_title = 'Students'; $active = 'students.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['action'] ?? '';
    try {
        $id = request_int($_POST['id'] ?? '0');
        if ($id === false || $id < 0) throw new Exception('Invalid student ID.');
        if ($act === 'delete' && has_role('Administrator')) {
            $pdo->prepare('DELETE FROM students WHERE student_id=?')->execute([$id]);
            flash('Student deleted.');
        } elseif ($act === 'save') {
            $fields = [];
            foreach (['full_name', 'gender', 'id_number', 'phone', 'email', 'address', 'course', 'course_fee'] as $field) {
                $value = $_POST[$field] ?? '';
                if (!is_string($value)) throw new Exception('Invalid student details.');
                $fields[$field] = trim($value);
            }
            if ($fields['full_name'] === '' || $fields['phone'] === '') throw new Exception('Name and phone are required.');
            if (!in_array($fields['gender'], ['Male', 'Female', 'Other'], true)) throw new Exception('Choose a valid gender.');
            if (!in_array($fields['course'], ['Class A (Motorcycle)', 'Class B (Light vehicle)', 'Class C (Light truck)', 'Class D (PSV / Bus)', 'Refresher course'], true)) throw new Exception('Choose a valid course.');
            if (!is_numeric($fields['course_fee']) || !is_finite((float)$fields['course_fee']) || (float)$fields['course_fee'] < 0) throw new Exception('Enter a valid non-negative course fee.');
            if ($fields['email'] !== '' && !filter_var($fields['email'], FILTER_VALIDATE_EMAIL)) throw new Exception('Enter a valid email address.');
            $d = [$fields['full_name'], $fields['gender'], $fields['id_number'], $fields['phone'],
                  $fields['email'], $fields['address'], $fields['course'], (float)$fields['course_fee']];
            if ($id) {
                $pdo->prepare('UPDATE students SET full_name=?,gender=?,id_number=?,phone=?,email=?,address=?,course=?,course_fee=? WHERE student_id=?')->execute([...$d, $id]);
                flash('Student updated.');
            } else {
                $pdo->beginTransaction();
                $uid = null;
                $username = $_POST['username'] ?? '';
                $password = $_POST['password'] ?? '';
                if (!is_string($username) || !is_string($password)) throw new Exception('Invalid login details.');
                if (trim($username) !== '') {
                    if (strlen($password) < 6) throw new Exception('Password must be at least 6 characters.');
                    $uid = create_login(trim($username), $password, $d[0], 'Student');
                }
                $pdo->prepare('INSERT INTO students (full_name,gender,id_number,phone,email,address,course,course_fee,user_id,reg_date) VALUES (?,?,?,?,?,?,?,?,?,CURDATE())')->execute([...$d, $uid]);
                $pdo->commit();
                flash('Student registered.');
            }
        }
    } catch (PDOException $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Student save failed: ' . $ex->getMessage());
        flash($ex->getCode() == 23000 ? 'That username or student record conflicts with existing data.' : 'Unable to save the student because of a database error.', 'err');
    } catch (Exception $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash($ex->getMessage(), 'err');
    }
    redirect('students.php');
}

$edit = null;
if (isset($_GET['edit'])) {
    $editId = request_int($_GET['edit']);
    if ($editId === false || $editId < 1) { http_response_code(400); exit('Invalid student ID.'); }
    $s = $pdo->prepare('SELECT * FROM students WHERE student_id=?'); $s->execute([$editId]); $edit = $s->fetch();
}
$q = trim($_GET['q'] ?? '');
$st = $pdo->prepare("SELECT s.*, s.course_fee - COALESCE((SELECT SUM(amount) FROM payments WHERE student_id=s.student_id),0) balance FROM students s WHERE s.full_name LIKE ? OR s.phone LIKE ? OR s.id_number LIKE ? ORDER BY s.student_id DESC");
$st->execute(["%$q%", "%$q%", "%$q%"]);
$rows = $st->fetchAll();
require 'includes/header.php';
$courses = ['Class A (Motorcycle)', 'Class B (Light vehicle)', 'Class C (Light truck)', 'Class D (PSV / Bus)', 'Refresher course'];
?>
<div class="panel">
  <h3 style="margin-top:0"><?= $edit ? 'Edit student' : 'Register new student' ?></h3>
  <form method="post" class="grid"><?= csrf_field() ?>
    <input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= $edit['student_id'] ?? 0 ?>">
    <label>Full name <input name="full_name" required value="<?= e($edit['full_name'] ?? '') ?>"></label>
    <label>Gender <select name="gender"><?php foreach (['Male', 'Female', 'Other'] as $g) echo '<option ' . (($edit['gender'] ?? '') === $g ? 'selected' : '') . '>' . $g . '</option>'; ?></select></label>
    <label>ID number <input name="id_number" value="<?= e($edit['id_number'] ?? '') ?>"></label>
    <label>Phone <input name="phone" required value="<?= e($edit['phone'] ?? '') ?>"></label>
    <label>Email <input type="email" name="email" value="<?= e($edit['email'] ?? '') ?>"></label>
    <label>Address <input name="address" value="<?= e($edit['address'] ?? '') ?>"></label>
    <label>Course <select name="course"><?php foreach ($courses as $c) echo '<option ' . (($edit['course'] ?? 'Class B (Light vehicle)') === $c ? 'selected' : '') . '>' . $c . '</option>'; ?></select></label>
    <label>Course fee (KES) <input type="number" step="0.01" min="0" name="course_fee" required value="<?= e($edit['course_fee'] ?? '15000') ?>"></label>
    <?php if (!$edit): ?>
      <label>Login username (optional) <input name="username" autocomplete="off"></label>
      <label>Login password <input type="password" name="password" autocomplete="new-password"></label>
    <?php endif; ?>
    <div><button class="btn"><?= $edit ? 'Update' : 'Register' ?></button> <?php if ($edit) echo '<a class="btn grey" href="students.php">Cancel</a>'; ?></div>
  </form>
</div>
<div class="panel">
  <form class="toolbar"><input name="q" placeholder="Search name, phone, ID…" value="<?= e($q) ?>"><button class="btn sm">Search</button></form>
  <table><tr><th>#</th><th>Name</th><th>Phone</th><th>Course</th><th>Fee</th><th>Balance</th><th>Registered</th><th></th></tr>
  <?php foreach ($rows as $r): ?>
    <tr><td><?= $r['student_id'] ?></td><td><?= e($r['full_name']) ?></td><td><?= e($r['phone']) ?></td><td><?= e($r['course']) ?></td>
      <td><?= money($r['course_fee']) ?></td>
      <td><span class="badge <?= $r['balance'] > 0 ? 'b-warn' : 'b-ok' ?>"><?= money(max($r['balance'], 0)) ?></span></td>
      <td><?= e($r['reg_date']) ?></td>
      <td><a class="btn sm" href="students.php?edit=<?= $r['student_id'] ?>">Edit</a>
        <?php if (has_role('Administrator')): ?>
        <form method="post" class="inline" onsubmit="return confirm('Delete this student and all their lessons, payments and results?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['student_id'] ?>"><button class="btn sm red">Delete</button></form>
        <?php endif; ?></td></tr>
  <?php endforeach; if (!$rows) echo '<tr><td colspan="8" class="muted">No students found.</td></tr>'; ?>
  </table>
</div>
<?php require 'includes/footer.php'; ?>
