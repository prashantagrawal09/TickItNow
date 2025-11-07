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

  // fetch schedule directly from show_inventory
  $sql = "
    SELECT si.venue_id, si.start_at
    FROM show_inventory si
    WHERE si.show_id = ?
      AND si.start_at >= DATE_ADD(CURDATE(), INTERVAL ? DAY)
      AND si.start_at <  DATE_ADD(CURDATE(), INTERVAL (? + 1) DAY)
    GROUP BY si.venue_id, si.start_at
    ORDER BY si.venue_id, si.start_at
  ";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([$show_id, $startDays, $endDays]);

  $out = [];
  while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $vid = $r['venue_id'];
    $out[] = [
      "show_id"  => $show_id,
      "venue_id" => $vid,
      "venue"    => $VENUE_NAMES[$vid] ?? $vid,
      "start_at" => $r['start_at'],
    ];
  }

  echo json_encode($out);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(["error"=>$e->getMessage()]);
}