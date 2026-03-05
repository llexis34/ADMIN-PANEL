<?php
// admin/api/admin_logout.php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();
header("Content-Type: application/json; charset=utf-8");

$_SESSION = [];
session_destroy();
echo json_encode(["ok" => true]);
