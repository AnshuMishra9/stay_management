-- ============================================================
--  Stay Management System - Database Schema
--  OTP-based authentication using a two-table design:
--    users         -> permanent authorized-user records
--    otp_requests  -> full history of every OTP generated (audit trail)
--  Import via phpMyAdmin or:  mysql -u root < db/stay_management.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS `stay_management`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_general_ci;

USE `stay_management`;

-- Drop children before parents (FK ordering).
DROP TABLE IF EXISTS `otp_requests`;
DROP TABLE IF EXISTS `users`;

-- ------------------------------------------------------------
--  Table: users  (permanent user information only — never OTPs)
-- ------------------------------------------------------------
CREATE TABLE `users` (
    `id`         BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `mobile_no`  VARCHAR(15)      NOT NULL,
    `is_active`  TINYINT(1)       NOT NULL DEFAULT 1,
    `last_login` DATETIME         DEFAULT NULL,
    `created_at` DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_mobile` (`mobile_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
--  Table: otp_requests  (one NEW row per OTP request — history)
-- ------------------------------------------------------------
CREATE TABLE `otp_requests` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`     BIGINT UNSIGNED NOT NULL,
    `otp`         VARCHAR(6)      NOT NULL,
    `expires_at`  DATETIME        NOT NULL,
    `is_verified` TINYINT(1)      NOT NULL DEFAULT 0,
    `attempts`    INT             NOT NULL DEFAULT 0,
    `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_otp_lookup` (`user_id`, `is_verified`, `expires_at`),
    CONSTRAINT `fk_otp_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
--  Seed data (authorized users for development / demo login)
--    9876543210 -> active
--    9988776655 -> active
--    9123456789 -> inactive (blocked)
-- ------------------------------------------------------------
INSERT INTO `users` (`mobile_no`, `is_active`) VALUES
    ('9876543210', 1),
    ('9988776655', 1),
    ('9123456789', 0);
