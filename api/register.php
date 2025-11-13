<?php
require_once "../db.php";
require_once "_session_boot.php";

function redirect($url){ header("Location: $url"); exit; }
function back_err($msg){ redirect("../register.html?error=".urlencode($msg)); }

// Accept either 'full_name' or 'name' from the form
$full_name = trim($_POST['full_name'] ?? ($_POST['name'] ?? ''));
$email     = trim($_POST['email'] ?? '');

$rawPhone = $_POST['phone'] ?? '';
$digits = preg_replace('/\D+/', '', $rawPhone);
if (strlen($digits) !== 8) {
  back_err("Phone must be exactly 8 digits (Singapore).");
}
$phone = $digits; // store ONLY the 8 digits

$password  = $_POST['password'] ?? '';
$confirmPw = $_POST['confirm_password'] ?? '';

if ($full_name === '' || $email === '' || $phone === '' || $password === '' || $confirmPw === '') {
  back_err("All fields are required.");
}
if (!preg_match("/^[A-Za-z][A-Za-z\s'.-]+$/", $full_name)) {
  back_err("Name can only contain letters, spaces, apostrophes, and hyphens.");
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  back_err("Invalid email address.");
}
// Keep server-side stricter than client: ≥8 chars, letters & numbers
if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
  back_err("Password must be ≥8 chars and include letters & numbers.");
}
if ($password !== $confirmPw) {
  back_err("Passwords do not match.");
}

try {
  // Duplicate email check
  $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
  $stmt->execute([$email]);
  if ($stmt->fetch()) back_err("An account with this email already exists.");

  $hash = password_hash($password, PASSWORD_DEFAULT);

  // NOTE: if your users table uses 'name' instead of 'full_name',
  // change the column list below accordingly.
  $ins = $pdo->prepare("INSERT INTO users (full_name, email, phone, password_hash) VALUES (?,?,?,?)");
  $ins->execute([$full_name, $email, $phone, $hash]);

  // Log in immediately
  $_SESSION['user'] = [
    'id'    => $pdo->lastInsertId(),
    'name'  => $full_name,
    'email' => $email,
    'phone' => $phone
  ];

  // Optional return flow
  $ret = $_GET['return'] ?? $_POST['return'] ?? '';
  if ($ret) redirect("../$ret?notice=".urlencode("Account created successfully"));

  redirect("../index.html?notice=".urlencode("Account created successfully"));

} catch (Throwable $e) {
  // Helpful for debugging in PHP error log:
  // error_log('register.php failed: '.$e->getMessage());
  back_err("Server error: ".$e->getMessage());
}
