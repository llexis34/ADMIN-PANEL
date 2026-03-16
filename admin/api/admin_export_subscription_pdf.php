<?php
// admin/api/admin_export_subscription_pdf.php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);

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
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
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

function v($val): string
{
    return htmlspecialchars((string)($val ?? ""), ENT_QUOTES, 'UTF-8');
}

function normalize_application_type(?string $type): string
{
    $t = trim((string)$type);
    if ($t === "Regular" || $t === "Transfer to Regular") {
        return "Regular";
    }
    return "Associate";
}

$appType     = normalize_application_type($f["application_type"] ?? "");
$fullName    = trim(($f["first_name"] ?? $row["first_name"] ?? "") . " " . ($f["last_name"] ?? $row["last_name"] ?? ""));
$homeAddress = trim((string)($f["home_address"] ?? ""));
$civilStatus = trim((string)($f["civil_status"] ?? ""));

$sigImg  = "";
$sigPath = $f["signature_file"] ?? $row["signature_path"] ?? "";
if ($sigPath) {
    $absS = __DIR__ . "/../../" . ltrim($sigPath, "/");
    if (file_exists($absS)) {
        $sData  = base64_encode(file_get_contents($absS));
        $sExt   = strtolower(pathinfo($absS, PATHINFO_EXTENSION));
        $sMime  = $sExt === "png" ? "image/png" : (($sExt === "webp") ? "image/webp" : "image/jpeg");
        $sigImg = "data:{$sMime};base64,{$sData}";
    }
}

if ($appType === "Regular") {
    $agreementChipLeft  = "FOR TRANSFER TO REGULAR MEMBER";
    $agreementChipRight = "MRD-12-B/Rev. 1";
    $shareType          = "common";
    $shareCount         = "TWO THOUSAND (2,000)";
    $totalAmount        = "TWENTY THOUSAND PESOS (P 20,000.00)";
    $minimumPayment     = "TEN THOUSAND PESOS (P10,000.00)";
    $minimumShares      = "ONE THOUSAND (1,000)";
    $remainingCapital   = "TEN THOUSAND PESOS (P10,000.00)";
    $payPeriod          = "FIVE (5) years";
} else {
    $agreementChipLeft  = "FOR NEW ASSOCIATE MEMBER";
    $agreementChipRight = "MRD-12-A/Rev. 1";
    $shareType          = "preferred";
    $shareCount         = "THREE HUNDRED (300)";
    $totalAmount        = "THREE THOUSAND PESOS (P 3,000.00)";
    $minimumPayment     = "ONE THOUSAND PESOS (P1,000.00)";
    $minimumShares      = "ONE HUNDRED (100)";
    $remainingCapital   = "TWO THOUSAND PESOS (P2,000.00)";
    $payPeriod          = "TWO (2) years";
}

$fileName = "subscription_agreement_" . preg_replace('/[^A-Za-z0-9_-]/', '_', $fullName ?: ("ID_" . $row["id"])) . ".pdf";

ob_start();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Subscription Agreement - <?= v($fullName) ?></title>
    <style>
        @page {
            margin: 14mm 15mm 13mm 15mm;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            font-size: 11pt;
            color: #000;
            line-height: 1.45;
        }

        .page {
            width: 100%;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        /* ── HEADER ── */
        .hdr {
            margin-bottom: 10mm;
        }

        .hdr td {
            vertical-align: middle;
        }

        .hdr-left {
            width: 50%;
        }

        .hdr-right {
            width: 50%;
            text-align: right;
        }

        /*
         * Both boxes: 1px border, italic bold — matches the original printed form.
         */
        .hdr-box-left {
            display: inline-table;
            width: auto;
            border-collapse: collapse;
            border: 1px solid #000;
            font-style: italic;
            font-weight: bold;
            font-size: 10pt;
            text-align: left;
        }

        .hdr-box-left td {
            padding: 2.5mm 5mm;
            line-height: 1.2;
        }

        .hdr-box-right {
            display: inline-table;
            width: auto;
            border-collapse: collapse;
            border: 1px solid #000;
            font-style: italic;
            font-weight: bold;
            font-size: 10pt;
            text-align: center;
        }

        .hdr-box-right td {
            padding: 2.5mm 5mm;
            line-height: 1.2;
        }

        /* ── TITLE ── */
        .title {
            text-align: center;
            font-weight: bold;
            font-size: 13pt;
            margin-bottom: 8mm;
        }

        /* ── INTRO PARAGRAPH ──
         *
         * Single <td> so mPDF keeps all inline content (including the
         * underlined spans) on the same flow.
         * Indent is faked with &nbsp; entities — mPDF-safe approach.
         */
        .intro-wrap {
            width: 100%;
            margin-bottom: 8mm;
        }

        .intro-wrap td {
            text-align: justify;
            line-height: 1.75;
            vertical-align: top;
            padding: 0;
        }

        /*
         * Underlined fill-in field.
         * border-bottom aligns flush with the text baseline in mPDF.
         * display:inline is the only value mPDF honours inside flowing text.
         */
        .u {
            border-bottom: 1px solid #000;
            padding: 0 1.5mm 0 1.5mm;
            white-space: nowrap;
            display: inline;
        }

        /* ── LEAD LINE ── */
        .lead {
            margin: 0 0 5mm 12mm;
            text-align: left;
            font-size: 11pt;
        }

        /* ── PLEDGE ITEMS ── */
        .items {
            width: 100%;
            margin-bottom: 7mm;
        }

        .items td {
            vertical-align: top;
            line-height: 1.7;
        }

        .letter {
            width: 14mm;
            padding-left: 10mm;
            padding-right: 2mm;
            padding-bottom: 6mm;
        }

        .text {
            padding-right: 6mm;
            padding-bottom: 6mm;
            text-align: justify;
        }

        .ub {
            font-weight: bold;
            text-decoration: underline;
        }

        /* ── CLOSING ── */
        .closing-wrap {
            width: 100%;
            margin-bottom: 9mm;
        }

        .closing-wrap td {
            text-align: justify;
            line-height: 1.65;
            padding: 0;
        }

        /* ── DONE THIS ── */
        .done {
            width: 72%;
            margin: 0 auto 14mm auto;
        }

        .done td {
            vertical-align: bottom;
            white-space: nowrap;
        }

        .done-label {
            width: 20mm;
        }

        .done-date {
            width: 44mm;
            border-bottom: 1px solid #000;
            text-align: center;
            padding: 0 2mm 0.8mm 2mm;
        }

        .done-at {
            width: 10mm;
            text-align: center;
        }

        .done-place {
            width: 60mm;
            border-bottom: 1px solid #000;
            text-align: center;
            padding: 0 2mm 0.8mm 2mm;
        }

        /* ── SUBSCRIBER SIGNATURE ──
         * Sits on the RIGHT side of the page.
         * Uses a two-column table: left spacer + right signature block.
         */
        .sig-section {
            width: 100%;
            margin-bottom: 10mm;
        }

        .sig-section td {
            padding: 0;
            vertical-align: top;
        }

        .sig-spacer {
            width: 50%;
        }

        .sig-right {
            width: 50%;
            text-align: center;
            padding-right: 0;
        }

        .sig-line-wrap {
            width: 75%;
            margin: 0 auto 1.5mm auto;
        }

        .sig-line-wrap td {
            vertical-align: bottom;
            padding: 0;
        }

        .sig-line {
            height: 16mm;
            border-bottom: 1px solid #000;
            text-align: center;
            vertical-align: bottom;
        }

        .sig-img {
            max-width: 34mm;
            max-height: 10mm;
            display: block;
            margin: 0 auto 1mm auto;
        }

        .sig-label {
            text-align: center;
            font-size: 11pt;
        }

        /* ── CONFORME / MRD MANAGER ──
         * Placed BELOW the subscriber signature block, on the LEFT side.
         */
        .conforme-section {
            width: 100%;
        }

        .conforme-section td {
            padding: 0;
            vertical-align: top;
        }

        .conforme-left {
            width: 52%;
            padding: 0 0 0 4mm;
            text-align: left;
        }

        .conforme-spacer {
            width: 48%;
        }

        .conforme-label {
            font-size: 11pt;
            margin-bottom: 3mm;
            text-align: left;
        }

        /* narrow wrap so the line is short like the original photo (~42mm) */
        .mrd-sig-table {
            width: 60mm;
            margin: 0;
            border-collapse: collapse;
        }

        .mrd-sig-table td {
            padding: 0;
        }

        .mrd-sig-line {
            height: 16mm;
            border-bottom: 1px solid #000;
        }

        .mrd-text {
            text-align: center;
            font-size: 11pt;
            padding-top: 1.5mm;
        }
    </style>
</head>

<body>
    <div class="page">

        <!-- ── HEADER ── -->
        <table class="hdr">
            <tr>
                <td class="hdr-left">
                    <table class="hdr-box-left" cellspacing="0" cellpadding="0">
                        <tr>
                            <td><?= v($agreementChipLeft) ?></td>
                        </tr>
                    </table>
                </td>
                <td class="hdr-right">
                    <table class="hdr-box-right" cellspacing="0" cellpadding="0">
                        <tr>
                            <td><?= v($agreementChipRight) ?></td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <!-- ── TITLE ── -->
        <div class="title">SUBSCRIPTION AGREEMENT</div>

        <!-- ── INTRO PARAGRAPH ──
             All content in a single <td>.
             Leading &nbsp; entities create the first-line indent (mPDF-safe).
             Name and address get underlines; civil status is plain text.
        -->
        <table class="intro-wrap">
            <tr>
                <td>
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;I,
                    <span class="u"><?= v($fullName) ?></span>,
                    <?= v(strtolower($civilStatus ?: "single")) ?>
                    of legal age, a resident of
                    <span class="u"><?= v($homeAddress) ?></span>,
                    hereby subscribe <?= v($shareType) ?> shares of the authorized
                    share capital of Limcoma Multi-Purpose Cooperative, a cooperative duly registered and existing
                    under and by virtue of the laws of the Republic of the Philippines, with principal office address at
                    Gen. Luna St., Sabang, Lipa City.
                </td>
            </tr>
        </table>

        <!-- ── LEAD ── -->
        <div class="lead">In view of the foregoing, I hereby pledge to:</div>

        <!-- ── PLEDGE ITEMS ── -->
        <table class="items">
            <tr>
                <td class="letter">a.</td>
                <td class="text">
                    Subscribe <span class="ub"><?= v($shareCount) ?></span> <?= v($shareType) ?> shares with the total amount of
                    <span class="ub"><?= v($totalAmount) ?></span>;
                </td>
            </tr>
            <tr>
                <td class="letter">b.</td>
                <td class="text">
                    <?php if ($appType === "Regular"): ?>
                        Pay the required minimum share amounting to
                    <?php else: ?>
                        Pay the sum of at least
                    <?php endif; ?>
                    <span class="ub"><?= v($minimumPayment) ?></span>
                    representing the value of
                    <span class="ub"><?= v($minimumShares) ?></span>
                    shares, upon approval of my application for membership.
                </td>
            </tr>
            <tr>
                <td class="letter">c.</td>
                <td class="text">
                    Pay my remaining subscribed capital of
                    <span class="ub"><?= v($remainingCapital) ?></span>
                    within <span class="ub"><?= v($payPeriod) ?></span>.
                </td>
            </tr>
        </table>

        <!-- ── CLOSING ──
             Same indent technique as the intro paragraph.
        -->
        <table class="closing-wrap">
            <tr>
                <td>
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;I understand that my failure to pay the full subscription on the terms stated above may
                    affect my rights and the status of my membership in accordance with the Cooperative By-Laws
                    and its rules and regulations.
                </td>
            </tr>
        </table>

        <!-- ── DONE THIS (intentionally left blank) ── -->
        <table class="done">
            <tr>
                <td class="done-label">Done this</td>
                <td class="done-date">&nbsp;</td>
                <td class="done-at">at</td>
                <td class="done-place">&nbsp;</td>
            </tr>
        </table>

        <!-- ── SUBSCRIBER SIGNATURE (right side) ── -->
        <table class="sig-section">
            <tr>
                <td class="sig-spacer">&nbsp;</td>
                <td class="sig-right">
                    <table class="sig-line-wrap">
                        <tr>
                            <td class="sig-line">
                                <?php if ($sigImg): ?>
                                    <img src="<?= $sigImg ?>" alt="Signature" class="sig-img">
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                    <div class="sig-label">Name and Signature of Subscriber</div>
                </td>
            </tr>
        </table>

        <!-- ── CONFORME / MRD MANAGER ──
             Appears BELOW the subscriber signature, on the LEFT side.
        -->
        <table class="conforme-section">
            <tr>
                <td class="conforme-left">
                    <div class="conforme-label">Conforme:</div>
                    <div class="mrd-wrap">
                        <table class="mrd-sig-table">
                            <tr>
                                <td class="mrd-sig-line">&nbsp;</td>
                            </tr>
                            <tr>
                                <td class="mrd-text">MRD Manager</td>
                            </tr>
                        </table>
                    </div>
                </td>
                <td class="conforme-spacer">&nbsp;</td>
            </tr>
        </table>
    </div>
</body>

</html>
<?php
$html = ob_get_clean();

$tempDir = __DIR__ . "/../../tmp/mpdf";
if (!is_dir($tempDir)) {
    mkdir($tempDir, 0777, true);
}

$oldHandler = set_error_handler(static function (
    int $severity,
    string $message,
    string $file = '',
    int $line = 0
): bool {
    return true;
});

try {
    $mpdf = new Mpdf([
        'mode'                 => 'utf-8',
        'format'               => 'A4',
        'orientation'          => 'P',
        'tempDir'              => $tempDir,
        'margin_left'          => 15,
        'margin_right'         => 15,
        'margin_top'           => 14,
        'margin_bottom'        => 13,
        'margin_header'        => 0,
        'margin_footer'        => 0,
        'shrink_tables_to_fit' => 0,
        'autoScriptToLang'     => false,
        'autoLangToFont'       => false,
        'setAutoTopMargin'     => false,
        'setAutoBottomMargin'  => false,
    ]);

    $mpdf->showImageErrors = false;
    $mpdf->WriteHTML($html);
    $pdf = $mpdf->Output($fileName, Destination::STRING_RETURN);

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . addslashes($fileName) . '"');
    echo $pdf;
    exit;
} catch (Throwable $e) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(500);
    echo 'PDF generation failed: ' . $e->getMessage();
    exit;
} finally {
    restore_error_handler();
}
