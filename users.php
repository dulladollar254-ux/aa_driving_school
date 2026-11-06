<?php
require 'config.php';
require_role(['Administrator']);
$page_title = 'Users'; $active = 'users.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['action'] ?? '';
    try {
        if ($act === 'add') {
            $name = $_POST['full_name'] ?? '';
            $un = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            if (!is_string($name) || !is_string($un) || !is_string($password)) throw new Exception('Invalid user details.');
            $name = trim($name); $un = trim($un);
            if ($name === '' || $un === '' || strlen($password) < 6) throw new Exception('Name, username and a password of 6+ characters are required.');
            if (!in_array($_POST['role'], ['Administrator', 'Receptionist'], true)) throw new Exception('Choose an administrator or receptionist role. Create instructor and student accounts from their profile forms.');
            create_login($un, $password, $name, $_POST['role']);
            flash('User created.');
        } elseif ($act === 'reset') {
            $password = $_POST['password'] ?? '';
            if (!is_string($password) || strlen($password) < 6) throw new Exception('Password must be at least 6 characters.');
            $id = request_int($_POST['id'] ?? null);
            if ($id === false || $id < 1) throw new Exception('Invalid user ID.');
            $pdo->prepare('UPDATE users SET password=? WHERE user_id=?')->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
            flash('Password reset.');
        } elseif ($act === 'delete') {
            $id = request_int($_POST['id'] ?? null);
            if ($id === false || $id < 1) throw new Exception('Invalid user ID.');
            if ($id === (int)user()['user_id']) throw new Exception('You cannot delete your own account.');
            $pdo->prepare('DELETE FROM users WHERE user_id=?')->execute([$id]);
            flash('User deleted.');
        }
    } catch (PDOException $ex) {
        error_log('User management request failed: ' . $ex->getMessage());
        flash($ex->getCode() == 23000 ? 'That username already exists.' : 'Unable to update users because of a database error.', 'err');
    } catch (Exception $ex) { flash($ex->getMessage(), 'err'); }
    redirect('users.php');
}
$rows = $pdo->query('SELECT * FROM users ORDER BY role, username')->fetchAll();
require 'includes/header.php';
?>
<div class="panel">
  <h3 style="margin-top:0">Add user</h3>
  <form method="post" class="grid"><?= csrf_field() ?><input type="hidden" name="action" value="add">
    <label>Full name <input name="full_name" required></label>
    <label>Username <input name="username" required autocomplete="off"></label>
    <label>Password <input type="password" name="password" required autocomplete="new-password"></label>
    <label>Role <select name="role"><option>Administrator</option><option selected>Receptionist</option></select></label>
    <div><button class="btn">Create</button></div>
  </form>
</div>
<div class="panel"><table><tr><th>#</th><th>Name</th><th>Username</th><th>Role</th><th>Created</th><th>Reset password</th><th></th></tr>
<?php foreach ($rows as $r): ?>
  <tr><td><?= $r['user_id'] ?></td><td><?= e($r['full_name']) ?></td><td><?= e($r['username']) ?></td><td><span class="badge b-info"><?= e($r['role']) ?></span></td><td><?= e($r['created_at']) ?></td>
  <td><form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="reset"><input type="hidden" name="id" value="<?= $r['user_id'] ?>"><input type="password" name="password" placeholder="new password" style="width:140px;display:inline" autocomplete="new-password"> <button class="btn sm">Set</button></form></td>
  <td><?php if ($r['user_id'] != user()['user_id']): ?><form method="post" class="inline" onsubmit="return confirm('Delete this user?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['user_id'] ?>"><button class="btn sm red">Delete</button></form><?php endif; ?></td></tr>
<?php endforeach; ?></table></div>
<?php require 'includes/footer.php'; ?>
