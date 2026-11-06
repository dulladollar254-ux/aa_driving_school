<?php
require 'config.php';
require_role(['Administrator', 'Instructor', 'Student']);
$page_title = 'Test Results'; $active = 'results.php';
$manager = has_role('Administrator', 'Instructor');
$isStudent = has_role('Student');
$sid = $isStudent ? my_student_id() : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        if (!$manager) throw new Exception('Not allowed.');
        if (($_POST['action'] ?? '') === 'delete') {
            if (!has_role('Administrator')) throw new Exception('Only the administrator can delete results.');
            $id = request_int($_POST['id'] ?? null);
            if ($id === false || $id < 1) throw new Exception('Invalid result ID.');
            $pdo->prepare('DELETE FROM test_results WHERE result_id=?')->execute([$id]);
            flash('Result deleted.');
        } else {
            $studentValue = $_POST['student_id'] ?? null;
            $scoreValue = $_POST['score'] ?? null;
            $studentId = request_int($studentValue);
            $score = request_int($scoreValue);
            if ($studentId === false || $studentId < 1 || $score === false || $score < 0 || $score > 100) throw new Exception('Select a student and enter a score between 0 and 100.');
            $testType = $_POST['test_type'] ?? '';
            if (!in_array($testType, ['Theory', 'Practical'], true)) throw new Exception('Bad test type.');
            $date = $_POST['test_date'] ?? '';
            if ($date === '') $date = date('Y-m-d');
            if (!valid_date($date)) throw new Exception('Enter a valid test date.');
            $remarks = $_POST['remarks'] ?? '';
            if (!is_string($remarks)) throw new Exception('Enter valid remarks.');
            $pdo->prepare('INSERT INTO test_results (student_id,test_type,test_date,score,remarks) VALUES (?,?,?,?,?)')
                ->execute([$studentId, $testType, $date, $score, trim($remarks)]);
            flash('Result recorded.');
        }
    } catch (PDOException $ex) {
        error_log('Test result request failed: ' . $ex->getMessage());
        flash('Unable to save the result because of a database error.', 'err');
    } catch (Exception $ex) { flash($ex->getMessage(), 'err'); }
    redirect('results.php');
}

$sql = 'SELECT r.*, s.full_name student FROM test_results r JOIN students s USING(student_id)' . ($isStudent ? ' WHERE r.student_id=?' : '') . ' ORDER BY test_date DESC, result_id DESC LIMIT 200';
$st = $pdo->prepare($sql); $st->execute($isStudent ? [$sid] : []); $rows = $st->fetchAll();
$studentsL = $manager ? $pdo->query('SELECT student_id, full_name FROM students ORDER BY full_name')->fetchAll() : [];
require 'includes/header.php';
?>
<?php if ($manager): ?>
<div class="panel">
  <h3 style="margin-top:0">Record test result <span class="muted small">(pass mark <?= PASS_MARK ?>%)</span></h3>
  <form method="post" class="grid"><?= csrf_field() ?>
    <label>Student <select name="student_id" required><option value="">— select —</option><?php foreach ($studentsL as $o) echo '<option value="' . $o['student_id'] . '">' . e($o['full_name']) . '</option>'; ?></select></label>
    <label>Test type <select name="test_type"><option>Theory</option><option>Practical</option></select></label>
    <label>Date <input type="date" name="test_date" value="<?= date('Y-m-d') ?>"></label>
    <label>Score (0–100) <input type="number" name="score" min="0" max="100" required></label>
    <label>Remarks <input name="remarks"></label>
    <div><button class="btn">Save result</button></div>
  </form>
</div>
<?php endif; ?>
<?php if ($isStudent && !$sid): ?>
<div class="panel">Your account is not linked to a student profile yet. Please contact the front desk.</div>
<?php endif; ?>
<div class="panel"><table><tr><th>Date</th><th>Student</th><th>Test</th><th>Score</th><th>Outcome</th><th>Remarks</th><th></th></tr>
<?php foreach ($rows as $r): $pass = $r['score'] >= PASS_MARK; ?>
  <tr><td><?= e($r['test_date']) ?></td><td><?= e($r['student']) ?></td><td><?= e($r['test_type']) ?></td><td><?= $r['score'] ?>%</td>
  <td><span class="badge <?= $pass ? 'b-ok' : 'b-err' ?>"><?= $pass ? 'Pass' : 'Fail' ?></span></td><td><?= e($r['remarks']) ?></td>
  <td><?php if (has_role('Administrator')): ?><form method="post" class="inline" onsubmit="return confirm('Delete this result?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['result_id'] ?>"><button class="btn sm red">Delete</button></form><?php endif; ?></td></tr>
<?php endforeach; if (!$rows) echo '<tr><td colspan="7" class="muted">No results recorded.</td></tr>'; ?>
</table></div>
<?php require 'includes/footer.php'; ?>
