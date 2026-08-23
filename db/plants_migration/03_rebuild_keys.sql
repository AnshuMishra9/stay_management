-- ============================================================================
-- 03_rebuild_keys.sql
-- STEP 3/4 : Indexes, UNIQUE keys, FOREIGN KEYs aur CHECK wapas — clean naamon se
-- PRE      : 01 + 02 chal chuke hon
-- ORDER    : Pehle saare UNIQUE/KEY (composite FK targets), phir FOREIGN KEYs
-- ============================================================================

-- ---------------------------------------------------------------------------
-- A) RENAMED / NEW INDEXES + UNIQUE KEYS  (purane naam wale drop, naye add)
-- ---------------------------------------------------------------------------
ALTER TABLE `plants`
  ADD KEY `idx_plant_status_name` (`plant_status`,`plant_name`),
  ADD KEY `idx_plant_ad_by` (`plant_ad_by`);

ALTER TABLE `users`
  DROP KEY `uq_users_id_tenant`,
  ADD UNIQUE KEY `uq_users_id_plant` (`user_id`,`fk_plant`),
  DROP KEY `idx_users_tenant_role_active`,
  ADD KEY `idx_users_plant_role_status` (`fk_plant`,`role`,`status`);

ALTER TABLE `properties`
  DROP KEY `uq_property_tenant_code`,
  ADD UNIQUE KEY `uq_property_plant_code` (`fk_plant`,`property_code`),
  DROP KEY `uq_property_id_tenant`,
  ADD UNIQUE KEY `uq_property_id_plant` (`property_id`,`fk_plant`),
  DROP KEY `idx_property_tenant_active_name`,
  ADD KEY `idx_property_plant_status_name` (`fk_plant`,`status`,`property_name`);

ALTER TABLE `user_property_access`
  DROP KEY `idx_upa_tenant_property`,
  ADD KEY `idx_upa_plant_property` (`fk_plant`,`property_id`);

ALTER TABLE `customers`
  DROP KEY `uq_customer_tenant_phone`,
  ADD UNIQUE KEY `uq_customer_plant_phone` (`fk_plant`,`phone`),
  DROP KEY `idx_customer_tenant_active_name`,
  ADD KEY `idx_customer_plant_status_name` (`fk_plant`,`status`,`customer_name`);

ALTER TABLE `customer_identities`
  DROP KEY `idx_ci_tenant_customer`,
  ADD KEY `idx_ci_plant_customer` (`fk_plant`,`customer_id`);

ALTER TABLE `rooms`
  DROP KEY `idx_room_property_active_category`,
  ADD KEY `idx_room_property_status_category` (`property_id`,`status`,`category_id`);
  -- uq_room_code / uq_room_no / uq_room_id_property / fk_room_category_scope key: auto-updated

ALTER TABLE `booking_details`
  DROP KEY `idx_bd_status_id`,
  ADD KEY `idx_bd_sd` (`sd_id`),
  DROP KEY `idx_bd_property_status_date`,
  ADD KEY `idx_bd_property_sd_date` (`property_id`,`sd_id`,`scheduled_check_in_date`),
  DROP KEY `idx_bd_tenant_customer`,
  ADD KEY `idx_bd_plant_customer` (`fk_plant`,`customer_id`);
  -- uk_booking_number / uq_bd_identity_scope: auto-updated, naam same rakha

-- ---------------------------------------------------------------------------
-- B) FOREIGN KEYS — parents first (plants/users), phir children
-- ---------------------------------------------------------------------------
-- plants <-> users (circular pair, dono unique/PK ready hain)
ALTER TABLE `users`
  ADD CONSTRAINT `fk_user_plant`    FOREIGN KEY (`fk_plant`)   REFERENCES `plants` (`plant_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_user_added_by` FOREIGN KEY (`added_by`)   REFERENCES `users` (`user_id`)   ON UPDATE CASCADE;

ALTER TABLE `plants`
  ADD CONSTRAINT `fk_plant_ad_by` FOREIGN KEY (`plant_ad_by`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE;

-- properties
ALTER TABLE `properties`
  ADD CONSTRAINT `fk_property_plant`     FOREIGN KEY (`fk_plant`)  REFERENCES `plants` (`plant_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_property_added_by`  FOREIGN KEY (`added_by`)  REFERENCES `users` (`user_id`)   ON UPDATE CASCADE;

-- user_property_access (composite isolation FKs)
ALTER TABLE `user_property_access`
  ADD CONSTRAINT `fk_upa_user_plant`     FOREIGN KEY (`user_id`, `fk_plant`)     REFERENCES `users` (`user_id`, `fk_plant`)           ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_upa_property_plant` FOREIGN KEY (`property_id`, `fk_plant`) REFERENCES `properties` (`property_id`, `fk_plant`)  ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_upa_assigned_by`    FOREIGN KEY (`assigned_by`)             REFERENCES `users` (`user_id`)                       ON DELETE RESTRICT ON UPDATE CASCADE;

-- customers
ALTER TABLE `customers`
  ADD CONSTRAINT `fk_customer_plant` FOREIGN KEY (`fk_plant`) REFERENCES `plants` (`plant_id`) ON UPDATE CASCADE;

-- room_categories -> properties
ALTER TABLE `room_categories`
  ADD CONSTRAINT `fk_category_property` FOREIGN KEY (`property_id`) REFERENCES `properties` (`property_id`) ON UPDATE CASCADE;

-- rooms
ALTER TABLE `rooms`
  ADD CONSTRAINT `fk_room_property`       FOREIGN KEY (`property_id`)           REFERENCES `properties` (`property_id`)              ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_room_category_scope` FOREIGN KEY (`category_id`, `property_id`) REFERENCES `room_categories` (`category_id`, `property_id`) ON UPDATE CASCADE;

-- booking_details
ALTER TABLE `booking_details`
  ADD CONSTRAINT `fk_bd_property_plant`        FOREIGN KEY (`property_id`, `fk_plant`)      REFERENCES `properties` (`property_id`, `fk_plant`)          ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_bd_customer_plant`        FOREIGN KEY (`customer_id`, `fk_plant`)      REFERENCES `customers` (`id`, `fk_plant`)                    ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_bd_room_property`         FOREIGN KEY (`room_id`, `property_id`)       REFERENCES `rooms` (`id`, `property_id`)                     ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_bd_room_category_property` FOREIGN KEY (`room_category_id`, `property_id`) REFERENCES `room_categories` (`category_id`, `property_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_bd_channel`               FOREIGN KEY (`booking_channel_id`)           REFERENCES `booking_channels` (`channel_id`)                 ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_bd_sd`                    FOREIGN KEY (`sd_id`)                        REFERENCES `status_details` (`sd_id`)                        ON UPDATE CASCADE;

-- customer_identities (4-column composite scope)
ALTER TABLE `customer_identities`
  ADD CONSTRAINT `fk_ci_property_plant` FOREIGN KEY (`property_id`, `fk_plant`)                              REFERENCES `properties` (`property_id`, `fk_plant`)                          ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ci_customer_plant` FOREIGN KEY (`customer_id`, `fk_plant`)                              REFERENCES `customers` (`id`, `fk_plant`)                                    ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ci_booking_scope`  FOREIGN KEY (`booking_id`, `property_id`, `customer_id`, `fk_plant`) REFERENCES `booking_details` (`id`, `property_id`, `customer_id`, `fk_plant`) ON UPDATE CASCADE;

-- mobile_otp
ALTER TABLE `mobile_otp`
  ADD CONSTRAINT `fk_otp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE;

-- room_amenities (pehle se theek; agar missing ho to)
-- ALTER TABLE `room_amenities`
--   ADD CONSTRAINT `fk_ra_room`    FOREIGN KEY (`room_id`)    REFERENCES `rooms` (`id`) ON DELETE CASCADE,
--   ADD CONSTRAINT `fk_ra_amenity` FOREIGN KEY (`amenity_id`) REFERENCES `amenities` (`amenity_id`) ON DELETE CASCADE;

-- ---------------------------------------------------------------------------
-- C) CHECK constraint — users role/plant rule (renamed)
-- ---------------------------------------------------------------------------
ALTER TABLE `users`
  ADD CONSTRAINT `chk_users_role_plant` CHECK (
    `role` = 'super_admin' AND `fk_plant` IS NULL
    OR `role` IN ('admin','user') AND `fk_plant` IS NOT NULL
  );

-- ---------------------------------------------------------------------------
-- D) OPTIONAL — state_details engine/collation modernization (dream-modern style)
--    Chalana optional hai; data same rehta hai.
-- ---------------------------------------------------------------------------
-- ALTER TABLE `state_details` ENGINE=InnoDB, CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
