<?php
require 'config.php';
require_role(['Administrator', 'Receptionist']);
$page_title = 'Instructors'; $active = 'instructors.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['action'] ?? '';
    try {
        $id = request_int($_POST['id'] ?? '0');
        if ($id === false || $id < 0) throw new Exception('Invalid instructor ID.');
        if ($act === 'delete' && has_role('Administrator')) {
            $pdo->prepare('DELETE FROM instructors WHERE instructor_id=?')->execute([$id]);
            flash('Instructor deleted.');
        } elseif ($act === 'save') {
            $fields = [];
            foreach (['full_name', 'phone', 'email', 'license_no'] as $field) {
                $value = $_POST[$field] ?? '';
                if (!is_string($value)) throw new Exception('Invalid instructor details.');
                $fields[$field] = trim($value);
            }
            $d = [$fields['full_name'], $fields['phone'], $fields['email'], $fields['license_no']];
            if ($d[0] === '' || $d[1] === '' || $d[3] === '') throw new Exception('Name, phone and license number are required.');
            if ($d[2] !== '' && !filter_var($d[2], FILTER_VALIDATE_EMAIL)) throw new Exception('Enter a valid email address.');
            if ($id) {
                $pdo->prepare('UPDATE instructors SET full_name=?,phone=?,email=?,license_no=? WHERE instructor_id=?')->execute([...$d, $id]);
                flash('Instructor updated.');
            } else {
                $pdo->beginTransaction();
                $uid = null;
                $username = $_POST['username'] ?? '';
                $password = $_POST['password'] ?? '';
                if (!is_string($username) || !is_string($password)) throw new Exception('Invalid login details.');
                if (trim($username) !== '') {
                    if (strlen($password) < 6) throw new Exception('Password must be at least 6 characters.');
                    $uid = create_login(trim($username), $password, $d[0], 'Instructor');
                }
                $pdo->prepare('INSERT INTO instructors (full_name,phone,email,license_no,user_id) VALUES (?,?,?,?,?)')->execute([...$d, $uid]);
                $pdo->commit();
                flash('Instructor added.');
            }
        }
    } catch (PDOException $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Instructor save failed: ' . $ex->getMessage());
        $m = $ex->getCode() == 23000 ? 'Duplicate license/username, or the instructor still has lessons assigned.' : 'Unable to save the instructor because of a database error.';
        flash($m, 'err');
    } catch (Exception $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash($ex->getMessage(), 'err');
    }
    redirect('instructors.php');
}

$edit = null;
if (isset($_GET['edit'])) {
    $editId = request_int($_GET['edit']);
    if ($editId === false || $editId < 1) { http_response_code(400); exit('Invalid instructor ID.'); }
    $s = $pdo->prepare('SELECT * FROM instructors WHERE instructor_id=?'); $s->execute([$editId]); $edit = $s->fetch();
}
$rows = $pdo->query('SELECT i.*, (SELECT COUNT(*) FROM lessons WHERE instructor_id=i.instructor_id) lessons FROM instructors i ORDER BY full_name')->fetchAll();
require 'includes/header.php';
?>
<div class="panel">
  <h3 style="margin-top:0"><?= $edit ? 'Edit instructor' : 'Add instructor' ?></h3>
  <form method="post" class="grid"><?= csrf_field() ?>
    <input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= $edit['instructor_id'] ?? 0 ?>">
    <label>Full name <input name="full_name" required value="<?= e($edit['full_name'] ?? '') ?>"></label>
    <label>Phone <input name="phone" required value="<?= e($edit['phone'] ?? '') ?>"></label>
    <label>Email <input type="email" name="email" value="<?= e($edit['email'] ?? '') ?>"></label>
    <label>License no. <input name="license_no" required value="<?= e($edit['license_no'] ?? '') ?>"></label>
    <?php if (!$edit): ?>
      <label>Login username (optional) <input name="username" autocomplete="off"></label>
      <label>Login password <input type="password" name="password" autocomplete="new-password"></label>
    <?php endif; ?>
    <div><button class="btn"><?= $edit ? 'Update' : 'Add' ?></button> <?php if ($edit) echo '<a class="btn grey" href="instructors.php">Cancel</a>'; ?></div>
  </form>
</div>
<div class="panel"><table><tr><th>#</th><th>Name</th><th>Phone</th><th>Email</th><th>License no.</th><th>Lessons</th><th></th></tr>
<?php foreach ($rows as $r): ?>
  <tr><td><?= $r['instructor_id'] ?></td><td><?= e($r['full_name']) ?></td><td><?= e($r['phone']) ?></td><td><?= e($r['email']) ?></td><td><?= e($r['license_no']) ?></td><td><?= $r['lessons'] ?></td>
  <td><a class="btn sm" href="instructors.php?edit=<?= $r['instructor_id'] ?>">Edit</a>
  <?php if (has_role('Administrator')): ?><form method="post" class="inline" onsubmit="return confirm('Delete this instructor?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['instructor_id'] ?>"><button class="btn sm red">Delete</button></form><?php endif; ?></td></tr>
<?php endforeach; ?></table></div>
<?php require 'includes/footer.php'; ?>
