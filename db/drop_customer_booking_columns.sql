-- ============================================================
--  Finish the booking normalization: remove the booking columns
--  from `customers`. Bookings now live ONLY in `booking_details`
--  (one customer -> many bookings, FK customer_id).
--
--  PRE-REQ: run db/booking_details_table.sql FIRST — it creates the
--  table and migrates each customer's booking into it. Verify with:
--     SELECT COUNT(*) FROM booking_details;
--  before running this (it is destructive: the columns are dropped).
-- ============================================================
USE `stay_management`;

ALTER TABLE `customers`
    DROP COLUMN `booking_channel_id`,
    DROP COLUMN `booking_status`,
    DROP COLUMN `booking_by`,
    DROP COLUMN `guest_name`,
    DROP COLUMN `guest_mobile_no`,
    DROP COLUMN `guest_contact_no`,
    DROP COLUMN `property_name`,
    DROP COLUMN `scheduled_check_in_date`,
    DROP COLUMN `scheduled_check_out_date`,
    DROP COLUMN `length_of_stay`,
    DROP COLUMN `checked_in_at`,
    DROP COLUMN `checked_out_at`,
    DROP COLUMN `total_guest`,
    DROP COLUMN `room_category_id`,
    DROP COLUMN `room_quantity`,
    DROP COLUMN `total_unit`,
    DROP COLUMN `total_amount`,
    DROP COLUMN `amount_paid`,
    DROP COLUMN `remaining_amount`;
