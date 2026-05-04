<?php
// admin/api/admin_me.php
declare(strict_types=1);
require __DIR__ . "/admin_db.php";

$admin = require_admin_login();

echo json_encode([
    "ok"       => true,
    "id"       => $admin["id"],
    "username" => (string)$_SESSION["admin_username"],
    "role"     => $admin["role"],
]);
