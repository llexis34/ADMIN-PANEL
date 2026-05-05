```php
<?php
// api/auth_login.php
declare(strict_types=1);
require __DIR__ . "/db.php";

require_post();
$data = json_input();

// ✅ Ensure correct timezone (important for daily/monthly stats)
date_default_timezone_set("Asia/Manila");

$email = strtolower(trim($data["email"] ?? ""));
$password = (string)($data["password"] ?? "");

if ($email === "" || $password === "") {
  http_response_code(400);
  echo json_encode([
    "ok" => false,
    "error" => "Email and password are required"
  ]);
  exit;
}

try {
  // 🔍 Get user
  $st = $pdo->prepare("
    SELECT id, email, password_hash 
    FROM users 
    WHERE email = ? 
    LIMIT 1
  ");
  $st->execute([$email]);
  $user = $st->fetch();

  // ❌ Invalid login
  if (!$user || !password_verify($password, $user["password_hash"])) {
    http_response_code(401);
    echo json_encode([
      "ok" => false,
      "error" => "Invalid credentials"
    ]);
    exit;
  }

  // ✅ Create session
  $_SESSION["user_id"] = (int)$user["id"];
  $_SESSION["email"] = (string)$user["email"];

  // ✅ LOGIN TRACKING (NEW)
  $log = $pdo->prepare("
    INSERT INTO user_login_logs (user_id)
    VALUES (?)
  ");
  $log->execute([(int)$user["id"]]);

  // ✅ Success response
  echo json_encode([
    "ok" => true
  ]);

} catch (Throwable $e) { 
  http_response_code(500);
  echo json_encode([
    "ok" => false,
    "error" => "Server error"
  ]);
}

