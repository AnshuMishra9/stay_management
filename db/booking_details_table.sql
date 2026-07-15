-- ============================================================
--  booking_details — one customer -> many bookings.
--
--    id             : PK
--    booking_number : human-facing unique no. (BKG00001, BKG00002, …)
--    customer_id    : FK -> customers.id  (whose booking is this)
--                     ON DELETE CASCADE -> deleting a customer removes
--                     their bookings too.
--    room_id        : FK -> rooms.id  (the specific room ALLOTTED to this
--                     booking; a room can be allotted to at most one booking
--                     at a time). ON DELETE SET NULL.
--
--  scheduled_check_in_date / scheduled_check_out_date are kept for the
--  Booking Details list (view-only) — their form inputs were removed.
--
--  Import (after customers.sql + rooms.sql):
--     mysql -u root < db/booking_details_table.sql
-- ============================================================
USE `stay_management`;

CREATE TABLE IF NOT EXISTS `booking_details` (
    `id`                       BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `booking_number`           VARCHAR(20)  NOT NULL COMMENT 'human-facing unique, e.g. BKG00001',
    `customer_id`              BIGINT(20) UNSIGNED NOT NULL COMMENT 'FK -> customers.id (whose booking)',

    `booking_channel_id`       BIGINT(20) UNSIGNED DEFAULT NULL COMMENT 'FK booking_channels.channel_id',
    `booking_status`           ENUM('enquiry','confirmed','checked_in','checked_out','cancelled','no_show') DEFAULT NULL,
    `property_name`            VARCHAR(150) DEFAULT NULL,
    `scheduled_check_in_date`  DATE         DEFAULT NULL,
    `scheduled_check_out_date` DATE         DEFAULT NULL,
    `length_of_stay`           INT          DEFAULT NULL,
    `checked_in_at`            DATETIME     DEFAULT NULL,
    `checked_out_at`           DATETIME     DEFAULT NULL,
    `total_guest`              INT          DEFAULT NULL,
    `room_category_id`         BIGINT(20) UNSIGNED DEFAULT NULL COMMENT 'FK room_categories.category_id',
    `room_id`                  BIGINT(20) UNSIGNED DEFAULT NULL COMMENT 'FK rooms.id — allotted room',
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
    KEY `idx_bd_room` (`room_id`),
    CONSTRAINT `fk_bd_customer` FOREIGN KEY (`customer_id`)
        REFERENCES `customers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_bd_room` FOREIGN KEY (`room_id`)
        REFERENCES `rooms` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
