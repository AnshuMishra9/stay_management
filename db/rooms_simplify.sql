-- ============================================================
--  Room Master simplification.
--
--  Kept:  room_code, room_no (single "Room Name / Number" field),
--         category_id, floor_no, description, remarks,
--         extra_bed_allowed, selling_price (single price),
--         housekeeping_status (now Available / Not Available),
--         is_active + audit columns.
--
--  Dropped: room_name (merged into room_no), wing, room_size,
--         room_size_unit, all Occupancy & Configuration columns,
--         base_price + the extra pricing columns, tax_id (+ its FK),
--         effective_from/to, room_condition, room_phone, image_path.
--
--  Amenities: the amenities / room_amenities master tables are kept
--         (they're independent masters) — only the Room form's
--         amenity section was removed.
--
--  Safe to re-run.
-- ============================================================
USE `stay_management`;

-- Housekeeping: collapse the old 4-value set into Available / Not Available.
UPDATE `rooms`
   SET `housekeeping_status` = CASE
        WHEN `housekeeping_status` IN ('Clean', 'Inspected') THEN 'Available'
        ELSE 'Not Available'
   END
 WHERE `housekeeping_status` IS NULL
    OR `housekeeping_status` NOT IN ('Available', 'Not Available');

ALTER TABLE `rooms`
    MODIFY `housekeeping_status` VARCHAR(30) DEFAULT 'Available';

-- Drop the tax FK before dropping the column it guards.
SET @has_fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rooms' AND CONSTRAINT_NAME = 'fk_room_tax');
SET @sql := IF(@has_fk > 0, 'ALTER TABLE `rooms` DROP FOREIGN KEY `fk_room_tax`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

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

CALL `_drop_col`('rooms', 'room_name');
CALL `_drop_col`('rooms', 'wing');
CALL `_drop_col`('rooms', 'room_size');
CALL `_drop_col`('rooms', 'room_size_unit');
CALL `_drop_col`('rooms', 'max_adults');
CALL `_drop_col`('rooms', 'max_children');
CALL `_drop_col`('rooms', 'bed_type');
CALL `_drop_col`('rooms', 'bed_count');
CALL `_drop_col`('rooms', 'bed_size');
CALL `_drop_col`('rooms', 'accessible_room');
CALL `_drop_col`('rooms', 'connected_room');
CALL `_drop_col`('rooms', 'smoking');
CALL `_drop_col`('rooms', 'balcony');
CALL `_drop_col`('rooms', 'window_view');
CALL `_drop_col`('rooms', 'base_price');
CALL `_drop_col`('rooms', 'tax_id');
CALL `_drop_col`('rooms', 'sac_code');
CALL `_drop_col`('rooms', 'extra_person_charge');
CALL `_drop_col`('rooms', 'child_charge');
CALL `_drop_col`('rooms', 'effective_from');
CALL `_drop_col`('rooms', 'effective_to');
CALL `_drop_col`('rooms', 'room_condition');
CALL `_drop_col`('rooms', 'room_phone');
CALL `_drop_col`('rooms', 'image_path');

DROP PROCEDURE IF EXISTS `_drop_col`;
