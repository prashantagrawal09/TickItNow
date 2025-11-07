<?php
require_once "../db.php";
// Seed inventory for upcoming schedules (Standard/Premium/VIP = 60/40/20 seats)
header("Content-Type: application/json; charset=utf-8");

try{
  // Pull distinct show/venue/start_at from schedules table
  $rows = $pdo->query("
    SELECT s.show_id, s.venue, s.start_at
    FROM schedules s
    ORDER BY s.start_at ASC
  ")->fetchAll(PDO::FETCH_ASSOC);

  $ins = $pdo->prepare("
    INSERT INTO show_inventory (show_id, venue_id, start_at, ticket_class, available_qty)
    VALUES (?,?,?,?,?)
    ON DUPLICATE KEY UPDATE available_qty=VALUES(available_qty)
  ");

  $count = 0;
  foreach($rows as $r){
    foreach ([['Standard',60],['Premium',40],['VIP',20]] as [$cls,$qty]){
      $ins->execute([(int)$r['show_id'], venueIdFromName($r['venue']), $r['start_at'], $cls, $qty]);
      $count += $ins->rowCount();
    }
  }

  echo json_encode(["ok"=>true, "seeded_rows"=>$count]);
}catch(Throwable $e){
  http_response_code(500);
  echo json_encode(["error"=>$e->getMessage()]);
}

function venueIdFromName($name){
  // Map your display names to the IDs you use on the client
  $map = [
    'Orchard Cineplex A'    => 'inox',
    'Marina Theatre Hall 2' => 'pvr1',
    'Jewel Cinema 5'        => 'pvr2',
    'Tampines Stage 1'      => 'pvr3',
  ];
  return $map[$name] ?? preg_replace('/\W+/','', strtolower($name));
}