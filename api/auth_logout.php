<?php
// api/auth_logout.php
declare(strict_types=1);
require __DIR__ . "/db.php";

session_destroy();
echo json_encode(["ok" => true]);