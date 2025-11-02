<?php
require_once "../db.php";
require_once "_session_boot.php";

$id = $_POST["id"] ?? null;
if(!$id){ http_response_code(400); echo json_encode(["error"=>"Missing id"]); exit; }

$del = $pdo->prepare("DELETE FROM preference_items WHERE id=? AND session_id=?");
$del->execute([(int)$id, $session_id]);

require "list_preferences.php";