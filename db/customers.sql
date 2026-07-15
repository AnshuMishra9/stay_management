-- ============================================================
--  Stay Management System - Customers Master
--  Import:  mysql -u root < db/customers.sql
--
--  NOTE: This is the CURRENT (final) shape of the table. The contact/
--  address block (alt_phone, landline_no, email, address1/2, city,
--  district, state, zip_code) and the old owner_name / customer_type
--  columns were removed — see the matching migration scripts in this
--  folder for existing databases. Identity proofs now live in their own
--  table: see db/customer_identities.sql.
-- ============================================================

USE `stay_management`;

DROP TABLE IF EXISTS `customers`;

CREATE TABLE `customers` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `customer_code`    VARCHAR(20)     NOT NULL,               -- e.g. CUST00004 (shown as "Customer ID")
    `customer_name`    VARCHAR(150)    NOT NULL,
    `phone`            VARCHAR(20)     NOT NULL,               -- Mobile No
    `pincode`          VARCHAR(15)     DEFAULT NULL,
    `country`          VARCHAR(100)    DEFAULT NULL,
    `is_active`        TINYINT(1)      NOT NULL DEFAULT 1,
    `created_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME        DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_customer_code` (`customer_code`),
    KEY `idx_name`  (`customer_name`),
    KEY `idx_phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
--  Seed data (so the grid + filters have content immediately)
-- ------------------------------------------------------------
INSERT INTO `customers`
    (`customer_code`, `customer_name`, `phone`, `pincode`, `country`, `is_active`)
VALUES
    ('CUST00001', 'Sunrise Residency', '9483700451', '571107', 'India', 1),
    ('CUST00002', 'Blue Orchid Hotel', '9123212345', '560001', 'India', 1),
    ('CUST00003', 'Green Valley Inn',  '9944456772', '641018', 'India', 1),
    ('CUST00004', 'Seaside Comforts',  '9822222222', '600001', 'India', 0),
    ('CUST00005', 'Hilltop Stays',     '9999999991', '629001', 'India', 1),
    ('CUST00006', 'City Center Lodge', '8882357897', '560025', 'India', 1);
