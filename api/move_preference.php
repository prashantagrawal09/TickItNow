<?php
require_once "../db.php";
require_once "_session_boot.php";

$id = isset($_POST["id"]) ? (int)$_POST["id"] : 0;
$dir = $_POST["dir"] ?? "";
if(!$id || !in_array($dir,["up","down"],true)){
  http_response_code(400); echo json_encode(["error"=>"Bad params"]); exit;
}

// fetch current item
$cur = $pdo->prepare("SELECT id, rank FROM preference_items WHERE id=? AND session_id=?");
$cur->execute([$id,$session_id]);
$row = $cur->fetch();
if(!$row){ http_response_code(404); echo json_encode(["error"=>"Not found"]); exit; }

$myRank = (int)$row["rank"];
if($myRank === 0){ // normalize ranks if needed
  // re-seed ranks
  $rows = $pdo->prepare("SELECT id FROM preference_items WHERE session_id=? ORDER BY created_at ASC, id ASC");
  $rows->execute([$session_id]);
  $r = 1;
  while($x = $rows->fetch()){ $pdo->prepare("UPDATE preference_items SET rank=? WHERE id=?")->execute([$r++, $x["id"]]); }
}

// neighbor
if($dir === "up"){
  $nbr = $pdo->prepare("SELECT id, rank FROM preference_items WHERE session_id=? AND rank < ? ORDER BY rank DESC LIMIT 1");
  $nbr->execute([$session_id, $myRank]);
}else{
  $nbr = $pdo->prepare("SELECT id, rank FROM preference_items WHERE session_id=? AND rank > ? ORDER BY rank ASC LIMIT 1");
  $nbr->execute([$session_id, $myRank]);
}
$n = $nbr->fetch();
if($n){
  $pdo->beginTransaction();
  $pdo->prepare("UPDATE preference_items SET rank=? WHERE id=?")->execute([(int)$n["rank"], $id]);
  $pdo->prepare("UPDATE preference_items SET rank=? WHERE id=?")->execute([$myRank, (int)$n["id"]]);
  $pdo->commit();
}

require "list_preferences.php";