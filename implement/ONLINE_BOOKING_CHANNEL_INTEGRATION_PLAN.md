# Online Booking Channels / OTA Integration Plan

> **Document status:** implementation blueprint only  
> **Prepared for:** current Stay Management application  
> **Prepared on:** 23 August 2026  
> **Application code changed while preparing this document:** No

## 1. Seedha answer

Haan, yeh feature possible hai. Booking.com, MakeMyTrip, Goibibo, Agoda, Expedia, Airbnb jaise online platforms se reservation application me aa sakti hai, new guest ka customer profile ban sakta hai, existing guest safely reuse ho sakta hai, aur offline booking hone par baaki connected platforms ki availability update ki ja sakti hai.

Lekin ise sirf `booking_channels` dropdown me API add karke safe tarah se implement nahi kiya ja sakta. Yeh **two-way PMS/channel-manager integration** hai:

1. Online platform se new/modified/cancelled reservation receive karna.
2. Us reservation ko correct plant/account, property, room category aur customer se map karna.
3. Local booking aur inventory ko atomic transaction me update karna.
4. Bachi hui availability ko sab connected platforms par publish karna.
5. Duplicate messages, delayed messages, API failure, cancellation, multi-room booking aur simultaneous offline/online booking ko safely handle karna.

Recommended first production approach:

- **Channel-manager-first integration**, jahan ek certified channel manager multiple OTAs ko connect kare.
- Application me provider-independent adapter layer rahe, taaki future me direct Booking.com/Expedia adapter bhi add ho sake.
- Phase 1 me **reservation import + availability sync** implement ho.
- Rates, restrictions, promotions aur payment-card handling ko separate later phase me rakha jaye.

“Sabhi online platforms” tabhi cover ho sakte hain jab selected channel manager un platforms ko support karta ho ya us OTA se direct commercial/API access milta ho. Har OTA ka ek common public universal API nahi hai.

---

## 2. Important domain clarification

### 2.1 Plant hotel nahi hai

Current application me:

- `plant` hidden owner/account/tenant boundary hai;
- `property` actual hotel hai;
- ek Admin/plant multiple properties own kar sakta hai;
- User ko selected properties assign hoti hain.

Isliye online integration **Admin ya plant ke naam par ek common connection** nahi hogi. Har connection ke authoritative scope me dono values rahengi:

```text
fk_plant + property_id
```

Ek Admin ke Hotel A aur Hotel B ke external hotel IDs, mappings, queues aur sync health completely separate honge. Provider ek API key se multiple hotels allow kare to encrypted credential account plant/system scope me safely reuse ho sakta hai, lekin har hotel ka connection aur authorization phir bhi explicit `fk_plant + property_id` row se hi resolve hoga.

### 2.2 “New user” ka meaning

Online reservation me aane wala guest application ka login `user` nahi banega. Woh `customers` table ka **customer/guest profile** hoga. Login roles (`super_admin`, `admin`, `user`) unchanged rahenge.

### 2.3 Room category ka exact role

Current decision preserve rahega:

- Room Master me room category optional reh sakti hai.
- Category ke bina room create aur offline operate kiya ja sakega.
- Current `/room-categories/*` compatibility sub-flow Room Master ke `Add Room Category` action se launch hota rahega; separate navbar/Manage item add nahi hoga.
- Lekin OTA physical room number `101` nahi, aam taur par room type/category jaise `Deluxe` ya `Suite` sell karta hai.
- Isliye **sirf online sell hone wale rooms ko mapped category me hona mandatory hoga**.
- Uncategorized rooms “Offline only” mark honge aur online inventory count me include nahi honge.

Channel setup page clearly batayega:

```text
3 uncategorized active rooms are excluded from online inventory.
```

---

## 3. Current application ka in-depth audit

### 3.1 Jo foundation already sahi hai

Current app me kaafi useful safety already available hai:

- CodeIgniter 3 + PHP + MySQL application.
- Super Admin → Admin/plant → Property User hierarchy.
- `customers` plant-scoped shared profiles hain.
- `booking_details`, rooms, categories aur documents property-scoped hain.
- Composite foreign keys cross-plant/property customer, room aur category relation reject karti hain.
- Admin own properties manage karta hai; User assigned properties hi access karta hai.
- Operational requests active property context me chalti hain.
- Stale property-context write 409 se reject hota hai.
- Customer/booking creation transaction me hoti hai.
- Existing manual booking writer plant → property → room → overlapping bookings ke stable lock order ka use karta hai.
- Booking statuses already present hain: `room_booked`, `checked_in`, `checked_out`, `cancelled`, `no_show`.
- Global `booking_channels` reference me Walk-in, MakeMyTrip, Goibibo, Booking.com, Agoda, Expedia, Hotels.com, Airbnb etc. already seeded hain.

Yeh `booking_channels` records abhi sirf booking-source labels aur commission defaults hain. Inme API credential, external hotel ID, room mapping, event revision ya sync status nahi hai.

### 3.2 Current customer behavior

Current `Customers::booking_save()` mobile number se plant ke andar customer reuse karta hai, warna new profile create karta hai. Yeh offline/manual workflow ke liye useful hai aur preserve hona chahiye.

OTA ke liye current rule directly sufficient nahi hai, kyunki:

- exact text match hota hai; E.164 normalized match nahi;
- `+9198...`, `9198...`, spaces aur local digits duplicate profiles bana sakte hain;
- inactive customer normal lookup me nahi milta;
- OTA phone masked, proxy, invalid ya missing ho sakta hai;
- online payload se existing trusted customer name/phone blindly overwrite nahi karna chahiye.

### 3.3 Sabse critical current inventory gap

Current Inventory calendar aur room lookup physical `room_id` booking ko block karte hain. `room_category_id` aur `room_quantity` fields hone ke bawajood, active booking me exact `room_id` null ho to current availability calculation use consume nahi karti.

OTA reservation normally aise aa sakti hai:

```text
Property: Hotel A
Room type: Deluxe
Quantity: 2
Physical room: not assigned yet
```

Agar ise current `booking_details` me directly save kar diya jaye to:

- booking list me record dikh sakta hai;
- lekin Inventory rooms ko available dikhati rahegi;
- offline desk same capacity dubara sell kar sakta hai;
- overbooking ho sakti hai.

Is gap ko fix kiye bina live OTA import enable nahi kiya jana chahiye.

### 3.4 Current schema ki OTA limitations

`booking_details` abhi ek customer, ek category, ek optional physical room aur quantity fields rakhta hai. Missing concepts:

- external reservation/confirmation ID;
- provider event ID aur revision/version;
- external hotel, room-type aur rate-plan ID;
- reservation origin and provider status;
- received/acknowledged/synced timestamps;
- sanitized/encrypted event audit and normalized snapshot;
- idempotency keys;
- currency, tax, commission and payment model details;
- multi-category/multi-room reservation units;
- per-night category allocation;
- sync error/attention state.

### 3.5 Current runtime integration gaps

Abhi application me nahi hain:

- public webhook controller;
- provider HTTP adapter/client;
- background worker;
- durable inbox/outbox queue;
- retry/dead-letter processing;
- periodic reservation/inventory reconciliation;
- encrypted integration secret storage;
- channel sync dashboard and alerts.

### 3.6 Read-only regression baseline

Plan banate samay current code ko modify kiye bina following baseline verify hua:

- multitenancy/isolation: **54 checks passed**;
- soft delete/history preservation: **33 checks passed**;
- identity upload security: **10 checks passed**;
- Inventory booking-details UI test: passed;
- responsive identity UI test: passed;
- role/navigation responsive result: passed for Super Admin, Admin and User, including User ke Manage-route denials.

Implementation ke baad yeh baseline compulsory regression gate rahega.

---

## 4. Platform connectivity reality

### 4.1 Direct OTA API

Direct integration technically possible hai, lekin har provider ka different contract, protocol, certification, rate limit, mapping model aur support process hota hai.

Examples:

- Booking.com Connectivity APIs reservation retrieval aur Rates & Availability provide karti hain, lekin Connectivity Partner onboarding/certification required hota hai.
- Expedia lodging connectivity me reservation aur availability/rates capabilities hain; adoption Technical Account Manager aur available partner capability par depend karti hai.
- Airbnb connected PMS/channel-manager software ko listing, pricing aur availability manage karne deta hai, but approved API program/access required hai.
- Agoda connection me property ID, room type IDs aur rate plan IDs ko selected channel manager ke saath map karna hota hai.
- MakeMyTrip public hotelier material channel-manager network show karta hai, par publicly open universal PMS booking API assume nahi ki ja sakti.
- Google Hotels primarily hotel pricing/content/ARI distribution flow hai. Booking kis OTA/booking engine par complete hoti hai, us source se reservation aayegi; Google ko automatically reservation webhook source nahi maana jayega.

### 4.2 Channel manager

Channel manager multiple OTA connections ko normalize karke PMS ko usually:

- booking feed/webhook;
- room type/rate plan mapping;
- availability/rate/restriction push;
- booking acknowledgement;
- sync errors and reconciliation support

provide karta hai.

Publicly documented example ke roop me Channex booking revisions, booking acknowledgement, webhooks aur ARI APIs expose karta hai. Yeh plan Channex ko final vendor lock nahi karta. MakeMyTrip/Goibibo/Agoda/Booking.com/Airbnb coverage, India support, pricing, PCI boundary, SLA aur sandbox verify karne ke baad vendor choose hoga.

### 4.3 iCal kyun sufficient nahi hai

iCal fallback kuch vacation-rental channels ke dates block kar sakta hai, lekin normally rich guest profile, amount, tax, room quantity, modification ordering, acknowledgement aur real-time pooled hotel inventory reliably provide nahi karta. Full requirement ke liye iCal primary solution nahi hoga.

### 4.4 Recommended decision

| Option | Delivery speed | Maintenance | Platform coverage | Recommendation |
|---|---:|---:|---:|---|
| One channel manager adapter | Faster | Medium | Vendor-dependent, often broad | **Phase 1 recommended** |
| Direct adapter per OTA | Slow | Very high | Only certified OTAs | Selective later |
| iCal only | Fast | Low | Limited | Fallback only |
| Manual external booking entry | Immediate | Manual effort | Any unsupported channel | Temporary safety fallback |

Before vendor selection, every existing property ka onboarding survey hoga:

- property kis-kis OTA par listed hai;
- currently kaunsa channel manager use ho raha hai;
- external property/hotel IDs;
- room type and rate plan IDs;
- future confirmed bookings count;
- payment model and currency;
- existing channel manager migrate hoga ya app chosen manager se connect karega.

Ek OTA listing ko ek time par do inventory authorities control nahi karengi.

---

## 5. Target architecture

```text
                   ┌─────────────────────────────┐
                   │ Booking.com / MMT / Agoda / │
                   │ Expedia / Airbnb / others   │
                   └──────────────┬──────────────┘
                                  │
                        certified connection
                                  │
                   ┌──────────────▼──────────────┐
                   │ Channel manager or direct   │
                   │ provider adapter            │
                   └──────────────┬──────────────┘
                                  │ webhook / feed
                   ┌──────────────▼──────────────┐
                   │ Authenticated public inbox  │
                   │ durable + idempotent        │
                   └──────────────┬──────────────┘
                                  │ background worker
          ┌───────────────────────▼────────────────────────┐
          │ Normalizer → Booking Service → Customer        │
          │ Resolver → Availability Service                │
          │ explicit fk_plant/property_id only             │
          └───────────┬──────────────────────┬──────────────┘
                      │ one DB transaction   │
             ┌────────▼─────────┐   ┌────────▼─────────────┐
             │ booking/customer│   │ inventory + outbox   │
             └──────────────────┘   └────────┬─────────────┘
                                             │ async worker
                                  ┌──────────▼─────────────┐
                                  │ Absolute availability  │
                                  │ to all active channels │
                                  └────────────────────────┘
```

### 5.1 Authority rules

To avoid update conflicts, authority explicitly defined hogi:

| Data | Authoritative source |
|---|---|
| Total physical room capacity | Stay Management Room Master |
| Maintenance/owner blocks | Stay Management |
| Pooled sellable inventory | Stay Management Availability Service |
| OTA-origin reservation creation/modification/cancellation | Origin OTA/provider feed |
| Walk-in/offline reservation | Stay Management |
| Physical room assignment | Stay Management hotel operations |
| Customer master verified local data | Stay Management |
| Reservation-time guest snapshot | Origin reservation payload |
| Rates/restrictions in Phase 1 | Existing OTA/channel extranet |
| Rates/restrictions after later phase | Explicitly selected rate authority |

Phase 1 me OTA-origin date/category/quantity ko local staff silently edit nahi karega. Staff notes, payment tracking aur physical room allotment local reh sakte hain. Provider modification/cancellation adapter through apply hogi.

---

## 6. Central pooled inventory: correct rule

### 6.1 “Har platform par minus one” nahi

Wrong approach:

```text
Offline booking hui → Booking.com -1 → Agoda -1 → MMT -1
```

Retry ya duplicate request hone par double decrement ho sakta hai. Out-of-order response inventory corrupt kar sakti hai.

Correct approach:

```text
Stay Management me authoritative remaining inventory calculate karo
→ affected dates ka absolute number har channel ko send karo
```

Example:

```text
Deluxe active online-eligible rooms = 5
Booking.com confirmed units         = 1
Offline confirmed units             = 1
Maintenance blocks                  = 0
Safety buffer                       = 0
Remaining sellable                  = 3

Publish changed dates:
Booking.com Deluxe = 3
Agoda Deluxe       = 3
MakeMyTrip Deluxe  = 3
Expedia Deluxe     = 3
```

Agoda par ek booking aur aayi to local remaining `2` hoga aur affected dates par sab platforms ko absolute `2` publish hoga.

### 6.2 Formula

Har `property + local room category + stay date` ke liye:

```text
sellable = max(
    0,
    base_online_pool
    - consuming_booking_units
    - maintenance_or_owner_blocks
    - safety_buffer
    + approved_overbook_limit
)
```

Locked defaults:

- `approved_overbook_limit = 0`;
- pilot me configurable safety buffer recommended `1`;
- stable reconciliation ke baad Admin explicitly buffer `0` kar sakta hai;
- checkout date exclusive hogi: `[check-in, check-out)`;
- `room_booked` aur `checked_in` consume karenge;
- `cancelled`, `no_show` aur completed past nights sellable future stock consume nahi karenge.

Optional channel cap ho to:

```text
published_to_channel = min(sellable, channel_cap)
```

Default pooled inventory hoga, separate allotment buckets nahi.

`base_online_pool` active, online-enabled rooms ko count karega **including temporarily blocked rooms**. Temporary maintenance/owner blocks sirf `maintenance_or_owner_blocks` me subtract honge. A blocked room ko base se exclude aur block count se subtract dono nahi kiya jayega.

### 6.3 Physical room and category hold

- Offline desk exact room 101 select kare: booking category ki one unit consume karegi aur room 101 specifically block hoga.
- OTA Deluxe booking aaye: one Deluxe unit consume hogi, `room_id` initially null reh sakta hai.
- Baad me room allot karne par category consumption dobara add nahi hogi; sirf existing unit ko physical room milega.
- Inventory calendar har category par `physical free`, `unassigned online holds` aur `sellable remaining` numbers separately dikhayega. Unassigned holds dedicated virtual rows/chips me dikhenge; individual room cell ko misleading “sellable” label nahi milega.
- Agar 5 physical rooms me 2 unassigned holds hain to staff kisi bhi free physical room ko allot kar sakta hai, lekin UI clearly sirf 3 new sellable slots batayegi. Exact room selector and final write shared category capacity recheck karenge.

### 6.4 Online eligibility

Shared physical online pool property/category level par exactly once calculate hoga. Connection mapping pool ko create/change nahi karegi; mapping sirf batayegi ki same pool kis external room type par publish hoga. Channel A unmap hone se Channel B ka pool change nahi hoga.

Room base online pool me tabhi count hoga jab:

- property active ho;
- plant/account active ho;
- room active ho;
- room ki active category ho;
- category ka `online_sellable` flag enabled ho;
- room ka optional online override use exclude na kare.

Schema/UI rule:

- `room_categories.online_sellable` default `0` add hoga, isliye migration se existing behavior ya external sale automatically start nahi hogi;
- `rooms.online_sellable` nullable override hoga: null = category default, `0` = exclude, `1` = include when category enabled;
- mapping wizard explicit preview/confirmation ke baad category online-enable karega;
- temporary maintenance base eligibility ko change nahi karegi; `inventory_blocks` separately subtract hoga.

Uncategorized room current offline workflow me valid rahega.

### 6.5 Undated consuming bookings

Current normal booking path scheduled dates ke bina a live `room_booked` record rakh sakta hai. Aisi row ke category/date allocations safely infer nahi kiye ja sakte.

Locked rule:

- mapping wizard kisi category ko online enable nahi karega jab us category me zero-date/invalid-date live consuming booking ho;
- Admin ko valid scheduled check-in/out add, booking cancel/no-show, ya category ko offline-only rakhna hoga;
- online-enabled category me every new consuming manual/OTA booking ke valid `[check-in, check-out)` dates server-side mandatory honge;
- unknown stay ko arbitrary one night ya complete future horizon maan kar silently block nahi kiya jayega.

---

## 7. Detailed event flows

### 7.1 New online booking

1. Provider signed webhook bhejta hai ya polling feed me revision available karta hai.
2. Public endpoint exact raw request ki authentication verify karta hai.
3. Event unique provider event ID/body hash ke saath durable inbox me insert hota hai.
4. Duplicate event ho to new business write ke bina success response milta hai.
5. Endpoint provider contract ke according jaldi acknowledge karta hai; heavy processing worker me hoti hai. Signature valid hone ke bawajood durable insert fail ho to `2xx` nahi, provider-retriable non-2xx/`503` return hoga. `2xx` sirf durable insert ya proven duplicate ke baad.
6. Property-specific webhook me stored connection, aur account-level webhook me authenticated provider account + external hotel ID mapping, authoritative `fk_plant/property_id` connection resolve karte hain. Payload ka koi local plant/property ID trust nahi hota.
7. External room type/rate plan mapping validate hoti hai.
8. Guest, dates, units, money, currency, source and status normalized hote hain.
9. Transaction plant → property → external reservation mutex/crosswalk lock karti hai, locked current revision se old/new ranges derive karti hai, phir category/date inventory rows stable order me lock karti hai.
10. Customer Resolver existing customer reuse ya new/incomplete OTA profile create karta hai.
11. External reservation ID/revision idempotently upsert hota hai.
12. Booking header, units, night allocations aur guest snapshot save hote hain.
13. Affected category/date ranges ke outbound availability jobs same transaction me insert hote hain.
14. Commit ke baad provider booking revision acknowledge hoti hai.
15. Worker changed dates ka absolute remaining count every active outbound **transport connection** ko push karta hai. Channel-manager connection ko one update milega aur woh mapped OTAs me fan-out karega; direct OTA connections ko separately update milega.
16. Booking list me channel badge, external confirmation and sync state dikhte hain.

If confirmed OTA reservation local calculated stock se zyada ho:

- reservation reject/delete nahi hogi;
- booking save hogi;
- `needs_attention/overbooked` flag lagega;
- availability zero publish hogi;
- Admin/operations ko critical alert milega.

Provider already guest ko confirm kar chuka ho sakta hai; inventory shortage notification ko silently reject karna safe behavior nahi hai.

### 7.2 Offline / walk-in booking

1. Current form and URL same rahenge.
2. Browser wrapper normalized command common Booking Service ko dega.
3. Transaction category/date state lock karegi.
4. Requested category capacity and, if selected, physical room overlap verify honge.
5. Current plant-scoped customer reuse/create behavior compatible form me chalega.
6. Booking/customer save honge.
7. Same transaction me changed dates ke outbox records create honge.
8. Local commit successful hote hi user ko success milega.
9. Background worker every active outbound transport connection ko updated absolute availability send karega; aggregator apne connected platforms ko fan-out karega.
10. UI `Synced`, `Sync pending`, ya `Needs attention` state show karegi.

Network call booking DB transaction ke andar nahi hogi. Provider slow/down hone par reception booking screen hang nahi hogi.

### 7.3 Booking modification

For date, category ya quantity change:

1. Plant/property ke baad external reservation mutex/version row pehle lock hogi. Locked current state se old aur incoming new ranges ka union derive hoga, phir union stable category/date order me lock hoga. Version transaction ke beech badli dikhe to rollback/retry mandatory hoga.
2. Provider revision older hai to ignore-but-audit hoga.
3. Existing newer/equal revision duplicate hai to no-op hoga.
4. Old allocations release aur new allocations reserve ek transaction me honge.
5. Assigned physical room incompatible ho to silently move nahi hoga; `room re-allocation required` attention state lagegi.
6. Old aur new affected dates ke absolute availability jobs create honge.

### 7.4 Cancellation and no-show

- External cancellation matching connection + reservation ID se hogi.
- Booking hard-delete nahi hogi.
- Status `cancelled` hoga, provider cancellation timestamp/reason audit me rahega.
- Consuming night allocations release hongi.
- Affected dates ki inventory republish hogi.
- Duplicate cancellation no-op hogi.
- Cancel-before-create/out-of-order case durable quarantine/tombstone se handle hoga; guessed booking create nahi hogi.
- `no_show` provider capability aur hotel policy ke according inventory release karega.
- Locally `checked_in` ya `checked_out` booking par late provider cancellation blindly apply nahi hogi. Event quarantine/attention me jayega; checked-out history kabhi rewrite/release nahi hogi. Partially checked-in multi-unit group me only unstarted units ke treatment ke liye explicit Admin resolution/provider policy lagegi.

### 7.5 Check-in and checkout

- Check-in booked state se checked-in state me move hai; same stay nights consume rahengi.
- Early checkout future remaining nights release kar sakta hai, explicit policy required.
- Extended stay new dates ko transactionally reserve karega; zero availability par warning/approval flow hoga.
- Physical room assignment existing operations me rahega, but shared Availability Service use karega.

### 7.6 Room/category changes

Following actions inventory events generate karenge:

- active mapped room create;
- room deactivate/reactivate;
- room category change;
- category deactivate/reactivate;
- mapping enable/disable;
- maintenance/owner block;
- property/plant/connection pause/resume.

Assigned User ke current Room Master/Room Category operational permissions preserve rahenge. Mapped room/category capacity-impacting change par server-side impact preview, confirmation, actor audit aur outbox event mandatory hoga; User credentials, mappings ya connection controls nahi dekh sakega.

Mapped category deactivation direct allow nahi hogi. Future reservation/mapping preflight hoga; Admin/Super ko pehle stop-sell/zero publish acknowledgement, future-booking resolution and mapping disable complete karna hoga. Tab category deactivation retry hogi. Unmapped category ka existing workflow unchanged rahega.

Category mapping change existing future reservations ke saath direct allow nahi hogi jab tak reconciliation pass na ho.

---

## 8. Customer reuse and profile policy

### 8.1 Matching order

Customer matching tenant/plant ke andar hi hogi:

1. Existing `(connection, external_guest_id)` crosswalk.
2. Valid normalized mobile number, including inactive profile lookup.
3. Verified normalized email only when unique and provider trust policy allows.
4. Otherwise new OTA customer profile.

Name-only matching kabhi automatic nahi hogi.

### 8.2 Phone normalization

- Raw phone reservation snapshot me preserve hoga.
- Customer master ke liye country-aware normalized value, preferably E.164, store hoga.
- Masked/proxy value ko real phone nahi maana jayega.
- Current offline UI mobile required rakhegi.
- OTA-created customer ke liye DB phone nullable hona zaruri ho sakta hai.
- Fake/shared placeholder phone number use nahi hoga, warna unrelated guests merge ho sakte hain.

### 8.3 Existing profile overwrite rule

Provider payload:

- verified local non-empty phone/name ko silently replace nahi karega;
- missing local field safely fill kar sakta hai when trust policy permits;
- booking-specific guest details `booking_contact_snapshot` me rahengi;
- mismatch ko review badge milega.

### 8.4 Incomplete OTA profile

Phone absent ho to:

- customer code and guest name ke saath `profile_completeness = incomplete_ota`;
- external guest mapping;
- booking contact snapshot;
- check-in UI me “Mobile number required/verify” prompt.

Check-in par entered phone existing customer se match kare to Phase 1 me automatic booking/customer relink nahi hoga. Current booking-scoped identity composite FKs aur “booking customer cannot change” rule preserve rahenge. System `customer_duplicate_candidates` review item banayega; verified contact reservation snapshot me save hogi. Separate audited merge/alias feature future me exact FK migration ke saath implement hue bina historical booking/document owner change nahi hoga.

### 8.5 Inactive customer

Normalized phone same plant me inactive profile se match kare to configured deliberate-new-booking policy ke according reactivate hoga. Duplicate active profile create nahi hona chahiye.

---

## 9. Proposed database design

All new property-specific tables redundant `fk_plant` and `property_id` carry karengi. Composite foreign keys DB level par cross-admin/property relation reject karengi. Operational and integration history hard-delete nahi hogi.

### 9.1 Global provider catalog

#### `integration_providers`

| Column | Purpose |
|---|---|
| `provider_id` | Primary key |
| `provider_code` | `channex`, `booking_com_direct`, etc.; unique |
| `provider_name` | Display label |
| `adapter_code` | Application adapter implementation |
| `integration_type` | `channel_manager` or `direct_ota` |
| `capabilities_json` | reservations, availability, rates, webhooks, polling |
| `status` | Enabled/disabled |
| timestamps | Audit |

This table me property credentials nahi honge.

### 9.2 Optional shared provider credential account

#### `integration_provider_accounts`

Provider ek credential se multiple hotels support kare to secret duplicate nahi hoga:

- `provider_account_id`;
- `provider_id`;
- `scope_type` (`plant` or tightly controlled `system`);
- plant scope me mandatory `fk_plant`;
- encrypted credential/account-level webhook secret envelope + key version;
- status/rotation/audit timestamps.

Plant-scoped account sirf same plant ki explicit property connections reference kar sakta hai. System-scoped PMS credential only Super/platform operations manage karega. Credential account se hotel scope infer nahi hoga; webhook/feed ka external hotel ID mandatory property connection se resolve hoga.

### 9.3 Property connection

#### `property_channel_connections`

| Column | Purpose |
|---|---|
| `connection_id` | Primary key |
| `fk_plant`, `property_id` | Immutable authoritative scope |
| `provider_id` | Transport provider/adapter |
| `provider_account_id` | Optional shared encrypted credential reference |
| `external_property_id` | Provider-side hotel/property ID |
| `environment` | sandbox/production |
| `connection_status` | draft/testing/active/paused/error/disconnected |
| `credential_ciphertext` | Optional property-specific encrypted credential override |
| `webhook_secret_ciphertext` | Encrypted, versioned secret |
| `secret_version` | Rotation support |
| `provider_timezone_metadata` | Provider-specific metadata only, not hotel authority |
| `sync_horizon_days` | Provider-specific future horizon |
| `inbound_enabled`, `outbound_enabled` | Independent kill switches |
| `last_inbound_at`, `last_outbound_at`, `last_reconciled_at` | Health |
| `last_error_code`, `last_error_at` | Diagnostics |
| `added_by`, timestamps, `status`, `active_flag` | Existing conventions |

Unique active key property + provider + external property par hoga. Secret kabhi UI me read-back nahi hoga.

Hotel operating timezone and default currency authoritative `properties.timezone_name` (IANA name) and `properties.currency_code` me add honge. All adapters provider timestamp ko property timezone me normalize karenge; multiple connections contradictory hotel timezones define nahi kar sakti.

### 9.4 OTA listings behind a connection

#### `property_channel_listings`

Aggregator connection ke andar Booking.com/Agoda/MMT listings represent karega:

- `listing_id`;
- connection + plant/property scope;
- global `booking_channel_id`;
- external channel/listing property ID;
- status and capabilities;
- last sync/error;
- unique active `(property_id, booking_channel_id)` to prevent two authorities for one OTA listing.

Direct adapter me one connection usually one listing hoga.

### 9.5 Room and rate mapping

#### `channel_room_type_mappings`

- connection/listing scope;
- local `room_category_id`;
- `external_room_type_id`;
- optional online room cap;
- safety buffer override;
- mapping status;
- last verified timestamp;
- composite FK enforcing same property.

#### `channel_rate_plan_mappings`

- connection/listing;
- local category;
- future local rate-plan ID;
- external rate-plan ID;
- occupancy/meal/cancellation metadata;
- status.

Phase 1 me rate-plan mapping reservation normalization ke liye read-only ho sakti hai. Full outbound price sync later phase me enable hogi.

### 9.6 Reservation grouping and units

#### `booking_units`

Recommended additive child model:

- `booking_unit_id`;
- booking + `customer_id` + plant/property scope;
- `unit_index`;
- local `room_category_id`;
- optional allotted `room_id`;
- adults/children;
- unit amount;
- unit lifecycle status and scheduled/actual check-in/out timestamps;
- unique booking/unit index.

One OTA reservation with two Deluxe and one Suite three booking-unit rows rakh sakti hai. Physical room later per unit assign hoga.

Existing manual booking ko exactly one unit milega, isliye current one-room routes/screens unchanged render kar sakte hain. Legacy `booking_details.room_id`, `room_category_id`, `room_quantity`, `total_unit` compatibility period me remain karenge; immediately drop nahi honge.

Multi-unit OTA booking same booking header ke detail page par unit list dikhayegi. Existing `/checkin/:bookingId`, edit and checkout route:

- sole unit ho to current screen directly kholegi;
- multiple units ho to same route ke andar unit selection/all-units action dikhayegi;
- room assignment, check-in, checkout, cancellation and no-show unit level par ho sakte hain;
- `booking_details.sd_id` compatibility summary derive hoga: all cancelled → cancelled, all checked-out → checked-out, any checked-in → checked-in, otherwise room-booked; partial/mixed state separate computed badge hoga, fabricated status master value nahi.

Booking-scoped identities group/customer level par rahengi, individual unit ko silently different customer nahi milega.

DB isolation ke liye `booking_units` redundant `customer_id`, `property_id`, `fk_plant` carry karega aur current `booking_details (id, property_id, customer_id, fk_plant)` composite unique key ko reference karega. Unit/category/room keys bhi same property ke composite FKs se enforce honge.

#### `booking_inventory_nights`

- booking unit;
- plant/property/category;
- `stay_date`;
- consuming quantity/status;
- released timestamp/reason;
- unique active unit/date allocation.

Yeh exact nights provide karega aur category holds ko current Inventory se invisible hone nahi dega.

### 9.7 Inventory state

#### `inventory_days`

Key: property + category + stay date.

Suggested fields:

- `total_online_units`;
- `reserved_units`;
- `blocked_units`;
- `safety_buffer`;
- `overbook_limit` default 0;
- `inventory_version`;
- timestamps.

Yeh materialized projection and deterministic `FOR UPDATE` mutex hoga. Authoritative booking allocations aur room counts se scheduled reconciliation isse rebuild/verify karegi.

#### `inventory_blocks`

- property/category/date range;
- quantity;
- reason (`maintenance`, `owner_hold`, `out_of_service`, etc.);
- optional physical room;
- status and audit.

Current `rooms.status` active/deactivated behavior preserve rahega. Temporary maintenance ke liye hard deactivation misuse nahi hogi.

### 9.8 External reservation crosswalk

#### `external_reservations`

- connection/listing + plant/property;
- `external_reservation_id`;
- internal `booking_id`;
- latest provider revision/version/update timestamp;
- external status;
- external confirmation number;
- source booked timestamp;
- currency, totals, commission summary;
- attention/overbook state;
- received/acknowledged timestamps;
- safe-default unique `(listing_id, external_reservation_id)`. Selected provider written contract connection-wide uniqueness guarantee kare tab adapter additionally connection-level key store kar sakta hai.

#### `external_reservation_revisions`

Append-only revision history:

- external reservation;
- event/revision ID;
- provider event time and received time;
- exact raw-body hash;
- encrypted sanitized payload reference; raw provider body ordinary DB/storage me retain nahi;
- processing result;
- no ordinary log exposure.

Ingress exact raw body memory me signature-verify karega, prohibited card fields drop/redact karega, phir only required PII payload encryption-at-rest ke saath persist karega. Provider configuration card/PAN/CVV delivery disable karegi; agar non-PCI feed guarantee nahi milti to connector enable nahi hoga. Remaining payload ke liye provider/legal retention TTL, scheduled purge, access audit, encrypted backups and backup-expiry deletion policy mandatory hongi. Raw hash audit/idempotency ke liye retain ho sakta hai.

### 9.9 Customer external identity and snapshot

#### `customer_external_identities`

- plant-scoped customer;
- listing/provider namespace;
- external guest ID;
- verified flags;
- safe-default unique listing/provider namespace + external guest ID.

#### `booking_contact_snapshots`

Reservation-time name, masked/raw contact metadata, address and provider-specific guest reference. This protects historical booking information when shared customer master later changes.

### 9.10 Durable integration queues

#### `integration_inbox_events`

- connection + plant/property;
- provider event ID;
- exact body hash;
- event type;
- received timestamp;
- processing status (`received`, `processing`, `done`, `retry`, `quarantined`, `dead`);
- attempts, lease, next attempt;
- sanitized error;
- unique listing/provider event key; fallback connection/listing + canonical digest.

#### `integration_outbox_jobs`

- connection/listing + scope;
- job type;
- category/date range;
- authoritative inventory version;
- coalescing/dedupe key;
- payload reference;
- status, lease, attempts, next attempt;
- created in same DB transaction as booking/inventory change.

#### `integration_attempts`

- inbox/outbox job;
- correlation ID;
- request start/end;
- HTTP/result code;
- redacted provider response/error;
- retry decision;
- no credentials, PAN, CVV or unmasked guest PII.

### 9.11 Existing table changes

#### `booking_details`

Additive columns:

- `source_type` (`manual`, `ota`, `import`);
- `booked_at`;
- `currency_code`;
- `reservation_group/version` linkage as needed;
- `attention_status`;
- optional source external reference display cache.

`property_name` historical snapshot authorization me use nahi hogi aur existing rows untouched rahengi.

#### `properties`, `room_categories`, `rooms`

- `properties.timezone_name` and `currency_code` authoritative hotel settings;
- `room_categories.online_sellable` default `0`;
- `rooms.online_sellable` nullable category override;
- migration defaults external sale start nahi karenge;
- shared pool mapping-independent rahega.

#### `customers`

Migration after code readiness:

- `phone_normalized` nullable;
- `phone` OTA profiles ke liye nullable, while offline form still requires it;
- `email` and normalized email nullable;
- `profile_source`;
- `profile_completeness`;
- trust/verification timestamps;
- active normalized-phone uniqueness only when non-null.

Fake phone se NOT NULL satisfy nahi kiya jayega.

### 9.12 Rate model: later phase

Current per-room selling price complete OTA rate-plan model nahi hai. Outbound rates ke liye later tables required hongi:

- `rate_plans`;
- `category_rate_plans`;
- `daily_rates_restrictions`;
- occupancy pricing;
- meal plan;
- cancellation policy;
- stop sell, CTA, CTD, min/max stay;
- taxes and fees.

Phase 1 me OTA-provided booking amount save hoga, lekin current `selling_price` ko universal OTA rate samajh kar push nahi kiya jayega.

---

## 10. Application code organization

Exact names implementation time par framework conventions ke according adjust ho sakte hain, but responsibilities separate rahengi.

### 10.1 Common domain libraries/services

Proposed:

```text
application/libraries/Booking_service.php
application/libraries/Customer_resolver.php
application/libraries/Availability_service.php
application/libraries/Integration_inbox_service.php
application/libraries/Integration_outbox_service.php
application/libraries/Reconciliation_service.php
application/libraries/Channels/Channel_adapter_interface.php
application/libraries/Channels/Channel_manager_adapter.php
application/libraries/Channels/Booking_com_adapter.php   # later, if approved
```

Provider-specific code sirf adapter me hoga. Core booking code me `if provider == Booking.com` conditions nahi hongi.

### 10.2 Models

```text
application/models/Channel_connection_model.php
application/models/Channel_mapping_model.php
application/models/External_reservation_model.php
application/models/Integration_event_model.php
application/models/Inventory_day_model.php
application/models/Booking_unit_model.php
application/models/Inventory_block_model.php
```

Har operational method explicit `fk_plant/property_id` lega. Null ka meaning “all” kabhi nahi hoga.

### 10.3 Controllers

#### Browser management

```text
application/controllers/ChannelConnections.php
```

- `Secure_Controller`;
- Super/Admin only;
- Admin own property lookup via management authorization;
- User 403;
- every mutation POST-only + current session write token;
- configuration target active property session se infer nahi, requested managed property ko explicitly authorize karega.

#### Public provider endpoint

```text
application/controllers/api/ChannelWebhooks.php
```

- browser login/session par depend nahi;
- new integration-specific base guard;
- raw body authentication before parse;
- random public connection/account key + provider signature mapping;
- property-specific hook me stored connection; account-level hook me external hotel ID se explicit property connection derive;
- durable insert only, heavy work nahi.

#### CLI worker

```text
application/controllers/cli/ChannelWorker.php
application/controllers/cli/ChannelReconcile.php
```

- CLI-only guard;
- leased DB jobs;
- crash recovery;
- per-property ordering;
- process exit code and structured logs.

### 10.4 Existing files requiring controlled refactor

- `application/controllers/Customers.php`
  - current browser validation/redirect/JSON contract preserve;
  - customer + booking write common Booking Service me extract;
  - check-in/out inventory events emit.
  - OTA-owned dates/category/quantity/status UI me readonly and server-side immutable; forged POST reject/ignore with audit, role chahe Super/Admin/User ho.
- `application/models/Customer_model.php`
  - normalized customer lookup;
  - booking-unit aware availability;
  - server `date()` assumptions ko authoritative property IANA timezone me convert;
  - current aliases and URLs preserve.
- `application/models/Inventory_model.php`
  - category holds count kare;
  - shared Availability Service use kare;
  - unassigned hold display support.
- `application/controllers/Rooms.php` and room/category models
  - online capacity-change event;
  - mapping safety validation.
  - current property User permission preserve, but mapped-capacity impact confirmation/audit mandatory.
- `application/controllers/Properties.php`
  - connection pause/deactivation lifecycle.
- `application/views/customers/*`
  - source channel, external confirmation, sync/attention badge.
  - multi-unit/category reservation ko misleading primary category ke bajay `Multiple` summary + unit detail list.
- `application/views/inventory/calendar.php`
  - category unassigned-hold summary;
  - same existing physical room workflow.
- `application/views/properties/list.php`
  - authorized `Online Channels` action.
- `application/views/properties/form.php`
  - edit-only connection health/link summary.
- `application/config/routes.php`
  - management, webhook and CLI routes.

### 10.5 Suggested routes

Management routes:

```text
GET  /properties/channels/{propertyId}
POST /properties/channels/{propertyId}/connect
POST /properties/channels/{propertyId}/test
POST /properties/channels/{propertyId}/mapping/save
POST /properties/channels/{propertyId}/pause
POST /properties/channels/{propertyId}/resume
POST /properties/channels/{propertyId}/sync
GET  /properties/channels/{propertyId}/events
```

Provider route example:

```text
POST /api/v1/channel-webhooks/{provider}/{randomPublicConnectionOrAccountKey}
```

CLI examples:

```text
php index.php cli/channelworker process
php index.php cli/channelreconcile due
```

Numeric plant/property IDs webhook URL se authoritative nahi honge.

---

## 11. UI and permissions

### 11.1 Role matrix

| Action | Super Admin | Admin | User |
|---|---|---|---|
| See connection status | All properties | Own properties | No configuration |
| Add/test credentials | Support policy, all | Own property | No |
| Map categories/rates | All for support | Own property | No |
| Pause/resume/full sync | All for support | Own property | No |
| See booking source/sync badge | Yes | Yes | Yes, assigned active property only |
| See raw payload/secrets | No plaintext secrets | No plaintext secrets | No |
| Work on imported booking | Selected property | Selected own property | Selected assigned property |

User role ko Manage option nahi diya jayega.

User current assigned-property operational rights se Room/Room Category create/edit/deactivate kar sakta hai. Yeh permission preserve hogi, lekin mapped inventory change external availability ko affect karega; isliye impact warning, explicit confirmation, audit and outbox publish hoga. Active mapped category ko direct deactivate karna mapping-closeout rule se blocked hoga.

### 11.2 Best placement

New global Manage menu banane ki jagah:

- `Manage > Properties` list ke row actions me `Online Channels`;
- property edit page par connection summary;
- dedicated wizard page for credentials, connection test, room mapping, future reservation import and sync health.

Room category mapping Room Master se launched current `/room-categories/*` sub-flow ko reuse karegi. Separate Room Category navbar/Manage navigation wapas add nahi hogi.

### 11.3 Connection wizard

Recommended steps:

1. Provider/channel manager select.
2. Sandbox credentials save.
3. Connection test.
4. External property match.
5. Room category mapping.
6. Rate-plan mapping/read-only import.
7. Uncategorized/excluded room warning.
8. Existing future reservations import.
9. Inventory comparison preview.
10. Inbound-only shadow mode.
11. Outbound availability pilot enable.

### 11.4 Operational booking UI

Booking list/detail me:

- `Online / Offline` source;
- OTA/channel name;
- external confirmation;
- received time;
- mapped category and quantity;
- physical room assignment status;
- sync badge;
- `needs attention` reason;
- masked guest contact;
- no credentials/raw payload.

OTA-origin booking me provider-owned dates, category, quantity and cancellation status readonly honge. Server request me forged editable values bhejne par write reject/ignore hoga; permitted local fields and physical room/unit allotment hi change honge.

Asynchronous OTA booking browser ke open cache ke bahar aa sakti hai. Property booking/inventory data version ya short polling mechanism local UI cache invalidate karega, taaki old availability flash na ho.

---

## 12. Concurrency, idempotency and ordering

### 12.1 Delivery semantics

External systems generally at-least-once delivery kar sakte hain. “Exactly once network delivery” assume nahi hogi. Business effect idempotent banaya jayega.

Required unique keys:

- listing/provider namespace + provider event ID;
- fallback connection/listing + canonical payload digest;
- listing/provider namespace + external reservation ID;
- external reservation + revision/version;
- booking unit + stay date;
- outbox inventory scope + inventory version.

### 12.2 Duplicate webhook

Same event 100 times sequential/concurrent aaye:

- one inbox business process;
- one external reservation mapping;
- one customer decision;
- one booking result;
- no repeated inventory decrement;
- duplicates success/no-op audit.

### 12.3 Out-of-order revisions

- Newer provider version applied.
- Older version arriving later ignored but retained in audit.
- Same version with different payload quarantined as provider inconsistency.
- Cancel-before-create isolated for reconciliation.
- Per reservation processing order serialized.

### 12.4 Lock order

All manual, OTA and import writers same broad-to-narrow order use karenge:

```text
plant/account
→ property
→ external reservation mutex/crosswalk (OTA command only)
→ category/date inventory rows sorted by category/date
→ physical room rows sorted by ID
→ target/overlapping booking rows
```

External mutex locked current revision se old range derive karne ke baad hi date locks choose honge. New external ID ke liye listing-scoped mutex/key row atomically create/lock hogi. Version recheck fail ho to whole transaction retry hogi. Separate controller-specific lock protocols nahi honge.

### 12.5 Outbound update type

Affected dates ka **absolute availability value** publish hoga, but provider guideline ke according only changed dates batched hongi. Har few minutes full horizon spam nahi hoga. Provider-supported scheduled full reconciliation off-peak/daily ho sakti hai.

### 12.6 Retry classification

| Result | Action |
|---|---|
| timeout, reset, DNS transient, HTTP 5xx | exponential backoff + jitter |
| HTTP 429 | `Retry-After` respect |
| HTTP 401/403 | connection pause/credential alert; endless retry nahi |
| permanent validation 4xx | quarantine/dead-letter |
| partial batch response | only failed items retry |
| response lost after provider accepted | idempotency/version + reconciliation |

Worker HTTP call DB booking transaction ke bahar rahegi.

### 12.7 Overbooking truth

100% zero-overbooking mathematical guarantee external OTA latency ke saath possible nahi hai. Do platforms last unit ko local update receive karne se pehle simultaneously sell kar sakte hain.

Risk reduction:

- one channel manager;
- immediate changed-date pushes;
- safety buffer;
- closeout threshold;
- fast queue monitoring;
- absolute values;
- periodic reconciliation;
- critical drift/overbook alert.

---

## 13. Webhook and integration security

### 13.1 Authentication

Adapter provider contract ke according one or more use karega:

- HMAC over exact raw body;
- signed timestamp with replay window;
- OAuth/client credential;
- mTLS;
- provider API-key authenticated pull;
- provider IP allowlist only defense-in-depth.

Signature comparison constant-time hogi. Current + previous secret limited overlap me secret rotation support karenge.

### 13.2 Request hardening

- HTTPS only;
- strict content type;
- body-size limit;
- required headers/schema;
- compressed payload limits;
- JSON depth/field validation;
- XML external entities and network resolution disabled;
- per-connection rate limit;
- replay ID persistence;
- provider endpoints fixed allowlist/template, user-entered arbitrary URL nahi;
- redirect restriction, TLS verification, connect/read timeouts;
- unknown hotel/room/rate mapping guessed property par write nahi karegi.

### 13.3 Isolation

- Payload ka `property_id`, plant ID ya booking channel ID local authorization ke liye trust nahi hoga.
- Authenticated property connection ya provider account + external hotel mapping se plant/property derive hoga.
- Every new FK same composite isolation convention follow karegi.
- Admin A forged connection/mapping/event ID se Admin B data nahi dekh sakega.
- Super support secrets reveal nahi karega.

### 13.4 Secret storage

- Master encryption key environment/secret store me, repo/DB se separate.
- CI config ka currently empty encryption key production credential protection ke liye acceptable nahi.
- Encrypted credential envelope with key version.
- UI secret write-only; saved value masked.
- Logs, exceptions, DB attempt logs aur screenshots me secret redaction.

### 13.5 Payment data

Recommended Phase 1 rule:

- PAN/full card number import/store nahi;
- CVV/CVC kabhi store nahi;
- provider token, payment model, masked last-four (only if contract permits), collection status and secure extranet reference only;
- virtual-card access tab tak nahi jab tak PCI scope formally assessed na ho.

PCI DSS cardholder data store/process/transmit karne wale environment par apply hota hai, aur CVV post-authorization storage prohibited hai even if encrypted. Isliye payment card handling initial integration se explicitly out rahegi.

### 13.6 Browser security before public deployment

Current localhost/development configuration ko internet-facing webhook deployment se pehle harden karna mandatory hai:

- production HTTPS base URL;
- secure cookies;
- browser CSRF enable; sirf signed webhook URI explicit exemption. Blanket switch se pehle every existing HTML form, Angular/AJAX write, token refresh and cross-tab property token flow migrate/test hoga; compatibility gate pass hue bina config flip nahi hoga;
- non-development environment/error pages;
- DB non-root least-privilege account, strong secret, strict mode/TLS as applicable;
- OTP response se development OTP remove/production-gate;
- OTP request/verify rate limits;
- meaningful sanitized logging and rotation;
- server/network firewall;
- backup and restore drill.

---

## 14. Worker, scheduling and reconciliation

### 14.1 CI3-compatible queue

Application me queue framework nahi hai. First implementation me durable MySQL inbox/outbox + CLI worker practical rahega.

Worker behavior:

- small batches claim with lease;
- lease expiry par crash recovery;
- lease-owner fencing token and conditional completion update;
- every external send se immediately pehle current connection epoch/version and kill switch recheck;
- pause/credential rotation par epoch increment, so already-claimed stale worker send nahi kar sake;
- multiple workers safe;
- per reservation and property ordering;
- max attempts;
- dead-letter;
- graceful shutdown;
- correlation IDs.

Windows/XAMPP environment me Windows Task Scheduler CLI command every minute/continuous supervised task chala sakta hai. Production Linux me cron/supervisor/systemd alternative ho sakta hai.

### 14.2 Polling fallback

Provider webhook na de ya webhook only notification ho to:

- webhook feed pull trigger kare;
- scheduled incremental pull cursor use kare;
- provider-required acknowledgement local successful commit ke baad ho;
- missed window periodic reconciliation se recover ho.

### 14.3 Reconciliation jobs

Per property/connection:

- unacknowledged reservation feed pull;
- future active reservations compare;
- internal vs external room/category mapping compare;
- affected horizon availability compare;
- stuck inbox/outbox recover;
- daily full inventory check where provider permits;
- credentials/connection health test.

Drift automatic safe correction se repair ho sakti hai; ambiguous booking/customer/mapping drift quarantine aur Admin alert me jayegi.

---

## 15. Production observability

Connection dashboard metrics:

- last inbound event received;
- last event successfully processed;
- last reservation acknowledgement;
- last successful availability push;
- last reconciliation;
- queue depth and oldest job age;
- retry and dead-letter count;
- signature/auth failures;
- unmapped room/rate events;
- internal/provider inventory drift;
- overbook/negative-capacity exceptions;
- credential expiry/connection pause.

Structured log fields:

```text
correlation_id
connection_id
listing_id
event_id
external_reservation_id
property_id
inventory_version
attempt_number
result_code
```

Logs me guest name, complete phone/email, credentials, raw card/payment data nahi honge.

Critical alerts:

- confirmed booking import failed;
- queue oldest age threshold crossed;
- authentication expired;
- unknown property/room mapping;
- provider and local availability mismatch;
- inventory invariant broken;
- outbound stopped while property still selling;
- property/plant deactivated but connection still active.

---

## 16. Plant/property/user lifecycle

Effective connection active hone ke liye all true honge:

```text
plant active
AND property active
AND owner/account suspension policy permits integration
AND connection active
AND inbound/outbound switch enabled
```

Rules:

- User assignment revoke hone se system sync stop nahi hogi; sirf that user ka UI access revoke hoga.
- Public authenticated ingress normally active rahega aur events durably receive karega even when processing paused; confirmed reservation notification lose nahi hogi. Paused event quarantine/alert me wait karega.
- Property/plant deactivate karne se pehle mapped listings par zero/stop-sell push and acknowledgement, future reservation reconciliation, then outbound epoch increment/pause hoga. Provider unavailable ho to deactivation block hogi ya authorized Admin ko confirmed manual-extranet closeout evidence record karna hoga.
- Property deactivate hone par successful closeout ke baad processing/outbound pause, history retained.
- Plant/account deactivate hone par same closeout workflow every active property connection par complete hoga; silent local pause ke saath external rooms sellable nahi chhode jayenge.
- Connection disconnect par credentials revoke if provider supports, records/audit retain.
- Reactivation se pehle connection test + future reservation pull + full reconciliation required.
- Mapping/connection rows hard-delete nahi honge.

Current Admin status toggle aur plant status behavior implementation se pehle standardize/test karna hoga, taaki owner suspension ke baad background sync unintended active na rahe.

---

## 17. Repeat-safe migration strategy

New migration folder proposed:

```text
db/channel_integration/
  README.md
  backup_before_migration.sql
  01_provider_connection_tables.sql
  02_booking_units_inventory_tables.sql
  03_customer_contact_changes.sql
  04_backfill_legacy_booking_units.sql
  05_constraints_indexes.sql
  06_verify_reconciliation.sql
  07_optional_rate_plan_tables.sql
```

Sequence:

1. DB and secure uploads backup; restore test.
2. Preflight DB version, engine, charset, row counts and invalid legacy values.
3. Add new tables/nullable columns without changing reads.
4. Seed global integration provider definitions disabled.
5. Backfill one or more booking units per legacy booking.
6. Reconcile ambiguous `room_quantity` vs `total_unit`; do not silently guess live future inventory.
7. Assigned legacy room becomes one assigned unit; remaining quantity, if any, becomes unassigned units after verification.
8. Inventory-enable flags add with safe defaults (`category = offline`, room override null); no OTA sale auto-start.
9. Populate night allocations for consuming dated bookings.
10. List every undated/invalid-date live consuming booking; mapped-category enable gate requires this list zero after manual remediation.
11. Build inventory-day projection for configured horizon.
12. Run dual calculation: old physical inventory vs new ledger; investigate differences.
13. Add indexes/composite FKs/check constraints. `booking_units` current four-column booking scope FK use karega and night rows unit/property/category/plant composite keys use karengi.
14. Make required fields non-null only after verification.
15. Deploy common services behind feature flags.
16. Keep legacy columns and compatibility reads until all regression/pilot gates pass.
17. Refresh full DB snapshot after production-approved migration.

Migration verification:

- table row counts before/after;
- each booking has expected units;
- assigned room belongs same property/category where category exists;
- no cross-scope external/mapping rows;
- no duplicate external keys;
- no live future booking missing night allocations;
- no undated live consuming booking in any category proposed for mapping;
- computed category consumption equals booking units;
- inactive/history rows preserved;
- rollback is pause/feature flag and code compatibility, not destructive deletion.

---

## 18. Implementation phases and gates

### Phase 0 — Commercial/provider discovery

Deliverables:

- property/channel survey;
- selected channel manager/direct provider;
- written OTA coverage matrix;
- sandbox and production-access process;
- cost/SLA/support review;
- PCI/data-processing boundary;
- owner migration/cutover policy.

Gate: required Indian/global OTAs and APIs confirmed in writing. Marketing claim ko API capability nahi maana jayega.

### Phase 1 — Internal inventory foundation

Deliverables:

- Booking Service extraction;
- Customer Resolver;
- booking units/night allocations;
- category/date inventory ledger;
- maintenance blocks;
- Inventory and room lookup both same Availability Service;
- current manual flow/URLs unchanged;
- feature flags disabled by default.

Gate: existing tests pass plus real concurrent last-room tests pass. Category-only hold local availability correctly reduce kare.

### Phase 2 — Integration infrastructure

Deliverables:

- connection/mapping schema and UI;
- encrypted secrets;
- webhook inbox;
- worker/outbox/retry/dead-letter;
- mock provider adapter;
- observability dashboard;
- user role has no configuration access.

Gate: duplicate, replay, forged scope, crash recovery and provider-failure test suite passes.

### Phase 3 — Sandbox/shadow inbound

Deliverables:

- chosen provider adapter;
- external property/category/rate mapping;
- sandbox new/modify/cancel normalization;
- sanitized/encrypted event quarantine;
- shadow import comparison without affecting operational inventory initially.

Gate: provider sandbox certification cases and local comparison pass.

### Phase 4 — Shadow comparison and controlled cutover preparation

Deliverables:

- one property, limited mapped categories;
- shadow events remain non-operational until cutover;
- maintenance-window/local-create freeze;
- all existing future reservations controlled initial import and reconciliation;
- Admin/operations notifications;
- no outbound inventory yet;
- manual extranet availability remains authority and no unsynchronised local inventory change is allowed during the short cutover window.

There will be no sustained “live inbound consuming bookings but outbound disabled” mode. Agar local booking freeze/manual extranet SLA enforce nahi ho sakti to Phase 4 production data write nahi karegi.

Gate: future reservations, mappings and calculated availability exactly reconcile; duplicate/missing event shadow cases pass.

### Phase 5 — Two-way availability pilot

Deliverables:

- PMS inventory becomes authority for pilot category/property;
- live inbound processing and outbound availability together enable;
- initial full availability push;
- immediate changed-date outbox;
- safety buffer;
- drift monitoring;
- kill switches.

Cutover steps:

1. Mapping freeze.
2. Future reservation pull and compare.
3. Local capacity/block verification.
4. Outbound initial full sync.
5. OTA extranet spot checks.
6. Controlled test booking, modification and cancellation.
7. 24/7 alert owner during pilot window.

Gate: agreed monitoring period without unresolved drift/overbooking.

### Phase 6 — Rollout and optional direct adapters

- one property/connection at a time;
- unsupported channels manual/iCal fallback only;
- direct OTA adapter only where commercial value justifies separate certification/maintenance;
- rates/restrictions after reservation/inventory stability.

### Rough engineering size

Subject to provider access and current data cleanup:

| Workstream | Indicative effort |
|---|---:|
| Inventory/customer/booking domain foundation | 10–18 developer-days |
| Connection, inbox/outbox, worker, security | 10–18 developer-days |
| First provider/channel-manager adapter + sandbox | 15–30 developer-days |
| Management/operational UI and observability | 8–14 developer-days |
| Concurrency, security, regression and pilot hardening | 12–20 developer-days |

Total is an engineering planning range, not a delivery promise. Provider onboarding/certification can add independent calendar time. Each direct OTA adapter can add substantial separate effort.

---

## 19. Exhaustive test plan

### 19.1 Existing regression suite

Must remain passing:

- PHP lint across application;
- `tests/multitenancy_isolation_test.php`;
- `tests/soft_delete_test.php`;
- `tests/identity_upload_guard_test.php`;
- Inventory/booking UI test;
- identity responsive/camera/crop test;
- role management responsive test;
- existing login/OTP/property-switch contracts.

### 19.2 Inventory unit tests

- checkout-exclusive range;
- one-night and multi-night stays;
- assigned room booking consumes category exactly once;
- unassigned OTA category hold consumes quantity;
- multiple units/categories;
- partial modification/cancellation;
- maintenance block;
- safety buffer;
- channel cap;
- uncategorized room excluded online but usable offline;
- inactive room/category/property excluded;
- checked-in, checked-out, cancelled and no-show behavior;
- multi-unit split assignment/check-in/checkout/no-show and derived header badge;
- late OTA cancellation after local check-in/out quarantined without inventory/history corruption;
- temporary maintenance counted exactly once;
- mapping A disable does not change shared pool published through mapping B;
- early checkout/extended stay policy;
- projection rebuild equals allocation source.

### 19.3 Customer tests

- new real phone;
- normalized phone variants reuse same plant customer;
- same phone different plants remains isolated;
- inactive profile deliberate reactivation;
- missing phone OTA profile;
- masked/proxy phone;
- external guest crosswalk;
- same name different people do not merge;
- OTA data does not overwrite trusted local profile;
- check-in matching another customer creates duplicate-candidate review without automatic booking/document relink;
- customer code concurrency.

### 19.4 Webhook/security tests

- valid/invalid signature;
- body changed after signature;
- wrong secret;
- stale/future timestamp;
- replay;
- secret rotation current/previous window;
- missing/duplicate headers;
- malformed/oversized JSON;
- malformed XML and XXE attempts;
- compressed body bomb limits;
- rate limit;
- forged plant/property/hotel/category/rate-plan IDs;
- cross-admin connection/mapping/event lookup;
- SSRF endpoint and redirect;
- invalid TLS;
- header injection;
- durable inbox insert failure returns retriable non-2xx;
- browser CSRF compatibility across every existing form/AJAX flow before config enable;
- encrypted sanitized payload retention/purge/access/backup-expiry policy;
- provider payload containing prohibited PAN/CVV is never persisted;
- log/DB scan for plaintext credentials and unmasked PII.

### 19.5 Idempotency/order tests

- same event sequentially 100 times;
- same event concurrently 100 times;
- same external reservation with different event IDs;
- same external reservation number on two aggregator listings remains distinct;
- create → modify → cancel;
- modify before create;
- cancel before create;
- older revision after newer revision;
- same version different body;
- worker crash after inbox insert;
- crash during transaction;
- crash after commit before provider acknowledgement;
- outbox send accepted but response lost;
- v2/v3 concurrent modification locks reservation mutex before deriving ranges;
- expired-lease old worker is fenced from send/complete;
- pause/credential rotation epoch blocks already-claimed stale job.

### 19.6 Real database concurrency tests

Separate DB connections/processes and synchronization barrier required:

- last category unit: offline booking + two OTA events simultaneously;
- exact room vs unassigned category hold;
- same category different dates;
- two properties of same Admin stay independent;
- two Admin plants remain isolated;
- duplicate webhook concurrent workers;
- modification moving date/category while offline booking starts;
- cancellation releasing stock while another booking arrives;
- deadlock/lock timeout retry;
- burst of 100–1,000 events.

Assertions:

- no duplicate external reservation;
- no cross-scope record;
- reserved count never negative;
- over-capacity only recorded as explicit confirmed-provider exception;
- every committed inventory change has outbox event;
- no assigned + category double count.

### 19.7 Outbound/mock provider tests

- HTTP 200;
- slow timeout;
- connection reset/DNS failure;
- 429 + `Retry-After`;
- 500;
- 401/403 connection pause;
- permanent validation 400;
- partial batch success;
- retry backoff/jitter;
- max attempts/dead-letter;
- superseded availability jobs coalesce;
- worker restart/duplicate send;
- one failed platform does not block others;
- aggregator receives one transport update while direct connections receive their own updates;
- reconciliation repairs drift.

### 19.8 End-to-end provider sandbox

- new OTA booking appears with correct property/source/customer;
- existing verified customer safely reused;
- missing contact produces incomplete profile;
- offline booking reduces all mapped channels;
- OTA booking reduces local and other channel availability;
- multi-room reservation units correct;
- modification moves allocation atomically;
- cancellation releases availability;
- provider-owned OTA fields reject forged local POST changes for every role;
- physical room allot/check-in/out preserved;
- unknown mapping quarantined;
- mapped category cannot deactivate before confirmed stop-sell/unmap;
- room/category changes by assigned User produce warning/audit/outbox without exposing Manage;
- property/plant closeout pushes zero before processing pause; ingress still durably captures events;
- property/plant/connection pause and worker fencing work;
- User cannot see/manage connection settings;
- Super support and Admin ownership boundary work;
- mobile 320px, 390px and desktop screens remain usable.

---

## 20. Acceptance criteria

Feature production-ready tab maana jayega jab:

1. One property ki connection/mapping kisi other Admin/property se inaccessible ho.
2. Duplicate/new/modify/cancel provider events duplicate customer/booking/inventory effect na banayein.
3. Online category booking without physical room local capacity reduce kare.
4. Offline booking commit ke baad every active outbound transport connection ko affected-date absolute update queue ho; channel manager mapped OTAs me fan-out kare.
5. Provider unavailable hone par local booking safe rahe aur retry/alert visible ho.
6. Existing manual booking, check-in/out, customer, identity, Room Master and Inventory flows regress na hon.
7. Uncategorized rooms offline usable but online excluded hon.
8. Customer phone missing/masked case fake profile merge na kare.
9. Future reservation cutover reconciliation complete ho.
10. Secrets/plain card data logs/UI/ordinary DB fields me expose na ho.
11. User role ko Manage/config routes ka access na ho.
12. Kill switch aur rollback drill verified ho.
13. Current test baseline plus new concurrency/security/provider sandbox suite pass ho.
14. Online-enabled category me zero undated live consuming booking ho.
15. Multi-unit partial lifecycle and late terminal-state cancellation deterministic/audited ho.
16. Property/plant deactivation external stop-sell/zero confirmation ke bina stale rooms online na chhode.

---

## 21. Rollback and incident procedure

Rollback destructive nahi hoga:

1. If outbound path healthy hai to affected listings ko zero/stop-sell push and acknowledgement; otherwise immediately confirmed manual extranet closeout.
2. Connection epoch increment karke outbound workers fence/pause.
3. Processing pause required ho sakti hai, but authenticated public ingress durable events receive/quarantine karta rahega.
4. OTA/channel-manager extranet ko temporary manual inventory authority banayein.
5. Queue/events/bookings/audit delete na karein.
6. Future reservations and availability reconcile karein.
7. Code feature flag se legacy operational UI continue kare.
8. Fix deploy, connection test, controlled full sync, then resume.

If one provider fails, other connection jobs independent process honge. Global pause aur per-property/per-connection pause dono available rahenge.

---

## 22. Locked recommended defaults

- Channel-manager-first, adapter-based architecture.
- Connection/mapping per property, never plant-wide implicit hotel scope; encrypted provider credential account optionally same-plant/system policy me shared ho sakta hai.
- Pooled category inventory as source of truth.
- Physical pool property/category level par mapping-independent; connection mappings only publish it.
- Absolute availability values for affected dates; blind `-1/+1` deltas nahi.
- Room category remains optional for offline rooms, required only for online-published inventory.
- Category compatibility routes remain, launched from Room Master; no navbar/Manage entry.
- User role receives no Manage/config option.
- Existing User Room/Category operations preserve, with mapped-capacity warning/audit/outbox and mapped-category closeout guard.
- Super/Admin configuration; operational users source/status dekh sakte hain.
- Phase 1 reservations + inventory only; rates/restrictions later.
- OTA booking may remain physically unassigned until allotment.
- Confirmed provider overbook saved + alerted, silently discarded nahi.
- Customer external crosswalk then normalized plant phone; name-only merge never.
- Missing-phone customer supported without fake number.
- Durable inbox/outbox, async network calls, periodic reconciliation.
- No payment card/CVV storage.
- Feature flag per connection/property and pilot safety buffer.
- Hotel IANA timezone `properties` par authoritative.
- No sustained live inbound-only consuming mode; production cutover inbound + outbound together.
- No hard deletion of integration or reservation history.

---

## 23. Decisions required before coding the first provider

1. Existing hotels currently kaunsa channel manager use karte hain?
2. First pilot property and first required OTA set kya hai?
3. Owners existing manager retain karenge ya common supported manager par migrate?
4. Selected manager MakeMyTrip/Goibibo/Agoda/Booking.com/Expedia/Airbnb me exactly kin channels ko support karta hai?
5. Phase 1 me only reservation + inventory locked hai ya rate sync bhi immediately required hai?
6. Pilot safety buffer one room acceptable hai?
7. Early checkout future inventory release policy kya hogi?
8. Confirmed overbooking escalation contact/SLA kya hoga?
9. Missing phone guest ko check-in se pehle verify karna mandatory hai?
10. Currency, tax, commission and Hotel Collect/OTA Collect display rules kya hain?
11. Public production domain, HTTPS and continuously running worker environment kya hoga?

Recommended answers defaults section me already diye gaye hain; provider/vendor choice commercial information ke bina safely assume nahi ki ja sakti.

---

## 24. Official references checked

Accessed 23 August 2026. Provider behavior and access programs change ho sakte hain; implementation start aur go-live se pehle current terms re-verify honge.

- [Booking.com — About the Connectivity APIs](https://developers.booking.com/connectivity/docs)
- [Booking.com — Reservations API overview](https://developers.booking.com/connectivity/docs/reservations-api/reservations-overview)
- [Booking.com — Rates & Availability API overview](https://developers.booking.com/connectivity/docs/ari)
- [Expedia Group — Booking Notification API introduction](https://developers.expediagroup.com/supply/lodging/docs/booking_apis/booking_notification/getting_started/introduction/)
- [Expedia Group — Lodging connectivity updates](https://developers.expediagroup.com/supply/lodging/updates)
- [Airbnb — Manage listings with PMS/channel-manager software](https://www.airbnb.com/help/article/2346)
- [Airbnb — Connect a channel manager](https://www.airbnb.com/help/article/3304)
- [Agoda Partner Hub — Connect and map a channel manager](https://partnerhub.agoda.com/how-do-i-manage-my-channel-manager-connection/)
- [MakeMyTrip — Hotel registration and channel-manager network](https://www.makemytrip.com/hotels/hotelier-register.htm)
- [Google Hotels — Hotel Prices/ARI documentation](https://developers.google.com/hotels/hotel-prices/)
- [Google Hotels — Inventory message](https://developers.google.com/hotels/hotel-prices/dev-guide/ari-inventory-message)
- [Channex — PMS integration guide](https://docs.channex.io/guides/pms-integration-guide)
- [Channex — Booking revisions and acknowledgement](https://docs.channex.io/api-v.1-documentation/bookings-collection)
- [Channex — Webhook collection](https://docs.channex.io/api-v.1-documentation/webhook-collection)
- [PCI Security Standards Council — PCI DSS](https://www.pcisecuritystandards.org/standards/pci-dss/)
- [PCI SSC — Card verification code storage rule](https://www.pcisecuritystandards.org/faqs/are-merchants-allowed-to-request-card-verification-codes-values-from-cardholders/)

---

## 25. Final recommendation

Feature app me fit hota hai, aur current plant/property isolation iske liye achhi base hai. Production-safe implementation ka first non-negotiable step OTA API call nahi, balki **shared category/date availability foundation** hai. Uske baad common Booking Service, customer resolver, durable inbox/outbox aur per-property mapping add honge.

Sabse safe delivery order:

```text
Inventory foundation
→ common booking/customer service
→ connection + secure queue infrastructure
→ mock/sandbox adapter
→ one-way pilot import
→ full future-reservation reconciliation
→ two-way availability pilot
→ gradual property rollout
→ optional rates/direct OTA adapters
```

Is order se current app working, current login, Room Master, Inventory, booking, customer, check-in/out, identity workflow aur role/property isolation ko preserve karte hue feature add kiya ja sakta hai.
