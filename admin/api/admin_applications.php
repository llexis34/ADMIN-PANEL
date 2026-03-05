<?php
// admin/api/admin_applications.php
// Handles: list, view, update status, edit, delete applications
declare(strict_types=1);
require __DIR__ . "/admin_db.php";

$admin = require_admin_login();
$action = $_GET["action"] ?? "list";

try {
    // ── LIST all applications ───────────────────────────────
    if ($action === "list") {
        $status_filter = $_GET["status"] ?? "";
        $search = trim($_GET["search"] ?? "");

        $where = [];
        $params = [];

        if ($status_filter !== "" && in_array($status_filter, ["Pending", "Approved", "Rejected"], true)) {
            $where[] = "ms.status = ?";
            $params[] = $status_filter;
        }

        if ($search !== "") {
            $like = "%" . $search . "%";
            $where[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR CAST(ms.id AS CHAR) LIKE ? OR ms.form_json LIKE ?)";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $whereSQL = $where ? "WHERE " . implode(" AND ", $where) : "";

        $sql = "
        SELECT ms.id, ms.user_id, ms.status, ms.submitted_at, ms.photo_path,
               u.first_name, u.last_name, u.email, u.phone
        FROM membership_submissions ms
        LEFT JOIN users u ON ms.user_id = u.id
        {$whereSQL}
        ORDER BY ms.submitted_at DESC
    ";

        $st = $pdo->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll();

        echo json_encode(["ok" => true, "data" => $rows]);
        exit;
    }

    // ── VIEW single application ─────────────────────────────
    if ($action === "view") {
        $id = (int) ($_GET["id"] ?? 0);
        $st = $pdo->prepare("
            SELECT ms.*, u.first_name, u.last_name, u.email, u.phone, u.is_active
            FROM membership_submissions ms
            LEFT JOIN users u ON ms.user_id = u.id
            WHERE ms.id = ?
        ");
        $st->execute([$id]);
        $row = $st->fetch();

        if (!$row) {
            echo json_encode(["ok" => false, "error" => "Not found"]);
            exit;
        }

        // Decode form_json
        $row["form_data"] = json_decode($row["form_json"] ?? "{}", true) ?: [];

        echo json_encode(["ok" => true, "data" => $row]);
        exit;
    }

    // ── UPDATE STATUS ───────────────────────────────────────
    if ($action === "update_status") {
        require_post();
        $data = json_input();
        $id = (int) ($data["id"] ?? 0);
        $status = $data["status"] ?? "";
        $notes = trim($data["admin_notes"] ?? "");

        if (!in_array($status, ["Approved", "Incomplete"], true)) {
            echo json_encode(["ok" => false, "error" => "Invalid status"]);
            exit;
        }

        $st = $pdo->prepare("
            UPDATE membership_submissions
            SET status = ?, admin_notes = ?, reviewed_by = ?, reviewed_at = NOW()
            WHERE id = ?
        ");
        $st->execute([$status, $notes ?: null, $admin["id"], $id]);

        echo json_encode(["ok" => true]);
        exit;
    }

    // ── DELETE application ──────────────────────────────────
    if ($action === "delete") {
        require_post();
        // Only full admin can delete
        if ($admin["role"] !== "admin") {
            echo json_encode(["ok" => false, "error" => "Insufficient permissions"]);
            exit;
        }
        $data = json_input();
        $id = (int) ($data["id"] ?? 0);

        $pdo->prepare("DELETE FROM membership_submissions WHERE id = ?")->execute([$id]);
        echo json_encode(["ok" => true]);
        exit;
    }

    // ── EDIT / UPDATE form data fields ──────────────────────
    if ($action === "edit") {
        require_post();
        $data = json_input();
        $id = (int) ($data["id"] ?? 0);

        // Fetch existing
        $st = $pdo->prepare("SELECT form_json FROM membership_submissions WHERE id = ?");
        $st->execute([$id]);
        $row = $st->fetch();
        if (!$row) {
            echo json_encode(["ok" => false, "error" => "Not found"]);
            exit;
        }

        $form = json_decode($row["form_json"] ?? "{}", true) ?: [];

        // Merge editable fields
        $editable = [
            "application_type",
            "last_name",
            "first_name",
            "middle_name",
            "gender",
            "birthdate",
            "age",
            "religion",
            "dependents",
            "civil_status",
            "education",
            "livelihood",
            "gross_monthly_income",
            "home_address",
            "mobile",
            "telephone",
            "email",
            "tin",
            "work_address",
            "ofw_country",
            "ofw_work",
            "ofw_years",
            "spouse_name",
            "spouse_occupation",
            "spouse_company",
            "father_name",
            "father_occupation",
            "mother_name",
            "mother_occupation",
            "benef_name_1",
            "benef_relation_1",
            "benef_alloc_1",
            "benef_contact_1",
            "benef_name_2",
            "benef_relation_2",
            "benef_alloc_2",
            "benef_contact_2",
            "benef_name_3",
            "benef_relation_3",
            "benef_alloc_3",
            "benef_contact_3",
            "benef_name_4",
            "benef_relation_4",
            "benef_alloc_4",
            "benef_contact_4",
            "avail_feeds",
            "using_feeds_now",
            "feeds_brand",
            "avail_loans",
            "avail_savings",
            "avail_time_deposit",
            "baboy_sow",
            "baboy_piglet",
            "baboy_boar",
            "baboy_grower",
            "manok_patilugin",
            "manok_broiler",
            "iba_pang_alaga",
            "signature",
            "signature_date"
        ];

        foreach ($editable as $f) {
            if (isset($data[$f])) {
                $form[$f] = $data[$f];
            }
        }

        $pdo->prepare("UPDATE membership_submissions SET form_json = ? WHERE id = ?")
            ->execute([json_encode($form), $id]);

        echo json_encode(["ok" => true]);
        exit;
    }

    echo json_encode(["ok" => false, "error" => "Unknown action"]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(["ok" => false, "error" => "Server error: " . $e->getMessage()]);
}
