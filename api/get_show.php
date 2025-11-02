<?php
require_once "../db.php";

$id = intval($_GET['id'] ?? 0);
if(!$id){ http_response_code(400); echo json_encode(["error"=>"Missing id"]); exit; }

$stmt = $pdo->prepare("SELECT * FROM shows WHERE id=?");
$stmt->execute([$id]);
$show = $stmt->fetch();

if(!$show){ http_response_code(404); echo json_encode(["error"=>"Not found"]); exit; }

echo json_encode($show);
?>