<?php
// api/add_preference.php
require_once "../db.php";
require_once "_session_boot.php";
header("Content-Type: application/json; charset=utf-8");

$payload = json_decode(file_get_contents('php://input'), true);
if (!$payload) { http_response_code(400); echo json_encode(["error"=>"Invalid JSON"]); exit; }

$show_id      = (int)($payload['show_id'] ?? 0);
$venue_id     = trim($payload['venue_id'] ?? '');
$venue_name   = trim($payload['venue_name'] ?? '');
$start_at     = trim($payload['start_at'] ?? '');            // "YYYY-MM-DD HH:MM:SS" local
$ticket_class = trim($payload['ticket_class'] ?? 'Standard');
$qty          = (int)($payload['qty'] ?? 1);
$price        = (float)($payload['price'] ?? 0);

if (!$show_id || !$venue_id || !$venue_name || !$start_at) {
  http_response_code(400); echo json_encode(["error"=>"Missing fields"]); exit;
}

try{
  // compute next rank for this session
  $rank = $pdo->prepare("SELECT COALESCE(MAX(rank),0)+1 AS r FROM preference_items WHERE session_id=?");
  $rank->execute([$session_id]);
  $nextRank = (int)$rank->fetchColumn();

  $ins = $pdo->prepare("
    INSERT INTO preference_items
      (session_id, rank, show_id, venue_id, venue_name, start_at, ticket_class, qty, price, created_at)
    VALUES (?,?,?,?,?,?,?,?,?, NOW())
  ");
  $ins->execute([$session_id, $nextRank, $show_id, $venue_id, $venue_name, $start_at, $ticket_class, $qty, $price]);

  echo json_encode(["ok"=>true, "id"=>$pdo->lastInsertId(), "rank"=>$nextRank]);
}catch(Throwable $e){
  http_response_code(500); echo json_encode(["error"=>$e->getMessage()]);
}