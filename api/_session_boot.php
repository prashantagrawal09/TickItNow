<?php
// start session and send JSON headers
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
header("Content-Type: application/json; charset=utf-8");
$session_id = session_id();