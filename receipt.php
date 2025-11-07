<?php
require_once "db.php"; // adjust path if your db.php is in a parent folder
// Usage: receipt.php?ref=BOOKING_REF or ?id=BOOKING_ID

$ref = $_GET['ref'] ?? null;
$id  = isset($_GET['id']) ? (int)$_GET['id'] : null;

if (!$ref && !$id) {
  http_response_code(400);
  echo "<h1>Bad request</h1><p>Provide ?ref=… or ?id=…</p>";
  exit;
}

if ($ref) {
  $stmt = $pdo->prepare("SELECT * FROM bookings WHERE booking_ref = ?");
  $stmt->execute([$ref]);
} else {
  $stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ?");
  $stmt->execute([$id]);
}
$booking = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$booking) {
  http_response_code(404);
  echo "<h1>Not found</h1><p>Booking not found.</p>";
  exit;
}

$items = $pdo->prepare("
  SELECT bi.tickets, bi.price_each
  FROM booking_items bi
  WHERE bi.booking_id = ?
  ORDER BY bi.id ASC
");
$items->execute([$booking['id']]);
$lines = $items->fetchAll(PDO::FETCH_ASSOC);

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Receipt • <?=h($booking['booking_ref'])?></title>
  <link rel="stylesheet" href="assets/styles.css">
  <style>
    .receipt { max-width: 760px; margin: 32px auto; }
    .receipt h1 { margin-bottom: 0; }
    .muted { color: #666; }
  </style>
</head>
<body>
<header class="header">
  <nav class="nav container">
    <div class="logo"><span aria-hidden="true">🎟️</span><span>TickItNow</span></div>
    <div class="links">
      <a href="index.html">Home</a>
      <a href="shows.html">Browse Shows</a>
      <a href="preferences.html">My Preferences</a>
      <a href="account.html">Account</a>
    </div>
  </nav>
</header>

<main class="container receipt">
  <h1>Booking Receipt</h1>
  <p class="muted">Reference: <strong><?=h($booking['booking_ref'])?></strong></p>
  <p class="muted">Booked at: <?=h($booking['booked_at'] ?? $booking['created_at'] ?? '')?></p>

  <div class="card" style="margin-top:12px"><div class="card-body">
    <h2>Buyer</h2>
    <div><strong>Name:</strong> <?=h($booking['buyer_name'])?></div>
    <div><strong>Email:</strong> <?=h($booking['buyer_email'])?></div>
    <div><strong>Phone:</strong> <?=h($booking['buyer_phone'])?></div>
    <?php if (!empty($booking['buyer_note'])): ?>
      <div><strong>Note:</strong> <?=h($booking['buyer_note'])?></div>
    <?php endif; ?>
  </div></div>

  <div class="card" style="margin-top:12px"><div class="card-body">
    <h2>Items</h2>
    <?php if (!$lines): ?>
      <p class="muted">No line items recorded.</p>
    <?php else: ?>
      <table class="table" style="margin-top:10px">
        <thead><tr><th>Qty</th><th>Price each</th><th>Line total</th></tr></thead>
        <tbody>
        <?php
          $sum = 0.0;
          foreach ($lines as $li) {
            $q = (int)$li['tickets'];
            $u = (float)$li['price_each'];
            $lt = $q * $u; $sum += $lt;
            echo "<tr><td>".h($q)."</td><td>$".number_format($u,2)."</td><td>$".number_format($lt,2)."</td></tr>";
          }
        ?>
        </tbody>
      </table>
      <div class="flex space-between" style="margin-top:8px">
        <strong>Total</strong>
        <strong>$<?=number_format((float)$booking['total_amount'] ?: $sum, 2)?></strong>
      </div>
    <?php endif; ?>
  </div></div>

  <div class="flex" style="margin-top:16px">
    <a class="btn primary" href="shows.html">Book More</a>
    <a class="btn ghost" href="account.html">Back to Account</a>
    <button class="btn" onclick="window.print()">Print</button>
  </div>
</main>

<footer class="site-footer">
  <div class="container">
    <p class="copyright">© 2025 TickItNow. All rights reserved.</p>
  </div>
</footer>
</body>
</html>