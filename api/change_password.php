<?php
require_once "../db.php";
require_once "_session_boot.php";
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user']['id'])) {
  http_response_code(401);
  echo json_encode(['ok' => false, 'error' => 'Login required.']);
  exit;
}

$userId = (int)$_SESSION['user']['id'];
$current = $_POST['current_password'] ?? '';
$new     = $_POST['new_password'] ?? '';
$confirm = $_POST['confirm_password'] ?? '';

if ($current === '' || $new === '' || $confirm === '') {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'All fields are required.']);
  exit;
}
if ($new !== $confirm) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'New passwords do not match.']);
  exit;
}
if (strlen($new) < 8 || !preg_match('/[A-Za-z]/', $new) || !preg_match('/\d/', $new)) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'Password must be ≥8 chars with letters and numbers.']);
  exit;
}

try {
  $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ? LIMIT 1");
  $stmt->execute([$userId]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$row || !password_verify($current, $row['password_hash'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Current password is incorrect.']);
    exit;
  }

  $hash = password_hash($new, PASSWORD_DEFAULT);
  $upd = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
  $upd->execute([$hash, $userId]);

  echo json_encode(['ok' => true, 'message' => 'Password updated successfully.']);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Server error: '.$e->getMessage()]);
}
