<?php
// admin/api/admin_db.php
// Shared database connection + helpers for Admin Panel
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Content-Type: application/json; charset=utf-8");
header("X-Content-Type-Options: nosniff");

// ── Database credentials (same as your existing project) ──
$DB_HOST = "localhost";
$DB_NAME = "new_web2_main";
$DB_USER = "root";
$DB_PASS = ""; // XAMPP default

try {
    $pdo = new PDO(
        "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(["ok" => false, "error" => "DB connection failed"]);
    exit;
}

// ── Helpers ──────────────────────────────────────────────

function require_admin_login(): array {
    if (empty($_SESSION["admin_id"])) {
        http_response_code(401);
        echo json_encode(["ok" => false, "error" => "Admin not logged in"]);
        exit;
    }
    return [
        "id"   => (int)$_SESSION["admin_id"],
        "role" => (string)$_SESSION["admin_role"],
    ];
}

function require_full_admin(): void {
    $a = require_admin_login();
    if ($a["role"] !== "admin") {
        http_response_code(403);
        echo json_encode(["ok" => false, "error" => "Full admin access required"]);
        exit;
    }
}

function require_post(): void {
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        http_response_code(405);
        echo json_encode(["ok" => false, "error" => "POST only"]);
        exit;
    }
}

function json_input(): array {
    if (!empty($_POST)) return $_POST;
    $raw = file_get_contents("php://input");
    $data = json_decode($raw ?: "{}", true);
    return is_array($data) ? $data : [];
}
