<?php
require __DIR__ . '/vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;

$mail = new PHPMailer(true);
$mail->isSMTP();
$mail->Host = '127.0.0.1';
$mail->Port = 1025;
$mail->SMTPAuth = false;
$mail->SMTPSecure = false;

$mail->setFrom('no-reply@tickitnow.local', 'TickItNow');
$mail->addAddress('you@example.com', 'You');
$mail->isHTML(true);
$mail->Subject = 'Mailpit test';
$mail->Body = 'Hello from PHPMailer + Mailpit!';
$mail->send();

echo 'Sent — check Mailpit at http://localhost:8025';