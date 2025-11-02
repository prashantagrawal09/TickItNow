<?php
require_once "../db.php";
require_once "_session_boot.php";

$body = json_decode(file_get_contents("php://input"), true);
if (!$body) { http_response_code(400); echo json_encode(["error"=>"Bad JSON"]); exit; }

$required = ["show_id","venue_id","venue_name","start_at","ticket_class","qty","price"];
foreach($required as $k){
  if(!isset($body[$k]) || $body[$k]===""){ http_response_code(400); echo json_encode(["error"=>"Missing $k"]); exit; }
}

try{
  // choose rank = max(rank)+1 for this session
  $rankStmt = $pdo->prepare("SELECT COALESCE(MAX(rank),0)+1 AS next_rank FROM preference_items WHERE session_id=?");
  $rankStmt->execute([$session_id]);
  $nextRank = (int)$rankStmt->fetchColumn();

  // insert or update
  $stmt = $pdo->prepare("
    INSERT INTO preference_items
      (session_id, user_id, show_id, venue_id, venue_name, start_at, ticket_class, qty, price, rank)
    VALUES
      (?,?,?,?,?,?,?,?,?,?)
    ON DUPLICATE KEY UPDATE
      qty=VALUES(qty),
      price=VALUES(price),
      ticket_class=VALUES(ticket_class)
  ");
  $stmt->execute([
    $session_id, null,
    (int)$body["show_id"],
    substr($body["venue_id"],0,32),
    substr($body["venue_name"],0,160),
    $body["start_at"],       // "YYYY-MM-DD HH:MM:SS"
    substr($body["ticket_class"],0,32),
    (int)$body["qty"],
    (float)$body["price"],
    $nextRank
  ]);

  require "list_preferences.php"; // return the updated list
} catch (Throwable $e){
  http_response_code(500);
  echo json_encode(["error"=>$e->getMessage()]);
}