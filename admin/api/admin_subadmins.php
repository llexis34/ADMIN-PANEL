<?php
// admin/api/admin_subadmins.php
// Manage admin_accounts - full admin only
declare(strict_types=1);
require __DIR__ . "/admin_db.php";

require_super_admin();
$action = $_GET["action"] ?? "list";

try {
    // ── LIST ────────────────────────────────────────────────
    if ($action === "list") {
        $rows = $pdo->query("SELECT id, username, email, role, is_active, created_at, last_login FROM admin_accounts ORDER BY created_at DESC")->fetchAll();
        echo json_encode(["ok" => true, "data" => $rows]);
        exit;
    }

    // ── CREATE ──────────────────────────────────────────────
    if ($action === "create") {
        require_post();
        $data     = json_input();
        $username = trim($data["username"] ?? "");
        $email    = strtolower(trim($data["email"] ?? ""));
        $password = (string)($data["password"] ?? "");
        $role     = $data["role"] ?? "sub_admin";

        if ($username === "" || $email === "" || strlen($password) < 8) {
            echo json_encode(["ok" => false, "error" => "Username, email, and password (8+ chars) required"]);
            exit;
        }

        if (!in_array($role, ["super_admin", "admin", "sub_admin"], true)) {
            $role = "sub_admin";
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO admin_accounts (username, email, password_hash, role) VALUES (?,?,?,?)")
            ->execute([$username, $email, $hash, $role]);

        echo json_encode(["ok" => true]);
        exit;
    }

    // ── EDIT ─────────────────────────────────────────────────
    if ($action === "edit") {
        require_post();
        $data     = json_input();
        $id       = (int)($data["id"] ?? 0);
        $username = trim($data["username"] ?? "");
        $email    = strtolower(trim($data["email"] ?? ""));
        $role     = $data["role"] ?? "sub_admin";

        if ($id <= 0 || $username === "" || $email === "") {
            echo json_encode(["ok" => false, "error" => "ID, username, and email are required"]);
            exit;
        }

        if (!in_array($role, ["super_admin", "admin", "sub_admin"], true)) {
            $role = "sub_admin";
        }

        $pdo->prepare("UPDATE admin_accounts SET username=?, email=?, role=? WHERE id=?")
            ->execute([$username, $email, $role, $id]);

        if (!empty($data["password"])) {
            if (strlen($data["password"]) < 8) {
                echo json_encode(["ok" => false, "error" => "Password must be at least 8 characters"]);
                exit;
            }

            $hash = password_hash($data["password"], PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE admin_accounts SET password_hash=? WHERE id=?")
                ->execute([$hash, $id]);
        }

        echo json_encode(["ok" => true]);
        exit;
    }

    // ── TOGGLE ACTIVE ────────────────────────────────────────
    if ($action === "toggle_active") {
        require_post();
        $data      = json_input();
        $id        = (int)($data["id"] ?? 0);
        $is_active = (int)(bool)($data["is_active"] ?? 0);

        // Prevent deactivating self
        if ($id === (int)$_SESSION["admin_id"]) {
            echo json_encode(["ok" => false, "error" => "Cannot deactivate your own account"]);
            exit;
        }

        $pdo->prepare("UPDATE admin_accounts SET is_active=? WHERE id=?")->execute([$is_active, $id]);
        echo json_encode(["ok" => true]);
        exit;
    }

    // ── DELETE ───────────────────────────────────────────────
    if ($action === "delete") {
        require_post();
        $data = json_input();
        $id   = (int)($data["id"] ?? 0);

        if ($id === (int)$_SESSION["admin_id"]) {
            echo json_encode(["ok" => false, "error" => "Cannot delete your own account"]);
            exit;
        }

        $pdo->prepare("DELETE FROM admin_accounts WHERE id=?")->execute([$id]);
        echo json_encode(["ok" => true]);
        exit;
    }

    echo json_encode(["ok" => false, "error" => "Unknown action"]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(["ok" => false, "error" => "Server error"]);
}
