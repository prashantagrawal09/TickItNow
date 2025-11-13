ALTER TABLE preference_items
  ADD COLUMN schedule_id INT NOT NULL DEFAULT 0 AFTER show_id;

UPDATE preference_items p
LEFT JOIN halls h
  ON h.hall_code = p.venue_id
LEFT JOIN schedules s
  ON s.show_id = p.show_id
 AND s.start_at = p.start_at
 AND s.venue = COALESCE(h.hall_name, p.venue_name)
SET p.schedule_id = s.id
WHERE p.schedule_id = 0
  AND s.id IS NOT NULL;

ALTER TABLE booking_seats
  ADD COLUMN schedule_id INT NOT NULL DEFAULT 0 AFTER seat_id;

ALTER TABLE booking_seats
  ADD KEY idx_booking_seats_schedule (schedule_id);

ALTER TABLE booking_seats
  ADD CONSTRAINT fk_booking_seats_schedule FOREIGN KEY (schedule_id)
    REFERENCES schedules(id)
    ON DELETE CASCADE;
