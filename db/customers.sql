-- ============================================================
--  Stay Management System - Customers Master
--  Import:  mysql -u root < db/customers.sql
-- ============================================================

USE `stay_management`;

DROP TABLE IF EXISTS `customers`;

CREATE TABLE `customers` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `customer_code`    VARCHAR(20)     NOT NULL,               -- e.g. CUST00004 (shown as "Customer ID")
    `customer_name`    VARCHAR(150)    NOT NULL,
    `owner_name`       VARCHAR(150)    DEFAULT NULL,           -- Owner Name / Contact Person
    `phone`            VARCHAR(20)     NOT NULL,               -- Mobile No
    `alt_phone`        VARCHAR(20)     DEFAULT NULL,
    `landline_no`      VARCHAR(20)     DEFAULT NULL,
    `email`            VARCHAR(150)    DEFAULT NULL,
    `customer_type`    VARCHAR(50)     DEFAULT NULL,           -- Retail / Wholesale / Corporate ...
    `address1`         VARCHAR(255)    DEFAULT NULL,
    `address2`         VARCHAR(255)    DEFAULT NULL,
    `city`             VARCHAR(100)    DEFAULT NULL,
    `district`         VARCHAR(100)    DEFAULT NULL,
    `pincode`          VARCHAR(15)     DEFAULT NULL,
    `zip_code`         VARCHAR(15)     DEFAULT NULL,
    `state`            VARCHAR(100)    DEFAULT NULL,
    `country`          VARCHAR(100)    DEFAULT NULL,
    -- Identity (shown only in detail/edit view, never in the list grid)
    `aadhar_number`    VARCHAR(20)     DEFAULT NULL,
    `aadhar_name`      VARCHAR(150)    DEFAULT NULL,
    `aadhar_card_path` VARCHAR(255)    DEFAULT NULL,           -- path relative to secure uploads base
    `pan_number`       VARCHAR(20)     DEFAULT NULL,
    `pan_name`         VARCHAR(150)    DEFAULT NULL,
    `pan_card_path`    VARCHAR(255)    DEFAULT NULL,
    `is_active`        TINYINT(1)      NOT NULL DEFAULT 1,
    `created_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME        DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_customer_code` (`customer_code`),
    KEY `idx_name`  (`customer_name`),
    KEY `idx_phone` (`phone`),
    KEY `idx_city`  (`city`),
    KEY `idx_state` (`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
--  Seed data (so the grid + filters have content immediately)
-- ------------------------------------------------------------
INSERT INTO `customers`
    (`customer_code`, `customer_name`, `owner_name`, `phone`, `alt_phone`, `email`,
     `customer_type`, `address1`, `city`, `district`, `pincode`, `state`, `country`, `is_active`)
VALUES
    ('CUST00001', 'Sunrise Residency',   'Ramesh Kumar',  '9483700451', NULL,          'ramesh@sunrise.in',  'Retail Customer',    'Periyapatna town',        'Mysore',     'Mysore',     '571107', 'Karnataka',  'India', 1),
    ('CUST00002', 'Blue Orchid Hotel',   'YUI',           '9123212345', '9123212300',  'contact@blueorchid.in','Wholesale Customer', 'MG Road',               'Bengaluru',  'Bangalore',  '560001', 'Karnataka',  'India', 1),
    ('CUST00003', 'Green Valley Inn',    'Naveen',        '9944456772', NULL,          NULL,                 'Corporate',          'Race Course Road',      'Coimbatore', 'Coimbatore', '641018', 'Tamil Nadu', 'India', 1),
    ('CUST00004', 'Seaside Comforts',    'Arun',          '9822222222', NULL,          'arun@seaside.in',    'Retail Customer',    'Beach Road',            'Chennai',    'Chennai',    '600001', 'Tamil Nadu', 'India', 0),
    ('CUST00005', 'Hilltop Stays',       'Rajeeva',       '9999999991', '9888888882',  'info@hilltop.in',    'Wholesale Customer', 'Hill Station Road',     'Nagercoil',  'Kanyakumari','629001', 'Tamil Nadu', 'India', 1),
    ('CUST00006', 'City Center Lodge',   'Shree',   '8882357897', NULL,          'divya@citycenter.in','Retail Customer',    'Brigade Road',          'Bengaluru',  'Bangalore',  '560025', 'Karnataka',  'India', 1);
