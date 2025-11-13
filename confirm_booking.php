<?php
session_start();
if (empty($_SESSION['user']['id'])) {
  echo '<!doctype html><html><body><p>You must be logged in to book seats.</p><p><a href="login.html">Go to login</a></p></body></html>';
  exit;
}
$userId = (int)$_SESSION['user']['id'];
$showId = isset($_POST['show_id']) ? (int)$_POST['show_id'] : 0;
$seatIds = isset($_POST['seat_ids']) && is_array($_POST['seat_ids']) ? array_unique(array_map('intval', $_POST['seat_ids'])) : [];
if ($showId <= 0 || count($seatIds) === 0) {
  echo '<!doctype html><html><body><p>Missing showtime or seats.</p><p><a href="shows.html">Back to shows</a></p></body></html>';
  exit;
}
$conn = mysqli_connect('localhost', 'root', '', 'TickItNow');
if (!$conn) {
  die('Database connection failed');
}
mysqli_set_charset($conn, 'utf8mb4');
$showStmt = mysqli_prepare($conn, "SELECT s.id, s.show_id, s.venue, s.start_at, s.price, sh.title, h.hall_id, h.hall_name FROM schedules s JOIN shows sh ON sh.id = s.show_id LEFT JOIN halls h ON h.hall_name = s.venue WHERE s.id = ? LIMIT 1");
mysqli_stmt_bind_param($showStmt, 'i', $showId);
mysqli_stmt_execute($showStmt);
$showResult = mysqli_stmt_get_result($showStmt);
$showInfo = mysqli_fetch_assoc($showResult);
mysqli_free_result($showResult);
mysqli_stmt_close($showStmt);
if (!$showInfo || empty($showInfo['hall_id'])) {
  echo '<!doctype html><html><body><p>Showtime information not available.</p><p><a href="shows.html">Back to shows</a></p></body></html>';
  exit;
}
$hallId = (int)$showInfo['hall_id'];
$seatList = implode(',', array_filter($seatIds));
if ($seatList === '') {
  echo '<!doctype html><html><body><p>No seats selected.</p><p><a href="shows.html">Back to shows</a></p></body></html>';
  exit;
}
$countSql = "SELECT COUNT(*) AS total FROM seats WHERE hall_id = $hallId AND seat_id IN ($seatList)";
$countRes = mysqli_query($conn, $countSql);
$validCount = 0;
if ($countRes) {
  $row = mysqli_fetch_assoc($countRes);
  if ($row) $validCount = (int)$row['total'];
  mysqli_free_result($countRes);
}
if ($validCount !== count($seatIds)) {
  echo '<!doctype html><html><body><p>Invalid seat selection.</p><p><a href="shows.html">Back to shows</a></p></body></html>';
  exit;
}
mysqli_begin_transaction($conn);
$conflictSql = "SELECT bs.seat_id FROM booking_seats bs JOIN bookings b ON b.id = bs.booking_id WHERE bs.schedule_id = $showId AND b.status = 'CONFIRMED' AND bs.seat_id IN ($seatList) FOR UPDATE";
$conflictRes = mysqli_query($conn, $conflictSql);
if ($conflictRes && mysqli_num_rows($conflictRes) > 0) {
  mysqli_rollback($conn);
  mysqli_free_result($conflictRes);
  echo '<!doctype html><html><body><p>Sorry, one or more seats were just booked. Please choose again.</p><p><a href="javascript:history.back()">Go back</a></p></body></html>';
  exit;
}
if ($conflictRes) {
  mysqli_free_result($conflictRes);
}
$price = (float)$showInfo['price'];
$totalAmount = $price * count($seatIds);
$bookingRef = 'SEAT-' . strtoupper(substr(md5(uniqid('', true)), 0, 6));
$now = date('Y-m-d H:i:s');
$userData = $_SESSION['user'];
$buyerName = isset($userData['name']) ? $userData['name'] : 'Seat Guest';
$buyerEmail = isset($userData['email']) ? $userData['email'] : 'guest@example.com';
$buyerPhone = isset($userData['phone']) ? $userData['phone'] : '';
$insertSql = "INSERT INTO bookings (user_id, show_id, booking_ref, buyer_name, buyer_email, buyer_phone, buyer_note, booked_at, total_amount, status) VALUES (?,?,?,?,?,?,?,?,?,?)";
$insertStmt = mysqli_prepare($conn, $insertSql);
$emptyNote = '';
$status = 'CONFIRMED';
mysqli_stmt_bind_param($insertStmt, 'iissssssds', $userId, $showId, $bookingRef, $buyerName, $buyerEmail, $buyerPhone, $emptyNote, $now, $totalAmount, $status);
mysqli_stmt_execute($insertStmt);
mysqli_stmt_close($insertStmt);
$bookingId = mysqli_insert_id($conn);
$itemStmt = mysqli_prepare($conn, "INSERT INTO booking_items (booking_id, tickets, price_each) VALUES (?, ?, ?)");
$ticketCount = count($seatIds);
$priceEach = $price;
mysqli_stmt_bind_param($itemStmt, 'iid', $bookingId, $ticketCount, $priceEach);
mysqli_stmt_execute($itemStmt);
mysqli_stmt_close($itemStmt);
$seatStmt = mysqli_prepare($conn, "INSERT INTO booking_seats (booking_id, seat_id, schedule_id) VALUES (?, ?, ?)");
$seatIdValue = 0;
$scheduleIdValue = $showId;
mysqli_stmt_bind_param($seatStmt, 'iii', $bookingId, $seatIdValue, $scheduleIdValue);
foreach ($seatIds as $sid) {
  $seatIdValue = $sid;
  mysqli_stmt_execute($seatStmt);
}
mysqli_stmt_close($seatStmt);
mysqli_commit($conn);
$labels = [];
$labelSql = "SELECT seat_row, seat_col FROM seats WHERE seat_id IN ($seatList) ORDER BY seat_row, seat_col";
$labelRes = mysqli_query($conn, $labelSql);
while ($row = mysqli_fetch_assoc($labelRes)) {
  $labels[] = $row['seat_row'] . $row['seat_col'];
}
if ($labelRes) {
  mysqli_free_result($labelRes);
}
mysqli_close($conn);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Booking Confirmed • TickItNow</title>
  <link rel="stylesheet" href="assets/site.css">
  <link rel="stylesheet" href="assets/tw.out.css">
</head>
<body>
<header class="header">
  <nav class="nav container">
    <div class="logo">
      <span aria-hidden="true">🎟️</span>
      <span class="brand">TickItNow</span>
    </div>
    <div class="links">
      <a href="index.html">Home</a>
      <a href="shows.html">Browse Shows</a>
      <a href="preferences.html">My Preferences</a>
      <a href="available.html">Available Bookings</a>
    </div>
    <a href="account.html" title="My Account" class="account-icon" style="font-size:24px;">👤</a>
  </nav>
</header>
<main class="container" style="margin-top:24px; margin-bottom:40px;">
  <div class="card">
    <div class="card-body">
      <h2>Booking confirmed</h2>
      <p class="meta">Reference: <?php echo htmlspecialchars($bookingRef, ENT_QUOTES, 'UTF-8'); ?></p>
      <p class="meta">Show: <?php echo htmlspecialchars($showInfo['title'], ENT_QUOTES, 'UTF-8'); ?> • <?php echo htmlspecialchars(date('D, d M Y g:i A', strtotime($showInfo['start_at'])), ENT_QUOTES, 'UTF-8'); ?></p>
      <p class="meta">Hall: <?php echo htmlspecialchars($showInfo['hall_name'], ENT_QUOTES, 'UTF-8'); ?></p>
      <p class="meta">Seats: <?php echo htmlspecialchars(implode(', ', $labels), ENT_QUOTES, 'UTF-8'); ?></p>
      <p class="meta">Total paid: $<?php echo htmlspecialchars(number_format($totalAmount, 2), ENT_QUOTES, 'UTF-8'); ?></p>
      <div style="margin-top:18px; display:flex; gap:10px; flex-wrap:wrap;">
        <a class="btn primary" href="shows.html">Book another show</a>
        <a class="btn" href="receipt.php?id=<?php echo (int)$bookingId; ?>">View receipt</a>
      </div>
    </div>
  </div>
</main>
<footer class="site-footer">
  <div class="container">
    <div class="footer-links">
      <a href="terms.html">Terms &amp; Conditions</a>
      <a href="privacy.html">Privacy Policy</a>
      <a href="contact.html">Contact Us</a>
    </div>
    <p class="copyright">© 2025 TickItNow. All rights reserved.</p>
  </div>
</footer>
</body>
</html>
