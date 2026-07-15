# Database — how to set up / sync

All scripts target the `stay_management` database (MySQL / MariaDB).

## A) You already have the database (sync after `git pull`)

Run the three **migration** scripts once. They are **idempotent** (safe to re-run —
already-applied changes become no-ops), so nothing breaks if you run them twice:

```
mysql -u root stay_management < db/drop_customer_contact_address_columns.sql
mysql -u root stay_management < db/booking_drop_guest_fields_add_room.sql
mysql -u root stay_management < db/rooms_simplify.sql
mysql -u root stay_management < db/migrate_customer_identities.sql
```

What they do:
- **drop_customer_contact_address_columns.sql** — removes the customer fields
  Alt Mobile No … Zip Code (`alt_phone, landline_no, email, address1, address2,
  city, district, state, zip_code`).
- **booking_drop_guest_fields_add_room.sql** — drops `booking_by, guest_name,
  guest_mobile_no, guest_contact_no` and adds `booking_details.room_id`
  (the allotted room, FK → `rooms.id`).
- **rooms_simplify.sql** — trims the `rooms` table (single name/number, single
  price, no occupancy/amenity/extra-pricing/effective/condition/phone/image
  columns) and switches Housekeeping to **Available / Not Available**.
- **migrate_customer_identities.sql** — moves the fixed Aadhar/PAN fields off
  `customers` into the new `customer_identities` table (Aadhar / PAN / Passport /
  Voter ID, each with a number + uploaded document) and drops the old columns.

After running these, your schema matches everyone else's.

## B) Fresh install (build the database from scratch)

Import in this order:

```
mysql -u root < db/stay_management.sql            # database + users/otp
mysql -u root < db/state_details.sql
mysql -u root < db/booking_channels.sql
mysql -u root < db/rooms.sql                      # categories, taxes, amenities, rooms
mysql -u root < db/customers.sql
mysql -u root < db/customer_identities.sql        # customer identity proofs
mysql -u root < db/booking_details_table.sql
mysql -u root < db/dummy_booking_data.sql         # optional demo bookings
```

`customers.sql`, `rooms.sql` and `booking_details_table.sql` already describe the
**current (final) schema**, so a fresh install needs no migration scripts.

## Historical (already applied — do NOT run on a fresh install)

These captured earlier schema steps and are kept only for reference:
`customer_booking_fields.sql`, `customer_checkin_status.sql`,
`drop_customer_booking_columns.sql`, `drop_customer_type_owner.sql`.
