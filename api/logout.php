<?php
require_once "_session_boot.php";
$_SESSION['user'] = null;
header("Location: ../account.html?notice=" . urlencode("Logged out"));
exit;