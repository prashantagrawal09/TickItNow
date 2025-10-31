<?php
// contact_submit.php (PHP 7+)
// SECURITY: use prepared statements; no external emails (course rule).

require_once __DIR__ . '/server/db.php'; // PDO $pdo (create this once for your project)

function clean($s){ return trim($s ?? ''); }

$topic       = clean($_POST['topic'] ?? '');
$full_name   = clean($_POST['full_name'] ?? '');
$email       = clean($_POST['email'] ?? '');
$phone       = clean($_POST['phone'] ?? '');
$booking_ref = clean($_POST['booking_ref'] ?? '');
$message     = clean($_POST['message'] ?? '');

$errors = [];
if ($topic === '') $errors[] = "Topic is required.";
if ($full_name === '') $errors[] = "Full name is required.";
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required.";
if ($phone === '') $errors[] = "Mobile number is required.";
if ($message === '') $errors[] = "Please describe your issue.";

if ($errors) {
  // Basic failback UI (keep it simple for the course)
  echo "<h2>There were problems with your submission:</h2><ul>";
  foreach ($errors as $e) echo "<li>" . htmlspecialchars($e) . "</li>";
  echo "</ul><p><a href='contact.html'>Go back</a></p>";
  exit;
}

// Insert into DB
$stmt = $pdo->prepare("
  INSERT INTO contact_messages (topic, full_name, email, phone, booking_ref, message, created_at)
  VALUES (?,?,?,?,?,?,NOW())
");
$stmt->execute([$topic, $full_name, $email, $phone, $booking_ref, $message]);
$id = $pdo->lastInsertId();

// Local email acknowledgement (course rule: only to local account)
$subject = "TickItNow – We received your message (Ref #$id)";
$body = "Hi $full_name,\n\nThanks for contacting TickItNow.\n\n".
        "Reference: $id\nTopic: $topic\nBooking Ref: $booking_ref\n\n".
        "We’ll get back to you shortly.\n\n— TickItNow Support";
@mail($email, $subject, $body, "From: support@localhost");

// Simple thank-you page
echo "<!doctype html><meta charset='utf-8'><title>Thanks</title>";
echo "<div style='font-family:system-ui; max-width:680px; margin:40px auto'>";
echo "<h2>Thank you, $full_name!</h2>";
echo "<p>Your request has been received. Reference: <strong>#".htmlspecialchars($id)."</strong></p>";
echo "<p><a href='index.html'>Return to home</a></p>";
echo "</div>";