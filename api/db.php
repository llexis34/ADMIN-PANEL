<?php
// api/db.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

header("Content-Type: application/json; charset=utf-8");

// ✅ Change these to match phpMyAdmin credentials
$DB_HOST = "localhost";
$DB_NAME = "new_web2_main";
$DB_USER = "root";
$DB_PASS = "";

try {
    $pdo = new PDO(
        "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "error" => "Database connection failed: " . $e->getMessage()
    ]);
    exit;
}

function json_input(): array {
  // 1) If request is form-data or x-www-form-urlencoded, use $_POST
  if (!empty($_POST)) {
    return $_POST;
  }

  // 2) Otherwise try JSON body
  $raw = file_get_contents("php://input");
  $data = json_decode($raw ?: "{}", true);
  return is_array($data) ? $data : [];
}

function require_post(): void {
  if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["ok" => false, "error" => "POST only"]);
    exit;
  }
}

function require_login(): int {
  if (!isset($_SESSION["user_id"])) {
    http_response_code(401);
    echo json_encode(["ok" => false, "error" => "Not logged in"]);
    exit;
  }
  return (int)$_SESSION["user_id"];
}