-- ============================================================
-- Booking Channels Master
-- Stay Management System
-- ============================================================

DROP TABLE IF EXISTS `booking_channels`;

CREATE TABLE `booking_channels` (

    `channel_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Primary Key',

    `channel_name` VARCHAR(100) NOT NULL COMMENT 'Booking Channel Name',

    `channel_category` ENUM(
        'Direct',
        'OTA',
        'Corporate',
        'Agent',
        'Referral',
        'Internal',
        'Other'
    ) NOT NULL COMMENT 'Booking Channel Category',

    `commission_percentage` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Default Commission Percentage',

    `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = Active, 0 = Inactive',

    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`channel_id`),

    UNIQUE KEY `uk_channel_name` (`channel_name`),

    KEY `idx_channel_category` (`channel_category`),

    KEY `idx_status` (`status`)

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Seed Data
-- ============================================================

INSERT INTO `booking_channels`
(
    `channel_name`,
    `channel_category`,
    `commission_percentage`,
    `status`
)
VALUES
('Walk-in',        'Direct',      0.00, 1),

('MakeMyTrip',     'OTA',        18.00, 1),
('Goibibo',        'OTA',        18.00, 1),
('Booking.com',    'OTA',        15.00, 1),
('Agoda',          'OTA',        18.00, 1),
('Expedia',        'OTA',        18.00, 1),
('Hotels.com',     'OTA',        18.00, 1),
('Ixigo',          'OTA',        15.00, 1),
('Cleartrip',      'OTA',        15.00, 1),
('Yatra',          'OTA',        15.00, 1),
('Airbnb',         'OTA',        15.00, 1),
('Trip.com',       'OTA',        15.00, 1),

('Company',        'Corporate',   0.00, 1),
('Government',     'Corporate',   0.00, 1),

('Travel Agent',   'Agent',      10.00, 1);