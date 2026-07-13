-- ============================================================
--  Remove `customer_type` and `owner_name` from customers.
--  These fields are no longer used anywhere in the app.
-- ============================================================
USE `stay_management`;

ALTER TABLE `customers`
    DROP COLUMN `customer_type`,
    DROP COLUMN `owner_name`;
