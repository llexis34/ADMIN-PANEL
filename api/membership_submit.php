<?php
// api/membership_submit.php
declare(strict_types=1);
require __DIR__ . "/db.php";

require_post();
$user_id = require_login();

// Accept multipart/form-data (photo + signature_file uploads)
$form = $_POST;

// Convert all string inputs to uppercase
foreach ($form as $key => $value) {
  if (is_string($value)) {
    $form[$key] = strtoupper(trim($value));
  }
}

// Always use session email
$form["email"] = (string)($_SESSION["email"] ?? ($form["email"] ?? ""));

// Sanitize facebook_link if provided (new field added in Folder 2)
if (isset($form["facebook_link"])) {
  $form["facebook_link"] = trim((string)$form["facebook_link"]);
}

$uploadDir = dirname(__DIR__) . "/uploads";
if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

// Build safe name prefix
$firstName = trim((string)($form["first_name"] ?? ""));
$lastName  = trim((string)($form["last_name"] ?? ""));

if ($firstName === "" || $lastName === "") {
  $stUser = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
  $stUser->execute([$user_id]);
  $uRow = $stUser->fetch(PDO::FETCH_ASSOC);
  if ($uRow) {
    if ($firstName === "") $firstName = (string)($uRow["first_name"] ?? "");
    if ($lastName === "")  $lastName  = (string)($uRow["last_name"] ?? "");
  }
}

$safeFirst = trim(strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $firstName)), '_') ?: "member";
$safeLast  = trim(strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $lastName)), '_')  ?: "user";

$photo_path = null;

// Handle photo upload
if (isset($_FILES["photo"]) && $_FILES["photo"]["error"] === UPLOAD_ERR_OK) {
  $tmp  = $_FILES["photo"]["tmp_name"];
  $name = $_FILES["photo"]["name"] ?? "photo";
  $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));

  $allowed = ["jpg", "jpeg", "png", "webp"];
  if (!in_array($ext, $allowed, true)) {
    http_response_code(400);
    echo json_encode(["ok" => false, "error" => "Photo must be JPG/PNG/WEBP"]);
    exit;
  }

  // Delete old photo
  $stOld = $pdo->prepare("SELECT photo_path FROM membership_submissions WHERE user_id = ? ORDER BY id DESC LIMIT 1");
  $stOld->execute([$user_id]);
  $oldRow = $stOld->fetch(PDO::FETCH_ASSOC);
  if ($oldRow && !empty($oldRow["photo_path"])) {
    $oldFile = dirname(__DIR__) . "/" . ltrim((string)$oldRow["photo_path"], "/");
    if (is_file($oldFile)) @unlink($oldFile);
  }

  $newName = $safeFirst . "_" . $safeLast . "_" . $user_id . "_" . time() . "." . $ext;
  $dest    = $uploadDir . "/" . $newName;

  if (!move_uploaded_file($tmp, $dest)) {
    http_response_code(500);
    echo json_encode(["ok" => false, "error" => "Failed to save photo"]);
    exit;
  }

  $photo_path = "uploads/" . $newName;
}

// Handle signature file upload (Folder 2 changed from text input to file upload)
if (isset($_FILES["signature_file"]) && $_FILES["signature_file"]["error"] === UPLOAD_ERR_OK) {
  $tmp  = $_FILES["signature_file"]["tmp_name"];
  $name = $_FILES["signature_file"]["name"] ?? "signature";
  $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));

  $allowedSig = ["jpg", "jpeg", "png", "webp", "gif"];
  if (!in_array($ext, $allowedSig, true)) {
    http_response_code(400);
    echo json_encode(["ok" => false, "error" => "Signature must be an image (JPG/PNG/WEBP/GIF)"]);
    exit;
  }

  $sigName = $safeFirst . "_" . $safeLast . "_sig_" . $user_id . "_" . time() . "." . $ext;
  $dest    = $uploadDir . "/" . $sigName;

  if (!move_uploaded_file($tmp, $dest)) {
    http_response_code(500);
    echo json_encode(["ok" => false, "error" => "Failed to save signature file"]);
    exit;
  }

  $form["signature_file"] = "uploads/" . $sigName;
  unset($form["signature"]); // remove old text-based key if present
}

$form["subscription_agreement_accepted"] = (isset($form["subscription_agreement_accepted"]) && $form["subscription_agreement_accepted"] === "1") ? "1" : "0";
$form["kasunduan_accepted"] = (isset($form["kasunduan_accepted"]) && $form["kasunduan_accepted"] === "1") ? "1" : "0";

// Save all fields as JSON (includes facebook_link and signature path)
$form_json = json_encode($form, JSON_UNESCAPED_UNICODE);

try {
  $ins = $pdo->prepare("INSERT INTO membership_submissions (user_id, photo_path, form_json, status) VALUES (?,?,?,?)");
  $ins->execute([$user_id, $photo_path, $form_json, ""]);

  echo json_encode(["ok" => true]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(["ok" => false, "error" => "Server error"]);
}
