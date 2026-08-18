-- Existing installations: add the optional back-side image path.
-- Safe to run more than once on MariaDB/MySQL versions supporting IF NOT EXISTS.
ALTER TABLE `customer_identities`
    ADD COLUMN IF NOT EXISTS `document_path_2` varchar(255) DEFAULT NULL
    AFTER `document_path`;

