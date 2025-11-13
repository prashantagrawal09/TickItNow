<?php
session_start();
$showId = isset($_GET['show_id']) ? (int)$_GET['show_id'] : 0;
$requestedQty = isset($_GET['qty']) ? max(0, (int)$_GET['qty']) : 0;
$venueCodeParam = isset($_GET['venue_id']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['venue_id']) : '';
$venueNameParam = isset($_GET['venue_name']) ? trim($_GET['venue_name']) : '';
$conn = mysqli_connect('localhost', 'root', '', 'TickItNow');
if (!$conn) {
  die('Database connection failed');
}
mysqli_set_charset($conn, 'utf8mb4');
$showInfo = null;
$hallId = 0;
$hallName = '';
$error = '';
if ($showId > 0) {
  $stmt = mysqli_prepare($conn, "SELECT s.id, s.show_id, s.venue, s.start_at, s.price, sh.title, h.hall_id, h.hall_name FROM schedules s JOIN shows sh ON sh.id = s.show_id LEFT JOIN halls h ON h.hall_name = s.venue WHERE s.id = ? LIMIT 1");
  mysqli_stmt_bind_param($stmt, 'i', $showId);
  mysqli_stmt_execute($stmt);
  $result = mysqli_stmt_get_result($stmt);
  $showInfo = mysqli_fetch_assoc($result);
  mysqli_free_result($result);
  mysqli_stmt_close($stmt);
  if ($showInfo) {
    $hallId = (int)$showInfo['hall_id'];
    $hallName = $showInfo['hall_name'];
    if ($hallId === 0) {
      $error = 'Hall information not found for this show.';
    }
  } else {
    $error = 'Showtime not found.';
  }
} else {
  $error = 'Invalid showtime selected.';
}
$venueCodeForPref = $venueCodeParam !== '' ? $venueCodeParam : ($hallName ? strtolower(preg_replace('/\s+/', '', $hallName)) : '');
$venueNameForPref = $venueNameParam !== '' ? $venueNameParam : $hallName;
$seatMap = [];
$bookedSeats = [];
if (!$error && $hallId > 0) {
  $seatSql = "SELECT seat_id, seat_row, seat_col FROM seats WHERE hall_id = ? ORDER BY seat_row, seat_col";
  $stmt = mysqli_prepare($conn, $seatSql);
  mysqli_stmt_bind_param($stmt, 'i', $hallId);
  mysqli_stmt_execute($stmt);
  $res = mysqli_stmt_get_result($stmt);
  while ($row = mysqli_fetch_assoc($res)) {
    $key = $row['seat_row'] . '-' . $row['seat_col'];
    $seatMap[$key] = $row;
  }
  mysqli_free_result($res);
  mysqli_stmt_close($stmt);

  $bookedSql = "SELECT bs.seat_id FROM booking_seats bs JOIN bookings b ON b.id = bs.booking_id WHERE bs.schedule_id = ? AND b.status = 'CONFIRMED'";
  $stmt = mysqli_prepare($conn, $bookedSql);
  mysqli_stmt_bind_param($stmt, 'i', $showId);
  mysqli_stmt_execute($stmt);
  $res = mysqli_stmt_get_result($stmt);
  while ($row = mysqli_fetch_assoc($res)) {
    $bookedSeats[(int)$row['seat_id']] = true;
  }
  mysqli_free_result($res);
  mysqli_stmt_close($stmt);
}
$rows = ['A','B','C','D','E','F','G','H','I','J'];
$cols = range(1, 10);
$totalSeats = count($seatMap);
$bookedCount = count($bookedSeats);
$freeSeats = max(0, $totalSeats - $bookedCount);
$maxSelectable = $freeSeats;
$exactSeatsRequired = 0;
$availabilityNote = '';
$insufficientQty = false;
if (!$error && $hallId > 0) {
  if ($freeSeats <= 0) {
    $availabilityNote = 'No seats available for this showtime.';
  } elseif ($requestedQty > 0) {
    if ($requestedQty <= $freeSeats) {
      $exactSeatsRequired = $requestedQty;
      $availabilityNote = 'Please select exactly '.$requestedQty.' seat'.($requestedQty>1?'s':'').'.';
    } else {
      $insufficientQty = true;
      $availabilityNote = 'Only '.$freeSeats.' seat'.($freeSeats===1?'':'s').' left. Please go back and choose a lower quantity.';
      $maxSelectable = 0;
    }
  } else {
    $availabilityNote = 'Seats available: '.$freeSeats.'.';
  }
}
mysqli_close($conn);
function h($value) {
  return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Seat Selection • TickItNow</title>
  <link rel="stylesheet" href="assets/site.css">
  <link rel="stylesheet" href="assets/tw.out.css">
  <style>
    .seat-grid { display:grid; grid-template-columns:repeat(10, minmax(28px,1fr)); gap:6px; }
    .seat-cell { text-align:center; font-size:12px; }
    .seat-free { background:#d1fae5; border:1px solid #10b981; border-radius:6px; padding:8px 0; cursor:pointer; display:block; color:#065f46; }
    .seat-booked { background:#fee2e2; border:1px solid #f87171; border-radius:6px; padding:8px 0; color:#991b1b; font-weight:bold; }
    .seat-free input { margin-bottom:4px; }
    .seat-row-label { font-weight:bold; margin:12px 0 4px; }
    .screen-indicator {
      text-align:center;
      width:80%;
      margin:0 auto 18px;
      padding:6px 0;
      border:1px solid #cbd5f5;
      border-radius:12px;
      color:#94a3b8;
      letter-spacing:0.25em;
      text-transform:uppercase;
      background:linear-gradient(90deg,#f1f5f9,#e2e8f0,#f1f5f9);
    }
  </style>
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
      <?php if ($error): ?>
        <h2>Seat Selection</h2>
        <p class="meta"><?php echo h($error); ?></p>
      <?php else: ?>
        <h2>Select seats for <?php echo h($showInfo['title']); ?></h2>
        <p class="meta">Hall: <?php echo h($hallName); ?> • <?php echo h(date('D, d M Y g:i A', strtotime($showInfo['start_at']))); ?></p>
        <p class="meta">Price per seat: $<?php echo h(number_format((float)$showInfo['price'], 2)); ?></p>
        <p id="seatCount" class="meta" style="margin-top:10px;">Selected seats: 0</p>
        <?php if ($availabilityNote): ?>
        <p id="seatLimitMsg" class="meta" style="margin-top:4px;<?php echo $freeSeats<=0?'color:#f87171;':''; ?>">
          <?php echo h($availabilityNote); ?>
        </p>
        <?php else: ?>
        <p id="seatLimitMsg" class="meta" style="margin-top:4px; display:none;"></p>
        <?php endif; ?>
        <form id="seat-form" method="post" action="#" style="margin-top:18px;">
          <input type="hidden" name="show_id" value="<?php echo h($showId); ?>">
          <div class="screen-indicator">Screen</div>
          <div class="seat-grid">
            <?php foreach ($rows as $row): ?>
              <?php foreach ($cols as $col): ?>
                <?php
                  $key = $row . '-' . $col;
                  if (!isset($seatMap[$key])) {
                    echo '<div class="seat-cell">&nbsp;</div>';
                    continue;
                  }
                  $seat = $seatMap[$key];
                  $seatId = (int)$seat['seat_id'];
                  $label = $row . $col;
                  $booked = isset($bookedSeats[$seatId]);
                ?>
                <div class="seat-cell">
                  <?php if ($booked): ?>
                    <div class="seat-booked" title="Booked"><?php echo h($label); ?></div>
                  <?php else: ?>
                    <label class="seat-free">
                      <input type="checkbox" name="seat_ids[]" value="<?php echo h($seatId); ?>" data-label="<?php echo h($label); ?>">
                      <div><?php echo h($label); ?></div>
                    </label>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            <?php endforeach; ?>
          </div>
          <div style="margin-top:20px; display:flex; gap:12px; flex-wrap:wrap;">
            <button type="submit" id="confirm-btn" class="btn primary" <?php echo ($freeSeats <= 0 || $insufficientQty) ? 'disabled' : ''; ?>>Confirm seats</button>
            <a class="btn" href="show.html?id=<?php echo h($showInfo['show_id']); ?>">Back to show</a>
          </div>
        </form>
      <?php endif; ?>
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
<script>
(function(){
  var form = document.getElementById('seat-form');
  if(!form) return;
  var counter = document.getElementById('seatCount');
  var confirmBtn = document.getElementById('confirm-btn');
  var maxSelectable = <?php echo (int)$maxSelectable; ?>;
  var exactSeats = <?php echo (int)$exactSeatsRequired; ?>;
  var freeSeats = <?php echo (int)$freeSeats; ?>;
  var insufficient = <?php echo $insufficientQty ? 'true' : 'false'; ?>;
  function seatHash(list) {
    var str = list.join('-');
    var hash = 0;
    for (var i = 0; i < str.length; i++) {
      hash = (hash * 31 + str.charCodeAt(i)) >>> 0;
    }
    return hash.toString(16);
  }

  var showPayload = {
    show_id: <?php echo (int)$showInfo['show_id']; ?>,
    schedule_id: <?php echo (int)$showId; ?>,
    venue_id: <?php echo json_encode($venueCodeForPref); ?>,
    venue_name: <?php echo json_encode($venueNameForPref); ?>,
    start_at: <?php echo json_encode($showInfo['start_at']); ?>,
    price: <?php echo json_encode((float)$showInfo['price']); ?>
  };

  function updateCount(){
    var boxes = document.querySelectorAll('input[name="seat_ids[]"]:checked');
    counter.textContent = 'Selected seats: ' + boxes.length;
  }
  function onSeatChange(e){
    if ((insufficient || maxSelectable === 0) && e.target.checked) {
      e.target.checked = false;
      return;
    }
    if (maxSelectable > 0 && e.target.checked) {
      var selected = document.querySelectorAll('input[name="seat_ids[]"]:checked');
      if (selected.length > maxSelectable) {
        e.target.checked = false;
        alert('Only ' + maxSelectable + ' seat' + (maxSelectable>1?'s':'') + ' available.');
        return;
      }
    }
    updateCount();
  }
  var boxes = document.querySelectorAll('input[name="seat_ids[]"]');
  for(var i=0;i<boxes.length;i++){
    boxes[i].addEventListener('change', onSeatChange);
    if (insufficient || freeSeats <= 0) {
      boxes[i].disabled = true;
    }
  }
  updateCount();
  form.addEventListener('submit', function(e){
    e.preventDefault();
    if (insufficient || maxSelectable === 0) {
      alert('Not enough seats remain for the quantity you chose. Please go back and update the number.');
      return;
    }
    var selectedInputs = document.querySelectorAll('input[name="seat_ids[]"]:checked');
    var selected = selectedInputs.length;
    if(selected === 0){
      alert('Please select at least one seat.');
      return;
    }
    if (exactSeats > 0 && selected !== exactSeats) {
      alert('Please select exactly ' + exactSeats + ' seat' + (exactSeats>1?'s':'') + '.');
      return;
    }
    if (maxSelectable > 0 && selected > maxSelectable) {
      alert('Only ' + maxSelectable + ' seat' + (maxSelectable>1?'s':'') + ' available.');
      return;
    }
    var seatIds = Array.from(selectedInputs).map(function(box){
      return parseInt(box.value, 10);
    }).filter(function(num){ return Number.isFinite(num) && num > 0; });
    var seatLabels = Array.from(selectedInputs).map(function(box){
      return box.getAttribute('data-label') || ('Seat #' + box.value);
    });
    var seatString = seatLabels.join(', ');
    if (seatString.length > 200) seatString = seatString.slice(0, 197) + '...';
    var payload = {
      show_id: showPayload.show_id,
      schedule_id: showPayload.schedule_id,
      venue_id: showPayload.venue_id,
      venue_name: showPayload.venue_name || '',
      start_at: showPayload.start_at,
      ticket_class: 'SeatSel-' + seatHash(seatLabels),
      qty: selected,
      price: showPayload.price,
      seat_ids: seatIds,
      seat_labels: seatString
    };

    if (confirmBtn) {
      confirmBtn.disabled = true;
      confirmBtn.textContent = 'Saving...';
    }
    fetch('api/add_preference.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
    .then(function(r){ return r.json().then(function(data){ return { ok:r.ok, data:data }; }); })
    .then(function(res){
      if (!res.ok || (res.data && res.data.error)) {
        throw new Error(res.data && res.data.error ? res.data.error : 'Unable to save preference.');
      }
      window.location.href = 'preferences.html';
    })
    .catch(function(err){
      alert(err.message || 'Unable to save preference.');
      if (confirmBtn) {
        confirmBtn.disabled = false;
        confirmBtn.textContent = 'Confirm seats';
      }
    });
  });
})();
</script>
</body>
</html>
