-- ============================================================
--  Stay Management System - Room Manager (Room Master)
--  Mirrors the Customers Master pattern.
--  Import:  mysql -u root < db/rooms.sql
--
--  Design note (kept faithful to the flat Customers pattern):
--    * room_categories  -> category master (Category dropdown / filter)
--    * amenities        -> amenity master (Section 3 checkbox grid)
--    * room_amenities   -> many-to-many rooms <-> amenities
--    * taxes            -> tax master (Pricing "Tax" dropdown)
--    * rooms            -> one physical room; CURRENT pricing lives here
--                          (base/selling/tax/effective dates) exactly like
--                          the Customers table keeps everything on one row.
-- ============================================================

USE `stay_management`;

-- Drop children before parents (FK ordering).
DROP TABLE IF EXISTS `room_amenities`;
DROP TABLE IF EXISTS `rooms`;
DROP TABLE IF EXISTS `amenities`;
DROP TABLE IF EXISTS `taxes`;
DROP TABLE IF EXISTS `room_categories`;

-- ------------------------------------------------------------
--  Table: room_categories  (category master)
-- ------------------------------------------------------------
CREATE TABLE `room_categories` (
    `category_id`     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `category_name`   VARCHAR(100)    NOT NULL,
    `short_code`      VARCHAR(20)     DEFAULT NULL,
    `description`     VARCHAR(255)    DEFAULT NULL,
    `max_adults`      INT             DEFAULT NULL,
    `max_children`    INT             DEFAULT NULL,
    `room_size`       VARCHAR(30)     DEFAULT NULL,
    `room_size_unit`  VARCHAR(15)     DEFAULT 'sq.ft',
    `bed_type`        VARCHAR(40)     DEFAULT NULL,
    `bed_count`       INT             DEFAULT NULL,
    `smoking_allowed` TINYINT(1)      NOT NULL DEFAULT 0,
    `base_price`      DECIMAL(12,2)   DEFAULT NULL,
    `default_tax_id`  BIGINT UNSIGNED DEFAULT NULL,
    `default_sac_code` VARCHAR(20)    DEFAULT NULL,
    `image`           VARCHAR(255)    DEFAULT NULL,
    `display_order`   INT             NOT NULL DEFAULT 0,
    `status`          TINYINT(1)      NOT NULL DEFAULT 1,
    `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME        DEFAULT NULL,
    PRIMARY KEY (`category_id`),
    UNIQUE KEY `uq_category_name` (`category_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
--  Table: taxes  (tax master, referenced by Pricing)
-- ------------------------------------------------------------
CREATE TABLE `taxes` (
    `tax_id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tax_name`       VARCHAR(60)     NOT NULL,
    `tax_percentage` DECIMAL(5,2)    NOT NULL DEFAULT 0.00,
    `status`         TINYINT(1)      NOT NULL DEFAULT 1,
    PRIMARY KEY (`tax_id`),
    UNIQUE KEY `uq_tax_name` (`tax_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
--  Table: amenities  (amenity master, drives the checkbox grid)
-- ------------------------------------------------------------
CREATE TABLE `amenities` (
    `amenity_id`   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `amenity_name` VARCHAR(60)     NOT NULL,
    `icon`         VARCHAR(60)     DEFAULT NULL,   -- SVG filename in assets/icons/ (falls back to emoji/text)
    `status`       TINYINT(1)      NOT NULL DEFAULT 1,
    PRIMARY KEY (`amenity_id`),
    UNIQUE KEY `uq_amenity_name` (`amenity_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
--  Table: rooms  (one physical room; current pricing on the row)
-- ------------------------------------------------------------
CREATE TABLE `rooms` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `room_code`         VARCHAR(20)     NOT NULL,           -- e.g. ROOM00004 (immutable system id / upload folder)
    `room_no`           VARCHAR(30)     NOT NULL,           -- user-entered room number, unique, no spaces
    `room_name`         VARCHAR(150)    DEFAULT NULL,
    `category_id`       BIGINT UNSIGNED DEFAULT NULL,
    `floor_no`          VARCHAR(20)     DEFAULT NULL,
    `wing`              VARCHAR(40)     DEFAULT NULL,
    `room_size`         VARCHAR(30)     DEFAULT NULL,
    `room_size_unit`    VARCHAR(15)     DEFAULT 'sq.ft',
    `description`       VARCHAR(500)    DEFAULT NULL,
    `remarks`           VARCHAR(500)    DEFAULT NULL,
    -- Occupancy / configuration
    `max_adults`        INT             DEFAULT NULL,
    `max_children`      INT             DEFAULT NULL,
    `bed_type`          VARCHAR(40)     DEFAULT NULL,
    `bed_count`         INT             DEFAULT NULL,
    `bed_size`          VARCHAR(40)     DEFAULT NULL,
    `extra_bed_allowed` TINYINT(1)      NOT NULL DEFAULT 0,
    `accessible_room`   TINYINT(1)      NOT NULL DEFAULT 0,
    `connected_room`    VARCHAR(60)     DEFAULT NULL,
    `smoking`           TINYINT(1)      NOT NULL DEFAULT 0,
    `balcony`           TINYINT(1)      NOT NULL DEFAULT 0,
    `window_view`       VARCHAR(60)     DEFAULT NULL,
    -- Pricing (current effective pricing, kept on the room row)
    `base_price`        DECIMAL(12,2)   DEFAULT NULL,
    `selling_price`     DECIMAL(12,2)   DEFAULT NULL,
    `tax_id`            BIGINT UNSIGNED DEFAULT NULL,
    `sac_code`          VARCHAR(20)     DEFAULT NULL,
    `extra_person_charge` DECIMAL(12,2) DEFAULT NULL,
    `child_charge`      DECIMAL(12,2)   DEFAULT NULL,
    `effective_from`    DATE            DEFAULT NULL,
    `effective_to`      DATE            DEFAULT NULL,
    -- Housekeeping / operational
    `housekeeping_status` VARCHAR(30)   DEFAULT 'Clean',    -- Clean / Dirty / Inspected / Out of Service
    `room_condition`      VARCHAR(30)   DEFAULT 'Good',     -- Good / Fair / Under Maintenance / Damaged
    `room_phone`          VARCHAR(20)   DEFAULT NULL,
    `image_path`          VARCHAR(255)  DEFAULT NULL,       -- path relative to secure uploads base
    `is_active`         TINYINT(1)      NOT NULL DEFAULT 1, -- operational status
    -- Audit
    `created_by`        VARCHAR(20)     DEFAULT NULL,       -- mobile_no from session
    `created_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_by`        VARCHAR(20)     DEFAULT NULL,
    `updated_at`        DATETIME        DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_room_code` (`room_code`),
    UNIQUE KEY `uq_room_no`   (`room_no`),
    KEY `idx_category` (`category_id`),
    KEY `idx_floor`    (`floor_no`),
    KEY `idx_hk`       (`housekeeping_status`),
    CONSTRAINT `fk_room_category` FOREIGN KEY (`category_id`)
        REFERENCES `room_categories` (`category_id`) ON DELETE SET NULL,
    CONSTRAINT `fk_room_tax` FOREIGN KEY (`tax_id`)
        REFERENCES `taxes` (`tax_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
--  Table: room_amenities  (many-to-many rooms <-> amenities)
-- ------------------------------------------------------------
CREATE TABLE `room_amenities` (
    `room_id`    BIGINT UNSIGNED NOT NULL,
    `amenity_id` BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (`room_id`, `amenity_id`),
    KEY `idx_ra_amenity` (`amenity_id`),
    CONSTRAINT `fk_ra_room` FOREIGN KEY (`room_id`)
        REFERENCES `rooms` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ra_amenity` FOREIGN KEY (`amenity_id`)
        REFERENCES `amenities` (`amenity_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
--  Seed data
-- ============================================================

-- Taxes (tax_id 1..5)
INSERT INTO `taxes` (`tax_name`, `tax_percentage`, `status`) VALUES
    ('GST 0%',  0.00,  1),
    ('GST 5%',  5.00,  1),
    ('GST 12%', 12.00, 1),
    ('GST 18%', 18.00, 1),
    ('GST 28%', 28.00, 1);

-- Categories (category_id 1..5)
INSERT INTO `room_categories`
    (`category_name`, `short_code`, `description`, `max_adults`, `max_children`,
     `room_size`, `room_size_unit`, `bed_type`, `bed_count`, `smoking_allowed`,
     `base_price`, `default_tax_id`, `default_sac_code`, `display_order`, `status`)
VALUES
    ('Standard',        'STD',  'Comfortable standard room',        2, 1, '180', 'sq.ft', 'Double',  1, 0, 2000.00,  3, '996311', 1, 1),
    ('Deluxe',          'DLX',  'Spacious deluxe room',             2, 2, '250', 'sq.ft', 'Queen',   1, 0, 3200.00,  4, '996311', 2, 1),
    ('Super Deluxe',    'SDLX', 'Premium super deluxe room',        3, 2, '320', 'sq.ft', 'King',    1, 0, 4500.00,  4, '996311', 3, 1),
    ('Suite',           'STE',  'Luxury suite with living area',    3, 2, '480', 'sq.ft', 'King',    1, 0, 7000.00,  4, '996311', 4, 1),
    ('Executive Suite', 'EXE',  'Top-tier executive suite',         4, 2, '650', 'sq.ft', 'King',    2, 0, 9500.00,  4, '996311', 5, 1);

-- Amenities (amenity_id 1..15)
INSERT INTO `amenities` (`amenity_name`, `icon`, `status`) VALUES
    ('WiFi',            'wifi.png',            1),   -- 1
    ('Television',      'television.png',      1),   -- 2
    ('Smart TV',        'smart-tv.png',        1),   -- 3
    ('Mini Bar',        'mini-bar.png',        1),   -- 4
    ('Coffee Machine',  'coffee-machine.png',  1),   -- 5
    ('Hair Dryer',      'hair-dryer.png',      1),   -- 6
    ('Safe Locker',     'safe.png',            1),   -- 7
    ('Iron',            'iron.png',            1),   -- 8
    ('Bathtub',         'bathtub.png',         1),   -- 9
    ('Shower',          'shower.png',          1),   -- 10
    ('Microwave',       'microwave.png',       1),   -- 11
    ('Air Conditioner', 'air-conditioner.png', 1),   -- 12
    ('Refrigerator',    'refrigerator.png',    1),   -- 13
    ('Balcony',         'balcony-window.png',  1),   -- 14
    ('Work Desk',       'desk-table.png',      1);   -- 15

-- Rooms (id 1..6)
INSERT INTO `rooms`
    (`room_code`, `room_no`, `room_name`, `category_id`, `floor_no`, `wing`, `room_size`, `room_size_unit`,
     `description`, `remarks`, `max_adults`, `max_children`, `bed_type`, `bed_count`, `bed_size`,
     `extra_bed_allowed`, `accessible_room`, `connected_room`, `smoking`, `balcony`, `window_view`,
     `base_price`, `selling_price`, `tax_id`, `sac_code`, `extra_person_charge`, `child_charge`,
     `effective_from`, `effective_to`, `housekeeping_status`, `room_condition`, `room_phone`,
     `is_active`, `created_by`)
VALUES
    ('ROOM00001', '101', 'Garden View Standard', 1, '1', 'East',  '180', 'sq.ft',
     'Cosy standard room overlooking the garden.', NULL, 2, 1, 'Double', 1, 'Double',
     0, 0, NULL, 0, 0, 'Garden',
     2000.00, 2200.00, 3, '996311', 500.00, 250.00,
     '2026-01-01', NULL, 'Clean', 'Good', '101', 1, '9876543210'),
    ('ROOM00002', '102', 'City View Standard', 1, '1', 'East',  '180', 'sq.ft',
     'Standard room with a city-facing window.', NULL, 2, 1, 'Twin', 2, 'Single',
     1, 1, NULL, 1, 0, 'City',
     2000.00, 2200.00, 3, '996311', 500.00, 250.00,
     '2026-01-01', NULL, 'Dirty', 'Good', '102', 1, '9876543210'),
    ('ROOM00003', '201', 'Deluxe Queen', 2, '2', 'West',  '250', 'sq.ft',
     'Spacious deluxe room with queen bed.', NULL, 2, 2, 'Queen', 1, 'Queen',
     1, 0, '202', 0, 1, 'Pool',
     3200.00, 3500.00, 4, '996311', 700.00, 350.00,
     '2026-01-01', NULL, 'Inspected', 'Good', '201', 1, '9876543210'),
    ('ROOM00004', '202', 'Deluxe Connected', 2, '2', 'West',  '250', 'sq.ft',
     'Deluxe room, connects to 201.', 'Family friendly', 2, 2, 'Queen', 1, 'Queen',
     1, 0, '201', 0, 1, 'Pool',
     3200.00, 3500.00, 4, '996311', 700.00, 350.00,
     '2026-01-01', NULL, 'Clean', 'Good', '202', 0, '9876543210'),
    ('ROOM00005', '301', 'Super Deluxe King', 3, '3', 'North', '320', 'sq.ft',
     'Premium super deluxe with king bed.', NULL, 3, 2, 'King', 1, 'King',
     1, 1, NULL, 0, 1, 'Sea',
     4500.00, 4900.00, 4, '996311', 900.00, 450.00,
     '2026-01-01', NULL, 'Clean', 'Good', '301', 1, '9876543210'),
    ('ROOM00006', '401', 'Presidential Suite', 4, '4', 'North', '480', 'sq.ft',
     'Luxury suite with separate living area.', 'VIP', 3, 2, 'King', 1, 'King',
     1, 1, NULL, 0, 1, 'Sea',
     7000.00, 7800.00, 4, '996311', 1200.00, 600.00,
     '2026-01-01', NULL, 'Out of Service', 'Under Maintenance', '401', 1, '9876543210');

-- Room ↔ amenity links
INSERT INTO `room_amenities` (`room_id`, `amenity_id`) VALUES
    (1,1),(1,2),(1,10),(1,12),(1,13),
    (2,1),(2,2),(2,10),(2,12),(2,13),(2,8),
    (3,1),(3,3),(3,4),(3,5),(3,9),(3,10),(3,12),(3,13),(3,14),
    (4,1),(4,3),(4,4),(4,5),(4,10),(4,12),(4,13),(4,14),
    (5,1),(5,3),(5,4),(5,5),(5,6),(5,9),(5,10),(5,12),(5,13),(5,14),(5,15),
    (6,1),(6,3),(6,4),(6,5),(6,6),(6,7),(6,9),(6,10),(6,11),(6,12),(6,13),(6,14),(6,15);
