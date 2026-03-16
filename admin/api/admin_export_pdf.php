<?php
// admin/api/admin_export_pdf.php
declare(strict_types=1);

require_once __DIR__ . "/../../vendor/autoload.php";

use Mpdf\Mpdf;
use Mpdf\Output\Destination;

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
$branch = trim((string)($row["branch"] ?? ""));

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
        $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
        $mime = $ext === "png" ? "image/png" : (($ext === "webp") ? "image/webp" : "image/jpeg");
        $photo = "<img src='data:{$mime};base64,{$imgData}' style='width:95px;height:95px;display:block;margin:0 auto;border:0;' />";
    }
}

$sigImg = "";
$sigPath = $f["signature_file"] ?? $row["signature_path"] ?? "";
if ($sigPath) {
    $absS = __DIR__ . "/../../" . ltrim($sigPath, "/");
    if (file_exists($absS)) {
        $sData = base64_encode(file_get_contents($absS));
        $sExt = strtolower(pathinfo($absS, PATHINFO_EXTENSION));
        $sMime = $sExt === "png" ? "image/png" : (($sExt === "webp") ? "image/webp" : "image/jpeg");
        $sigImg = "<img src='data:{$sMime};base64,{$sData}' style='max-width:140px;max-height:42px;display:inline-block;' />";
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
    <link rel="icon" type="image/png" href="../../images/limcoma logoo.png">
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
            padding: 0;
            margin: 0;
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
            table-layout: fixed;
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
            text-align: center;
            vertical-align: top;
            padding-right: 0;
        }

        .photo-box {
            width: 100px;
            height: 100px;
            display: inline-block;
            border: 1px solid #999;
            text-align: center;
            vertical-align: top;
            overflow: hidden;
            line-height: 100px;
            background: #fff;
        }

        .photo-box img {
            width: 95px;
            height: 95px;
            display: inline-block;
            vertical-align: middle;
            margin: 0 auto;
        }

        table {
            page-break-inside: auto;
            width: 100%;
            border-collapse: collapse;
            border-spacing: 0;
            margin-bottom: 4px;
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

        .signature-img {
            max-width: 160px;
            max-height: 52px;
            display: inline-block;
            vertical-align: bottom;
        }

        th.section,
        td.lbl,
        .benef-table th {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
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

        .declaration-table {
            page-break-inside: avoid !important;
            break-inside: avoid !important;
        }

        @media print {
            body {
                padding: 0 !important;
                font-size: 10px;
            }

            .no-print {
                display: none !important;
            }

            .status-badge {
                display: none !important;
            }

            .header {
                margin-bottom: 6px;
                padding-bottom: 6px;
            }

            .top-meta {
                margin-bottom: 6px;
            }

            table {
                margin-bottom: 3px;
            }

            th.section {
                padding: 4px 6px;
                font-size: 9px;
            }

            td.lbl,
            td.val,
            .benef-table th,
            .benef-table td {
                padding: 3px 5px;
                font-size: 10px;
            }

            .photo-box {
                width: 100px;
                height: 100px;
            }

            .declaration-table {
                margin-bottom: 0 !important;
                page-break-inside: avoid !important;
                break-inside: avoid !important;
            }
        }

        /* PDF mode only */
        body.pdf-mode {
            padding: 0 !important;
            margin: 0 !important;
            font-size: 10pt;
        }

        body.pdf-mode .header {
            margin-bottom: 4mm;
            padding-bottom: 3mm;
            border-bottom: 2px solid #15355a;
            width: 100%;
        }

        body.pdf-mode .top-meta {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
            margin-bottom: 4mm;
        }

        body.pdf-mode .meta-left {
            width: 58%;
        }

        body.pdf-mode .meta-mid {
            width: 20%;
        }

        body.pdf-mode .meta-photo {
            width: 22%;
            padding-right: 0;
            text-align: center;
        }

        body.pdf-mode .photo-box {
            width: 25mm;
            height: 25mm;
            display: block;
            border: 1px solid #999;
            overflow: hidden;
            text-align: center;
            line-height: 25mm;
            background: #fff;
            margin-left: auto;
        }

        body.pdf-mode .photo-box img {
            width: 25mm;
            height: 25mm;
            display: block;
        }

        body.pdf-mode table {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
            border-spacing: 0;
            margin-bottom: 2mm;
        }

        body.pdf-mode th.section {
            padding: 2.5mm 3mm;
            font-size: 9pt;
            background: #15355a !important;
            color: #ffffff !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        body.pdf-mode td.lbl {
            width: 22%;
            padding: 1.5mm 2mm;
            font-size: 9pt;
            font-weight: bold;
            background: #f0f4f8 !important;
            color: #15355a !important;
            border: 0.3mm solid #ccc;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        body.pdf-mode td.val {
            width: 28%;
            padding: 1.5mm 2mm;
            font-size: 9pt;
            border: 0.3mm solid #ccc;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        body.pdf-mode .benef-table {
            width: 100%;
            table-layout: fixed;
        }

        body.pdf-mode .benef-table th {
            padding: 1.5mm 2mm;
            font-size: 9pt;
            border: 0.3mm solid #ccc;
            background: #e8f0f8 !important;
            color: #15355a !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        body.pdf-mode .benef-table th:nth-child(1),
        body.pdf-mode .benef-table td:nth-child(1) {
            width: 8%;
        }

        body.pdf-mode .benef-table th:nth-child(2),
        body.pdf-mode .benef-table td:nth-child(2) {
            width: 32%;
        }

        body.pdf-mode .benef-table th:nth-child(3),
        body.pdf-mode .benef-table td:nth-child(3) {
            width: 22%;
        }

        body.pdf-mode .benef-table th:nth-child(4),
        body.pdf-mode .benef-table td:nth-child(4) {
            width: 14%;
        }

        body.pdf-mode .benef-table th:nth-child(5),
        body.pdf-mode .benef-table td:nth-child(5) {
            width: 24%;
        }

        body.pdf-mode .benef-table td {
            padding: 1.5mm 2mm;
            font-size: 9pt;
            border: 0.3mm solid #ccc;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        body.pdf-mode .declaration-table {
            width: 100%;
            table-layout: fixed;
            margin-bottom: 0 !important;
            page-break-inside: avoid !important;
            break-inside: avoid !important;
        }

        body.pdf-mode .signature-img {
            max-width: 38mm;
            max-height: 14mm;
            display: block;
        }

        @page {
            margin: 10mm;
        }
    </style>
</head>

<body class="<?= $mode === 'pdf' ? 'pdf-mode' : 'print-mode' ?>">

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
                <strong>LIMCOMA Branch:</strong> <?= $branch !== "" ? v($branch) : "" ?><br />
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
                    <div class="photo-box" style="color:#aaa;font-size:9px;text-align:center;line-height:100px;">No Photo</div>
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
        <?= row2("Years Working Abroad", $f["ofw_years"] ?? "", "Facebook Profile", $f["facebook_link"] ?? "") ?>
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
            <th class="section" colspan="4">Agreements</th>
        </tr>
        <?= row2(
            "Subscription Agreement",
            ($f["subscription_agreement_accepted"] ?? "0") === "1" ? "Accepted" : "Not Accepted",
            "Kasunduan / Capital Agreement",
            ($f["kasunduan_accepted"] ?? "0") === "1" ? "Accepted" : "Not Accepted"
        ) ?>
    </table>

    <table class="declaration-table" style="page-break-inside:avoid;">
        <tr>
            <th class="section" colspan="4">Declaration / Signature</th>
        </tr>
        <tr>
            <td class="lbl">Signature Image</td>
            <td class="val" style="padding:6px;">
                <?php if ($sigImg): ?>
                    <?= $sigImg ?>
                <?php else: ?>
                    <?= v($f["signature"] ?? "") ?>
                <?php endif; ?>
            </td>
            <td class="lbl">Date Signed</td>
            <td class="val"><?= v($f["signature_date"] ?? "") ?></td>
        </tr>
    </table>

    <?php
    $checklistFiles = $f["checklist_files"] ?? [];
    $clLabels = [
        "a1" => "Associate: Application Form",
        "a2" => "Associate: 2x2 Photo",
        "a3" => "Associate: Gov't ID",
        "a4" => "Associate: Birth/Marriage Cert",
        "r1" => "Regular: Application Form",
        "r2" => "Regular: 2x2 Photo",
        "r3" => "Regular: Gov't ID",
        "r4" => "Regular: Share Capital Proof",
        "r5" => "Regular: ID Fee Proof"
    ];
    if (!empty($checklistFiles) && is_array($checklistFiles)):
    ?>
        <table>
            <tr>
                <th class="section" colspan="4">Checklist Uploaded Files</th>
            </tr>
            <?php foreach ($checklistFiles as $ck => $cpath):
                $absC = __DIR__ . "/../../" . ltrim($cpath, "/");
                $ext = strtolower(pathinfo($absC, PATHINFO_EXTENSION));
                $clLabel = $clLabels[$ck] ?? $ck;
            ?>
                <tr>
                    <td class="lbl"><?= v($clLabel) ?></td>
                    <td class="val" colspan="3">
                        <?php if (in_array($ext, ["jpg", "jpeg", "png", "webp", "gif"]) && file_exists($absC)): ?>
                            <?php
                            $imgD = base64_encode(file_get_contents($absC));
                            $imgM = $ext === "png" ? "image/png" : ($ext === "webp" ? "image/webp" : "image/jpeg");
                            ?>
                            <img src="data:<?= $imgM ?>;base64,<?= $imgD ?>" style="max-width:150px;max-height:90px;" />
                        <?php elseif ($ext === "pdf"): ?>
                            [PDF File: <?= v(basename($cpath)) ?>]
                        <?php else: ?>
                            <?= v(basename($cpath)) ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

    <?php if ($mode !== "print" && $mode !== "pdf" && !empty($row["admin_notes"])): ?>
        <table>
            <tr>
                <th class="section" colspan="4">Admin Notes</th>
            </tr>
            <?= row1("Notes", $row["admin_notes"]) ?>
        </table>
    <?php endif; ?>

</body>

</html>
<?php
$html = ob_get_clean();

if ($mode === "pdf") {
    $tempDir = __DIR__ . "/../../tmp/mpdf";

    if (!is_dir($tempDir)) {
        mkdir($tempDir, 0777, true);
    }

    $mpdf = new Mpdf([
        'mode' => 'utf-8',
        'tempDir' => $tempDir,
        'margin_left' => 10,
        'margin_right' => 10,
        'margin_top' => 10,
        'margin_bottom' => 10,
        'margin_header' => 0,
        'margin_footer' => 0,
        'autoScriptToLang' => false,
        'autoLangToFont' => false,
        'setAutoTopMargin' => false,
        'setAutoBottomMargin' => false,
        'shrink_tables_to_fit' => 0,
        'ignore_invalid_utf8' => true,
        'useSubstitutions' => false,
    ]);

    $mpdf->showImageErrors = true;
    $mpdf->SetDisplayMode('fullpage', 'single');
    $mpdf->WriteHTML($html);

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . addslashes($fileName) . '"');
    echo $mpdf->Output($fileName, Destination::STRING_RETURN);
    exit;
}

header("Content-Type: text/html; charset=utf-8");
echo $html;
