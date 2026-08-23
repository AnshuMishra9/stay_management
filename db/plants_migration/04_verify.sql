-- ============================================================================
-- 04_verify.sql
-- STEP 4/4 : Migration ke baad verification queries
-- EXPECTED : sab checks PASS
-- TIP      : Migration se PEHLE bhi ye chala kar counts note kar lo (baseline).
-- ============================================================================

-- 1) Old tables gone, new tables present
SELECT table_name FROM information_schema.TABLES
WHERE table_schema = 'stay_management'
  AND table_name IN ('tenants','status_master','taxes','otp_requests','plants','status_details','gst_rates','mobile_otp');
-- Expected: sirf plants / status_details / gst_rates / mobile_otp

-- 2) fk_plant columns kahin missing na ho (6 tables me hona chahiye)
SELECT table_name, column_name, column_type
FROM information_schema.COLUMNS
WHERE table_schema = 'stay_management'
  AND column_name = 'fk_plant'
ORDER BY table_name;
-- Expected: users, properties, user_property_access, customers,
--           customer_identities, booking_details

-- 3) tenant_id kahin bacha na ho
SELECT COUNT(*) AS leftover_tenant_id_columns
FROM information_schema.COLUMNS
WHERE table_schema = 'stay_management' AND column_name = 'tenant_id';
-- Expected: 0

-- 4) bigint(20) unsigned bacha na ho (sab int ho chuke)
SELECT COUNT(*) AS leftover_bigint_columns
FROM information_schema.COLUMNS
WHERE table_schema = 'stay_management'
  AND data_type = 'bigint';
-- Expected: 0

-- 5) Row counts baseline vs after (manually compare with pre-migration values)
SELECT 'plants' t, COUNT(*) c FROM plants
UNION ALL SELECT 'users', COUNT(*) FROM users
UNION ALL SELECT 'properties', COUNT(*) FROM properties
UNION ALL SELECT 'user_property_access', COUNT(*) FROM user_property_access
UNION ALL SELECT 'customers', COUNT(*) FROM customers
UNION ALL SELECT 'customer_identities', COUNT(*) FROM customer_identities
UNION ALL SELECT 'rooms', COUNT(*) FROM rooms
UNION ALL SELECT 'room_categories', COUNT(*) FROM room_categories
UNION ALL SELECT 'booking_details', COUNT(*) FROM booking_details
UNION ALL SELECT 'booking_channels', COUNT(*) FROM booking_channels
UNION ALL SELECT 'status_details', COUNT(*) FROM status_details
UNION ALL SELECT 'gst_rates', COUNT(*) FROM gst_rates
UNION ALL SELECT 'mobile_otp', COUNT(*) FROM mobile_otp
UNION ALL SELECT 'amenities', COUNT(*) FROM amenities
UNION ALL SELECT 'state_details', COUNT(*) FROM state_details;

-- 6) FK/CHECK constraints wapas aa gaye?
SELECT table_name, constraint_name, constraint_type
FROM information_schema.TABLE_CONSTRAINTS
WHERE table_schema = 'stay_management'
  AND constraint_type IN ('FOREIGN KEY','CHECK')
ORDER BY table_name;

-- 7) Spot check — key structures
SHOW CREATE TABLE `plants`;
-- SHOW CREATE TABLE `users`;
-- SHOW CREATE TABLE `booking_details`;

-- 8) Data sanity: har admin/plant ka data abhi bhi isolated & linked hai
SELECT p.plant_id, p.plant_name, u.user_id, u.role
FROM plants p JOIN users u ON u.fk_plant = p.plant_id
ORDER BY p.plant_id, u.user_id;
