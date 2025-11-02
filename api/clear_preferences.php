<?php
require_once "../db.php";
require_once "_session_boot.php";

$pdo->prepare("DELETE FROM preference_items WHERE session_id=?")->execute([$session_id]);
echo json_encode(["ok"=>true]);