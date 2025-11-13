<?php
require_once "../db.php";
require_once "_session_boot.php";

$sql = "
  SELECT p.id,
         p.show_id,
         p.schedule_id,
         s.title AS show_title,
         p.venue_id,
         p.venue_name,
         p.ticket_class,
         p.seat_ids,
         p.seat_labels,
         p.qty,
         p.price,
         -- ISO 8601 without Z so browsers treat it as LOCAL when we append 'T'
         DATE_FORMAT(p.start_at, '%Y-%m-%dT%H:%i:%s') AS start_at_iso,
         p.rank,
         p.created_at
  FROM preference_items p
  LEFT JOIN shows s ON s.id = p.show_id
  WHERE p.session_id = ?
  ORDER BY
    COALESCE(p.rank, 999999),  -- items without rank go to bottom
    p.created_at ASC, p.id ASC
";
$q = $pdo->prepare($sql);
$q->execute([$session_id]);
echo json_encode($q->fetchAll());
