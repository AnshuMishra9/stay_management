-- ============================================================
--  DUMMY / TEST DATA — Customers + their Bookings
--  14 sample customers (CUST00007 … CUST00020) with varied bookings
--  so the Booking Details page (customers/bookings) filters can be tested:
--    • booking_status: checked_out / checked_in / confirmed /
--                      enquiry / cancelled / no_show
--    • different booking channels (Walk-in, OTAs, Corporate, Agent)
--    • check-in / check-out dates spread over Jun–Aug 2026
--    • a few bookings have an ALLOTTED room (room_id) to show that flow
--
--  Bookings live in `booking_details` (one customer -> many bookings).
--  Import AFTER: customers.sql, rooms.sql, booking_details_table.sql,
--  booking_channels.sql.
--
--  "Today" reference for this data set: 2026-07-09
--  To REMOVE this dummy data later, run the DELETE at the bottom
--  (booking rows cascade-delete with their customer).
-- ============================================================
USE `stay_management`;

-- ---- Customers (identity only; contact/address fields were removed) ----
INSERT INTO `customers` (`customer_code`, `customer_name`, `phone`, `country`, `is_active`) VALUES
    ('CUST00007','Aarav Sharma', '9810012341','India',1),
    ('CUST00008','Isha Verma',   '9820022342','India',1),
    ('CUST00009','Rohan Mehta',  '9830032341','India',1),
    ('CUST00010','Priya Nair',   '9840012344','India',1),
    ('CUST00011','Karan Singh',  '9811012345','India',1),
    ('CUST00012','Ananya Iyer',  '9822012346','India',1),
    ('CUST00013','Vikram Rao',   '9833012347','India',1),
    ('CUST00014','Sneha Joshi',  '9844012348','India',1),
    ('CUST00015','Arjun Kumar',  '9855012349','India',1),
    ('CUST00016','Meera Pillai', '9846012350','India',1),
    ('CUST00017','Rahul Gupta',  '9812012351','India',1),
    ('CUST00018','Divya Menon',  '9840042343','India',1),
    ('CUST00019','Aditya Nanda', '9850052341','India',1),
    ('CUST00020','Nisha Reddy',  '9860062341','India',1);

-- ---- Bookings (customer_id resolved from customer_code) ----
INSERT INTO `booking_details`
    (`booking_number`, `customer_id`, `booking_channel_id`, `booking_status`, `property_name`,
     `scheduled_check_in_date`, `scheduled_check_out_date`, `length_of_stay`,
     `checked_in_at`, `checked_out_at`, `total_guest`, `room_category_id`, `room_id`,
     `room_quantity`, `total_amount`, `amount_paid`, `remaining_amount`, `created_at`)
SELECT v.* FROM (
    -- Past stays (checked out)
    SELECT 'BKG00001' bn,(SELECT id FROM customers WHERE customer_code='CUST00007') cid, 1 ch,'checked_out' st,'Grand Palace Inn' pn,'2026-06-20' sin,'2026-06-23' sout,3 los,'2026-06-20 13:00:00' cin,'2026-06-23 11:00:00' cout,2 tg,1 rc, NULL rid,1 rq,6000.00 ta,6000.00 ap,0.00 ra,'2026-06-18 09:00:00' ca
    UNION ALL SELECT 'BKG00002',(SELECT id FROM customers WHERE customer_code='CUST00008'),2,'checked_out','Pink City Residency','2026-06-28','2026-07-02',4,'2026-06-28 14:00:00','2026-07-02 10:30:00',3,2,NULL,1,12000.00,12000.00,0.00,'2026-06-25 09:00:00'
    UNION ALL SELECT 'BKG00003',(SELECT id FROM customers WHERE customer_code='CUST00018'),9,'checked_out','Kongu Comforts','2026-07-01','2026-07-04',3,'2026-07-01 13:30:00','2026-07-04 11:00:00',2,3,NULL,1,15000.00,15000.00,0.00,'2026-06-29 09:00:00'
    -- Currently staying (checked in) — with an allotted room
    UNION ALL SELECT 'BKG00004',(SELECT id FROM customers WHERE customer_code='CUST00009'),4,'checked_in','Sea Breeze Resort','2026-07-06','2026-07-12',6,'2026-07-06 15:00:00',NULL,2,4,1,1,24000.00,10000.00,14000.00,'2026-07-02 09:00:00'
    UNION ALL SELECT 'BKG00005',(SELECT id FROM customers WHERE customer_code='CUST00010'),5,'checked_in','Backwater Suites','2026-07-08','2026-07-16',8,'2026-07-08 12:30:00',NULL,4,3,3,2,32000.00,16000.00,16000.00,'2026-07-03 09:00:00'
    UNION ALL SELECT 'BKG00006',(SELECT id FROM customers WHERE customer_code='CUST00019'),15,'checked_in','Lake View Haveli','2026-07-09','2026-07-14',5,'2026-07-09 12:00:00',NULL,4,4,5,2,45000.00,15000.00,30000.00,'2026-07-04 09:00:00'
    -- Upcoming (confirmed)
    UNION ALL SELECT 'BKG00007',(SELECT id FROM customers WHERE customer_code='CUST00011'),3,'confirmed','Capital Stay','2026-07-11','2026-07-13',2,NULL,NULL,2,1,NULL,1,5000.00,2000.00,3000.00,'2026-07-05 09:00:00'
    UNION ALL SELECT 'BKG00008',(SELECT id FROM customers WHERE customer_code='CUST00012'),11,'confirmed','Marina Comforts','2026-07-14','2026-07-18',4,NULL,NULL,2,2,NULL,1,14000.00,5000.00,9000.00,'2026-07-06 09:00:00'
    UNION ALL SELECT 'BKG00009',(SELECT id FROM customers WHERE customer_code='CUST00013'),6,'confirmed','Charminar Grand','2026-07-20','2026-07-25',5,NULL,NULL,3,5,NULL,1,40000.00,20000.00,20000.00,'2026-07-07 09:00:00'
    UNION ALL SELECT 'BKG00010',(SELECT id FROM customers WHERE customer_code='CUST00020'),14,'confirmed','Beachfront Executive','2026-07-22','2026-07-24',2,NULL,NULL,2,2,NULL,1,10000.00,3000.00,7000.00,'2026-07-08 09:00:00'
    -- Enquiries (future, not confirmed)
    UNION ALL SELECT 'BKG00011',(SELECT id FROM customers WHERE customer_code='CUST00014'),2,'enquiry','Hillside Retreat','2026-08-01','2026-08-05',4,NULL,NULL,2,2,NULL,1,15000.00,0.00,15000.00,'2026-07-08 09:00:00'
    UNION ALL SELECT 'BKG00012',(SELECT id FROM customers WHERE customer_code='CUST00015'),13,'enquiry','Tech Park Stay','2026-08-10','2026-08-12',2,NULL,NULL,1,1,NULL,1,6000.00,0.00,6000.00,'2026-07-08 09:00:00'
    -- Cancelled / No-show
    UNION ALL SELECT 'BKG00013',(SELECT id FROM customers WHERE customer_code='CUST00016'),10,'cancelled','Capital Comforts','2026-07-15','2026-07-17',2,NULL,NULL,2,1,NULL,1,5000.00,1000.00,4000.00,'2026-07-06 09:00:00'
    UNION ALL SELECT 'BKG00014',(SELECT id FROM customers WHERE customer_code='CUST00017'),1,'no_show','Taj Nagari Inn','2026-07-05','2026-07-07',2,NULL,NULL,2,2,NULL,1,8000.00,8000.00,0.00,'2026-07-03 09:00:00'
) v;

-- ============================================================
--  CLEANUP — remove the dummy rows when you're done
--  (booking_details rows cascade-delete with their customer):
--
--  DELETE FROM `customers` WHERE `customer_code` BETWEEN 'CUST00007' AND 'CUST00020';
-- ============================================================
