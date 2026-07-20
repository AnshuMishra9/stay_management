-- ============================================================
--  status_master — the single source of truth for booking status.
--  Creates the table AND seeds its 5 statuses.
--
--  Use this when you only need to add status_master to an existing
--  database (the full snapshot db/stay_management.sql already includes
--  it). Safe to re-run: the CREATE is IF NOT EXISTS and the seed uses
--  ON DUPLICATE KEY UPDATE, so nothing is duplicated or lost.
--
--    mysql -u root < db/status_master.sql
--
--  NOTE: bookings reference this via booking_details.status_id (FK
--  fk_bd_status). If your booking_details still has the old
--  `booking_status` ENUM instead of `status_id`, import the full
--  snapshot (db/stay_management.sql) rather than this file alone.
-- ============================================================
USE `stay_management`;

CREATE TABLE IF NOT EXISTS `status_master` (
    `status_id`     BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `status_code`   VARCHAR(30)  NOT NULL,
    `status_name`   VARCHAR(50)  NOT NULL,
    `display_order` INT(11)      NOT NULL DEFAULT 0,
    `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (`status_id`),
    UNIQUE KEY `uq_status_code` (`status_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `status_master` (`status_id`, `status_code`, `status_name`, `display_order`, `is_active`) VALUES
    (1, 'room_booked', 'Room booked', 1, 1),
    (2, 'checked_in',  'Checked in',  2, 1),
    (3, 'checked_out', 'Checked out', 3, 1),
    (4, 'cancelled',   'Cancelled',   4, 1),
    (5, 'no_show',     'No show',     5, 1)
ON DUPLICATE KEY UPDATE
    `status_code`   = VALUES(`status_code`),
    `status_name`   = VALUES(`status_name`),
    `display_order` = VALUES(`display_order`),
    `is_active`     = VALUES(`is_active`);
