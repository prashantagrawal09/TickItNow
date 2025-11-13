DROP TABLE IF EXISTS seats;

CREATE TABLE IF NOT EXISTS halls (
  hall_id INT AUTO_INCREMENT PRIMARY KEY,
  hall_name VARCHAR(100) NOT NULL
);

INSERT IGNORE INTO halls (hall_id, hall_name) VALUES
  (1, 'Orchard Cineplex A'),
  (2, 'Marina Theatre Hall 2'),
  (3, 'Jewel Cinema 5'),
  (4, 'Tampines Stage 1');

CREATE TABLE seats (
  seat_id INT AUTO_INCREMENT PRIMARY KEY,
  hall_id INT NOT NULL,
  seat_row CHAR(1) NOT NULL,
  seat_col INT NOT NULL
);

INSERT INTO seats (hall_id, seat_row, seat_col)
SELECT h.hall_id, r.letter, c.num
FROM halls h
CROSS JOIN (
  SELECT 'A' AS letter UNION ALL SELECT 'B' UNION ALL SELECT 'C' UNION ALL SELECT 'D' UNION ALL SELECT 'E'
  UNION ALL SELECT 'F' UNION ALL SELECT 'G' UNION ALL SELECT 'H' UNION ALL SELECT 'I' UNION ALL SELECT 'J'
) AS r
CROSS JOIN (
  SELECT 1 AS num UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5
  UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9 UNION ALL SELECT 10
) AS c;

ALTER TABLE bookings
  ADD COLUMN show_id INT NULL AFTER user_id,
  ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'CONFIRMED' AFTER booked_at,
  ADD KEY idx_bookings_show (show_id);

ALTER TABLE bookings
  ADD CONSTRAINT fk_bookings_schedule FOREIGN KEY (show_id) REFERENCES schedules(id);

CREATE TABLE IF NOT EXISTS booking_seats (
  booking_id INT NOT NULL,
  seat_id INT NOT NULL,
  schedule_id INT NOT NULL DEFAULT 0,
  PRIMARY KEY (booking_id, seat_id),
  KEY idx_booking_seats_schedule (schedule_id),
  CONSTRAINT fk_booking_seats_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
  CONSTRAINT fk_booking_seats_seat FOREIGN KEY (seat_id) REFERENCES seats(seat_id),
  CONSTRAINT fk_booking_seats_schedule FOREIGN KEY (schedule_id) REFERENCES schedules(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
