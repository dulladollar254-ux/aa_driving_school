<?php
// Core configuration, database connection and helper functions.
session_start();

const DB_HOST = 'localhost';
const DB_NAME = 'aa_driving_school';
const DB_USER = 'root';
const DB_PASS = '';
const APP_NAME = 'AA Driving School';
const PASS_MARK = 60; // test pass mark (%)

try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $ex) {
    die('<h3>Database connection failed.</h3><p>Start MySQL in XAMPP and import <b>database.sql</b> first.</p>');
}

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function money($n) { return 'KES ' . number_format((float)$n, 2); }
function redirect($url) { header("Location: $url"); exit; }
function user() { return $_SESSION['user'] ?? null; }
function flash($msg, $type = 'ok') { $_SESSION['flash'] = [$msg, $type]; }

function csrf_token() {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function csrf_field() { return '<input type="hidden" name="csrf" value="' . csrf_token() . '">'; }
function csrf_check() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
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
