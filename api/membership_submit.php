<?php
// api/membership_submit.php
declare(strict_types=1);
require __DIR__ . "/db.php";

require_post();
$user_id = require_login();

// Accept multipart/form-data (because you have a photo upload input in membership_page.html) :contentReference[oaicite:3]{index=3}
$form = $_POST; // all inputs except files

// Optional: if you want to ignore "email" field and always use session email:
$form["email"] = (string)($_SESSION["email"] ?? ($form["email"] ?? ""));

$photo_path = null;

// Handle photo upload if present
if (isset($_FILES["photo"]) && $_FILES["photo"]["error"] === UPLOAD_ERR_OK) {
  $tmp = $_FILES["photo"]["tmp_name"];
  $name = $_FILES["photo"]["name"] ?? "photo";
  $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

  $allowed = ["jpg","jpeg","png","webp"];
  if (!in_array($ext, $allowed, true)) {
    http_response_code(400);
    echo json_encode(["ok" => false, "error" => "Photo must be JPG/PNG/WEBP"]);
    exit;
  }

  $uploadDir = dirname(__DIR__) . "/uploads";
  if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

  $newName = "member_" . $user_id . "_" . time() . "." . $ext;
  $dest = $uploadDir . "/" . $newName;

  if (!move_uploaded_file($tmp, $dest)) {
    http_response_code(500);
    echo json_encode(["ok" => false, "error" => "Failed to save photo"]);
    exit;
  }

  // store relative path for later display
  $photo_path = "uploads/" . $newName;
}

// Save everything as JSON
$form_json = json_encode($form, JSON_UNESCAPED_UNICODE);

try {
  $ins = $pdo->prepare("INSERT INTO membership_submissions (user_id, photo_path, form_json, status) VALUES (?,?,?,?)");
  $ins->execute([$user_id, $photo_path, $form_json, ""]);

  echo json_encode(["ok" => true]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(["ok" => false, "error" => "Server error"]);
}