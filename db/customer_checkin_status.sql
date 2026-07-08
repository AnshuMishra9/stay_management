-- ============================================================
--  Booking lifecycle + check-in tracking for `customers`.
--
--  Adds a self-documenting booking status and ACTUAL check-in/
--  check-out timestamps, and renames the two date columns so the
--  name itself says what is stored:
--
--    check_in  -> scheduled_check_in_date   (PLANNED arrival date)
--    check_out -> scheduled_check_out_date  (PLANNED departure date)
--
--  New columns:
--    booking_status  -> where the booking is in its lifecycle
--    checked_in_at   -> real timestamp the guest walked in  (NULL = not yet)
--    checked_out_at  -> real timestamp the guest left       (NULL = not yet)
--
--  So "has the guest checked in?"  ->  checked_in_at IS NOT NULL
--     "has the booking arrived?"   ->  booking_status IN ('confirmed','checked_in','checked_out')
-- ============================================================
USE `stay_management`;

ALTER TABLE `customers`
    -- Rename the two ambiguous date columns to say "scheduled/planned".
    CHANGE COLUMN `check_in`  `scheduled_check_in_date`  DATE DEFAULT NULL
        COMMENT 'PLANNED arrival date (guest is expected to check in on this date)',
    CHANGE COLUMN `check_out` `scheduled_check_out_date` DATE DEFAULT NULL
        COMMENT 'PLANNED departure date (guest is expected to check out on this date)',

    -- Booking lifecycle stage.
    ADD COLUMN `booking_status`
        ENUM('enquiry','confirmed','checked_in','checked_out','cancelled','no_show')
        DEFAULT NULL
        COMMENT 'Booking lifecycle: enquiry -> confirmed -> checked_in -> checked_out (or cancelled / no_show)'
        AFTER `booking_channel_id`,

    -- ACTUAL check-in / check-out event timestamps (not the planned dates).
    ADD COLUMN `checked_in_at` DATETIME DEFAULT NULL
        COMMENT 'ACTUAL date+time the guest physically checked in (NULL = has NOT checked in yet)'
        AFTER `length_of_stay`,
    ADD COLUMN `checked_out_at` DATETIME DEFAULT NULL
        COMMENT 'ACTUAL date+time the guest checked out (NULL = still staying / not checked out yet)'
        AFTER `checked_in_at`,

    ADD KEY `idx_booking_status` (`booking_status`);
