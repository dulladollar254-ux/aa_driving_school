<?php
// Core configuration, database connection and helper functions.
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
$is_https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
$cookie_params = session_get_cookie_params();
session_set_cookie_params([
    'lifetime' => 0,
    'path' => $cookie_params['path'] ?: '/',
    'domain' => $cookie_params['domain'],
    'secure' => $is_https,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

const APP_NAME = 'AA Driving School';
const PASS_MARK = 60;

$db_config = [
    'host' => 'localhost',
    'name' => 'aa_driving_school',
    'user' => 'root',
    'pass' => '',
];
$local_config_file = __DIR__ . '/config.local.php';
if (is_file($local_config_file)) {
    $local_config = require $local_config_file;
    if (!is_array($local_config)) {
        http_response_code(500);
        die('Invalid database configuration. Check config.local.php.');
    }
    $db_config = array_replace($db_config, $local_config);
}
$environment_password = getenv('AA_DRIVING_DB_PASS');
if ($environment_password !== false) {
    $db_config['pass'] = $environment_password;
}

try {
    $pdo = new PDO(
        'mysql:host=' . $db_config['host'] . ';dbname=' . $db_config['name'] . ';charset=utf8mb4',
        $db_config['user'],
        $db_config['pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $ex) {
    error_log('Database connection failed: ' . $ex->getMessage());
    http_response_code(503);
    die('<h3>Database connection failed.</h3><p>Verify that MySQL is running, the database exists, and the settings in <b>config.local.php</b> are correct.</p>');
}

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function money($n) { return 'KES ' . number_format((float)$n, 2); }
function redirect($url) { header("Location: $url"); exit; }
function user() { return $_SESSION['user'] ?? null; }
function flash($msg, $type = 'ok') { $_SESSION['flash'] = [$msg, $type]; }
function request_int($value) { return is_string($value) ? filter_var($value, FILTER_VALIDATE_INT) : false; }

function valid_date($value) {
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return false;
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    $errors = DateTimeImmutable::getLastErrors();
    return $date && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
        && $date->format('Y-m-d') === $value;
}

function csrf_token() {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field() { return '<input type="hidden" name="csrf" value="' . csrf_token() . '">'; }
function csrf_check() {
    $session_token = $_SESSION['csrf'] ?? null;
    $posted_token = $_POST['csrf'] ?? null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!is_string($session_token) || !is_string($posted_token) || !hash_equals($session_token, $posted_token))) {
        http_response_code(400);
        die('Invalid or expired form. Go back and try again.');
    }
}

function require_login() { if (!user()) redirect('index.php'); }
function require_role(array $roles) {
    require_login();
    if (!in_array(user()['role'], $roles, true)) {
        http_response_code(403);
        die('Access denied: your role cannot open this page.');
    }
}
function has_role(...$roles) { return user() && in_array(user()['role'], $roles, true); }

// Current logged-in student's / instructor's profile id (or null)
function my_student_id() {
    global $pdo;
    $s = $pdo->prepare('SELECT student_id FROM students WHERE user_id=?');
    $s->execute([user()['user_id']]);
    return $s->fetchColumn() ?: null;
}
function my_instructor_id() {
    global $pdo;
    $s = $pdo->prepare('SELECT instructor_id FROM instructors WHERE user_id=?');
    $s->execute([user()['user_id']]);
    return $s->fetchColumn() ?: null;
}

// Fee balance for a student
function student_balance($student_id) {
    global $pdo;
    $s = $pdo->prepare('SELECT s.course_fee - COALESCE((SELECT SUM(amount) FROM payments WHERE student_id=s.student_id),0) FROM students s WHERE s.student_id=?');
    $s->execute([$student_id]);
    return (float)$s->fetchColumn();
}

// Create a login account; returns user_id. Throws on duplicate username.
function create_login($username, $password, $full_name, $role) {
    global $pdo;
    $st = $pdo->prepare('INSERT INTO users (username,password,full_name,role) VALUES (?,?,?,?)');
    $st->execute([$username, password_hash($password, PASSWORD_DEFAULT), $full_name, $role]);
    return (int)$pdo->lastInsertId();
}
