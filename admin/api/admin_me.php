<?php
// admin/api/admin_me.php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();
header("Content-Type: application/json; charset=utf-8");

if (empty($_SESSION["admin_id"])) {
    http_response_code(401);
    echo json_encode(["ok" => false]);
    exit;
}

echo json_encode([
    "ok"       => true,
    "id"       => (int)$_SESSION["admin_id"],
    "username" => (string)$_SESSION["admin_username"],
    "role"     => (string)$_SESSION["admin_role"],
]);
