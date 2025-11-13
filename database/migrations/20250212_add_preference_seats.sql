ALTER TABLE preference_items
  ADD COLUMN seat_ids TEXT NULL AFTER ticket_class,
  ADD COLUMN seat_labels TEXT NULL AFTER seat_ids;
