<?php
// api/add_preference.php
require_once "../db.php";
require_once "_session_boot.php";
header("Content-Type: application/json; charset=utf-8");

$payload = json_decode(file_get_contents('php://input'), true);
if (!$payload) { http_response_code(400); echo json_encode(["error"=>"Invalid JSON"]); exit; }

$show_id      = (int)($payload['show_id'] ?? 0);
$schedule_id  = (int)($payload['schedule_id'] ?? 0);
$venue_id     = trim($payload['venue_id'] ?? '');
$venue_name   = trim($payload['venue_name'] ?? '');
$start_at     = trim($payload['start_at'] ?? '');            // "YYYY-MM-DD HH:MM:SS" local
$ticket_class = trim($payload['ticket_class'] ?? 'Standard');
$qty          = (int)($payload['qty'] ?? 1);
$price        = (float)($payload['price'] ?? 0);
$seatIdsInput = $payload['seat_ids'] ?? [];
$seatLabels   = trim($payload['seat_labels'] ?? '');
$seatIds = [];
if (is_string($seatIdsInput)) {
  $seatIds = array_values(array_filter(array_map('intval', explode(',', $seatIdsInput)), fn($x)=>$x>0));
} elseif (is_array($seatIdsInput)) {
  $seatIds = array_values(array_filter(array_map('intval', $seatIdsInput), fn($x)=>$x>0));
}
$seatIdsCsv = $seatIds ? implode(',', $seatIds) : null;

if (
  !$show_id || !$venue_id || !$venue_name || !$start_at ||
  ($seatIds && $schedule_id <= 0)
) {
  http_response_code(400); echo json_encode(["error"=>"Missing fields"]); exit;
}

try{
  // compute next rank for this session
  $rank = $pdo->prepare("SELECT COALESCE(MAX(rank),0)+1 AS r FROM preference_items WHERE session_id=?");
  $rank->execute([$session_id]);
  $nextRank = (int)$rank->fetchColumn();

  $ins = $pdo->prepare("
    INSERT INTO preference_items
      (session_id, rank, show_id, schedule_id, venue_id, venue_name, start_at, ticket_class, seat_ids, seat_labels, qty, price, created_at)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?, NOW())
  ");
  $ins->execute([
    $session_id,
    $nextRank,
    $show_id,
    $schedule_id,
    $venue_id,
    $venue_name,
    $start_at,
    $ticket_class,
    $seatIdsCsv,
    $seatLabels,
    $qty,
    $price
  ]);

  echo json_encode(["ok"=>true, "id"=>$pdo->lastInsertId(), "rank"=>$nextRank]);
}catch(PDOException $e){
  if ($e->getCode() === '23000') {
    if ($schedule_id > 0) {
      $upd = $pdo->prepare("
        UPDATE preference_items
        SET venue_id = ?, venue_name = ?, schedule_id = ?, seat_ids = ?, seat_labels = ?, qty = ?, price = ?, created_at = NOW()
        WHERE session_id = ? AND schedule_id = ? AND ticket_class = ?
      ");
      $upd->execute([$venue_id, $venue_name, $schedule_id, $seatIdsCsv, $seatLabels, $qty, $price, $session_id, $schedule_id, $ticket_class]);
    } else {
      $upd = $pdo->prepare("
        UPDATE preference_items
        SET venue_id = ?, venue_name = ?, seat_ids = ?, seat_labels = ?, qty = ?, price = ?, created_at = NOW()
        WHERE session_id = ? AND show_id = ? AND start_at = ? AND ticket_class = ?
      ");
      $upd->execute([$venue_id, $venue_name, $seatIdsCsv, $seatLabels, $qty, $price, $session_id, $show_id, $start_at, $ticket_class]);
    }
    echo json_encode(["ok"=>true, "updated"=>true]);
  } else {
    http_response_code(500);
    echo json_encode(["error"=>$e->getMessage()]);
  }
}catch(Throwable $e){
  http_response_code(500); echo json_encode(["error"=>$e->getMessage()]);
}
