# stay_management → dream_project Naming Alignment Plan

> **STATUS: ✅ IMPLEMENTED & VERIFIED (live DB migrated, application layer updated).**
> Backup: `backup_before_migration.sql` (isi folder me). Rollback: dump restore karke
> git se models/controllers wapas lana.
>
> **PHASE 2 (Soft-Delete): ✅ DONE — `05_soft_delete.sql`**
> - `customer_identities` me `status` column add hua (0 = removed, 1 = active)
> - `active_flag` virtual column (`IF(status=1,1,NULL)`) se unique keys ab sirf
>   ACTIVE rows par lagti hain — deleted records ka phone/room_no/code/mobile dobara
>   use ho sakta hai, lekin active duplicates kabhi nahi ban sakte
>   (customers, properties, rooms, room_categories, users)
> - Saare delete endpoints ab sirf status=0 karte hain; koi bhi row hard-delete nahi hoti
> - Identity document files disk par kabhi delete nahi hoti
> - Deleted rows default lists se hidden; Status filter (Active/Inactive/All) se dikhte hain
> - Tests: `tests/soft_delete_test.php` (33 checks) + isolation test 54 checks — sab PASS

## Implementation Summary (jo ho gaya)

1. **DB migration** — 01→03 scripts live DB par chale; 04_verify sab PASS.
   (Fix note: `room_amenities` ke `fk_ra_room`/`fk_ra_amenity` FKs pehle 01 me miss the;
   `02b_continue.sql` unke drop + remaining sections ke saath complete hua.)
2. **Snapshot regenerated** — `db/stay_management.sql` ab migrated schema ka fresh dump hai.
3. **Application layer** — models naye column/table names par; SELECT aliases app-facing
   names (`id`, `tenant_id`, `is_active`, `pincode`, `status_id`) return karte hain taaki
   controllers/views/JS unchanged rahen aur behavior identical ho. Write paths model
   boundaries par keys translate karti hain.
4. **Tests updated** — `tests/multitenancy_isolation_test.php` fixtures/SQL naye naam par.
5. **Verification** — integration test 54/54 PASS; HTTP page-matrix baseline ke barabar
   (super: sab 200 · admin: ops 200, /admins 403 · user: ops 200, mgmt 403); row counts preserved.

## Goal

`stay_management` database ka structure/naming `dream_project.sql` jaise conventions par lana,
jisme **admin account (tenants) hi `plants` banega** — plant apne users create karega
(dream_project me jaise plant ke users hote hain).

## Final Hierarchy

```
super_admin  (role enum, unchanged)
   |
 plants      (ex-tenants)  -- admin account = plant; plant creates users & properties
   |
 properties  (hotel units under a plant)
   |
 rooms / room_categories / customers / booking_details / customer_identities
```

---

## Naming Rules Applied (dream_project style)

| Rule | dream example | Applied here as |
|---|---|---|
| Plant reference column | `fk_plant int NOT NULL` | har purani `tenant_id` → `fk_plant` |
| Master PK | `<entity>_id int` | `plant_id`, `user_id`, `sd_id` |
| Generic entity PK | plain `id int` | customers, rooms, properties, gst_rates, mobile_otp |
| Status flag | `status tinyint(1)` / `<prefix>_status int` | see per-table map |
| Audit by/date | `added_by int` / `<prefix>_ad_by`+`<prefix>_ad_dt` | see per-table map |
| Money | `decimal(10,2)` | selling_price, amounts |
| Rates | `decimal(5,2)` | gst rate |
| Engine | InnoDB utf8mb4_general_ci | sab tables (dream ke naye tables jaisa) |

**PK types:** sabhi `bigint(20) unsigned` → `int`.

---

## Table Rename Map

| Current table | New table | Dream counterpart |
|---|---|---|
| `tenants` | **`plants`** | `plants` (exact) |
| `users` | `users` | `users` (exact) |
| `otp_requests` | **`mobile_otp`** | `mobile_otp` (exact) |
| `status_master` | **`status_details`** | `status_details` (exact) |
| `taxes` | **`gst_rates`** | `gst_rates` (exact) |
| `properties` | `properties` | no equivalent (kept) |
| `user_property_access` | same | ~`user_other_plants` (ours better/normalized) |
| `customers` | same | ~`patients` (domain differs: hotel guest) |
| `customer_identities` | same | none |
| `rooms` | same | none |
| `room_categories` | same | ~`product_categories` pattern |
| `room_amenities`, `amenities` | same | none |
| `booking_details` | same | ~`orderhead` (domain differs; NOT merged) |
| `booking_channels` | same | none |
| `state_details` | same | already identical ✓ |

---

## Column-Level Mapping (old → new)

### 1. tenants → plants
| Old column | New column | Type |
|---|---|---|
| id bigint u/AI | **plant_id** int AI | int |
| name varchar(150) | **plant_name** varchar(255) | varchar(255) |
| is_active tinyint(1) | **plant_status** int DEFAULT 1 | int |
| created_by bigint u | **plant_ad_by** int NULL | int |
| created_at datetime | **plant_ad_dt** datetime DEFAULT CURRENT_TIMESTAMP | datetime |
| updated_at | updated_at *(retained for app)* | datetime |

### 2. users
| Old | New | Note |
|---|---|---|
| id | **user_id** int AI | dream exact |
| name varchar(150) | name varchar(200) | dream patients.name=200 |
| tenant_id | **fk_plant** int NULL | super_admin me NULL |
| is_active | **status** tinyint(1) | |
| created_by | **added_by** int | dream invoice.added_by style |
| mobile_no, role, last_login, created_at, updated_at | same | role enum unchanged |

### 3. properties
id→**property_id**, tenant_id→**fk_plant**, is_active→**status** tinyint(1), created_by→**added_by** int;
baaki (property_code, property_name, timestamps) same.

### 4. user_property_access
tenant_id→**fk_plant**; user_id, property_id, assigned_by, created_at same.

### 5. customers
tenant_id→**fk_plant**, pincode→**pin_code**, is_active→**status** tinyint(1),
customer_name varchar(150)→varchar(200); id/customer_code/phone/country/timestamps same.

### 6. customer_identities
tenant_id→**fk_plant**; baaki same.

### 7. rooms
selling_price decimal(12,2)→**decimal(10,2)**, is_active→**status** tinyint(1),
created_by varchar(20)→**added_by int**, updated_by varchar(20)→**updated_by int**.

### 8. room_categories
default_tax_id bigint u→int (naam same rakha — semantic clarity); baaki same.

### 9. booking_details
tenant_id→**fk_plant**, status_id→**sd_id** (status_details.sd_id ref),
total_amount/amount_paid/remaining_amount decimal(12,2)→**decimal(10,2)**.

### 10. booking_channels / amenities / room_amenities
Sirf type alignment (bigint→int). Naam same.

### 11. status_master → status_details
status_id→**sd_id**, status_name→**sd_name**, is_active→**sd_status** int,
NEW: sd_ad_by int NULL, sd_ad_dt datetime NULL; status_code/display_order retained.

### 12. taxes → gst_rates
tax_id→**id**, tax_percentage decimal(5,2)→**rate** decimal(5,2);
tax_name retained (UI ke liye extra); status already tinyint(1).

### 13. otp_requests → mobile_otp
is_verified→**status** tinyint(1); user_id FK, otp varchar(6), expires_at, attempts, created_at retained
(hamare security columns behtar hain — attempts/is_verified tracking).

### 14. state_details
Structure already dream-exact. Recommended (optional step in script): MyISAM/latin1 → InnoDB/utf8mb4.

---

## Index / Constraint Naming

Sab keys drop ho kar clean dream-aligned naamon se recreate hongi:
`tenant` word wale naam → `plant`: e.g.
- `uq_users_id_tenant` → `uq_users_id_plant`
- `idx_users_tenant_role_active` → `idx_users_plant_role_status`
- `fk_user_tenant` → `fk_user_plant`
- `chk_users_role_tenant` → `chk_users_role_plant`
- `uq_bd_identity_scope (id,property_id,customer_id,fk_plant)` — structure same

Composite multitenancy keys (isolation design) **structure-wise unchanged** — sirf renamed column follow karti hain.

---

## Jo Intentionally NAHI Badla (decisions)

1. `role enum('super_admin','admin','user')` — user_type permission matrix nahi banayi.
2. `properties` level — plant ke neeche hotels; app ka property-switcher intact.
3. `booking_details` ko `orderhead/orderpos` me split NAHI kiya (hotel domain ≠ optical orders).
4. camelCase (`mobileNo`) adopt nahi kiya — snake_case maintained.
5. OTP security columns (attempts, user_id FK) retained.
6. Engine InnoDB everywhere; MyISAM/latin1 adopt nahi kiya.

---

## Execution Order (jab approve ho)

```
0. Backup: mysqldump stay_management > backup_before_plants_alignment.sql
1. 01_drop_constraints.sql   (saare FKs + CHECK drop)
2. 02_rename_tables_columns.sql  (RENAME TABLE + CHANGE COLUMN)
3. 03_rebuild_keys.sql       (indexes + unique + FK + CHECK wapas)
4. 04_verify.sql             (verification queries)
5. Phase-2 (alag kaam): application code update
   - models/controllers/views me old column/table names (~700 refs: tenant_id 348,
     property_id 277, is_active 139, ...) 
   - tests/ update
```

⚠️ **Code impact warning:** SQL migration akela app ko tod dega jab tak application layer
(models/controllers/tests) new names par update na ho. Isliye SQL files abhi sirf READY hain —
run sirf code-update commit ke saath karna hai.

## Rollback

Migration se pehle ka dump restore karke original state wapas aa jayega.
