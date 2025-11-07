<?php
require_once "../db.php";
require_once "_session_boot.php";
header("Content-Type: application/json; charset=utf-8");

/* Returns current session's preferences with optional inventory info */
$sql = "
  SELECT
    p.id,
    p.rank,
    p.show_id,
    s.title AS show_title,
    p.venue_id,
    p.venue_name,
    p.ticket_class,
    p.qty,
    p.price,
    DATE_FORMAT(p.start_at, '%Y-%m-%dT%H:%i:%s') AS start_at_iso,
    COALESCE(inv.available_qty, 0) AS available_qty,
    CASE
      WHEN inv.available_qty IS NULL THEN 0
      WHEN inv.available_qty >= p.qty THEN 1
      ELSE 0
    END AS is_available
  FROM preference_items p
  LEFT JOIN shows s
    ON s.id = p.show_id
  LEFT JOIN show_inventory inv
    ON inv.show_id = p.show_id
   AND inv.venue_id = p.venue_id
   AND inv.start_at = p.start_at
   AND inv.ticket_class = p.ticket_class
  WHERE p.session_id = ?
  ORDER BY COALESCE(p.rank, 999999), p.created_at ASC, p.id ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$session_id]);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));