<?php
// admin/api/admin_export_kasunduan_pdf.php
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

$fullName = trim(($f["first_name"] ?? $row["first_name"] ?? "") . " " . ($f["last_name"] ?? $row["last_name"] ?? ""));

$sigImg = "";
$sigPath = $f["signature_file"] ?? $row["signature_path"] ?? "";
if ($sigPath) {
    $absS = __DIR__ . "/../../" . ltrim($sigPath, "/");
    if (file_exists($absS)) {
        $sData = base64_encode(file_get_contents($absS));
        $sExt = strtolower(pathinfo($absS, PATHINFO_EXTENSION));
        $sMime = $sExt === "png" ? "image/png" : (($sExt === "webp") ? "image/webp" : "image/jpeg");
        $sigImg = "data:{$sMime};base64,{$sData}";
    }
}

$fileName = "kasunduan_capital_agreement_" . preg_replace('/[^A-Za-z0-9_-]/', '_', $fullName ?: ("ID_" . $row["id"])) . ".pdf";

ob_start();
?>
<!DOCTYPE html>
<html lang="tl">

<head>
    <meta charset="UTF-8">
    <title>Kasunduan / Capital Agreement - <?= v($fullName) ?></title>
    <style>
        @page {
            margin: 18mm 15mm 18mm 15mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            color: #000;
            font-size: 11pt;
            line-height: 1.55;
            margin: 0;
            padding: 0;
        }

        .page {
            width: 100%;
        }

        .title {
            text-align: center;
            font-size: 13pt;
            font-weight: bold;
            margin-bottom: 6mm;
            text-transform: uppercase;
        }

        p {
            text-align: justify;
            margin: 0 0 4mm 0;
        }

        ol {
            margin: 0 0 4mm 20px;
            padding: 0;
        }

        li {
            margin-bottom: 3.5mm;
            text-align: justify;
        }

        ul {
            margin-top: 1.5mm;
            margin-bottom: 1.5mm;
            padding-left: 18px;
        }

        ul li {
            margin-bottom: 1mm;
            list-style-type: none;
        }

        .section-title {
            font-size: 11pt;
            font-weight: bold;
            text-align: center;
            margin-top: 8mm;
            margin-bottom: 5mm;
            text-transform: uppercase;
        }

        .sign-wrap {
            margin-top: 16mm;
            width: 100%;
        }

        .sign-box {
            width: 68mm;
            margin-left: auto;
            text-align: center;
        }

        .sign-line-area {
            width: 100%;
            height: 20mm;
            border-bottom: 1px solid #000;
            position: relative;
            display: flex;
            align-items: flex-end;
            justify-content: center;
            padding-bottom: 2mm;
        }

        .signature-image {
            display: block;
            max-width: 50mm;
            max-height: 16mm;
            margin: 0 auto;
        }

        .sign-label {
            margin-top: 2mm;
            font-size: 10pt;
            text-align: center;
        }
    </style>
</head>

<body>
    <div class="page">

        <div class="title">KASUNDUAN, PAGSAPI AT SUBSKRIPSYON SA KAPITAL</div>

        <p>
            Ako ay sumasang-ayon na maging kasapi ng Limcoma Multi-Purpose Cooperative at handang dumalo sa
            kaukulang pag-aaral o ang tinatawag na <strong>"Pre-Membership Education Seminar"</strong> upang malaman ko
            ang lahat ng mga layunin at mga gawaing pangkabuhayan ng kooperatibang ito.
        </p>

        <p>
            Pagkatapos na ako'y matanggap bilang kasapi ng kooperatibang ito ay nangangako ako na susunod sa mga
            naririto'ng patakaran at alituntunin.
        </p>

        <ol>
            <li>
                Ako ay nangangakong susunod o tutupad sa mga tadhana ng Artikulo ng Kooperatiba, "By Laws" at lahat ng
                kautusan, patakaran o alituntunin na ipinatutupad ng kooperatiba sa mga kasapi at iba pang mga kinikilalang
                awtoridad at kung ako'y magkakasala o magkulang sa pagsunod ay nalalaman ko po na ako'y mapaparusahan ng
                alinman sa mga sumusunod:
                <ul>
                    <li>a)&nbsp;&nbsp;Multa</li>
                    <li>b)&nbsp;&nbsp;Pagkasuspindi sa kooperatiba</li>
                    <li>c)&nbsp;&nbsp;Pagkatiwalag sa kooperatiba</li>
                </ul>
            </li>

            <li>
                Ako ay nangangakong dadalo sa lahat ng pagpupulong ng kooperatiba, kumperensiya man o seminar
                lalung-lalo na sa <strong>"Taunang Pangkalahatang Pagpupulong"</strong> o ang
                <strong>"Annual Regular General Assembly Meeting"</strong> para sa mga regular na kasapi at kung hindi
                makakadalo dahil sa hindi maiwasang kadahilanan ay nararapat na may kapahintulutan ng kinauukulang pinuno.
            </li>

            <li>
                Na ako ay maaaring matanggal bilang kasapi sa mga sumusunod na kadahilanan:
                <ul>
                    <li>a)&nbsp;&nbsp;Hindi tumatangkilik ng mga produktong kooperatiba sa loob ng dalawang (2) taon.</li>
                    <li>b)&nbsp;&nbsp;May pagkakataong na lampas sa isang (1) taon.</li>
                    <li>c)&nbsp;&nbsp;Kahit padalhan ng sulat ay hindi tumutugon sa kahit na anong kadahilanan.</li>
                </ul>
            </li>

            <li>
                Na ako ay susunod sa kautusan ng mga kinikilalang awtoridad tulad ng Cooperative Development Authority (CDA)
                para sa aming kabutihan.
            </li>

            <li>
                Na ipinangangako ko na ako'y magiging isang mabuting kasapi ng kooperatiba at kung kinakailangan ng samahan
                ang aking tulong ay ako'y nakahandang magbigay ng personal na serbisyo para sa ikaunlad nito.
            </li>

            <li>
                Na ako ay makikibahagi sa patuloy na pagpapalago ng kapital ng kooperatiba sa pamamagitan ng paglalaan ng
                aking taunang dibidendo bilang karagdagang subskripsyon at saping kapital.
            </li>

            <li>
                Na batid ko at sumang-ayon ako na ang saping kapital ay hindi maaaring bawasan o bawiin sa loob ng 1 taon mula
                ng ito ay malagak maliban na lamang kung may pahintulot ng pamunuan ng Hunta Direktiba.
            </li>

            <li>
                Na nalaman ko na kung ako'y magkasala sa kooperatiba at tuluyang itiwaalag ay maaaring parusahan ako ng
                samahan na hindi na ibalik sa akin ang lahat kong karapatan, kapakinabangan o ari-arian na nasa pag-iingat ng
                kooperatiba, maging ito ay salapi o anupaman depende sa bigat ng aking pagkakasala.
            </li>
        </ol>

        <div class="section-title">DEKLARASYON AT PAHINTULOT SA PAGKOLEKTA AT PAGPROSESO NG PERSONAL NA IMPORMASYON</div>

        <p>
            Pinatutunayan ko na lahat ng mga impormasyon sa dokumentong ito ay totoo. Batid ko na anumang pagsisinungaling o
            pagkakamali ay magiging batayan sa pagkawalang-bisa, pagkansela ng aking aplikasyon o pagkatiwalag sa pagiging
            kasapi at handa kong tanggapin ang anumang kaparusahang naaayon sa batas ng Limcoma Multi-Purpose Cooperative.
        </p>

        <p>
            Sa pamamagitan ng aking paglagda sa ibaba, sumasang-ayon ako sa ipinatutupad na Data Privacy Act at nagbibigay ng
            aking pahintulot na kolektahin at iproseso ang aking personal na impormasyon alinsunod dito.
        </p>

        <div class="sign-wrap">
            <div class="sign-box">
                <div class="sign-line-area">
                    <?php if ($sigImg): ?>
                        <img src="<?= $sigImg ?>" alt="Signature" class="signature-image">
                    <?php endif; ?>
                </div>
                <div class="sign-label">Lagda at Petsa</div>
            </div>
        </div>

    </div>
</body>

</html>
<?php
$html = ob_get_clean();

$tempDir = __DIR__ . "/../../tmp/mpdf";
if (!is_dir($tempDir)) {
    mkdir($tempDir, 0777, true);
}

$mpdf = new Mpdf([
    'mode' => 'utf-8',
    'format' => 'A4',
    'orientation' => 'P',
    'tempDir' => $tempDir,
    'margin_left' => 15,
    'margin_right' => 15,
    'margin_top' => 18,
    'margin_bottom' => 18,
    'margin_header' => 0,
    'margin_footer' => 0,
    'shrink_tables_to_fit' => 0,
    'autoScriptToLang' => false,
    'autoLangToFont' => false,
    'setAutoTopMargin' => false,
    'setAutoBottomMargin' => false,
]);

$mpdf->showImageErrors = true;
$mpdf->SetDisplayMode('fullpage', 'single');
$mpdf->WriteHTML($html);

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . addslashes($fileName) . '"');
echo $mpdf->Output($fileName, Destination::STRING_RETURN);
exit;
