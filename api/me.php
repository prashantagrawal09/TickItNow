<?php
// api/me.php
require_once "_session_boot.php";
header("Content-Type: application/json; charset=utf-8");

// If you already have login, read user from session:
if (!empty($_SESSION['user'])) {
  $u = $_SESSION['user']; // e.g. ['id'=>..., 'name'=>..., 'email'=>..., 'phone'=>...]
  echo json_encode([
    "authenticated" => true,
    "name"  => $u['name']  ?? '',
    "email" => $u['email'] ?? '',
    "phone" => $u['phone'] ?? ''
  ]);
  exit;
}

// Not logged in
echo json_encode(["authenticated"=>false]);