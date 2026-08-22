# Admin and multi-property migration

`add_admin_multitenancy.sql` upgrades an existing Stay Management database in
place. It is repeat-safe, but a database and `secure_uploads/` backup is still
required before the first production run.

## Existing database

1. Apply older feature migrations first, including
   `add_booking_identity_scope.sql` when the target schema does not yet have
   `customer_identities.booking_id`.
2. Back up the selected database and the complete `secure_uploads/` folder.
3. Run `add_admin_multitenancy.sql` against that database.
4. Confirm every verification query printed at the end reports zero null scope
   and zero cross-scope violations.
5. Sign in through the unchanged mobile OTP screen and verify Legacy Property
   before creating additional admins or hotels.

The deterministic legacy mapping is:

- `9876543210`: active Super Admin
- `9988776655`: active Legacy Admin, owner of Legacy Account
- `9123456789`: inactive Legacy User, assigned to Legacy Property

All pre-migration rooms, customers, bookings, categories, and documents are
backfilled into Legacy Account / Legacy Property. Historical
`booking_details.property_name` text is preserved but is never used for
authorization.

## Fresh installation

`stay_management.sql` is the self-contained full snapshot. It drops and
recreates the `stay_management` database, so it must not be run over an
existing installation that needs to retain data.

## Runtime boundaries

- Super Admin can manage every admin/property and operate one selected active
  property at a time.
- An Admin can manage only its tenant, properties, and hotel users.
- A User can operate only explicitly assigned active properties.
- Customer profiles are tenant-shared; bookings, rooms, categories, inventory,
  and identity documents are active-property scoped.
- Operational models always receive explicit tenant/property ids. A missing
  property never means “all properties.”

