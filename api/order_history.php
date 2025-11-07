<?php
require_once "../db.php";
require_once "_session_boot.php";
header("Content-Type: application/json; charset=utf-8");

try {
  // Must be logged in
  if (empty($_SESSION['user'])) {
    echo json_encode(["authenticated"=>false, "orders"=>[]]);
    exit;
  }

  $u = $_SESSION['user'];
  $uid   = isset($u['id'])    ? (int)$u['id'] : null;
  $email = isset($u['email']) ? trim($u['email']) : '';

  // Fetch bookings for this user:
  // - prefer user_id match when present
  // - also include email-based bookings (guest checkout)
  $sql = "
    SELECT
      b.id           AS booking_id,
      b.booking_ref  AS booking_ref,
      COALESCE(b.booked_at, b.created_at) AS booked_at,
      b.buyer_name,
      b.buyer_email,
      b.buyer_phone,
      b.total_amount AS total
    FROM bookings b
    WHERE
      ( ? IS NOT NULL AND b.user_id = ? )
      OR ( b.buyer_email = ? )
    ORDER BY COALESCE(b.booked_at, b.created_at) DESC
    LIMIT 50
  ";
  $stmt = $pdo->prepare($sql);
  // Note the first placeholder is for the IS NOT NULL guard (same value twice)
  $stmt->execute([$uid, $uid, $email]);
  $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

  if (!$orders) {
    echo json_encode(["authenticated"=>true, "orders"=>[]]);
    exit;
  }

  // Load line items only if we have any booking ids
  $ids = array_column($orders, 'booking_id');
  $itemsByBooking = [];

  if (count($ids) > 0) {
    $in  = implode(',', array_fill(0, count($ids), '?'));
    $it = $pdo->prepare("
      SELECT booking_id, tickets, price_each
      FROM booking_items
      WHERE booking_id IN ($in)
      ORDER BY booking_id ASC, id ASC
    ");
    $it->execute($ids);
    foreach ($it->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $bid = (int)$row['booking_id'];
      if (!isset($itemsByBooking[$bid])) $itemsByBooking[$bid] = [];
      $itemsByBooking[$bid][] = [
        "tickets"    => (int)$row['tickets'],
        "price_each" => (float)$row['price_each'],
      ];
    }
  }

  // Attach items to each order (empty array if none)
  foreach ($orders as &$o) {
    $bid = (int)$o['booking_id'];
    $o['items'] = $itemsByBooking[$bid] ?? [];
  }

  echo json_encode(["authenticated"=>true, "orders"=>$orders]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(["error"=>$e->getMessage()]);
}