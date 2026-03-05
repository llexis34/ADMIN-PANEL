<?php
// api/auth_register.php
declare(strict_types=1);
require __DIR__ . "/db.php";

require_post();
$data = json_input();

$email = strtolower(trim($data["email"] ?? ""));
$password = (string)($data["password"] ?? "");
$first = trim($data["first_name"] ?? $data["firstName"] ?? "");
$last  = trim($data["last_name"] ?? $data["lastName"] ?? "");
$phone = trim($data["phone"] ?? "");

if ($email === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  http_response_code(400);
  echo json_encode(["ok" => false, "error" => "Valid email is required"]);
  exit;
}
if (strlen($password) < 8) {
  http_response_code(400);
  echo json_encode(["ok" => false, "error" => "Password must be at least 8 characters"]);
  exit;
}

try {
  // check existing
  $st = $pdo->prepare("SELECT id FROM users WHERE email = ?");
  $st->execute([$email]);
  if ($st->fetch()) {
    http_response_code(409);
    echo json_encode(["ok" => false, "error" => "Email already registered"]);
    exit;
  }

  $hash = password_hash($password, PASSWORD_DEFAULT);

  $ins = $pdo->prepare("INSERT INTO users (email, password_hash, first_name, last_name, phone) VALUES (?,?,?,?,?)");
  $ins->execute([$email, $hash, $first ?: null, $last ?: null, $phone ?: null]);

  // auto-login after signup (optional but common)
  $_SESSION["user_id"] = (int)$pdo->lastInsertId();
  $_SESSION["email"] = $email;

  echo json_encode(["ok" => true]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(["ok" => false, "error" => "Server error"]);
}