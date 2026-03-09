<?php
// admin/api/admin_export_pdf.php
declare(strict_types=1);

require_once __DIR__ . "/../../dompdf/autoload.inc.php";

use Dompdf\Dompdf;
use Dompdf\Options;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION["admin_id"])) {
    http_response_code(401);
    die("Unauthorized");
}

$DB_HOST = "localhost";
$DB_NAME = "new_web2_main";
$DB_USER = "root";
$DB_PASS = "";

try {
    $pdo = new PDO(
        "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (Throwable $e) {
    die("DB error");
}

$id = (int)($_GET["id"] ?? 0);
if (!$id) {
    die("No ID provided");
}

$st = $pdo->prepare("
    SELECT ms.*, u.first_name, u.last_name, u.email, u.phone
    FROM membership_submissions ms
    LEFT JOIN users u ON ms.user_id = u.id
    WHERE ms.id = ?
");
$st->execute([$id]);
$row = $st->fetch();

if (!$row) {
    die("Not found");
}

$f = json_decode($row["form_json"] ?? "{}", true) ?: [];

$displayNo = 1;

try {
    $numSt = $pdo->prepare("
        SELECT COUNT(*) + 1 AS display_no
        FROM membership_submissions
        WHERE submitted_at < ?
           OR (submitted_at = ? AND id < ?)
    ");
    $numSt->execute([
        $row["submitted_at"],
        $row["submitted_at"],
        $row["id"]
    ]);
    $displayNo = (int)($numSt->fetch()["display_no"] ?? 1);
} catch (Throwable $e) {
    $displayNo = 1;
}

function v($val): string
{
    return htmlspecialchars((string)($val ?? ""), ENT_QUOTES, 'UTF-8');
}

function row2($l1, $v1, $l2, $v2): string
{
    return "<tr><td class='lbl'>{$l1}</td><td class='val'>" . v($v1) . "</td><td class='lbl'>{$l2}</td><td class='val'>" . v($v2) . "</td></tr>";
}

function row1($l1, $v1): string
{
    return "<tr><td class='lbl'>{$l1}</td><td class='val' colspan='3'>" . v($v1) . "</td></tr>";
}

$mode = $_GET["mode"] ?? "print";

$photo = "";
if (!empty($row["photo_path"])) {
    $abs = __DIR__ . "/../../" . ltrim($row["photo_path"], "/");
    if (file_exists($abs)) {
        $imgData = base64_encode(file_get_contents($abs));
        $mime = str_ends_with(strtolower($abs), ".png") ? "image/png" : "image/jpeg";
        $photo = "<img src='data:{$mime};base64,{$imgData}' style='width:100%;height:100%;object-fit:contain;border:0;display:block;' />";
    }
}

$fullName = trim(($f["first_name"] ?? $row["first_name"] ?? "") . " " . ($f["last_name"] ?? $row["last_name"] ?? ""));
$fileName = "membership_application_" . preg_replace('/[^A-Za-z0-9_-]/', '_', $fullName ?: ("ID_" . $row["id"])) . ".pdf";

$logoSmall = "";
$logoPath = __DIR__ . "/../../images/limcoma logoo.png";

if (file_exists($logoPath)) {
    $data = base64_encode(file_get_contents($logoPath));
    $logoSmall = "<img src='data:image/png;base64,$data' class='header-logo' alt='LIMCOMA Logo' />";
}

ob_start();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <title>Print Membership Form - <?= v($fullName) ?></title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            color: #000;
            background: #fff;
            padding: 10px 10PX 0 10PX;
        }

        .header {
            text-align: center;
            border-bottom: 2px solid #15355a;
            padding-bottom: 8px;
            margin-bottom: 10px;
        }

        .header h1 {
            font-size: 14px;
            color: #15355a;
            letter-spacing: 1px;
        }

        .header p {
            font-size: 10px;
            color: #444;
            margin-top: 2px;
        }

        .top-meta {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }

        .top-meta td {
            vertical-align: top;
            border: none;
            padding: 0;
        }

        .meta-left {
            width: 55%;
        }

        .meta-mid {
            width: 20%;
            text-align: center;
        }

        .meta-photo {
            width: 15%;
            text-align: right;
            vertical-align: top;
            padding-right: 0;
        }

        .photo-box {
            width: 90px;
            height: 110px;
            display: inline-block;
            overflow: hidden;
            border: 1px solid #ccc;
            text-align: center;
            vertical-align: top;
        }

        .photo-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: bold;
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #6ee7b7;
        }

        .status-badge.incomplete {
            background: #fef3c7;
            color: #92400e;
            border-color: #fcd34d;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
        }

        th.section {
            background: #15355a;
            color: #fff;
            padding: 5px 8px;
            font-size: 10px;
            text-align: left;
            letter-spacing: 0.5px;
        }

        td.lbl {
            background: #f0f4f8;
            font-weight: bold;
            width: 22%;
            padding: 4px 6px;
            border: 1px solid #ccc;
            color: #15355a;
        }

        td.val {
            width: 28%;
            padding: 4px 6px;
            border: 1px solid #ccc;
        }

        .benef-table th {
            background: #e8f0f8;
            color: #15355a;
            padding: 4px 6px;
            border: 1px solid #ccc;
            font-size: 10px;
        }

        .benef-table td {
            padding: 4px 6px;
            border: 1px solid #ccc;
        }

        .footer {
            margin-top: 4px;
            border-top: 1px solid #ccc;
            padding-top: 4px;
            font-size: 9px;
            color: #666;
            text-align: center;
        }

        .header-top {
            text-align: center;
            margin-top: 2px;
        }

        .header-logo {
            height: 14px;
            vertical-align: middle;
            margin-right: -2px;
        }

        .coop-name {
            font-size: 10px;
            color: #444;
            vertical-align: middle;
        }

        .form-title {
            font-size: 14px;
            color: #15355a;
            letter-spacing: 1px;
            font-weight: bold;
            margin-top: 0;
            line-height: 1.2;
        }

        th.section,
        td.lbl,
        .benef-table th {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        table {
            border-collapse: collapse;
        }

        th.section {
            background: #15355a !important;
            color: #ffffff !important;
        }

        td.lbl {
            background: #f0f4f8 !important;
            color: #15355a !important;
        }

        .benef-table th {
            background: #e8f0f8 !important;
            color: #15355a !important;
        }

        @media print {
            body {
                padding: 0;
            }

            .no-print {
                display: none !important;
            }

            .status-badge {
                display: none !important;
            }

            @page {
                margin: 12mm;
            }
        }
    </style>
</head>

<body>

    <?php if ($mode !== "pdf"): ?>
        <div class="no-print" style="margin-bottom:12px;">
            <button onclick="window.print()"
                style="padding:8px 18px;background:#15355a;color:#fff;border:none;border-radius:4px;font-size:13px;cursor:pointer;font-weight:bold;">Print Form</button>
            <button onclick="window.close()"
                style="padding:8px 14px;background:#6b7280;color:#fff;border:none;border-radius:4px;font-size:13px;cursor:pointer;margin-left:8px;">Close</button>
        </div>
    <?php endif; ?>

    <div class="header">
        <div class="form-title">MEMBERSHIP APPLICATION FORM</div>

        <div class="header-top"><?= $logoSmall ?><span class="coop-name">LIMCOMA MULTI-PURPOSE COOPERATIVE</span></div>
    </div>

    <table class="top-meta">
        <tr>
            <td class="meta-left">
                <strong>Application Type:</strong> <?= v($f["application_type"] ?? "") ?><br />
                <strong>Application No:</strong> <?= (int)$displayNo ?><br />
                <strong>Submitted:</strong> <?= v(substr($row["submitted_at"] ?? "", 0, 10)) ?>
            </td>

            <td class="meta-mid">
                <?php
                $s = $row["status"] ?? "Incomplete";
                $cls = $s === "Approved" ? "" : "incomplete";
                $label = $s === "Approved" ? "Approved / Active" : "Incomplete";
                ?>
                <?php if ($mode !== "pdf" && $mode !== "print"): ?>
                    <span class="status-badge <?= $cls ?>"><?= v($label) ?></span>
                <?php endif; ?>
            </td>

            <td class="meta-photo">
                <?php if ($photo): ?>
                    <div class="photo-box"><?= $photo ?></div>
                <?php else: ?>
                    <div class="photo-box" style="line-height:110px;color:#aaa;font-size:9px;">No Photo</div>
                <?php endif; ?>
            </td>
        </tr>
    </table>

    <table>
        <tr>
            <th class="section" colspan="4">Personal Information</th>
        </tr>
        <?= row2("Last Name", $f["last_name"] ?? $row["last_name"], "First Name", $f["first_name"] ?? $row["first_name"]) ?>
        <?= row2("Middle Name", $f["middle_name"] ?? "", "Gender", $f["gender"] ?? "") ?>
        <?= row1("Home Address", $f["home_address"] ?? "") ?>
        <?= row2("Birthdate", $f["birthdate"] ?? "", "Age", $f["age"] ?? "") ?>
        <?= row2("Religion", $f["religion"] ?? "", "Civil Status", $f["civil_status"] ?? "") ?>
        <?= row2("Dependents", $f["dependents"] ?? "", "Educational Attainment", $f["education"] ?? "") ?>
        <?= row2("Livelihood", $f["livelihood"] ?? "", "Gross Monthly Income", $f["gross_monthly_income"] ?? "") ?>
    </table>

    <table>
        <tr>
            <th class="section" colspan="4">Contact & Work Information</th>
        </tr>
        <?= row2("Mobile No.", $f["mobile"] ?? $row["phone"], "Telephone", $f["telephone"] ?? "") ?>
        <?= row2("Email Address", $f["email"] ?? $row["email"], "TIN", $f["tin"] ?? "") ?>
        <?= row1("Work Address", $f["work_address"] ?? "") ?>
        <?= row2("OFW Country", $f["ofw_country"] ?? "", "OFW Work Abroad", $f["ofw_work"] ?? "") ?>
        <?= row2("Years Working Abroad", $f["ofw_years"] ?? "", "", "") ?>
    </table>

    <table>
        <tr>
            <th class="section" colspan="4">Family Information</th>
        </tr>
        <?= row2("Spouse Name", $f["spouse_name"] ?? "", "Spouse Occupation", $f["spouse_occupation"] ?? "") ?>
        <?= row2("Spouse Company", $f["spouse_company"] ?? "", "", "") ?>
        <?= row2("Father's Name", $f["father_name"] ?? "", "Father's Occupation", $f["father_occupation"] ?? "") ?>
        <?= row2("Mother's Name", $f["mother_name"] ?? "", "Mother's Occupation", $f["mother_occupation"] ?? "") ?>
    </table>

    <table>
        <tr>
            <th class="section" colspan="4">Beneficiaries</th>
        </tr>
    </table>

    <table class="benef-table" style="margin-bottom:8px;">
        <tr>
            <th>#</th>
            <th>Name</th>
            <th>Relation</th>
            <th>% Allocation</th>
            <th>Contact No.</th>
        </tr>
        <?php for ($i = 1; $i <= 4; $i++): ?>
            <tr>
                <td><?= $i ?></td>
                <td><?= v($f["benef_name_{$i}"] ?? "") ?></td>
                <td><?= v($f["benef_relation_{$i}"] ?? "") ?></td>
                <td><?= v($f["benef_alloc_{$i}"] ?? "") ?></td>
                <td><?= v($f["benef_contact_{$i}"] ?? "") ?></td>
            </tr>
        <?php endfor; ?>
    </table>

    <table>
        <tr>
            <th class="section" colspan="4">Products / Services</th>
        </tr>
        <?= row2("Avails Feeds", ($f["avail_feeds"] ?? "") ? "Yes" : "No", "Avails Loans", ($f["avail_loans"] ?? "") ? "Yes" : "No") ?>
        <?= row2("Avails Savings", ($f["avail_savings"] ?? "") ? "Yes" : "No", "Avails Time Deposit", ($f["avail_time_deposit"] ?? "") ? "Yes" : "No") ?>
        <?= row2("Currently Using Feeds", $f["using_feeds_now"] ?? "", "Feeds Brand", $f["feeds_brand"] ?? "") ?>
        <?= row2("Baboy - Sow", $f["baboy_sow"] ?? "", "Baboy - Piglet", $f["baboy_piglet"] ?? "") ?>
        <?= row2("Baboy - Boar", $f["baboy_boar"] ?? "", "Baboy - Grower", $f["baboy_grower"] ?? "") ?>
        <?= row2("Manok - Patilugin", $f["manok_patilugin"] ?? "", "Manok - Broiler", $f["manok_broiler"] ?? "") ?>
        <?= row1("Iba pang Alaga", $f["iba_pang_alaga"] ?? "") ?>
    </table>

    <table>
        <tr>
            <th class="section" colspan="4">Declaration</th>
        </tr>
        <?= row2("Signature (Typed Name)", $f["signature"] ?? "", "Date", $f["signature_date"] ?? "") ?>
    </table>

    <?php if (!empty($row["admin_notes"])): ?>
        <table>
            <tr>
                <th class="section" colspan="4">Admin Notes</th>
            </tr>
            <?= row1("Notes", $row["admin_notes"]) ?>
        </table>
    <?php endif; ?>

    <div class="footer">
        © LIMCOMA MULTI-PURPOSE COOPERATIVE
    </div>

</body>

</html>
<?php
$html = ob_get_clean();

if ($mode === "pdf") {
    $options = new Options();
    $options->set('isRemoteEnabled', true);

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('letter', 'portrait');
    $dompdf->render();
    $dompdf->stream($fileName, ['Attachment' => true]);
    exit;
}

header("Content-Type: text/html; charset=utf-8");
echo $html;
