<?php
require_once "../db.php";
header("Content-Type: application/json; charset=utf-8");

$show_id = isset($_GET['show_id']) ? (int)$_GET['show_id'] : 0;
$date    = $_GET['date'] ?? date('Y-m-d');

$sql = "
  SELECT venue_id, venue_name, start_at, ticket_class, available_qty, price
  FROM show_inventory
  WHERE show_id = :sid
    AND start_at >= CONCAT(:d, ' 00:00:00')
    AND start_at <  CONCAT(:d, ' 23:59:59')
  ORDER BY venue_name ASC, start_at ASC, ticket_class ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([':sid'=>$show_id, ':d'=>$date]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// group by venue
$out = [];
foreach ($rows as $r){
  $vid = $r['venue_id'];
  if (!isset($out[$vid])){
    $out[$vid] = [
      'venue_id' => $vid,
      'venue_name' => $r['venue_name'],
      'slots' => []
    ];
  }
  $t = new DateTime($r['start_at']);
  $label = $t->format('h:i A');
  $out[$vid]['slots'][] = [
    'start_at' => $r['start_at'],
    'time_label' => $label,
    'ticket_class' => $r['ticket_class'],
    'available_qty' => (int)$r['available_qty'],
    'price' => (float)($r['price'] ?? 12.00)
  ];
}
echo json_encode(array_values($out));

