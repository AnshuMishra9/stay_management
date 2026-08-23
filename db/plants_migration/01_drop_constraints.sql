-- ============================================================================
-- 01_drop_constraints.sql
-- STEP 1/4 : Saare FOREIGN KEYs aur CHECK constraint drop karo
--            (column/table renames se pehle zaroori)
-- TARGET   : stay_management (current live schema)
-- ============================================================================

-- booking_details ke sabse pehle (zyada dependents)
ALTER TABLE `booking_details` DROP FOREIGN KEY `fk_bd_channel`;
ALTER TABLE `booking_details` DROP FOREIGN KEY `fk_bd_customer_tenant`;
ALTER TABLE `booking_details` DROP FOREIGN KEY `fk_bd_property_tenant`;
ALTER TABLE `booking_details` DROP FOREIGN KEY `fk_bd_room_category_property`;
ALTER TABLE `booking_details` DROP FOREIGN KEY `fk_bd_room_property`;
ALTER TABLE `booking_details` DROP FOREIGN KEY `fk_bd_status`;

-- customer_identities
ALTER TABLE `customer_identities` DROP FOREIGN KEY `fk_ci_booking_scope`;
ALTER TABLE `customer_identities` DROP FOREIGN KEY `fk_ci_customer_tenant`;
ALTER TABLE `customer_identities` DROP FOREIGN KEY `fk_ci_property_tenant`;

-- rooms / room_categories / properties / user_property_access / customers
ALTER TABLE `rooms` DROP FOREIGN KEY `fk_room_category_scope`;
ALTER TABLE `rooms` DROP FOREIGN KEY `fk_room_property`;
ALTER TABLE `room_categories` DROP FOREIGN KEY `fk_category_property`;
ALTER TABLE `properties` DROP FOREIGN KEY `fk_property_created_by`;
ALTER TABLE `properties` DROP FOREIGN KEY `fk_property_tenant`;
ALTER TABLE `user_property_access` DROP FOREIGN KEY `fk_upa_assigned_by`;
ALTER TABLE `user_property_access` DROP FOREIGN KEY `fk_upa_property_tenant`;
ALTER TABLE `user_property_access` DROP FOREIGN KEY `fk_upa_user_tenant`;
ALTER TABLE `customers` DROP FOREIGN KEY `fk_customer_tenant`;

-- users / tenants / otp_requests
ALTER TABLE `users` DROP FOREIGN KEY `fk_user_created_by`;
ALTER TABLE `users` DROP FOREIGN KEY `fk_user_tenant`;
ALTER TABLE `tenants` DROP FOREIGN KEY `fk_tenant_created_by`;
ALTER TABLE `otp_requests` DROP FOREIGN KEY `fk_otp_user`;

-- CHECK constraint (users role/plant rule)
ALTER TABLE `users` DROP CONSTRAINT `chk_users_role_tenant`;

-- Verification: ye query 0 rows return karni chahiye
SELECT COUNT(*) AS remaining_fk_or_check
FROM information_schema.TABLE_CONSTRAINTS
WHERE CONSTRAINT_SCHEMA = 'stay_management'
  AND CONSTRAINT_TYPE IN ('FOREIGN KEY','CHECK');
