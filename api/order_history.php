<?php
require_once "../db.php";
require_once "_session_boot.php";
header("Content-Type: application/json; charset=utf-8");

if (empty($_SESSION['user'])) {
  echo json_encode(["authenticated"=>false, "orders"=>[]]);
  exit;
}

$u = $_SESSION['user'];

// ---- main bookings ----
$sql = "
  SELECT
    b.id AS booking_id,
    b.booked_at,
    b.buyer_name,
    b.buyer_email,
    b.buyer_phone,
    b.total_amount AS total
  FROM bookings b
  WHERE b.buyer_email = ?
  ORDER BY b.booked_at DESC
  LIMIT 50
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$u['email']]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$orders) {
  echo json_encode(["authenticated"=>true, "orders"=>[]]);
  exit;
}

// ---- collect items for those bookings ----
$ids = array_column($orders, 'booking_id');
$in  = implode(',', array_fill(0, count($ids), '?'));

$detail = $pdo->prepare("
  SELECT booking_id, show_id, venue_name, start_at, ticket_class, qty, unit_price
  FROM booking_items
  WHERE booking_id IN ($in)
  ORDER BY start_at ASC, id ASC
");
$detail->execute($ids);
$items = $detail->fetchAll(PDO::FETCH_ASSOC);

// ---- group items under their bookings ----
$by = [];
foreach ($items as $it) {
  $by[$it['booking_id']][] = $it;
}
foreach ($orders as &$o) {
  $o['items'] = $by[$o['booking_id']] ?? [];
}

// ---- output ----
echo json_encode(["authenticated"=>true, "orders"=>$orders]);