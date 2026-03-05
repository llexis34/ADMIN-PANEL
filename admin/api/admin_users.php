<?php
// admin/api/admin_users.php
// Handles: list users, edit, delete, toggle active
declare(strict_types=1);
require __DIR__ . "/admin_db.php";

$admin  = require_admin_login();
$action = $_GET["action"] ?? "list";

try {
    // ── LIST users ──────────────────────────────────────────
    if ($action === "list") {
    $search = trim($_GET["search"] ?? "");
    $params = [];
    $where  = "";

    if ($search !== "") {
        $where  = "WHERE first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR phone LIKE ? OR CAST(id AS CHAR) LIKE ?";
        $like   = "%" . $search . "%";
        $params = [$like, $like, $like, $like, $like];
    }

    $sql = "SELECT id, first_name, last_name, email, phone, is_active, created_at FROM users {$where} ORDER BY created_at DESC";
    $st  = $pdo->prepare($sql);
    $st->execute($params);
    echo json_encode(["ok" => true, "data" => $st->fetchAll()]);
    exit;
}

    // ── TOGGLE ACTIVE ───────────────────────────────────────
    if ($action === "toggle_active") {
        require_post();
        require_full_admin();
        $data      = json_input();
        $id        = (int)($data["id"] ?? 0);
        $is_active = (int)(bool)($data["is_active"] ?? 0);

        $pdo->prepare("UPDATE users SET is_active=? WHERE id=?")->execute([$is_active, $id]);
        echo json_encode(["ok" => true]);
        exit;
    }

    // ── DELETE user ─────────────────────────────────────────
    if ($action === "delete") {
        require_post();
        require_full_admin();
        $data = json_input();
        $id   = (int)($data["id"] ?? 0);

        // Also delete their membership submissions
        $pdo->prepare("DELETE FROM membership_submissions WHERE user_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]);

        echo json_encode(["ok" => true]);
        exit;
    }

    echo json_encode(["ok" => false, "error" => "Unknown action"]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(["ok" => false, "error" => "Server error"]);
}
