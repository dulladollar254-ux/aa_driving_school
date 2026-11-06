<?php
require 'config.php';
require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}
csrf_check();
$_SESSION = [];
session_destroy();
header('Location: index.php');
exit;
