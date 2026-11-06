<?php
require 'config.php';
require_role(['Administrator', 'Receptionist']);
$page_title = 'Vehicles'; $active = 'vehicles.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['action'] ?? '';
    try {
        $id = request_int($_POST['id'] ?? '0');
        if ($id === false || $id < 0) throw new Exception('Invalid vehicle ID.');
        if ($act === 'delete' && has_role('Administrator')) {
            $pdo->prepare('DELETE FROM vehicles WHERE vehicle_id=?')->execute([$id]);
            flash('Vehicle deleted.');
        } elseif ($act === 'save') {
            $regNo = $_POST['reg_no'] ?? '';
            $model = $_POST['model'] ?? '';
            $category = $_POST['category'] ?? '';
            $status = $_POST['status'] ?? '';
            if (!is_string($regNo) || !is_string($model) || !is_string($category) || !is_string($status)) throw new Exception('Invalid vehicle details.');
            if (!in_array($category, ['Class A', 'Class B', 'Class C', 'Class D'], true) || !in_array($status, ['Available', 'Maintenance'], true)) throw new Exception('Choose a valid vehicle category and status.');
            $d = [strtoupper(trim($regNo)), trim($model), $category, $status];
            if ($d[0] === '' || $d[1] === '') throw new Exception('Registration number and model are required.');
            if ($id) {
                $pdo->prepare('UPDATE vehicles SET reg_no=?,model=?,category=?,status=? WHERE vehicle_id=?')->execute([...$d, $id]);
                flash('Vehicle updated.');
            } else {
                $pdo->prepare('INSERT INTO vehicles (reg_no,model,category,status) VALUES (?,?,?,?)')->execute($d);
                flash('Vehicle added.');
            }
        }
    } catch (PDOException $ex) {
        error_log('Vehicle save failed: ' . $ex->getMessage());
        flash($ex->getCode() == 23000 ? 'That registration number already exists.' : 'Unable to save the vehicle because of a database error.', 'err');
    } catch (Exception $ex) { flash($ex->getMessage(), 'err'); }
    redirect('vehicles.php');
}

$edit = null;
if (isset($_GET['edit'])) {
    $editId = request_int($_GET['edit']);
    if ($editId === false || $editId < 1) { http_response_code(400); exit('Invalid vehicle ID.'); }
    $s = $pdo->prepare('SELECT * FROM vehicles WHERE vehicle_id=?'); $s->execute([$editId]); $edit = $s->fetch();
}
$rows = $pdo->query('SELECT * FROM vehicles ORDER BY reg_no')->fetchAll();
require 'includes/header.php';
?>
<div class="panel">
  <h3 style="margin-top:0"><?= $edit ? 'Edit vehicle' : 'Add vehicle' ?></h3>
  <form method="post" class="grid"><?= csrf_field() ?>
    <input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= $edit['vehicle_id'] ?? 0 ?>">
    <label>Registration no. <input name="reg_no" required value="<?= e($edit['reg_no'] ?? '') ?>"></label>
    <label>Model <input name="model" required value="<?= e($edit['model'] ?? '') ?>"></label>
    <label>Category <select name="category"><?php foreach (['Class A', 'Class B', 'Class C', 'Class D'] as $c) echo '<option ' . (($edit['category'] ?? '') === $c ? 'selected' : '') . '>' . $c . '</option>'; ?></select></label>
    <label>Status <select name="status"><?php foreach (['Available', 'Maintenance'] as $c) echo '<option ' . (($edit['status'] ?? '') === $c ? 'selected' : '') . '>' . $c . '</option>'; ?></select></label>
    <div><button class="btn"><?= $edit ? 'Update' : 'Add' ?></button> <?php if ($edit) echo '<a class="btn grey" href="vehicles.php">Cancel</a>'; ?></div>
  </form>
</div>
<div class="panel"><table><tr><th>#</th><th>Reg. no.</th><th>Model</th><th>Category</th><th>Status</th><th></th></tr>
<?php foreach ($rows as $r): ?>
  <tr><td><?= $r['vehicle_id'] ?></td><td><?= e($r['reg_no']) ?></td><td><?= e($r['model']) ?></td><td><?= e($r['category']) ?></td>
  <td><span class="badge <?= $r['status'] === 'Available' ? 'b-ok' : 'b-warn' ?>"><?= e($r['status']) ?></span></td>
  <td><a class="btn sm" href="vehicles.php?edit=<?= $r['vehicle_id'] ?>">Edit</a>
  <?php if (has_role('Administrator')): ?><form method="post" class="inline" onsubmit="return confirm('Delete this vehicle?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['vehicle_id'] ?>"><button class="btn sm red">Delete</button></form><?php endif; ?></td></tr>
<?php endforeach; ?></table></div>
<?php require 'includes/footer.php'; ?>
