<?php
// admin/api/admin_login.php
declare(strict_types=1);
require __DIR__ . "/admin_db.php";

require_post();
$data = json_input();

$username = trim($data["username"] ?? "");
$password = (string)($data["password"] ?? "");

if ($username === "" || $password === "") {
    http_response_code(400);
    echo json_encode(["ok" => false, "error" => "Username and password are required"]);
    exit;
}

try {
    $st = $pdo->prepare("SELECT id, username, email, password_hash, role, is_active FROM admin_accounts WHERE username = ? LIMIT 1");
    $st->execute([$username]);
    $admin = $st->fetch();

    if (!$admin || !password_verify($password, $admin["password_hash"])) {
        http_response_code(401);
        echo json_encode(["ok" => false, "error" => "Invalid username or password"]);
        exit;
    }

    if (!$admin["is_active"]) {
        http_response_code(403);
        echo json_encode(["ok" => false, "error" => "Account is disabled"]);
        exit;
    }

    // Update last login
    $pdo->prepare("UPDATE admin_accounts SET last_login = NOW() WHERE id = ?")->execute([$admin["id"]]);

    $_SESSION["admin_id"]       = (int)$admin["id"];
    $_SESSION["admin_username"] = $admin["username"];
    $_SESSION["admin_role"]     = $admin["role"];

    echo json_encode([
        "ok"   => true,
        "role" => $admin["role"],
        "name" => $admin["username"],
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(["ok" => false, "error" => "Server error"]);
}
