-- ============================================================
--  Drop the entire Stay Management database.
--
--  WARNING: destructive — this removes the `stay_management`
--  database and ALL of its data.
--
--  Usage:
--      mysql -u root < db/drop_database.sql
--
--  Then rebuild everything (structure + demo data) with:
--      mysql -u root < db/stay_management.sql
-- ============================================================

DROP DATABASE IF EXISTS `stay_management`;
