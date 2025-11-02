<?php
require_once __DIR__ . '/server/db.php';
require 'server/PHPMailer/src/PHPMailer.php';
require 'server/PHPMailer/src/SMTP.php';
require 'server/PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;

function clean($s){ return trim($s ?? ''); }

$name = clean($_POST['full_name']);
$email = clean($_POST['email']);
$password = clean($_POST['password']);

$errors = [];

// Email validation
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  $errors[] = "Invalid email format.";
}
$domain = substr(strrchr($email, "@"), 1);
if (!checkdnsrr($domain, "MX")) {
  $errors[] = "Email domain does not exist.";
}

// Password validation
if (strlen($password) < 8 || !preg_match('/[A-Za-z]/',$password) || !preg_match('/[0-9]/',$password)) {
  $errors[] = "Password must be at least 8 characters and contain letters and numbers.";
}

if ($errors) {
  echo "<h2>Errors:</h2><ul>";
  foreach($errors as $e) echo "<li>".htmlspecialchars($e)."</li>";
  echo "</ul><a href='register.html'>Go back</a>";
  exit;
}

// Hash password
$hash = password_hash($password, PASSWORD_DEFAULT);

// Insert
$stmt = $pdo->prepare("INSERT INTO users(full_name,email,password_hash,created_at) VALUES (?,?,?,NOW())");
$stmt->execute([$name,$email,$hash]);

// Send confirmation email via SMTP
$mail = new PHPMailer(true);
try {
  $mail->isSMTP();
  $mail->Host = 'smtp.gmail.com';
  $mail->SMTPAuth = true;
  $mail->Username = 'your_email@gmail.com';
  $mail->Password = 'your_app_password';
  $mail->SMTPSecure = 'tls';
  $mail->Port = 587;

  $mail->setFrom('noreply@tickitnow.local', 'TickItNow');
  $mail->addAddress($email, $name);
  $mail->Subject = 'Welcome to TickItNow!';
  $mail->Body = "Hi $name,\n\nYour account was successfully created.\n\nEnjoy booking your favourite shows!\n\n— TickItNow Team";

  $mail->send();
  echo "<h2>Account created successfully!</h2><p>A confirmation email has been sent to your address.</p><a href='login.html'>Log in now</a>";
} catch (Exception $e) {
  echo "<p>Account created, but email could not be sent: {$mail->ErrorInfo}</p>";
}