<?php
require_once "../db.php";
require_once "_session_boot.php";
header("Content-Type: application/json; charset=utf-8");
// stop caches returning stale history after login/logout
header("Cache-Control: no-store, no-cache, must-revalidate");

try {
  if (empty($_SESSION['user'])) {
    echo json_encode(["authenticated" => false, "orders" => []]);
    exit;
  }

  $u     = $_SESSION['user'];
  $uid   = isset($u['id'])    ? (int)$u['id'] : null;
  $email = isset($u['email']) ? trim($u['email']) : '';

  // 1) Fetch bookings with computed totals.
  //    We LEFT JOIN items so SUM(...) works even if there are no items (NULL -> fallback to total_amount).
  $sql = "
    SELECT
      b.id          AS booking_id,
      b.booking_ref AS booking_ref,
      b.booked_at   AS booked_at,
      b.buyer_name,
      b.buyer_email,
      b.buyer_phone,
      COALESCE(SUM(i.tickets * i.price_each), b.total_amount) AS total
    FROM bookings b
    LEFT JOIN booking_items i ON i.booking_id = b.id
    WHERE
      ( (? IS NOT NULL) AND b.user_id = ? )
      OR ( b.buyer_email = ? )
    GROUP BY b.id
    ORDER BY b.booked_at DESC
    LIMIT 50
  ";
  $stmt = $pdo->prepare($sql);
  // first ? is used by (? IS NOT NULL), then the same $uid for b.user_id, then email
  $stmt->execute([$uid, $uid, $email]);
  $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

  if (!$orders) {
    echo json_encode(["authenticated" => true, "orders" => []]);
    exit;
  }

  // 2) Fetch line items and attach to each order.
  $ids = array_column($orders, 'booking_id');
  $in  = implode(',', array_fill(0, count($ids), '?'));

  $it = $pdo->prepare("
    SELECT booking_id, tickets, price_each
    FROM booking_items
    WHERE booking_id IN ($in)
    ORDER BY booking_id ASC, id ASC
  ");
  $it->execute($ids);

  $itemsByBooking = [];
  foreach ($it->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $bid = (int)$row['booking_id'];
    $itemsByBooking[$bid][] = [
      "tickets"    => (int)$row['tickets'],
      "price_each" => (float)$row['price_each'],
    ];
  }

  foreach ($orders as &$o) {
    $bid = (int)$o['booking_id'];
    // cast a couple of fields so the frontend gets numbers, not strings
    $o['total']      = (float)$o['total'];
    $o['booking_id'] = (int)$o['booking_id'];
    $o['items']      = $itemsByBooking[$bid] ?? [];
  }

  echo json_encode(["authenticated" => true, "orders" => $orders]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(["error" => $e->getMessage()]);
}