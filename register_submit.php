<?php
require_once __DIR__ . '/server/db.php';

function clean($s){ return trim($s ?? ''); }

$name = clean($_POST['full_name'] ?? '');
$email = clean($_POST['email'] ?? '');
$password = clean($_POST['password'] ?? '');

if($name==='' || $email==='' || $password===''){
  exit("<p>Please fill all fields. <a href='register.html'>Go back</a></p>");
}
if(!filter_var($email,FILTER_VALIDATE_EMAIL)){
  exit("<p>Invalid email format. <a href='register.html'>Go back</a></p>");
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $pdo->prepare("INSERT INTO users(full_name,email,password_hash,created_at) VALUES (?,?,?,NOW())");
$stmt->execute([$name,$email,$hash]);

echo "<h2>Account created!</h2><p><a href='login.html'>Log in now</a></p>";