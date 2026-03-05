<?php
// api/auth_login.php
declare(strict_types=1);
require __DIR__ . "/db.php";

require_post();
$data = json_input();

$email = strtolower(trim($data["email"] ?? ""));
$password = (string)($data["password"] ?? "");

if ($email === "" || $password === "") {
  http_response_code(400);
  echo json_encode(["ok" => false, "error" => "Email and password are required"]);
  exit;
}

try {
  $st = $pdo->prepare("SELECT id, email, password_hash FROM users WHERE email = ? LIMIT 1");
  $st->execute([$email]);
  $user = $st->fetch();

  if (!$user || !password_verify($password, $user["password_hash"])) {
    http_response_code(401);
    echo json_encode(["ok" => false, "error" => "Invalid credentials"]);
    exit;
  }

  $_SESSION["user_id"] = (int)$user["id"];
  $_SESSION["email"] = (string)$user["email"];

  echo json_encode(["ok" => true]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(["ok" => false, "error" => "Server error"]);
}