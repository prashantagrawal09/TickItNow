<?php
require_once "../db.php";
header("Content-Type: application/json; charset=utf-8");

try {
  $show_id = isset($_GET['show_id']) ? (int)$_GET['show_id'] : 0;
  if ($show_id <= 0) { echo json_encode([]); exit; }

  // optional day window filters
  $startDays = isset($_GET['start_days']) ? (int)$_GET['start_days'] : 0;
  $endDays   = isset($_GET['end_days'])   ? (int)$_GET['end_days']   : 4;

  // map your venue IDs to readable names
  $VENUE_NAMES = [
    'inox' => 'Orchard Cineplex A',
    'pvr1' => 'Marina Theatre Hall 2',
    'pvr2' => 'Jewel Cinema 5',
    'pvr3' => 'Tampines Stage 1',
  ];

  // preload hall seat counts
  $hallSeatCounts = [];
  $seatStmt = $pdo->query("SELECT hall_id, COUNT(*) AS total_seats FROM seats GROUP BY hall_id");
  while ($row = $seatStmt->fetch(PDO::FETCH_ASSOC)) {
    $hallSeatCounts[(int)$row['hall_id']] = (int)$row['total_seats'];
  }
  $hallNameMap = [];
  $hallStmt = $pdo->query("SELECT hall_id, hall_name FROM halls");
  while ($row = $hallStmt->fetch(PDO::FETCH_ASSOC)) {
    $hallNameMap[strtoupper(trim($row['hall_name']))] = (int)$row['hall_id'];
  }

  $scheduleMap = [];
  $schedStmt = $pdo->prepare("SELECT id, venue, start_at FROM schedules WHERE show_id = ?");
  $schedStmt->execute([$show_id]);
  while ($row = $schedStmt->fetch(PDO::FETCH_ASSOC)) {
    $mapKey = strtoupper(trim($row['venue'])) . '|' . $row['start_at'];
    $scheduleMap[$mapKey] = (int)$row['id'];
  }

  // fetch confirmed bookings per schedule id
  $bookedCounts = [];
  if ($scheduleMap) {
    $ids = array_values($scheduleMap);
    $in = implode(',', array_fill(0, count($ids), '?'));
    $bookStmt = $pdo->prepare("
      SELECT bs.schedule_id, COUNT(*) AS c
      FROM booking_seats bs
      JOIN bookings b ON b.id = bs.booking_id
      WHERE b.status = 'CONFIRMED' AND bs.schedule_id IN ($in)
      GROUP BY bs.schedule_id
    ");
    $bookStmt->execute($ids);
    while ($row = $bookStmt->fetch(PDO::FETCH_ASSOC)) {
      $bookedCounts[(int)$row['schedule_id']] = (int)$row['c'];
    }
  }

  // fetch schedule directly from show_inventory
  $windowEnd = $endDays + 1;
  if ($windowEnd <= $startDays) {
    $windowEnd = $startDays + 1;
  }
  $sql = "
    SELECT si.venue_id, si.start_at, SUM(si.available_qty) AS total_available
    FROM show_inventory si
    WHERE si.show_id = ?
      AND si.start_at >= DATE_ADD(CURDATE(), INTERVAL ? DAY)
      AND si.start_at <  DATE_ADD(CURDATE(), INTERVAL ? DAY)
    GROUP BY si.venue_id, si.start_at
    ORDER BY si.venue_id, si.start_at
  ";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([$show_id, $startDays, $windowEnd]);

  $out = [];
  while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $vid = $r['venue_id'];
    $venueName = $VENUE_NAMES[$vid] ?? $vid;
    $mapKey = strtoupper(trim($venueName)) . '|' . $r['start_at'];
    $scheduleId = $scheduleMap[$mapKey] ?? null;
    $hallKey = strtoupper(trim($venueName));
    $hallId = $hallNameMap[$hallKey] ?? null;
    $seatCount = $hallId !== null && isset($hallSeatCounts[$hallId]) ? $hallSeatCounts[$hallId] : 100;
    $booked = $scheduleId ? ($bookedCounts[$scheduleId] ?? 0) : 0;
    $freeSeats = max(0, $seatCount - $booked);

    $out[] = [
      "show_id"  => $show_id,
      "venue_id" => $vid,
      "venue"    => $venueName,
      "start_at" => $r['start_at'],
      "schedule_id" => $scheduleId,
      "available_qty" => (int)$r['total_available'],
      "free_seats" => $freeSeats
    ];
  }

  echo json_encode($out);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(["error"=>$e->getMessage()]);
}
