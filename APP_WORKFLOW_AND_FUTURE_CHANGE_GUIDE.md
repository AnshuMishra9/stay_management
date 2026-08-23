# Stay Management App: Workflow, Planning and Future Change Guide

> Last reviewed: 24 August 2026
>
> Scope: Current working tree, including changes that may not yet be committed
>
> Coverage: Architecture plus screen-by-screen navigation, actions and database effects
>
> Purpose: Is document ko app ka functional baseline samjhein. Future me app ka behavior badle, slow ho, data galat scope me dikhe, ya workflow toot jaye to current behavior ko is guide se compare karein.

## 1. App ka Purpose

Stay Management ek multi-property hotel operations application hai. Iska main kaam hai:

- Multiple owners/accounts ko alag rakhna.
- Har account ke andar multiple hotel properties manage karna.
- Property-wise users, rooms aur room categories manage karna.
- Customer profile create ya reuse karna.
- Booking, check-in aur check-out lifecycle chalana.
- Date-wise room inventory aur availability dikhana.
- Customer identity documents securely store aur serve karna.
- Role aur property scope ke bahar ka data block karna.

Application ko sirf screen collection nahi samajhna chahiye. Iski sabse important guarantee hai ki har operation sahi account, sahi property aur sahi booking status ke scope me ho.

## 2. Terminology

Database aur purane code me kuch names business language se alag hain:

| Code/Database term | Business meaning |
| --- | --- |
| `plant`, `fk_plant` | Owner/account/tenant boundary. Yeh hotel property nahi hai. |
| `property` | Actual hotel/property jahan rooms aur stays operate hote hain. |
| `tenant_id`, `current_tenant_id`, `Tenant_model` | Compatibility names jo internally plant/account ko represent karte hain. |
| `customer` | Account level guest profile, jo same plant ki properties me reuse ho sakta hai. |
| `booking` | Property-scoped stay record. |
| `room_booked` | Reservation bani hai, guest abhi check-in nahi hua. |
| `checked_in` | Guest currently checked in hai. |
| `checked_out` | Stay complete ho chuka hai. |

Important distinction:

- Customer phone/code plant level par unique aur reusable hai.
- Rooms, categories, bookings aur inventory property level par scoped hain.
- Identity record customer ke saath plant/property/booking ownership bhi preserve karta hai.

## 3. Technical Architecture

### Backend

- Framework: CodeIgniter 3
- Language: PHP
- Database: MySQL/MariaDB
- Local stack: XAMPP
- Local base URL: `http://localhost/stay_management`
- Environment configuration: root `.env`, example values in `.env.example`

### Frontend

- AngularJS 1.x: login aur kuch list screens
- Vanilla JavaScript: inventory interaction, identity upload, camera aur crop flows
- Shared CSS/JS assets: `assets/`
- Views: `application/views/`

### Main Code Boundaries

Controller inheritance important hai:

1. `MY_Controller`
   Base application controller.

2. `Secure_Controller`
   Authentication, authoritative user reload, role checks, session write token aur active property state handle karta hai.

3. `Property_Controller`
   Operational screens ke liye ek active aur authorized property require karta hai. Property context token bhi isi boundary ka part hai.

4. `Ops_Controller`
   Customers, Bookings, Checkins aur Checkouts ka shared operational behavior rakhta hai. Shared tokens, date helpers, inventory callbacks aur identity upload security yahan centralize hain.

5. Feature controllers
   - `Customers`, `Bookings`, `Checkins`, `Checkouts` extend `Ops_Controller`.
   - `Inventory`, `Rooms`, `RoomCategories` extend `Property_Controller`.
   - `Admins`, `Properties`, `Users` extend `Secure_Controller`.

Future refactor me inheritance change karte waqt shared methods ko ek controller me chhod dena 500 errors ka common cause hai.

## 4. Roles and Access Planning

### Super Admin

- Plants/accounts create aur manage kar sakta hai.
- Admin accounts manage kar sakta hai.
- Sabhi properties aur hotel users manage kar sakta hai.
- Operational work ke liye phir bhi ek selected property use karta hai.

### Admin

- Apne plant/account ki properties manage kar sakta hai.
- Apne plant ke hotel users create/manage kar sakta hai.
- Owned ya authorized properties me operational work kar sakta hai.
- Admin accounts manage nahi kar sakta.

### User

- Sirf explicitly assigned active properties access kar sakta hai.
- Plant, admin, property ya user management routes access nahi kar sakta.
- Authorized selected property ke operational modules use karta hai.

### Access Rules

- Management routes ko selected property ki zarurat nahi hoti.
- Inventory, rooms, customers, bookings, check-ins aur check-outs ko active selected property chahiye.
- Ek authorized property ho to login ke baad auto-select ho sakti hai.
- Multiple properties ho to selection screen aati hai.
- Property switch par property context token rotate hota hai.
- Property switch par customer write token bhi invalidate hota hai.
- Revoked, inactive ya unauthorized property par select/no-properties/access flow trigger hona chahiye.
- Out-of-scope record ko leak nahi karna: normally `404` return hona chahiye.

## 5. Authentication Workflow

### Login Flow

1. User `/login` open karta hai.
2. Mobile number ke liye `/auth/send_otp` call hota hai.
3. OTP verify karne ke liye `/auth/verify_otp` call hota hai.
4. Sirf active aur authorized user login kar sakta hai.
5. Successful fresh login par session ID regenerate hoti hai.
6. Purana active property aur stale tokens clear hote hain.
7. Property access count ke hisab se property auto-select ya selection screen open hoti hai.

### OTP Rules and Current Implementation

- OTP lifetime: 120 seconds
- Wrong-attempt counter/message limit: 5. Current code correct OTP match ko attempts-cap check se pehle accept karta hai, isliye strict lockout fully enforced nahi hai.
- OTP single-use hai; successful verification conditional update se mark hoti hai.
- Intended security rule ke liye `security_helper.php` me `otp_exposure_allowed()` helper hai. Yeh sirf `ENVIRONMENT=development` aur exact `DEV_EXPOSE_OTP=true` combination allow karta hai.
- Current working code me `Auth::send_otp()` is helper ko apply nahi karta aur generated OTP ko JSON response me hamesha include karta hai.
- Isliye current as-built login demo behavior raw OTP ko browser tak bhejta hai. Production deployment se pehle yeh mandatory security fix hai.

### SMS Planning and Current Implementation

- `Sms_gateway` abstraction available hai.
- Default/local behavior log driver ho sakta hai.
- Fast2SMS/Twilio skeleton/config paths available hain.
- Current `Auth::send_otp()` SMS gateway dispatch call nahi karta; browser JSON response hi OTP delivery ka actual current path hai.
- Production SMS provider wiring, delivery failure handling aur final live verification deployment se pehle required hai.

## 6. Security Tokens

Global CodeIgniter CSRF currently disabled hai, isliye application-specific tokens important protection dete hain.

### Session Write Token

- Admin/property/user management mutations ko protect karta hai.
- Session-bound hona chahiye.
- Missing ya invalid token par write allow nahi honi chahiye.

### Property Context Token

- Operational AJAX aur writes ko current selected property se bind karta hai.
- Property switch ke baad purana tab stale maana jata hai.
- Stale token ka expected response `409 property_context_changed` hai.
- Client ko refresh/reload karke latest property context lena chahiye.

### Customer Write Token

- Customer, check-in aur identity upload related forms ko protect karta hai.
- Fresh operational session/form load par token available hona chahiye.
- Property switch par token invalidate/rotate hona chahiye.
- Missing token ka false `403` fresh form par nahi aana chahiye.

## 7. Setup and Master Data Workflow

Recommended business setup sequence:

1. Super Admin plant/account create karta hai.
2. Super Admin us plant ka Admin create karta hai.
3. Super Admin ya Admin property create karta hai.
4. Hotel users create hote hain aur unko allowed properties assign hoti hain.
5. Operator active property select karta hai.
6. Room categories create ki jaati hain.
7. Rooms create kiye jaate hain aur appropriate category/property se map hote hain.
8. Customers aur stay operations start ki jaati hain.

Room category currently Room Master subflow ka part hai. Yeh main Manage navigation item hona zaruri nahi hai.

## 8. Core Operational Workflow

Primary stay lifecycle:

```text
Customer create/reuse
        |
        v
Booking: room_booked
        |
        v
Check-in: checked_in
        |
        v
Check-out: checked_out
```

`cancelled` aur `no_show` status reference me available ho sakte hain, lekin current primary UI me unke liye complete dedicated tab workflow main path ka part nahi hai.

### Status-wise Screens

| Status | Primary screen/action |
| --- | --- |
| `room_booked` | Booking Details, next action Check-in |
| `checked_in` | Check-in Details, next action Check-out |
| `checked_out` | Check-out Details, view/edit completed record |
| `cancelled` | Inventory occupy nahi karega |
| `no_show` | Inventory occupy nahi karega |

Inventory occupied cell ka action status ke hisab se badalta hai:

- `room_booked` -> Check-in
- `checked_in` -> Check-out
- `checked_out` -> completed record view

## 9. Customer Workflow

### Create or Reuse

- Manual booking me phone number se existing customer search/reuse hota hai.
- Customer plant-scoped hai, isliye same account ki dusri property me reuse ho sakta hai.
- Inactive customer ko new valid stay ke time reactivate kiya ja sakta hai.
- Customer code aur phone plant boundary ke andar consistent rehne chahiye.

### Customer Data Rules

- Property A ka operator same plant customer ko booking ke liye reuse kar sakta hai, lekin Property B ki booking/history unauthorized context me nahi dekh sakta.
- Customer list/view me scope rules deliberately verify karein; customer profile sharing ka matlab cross-property booking disclosure nahi hai.
- Delete action physical delete nahi, status deactivate hona chahiye.

## 10. Booking Workflow

Booking do main entry points se ban sakti hai:

1. Booking form se manual create.
2. Inventory me available room/date selection se booking modal.

### Inventory-origin Booking Rules

- Initial status forcibly `room_booked` hota hai.
- Check-in date today ya future honi chahiye.
- Check-out date check-in ke baad honi chahiye.
- Selected room exact active property/room scope me hona chahiye.
- Room poore requested range me available hona chahiye.
- Invalid modal input ka expected response `422` hai.

### Concurrency and Overbooking Protection

Booking save sirf initial UI availability par depend nahi karta. Database transaction me availability dobara check hoti hai.

Locking/check order broad-to-narrow hona chahiye:

1. Plant/account scope
2. Property
3. Target room(s)
4. Overlapping ya target booking records
5. Final availability/status recheck
6. Customer aur booking commit

Is planning ka goal stale form aur simultaneous booking requests se double booking rokna hai.

### Booking Numbers and Ownership

- Booking number property-scoped unique hai.
- Customer code/phone plant-scoped hai.
- Customer create/reuse aur booking create atomic transaction me hone chahiye.
- Booking ka `fk_plant` aur `property_id` room/customer ownership ke saath consistent hona chahiye.

## 11. Check-in Workflow

1. Sirf valid `room_booked` booking Check-in action me aani chahiye.
2. Route, active property aur current status validate hote hain.
3. Dates, room category, room, customer phone aur form fields validate hote hain.
4. Transaction booking aur room ko lock/recheck karti hai.
5. Availability conflict ho to save reject hota hai.
6. Success par status `checked_in` aur `checked_in_at` timestamp set hota hai.
7. Identity synchronization database commit ke baad complete hoti hai.

Normal validation error ko `500` nahi banana chahiye. Form validation rerender generally `200` ho sakta hai; stale/concurrent conflict `409` ho sakta hai.

## 12. Check-out Workflow

1. Sirf `checked_in` booking Check-out action me aani chahiye.
2. Active property aur booking scope validate hota hai.
3. Transaction current booking status lock/recheck karti hai.
4. Success par status `checked_out` aur `checked_out_at` timestamp set hota hai.
5. Already checked-out record par duplicate transition nahi honi chahiye; completed record ki taraf idempotent redirect acceptable hai.

Legacy checkout URLs intentionally preserve kiye gaye hain. Routes refactor karte waqt inko regression test karein:

- `/customers/checkins/checkout/:id`
- `/customers/checkedouts`
- `/customers/checkedouts/details/:id`
- `/customers/checkedouts/edit/:id`

## 13. Date and Availability Rules

Application half-open date range use karti hai:

```text
[check_in, check_out)
```

Iska matlab:

- Check-in day occupied hai.
- Check-out day free hai aur next booking us din start ho sakti hai.
- Example: 10 Aug check-in, 12 Aug check-out -> room 10 aur 11 Aug occupied; 12 Aug available.

Additional rules:

- Checked-in stay without checkout date open-ended occupancy maana ja sakta hai.
- Undated live hold conservative block create karta hai.
- `cancelled` aur `no_show` inventory occupy nahi karte.
- Availability model/database state se derive hoti hai; room par permanent booked flag ko truth source nahi banana chahiye.

Known limitation:

> Jis booking ke paas physical `room_id` nahi hai, woh current room-grid Inventory me room consume nahi karti. OTA ya category-capacity integration se pehle is behavior ka explicit design required hai.

## 14. Inventory Workflow and UI Baseline

### Calendar Window

- Inventory ek 11-day window dikhata hai.
- Selected date center ke paas hoti hai: 5 din pehle aur 5 din baad.
- Previous/next navigation step 6 days hai.

### Room Pagination

- Maximum 15 rooms per page.
- Total/filter counts full filtered result par calculate hote hain, slice ke baad nahi.
- Pagination controls keyboard aur screen-reader accessible rehne chahiye.

### Room Selection

- Available cells click/drag se date range select ki ja sakti hai.
- Selection repaint sirf previously painted aur current cells ko touch karta hai.
- Har hover/mousemove par poori table scan nahi honi chahiye.
- Occupied cell popup booking summary/action dikhata hai.
- Identity document URLs/data occupied popup me expose nahi hone chahiye.

### Current Performance Baseline

Latest optimization ke baad tested larger property par approximately:

- Inventory DOM: about 840 elements
- Visible room/date slots: about 165
- Long tasks: 4 test runs me zero
- Median DOMContentLoaded: about 158 ms
- Authenticated backend: median about 61-62 ms, p95 about 73-83 ms, max about 100 ms in the tested local environment

Environment/machine ke hisab se exact timings change hongi. Important comparison symptoms hain: interaction immediate feel ho, hover pointer late na aaye, page switch freeze na ho aur repeated long tasks na ban rahe hon.

## 15. Identity Document Workflow

### Supported Documents

- Aadhar
- PAN
- Passport
- Voter ID
- Maximum identity rows: 20
- Per row up to two sides/images

### Upload Rules

- Side 1: supported image or PDF
- Side 2: JPG/PNG image only
- Maximum file size: 4 MB
- MIME type, file content aur extension validate honi chahiye.
- Image dimensions maximum 10,000 on an axis aur 25 million pixels total.
- Browser flow file upload, camera capture aur crop/aspect-ratio tools support karta hai.

### Secure Storage and Serving

- Files public uploads directory me direct serve nahi hote.
- Secure location: `FCPATH/secure_uploads/`
- Authenticated controller endpoint file stream karta hai.
- Plant, property, customer aur booking ownership check hoti hai.
- Path traversal block hona chahiye.
- Sensitive response me `nosniff`, sandboxed CSP aur `no-store` behavior preserve hona chahiye.

Future change me direct public file URL introduce karna security regression hoga.

## 16. Soft Delete and Data Retention

Business data ko physical delete karne ke bajay deactivate kiya jata hai:

- Plants
- Users
- Properties
- Customers
- Rooms
- Room categories

Expected delete behavior:

```text
status = 0
```

Old bookings, stay history aur audit relationships preserve hone chahiye. Generated `active_flag`/composite uniqueness active records ke business keys ko protect karte hain aur inactive key reuse allow kar sakte hain.

Kisi delete implementation ko `DELETE FROM` me badalne se pehle retention, foreign keys aur historical reports ka impact explicitly review karein.

## 17. Main Database Entities

| Table | Responsibility |
| --- | --- |
| `plants` | Account/tenant boundary |
| `users` | Super Admin, Admin aur User accounts |
| `properties` | Hotel properties |
| `user_property_access` | User-to-property authorization |
| `customers` | Plant-scoped guest profiles |
| `room_categories` | Property room categories |
| `rooms` | Property rooms |
| `booking_details` | Booking and stay lifecycle |
| `customer_identities` | Secure identity metadata/files |
| `status_details` | Status reference values |
| `booking_channels` | Booking source/channel reference |
| `mobile_otp` | OTP creation/verification state |

Composite foreign keys plant/property relationships enforce karte hain. Future migrations me sirf single `id` foreign key add karke scope columns ignore karna cross-account data risk create kar sakta hai.

## 18. Important Routes and Modules

### Account and Management

- `/admins`
- `/properties`
- `/users`
- `/properties/select`
- `/properties/switch`

### Operational

- `/inventory`
- `/inventory/booking_form`
- `/inventory/booking_detail/:id`
- `/customers`
- `/customers/bookings`
- `/customers/bookings/booking_form`
- `/customers/bookings/booking_save`
- `/customers/bookings/booking_view/:id`
- `/customers/bookings/checkin/:id`
- `/customers/checkins`
- `/customers/checkins/checkout/:id`
- `/customers/checkedouts`
- `/rooms`
- `/room-categories`

Kuch CRUD controller methods CodeIgniter implicit routes se bhi available ho sakte hain. Route cleanup se pehle views, JavaScript endpoints aur legacy bookmarks ko search karna zaruri hai.

## 19. Frontend Data and Cache Behavior

### Query Cache

`erp-query.js` session storage me stale-while-revalidate pattern use karta hai:

- Approximate cache lifetime: 10 minutes
- Cache key selected property context se scoped hai.
- Successful writes ke baad relevant namespace invalidate hona chahiye.
- `APP_FRESH`/fresh-load behavior stale list diagnose karne me useful hai.

### List Interaction

- Text filters approximately 300 ms debounce use karte hain.
- Searchable selects progressive enhancement hain.
- JS fail hone par basic form/select usable rehna chahiye jahan practical ho.
- CSS/JS filemtime cache busting multiple views me use hota hai.

Stale data issue me database ko blame karne se pehle browser session cache aur namespace invalidation verify karein.

## 20. Expected HTTP Behavior

| Status | Expected meaning |
| --- | --- |
| `200` | Page/form success, ya validation errors ke saath safe rerender |
| `302` | Normal redirect after navigation/write |
| `403` | Role denied ya invalid session/customer write token |
| `404` | Record missing, out of active scope, ya wrong workflow/status route |
| `409` | Stale property context, concurrent booking conflict, stale status/room state |
| `422` | Invalid Inventory modal/input payload |
| `500` | Unexpected programming/server failure only |

AJAX endpoints ko valid JSON return karna chahiye. Sensitive responses me browser/proxy caching avoid karein.

## 21. Performance Planning and Guardrails

### Known Cause of Previous Slowness

Development request logging ko verbose level par enable karne se every request par heavy file I/O hua tha. Normal local operation ke liye:

```php
$config['log_threshold'] = 0;
```

Verbose logging debugging ke liye temporary enable karein aur investigation ke baad restore karein.

### PHP Runtime

- Current XAMPP PHP configuration me OPcache globally disabled hai.
- Application current tested dataset par OPcache ke bina acceptable perform kar rahi hai.
- Production/deployment optimization me OPcache useful ho sakta hai, lekin code correctness us par depend nahi honi chahiye.
- App-level `.htaccess` me OPcache forcibly disable karne ki zarurat nahi honi chahiye.
- Output compression currently disabled hai.

### Performance Regression Checklist

Future frontend work me verify karein:

- DOM size unexpectedly multiple times grow to nahi hua.
- Event listeners har render/navigation par duplicate attach to nahi ho rahe.
- Hover/mousemove handler full table ya large form scan to nahi karta.
- Inventory still 15 rooms/page limit follow karti hai.
- AJAX request loop ya repeated refresh to nahi hai.
- Browser console me continuous errors to nahi hain.
- Backend request timings normal hain.
- Database query count ya overlapping availability query unexpectedly grow to nahi hui.

## 22. Ab Tak Ke Major Change Phases

### Phase 1: Rooms and Inventory Foundation

- Room master aur inventory date grid banaya gaya.
- Available/booked state aur date range behavior add hua.
- Occupied room popups/actions add hue.

### Phase 2: Inventory Booking and Media Interaction

- Inventory se booking modal flow add hua.
- Camera/image capture interaction introduce hua.
- Booking view ko room availability se connect kiya gaya.

### Phase 3: Multi-account and Multi-property Migration

- Plants/accounts, properties aur users ke ownership rules add hue.
- Super Admin/Admin/User roles establish hue.
- Property selection aur user-property assignments add hue.
- Role-based navigation aur access checks add hue.

### Phase 4: Data Preservation

- Soft delete policy establish hui.
- Composite constraints aur active-record uniqueness add hui.
- Historical bookings ko physical master-data deletion se protect kiya gaya.

### Phase 5: Authentication Hardening

- Environment-controlled OTP exposure helper add hua, lekin current `Auth::send_otp()` response me helper wiring pending hai.
- Attempt counter/message, expiry aur single-use behavior add hua; strict attempts-cap invalidation current controller me pending hai.
- SMS gateway abstraction add hui.
- Fresh login session/property state handling improve hua.

### Phase 6: Identity Security and UX

- Multi-row, two-sided identity documents add hue.
- Camera, crop aur mobile capture flows add hue.
- Upload validation aur secure authenticated streaming add hui.

### Phase 7: Booking Integrity

- Date overlap/availability rules centralize hue.
- Transaction locks aur final availability recheck add hua.
- Booking, check-in aur check-out status transitions strengthen hue.

### Phase 8: Controller Split

- Large Customers controller responsibilities `Bookings`, `Checkins`, `Checkouts` aur shared `Ops_Controller` me split hui.
- Legacy URLs preserve kiye gaye taaki existing buttons/bookmarks na tootein.

### Phase 9: Latest Stability and Performance Fixes

- Excessive logging disable karke page latency normalize ki gayi.
- Checkout legacy routes restore kiye gaye.
- Fresh customer/check-in write token creation fix hua.
- Inventory booking callback aur date helper shared controller me restore hue.
- Invalid date par undefined helper `500` fix hua.
- Room model aliases add hue taaki edit views me missing-property warnings na aayein.
- Super Admin property selector label normalize hua.
- Inventory ko 15-room pagination aur lower-cost selection repaint mila.
- Role, route, workflow, privacy, XSS, responsive aur performance tests broad set par run hue.

## 23. Current Verification Baseline

Latest verification me following areas pass hue:

- Changed/untracked PHP files syntax lint: 58 files, zero failures
- Authenticated backend smoke coverage: 106/106 requests
- Role-responsive layouts: 30 tested layouts
- Inventory workflow and booking details UI
- Inventory privacy and XSS checks
- Identity upload guard behavior
- OTP exposure helper rules; actual `Auth::send_otp()` integration abhi pending hai
- Responsive identity, camera and crop UI
- Cross-role operational route behavior
- Apache/CodeIgniter logs me test period ke during no new unexpected error

Live database me existing E2E/test-created rows intentionally delete nahi kiye gaye. Unko cleanup karne se pehle backup aur explicit approval required hai.

## 24. Safe Verification Commands

### Syntax and Static Checks

```powershell
C:\xampp\php\php.exe -l application\controllers\Inventory.php
C:\xampp\php\php.exe tests\identity_upload_guard_test.php
C:\xampp\php\php.exe tests\otp_exposure_test.php
C:\xampp\php\php.exe tests\audit_encoding.php
node --check assets\js\inventory-booking.js
```

Changed PHP files ko individually lint karein. Ek syntax failure deployment block maana jana chahiye.

### Browser/UI Checks

```powershell
node tests\role_management_responsive_ui_test.mjs
node tests\inventory_booking_details_ui_test.mjs
node tests\responsive_identity_ui_test.mjs
```

Yeh login/OTP state aur `last_login` update kar sakte hain. In scripts ko run karne se pehle local server aur test expectations check karein.

### Integrated Test Runner

```powershell
composer test
```

ya project configuration ke hisab se:

```powershell
C:\xampp\php\php.exe run_tests.php
```

Multitenancy/soft-delete tests disposable random test databases create/drop karte hain aur live database refuse karne ke liye designed hain. Phir bhi database config verify aur backup karna mandatory operational discipline hai.

### Scripts Jo Live Data Par Casually Nahi Chalane

- `tests/e2e_minimal.php`
- `debug_e2e_flow.php`
- Repair/rebuild/patch/fix scripts
- `db/stay_management.sql`

Inme business records create/update karne ya database drop/recreate karne ki capability ho sakti hai. Snapshot import live retention database par kabhi directly na karein.

## 25. Troubleshooting Matrix

| Symptom | Sabse pehle kya check karein |
| --- | --- |
| Sab pages slow | `log_threshold=0`, Apache/PHP errors, DB response, network waterfall, OPcache/runtime state |
| Inventory hover/click late | Active room count, 15-row pagination, DOM count, long tasks, full-grid repaint, accumulated E2E fixtures |
| Button wrong page kholta hai | `routes.php`, status-to-action mapping, controller split ke legacy routes |
| Fresh form par `403` | Customer/session write token create hua ya nahi; property switch ke baad stale form |
| `409 property_context_changed` | Dusre tab me property switch hui; page refresh karke latest context/token lein |
| Valid record par `404` | Active property, role authorization, record status aur plant/property ownership |
| Inventory modal `422` | Dates, room, required fields aur availability callback response |
| Undefined method `500` | Controller split ke baad helper `Ops_Controller` me available hai ya nahi |
| Room edit warning | Model aliases `is_active`/`created_by` aur view property names |
| List stale dikh rahi | `erp-query.js` cache key, namespace invalidation, session storage, `APP_FRESH` |
| Identity file nahi khul rahi | Ownership scope, secure path, stored metadata, MIME/extension validation |
| Dusri property ka data dikh raha | Critical security issue: request stop karein, scope query/FK/token inspect karein |
| Duplicate room booking | Transaction locks, overlap query aur final availability recheck verify karein |

## 26. Future Change Impact Checklist

Har meaningful change se pehle yeh questions answer karein:

### Scope

- Change plant/account level hai ya property level?
- Customer sharing allowed hai, lekin booking/history disclosure allowed hai?
- Super Admin, Admin aur User teeno ka expected behavior kya hai?
- Active property switch ke baad old tab ka behavior kya hoga?

### Data

- Existing historical data preserve hoga?
- Soft delete rules follow honge?
- Composite plant/property foreign keys required hain?
- Migration reversible hai aur backup liya gaya hai?

### Workflow

- Kaun se current statuses accept honge?
- Success par next status kya hoga?
- Duplicate request idempotent hogi?
- Wrong status request `404`, `409` ya validation response me se kya degi?
- Inventory occupancy/date rules par kya impact hai?

### Security

- Kaunsa token required hai?
- Role authorization controller aur model dono boundaries par consistent hai?
- File/data URL unauthorized property se accessible to nahi?
- JSON/HTML output escaped hai?
- Sensitive response cached to nahi ho raha?

### Performance

- Query count aur DOM size before/after measure kiya?
- Large property aur mobile viewport test hua?
- Mouse/scroll/input handlers bounded work karte hain?
- Cache invalidation successful writes ke baad hoti hai?

### Compatibility

- Existing route, bookmark aur button preserved hain?
- Controller method move ke baad shared dependencies available hain?
- Existing database rows without new nullable fields still load hote hain?
- Current browser tests aur PHP tests pass hain?

## 27. Behavior Drift Identification Procedure

Future me lage ki app alag behave kar rahi hai to yeh order follow karein:

1. Exact role, selected property, URL, record ID/status aur timestamp note karein.
2. Same action ka expected result is document me identify karein.
3. Browser Network tab me request URL, method, status code aur duration record karein.
4. Browser Console errors check karein.
5. CodeIgniter/Apache/PHP logs check karein, lekin permanent verbose logging enable na chhodein.
6. Record ke `fk_plant`, `property_id`, status, room aur dates database me verify karein.
7. Recent Git diff me routes, shared controllers, models, JS listeners, cache aur migrations inspect karein.
8. Smallest relevant test run karein; phir role/workflow regression suite run karein.
9. Expected behavior deliberately badla hai to isi document ko same change me update karein.
10. Behavior unintended hai to fix ke saath regression test add karein.

## 28. Future Feature Planning

### OTA/Online Booking Channel

`implement/ONLINE_BOOKING_CHANNEL_INTEGRATION_PLAN.md` me blueprint available hai, lekin online channel integration ko current implemented workflow na samjhein.

OTA work se pehle explicitly design karein:

- External booking idempotency
- Webhook signature/security
- Category-level capacity vs physical room assignment
- Booking without `room_id` ka inventory consumption
- Cancellation/no-show synchronization
- Retry, reconciliation aur audit history
- Rate/tax/payment ownership
- Per-property channel credentials

### Production Readiness

- `Auth::send_otp()` se raw OTP response remove/guard karke `otp_exposure_allowed()` wire karein.
- Production SMS provider ko actual authentication flow me wire aur live verify karein.
- `ENVIRONMENT` aur `DEV_EXPOSE_OTP` secure values confirm karein; sirf config set karna current controller gap ko solve nahi karta.
- HTTPS par secure cookie settings review karein.
- Database, `.env` aur `secure_uploads` backup/restore drill karein.
- OPcache aur deployment caching controlled environment me benchmark karein.
- Error logging enable karein, lekin request-level verbose logging ka volume control karein.

## 29. Backup and Versioning Policy

Current working tree me uncommitted changes ho sakte hain. Is document ki date current working behavior ko describe karti hai, sirf current Git `HEAD` ko nahi.

Major future work se pehle:

1. Database backup lein.
2. `secure_uploads` backup lein.
3. `.env` ka secure backup lein; repository me secrets commit na karein.
4. Current code changes review karke baseline commit/tag banayein.
5. Relevant smoke/performance tests run karke result note karein.
6. Feature branch me change karein.
7. Migration aur rollback steps document karein.

## 30. Change Record Template

Har major behavior change ke saath neeche jaisa record is file ke end me add karein:

```markdown
### YYYY-MM-DD: Short change title

- Reason:
- Modules/routes changed:
- Database changes:
- Expected old behavior:
- Expected new behavior:
- Role/property scope impact:
- Security/token impact:
- Performance before/after:
- Tests run:
- Migration steps:
- Rollback steps:
- Known limitations:
```

## 31. Non-negotiable Invariants

Future development me in rules ko accidental change nahi hona chahiye:

1. Ek plant/account ka private operational data dusre plant ko nahi dikhna chahiye.
2. Ek property ka booking/room/identity data unauthorized property context me nahi dikhna chahiye.
3. Role-denied management action execute nahi honi chahiye.
4. Booking save ke time availability transaction ke andar recheck honi chahiye.
5. Checkout day room availability ke liye free maana jana chahiye.
6. Cancelled/no-show stay inventory occupy nahi karna chahiye.
7. Business master delete historical data destroy nahi karna chahiye.
8. Identity files direct public path se serve nahi hone chahiye.
9. Property switch ke baad stale write context accept nahi hona chahiye.
10. Normal validation problem ko unexpected `500` me convert nahi karna chahiye.
11. Inventory interaction visible dataset ke size ke proportion me bounded rehni chahiye.
12. Deliberate workflow change ke saath tests aur yeh document dono update hone chahiye.

## 32. Complete Navigation Shell

### Login ke Baad Global Layout

Active property available hone par top navigation me yeh controls dikhte hain:

| Control | Kahan jata hai | Database effect |
| --- | --- | --- |
| Stay Management logo | Active property ho to `/inventory`; otherwise property selection/management path | Read only; koi business row change nahi |
| Active property selector | POST `/properties/switch`, phir `/inventory` | Business DB unchanged; session `active_property_id` aur property tokens rotate; last-property cookie update |
| Inventory | `/inventory` | Read only jab tak booking save na ho |
| Customer Master | `/customers` | List load read only |
| Room Master | `/rooms` | List load read only |
| Manage -> Plants | `/admins`, sirf Super Admin | List read only |
| Manage -> Properties | `/properties`, Super Admin/Admin | List read only |
| Manage -> Hotel Users | `/users`, Super Admin/Admin | List read only |
| Logout | `/logout` | Session destroy; business tables unchanged |

Active property hone par main navigation ke neeche status tabs har operational page par available hain:

| Tab | URL | Sirf kaun se records |
| --- | --- | --- |
| Booking Details | `/customers/bookings` | `booking_details.sd_id` ka code `room_booked` |
| Check-in Details | `/customers/checkins` | Status code `checked_in` |
| Check-out Details | `/customers/checkedouts` | Status code `checked_out` |

Yeh teen alag booking tables nahi hain. Teeno ek hi `booking_details` table ke status-filtered views hain. Status change hote hi record ek tab se gayab hokar dusre tab me dikhna chahiye.

### Property Na Hone par Navigation

- Normal User ke paas active assignment na ho to `/access/no-properties` aata hai; yahan sirf Logout hai.
- Admin/Super Admin ke paas operational property na ho to property management/onboarding page aata hai.
- `/access/forbidden` denied management role ka page hai; Return button application landing par le jata hai.
- In access pages se koi business DB mutation nahi hoti.

## 33. Login Screen: Actions and DB Effects

| Screen action | Request/next step | Validation | Database/session effect |
| --- | --- | --- | --- |
| Login page open | GET `/login` | Existing session user active hai ya nahi | Valid session ho to landing redirect; invalid session destroy; business DB unchanged |
| Get OTP | POST `/auth/send_otp` | Mobile format; active user; active plant/admin eligibility | `mobile_otp` me new row: `user_id`, 6-digit `otp`, `expires_at`, `status=0`, `attempts=0`, `created_at` |
| Change Number | Login form step 1 par wapas | Client-side action | DB unchanged |
| Verify OTP | POST `/auth/verify_otp` | Latest matching unexpired unused OTP | Success: target `mobile_otp.status=1`, `users.last_login` update, session ID regenerate, login/session token create |
| Wrong OTP | Same login step/error | Latest OTP state inspect | Latest `mobile_otp.attempts` increment; login session create nahi hoti |
| Resend OTP | POST `/auth/send_otp` again | Same authorization | Old OTP overwrite nahi hoti; `mobile_otp` me another audit row insert hoti hai |
| Logout | GET `/logout` | Logged-in state required nahi | PHP/CI session destroy; OTP/history rows unchanged |

Successful login landing:

1. One authorized property: property context auto-select karke Inventory.
2. Multiple authorized properties: `/properties/select`.
3. Normal User with zero properties: `/access/no-properties`.
4. Admin/Super Admin with zero properties: property management.

Current security caveat: Get OTP response raw OTP browser ko return karta hai. Yeh current demo behavior hai, production-safe behavior nahi.

## 34. Property Selection Screen

### `/properties/select`

Page har active authorized property ka card dikhata hai.

| Action | Destination | DB effect |
| --- | --- | --- |
| Open Property | POST `/properties/switch` -> `/inventory` | DB unchanged; active property session me set; context/write token rotate; `stay_last_property` cookie update |
| Unauthorized/inactive property forged POST | `404` | DB/session property change nahi |

Property selector ya top-navbar selector change ke baad old open tab ka property token stale hota hai. Old tab se write/AJAX action expected `409` de aur Inventory refresh karaye.

## 35. Inventory Page: Every Control and Effect

### Page Data

`/inventory` selected property ke liye read karta hai:

- Active `rooms`
- `room_categories`
- `booking_details`
- `status_details`
- Guest popup ke time `customers` aur booking/channel/room joins

Inventory availability DB me separate rows ke roop me save nahi hoti. Har request me active rooms minus overlapping live bookings se derive hoti hai.

### Header and Filters

| Control | Behavior | DB effect |
| --- | --- | --- |
| Previous arrow | Selected center date 6 days peeche; filters preserve | Read only |
| Date picker | Chosen date ko 11-day window ka selected/center date banata hai | Read only |
| Next arrow | Selected center date 6 days aage; filters preserve | Read only |
| Room Name/No. filter | Matching active rooms only | Read only query |
| Room Category filter | Selected active category ke rooms | Read only query |
| Clear filters | Same selected date, room/category filters remove | Read only |
| Room page links | Maximum 15 matching rooms ka next/previous slice | Read only |

Filter controls automatically submit hote hain. Inse `rooms` ya `booking_details` update nahi hote.

### Summary and Grid Meaning

- `All Rooms` row daily total Available aur Booked count dikhati hai.
- Individual row ek physical room hai.
- Cell value `1` ka matlab us room-night par available.
- Cell value `0` ka matlab occupied/unavailable.
- Past available date selectable nahi hoti.
- Today/future available cell booking selection ke liye button hoti hai.
- Occupied cell booking id ho to guest detail popup kholti hai.
- Housekeeping `Not Available` currently Inventory availability ko block nahi karta. Inventory active `rooms.status=1` par depend karti hai; housekeeping field display/filter metadata hai.

### Available Cell Workflow

| Action | UI/navigation | Database effect |
| --- | --- | --- |
| First available night click | Room aur first night select | None |
| Same room me more nights select/Last night change | Full date range availability AJAX recheck | Read-only availability query |
| Clear | Local selection remove | None |
| New Booking | `/inventory/booking_form` fragment modal me load | Read only; selected room/date server par revalidate |
| Booking modal Cancel/close | Inventory par return | None |
| Save Booking | AJAX POST `/customers/booking_save` | Customer insert/update plus `booking_details` insert in transaction |

Inventory booking save ka exact DB effect:

1. Existing phone milta hai to same plant ka `customers` row reuse/update hota hai.
2. Inactive existing customer ho to `customers.status` reactivate hota hai.
3. New phone ho to next plant-scoped customer code ke saath `customers` row insert hoti hai.
4. `booking_details` me property-scoped booking number ke saath new row insert hoti hai.
5. Forced status `room_booked` hota hai.
6. `property_id`, `fk_plant`, selected `room_id`, room-derived category, scheduled dates aur amounts save hote hain.
7. `length_of_stay` scheduled checkout minus check-in se server calculate karta hai.
8. `remaining_amount = total_amount - amount_paid` server calculate karta hai.
9. `checked_in_at` aur `checked_out_at` null rehte hain.
10. Success par Booking Details tab open hota hai aur list caches invalidate hote hain.

### Occupied Cell Workflow

Occupied cell click `/inventory/booking_detail/:id` se fresh status read karta hai. Popup view karne se DB update nahi hoti.

| Fresh status | Popup action | Destination | Save effect later |
| --- | --- | --- | --- |
| `room_booked` | Check-in | `/customers/bookings/checkin/:id` | Check-in form submit par status/customer/stay update |
| `checked_in` | Check-out | `/customers/checkins/checkout/:id` | Confirm par status checked_out/timestamp update |
| `checked_out` | View record | `/customers/checkedouts/details/:id` | Read only |

Popup intentionally identity rows/files load nahi karta.

## 36. Customer Master Page: Every Action and Effect

### `/customers` List

Columns:

- Customer ID/code
- Customer Name
- Mobile
- Pincode
- Country
- Status
- Actions

Filters: Customer ID, name, phone aur status. Typing ke 300 ms baad `/customers/list_ajax` read-only request hoti hai. Default list active records dikhati hai; status filter inactive/all rows dekhne ke liye use ho sakta hai.

| Action | Destination | DB/file effect |
| --- | --- | --- |
| Add Customer | `/customers/form` | Form load read only; next code preview |
| Eye/View | AJAX `/customers/view/:id`, modal | Read only; customer aur current-property unscoped identity metadata/secure links read |
| View Front/File or Back | `/customers/identity_file/:identity_id/:slot` | Read only secure stream; DB unchanged |
| Edit | `/customers/form/:id` | Form load read only |
| Delete | POST `/customers/delete/:id` | Soft delete: `customers.status=0`, `updated_at` update; bookings/identities/files retained |
| Clear filters | Active list reload | Read only |

Customer Delete ke baad row default active list se disappear karti hai, lekin physical customer row, booking history aur identity rows delete nahi hote.

### Add/Edit Customer Form

Fields aur destinations:

| Field/control | Database mapping/effect |
| --- | --- |
| Customer ID | Add par server-generated `customers.customer_code`; edit par immutable |
| Customer Name | `customers.customer_name` |
| Mobile No | `customers.phone`; plant-wide duplicate check |
| Pincode | `customers.pincode` |
| Country | `customers.country` |
| Active | `customers.status` through application alias `is_active` |
| Identity type/number | `customer_identities.identity_type`, `identity_number` |
| Front/file and back image | Secure relative paths in `document_path`, `document_path_2` |
| Cancel | `/customers`; no save |
| Save/Update Customer | POST `/customers/save`; customer transaction, then identity sync |

Customer row save transaction plant-level creation sequence lock use karti hai, phone uniqueness dobara check karti hai aur `created_at`/`updated_at` maintain karti hai.

Identity rows ka exact behavior:

- Add row -> `customer_identities` insert with plant, current property, customer and optional null booking scope.
- Existing row edit -> same scoped row update.
- Front/back valid replacement -> new unpredictable file save aur DB path update; successful replacement ke baad old physical file unlink hoti hai.
- Individual image remove -> DB path null; physical file current `_delete_identity_path()` no-op policy ki wajah se retain hoti hai.
- Entire identity row remove -> `customer_identities.status=0`; files physically retain hote hain.
- PDF front upload successful ho to back path null kiya jata hai; old back physical file retain hoti hai.
- Customer scalar save commit hone ke baad identity filesystem/DB sync hoti hai. Document error aaye to customer save rollback nahi hota; warning flash aati hai.

## 37. Booking Details Tab: Every Action and Effect

### `/customers/bookings`

Yeh tab sirf `room_booked` status rows dikhata hai.

Columns/filters:

- Booking No
- Customer Name
- Room No
- Room Category
- Status
- Actions

Filters booking number, customer name, room number aur room category par server-side apply hote hain. Is tab par date column/filter hidden hai.

| Action | Destination | DB effect |
| --- | --- | --- |
| New Booking | `/customers/booking_form` | Form load read only |
| Eye/View | AJAX `/customers/booking_view/:id` modal | Read only; customer, booking, room, category, channel, amounts and booking-scoped identities |
| Check-in icon | `/customers/bookings/checkin/:id` | Form load read only; save par transition possible |
| Edit booking | `/customers/bookings/edit/:id` | Form load; save customer and booking update |
| Modal Close | Same tab | None |
| Modal primary Check-in | Same check-in route | Form load only |

### Manual New/Edit Booking Form

Customer section:

- Mobile number type karne par `/customers/lookup` same plant customer read karta hai.
- Match ho to name/pincode/country autofill hote hain.
- New number ho to booking save ke transaction me customer create hota hai.
- Existing booking edit me customer identity phone ko dusre customer par switch karna blocked hai.

Booking fields and DB columns:

| Form field | `booking_details` effect |
| --- | --- |
| Booking Status | `sd_id`; `status_details` reference validated |
| Booking Channel | `booking_channel_id`; active channel required if selected |
| Property Name | Posted value trust nahi hoti; selected property se `property_id` and name snapshot |
| Check In datetime | `checked_in_at` in normal manual form |
| Check Out datetime | `checked_out_at` in normal manual form |
| Room Category | `room_category_id`; selected room ho to room ki category server override karti hai |
| Allot Room | `room_id`; current property aur overlap availability validate |
| Room Quantity | `room_quantity` |
| Length of Stay | `length_of_stay` |
| Total Guests | `total_guest` |
| Total Units | `total_unit` |
| Total Amount | `total_amount` |
| Amount Paid | `amount_paid` |
| Remaining Amount | Posted nahi hota; server-derived `remaining_amount` |

Save effects:

- New booking: `customers` insert/update plus `booking_details` insert atomically.
- Edit booking: linked customer submitted fields update plus same scoped `booking_details` row update.
- `booking_number`, `customer_id`, `fk_plant` aur `property_id` edit me immutable hain.
- Status `checked_in` choose ho aur timestamp blank ho to server current `checked_in_at` stamp karta hai.
- Status `checked_out` choose ho to missing check-in and checkout timestamps current time se stamp hote hain.
- Manual form currently active status dropdown expose karta hai. Isliye authorized operator direct status select kar sakta hai; resulting row selected status tab me dikhegi.
- Already `checked_in` booking ko booking edit URL se open karne par Check-in Edit par redirect hota hai.
- Already `checked_out` booking ko booking edit URL se open karne par read-only Check-out Edit page par redirect hota hai.

## 38. Check-in Details Tab: Every Action and Effect

### `/customers/checkins`

Sirf `checked_in` rows dikhte hain. Booking columns ke saath Date column/filter bhi hota hai. Date current checked-in timestamp day par filter hoti hai.

| Action | Destination | DB effect |
| --- | --- | --- |
| Eye/View | Booking detail modal | Read only |
| Check-out icon | `/customers/checkins/checkout/:id` | Confirmation page load; submit par checkout transition |
| Edit check-in | `/customers/checkins/edit/:id` | Editable checked-in customer/stay page |
| Date/text filters | AJAX `/customers/checkins_ajax` | Read only |

### Check-in from Booking Details

Route only `room_booked` record accept karta hai. Form fields:

- Customer name and mobile
- Booking status
- Scheduled check-in/check-out dates
- Room category and available physical room
- Dynamic booking-scoped identity rows/files

Save `/customers/checkin_save` ka effect:

1. Current row status `room_booked` revalidate aur transaction me lock hota hai.
2. Customer phone plant-wide collision check hota hai.
3. Selected room/current and old room lock hote hain.
4. Date overlap availability transaction me recheck hoti hai.
5. `customers.customer_name` aur `customers.phone` update hote hain.
6. `booking_details.sd_id`, `room_id`, room-derived `room_category_id`, scheduled dates and `length_of_stay` update hote hain.
7. Target status `checked_in` ho to existing timestamp preserve ya current `checked_in_at` set hota hai.
8. Non-checked-out status par `checked_out_at` null rehta hai.
9. DB commit ke baad booking-scoped `customer_identities` sync hoti hain.
10. Status `checked_in` hone par record Booking Details se Check-in Details tab me move hota hai.

Future scheduled arrival ko scheduled date se pehle `checked_in` nahi kiya ja sakta. Walk-in/manual record without scheduled date current time par check in ho sakta hai. Check-in page `checked_out` target block karta hai; checkout dedicated page se hota hai.

### Edit Check-in

- Route sirf current `checked_in` record accept karta hai.
- Status hidden/locked `checked_in` rehta hai.
- Customer name/mobile, dates, room/category aur booking-scoped identities change ki ja sakti hain.
- Save same `customers`, `booking_details` aur `customer_identities` effects use karta hai.
- Success ke baad Check-in Details tab par return.
- Stale status ya room conflict par save reject; newer checkout overwrite nahi hota.

## 39. Check-out Details Tab: Every Action and Effect

### Checkout Confirmation from Check-in Tab

`/customers/checkins/checkout/:id` sirf current `checked_in` booking accept karta hai.

Page par customer, booking, room, scheduled/actual dates, guests, amounts aur identities read only hain. Sirf Status selector me `Checked In` aur `Checked Out` available hain.

| Action | Result | DB effect |
| --- | --- | --- |
| Back | Check-in Details | None |
| Status Checked In + Confirm | Check-in Details par redirect | None |
| Status Checked Out + Confirm | Check-out complete | `booking_details.sd_id=checked_out`, missing `checked_in_at` fill, `checked_out_at=now`, `updated_at` update |
| Duplicate submit after complete | Check-out Details redirect | No second transition |
| Status changed by another tab | `409` | Transaction rollback |

Checkout customer, room, scheduled dates, amounts ya identities modify nahi karta.

### `/customers/checkedouts`

Sirf `checked_out` rows dikhte hain. Date filter checkout timestamp day par apply hoti hai.

| Action | Destination | DB effect |
| --- | --- | --- |
| Eye/View | Booking detail modal | Read only |
| Check-out record icon | `/customers/checkedouts/details/:id` | Full completed record read only |
| Edit icon | `/customers/checkedouts/edit/:id` | Label Edit hai, page deliberately read only |
| Identity links | Secure stream endpoint | Read only |
| Back | Check-out Details list | None |

Completed checkout record current application me edit/reopen nahi kiya ja sakta.

## 40. Room Master: Every Action and Effect

### `/rooms` List

Columns:

- Room No
- Category
- Floor
- Price
- Housekeeping
- Status
- Added By
- Actions

Filters: room number, category, floor, housekeeping status and active/inactive/all status.

| Action | Destination | DB effect |
| --- | --- | --- |
| Add Category | `/room-categories/add` | Form load only |
| Add Room | `/rooms/form` | Form load and next room code preview |
| Eye/View | AJAX `/rooms/view/:id` | Read only modal |
| Edit | `/rooms/form/:id` | Form load |
| Delete | POST `/rooms/delete/:id` | Soft delete `rooms.status=0`, `updated_at`; booking history retained |
| Clear filters | List reload | Read only |

### Room Form

| Field | `rooms` effect |
| --- | --- |
| Room ID | New row par generated `room_code`, edit par immutable |
| Room Name/Number | `room_no`, property-wide active duplicate validation |
| Category | Nullable `category_id`; selected active current-property category only |
| Floor | `floor_no` |
| Extra Bed Allowed | `extra_bed_allowed` |
| Description | `description` |
| Remarks | `remarks` |
| Price | `selling_price`, non-negative |
| Housekeeping Status | `housekeeping_status`: Available/Not Available |
| Active | `rooms.status` |
| Save new | Insert with `property_id`, generated code, `added_by`, `created_at` |
| Update | Scoped update plus `updated_by`, `updated_at` |
| Cancel | `/rooms`, no DB change |

Room deactivate hone par room active Inventory rows aur new room selection se disappear karta hai. Purani `booking_details.room_id` history intact rehti hai.

## 41. Room Categories: Every Action and Effect

### `/room-categories`

Room Master ke Add Category button se Add form khulta hai. Category list direct `/room-categories` route par Name, Code, Capacity, Bed, Status, Order aur Actions dikhati hai.

| Action | Destination | DB effect |
| --- | --- | --- |
| Room Master | `/rooms` | None |
| Add Category | `/room-categories/add` | Form load |
| Edit | `/room-categories/edit/:id` | Form load |
| Delete | POST `/room-categories/delete/:id` | Soft delete `room_categories.status=0`, `updated_at`; rooms/bookings retained |

Category form maps:

- Name -> `category_name`, property-wide uniqueness
- Short Code -> `short_code`, property-wide uniqueness when present
- Description -> `description`
- Max Adults/Children -> `max_adults`, `max_children`
- Room Size/Unit -> `room_size`, `room_size_unit`
- Bed Type/Count -> `bed_type`, `bed_count`
- Default Tax -> `default_tax_id` referencing active `gst_rates`
- SAC Code -> `default_sac_code`
- Display Order -> `display_order`
- Smoking Allowed -> `smoking_allowed`
- Active -> `status`

Save insert/update current `property_id` ke andar hota hai. Deactivated category history me retained hai. Existing active room ka category FK automatically null/change nahi hota, lekin new form dropdown active categories only dikhata hai.

## 42. Manage Menu: Plants, Properties and Hotel Users

### Plants (`/admins`) - Super Admin Only

List columns: owner/admin, mobile, plant name, property count, user count, status and actions.

| Action | Tables/columns affected |
| --- | --- |
| Add Plant | Transaction: `plants` insert with active plant; owner `users` insert with role `admin` and selected active status |
| Edit Plant | Owner `users.name/mobile_no/status` update; `plants.plant_name` update; ownership immutable |
| Activate/Deactivate button | Current code only owner Admin `users.status` update karta hai; `plants.plant_status` unchanged |
| Delete | Soft-deactivate both `plants.plant_status=0` and owner Admin `users.status=0`; child/business rows retained |

Important current caveat: Delete ke baad Activate button only owner user ko active karta hai, plant row ko nahi. Agar `plants.plant_status=0` hai to plant operational login still blocked reh sakta hai. Future fix me button label aur both-table transaction align karna chahiye.

### Properties (`/properties`) - Super Admin/Admin

List columns: property, code, Super Admin ke liye plant, status and actions.

| Action | Navigation/effect | DB effect |
| --- | --- | --- |
| Add Property | Form -> save | `properties` insert with immutable `fk_plant`, code/name/status, creator/time |
| Edit | Form -> save | `property_name`, `property_code`, `status`, `updated_at`; owner plant unchanged |
| Open | Switch -> Inventory | DB unchanged; session property and cookie update |
| Activate/Deactivate | List return | `properties.status` and `updated_at` update |
| Delete | List return | Same soft-deactivate; rooms/bookings/users/access rows retained |

Selected property deactivate/delete hone par current property context clear hota hai. Admin ka first active property create ho aur no context ho to save directly Inventory me enter kar sakta hai.

### Hotel Users (`/users`) - Super Admin/Admin

List columns: user, mobile, Super Admin ke liye plant, assigned property count, status and actions.

| Action | Tables/columns affected |
| --- | --- |
| Add User | `users` insert with role forced `user`, selected plant/status; `user_property_access` assignments insert |
| Edit User | `users.name/mobile_no/status/updated_at` update; existing property access rows delete and selected set re-insert |
| Activate/Deactivate | `users.status` and `updated_at` update; assignments retained |
| Delete | Soft-deactivate `users.status=0`; user/audit/access rows retained |
| Plant filter/select | Read-only list/form reload for that plant |

Admin sirf apne plant ke user manage karta hai. Super Admin plant choose kar sakta hai. Property assignments sirf selected plant ki active properties ho sakti hain. Empty assignment ka matlab normal User operational property access nahi paayega.

## 43. Page-to-Table Read/Write Summary

| Page/module | Main reads | Possible writes |
| --- | --- | --- |
| Login | `users`, `plants`, `mobile_otp`, authorized properties | `mobile_otp`, `users.last_login`, session |
| Property selector | `properties`, `user_property_access`, `plants/users` labels | Session/cookie only |
| Inventory | `rooms`, `room_categories`, `booking_details`, `status_details` | Booking modal through `customers`, `booking_details` |
| Customer Master | `customers`, `customer_identities` | `customers`, `customer_identities`, secure upload files |
| Booking Details | `booking_details`, `customers`, rooms/categories/status/channels/identities | `customers`, `booking_details` |
| Check-in Details | Same booking joins plus available rooms | `customers`, `booking_details`, `customer_identities`, secure files |
| Check-out Details | Completed booking/customer/identity joins | Checkout confirmation writes only `booking_details` status/timestamps; completed pages read only |
| Room Master | `rooms`, `room_categories` | `rooms` |
| Room Categories | `room_categories`, `gst_rates` | `room_categories` |
| Plants | `plants`, Admin `users`, counts | `plants`, Admin `users` |
| Properties | `properties`, plants/Admin labels | `properties`, session/cookie on Open |
| Hotel Users | `users`, plants, properties, `user_property_access` | `users`, `user_property_access` |

## 44. Cross-page Cache and Refresh Effects

- Customer save/delete invalidates Customer and Booking-related cached lists.
- Booking/status save invalidates Booking, Check-in and Check-out namespaces because one row can move tabs.
- Room save/delete invalidates Room list; Inventory next load reads active room state directly.
- Property switch changes `APP_CONTEXT_KEY`, so another property's session-storage rows must not be reused.
- View/filter/modal/Cancel actions should never invalidate data because they do not write.
- A successful DB write followed by stale visible data normally cache invalidation issue hai, DB failure nahi.

## 45. Current Functional Gaps to Remember

Yeh current code observations hain, planned features nahi:

1. Raw OTP current `send_otp` JSON me always returned hai; environment helper wired nahi hai.
2. SMS gateway abstraction actual OTP send flow me wired nahi hai.
3. Five wrong-attempt message/counter hai, lekin correct OTP path attempts cap check nahi karta; strict OTP lockout incomplete hai.
4. Plant Activate/Deactivate button owner Admin user status change karta hai, plant status nahi; Delete both ko inactive karta hai.
5. Housekeeping `Not Available` room ko Inventory booking se automatically block nahi karta.
6. Manual booking form active status dropdown se direct checked-in/checked-out state create kar sakta hai.
7. Completed check-out Edit action read-only page hai; reopen/edit lifecycle nahi hai.
8. Identity row/image remove DB reference clear/deactivate karta hai lekin physical file retain hoti hai; valid file replacement successful ho to replaced old physical file unlink hoti hai.
9. Booking without physical `room_id` room-grid capacity consume nahi karti.
10. Cancellation aur no-show reference statuses hain, dedicated complete management workflow nahi hai.
11. OTA/channel integration blueprint hai, implemented live integration nahi.

## 46. Complete Endpoint Directory

### Authentication and Access

| URL | Method | Controller action | Purpose |
| --- | --- | --- | --- |
| `/login` | GET | `Auth::index` | Login page/session landing |
| `/auth/send_otp` | POST/AJAX | `Auth::send_otp` | Create OTP row |
| `/auth/verify_otp` | POST/AJAX | `Auth::verify_otp` | Consume OTP and create login session |
| `/logout` | GET | `Auth::logout` | Destroy session |
| `/access/no-properties` | GET | `Access::no_properties` | User has no active property |
| `/access/forbidden` | GET | `Access::forbidden` | Role denied page |

### Inventory, Customer and Stay Operations

| URL | Method | Controller action | Purpose/effect |
| --- | --- | --- | --- |
| `/` or `/inventory` | GET | `Inventory::index` | Availability calendar |
| `/inventory/booking_form` | GET/AJAX | `Bookings::inventory_booking_form` | Validate selection and return booking form fragment |
| `/inventory/booking_detail/:id` | GET/AJAX | `Inventory::booking_detail` | Read occupied cell guest/booking summary |
| `/customers` | GET | `Customers::index` | Customer Master list shell |
| `/customers/list` or `/customers/list_ajax` | GET/AJAX | `Customers::list_ajax` | Filtered customer rows |
| `/customers/add` or `/customers/form` | GET | `Customers::form` | Add customer form |
| `/customers/edit/:id` or `/customers/form/:id` | GET | `Customers::form` | Edit customer form |
| `/customers/save` | POST | `Customers::save` | Insert/update customer and sync identities |
| `/customers/lookup` | GET/AJAX | `Customers::lookup` | Phone-based booking autofill |
| `/customers/view/:id` | GET/AJAX | `Customers::view` | Customer detail modal payload |
| `/customers/delete/:id` | POST/AJAX | `Customers::delete` | Soft-deactivate customer |
| `/customers/identity_file/:id/:slot` | GET | `Customers::identity_file` | Secure identity stream |
| `/customers/bookings` | GET | `Bookings::index` | `room_booked` tab |
| `/customers/bookings_ajax` | GET/AJAX | `Bookings::bookings_ajax` | Booking tab filtered rows |
| `/customers/booking_form` | GET | `Bookings::booking_form` | Manual new booking |
| `/customers/bookings/edit/:id` | GET | `Bookings::booking_form` | Edit reservation |
| `/customers/booking_save` | POST or AJAX | `Bookings::booking_save` | Customer plus booking transactional save |
| `/customers/booking_view/:id` | GET/AJAX | `Bookings::booking_view` | Full booking modal payload |
| `/customers/available_rooms` | GET/AJAX | `Bookings::available_rooms_ajax` | Date-range available rooms |
| `/customers/checkins` | GET | `Checkins::index` | `checked_in` tab |
| `/customers/checkins_ajax` | GET/AJAX | `Checkins::checkins_ajax` | Check-in tab rows |
| `/customers/bookings/checkin/:id` | GET | `Checkins::checkin` | Reservation check-in form |
| `/customers/checkins/edit/:id` | GET | `Checkins::checkin_edit` | Edit current check-in |
| `/customers/checkin_save` | POST | `Checkins::checkin_save` | Customer/stay/identity save and status transition |
| `/customers/checkins/checkout/:id` | GET | `Checkouts::checkout` | Checkout confirmation |
| `/customers/checkout_save` | POST | `Checkouts::checkout_save` | Checked-in to checked-out transition |
| `/customers/checkedouts` | GET | `Checkouts::index` | `checked_out` tab |
| `/customers/checkedouts_ajax` | GET/AJAX | `Checkouts::checkedouts_ajax` | Check-out tab rows |
| `/customers/checkedouts/details/:id` | GET | `Checkouts::checkedout_details` | Completed record read only |
| `/customers/checkedouts/edit/:id` | GET | `Checkouts::checkedout_edit` | Read-only completed record under Edit label |

### Room Master

| URL | Method | Controller action | Purpose/effect |
| --- | --- | --- | --- |
| `/rooms` or legacy `/dashboard` | GET | `Rooms::index` | Room list |
| `/rooms/list` or `/rooms/list_ajax` | GET/AJAX | `Rooms::list_ajax` | Filtered room rows |
| `/rooms/add` or `/rooms/form` | GET | `Rooms::form` | Add room |
| `/rooms/edit/:id` or `/rooms/form/:id` | GET | `Rooms::form` | Edit room |
| `/rooms/save` | POST | `Rooms::save` | Insert/update room |
| `/rooms/view/:id` | GET/AJAX | `Rooms::view` | Room detail modal |
| `/rooms/delete/:id` | POST/AJAX | `Rooms::delete` | Soft-deactivate room |
| `/room-categories` | GET | `RoomCategories::index` | Category list |
| `/room-categories/add` | GET | `RoomCategories::form` | Add category |
| `/room-categories/edit/:id` | GET | `RoomCategories::form` | Edit category |
| `/room-categories/save` | POST | `RoomCategories::save` | Insert/update category |
| `/room-categories/delete/:id` | POST | `RoomCategories::delete` | Soft-deactivate category |

### Management

| URL | Method | Controller action | Purpose/effect |
| --- | --- | --- | --- |
| `/admins` | GET | `Admins::index` | Plant/owner list |
| `/admins/add` | GET | `Admins::form` | Add plant/admin form |
| `/admins/edit/:id` | GET | `Admins::form` | Edit plant/admin |
| `/admins/save` | POST | `Admins::save` | Plant/admin insert/update |
| `/admins/status/:id` | POST | `Admins::status` | Owner Admin user status toggle |
| `/admins/delete/:id` | POST | `Admins::delete` | Plant and owner Admin soft-deactivate |
| `/properties` | GET | `Properties::index` | Property list |
| `/properties/add` | GET | `Properties::form` | Add property |
| `/properties/edit/:id` | GET | `Properties::form` | Edit property |
| `/properties/save` | POST | `Properties::save` | Property insert/update |
| `/properties/status/:id` | POST | `Properties::status` | Property active toggle |
| `/properties/delete/:id` | POST | `Properties::delete` | Property soft-deactivate |
| `/properties/select` | GET | `Properties::select_property` | Authorized property cards |
| `/properties/switch` | POST | `Properties::switch_property` | Rotate selected property context |
| `/users` | GET | `Users::index` | Hotel User list |
| `/users/add` | GET | `Users::form` | Add user |
| `/users/edit/:id` | GET | `Users::form` | Edit user |
| `/users/save` | POST | `Users::save` | User and assignments transaction |
| `/users/status/:id` | POST | `Users::status` | User active toggle |
| `/users/delete/:id` | POST | `Users::delete` | User soft-deactivate |

## 47. Source Ownership Map

| Functional area | Main controller | Main view(s) | Main model/service |
| --- | --- | --- | --- |
| Login/OTP | `application/controllers/Auth.php` | `application/views/auth/login.php` | `User_model`, `Otp_model`, `Property_model` |
| Session/role/property guards | `application/core/MY_Controller.php` | Navbar/access views | `User_model`, `Property_model`, `Tenant_model` |
| Shared stay/upload logic | `application/core/Ops_Controller.php` | Shared customer components | `Customer_model`, `Identity_upload_guard` |
| Inventory | `application/controllers/Inventory.php` | `application/views/inventory/calendar.php` | `Inventory_model`, `inventory-booking.js` |
| Customer Master | `application/controllers/Customers.php` | `customers/list.php`, `customers/form.php` | `Customer_model`, `customers.js` |
| Booking Details | `application/controllers/Bookings.php` | `customers/bookings.php`, `booking_form.php`, `booking_form_card.php` | `Customer_model`, `bookings.js`, `booking-form.js` |
| Check-in Details | `application/controllers/Checkins.php` | `customers/checkin.php`, `customers/checkins/edit.php` | `Customer_model`, identity/camera scripts |
| Check-out Details | `application/controllers/Checkouts.php` | `customers/components/checkout_page.php` wrappers | `Customer_model` |
| Room Master | `application/controllers/Rooms.php` | `rooms/list.php`, `rooms/form.php` | `Room_model`, `rooms.js` |
| Room Categories | `application/controllers/RoomCategories.php` | `room_categories/list.php`, `form.php` | `Room_category_model` |
| Plants | `application/controllers/Admins.php` | `admins/list.php`, `form.php` | `Tenant_model`, `User_model` |
| Properties | `application/controllers/Properties.php` | `properties/list.php`, `form.php`, `select.php` | `Property_model` |
| Hotel Users | `application/controllers/Users.php` | `users/list.php`, `form.php` | `User_model`, `Property_model` |
| Route compatibility | `application/config/routes.php` | Not applicable | Controller mappings |
| Cache/context UI | Not a controller | Shared layouts | `erp-query.js`, `property-context.js`, `erp-ui.js` |

---

This file is the operational contract for the current application. Code, database constraints, tests and this guide me disagreement ho to us disagreement ko release se pehle resolve aur document karein.
