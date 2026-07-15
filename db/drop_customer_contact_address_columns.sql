-- ============================================================
--  Remove the customer contact/address fields that were dropped
--  from the Add/Edit Customer form (Alt Mobile No … Zip Code).
--
--  Kept on the customer: phone, customer_name, pincode, country,
--  Aadhar/PAN identity fields, is_active.
--
--  Safe to re-run: each DROP is guarded so a missing column is a
--  no-op rather than an error.
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

CALL `_drop_col`('customers', 'alt_phone');
CALL `_drop_col`('customers', 'landline_no');
CALL `_drop_col`('customers', 'email');
CALL `_drop_col`('customers', 'address1');
CALL `_drop_col`('customers', 'address2');
CALL `_drop_col`('customers', 'city');
CALL `_drop_col`('customers', 'district');
CALL `_drop_col`('customers', 'state');
CALL `_drop_col`('customers', 'zip_code');

DROP PROCEDURE IF EXISTS `_drop_col`;
