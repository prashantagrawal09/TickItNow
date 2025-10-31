<?php
require_once __DIR__ . '/server/db.php';

$email = trim($_POST['email'] ?? '');
$pass  = trim($_POST['password'] ?? '');

$stmt = $pdo->prepare("SELECT * FROM users WHERE email=?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if(!$user || !password_verify($pass,$user['password_hash'])){
  exit("<p>Invalid email or password. <a href='login.html'>Try again</a></p>");
}

session_start();
$_SESSION['user_id'] = $user['id'];
$_SESSION['user_name'] = $user['full_name'];

echo "<h2>Welcome back, ".htmlspecialchars($user['full_name'])."!</h2>";
echo "<p><a href='index.html'>Go to home</a></p>";