<?php
// admin/api/admin_export.php
// Export membership applications as CSV
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION["admin_id"])) {
    http_response_code(401);
    die("Unauthorized");
}

$DB_HOST = "localhost";
$DB_NAME = "new_web2_main";
$DB_USER = "root";
$DB_PASS = "";

try {
    $pdo = new PDO("mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4", $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Throwable $e) {
    die("DB error");
}

$status_filter = $_GET["status"] ?? "";
$params = [];
$where  = "";

if (in_array($status_filter, ["Incomplete", "Approved"], true)) {
    $where    = "WHERE ms.status = ?";
    $params[] = $status_filter;
}

$rows = $pdo->prepare("
    SELECT ms.id, u.first_name, u.last_name, u.email, u.phone,
           ms.status, ms.submitted_at, ms.form_json
    FROM membership_submissions ms
    LEFT JOIN users u ON ms.user_id = u.id
    {$where}
    ORDER BY ms.submitted_at DESC
");
$rows->execute($params);
$data = $rows->fetchAll();

// Output CSV
header("Content-Type: text/csv; charset=utf-8");
header("Content-Disposition: attachment; filename=limcoma_applications_" . date("Ymd") . ".csv");

$out = fopen("php://output", "w");

// BOM for Excel UTF-8
fwrite($out, "\xEF\xBB\xBF");

// Header row
fputcsv($out, [
    "ID",
    "First Name",
    "Last Name",
    "Email",
    "Phone",
    "Status",
    "Submitted At",
    "Application Type",
    "Home Address",
    "Gender",
    "Birthdate",
    "Age",
    "Civil Status",
    "Education",
    "Livelihood",
    "Monthly Income",
    "Mobile",
    "TIN",
    "Work Address"
]);

foreach ($data as $row) {
    $f = json_decode($row["form_json"] ?? "{}", true) ?: [];
    fputcsv($out, [
        $row["id"],
        $row["first_name"] ?? ($f["first_name"] ?? ""),
        $row["last_name"]  ?? ($f["last_name"]  ?? ""),
        $row["email"]      ?? ($f["email"]       ?? ""),
        $row["phone"]      ?? ($f["mobile"]      ?? ""),
        $row["status"],
        $row["submitted_at"],
        $f["application_type"]    ?? "",
        $f["home_address"]        ?? "",
        $f["gender"]              ?? "",
        $f["birthdate"]           ?? "",
        $f["age"]                 ?? "",
        $f["civil_status"]        ?? "",
        $f["education"]           ?? "",
        $f["livelihood"]          ?? "",
        $f["gross_monthly_income"] ?? "",
        $f["mobile"]              ?? "",
        $f["tin"]                 ?? "",
        $f["work_address"]        ?? "",
    ]);
}

fclose($out);
