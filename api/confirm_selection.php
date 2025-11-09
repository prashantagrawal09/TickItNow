<?php
require_once "../db.php";
require_once "_session_boot.php";

require_once dirname(__DIR__) . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header("Content-Type: application/json; charset=utf-8");

// Always have a $body var
$raw = file_get_contents('php://input');
$body = null;
if ($raw) {
  $tmp = json_decode($raw, true);
  if (json_last_error() === JSON_ERROR_NONE) { $body = $tmp; }
}

/*
POST/JSON:
  select_item_ids[]=<pref_id>  (or "1,2,3")
  buyer_name, buyer_email, buyer_phone, buyer_note
*/

// ---------- collect selected IDs ----------
$ids = [];
if (isset($_POST['select_item_ids'])) {
  $ids = is_array($_POST['select_item_ids'])
    ? array_map('intval', $_POST['select_item_ids'])
    : array_map('intval', explode(',', $_POST['select_item_ids']));
} elseif ($body && isset($body['select_item_ids'])) {
  $v = $body['select_item_ids'];
  $ids = is_array($v) ? array_map('intval', $v) : array_map('intval', explode(',', $v));
}
$ids = array_values(array_filter($ids, fn($x)=>$x>0));
if (!count($ids)) { http_response_code(400); echo json_encode(["error"=>"No items selected"]); exit; }

// ---------- collect buyer fields ----------
$buyer_name  = trim($_POST['buyer_name']  ?? ($body['buyer_name']  ?? ''));
$buyer_email = trim($_POST['buyer_email'] ?? ($body['buyer_email'] ?? ''));
$buyer_phone_raw = trim($_POST['buyer_phone'] ?? ($body['buyer_phone'] ?? ''));
$buyer_digits = preg_replace('/\D+/', '', $buyer_phone_raw);
if (strlen($buyer_digits) !== 8) {
  http_response_code(400);
  echo json_encode(["error"=>"Phone must be exactly 8 digits for Singapore."]);
  exit;
}
$buyer_phone = '+65 ' . $buyer_digits; // use this in the booking insert

$buyer_note  = trim($_POST['buyer_note']  ?? ($body['buyer_note']  ?? ''));

if ($buyer_name === '' || $buyer_email === '' || $buyer_phone === '') {
  http_response_code(400); echo json_encode(["error"=>"Name, email, and phone are required."]); exit;
}
if (!filter_var($buyer_email, FILTER_VALIDATE_EMAIL)) {
  http_response_code(400); echo json_encode(["error"=>"Invalid email address."]); exit;
}

$user_id = !empty($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null;


function sendBookingEmail($toEmail, $toName, $bookingRef, $total, $items, $buyer) {
  $mail = new PHPMailer(true);
  try {
    // Mailpit SMTP (no auth / no TLS)
    $mail->isSMTP();
    $mail->Host = '127.0.0.1';
    $mail->Port = 1025;
    $mail->SMTPAuth = false;
    $mail->SMTPSecure = false;

    $mail->setFrom('no-reply@tickitnow.local', 'TickItNow');
    $mail->addAddress($toEmail, $toName ?: $toEmail);

    $mail->isHTML(true);
    $mail->Subject = "Your TickItNow Booking — {$bookingRef}";

    // Build the items table
    $rows = '';
    foreach ($items as $it) {
      $rows .= sprintf(
        '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s × %d</td><td>$%0.2f</td></tr>',
        htmlspecialchars('Show #'.$it['show_id']),
        htmlspecialchars($it['start_at']),
        htmlspecialchars($it['venue_name']),
        htmlspecialchars($it['ticket_class']),
        (int)$it['qty'],
        (float)$it['unit_price']
      );
    }

    $buyerBlock = '';
    if (!empty($buyer)) {
      $buyerBlock = sprintf(
        '<p><strong>Buyer:</strong> %s<br><span style="color:#555">Phone: %s • Email: %s</span>%s</p>',
        htmlspecialchars($buyer['name'] ?? ''),
        htmlspecialchars($buyer['phone'] ?? ''),
        htmlspecialchars($buyer['email'] ?? ''),
        !empty($buyer['note']) ? '<br><span style="color:#555">Note: '.htmlspecialchars($buyer['note']).'</span>' : ''
      );
    }

    $mail->Body = '
      <div style="font-family:system-ui,Segoe UI,Arial,sans-serif;max-width:640px;margin:0 auto;padding:16px">
        <h2>🎟️ TickItNow — Booking Confirmed</h2>
        <p>Reference: <strong>'.htmlspecialchars($bookingRef).'</strong></p>
        '.$buyerBlock.'
        <table width="100%" cellpadding="8" cellspacing="0" border="0" style="border-collapse:collapse">
          <thead>
            <tr style="text-align:left;border-bottom:1px solid #ddd">
              <th>Show</th><th>When</th><th>Venue</th><th>Tickets</th><th>Price</th>
            </tr>
          </thead>
          <tbody>'.$rows.'</tbody>
        </table>
        <p style="margin-top:12px"><strong>Total:</strong> $'.number_format((float)$total, 2).'</p>
        <p style="color:#666">Thank you for booking with TickItNow.</p>
      </div>';

    $mail->AltBody = "Booking {$bookingRef}\nTotal: $".number_format((float)$total, 2)."\n";

    $mail->send();
    return true;
  } catch (Exception $e) {
    // Optional: error_log('Mail error: '.$mail->ErrorInfo);
    return false; // don’t block the booking if mail fails
  }
}




try {
  $pdo->beginTransaction();

  // 1) Load selected preferences for THIS session and lock them
  $in = implode(',', array_fill(0, count($ids), '?'));
  $params = $ids; array_unshift($params, $session_id);

  $stmt = $pdo->prepare("
    SELECT p.*
    FROM preference_items p
    WHERE p.session_id=? AND p.id IN ($in)
    ORDER BY COALESCE(p.rank, 999999), p.created_at ASC, p.id ASC
    FOR UPDATE
  ");
  $stmt->execute($params);
  $prefs = $stmt->fetchAll(PDO::FETCH_ASSOC);

  if (count($prefs) !== count($ids)) {
    throw new Exception("Some selected items were not found in your session.");
  }

  // 2) Lock inventory rows & validate availability
  $lockInv = $pdo->prepare("
    SELECT id, available_qty
    FROM show_inventory
    WHERE show_id=? AND venue_id=? AND start_at=? AND ticket_class=?
    FOR UPDATE
  ");
  $insuff = [];
  foreach ($prefs as $p) {
    $lockInv->execute([(int)$p['show_id'], $p['venue_id'], $p['start_at'], $p['ticket_class']]);
    $inv = $lockInv->fetch(PDO::FETCH_ASSOC);
    $need = (int)$p['qty'];
    $have = $inv ? (int)$inv['available_qty'] : 0;
    if ($have < $need) $insuff[] = ["pref_id"=>(int)$p['id'], "have"=>$have, "need"=>$need];
  }
  if ($insuff) {
    $pdo->rollBack();
    echo json_encode(["ok"=>false,"reason"=>"insufficient","items"=>$insuff]);
    exit;
  }

  // 3) Deduct seats ONCE
  $deduct = $pdo->prepare("
    UPDATE show_inventory
    SET available_qty = available_qty - ?
    WHERE show_id=? AND venue_id=? AND start_at=? AND ticket_class=? AND available_qty >= ?
  ");
  foreach ($prefs as $p) {
    $need = (int)$p['qty'];
    $deduct->execute([$need, (int)$p['show_id'], $p['venue_id'], $p['start_at'], $p['ticket_class'], $need]);
    if ($deduct->rowCount() !== 1) {
      $pdo->rollBack();
      echo json_encode(["ok"=>false,"reason"=>"race_conflict","pref_id"=>(int)$p['id']]); exit;
    }
  }

  // 4) Create booking header
  $total = 0.0;
  foreach ($prefs as $p) { $total += ((float)$p['price']) * ((int)$p['qty']); }

  $bookingRef = 'TKN-' . date('ymd') . '-' . strtoupper(substr(md5(uniqid('', true)), 0, 6));

  $insHdr = $pdo->prepare("
    INSERT INTO bookings
      (user_id, booking_ref, buyer_name, buyer_email, buyer_phone, buyer_note, total_amount)
    VALUES (?,?,?,?,?,?,?)
  ");
  $insHdr->execute([
    $user_id, $bookingRef, $buyer_name, $buyer_email, $buyer_phone, $buyer_note, $total
  ]);
  $bookingId = (int)$pdo->lastInsertId();

  // 5) Create booking lines (no further deduction here)
  $insLine = $pdo->prepare("
    INSERT INTO booking_items (booking_id, tickets, price_each)
    VALUES (?,?,?)
  ");
  foreach ($prefs as $p) {
    $insLine->execute([$bookingId, (int)$p['qty'], (float)$p['price']]);
  }

  // 6) Remove the just-booked preferences for this session
  // Remove ALL preferences for this session (not just selected)
  $del = $pdo->prepare("DELETE FROM preference_items WHERE session_id=?");
  $del->execute([$session_id]);

  $pdo->commit();
  // Send email via Mailpit (non-blocking)
sendBookingEmail(
  $buyer_email,
  $buyer_name,
  $bookingRef,
  $total,
  array_map(function($p){
    return [
      "show_id"      => (int)$p['show_id'],
      "venue_name"   => $p['venue_name'],
      "start_at"     => $p['start_at'],
      "ticket_class" => $p['ticket_class'],
      "qty"          => (int)$p['qty'],
      "unit_price"   => (float)$p['price']
    ];
  }, $prefs),
  ["name"=>$buyer_name, "email"=>$buyer_email, "phone"=>$buyer_phone, "note"=>$buyer_note]
);


  echo json_encode([
    "ok"          => true,
    "booking_id"  => $bookingId,
    "booking_ref" => $bookingRef,
    "total"       => $total,
    "buyer"       => [
      "name"  => $buyer_name,
      "email" => $buyer_email,
      "phone" => $buyer_phone,
      "note"  => $buyer_note
    ],
    "items"       => array_map(function($p){
      $u = (float)$p['price'];  // define $u!
      return [
        "pref_id"      => (int)$p['id'],
        "show_id"      => (int)$p['show_id'],
        "venue_id"     => $p['venue_id'],
        "venue_name"   => $p['venue_name'],
        "start_at"     => $p['start_at'],
        "ticket_class" => $p['ticket_class'],
        "qty"          => (int)$p['qty'],
        "unit_price"   => $u,
        "price"        => $u
      ];
    }, $prefs)
  ]);
  exit;
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  echo json_encode(["error"=>$e->getMessage()]);
  exit;
}