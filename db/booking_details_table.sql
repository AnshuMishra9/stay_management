-- ============================================================
--  booking_details — bookings pulled out of the flat `customers`
--  table into their own table (one customer -> many bookings).
--
--    id             : PK
--    booking_number : human-facing unique no. (BKG00001, BKG00002, …)
--    customer_id    : FK -> customers.id  (whose booking is this)
--                     ON DELETE CASCADE -> deleting a customer removes
--                     their bookings too.
--
--  The booking columns still exist on `customers` for now (the add/edit
--  form keeps working); this table is the normalized store the Booking
--  Details page reads from. The customer's booking is kept in sync on save.
-- ============================================================
USE `stay_management`;

CREATE TABLE IF NOT EXISTS `booking_details` (
    `id`                       BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `booking_number`           VARCHAR(20)  NOT NULL COMMENT 'human-facing unique, e.g. BKG00001',
    `customer_id`              BIGINT(20) UNSIGNED NOT NULL COMMENT 'FK -> customers.id (whose booking)',

    `booking_channel_id`       BIGINT(20) UNSIGNED DEFAULT NULL COMMENT 'FK booking_channels.channel_id',
    `booking_status`           ENUM('enquiry','confirmed','checked_in','checked_out','cancelled','no_show') DEFAULT NULL,
    `booking_by`               VARCHAR(150) DEFAULT NULL,
    `guest_name`               VARCHAR(150) DEFAULT NULL,
    `guest_mobile_no`          VARCHAR(20)  DEFAULT NULL,
    `guest_contact_no`         VARCHAR(20)  DEFAULT NULL,
    `property_name`            VARCHAR(150) DEFAULT NULL,
    `scheduled_check_in_date`  DATE         DEFAULT NULL,
    `scheduled_check_out_date` DATE         DEFAULT NULL,
    `length_of_stay`           INT          DEFAULT NULL,
    `checked_in_at`            DATETIME     DEFAULT NULL,
    `checked_out_at`           DATETIME     DEFAULT NULL,
    `total_guest`              INT          DEFAULT NULL,
    `room_category_id`         BIGINT(20) UNSIGNED DEFAULT NULL COMMENT 'FK room_categories.category_id',
    `room_quantity`            INT          DEFAULT NULL,
    `total_unit`               INT          DEFAULT NULL,
    `total_amount`             DECIMAL(12,2) DEFAULT NULL,
    `amount_paid`              DECIMAL(12,2) DEFAULT NULL,
    `remaining_amount`         DECIMAL(12,2) DEFAULT NULL,

    `created_at`               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`               DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_booking_number` (`booking_number`),
    KEY `idx_bd_customer` (`customer_id`),
    KEY `idx_bd_status` (`booking_status`),
    KEY `idx_bd_checkin` (`scheduled_check_in_date`),
    CONSTRAINT `fk_bd_customer` FOREIGN KEY (`customer_id`)
        REFERENCES `customers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
--  One-time migration: move each customer's existing booking
--  into booking_details, numbered BKG00001… by customer id.
--  (Safe to re-run: skips customers that already have a booking.)
-- ------------------------------------------------------------
SET @n := (SELECT COALESCE(MAX(CAST(SUBSTRING(booking_number, 4) AS UNSIGNED)), 0) FROM `booking_details`);

INSERT INTO `booking_details`
    (`booking_number`, `customer_id`, `booking_channel_id`, `booking_status`, `booking_by`,
     `guest_name`, `guest_mobile_no`, `guest_contact_no`, `property_name`,
     `scheduled_check_in_date`, `scheduled_check_out_date`, `length_of_stay`,
     `checked_in_at`, `checked_out_at`, `total_guest`, `room_category_id`, `room_quantity`,
     `total_unit`, `total_amount`, `amount_paid`, `remaining_amount`, `created_at`)
SELECT
    CONCAT('BKG', LPAD((@n := @n + 1), 5, '0')), c.`id`, c.`booking_channel_id`, c.`booking_status`, c.`booking_by`,
    c.`guest_name`, c.`guest_mobile_no`, c.`guest_contact_no`, c.`property_name`,
    c.`scheduled_check_in_date`, c.`scheduled_check_out_date`, c.`length_of_stay`,
    c.`checked_in_at`, c.`checked_out_at`, c.`total_guest`, c.`room_category_id`, c.`room_quantity`,
    c.`total_unit`, c.`total_amount`, c.`amount_paid`, c.`remaining_amount`, COALESCE(c.`created_at`, NOW())
FROM `customers` c
WHERE (c.`booking_status` IS NOT NULL OR c.`scheduled_check_in_date` IS NOT NULL OR c.`booking_channel_id` IS NOT NULL)
  AND NOT EXISTS (SELECT 1 FROM `booking_details` b WHERE b.`customer_id` = c.`id`)
ORDER BY c.`id`;
