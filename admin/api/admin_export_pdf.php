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

// Load signature image
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
            border-spacing: 0;
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

        /* =========================================================
           PDF-MODE ONLY — mPDF fixed-layout overrides
           These rules apply ONLY when body.pdf-mode is set.
           They do NOT affect the browser print layout.
        ========================================================== */

        /* ── Root canvas: lock to Letter page content width ───── */
        body.pdf-mode {
            padding: 0 !important;
            margin: 0 !important;
            font-size: 10pt;
            width: 190mm;          /* Letter width (215.9mm) minus 10mm L+R margins */
        }

        /* ── Header ─────────────────────────────────────────────── */
        body.pdf-mode .header {
            margin-bottom: 4mm;
            padding-bottom: 3mm;
            border-bottom: 2px solid #15355a;
            width: 190mm;
        }

        /* ── Top meta table (App info + photo) ──────────────────── */
        body.pdf-mode .top-meta {
            width: 190mm;
            table-layout: fixed;
            border-collapse: collapse;
            margin-bottom: 4mm;
        }

        body.pdf-mode .meta-left  { width: 112mm; }   /* absolute mm */
        body.pdf-mode .meta-mid   { width: 40mm; }
        body.pdf-mode .meta-photo { width: 27mm; padding-right: 0; text-align: center; }

        /* ── Photo box: absolute size, never reflows ─────────────── */
        body.pdf-mode .photo-box {
            width: 25mm;
            height: 25mm;
            display: block;
            border: 1px solid #999;
            overflow: hidden;
            text-align: center;
            line-height: 25mm;
            background: #fff;
        }

        body.pdf-mode .photo-box img {
            width: 25mm;
            height: 25mm;
            display: block;
        }

        /* ── All data tables: fixed layout, locked width ─────────── */
        body.pdf-mode table {
            width: 190mm;
            table-layout: fixed;
            border-collapse: collapse;
            border-spacing: 0;
            margin-bottom: 2mm;
        }

        /* ── Section header rows ─────────────────────────────────── */
        body.pdf-mode th.section {
            padding: 2.5mm 3mm;
            font-size: 9pt;
            background: #15355a !important;
            color: #ffffff !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        /* ── Label / value cells: ABSOLUTE mm widths — no % ─────── */
        body.pdf-mode td.lbl {
            width: 43mm;           /* fixed mm — never recalculates */
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
            width: 54mm;           /* fixed mm — never recalculates */
            padding: 1.5mm 2mm;
            font-size: 9pt;
            border: 0.3mm solid #ccc;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        /* ── Beneficiary table ───────────────────────────────────── */
        body.pdf-mode .benef-table {
            width: 190mm;
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

        /* Column widths: #  Name  Relation  % Alloc  Contact */
        body.pdf-mode .benef-table th:nth-child(1),
        body.pdf-mode .benef-table td:nth-child(1) { width: 8mm; }
        body.pdf-mode .benef-table th:nth-child(2),
        body.pdf-mode .benef-table td:nth-child(2) { width: 62mm; }
        body.pdf-mode .benef-table th:nth-child(3),
        body.pdf-mode .benef-table td:nth-child(3) { width: 42mm; }
        body.pdf-mode .benef-table th:nth-child(4),
        body.pdf-mode .benef-table td:nth-child(4) { width: 22mm; }
        body.pdf-mode .benef-table th:nth-child(5),
        body.pdf-mode .benef-table td:nth-child(5) { width: 56mm; }

        body.pdf-mode .benef-table td {
            padding: 1.5mm 2mm;
            font-size: 9pt;
            border: 0.3mm solid #ccc;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        /* ── Declaration / signature table ──────────────────────── */
        body.pdf-mode .declaration-table {
            width: 190mm;
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

        .declaration-table {
            page-break-inside: avoid !important;
            break-inside: avoid !important;
        }

        /* =========================================================
           AGREEMENT PAGES (Subscription + Kasunduan)
           Fixed layout for PDF, while keeping print view stable
        ========================================================== */

        .agreement-page {
            page-break-before: always;
            padding: 10px 34px 0 34px;
            color: #222;
        }

        .agreement-sheet {
            width: 100%;
            max-width: 700px;
            margin: 0 auto;
            page-break-inside: avoid !important;
            break-inside: avoid !important;
        }

        .agreement-topboxes {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 26px;
            table-layout: fixed;
        }

        .agreement-topboxes td {
            border: none;
            padding: 0;
            vertical-align: top;
        }

        .agreement-box-left {
            text-align: left;
            width: 50%;
        }

        .agreement-box-right {
            text-align: right;
            width: 50%;
        }

        .agreement-box {
            display: inline-block;
            border: 1px solid #777;
            padding: 6px 14px;
            font-size: 11px;
            font-weight: bold;
            font-style: italic;
            color: #444;
            min-width: 230px;
            text-align: center;
            letter-spacing: .2px;
            background: #fff;
        }

        .agreement-box.code {
            min-width: 170px;
        }

        .agreement-title {
            text-align: center;
            font-size: 18px;
            font-weight: 700;
            letter-spacing: .4px;
            color: #333;
            margin-bottom: 18px;
        }

        .agreement-subtitle {
            text-align: center;
            font-size: 10px;
            color: #444;
            margin-bottom: 22px;
        }

        .agreement-body {
            font-size: 11px;
            line-height: 1.75;
            color: #333;
            text-align: justify;
        }

        .agreement-body p {
            margin: 0 0 18px 0;
            text-indent: 38px;
        }

        .agreement-intro {
            text-indent: 28px;
        }

        .agreement-pledge {
            margin: 18px 0 10px 58px !important;
            text-align: left;
        }

        .agreement-ol {
            margin: 8px 0 22px 58px;
            padding-left: 20px;
            line-height: 1.9;
        }

        .agreement-ol li {
            margin-bottom: 14px;
            padding-left: 8px;
        }

        .agreement-ul {
            margin-top: 6px;
            margin-left: 18px;
        }

        .agreement-ul li {
            margin-bottom: 2px;
        }

        .agreement-footer-note {
            margin-top: 20px;
            margin-bottom: 30px;
            text-align: justify;
        }

        .agreement-sign {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            page-break-inside: avoid !important;
            break-inside: avoid !important;
            table-layout: fixed;
        }

        .agreement-sign td {
            border: none;
            vertical-align: top;
            padding: 4px 6px;
        }

        .agreement-sign-left {
            width: 52%;
            text-align: left;
            padding-top: 0;
            white-space: nowrap;
        }

        .agreement-sign-right {
            width: 48%;
            text-align: center;
            padding-top: 0;
        }

        .agreement-line {
            display: inline-block;
            width: 90px;
            border-bottom: 1px solid #000;
            height: 10px;
            vertical-align: bottom;
        }

        .agreement-line.place {
            width: 120px;
        }

        .agreement-role-line {
            width: 78%;
            margin: 35px 0 0 0;
            border-top: 1px solid #000;
            padding-top: 4px;
            text-align: center;
        }

        .agreement-subscriber-wrap {
            width: 78%;
            margin: 0 0 0 auto;
            text-align: center;
        }

        .agreement-subscriber-line {
            border-top: 1px solid #000;
            padding-top: 4px;
        }

        .agreement-subscriber-sub {
            font-size: 9px;
            color: #555;
            line-height: 1.2;
        }

        .agreement-signature-box {
            height: 28px;
            text-align: center;
            margin-bottom: 2px;
        }

        .agreement-signature-box img {
            max-width: 140px;
            max-height: 42px;
            display: inline-block;
        }

        .agreement-declaration-title {
            margin-top: 16px;
            margin-bottom: 8px;
            font-weight: bold;
            font-size: 10px;
            text-align: center;
            line-height: 1.3;
        }

        /* ── Agreement pages: locked mm canvas ───────────────────── */
        body.pdf-mode .agreement-page {
            page-break-before: always;
            padding: 0;
            margin: 0;
            color: #222;
            width: 190mm;
        }

        body.pdf-mode .agreement-sheet {
            width: 190mm;
            max-width: 190mm;
            margin: 0;
            padding: 0;
        }

        /* Top classification boxes */
        body.pdf-mode .agreement-topboxes {
            width: 190mm;
            table-layout: fixed;
            border-collapse: collapse;
            margin: 0 0 5mm 0;
        }

        body.pdf-mode .agreement-topboxes td {
            border: none;
            padding: 0;
            vertical-align: top;
        }

        body.pdf-mode .agreement-box-left {
            width: 118mm;
            text-align: left;
        }

        body.pdf-mode .agreement-box-right {
            width: 71mm;
            text-align: right;
        }

        body.pdf-mode .agreement-box {
            display: inline-block;
            border: 0.3mm solid #777;
            padding: 1.5mm 4mm;
            font-size: 10pt;
            font-weight: bold;
            font-style: italic;
            color: #444;
            text-align: center;
            letter-spacing: 0;
            background: #fff;
            width: auto;
            min-width: 0;
        }

        body.pdf-mode .agreement-box.code {
            width: 54mm;
        }

        /* Title */
        body.pdf-mode .agreement-title {
            width: 190mm;
            text-align: center;
            font-size: 13pt;
            font-weight: 700;
            letter-spacing: 0.2mm;
            color: #333;
            margin-bottom: 6mm;
        }

        body.pdf-mode .agreement-subtitle {
            display: none;
        }

        /* Body text — tightened to fill page like the physical printout */
        body.pdf-mode .agreement-body {
            width: 190mm;
            font-size: 10.5pt;
            line-height: 1.55;
            text-align: justify;
            color: #333;
        }

        body.pdf-mode .agreement-body p {
            text-indent: 15mm;
            margin: 0 0 4mm 0;
        }

        body.pdf-mode .agreement-intro {
            text-indent: 15mm !important;
        }

        body.pdf-mode .agreement-pledge {
            margin: 0 0 3mm 18mm !important;
            text-indent: 0 !important;
        }

        body.pdf-mode .agreement-ol {
            margin: 0 0 4mm 22mm;
            padding-left: 6mm;
            line-height: 1.55;
        }

        body.pdf-mode .agreement-ol li {
            margin-bottom: 4mm;
            padding-left: 1.5mm;
        }

        body.pdf-mode .agreement-ul {
            margin-top: 1.5mm;
            margin-left: 6mm;
        }

        body.pdf-mode .agreement-ul li {
            margin-bottom: 0.8mm;
        }

        body.pdf-mode .agreement-footer-note {
            width: 190mm;
            margin-top: 1mm;
            margin-bottom: 5mm;
            text-align: justify;
        }

        /* Signature table */
        body.pdf-mode .agreement-sign {
            width: 190mm;
            table-layout: fixed;
            border-collapse: collapse;
            margin-top: 8mm;
            page-break-inside: avoid !important;
            break-inside: avoid !important;
        }

        body.pdf-mode .agreement-sign td {
            border: none;
            vertical-align: top;
            padding: 1mm 2mm;
        }

        body.pdf-mode .agreement-sign-left {
            width: 98mm;
            text-align: left;
            white-space: nowrap;
            font-size: 9.5pt;
        }

        body.pdf-mode .agreement-sign-right {
            width: 91mm;
            text-align: center;
            padding-top: 0;
        }

        /* Underline blanks */
        body.pdf-mode .agreement-line {
            display: inline-block;
            width: 28mm;
            border-bottom: 0.3mm solid #000;
            height: 3mm;
            vertical-align: bottom;
        }

        body.pdf-mode .agreement-line.place {
            width: 38mm;
        }

        /* Subscriber signature block */
        body.pdf-mode .agreement-subscriber-wrap {
            width: 80mm;
            margin: 0 0 0 auto;
            text-align: center;
        }

        body.pdf-mode .agreement-signature-box {
            height: 14mm;
            text-align: center;
            margin-bottom: 1mm;
            overflow: hidden;
        }

        body.pdf-mode .agreement-signature-box img {
            max-width: 40mm;
            max-height: 13mm;
            display: inline-block;
        }

        body.pdf-mode .agreement-subscriber-line {
            border-top: 0.3mm solid #000;
            padding-top: 1mm;
            font-size: 9pt;
        }

        body.pdf-mode .agreement-subscriber-sub {
            font-size: 8pt;
            color: #555;
            line-height: 1.2;
        }

        body.pdf-mode .agreement-role-line {
            width: 74mm;
            margin: 14mm 0 0 0;
            border-top: 0.3mm solid #000;
            padding-top: 1mm;
            text-align: center;
            font-size: 9pt;
        }

        body.pdf-mode .agreement-declaration-title {
            width: 190mm;
            margin-top: 4mm;
            margin-bottom: 2.5mm;
            font-weight: bold;
            font-size: 9pt;
            text-align: center;
            line-height: 1.25;
        }

        @media print {
            .agreement-page {
                padding: 4px 18px 0 18px;
            }

            .agreement-sheet {
                max-width: 100%;
            }

            .agreement-title {
                font-size: 15px;
                margin-bottom: 10px;
            }

            .agreement-body {
                font-size: 9.8px;
                line-height: 1.45;
            }

            .agreement-body p {
                text-indent: 24px;
                margin-bottom: 10px;
            }

            .agreement-ol {
                margin: 6px 0 12px 36px;
                line-height: 1.45;
            }

            .agreement-pledge {
                margin-left: 36px !important;
            }

            .agreement-sign {
                margin-top: 6px;
                page-break-inside: avoid !important;
                break-inside: avoid !important;
            }

            .agreement-subscriber-wrap {
                margin-top: 4px;
            }

            .agreement-signature-box {
                min-height: 24px;
            }

            .agreement-declaration-title {
                margin-top: 12px;
                margin-bottom: 8px;
                font-size: 10px;
                line-height: 1.3;
            }
        }

        /* =========================================================
           @page — writes page size into PDF MediaBox/CropBox.
           When format is passed as [215.9, 279.4] to mPDF AND
           this @page rule matches, the PDF is sealed at Letter size.
           PDF viewers read MediaBox — they cannot change what's
           already burned into the file by the generator.
        ========================================================== */
        @page {
            size: 215.9mm 279.4mm portrait;
            margin: 10mm;
        }

        /* ── Root canvas: locked body in pdf-mode ───────────────── */
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
                $ext  = strtolower(pathinfo($absC, PATHINFO_EXTENSION));
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

    <?php if (!empty($row["admin_notes"])): ?>
        <table>
            <tr>
                <th class="section" colspan="4">Admin Notes</th>
            </tr>
            <?= row1("Notes", $row["admin_notes"]) ?>
        </table>
    <?php endif; ?>

    <!-- ══════════════════════════════════════════════════════════ -->
    <!-- PAGE BREAK — SUBSCRIPTION AGREEMENT -->
    <!-- ══════════════════════════════════════════════════════════ -->
    <div class="agreement-page">
        <div class="agreement-sheet">

            <table class="agreement-topboxes">
                <tr>
                    <td class="agreement-box-left">
                        <?php if (($f["application_type"] ?? "Associate") === "Associate"): ?>
                            <span class="agreement-box">FOR NEW ASSOCIATE MEMBER</span>
                        <?php else: ?>
                            <span class="agreement-box">FOR TRANSFER TO REGULAR MEMBER</span>
                        <?php endif; ?>
                    </td>
                    <td class="agreement-box-right">
                        <?php if (($f["application_type"] ?? "Associate") === "Associate"): ?>
                            <span class="agreement-box code">MRD-12-A/Rev. 1</span>
                        <?php else: ?>
                            <span class="agreement-box code">MRD-12-B/Rev. 1</span>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>

            <div class="agreement-title">SUBSCRIPTION AGREEMENT</div>
            <div class="agreement-subtitle"></div>

            <div class="agreement-body">
                <?php
                $appType = $f["application_type"] ?? "Associate";
                if ($appType === "Associate"):
                ?>
                    <p class="agreement-intro">
                        I, <strong><?= v($fullName) ?></strong>, <?= v($f["civil_status"] ?? "") ?>, of legal age,
                        a resident of <?= v($f["home_address"] ?? "") ?>, hereby subscribe preferred shares of the
                        authorized share capital of Limcoma Multi-Purpose Cooperative, a cooperative duly registered
                        and existing under and by virtue of the laws of the Republic of the Philippines, with principal
                        office address at Gen. Luna St., Sabang, Lipa City.
                    </p>

                    <p class="agreement-pledge">In view of the foregoing, I hereby pledge to:</p>

                    <ol type="a" class="agreement-ol">
                        <li>
                            Subscribe <strong>THREE HUNDRED (300)</strong> preferred shares with the total amount of
                            <strong>THREE THOUSAND PESOS (P 3,000.00)</strong>;
                        </li>
                        <li>
                            Pay the sum of at least <strong>ONE THOUSAND PESOS (P1,000.00)</strong> representing the
                            value of <strong>ONE HUNDRED (100)</strong> shares, upon approval of my application for membership.
                        </li>
                        <li>
                            Pay my remaining subscribed capital of <strong>TWO THOUSAND PESOS (P2,000.00)</strong>
                            within <strong>TWO (2) years</strong>.
                        </li>
                    </ol>
                <?php else: ?>
                    <p class="agreement-intro">
                        I, <strong><?= v($fullName) ?></strong>, <?= v($f["civil_status"] ?? "") ?>, of legal age,
                        a resident of <?= v($f["home_address"] ?? "") ?>, hereby subscribe common shares of the
                        authorized share capital of Limcoma Multi-Purpose Cooperative, a cooperative duly registered
                        and existing under and by virtue of the laws of the Republic of the Philippines, with principal
                        office address at Gen. Luna St., Sabang, Lipa City.
                    </p>

                    <p class="agreement-pledge">In view of the foregoing, I hereby pledge to:</p>

                    <ol type="a" class="agreement-ol">
                        <li>
                            Subscribe <strong>TWO THOUSAND (2,000)</strong> common shares with the total amount of
                            <strong>TWENTY THOUSAND PESOS (P 20,000.00)</strong>;
                        </li>
                        <li>
                            Pay the required minimum share amounting to
                            <strong>TEN THOUSAND PESOS (P10,000.00)</strong> representing the value of
                            <strong>ONE THOUSAND (1,000)</strong> shares, upon approval of my application for membership.
                        </li>
                        <li>
                            Pay my remaining subscribed capital of <strong>TEN THOUSAND PESOS (P10,000.00)</strong>
                            within <strong>FIVE (5) years</strong>.
                        </li>
                    </ol>
                <?php endif; ?>

                <p class="agreement-footer-note">
                    I understand that my failure to pay the full subscription on the terms stated above may affect my rights
                    and the status of my membership in accordance with the Cooperative By-Laws and its rules and regulations.
                </p>
            </div>

            <table class="agreement-sign">
                <tr>
                    <td class="agreement-sign-left">
                        Done this <span class="agreement-line"></span> at <span class="agreement-line place"></span>.
                    </td>

                    <td class="agreement-sign-right">
                        <div class="agreement-subscriber-wrap">
                            <div class="agreement-signature-box">
                                <?php if ($sigImg): ?>
                                    <?= $sigImg ?>
                                <?php endif; ?>
                            </div>

                            <div class="agreement-subscriber-line"><?= v($fullName) ?></div>
                            <div class="agreement-subscriber-sub">Name and Signature of Subscriber</div>
                        </div>
                    </td>
                </tr>

                <tr>
                    <td class="agreement-sign-left">
                        <div style="margin-top:10px;">Conforme:</div>
                        <div class="agreement-role-line">MRD Manager</div>
                    </td>

                    <td class="agreement-sign-right"></td>
                </tr>
            </table>

        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════ -->
    <!-- PAGE BREAK — KASUNDUAN -->
    <!-- ══════════════════════════════════════════════════════════ -->
    <div class="agreement-page">
        <div class="agreement-sheet">

            <div class="agreement-title">KASUNDUAN, PAGSAPI AT SUBSKRIPSYON SA KAPITAL</div>

            <div class="agreement-body">
                <p>
                    Ako ay sumasang-ayon na maging kasapi ng Limcoma Multi-Purpose Cooperative at handang dumalo sa
                    kaukulang pag-aaral o ang tinatawag na <strong>"Pre-Membership Education Seminar"</strong> upang
                    malaman ko ang lahat ng mga layunin at mga gawaing pangkabuhayan ng kooperatibang ito.
                </p>

                <p>
                    Pagkatapos na ako'y matanggap bilang kasapi ng kooperatibang ito ay nangangako ako na susunod sa
                    mga naririto'ng patakaran at alituntunin.
                </p>

                <ol class="agreement-ol" style="margin-left:28px;">
                    <li>
                        Ako ay nangangakong susunod o tutupad sa mga tadhana ng Artikulo ng Kooperatiba, "By Laws" at
                        lahat ng kautusan, patakaran o alituntunin na ipinatutupad ng kooperatiba sa mga kasapi at iba
                        pang mga kinikilalang awtoridad at kung ako'y magkakasala o magkulang sa pagsunod ay nalalaman
                        ko po na ako'y mapaparusahan ng alinman sa mga sumusunod:
                        <ul class="agreement-ul">
                            <li>Multa</li>
                            <li>Pagkasuspindi sa kooperatiba</li>
                            <li>Pagkatiwalag sa kooperatiba</li>
                        </ul>
                    </li>

                    <li>
                        Ako ay nangangakong dadalo sa lahat ng pagpupulong ng kooperatiba, kumperensiya man o seminar
                        lalung-lalo na sa <strong>"Taunang Pangkalahatang Pagpupulong"</strong> o ang
                        <strong>"Annual Regular General Assembly Meeting"</strong> para sa mga regular na kasapi at kung
                        hindi makakadalo dahil sa hindi maiwasang kadahilanan ay nararapat na may kapahintulutan ng
                        kinauukulang pinuno.
                    </li>

                    <li>
                        Na ako ay maaaring matanggal bilang kasapi sa mga sumusunod na kadahilanan:
                        <ul class="agreement-ul">
                            <li>Hindi tumatangkilik ng mga produktong kooperatiba sa loob ng dalawang (2) taon.</li>
                            <li>May pagkakataong na lampas sa isang (1) taon.</li>
                            <li>Kahit padalhan ng sulat ay hindi tumutugon sa kahit na anong kadahilanan.</li>
                        </ul>
                    </li>

                    <li>Na ako ay susunod sa kautusan ng mga kinikilalang awtoridad tulad ng Cooperative Development Authority (CDA) para sa aming kabutihan.</li>

                    <li>Na ipinangangako ko na ako'y magiging isang mabuting kasapi ng kooperatiba at kung kinakailangan ng samahan ang aking tulong ay ako'y nakahandang magbigay ng personal na serbisyo para sa ikaunlad nito.</li>

                    <li>Na ako ay makikibahagi sa patuloy na pagpapalago ng kapital ng kooperatiba sa pamamagitan ng paglalaan ng aking taunang dibidendo bilang karagdagang subskripsyon at saping kapital.</li>

                    <li>Na batid ko at sumang-ayon ako na ang saping kapital ay hindi maaaring bawasan o bawiin sa loob ng 1 taon mula ng ito ay malagak maliban na lamang kung may pahintulot ng pamunuan ng Hunta Direktiba.</li>

                    <li>Na nalaman ko na kung ako'y magkasala sa kooperatiba at tuluyang itiwaalag ay maaaring parusahan ako ng samahan na hindi na ibalik sa akin ang lahat kong karapatan, kapakinabangan o ari-arian na nasa pag-iingat ng kooperatiba, maging ito ay salapi o anupaman depende sa bigat ng aking pagkakasala.</li>
                </ol>

                <div class="agreement-declaration-title">
                    DEKLARASYON AT PAHINTULOT SA PAGKOLEKTA AT PAGPROSESO NG PERSONAL NA IMPORMASYON
                </div>

                <p>
                    Pinatutunayan ko na lahat ng mga impormasyon sa dokumentong ito ay totoo. Batid ko na anumang
                    pagsisinungaling o pagkakamali ay magiging batayan sa pagkawalang-bisa, pagkansela ng aking
                    aplikasyon o pagkatiwalag sa pagiging kasapi at handa kong tanggapin ang anumang kaparusahang
                    naaayon sa batas ng Limcoma Multi-Purpose Cooperative.
                </p>

                <p>
                    Sa pamamagitan ng aking paglagda sa ibaba, sumasang-ayon ako sa ipinatutupad na Data Privacy Act at
                    nagbibigay ng aking pahintulot na kolektahin at iproseso ang aking personal na impormasyon
                    alinsunod dito.
                </p>
            </div>

            <table class="agreement-sign" style="page-break-inside:avoid; break-inside:avoid; margin-top:4px;">
                <tr>
                    <td class="agreement-sign-left"></td>
                    <td class="agreement-sign-right" style="padding-top:2px;">
                        <div class="agreement-subscriber-wrap" style="margin-top:0;">
                            <div class="agreement-signature-box" style="min-height:24px;">
                                <?php if ($sigImg): ?>
                                    <?= $sigImg ?>
                                <?php endif; ?>
                            </div>
                            <div class="agreement-subscriber-line"><?= v($fullName) ?> &nbsp;|&nbsp; <?= v($f["signature_date"] ?? "") ?></div>
                            <div class="agreement-subscriber-sub">Lagda at Petsa</div>
                        </div>
                    </td>
                </tr>
            </table>

        </div>
    </div>

</body>

</html>
<?php
$html = ob_get_clean();

if ($mode === "pdf") {
    $tempDir = __DIR__ . "/../../tmp/mpdf";
    
    if (!is_dir($tempDir)) {
        mkdir($tempDir, 0777, true);
    }

    // ── Page dimensions (Letter) ──────────────────────────────────────
    // Passing format as an exact [width, height] mm array writes these
    // values directly into the PDF MediaBox. This is the PDF-level page
    // size definition — it cannot be overridden by a viewer's paper picker.
    $mpdf = new Mpdf([
        'mode'                  => 'utf-8',
        'format'                => [215.9, 279.4],  // Letter in mm — written to MediaBox
        'orientation'           => 'P',
        'tempDir'               => $tempDir,
        'margin_left'           => 10,
        'margin_right'          => 10,
        'margin_top'            => 10,
        'margin_bottom'         => 10,
        'margin_header'         => 0,
        'margin_footer'         => 0,
        'autoScriptToLang'      => false,
        'autoLangToFont'        => false,
        'setAutoTopMargin'      => false,
        'setAutoBottomMargin'   => false,
        'shrink_tables_to_fit'  => 0,
        'ignore_invalid_utf8'   => true,
        'useSubstitutions'      => false,
    ]);

    $mpdf->showImageErrors = true;
    $mpdf->SetDisplayMode('fullpage', 'single');
    $mpdf->WriteHTML($html);

    // ══════════════════════════════════════════════════════════════════
    // FLATTEN PIPELINE — makes the PDF behave like a scanned document.
    //
    // How it works:
    //   1. mPDF saves a normal HTML→PDF to a temp file (source PDF).
    //   2. pdftoppm renders every page of that PDF to a PNG image at
    //      200 DPI — like taking a perfect screenshot of each page.
    //   3. ImageMagick packs those PNG images back into a new PDF.
    //
    // The result is a PDF whose pages are pure images — no text flow,
    // no CSS, no reflow possible. Adobe (and every other viewer) cannot
    // change the layout when printing to a different paper size because
    // there is literally no layout left to change — just pixels.
    // ══════════════════════════════════════════════════════════════════

    $uid     = uniqid('', true);
    $tmpPdf  = $tempDir . '/src_'  . $uid . '.pdf';
    $flatPdf = $tempDir . '/flat_' . $uid . '.pdf';
    $imgBase = $tempDir . '/pg_'   . $uid;
    $dpi     = 200;

    // Step 1 — write source PDF
    $mpdf->Output($tmpPdf, Destination::FILE);

    // Step 2 — render pages to PNG with pdftoppm
    exec(sprintf(
        'pdftoppm -r %d -png %s %s 2>&1',
        $dpi,
        escapeshellarg($tmpPdf),
        escapeshellarg($imgBase)
    ));

    // pdftoppm outputs: imgBase-1.png, imgBase-2.png … (or imgBase.1.png on older builds)
    $pages = glob($imgBase . '-*.png');
    if (empty($pages)) {
        $pages = glob($imgBase . '.*.png');
    }
    natsort($pages);
    $pages = array_values($pages);

    $flatOk = false;
    if (!empty($pages)) {
        // Step 3 — reassemble pages into a flat image PDF.
        // -page Letter forces the PDF MediaBox to Letter size for every page.
        // -gravity center + -extent pads any page that is smaller than Letter.
        // This means even if the viewer's paper picker is set to Legal or A4,
        // the embedded page definition says Letter — Adobe cannot stretch it.
        $pageArgs = implode(' ', array_map('escapeshellarg', $pages));
        exec(sprintf(
            'convert -compress jpeg -quality 92 -density %d -units PixelsPerInch -page Letter %s %s 2>&1',
            $dpi,
            $pageArgs,
            escapeshellarg($flatPdf)
        ), $out, $code);
        $flatOk = ($code === 0 && file_exists($flatPdf) && filesize($flatPdf) > 0);
    }

    // Step 4 — serve flat PDF (or fall back to source PDF if flatten failed)
    $serve = $flatOk ? $flatPdf : $tmpPdf;

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . addslashes($fileName) . '"');
    header('Content-Length: ' . filesize($serve));
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    readfile($serve);

    // Cleanup
    @unlink($tmpPdf);
    @unlink($flatPdf);
    foreach ($pages as $p) { @unlink($p); }

    exit;
}

header("Content-Type: text/html; charset=utf-8");
echo $html;
