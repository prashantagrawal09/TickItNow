<?php
require_once "../db.php";
require_once "_session_boot.php";

function redirect($url){ header("Location: $url"); exit; }
function back_err($msg){ redirect("../register.html?error=".urlencode($msg)); }

$full_name = trim($_POST['full_name'] ?? '');
$email     = trim($_POST['email'] ?? '');
$phone     = trim($_POST['phone'] ?? '');
$password  = $_POST['password'] ?? '';

if ($full_name === '' || $email === '' || $phone === '' || $password === '') {
  back_err("All fields are required.");
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  back_err("Invalid email address.");
}
if (strlen($password) < 8 || !preg_match('/[A-Za-z]/',$password) || !preg_match('/\d/',$password)) {
  back_err("Password must be ≥8 chars and include letters & numbers.");
}

try {
  // Check duplicate email
  $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
  $stmt->execute([$email]);
  if ($stmt->fetch()) back_err("An account with this email already exists.");

  $hash = password_hash($password, PASSWORD_DEFAULT);

  $ins = $pdo->prepare("INSERT INTO users (full_name, email, phone, password_hash) VALUES (?,?,?,?)");
  $ins->execute([$full_name, $email, $phone, $hash]);

  // Log in immediately
  $_SESSION['user'] = [
    'id'   => $pdo->lastInsertId(),
    'name' => $full_name,
    'email'=> $email,
    'phone'=> $phone
  ];

  // If they came from a return= available.html, send them back there
  $ret = $_GET['return'] ?? $_POST['return'] ?? '';
  if ($ret) redirect("../$ret?notice=".urlencode("Account created successfully"));

  redirect("../index.html?notice=".urlencode("Account created successfully"));
} catch (Throwable $e) {
  back_err("Server error: ".$e->getMessage());
}