-- ============================================================
--  DUMMY / TEST DATA — Booking Details
--  Purpose: 14 sample customers with varied booking data so the
--  Booking Details page (customers/bookings) filters can be tested:
--    • booking_status: checked_out / checked_in / confirmed /
--                      enquiry / cancelled / no_show
--    • different booking channels (Walk-in, OTAs, Corporate, Agent)
--    • check-in / check-out dates spread over Jun–Aug 2026
--
--  "Today" reference for this data set: 2026-07-09
--    - stays before  09 Jul → checked_out
--    - stays around  09 Jul → checked_in (currently staying)
--    - stays after   09 Jul → confirmed / enquiry
--
--  Codes used: CUST00007 … CUST00020  (existing rows go up to CUST00006).
--  To REMOVE this dummy data later, run the DELETE at the bottom.
-- ============================================================
USE `stay_management`;

INSERT INTO `customers`
(`customer_code`, `customer_name`, `owner_name`, `phone`, `alt_phone`, `email`, `customer_type`,
 `city`, `district`, `state`, `country`, `is_active`,
 `booking_channel_id`, `booking_status`, `booking_by`, `guest_name`, `guest_mobile_no`, `property_name`,
 `scheduled_check_in_date`, `scheduled_check_out_date`, `length_of_stay`, `checked_in_at`, `checked_out_at`,
 `total_guest`, `room_category_id`, `room_quantity`, `total_amount`, `amount_paid`, `remaining_amount`, `created_at`)
VALUES
-- ---- Past stays (checked out) ----
('CUST00007','Aarav Sharma','Aarav Sharma','9810012341','9810012342','aarav@example.com','Retail Customer',
 'Mysore','Mysore','Karnataka','India',1,
 1,'checked_out','Aarav Sharma','Aarav Sharma','9810012341','Grand Palace Inn',
 '2026-06-20','2026-06-23',3,'2026-06-20 13:00:00','2026-06-23 11:00:00',
 2,1,1,6000.00,6000.00,0.00,'2026-06-18 09:00:00'),

('CUST00008','Isha Verma','Isha Verma','9820022342',NULL,'isha@example.com','Retail Customer',
 'Jaipur','Jaipur','Rajasthan','India',1,
 2,'checked_out','Isha Verma','Isha Verma','9820022342','Pink City Residency',
 '2026-06-28','2026-07-02',4,'2026-06-28 14:00:00','2026-07-02 10:30:00',
 3,2,1,12000.00,12000.00,0.00,'2026-06-25 09:00:00'),

('CUST00018','Divya Menon','Divya Menon','9840042343',NULL,'divya@example.com','Wholesale Customer',
 'Coimbatore','Coimbatore','Tamil Nadu','India',1,
 9,'checked_out','Divya Menon','Divya Menon','9840042343','Kongu Comforts',
 '2026-07-01','2026-07-04',3,'2026-07-01 13:30:00','2026-07-04 11:00:00',
 2,3,1,15000.00,15000.00,0.00,'2026-06-29 09:00:00'),

-- ---- Currently staying (checked in) ----
('CUST00009','Rohan Mehta','Rohan Mehta','9830032341','9830032343','rohan@example.com','Retail Customer',
 'Panaji','North Goa','Goa','India',1,
 4,'checked_in','Rohan Mehta','Rohan Mehta','9830032341','Sea Breeze Resort',
 '2026-07-06','2026-07-12',6,'2026-07-06 15:00:00',NULL,
 2,4,1,24000.00,10000.00,14000.00,'2026-07-02 09:00:00'),

('CUST00010','Priya Nair','Priya Nair','9840012344',NULL,'priya@example.com','Corporate',
 'Kochi','Ernakulam','Kerala','India',1,
 5,'checked_in','Priya Nair','Priya Nair','9840012344','Backwater Suites',
 '2026-07-08','2026-07-16',8,'2026-07-08 12:30:00',NULL,
 4,3,2,32000.00,16000.00,16000.00,'2026-07-03 09:00:00'),

('CUST00019','Aditya Nanda','Aditya Nanda','9850052341','9850052342','aditya@example.com','Corporate',
 'Udaipur','Udaipur','Rajasthan','India',1,
 15,'checked_in','Aditya Nanda','Aditya Nanda','9850052341','Lake View Haveli',
 '2026-07-09','2026-07-14',5,'2026-07-09 12:00:00',NULL,
 4,4,2,45000.00,15000.00,30000.00,'2026-07-04 09:00:00'),

-- ---- Upcoming (confirmed) ----
('CUST00011','Karan Singh','Karan Singh','9811012345',NULL,'karan@example.com','Retail Customer',
 'New Delhi','New Delhi','Delhi','India',1,
 3,'confirmed','Karan Singh','Karan Singh','9811012345','Capital Stay',
 '2026-07-11','2026-07-13',2,NULL,NULL,
 2,1,1,5000.00,2000.00,3000.00,'2026-07-05 09:00:00'),

('CUST00012','Ananya Iyer','Ananya Iyer','9822012346',NULL,'ananya@example.com','Retail Customer',
 'Chennai','Chennai','Tamil Nadu','India',1,
 11,'confirmed','Ananya Iyer','Ananya Iyer','9822012346','Marina Comforts',
 '2026-07-14','2026-07-18',4,NULL,NULL,
 2,2,1,14000.00,5000.00,9000.00,'2026-07-06 09:00:00'),

('CUST00013','Vikram Rao','Vikram Rao','9833012347','9833012348','vikram@example.com','Wholesale Customer',
 'Hyderabad','Hyderabad','Telangana','India',1,
 6,'confirmed','Vikram Rao','Vikram Rao','9833012347','Charminar Grand',
 '2026-07-20','2026-07-25',5,NULL,NULL,
 3,5,1,40000.00,20000.00,20000.00,'2026-07-07 09:00:00'),

('CUST00020','Nisha Reddy','Nisha Reddy','9860062341',NULL,'nisha@example.com','Corporate',
 'Visakhapatnam','Visakhapatnam','Andhra Pradesh','India',1,
 14,'confirmed','Govt Dept','Nisha Reddy','9860062341','Beachfront Executive',
 '2026-07-22','2026-07-24',2,NULL,NULL,
 2,2,1,10000.00,3000.00,7000.00,'2026-07-08 09:00:00'),

-- ---- Enquiries (future, not confirmed) ----
('CUST00014','Sneha Joshi','Sneha Joshi','9844012348',NULL,'sneha@example.com','Retail Customer',
 'Pune','Pune','Maharashtra','India',1,
 2,'enquiry','Sneha Joshi','Sneha Joshi','9844012348','Hillside Retreat',
 '2026-08-01','2026-08-05',4,NULL,NULL,
 2,2,1,15000.00,0.00,15000.00,'2026-07-08 09:00:00'),

('CUST00015','Arjun Kumar','Arjun Kumar','9855012349',NULL,'arjun@example.com','Corporate',
 'Bengaluru','Bengaluru','Karnataka','India',1,
 13,'enquiry','ABC Pvt Ltd','Arjun Kumar','9855012349','Tech Park Stay',
 '2026-08-10','2026-08-12',2,NULL,NULL,
 1,1,1,6000.00,0.00,6000.00,'2026-07-08 09:00:00'),

-- ---- Cancelled / No-show ----
('CUST00016','Meera Pillai','Meera Pillai','9846012350',NULL,'meera@example.com','Retail Customer',
 'Thiruvananthapuram','Thiruvananthapuram','Kerala','India',1,
 10,'cancelled','Meera Pillai','Meera Pillai','9846012350','Capital Comforts',
 '2026-07-15','2026-07-17',2,NULL,NULL,
 2,1,1,5000.00,1000.00,4000.00,'2026-07-06 09:00:00'),

('CUST00017','Rahul Gupta','Rahul Gupta','9812012351',NULL,'rahul@example.com','Retail Customer',
 'Agra','Agra','Uttar Pradesh','India',1,
 1,'no_show','Rahul Gupta','Rahul Gupta','9812012351','Taj Nagari Inn',
 '2026-07-05','2026-07-07',2,NULL,NULL,
 2,2,1,8000.00,8000.00,0.00,'2026-07-03 09:00:00');

-- ============================================================
--  CLEANUP — run this to remove the dummy rows when you're done:
--
--  DELETE FROM `customers` WHERE `customer_code` BETWEEN 'CUST00007' AND 'CUST00020';
-- ============================================================
