<?php
require 'config.php';
require_role(['Administrator', 'Receptionist']);
$page_title = 'Instructors'; $active = 'instructors.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['action'] ?? '';
    try {
        if ($act === 'delete' && has_role('Administrator')) {
            $pdo->prepare('DELETE FROM instructors WHERE instructor_id=?')->execute([(int)$_POST['id']]);
            flash('Instructor deleted.');
        } elseif ($act === 'save') {
            $d = [trim($_POST['full_name']), trim($_POST['phone']), trim($_POST['email']), trim($_POST['license_no'])];
            if ($d[0] === '' || $d[1] === '' || $d[3] === '') throw new Exception('Name, phone and license number are required.');
            $id = (int)($_POST['id'] ?? 0);
            if ($id) {
                $pdo->prepare('UPDATE instructors SET full_name=?,phone=?,email=?,license_no=? WHERE instructor_id=?')->execute([...$d, $id]);
                flash('Instructor updated.');
            } else {
                $uid = null;
                if (trim($_POST['username']) !== '') {
                    if (strlen($_POST['password']) < 6) throw new Exception('Password must be at least 6 characters.');
                    $uid = create_login(trim($_POST['username']), $_POST['password'], $d[0], 'Instructor');
                }
                $pdo->prepare('INSERT INTO instructors (full_name,phone,email,license_no,user_id) VALUES (?,?,?,?,?)')->execute([...$d, $uid]);
                flash('Instructor added.');
            }
        }
    } catch (PDOException $ex) {
        $m = $ex->getCode() == 23000 ? 'Duplicate license/username, or the instructor still has lessons assigned.' : $ex->getMessage();
        flash($m, 'err');
    } catch (Exception $ex) { flash($ex->getMessage(), 'err'); }
    redirect('instructors.php');
}

$edit = null;
if (isset($_GET['edit'])) { $s = $pdo->prepare('SELECT * FROM instructors WHERE instructor_id=?'); $s->execute([(int)$_GET['edit']]); $edit = $s->fetch(); }
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
