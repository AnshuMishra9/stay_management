-- ============================================================
--  Add booking/stay fields to the (flat) customers table.
--  No table separation — these live directly on `customers`.
--  booking_channel_id -> booking_channels.channel_id (dropdown)
--  room_category_id   -> room_categories.category_id (dropdown)
-- ============================================================
USE `stay_management`;

ALTER TABLE `customers`
    ADD COLUMN `booking_channel_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'FK booking_channels.channel_id' AFTER `is_active`,
    ADD COLUMN `booking_by`         VARCHAR(150)    DEFAULT NULL COMMENT 'Who made the booking (may differ from guest)' AFTER `booking_channel_id`,
    ADD COLUMN `guest_name`         VARCHAR(150)    DEFAULT NULL AFTER `booking_by`,
    ADD COLUMN `guest_mobile_no`    VARCHAR(20)     DEFAULT NULL AFTER `guest_name`,
    ADD COLUMN `guest_contact_no`   VARCHAR(20)     DEFAULT NULL AFTER `guest_mobile_no`,
    ADD COLUMN `property_name`      VARCHAR(150)    DEFAULT NULL AFTER `guest_contact_no`,
    ADD COLUMN `check_in`           DATE            DEFAULT NULL AFTER `property_name`,
    ADD COLUMN `check_out`          DATE            DEFAULT NULL AFTER `check_in`,
    ADD COLUMN `length_of_stay`     INT             DEFAULT NULL COMMENT 'nights = check_out - check_in' AFTER `check_out`,
    ADD COLUMN `total_guest`        INT             DEFAULT NULL AFTER `length_of_stay`,
    ADD COLUMN `room_category_id`   BIGINT UNSIGNED DEFAULT NULL COMMENT 'FK room_categories.category_id' AFTER `total_guest`,
    ADD COLUMN `room_quantity`      INT             DEFAULT NULL COMMENT 'qty of the selected category' AFTER `room_category_id`,
    ADD COLUMN `total_unit`         INT             DEFAULT NULL COMMENT 'total rooms/units booked' AFTER `room_quantity`,
    ADD COLUMN `total_amount`       DECIMAL(12,2)   DEFAULT NULL AFTER `total_unit`,
    ADD COLUMN `amount_paid`        DECIMAL(12,2)   DEFAULT NULL AFTER `total_amount`,
    ADD COLUMN `remaining_amount`   DECIMAL(12,2)   DEFAULT NULL COMMENT 'total_amount - amount_paid' AFTER `amount_paid`,
    ADD KEY `idx_booking_channel` (`booking_channel_id`),
    ADD KEY `idx_room_category` (`room_category_id`);
