<?php
require_once "../db.php";
require_once "_session_boot.php";

function redirect($url){ header("Location: $url"); exit; }
function back_err($msg){ redirect("../login.html?error=".urlencode($msg)); }

$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if ($email === '' || $password === '') back_err("Email and password are required.");

try{
  $stmt = $pdo->prepare("SELECT id, full_name, email, phone, password_hash FROM users WHERE email = ?");
  $stmt->execute([$email]);
  $u = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$u || !password_verify($password, $u['password_hash'])) {
    back_err("Account does not exist or password is incorrect.");
  }

  $_SESSION['user'] = [
    'id'    => $u['id'],
    'name'  => $u['full_name'],
    'email' => $u['email'],
    'phone' => $u['phone']
  ];

  $ret = $_GET['return'] ?? $_POST['return'] ?? '';
  if ($ret) redirect("../$ret?notice=".urlencode("Logged in successfully"));
  redirect("../index.html?notice=".urlencode("Logged in successfully"));
}catch(Throwable $e){
  back_err("Server error: ".$e->getMessage());
}