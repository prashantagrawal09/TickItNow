<?php
require_once "../db.php";
require_once "_session_boot.php";

header("Content-Type: application/json; charset=utf-8");

/*
POST/JSON:
  select_item_ids[]=<pref_id>  (or "1,2,3")
  buyer_name
  buyer_email
  buyer_phone
  buyer_note   (optional)

Response:
  { ok:true, booking_id: N, booking_ref:"TKN-...", total: 123.45,
    buyer:{name,email,phone,note},
    items:[{show_id,venue_id,venue_name,start_at,ticket_class,qty,unit_price}] }
*/

// ---------- collect selected IDs ----------
$ids = [];
if (isset($_POST['select_item_ids'])) {
  $ids = is_array($_POST['select_item_ids'])
    ? array_map('intval', $_POST['select_item_ids'])
    : array_map('intval', explode(',', $_POST['select_item_ids']));
} else {
  $body = json_decode(file_get_contents('php://input'), true);
  if ($body && isset($body['select_item_ids'])) {
    $v = $body['select_item_ids'];
    $ids = is_array($v) ? array_map('intval', $v) : array_map('intval', explode(',', $v));
  }
}
$ids = array_values(array_filter($ids, fn($x)=>$x>0));
if (!count($ids)) { http_response_code(400); echo json_encode(["error"=>"No items selected"]); exit; }

// ---------- collect buyer fields ----------
$buyer_name  = trim($_POST['buyer_name']  ?? ($body['buyer_name']  ?? ''));
$buyer_email = trim($_POST['buyer_email'] ?? ($body['buyer_email'] ?? ''));
$buyer_phone = trim($_POST['buyer_phone'] ?? ($body['buyer_phone'] ?? ''));
$buyer_note  = trim($_POST['buyer_note']  ?? ($body['buyer_note']  ?? ''));

if ($buyer_name === '' || $buyer_email === '' || $buyer_phone === '') {
  http_response_code(400); echo json_encode(["error"=>"Name, email, and phone are required."]); exit;
}
if (!filter_var($buyer_email, FILTER_VALIDATE_EMAIL)) {
  http_response_code(400); echo json_encode(["error"=>"Invalid email address."]); exit;
}

$user_id = !empty($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null;

try {
  $pdo->beginTransaction();

  // 1️⃣ Load selected preferences for THIS session and lock them
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

  // 2️⃣ Lock inventory rows & validate availability
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

  // 3️⃣ Deduct ONCE (the only deduction)
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
      echo json_encode(["ok"=>false,"reason"=>"race_conflict","pref_id"=>(int)$p['id']]);
      exit;
    }
  }

  // 4️⃣ Create booking header
  $total = 0.0;
  foreach ($prefs as $p) {
    $total += ((float)$p['price']) * ((int)$p['qty']);
  }

  $bookingRef = 'TKN-' . date('ymd') . '-' . strtoupper(substr(md5(uniqid('', true)), 0, 6));

  $insHdr = $pdo->prepare("
    INSERT INTO bookings
      (user_id, booking_ref, buyer_name, buyer_email, buyer_phone, buyer_note, total_amount)
    VALUES (?,?,?,?,?,?,?)
  ");
  $insHdr->execute([
    $user_id,
    $bookingRef,
    $buyer_name,
    $buyer_email,
    $buyer_phone,
    $buyer_note,
    $total
  ]);

  $bookingId = (int)$pdo->lastInsertId();

  // 5️⃣ Create booking lines ONLY (no deduction here)
  $insLine = $pdo->prepare("
    INSERT INTO booking_items (booking_id, tickets, price_each)
    VALUES (?,?,?)
  ");
  foreach ($prefs as $p) {
    $insLine->execute([
      $bookingId,
      (int)$p['qty'],
      (float)$p['price']
    ]);
  }

  $pdo->commit();

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
      return [
        "pref_id"      => (int)$p['id'],
        "show_id"      => (int)$p['show_id'],
        "venue_id"     => $p['venue_id'],
        "venue_name"   => $p['venue_name'],
        "start_at"     => $p['start_at'],
        "ticket_class" => $p['ticket_class'],
        "qty"          => (int)$p['qty'],
        "unit_price"   => (float)$p['price']
      ];
    }, $prefs)
  ]);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  echo json_encode(["error"=>$e->getMessage()]);
}