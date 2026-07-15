-- ============================================================
--  Booking form changes:
--   1) Drop the fields removed from the New Booking form
--      (Booking By, Guest Name, Guest Mobile No, Guest Contact No).
--   2) Add `room_id` — the specific room ALLOTTED to a booking
--      (FK -> rooms.id). A room can be allotted to at most one
--      booking at a time; the form only offers rooms not already
--      allotted to another booking.
--
--  NOTE: scheduled_check_in_date / scheduled_check_out_date stay
--  in the table (view-only on the list) — only their form inputs
--  were removed.
--
--  Safe to re-run.
-- ============================================================
USE `stay_management`;

DROP PROCEDURE IF EXISTS `_drop_col`;
DELIMITER //
CREATE PROCEDURE `_drop_col`(IN tbl VARCHAR(64), IN col VARCHAR(64))
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.COLUMNS
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tbl AND COLUMN_NAME = col) THEN
        SET @sql = CONCAT('ALTER TABLE `', tbl, '` DROP COLUMN `', col, '`');
        PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
    END IF;
END //
DELIMITER ;

CALL `_drop_col`('booking_details', 'booking_by');
CALL `_drop_col`('booking_details', 'guest_name');
CALL `_drop_col`('booking_details', 'guest_mobile_no');
CALL `_drop_col`('booking_details', 'guest_contact_no');

DROP PROCEDURE IF EXISTS `_drop_col`;

-- Add the allotted-room column + FK (idempotent).
SET @has_room_id := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'booking_details' AND COLUMN_NAME = 'room_id');
SET @sql := IF(@has_room_id = 0,
    'ALTER TABLE `booking_details`
        ADD COLUMN `room_id` BIGINT(20) UNSIGNED DEFAULT NULL COMMENT ''FK rooms.id — allotted room'' AFTER `room_category_id`,
        ADD KEY `idx_bd_room` (`room_id`),
        ADD CONSTRAINT `fk_bd_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
