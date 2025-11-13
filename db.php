<?php
$dsn = "mysql:host=localhost;dbname=TickItNow;charset=utf8mb4";
$user = "root";
$pass = "";
$options = [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];
$pdo = new PDO($dsn, $user, $pass, $options);
?>
