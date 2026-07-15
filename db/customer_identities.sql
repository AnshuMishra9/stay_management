-- ============================================================
--  customer_identities — identity proofs for a customer.
--  One customer -> many identity proofs (Aadhar Card, PAN Card,
--  Passport, Voter ID …). Replaces the old fixed aadhar_* / pan_*
--  columns that used to live on `customers`.
--
--    identity_type   : aadhar | pan | passport | voter_id
--    identity_number : the number printed on that document
--    document_path   : uploaded image/scan, path relative to the
--                      secure uploads base (streamed behind auth)
--
--  Import AFTER customers.sql:
--     mysql -u root < db/customer_identities.sql
-- ============================================================
USE `stay_management`;

-- ( identity_type: aadhar | pan | passport | voter_id )
CREATE TABLE IF NOT EXISTS `customer_identities` (
    `id`              BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `customer_id`     BIGINT(20) UNSIGNED NOT NULL,
    `identity_type`   VARCHAR(30)  NOT NULL,
    `identity_number` VARCHAR(50)  DEFAULT NULL,
    `document_path`   VARCHAR(255) DEFAULT NULL,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_ci_customer` (`customer_id`),
    CONSTRAINT `fk_ci_customer` FOREIGN KEY (`customer_id`)
        REFERENCES `customers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
