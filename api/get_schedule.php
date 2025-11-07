<?php
require_once "../db.php";
header("Content-Type: application/json; charset=utf-8");

$show_id = isset($_GET['show_id']) ? (int)$_GET['show_id'] : 0;
if(!$show_id){ http_response_code(400); echo json_encode(["error"=>"Missing show_id"]); exit; }

/* Return all future schedule rows for this show (adjust as you like) */
$stmt = $pdo->prepare("
  SELECT show_id, venue, start_at, price
  FROM schedules
  WHERE show_id = ? AND start_at >= NOW()
  ORDER BY start_at ASC
");
$stmt->execute([$show_id]);
echo json_encode($stmt->fetchAll());