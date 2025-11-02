<?php
require_once "../db.php";

$show_id = intval($_GET['show_id'] ?? 0);
if(!$show_id){ http_response_code(400); echo json_encode(["error"=>"Missing show_id"]); exit; }

$stmt = $pdo->prepare("SELECT * FROM schedules WHERE show_id=? ORDER BY start_at");
$stmt->execute([$show_id]);
echo json_encode($stmt->fetchAll());
?>