<?php
// api/_session_boot.php
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['session_id'])) {
  $_SESSION['session_id'] = bin2hex(random_bytes(16));
}
$session_id = $_SESSION['session_id'];