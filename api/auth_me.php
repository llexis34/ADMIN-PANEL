<?php
// api/auth_me.php
declare(strict_types=1);
require __DIR__ . "/db.php";

if (!isset($_SESSION["user_id"])) {
  http_response_code(401);
  echo json_encode(["ok" => false]);
  exit;
}

echo json_encode([
  "ok" => true,
  "user" => [
    "id" => (int)$_SESSION["user_id"],
    "email" => (string)($_SESSION["email"] ?? "")
  ]
]);