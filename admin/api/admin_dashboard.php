<?php
// admin/api/admin_dashboard.php
declare(strict_types=1);
require __DIR__ . "/admin_db.php";

require_admin_login();

try {
    $stats = [];

    // Total registered users
    $stats["total_users"] = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

    // Total membership applications
    $stats["total_applications"] = (int) $pdo->query("SELECT COUNT(*) FROM membership_submissions")->fetchColumn();

    $stats["incomplete"] = (int) $pdo->query("SELECT COUNT(*) FROM membership_submissions WHERE status = 'Incomplete'")->fetchColumn();
    $stats["approved"] = (int) $pdo->query("SELECT COUNT(*) FROM membership_submissions WHERE status = 'Approved'")->fetchColumn();

    // Recent 5 applications
    $recent = $pdo->query("
        SELECT ms.id, ms.status, ms.submitted_at,
               u.first_name, u.last_name, u.email
        FROM membership_submissions ms
        LEFT JOIN users u ON ms.user_id = u.id
        ORDER BY ms.submitted_at DESC
        LIMIT 5
    ")->fetchAll();

    echo json_encode(["ok" => true, "stats" => $stats, "recent" => $recent]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(["ok" => false, "error" => "Server error"]);
}
