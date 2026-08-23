<?php
/**
 * Disposable-DB integration regression for tenant/property isolation.
 *
 * The live database name is refused. The current checked-in snapshot is
 * rewritten to a random stay_management_test_* database, real CI models and
 * DB constraints are exercised, then only that validated database is dropped.
 *
 * Run: C:\xampp\php\php.exe tests\multitenancy_isolation_test.php
 */

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$root = dirname(__DIR__);
$snapshot_path = $root.DIRECTORY_SEPARATOR.'db'.DIRECTORY_SEPARATOR.'stay_management.sql';
$database = 'stay_management_test_'.getmypid().'_'.bin2hex(random_bytes(4));
$database_created = FALSE;
$server_db = NULL;
$fixture_db = NULL;
$ci_db = NULL;
$failures = array();
$passes = 0;

function pass_test($label)
{
    global $passes;
    $passes++;
    echo "PASS: {$label}\n";
}

function fail_test($label, $detail = '')
{
    global $failures;
    $message = $label.($detail !== '' ? ' — '.$detail : '');
    $failures[] = $message;
    echo "FAIL: {$message}\n";
}

function expect_true($label, $condition, $detail = '')
{
    $condition ? pass_test($label) : fail_test($label, $detail);
}

function scalar_value(mysqli $db, $sql)
{
    $result = $db->query($sql);
    $row = $result->fetch_row();
    $result->free();
    return $row ? $row[0] : NULL;
}

function expect_sql_rejected(mysqli $db, $label, $sql)
{
    try {
        $db->query($sql);
        fail_test($label, 'database accepted a forbidden relation');
    } catch (mysqli_sql_exception $error) {
        pass_test($label.' (SQL '.$error->getCode().')');
    }
}

function row_ids(array $rows, $field = 'id')
{
    $ids = array_map(function ($row) use ($field) { return (int) $row->$field; }, $rows);
    sort($ids);
    return $ids;
}

function import_snapshot(mysqli $server, $snapshot_path, $database)
{
    if ( ! preg_match('/\Astay_management_test_[a-z0-9_]+\z/', $database)) {
        throw new RuntimeException('Unsafe disposable database name refused.');
    }
    $sql = file_get_contents($snapshot_path);
    if ($sql === FALSE || $sql === '') {
        throw new RuntimeException('Database snapshot could not be read.');
    }
    $sql = str_replace('`stay_management`', '`'.$database.'`', $sql, $replacements);
    if ($replacements < 3) {
        throw new RuntimeException('Snapshot rewrite did not match expected database markers.');
    }
    if (preg_match('/\b(?:DROP|CREATE)\s+DATABASE[^;]*`stay_management`|\bUSE\s+`stay_management`/i', $sql)) {
        throw new RuntimeException('Live database reference remained after snapshot rewrite.');
    }
    $server->multi_query($sql);
    do {
        if ($result = $server->store_result()) { $result->free(); }
    } while ($server->more_results() && $server->next_result());
}

function seed_fixtures(mysqli $db)
{
    $statements = array(
        "INSERT INTO plants (plant_id,plant_name,plant_status,plant_ad_by) VALUES
            (101,'Admin A Account',1,1),(201,'Admin B Account',1,1)",
        "INSERT INTO users (user_id,name,mobile_no,role,fk_plant,status,added_by) VALUES
            (101,'Admin A','9000000101','admin',101,1,1),
            (201,'Admin B','9000000201','admin',201,1,1),
            (111,'User A Single','9000000111','user',101,1,101),
            (112,'User A Multi','9000000112','user',101,1,101),
            (113,'User A No Property','9000000113','user',101,1,101),
            (211,'User B Single','9000000211','user',201,1,201)",
        "INSERT INTO properties (property_id,fk_plant,property_code,property_name,status,added_by) VALUES
            (101,101,'A_P1','Admin A Property One',1,101),
            (102,101,'A_P2','Admin A Property Two',1,101),
            (201,201,'B_P3','Admin B Property Three',1,201)",
        "INSERT INTO user_property_access (fk_plant,user_id,property_id,assigned_by) VALUES
            (101,111,101,101),(101,112,101,101),(101,112,102,101),(201,211,201,201)",
        "INSERT INTO room_categories
            (category_id,property_id,category_name,short_code,max_adults,max_children,status)
            VALUES
            (10101,101,'A P1 Standard','STD',2,1,1),
            (10201,102,'A P2 Standard','STD',2,1,1),
            (20101,201,'B P3 Standard','STD',2,1,1)",
        "INSERT INTO rooms
            (id,property_id,room_code,room_no,category_id,floor_no,selling_price,status)
            VALUES
            (10101,101,'ROOM500','500',10101,'5',2500,1),
            (10201,102,'ROOM500','500',10201,'5',2600,1),
            (20101,201,'ROOM500','500',20101,'5',2700,1)",
        "INSERT INTO customers
            (id,fk_plant,customer_code,customer_name,phone,status)
            VALUES
            (10101,101,'A_CUST1','Admin A Shared Guest','7000000101',1),
            (10102,101,'A_CUST2','Admin A Other Guest','7000000102',1),
            (20101,201,'B_CUST1','Admin B Guest','7000000201',1)",
        "INSERT INTO booking_details
            (id,fk_plant,property_id,booking_number,customer_id,sd_id,property_name,
             scheduled_check_in_date,scheduled_check_out_date,room_category_id,room_id,total_amount)
            VALUES
            (10101,101,101,'A_BOOK_P1',10101,1,'Admin A Property One','2026-09-01','2026-09-02',10101,10101,2500),
            (10201,101,102,'A_BOOK_P2',10101,1,'Admin A Property Two','2026-09-03','2026-09-04',10201,10201,2600),
            (20101,201,201,'B_BOOK_P3',20101,1,'Admin B Property Three','2026-09-05','2026-09-06',20101,20101,2700)",
        "INSERT INTO customer_identities
            (id,fk_plant,property_id,customer_id,booking_id,identity_type,identity_number)
            VALUES (10101,101,101,10101,10101,'aadhar','TEST-A-P1')",
    );
    foreach ($statements as $statement) { $db->query($statement); }
}

if ( ! function_exists('get_instance')) {
    function &get_instance()
    {
        return $GLOBALS['CI_TEST_INSTANCE'];
    }
}

/** Load only CodeIgniter Query Builder and the real persistence models. */
function bootstrap_models($root, $database)
{
    if ( ! defined('BASEPATH')) { define('BASEPATH', $root.DIRECTORY_SEPARATOR.'system'.DIRECTORY_SEPARATOR); }
    if ( ! defined('APPPATH')) { define('APPPATH', $root.DIRECTORY_SEPARATOR.'application'.DIRECTORY_SEPARATOR); }
    if ( ! defined('ENVIRONMENT')) { define('ENVIRONMENT', 'testing'); }
    if ( ! defined('EXT')) { define('EXT', '.php'); }
    require_once BASEPATH.'core/Common.php';
    require_once BASEPATH.'core/Model.php';
    require_once BASEPATH.'database/DB.php';

    $params = array(
        'dsn' => '', 'hostname' => '127.0.0.1', 'username' => 'root',
        'password' => '', 'database' => $database, 'dbdriver' => 'mysqli',
        'dbprefix' => '', 'pconnect' => FALSE, 'db_debug' => FALSE,
        'cache_on' => FALSE, 'cachedir' => '', 'char_set' => 'utf8mb4',
        'dbcollat' => 'utf8mb4_general_ci', 'swap_pre' => '', 'encrypt' => FALSE,
        'compress' => FALSE, 'stricton' => TRUE, 'failover' => array(),
        'save_queries' => TRUE,
    );
    $connection =& DB($params, TRUE);
    $GLOBALS['CI_TEST_INSTANCE'] = (object) array('db' => $connection);
    require_once APPPATH.'models/User_model.php';
    require_once APPPATH.'models/Tenant_model.php';
    require_once APPPATH.'models/Property_model.php';
    require_once APPPATH.'models/Room_model.php';
    require_once APPPATH.'models/Customer_model.php';
    return array(
        'db' => $connection,
        'users' => new User_model(),
        'properties' => new Property_model(),
        'rooms' => new Room_model(),
        'customers' => new Customer_model(),
    );
}

try {
    if ($database === 'stay_management' || ! preg_match('/\Astay_management_test_[a-z0-9_]+\z/', $database)) {
        throw new RuntimeException('Refusing unsafe database name.');
    }
    if ( ! is_file($snapshot_path)) {
        throw new RuntimeException('Missing database snapshot: '.$snapshot_path);
    }
    $server_db = new mysqli('127.0.0.1', 'root', '');
    $server_db->set_charset('utf8mb4');
    import_snapshot($server_db, $snapshot_path, $database);
    $database_created = TRUE;
    $fixture_db = new mysqli('127.0.0.1', 'root', '', $database);
    $fixture_db->set_charset('utf8mb4');
    seed_fixtures($fixture_db);

    echo "\nDatabase constraint coverage ({$database})\n";
    expect_true('fixture has two admin tenants and three active properties',
        (int) scalar_value($fixture_db, "SELECT COUNT(*) FROM plants WHERE plant_id IN (101,201)") === 2
        && (int) scalar_value($fixture_db, "SELECT COUNT(*) FROM properties WHERE property_id IN (101,102,201) AND status=1") === 3);
    expect_true('same room number/code is allowed in separate properties',
        (int) scalar_value($fixture_db, "SELECT COUNT(*) FROM rooms WHERE room_no='500' AND id IN (10101,10201,20101)") === 3);
    expect_sql_rejected($fixture_db, 'duplicate room number is rejected inside one property',
        "INSERT INTO rooms (property_id,room_code,room_no,category_id) VALUES (101,'ROOM501','500',10101)");
    expect_sql_rejected($fixture_db, 'room cannot reference another property category',
        "INSERT INTO rooms (property_id,room_code,room_no,category_id) VALUES (101,'BADCAT','998',10201)");
    $fixture_db->query(
        "INSERT INTO rooms (id,property_id,room_code,room_no,category_id,status) VALUES (10103,101,'ROOM_NOCAT','501',NULL,1)"
    );
    expect_true('Room Master accepts a room without creating a category first',
        scalar_value($fixture_db, "SELECT category_id FROM rooms WHERE id=10103") === NULL);
    $fixture_db->query(
        "INSERT INTO booking_details (id,fk_plant,property_id,booking_number,customer_id,sd_id,room_category_id,room_id) VALUES (11003,101,101,'NO_CATEGORY_ROOM',10102,1,NULL,10103)"
    );
    expect_true('booking accepts an allotted uncategorized room without a forged category',
        (int) scalar_value($fixture_db, "SELECT COUNT(*) FROM booking_details WHERE id=11003 AND room_id=10103 AND room_category_id IS NULL") === 1);
    expect_sql_rejected($fixture_db, 'user assignment cannot point at another tenant property',
        "INSERT INTO user_property_access (fk_plant,user_id,property_id,assigned_by) VALUES (101,111,201,101)");
    expect_sql_rejected($fixture_db, 'assignment tenant cannot be forged to match foreign property',
        "INSERT INTO user_property_access (fk_plant,user_id,property_id,assigned_by) VALUES (201,111,201,101)");
    expect_sql_rejected($fixture_db, 'super-admin role rejects a tenant id',
        "INSERT INTO users (name,mobile_no,role,fk_plant,status) VALUES ('Bad Super','9000099901','super_admin',101,1)");
    expect_sql_rejected($fixture_db, 'normal user role rejects a null tenant',
        "INSERT INTO users (name,mobile_no,role,fk_plant,status) VALUES ('Bad User','9000099902','user',NULL,1)");

    $fixture_db->query("INSERT INTO customers (id,fk_plant,customer_code,customer_name,phone) VALUES (11001,101,'PHONE_A','Phone A','7999999999')");
    $fixture_db->query("INSERT INTO customers (id,fk_plant,customer_code,customer_name,phone) VALUES (21001,201,'PHONE_B','Phone B','7999999999')");
    pass_test('same customer phone is allowed in separate tenants');
    expect_sql_rejected($fixture_db, 'duplicate customer phone is rejected inside one tenant',
        "INSERT INTO customers (id,fk_plant,customer_code,customer_name,phone) VALUES (11002,101,'PHONE_C','Phone C','7999999999')");
    $fixture_db->query("INSERT INTO booking_details (id,fk_plant,property_id,booking_number,customer_id,sd_id,room_category_id,room_id) VALUES (11001,101,101,'SHARED_CODE',10101,1,10101,10101)");
    $fixture_db->query("INSERT INTO booking_details (id,fk_plant,property_id,booking_number,customer_id,sd_id,room_category_id,room_id) VALUES (11002,101,102,'SHARED_CODE',10101,1,10201,10201)");
    pass_test('same booking number is allowed in separate properties');
    expect_sql_rejected($fixture_db, 'duplicate booking number is rejected inside one property',
        "INSERT INTO booking_details (fk_plant,property_id,booking_number,customer_id,sd_id) VALUES (101,101,'SHARED_CODE',10101,1)");
    expect_sql_rejected($fixture_db, 'booking cannot combine tenant A with tenant B property',
        "INSERT INTO booking_details (fk_plant,property_id,booking_number,customer_id,sd_id) VALUES (101,201,'BAD_PROPERTY',10101,1)");
    expect_sql_rejected($fixture_db, 'booking cannot combine tenant A property with tenant B customer',
        "INSERT INTO booking_details (fk_plant,property_id,booking_number,customer_id,sd_id) VALUES (101,101,'BAD_CUSTOMER',20101,1)");
    expect_sql_rejected($fixture_db, 'booking cannot use a room from another property',
        "INSERT INTO booking_details (fk_plant,property_id,booking_number,customer_id,sd_id,room_id) VALUES (101,101,'BAD_ROOM',10101,1,10201)");
    expect_sql_rejected($fixture_db, 'booking cannot use a category from another property',
        "INSERT INTO booking_details (fk_plant,property_id,booking_number,customer_id,sd_id,room_category_id) VALUES (101,101,'BAD_CATEGORY',10101,1,10201)");
    expect_sql_rejected($fixture_db, 'identity cannot attach a booking from another property',
        "INSERT INTO customer_identities (fk_plant,property_id,customer_id,booking_id,identity_type) VALUES (101,102,10101,10101,'passport')");
    expect_sql_rejected($fixture_db, 'identity cannot attach booking to another same-tenant customer',
        "INSERT INTO customer_identities (fk_plant,property_id,customer_id,booking_id,identity_type) VALUES (101,101,10102,10101,'passport')");
    expect_sql_rejected($fixture_db, 'property with operational history is protected by RESTRICT',
        "DELETE FROM properties WHERE property_id=101");
    expect_true('tenant-shared customer is reused by bookings in two properties',
        (int) scalar_value($fixture_db, "SELECT COUNT(DISTINCT property_id) FROM booking_details WHERE fk_plant=101 AND customer_id=10101 AND property_id IN (101,102)") === 2);

    echo "\nApplication model authorization coverage\n";
    $models = bootstrap_models($root, $database);
    $ci_db = $models['db'];
    $users = $models['users'];
    $properties = $models['properties'];
    $rooms = $models['rooms'];
    $customers = $models['customers'];

    $super = $users->get_active_by_mobile('9876543210');
    $admin_a = $users->get_active_by_mobile('9000000101');
    $admin_b = $users->get_active_by_mobile('9000000201');
    $user_single = $users->get_active_by_mobile('9000000111');
    $user_multi = $users->get_active_by_mobile('9000000112');
    $user_zero = $users->get_active_by_mobile('9000000113');
    $user_b = $users->get_active_by_mobile('9000000211');
    expect_true('identity reload preserves fixed role and tenant',
        $super && $super->role === 'super_admin' && $super->tenant_id === NULL
        && $admin_a && $admin_a->role === 'admin' && (int) $admin_a->tenant_id === 101
        && $user_single && $user_single->role === 'user' && (int) $user_single->tenant_id === 101);
    $active_property_result = $fixture_db->query('SELECT property_id AS id FROM properties WHERE status=1 ORDER BY property_id');
    $expected_active_property_ids = array();
    while ($active_property_row = $active_property_result->fetch_object()) {
        $expected_active_property_ids[] = (int) $active_property_row->id;
    }
    $active_property_result->free();
    expect_true('Super Admin property set spans all active tenants',
        row_ids($properties->list_authorized_for_user($super, TRUE)) === $expected_active_property_ids
        && count(array_intersect(array(101,102,201), $expected_active_property_ids)) === 3);
    expect_true('Admin A property set is own tenant only',
        row_ids($properties->list_authorized_for_user($admin_a, TRUE)) === array(101,102));
    expect_true('Admin B property set excludes Admin A and legacy',
        row_ids($properties->list_authorized_for_user($admin_b, TRUE)) === array(201));
    expect_true('single-property user receives exactly one assignment',
        row_ids($properties->list_authorized_for_user($user_single, TRUE)) === array(101));
    expect_true('multi-property user receives both own-tenant assignments',
        row_ids($properties->list_authorized_for_user($user_multi, TRUE)) === array(101,102));
    expect_true('zero-assignment active user receives empty property set',
        row_ids($properties->list_authorized_for_user($user_zero, TRUE)) === array());
    expect_true('Admin B user receives only P3',
        row_ids($properties->list_authorized_for_user($user_b, TRUE)) === array(201));
    expect_true('Admin A direct cross-admin property id resolves null',
        $properties->get_authorized_for_user(201, $admin_a, TRUE) === NULL);
    expect_true('single user unassigned same-tenant property resolves null',
        $properties->get_authorized_for_user(102, $user_single, TRUE) === NULL);
    expect_true('Super Admin resolves Admin B property id',
        $properties->get_authorized_for_user(201, $super, TRUE) !== NULL);
    expect_true('Admin management list is tenant isolated',
        row_ids($properties->list_for_management($admin_a)) === array(101,102));
    expect_true('cross-admin management lookup fails closed',
        $properties->get_for_management(201, $admin_a) === NULL);
    expect_true('assignment validator rejects mixed-tenant ids',
        $properties->active_ids_belong_to_tenant(array(101,201), 101) === FALSE);
    expect_true('assignment validator accepts both own active ids',
        $properties->active_ids_belong_to_tenant(array(101,102), 101) === TRUE);
    expect_true('Admin A hotel-user list excludes Admin B staff',
        row_ids($users->list_users(101)) === array(111,112,113));
    expect_true('stored assignments are tenant-scoped',
        $users->assigned_property_ids(112, 101) === array(101,102)
        && $users->assigned_property_ids(112, 201) === array());

    $fixture_db->query('UPDATE properties SET status=0 WHERE property_id=102');
    expect_true('property deactivation removes P2 next model request',
        row_ids($properties->list_authorized_for_user($user_multi, TRUE)) === array(101)
        && $properties->get_authorized_for_user(102, $admin_a, TRUE) === NULL);
    $fixture_db->query('UPDATE properties SET status=1 WHERE property_id=102');
    $fixture_db->query('DELETE FROM user_property_access WHERE user_id=112 AND property_id=102');
    expect_true('assignment revocation immediately removes P2 but preserves P1',
        row_ids($properties->list_authorized_for_user($user_multi, TRUE)) === array(101));
    $fixture_db->query("INSERT INTO user_property_access (fk_plant,user_id,property_id,assigned_by) VALUES (101,112,102,101)");

    $users->set_active(101, 0);
    expect_true('admin deactivation blocks owner identity reload',
        $users->get_active_by_mobile('9000000101') === NULL);
    expect_true('admin deactivation blocks its staff identity reloads',
        $users->get_active_by_mobile('9000000111') === NULL
        && $users->get_active_by_mobile('9000000112') === NULL);
    expect_true('another tenant stays eligible after Admin A deactivation',
        $users->get_active_by_mobile('9000000201') !== NULL
        && $users->get_active_by_mobile('9000000211') !== NULL);
    $users->set_active(101, 1);
    $fixture_db->query('UPDATE plants SET plant_status=0 WHERE plant_id=101');
    expect_true('tenant deactivation blocks owner and staff contexts',
        $users->get_active_by_mobile('9000000101') === NULL
        && $users->get_active_by_mobile('9000000111') === NULL);
    $fixture_db->query('UPDATE plants SET plant_status=1 WHERE plant_id=101');

    expect_true('Room model direct id requires explicit matching property',
        $rooms->get_by_id(101, 10101) !== NULL
        && $rooms->get_by_id(102, 10101) === NULL
        && $rooms->get_by_id(201, 10101) === NULL);
    expect_true('Room category predicate rejects foreign-property category',
        $rooms->category_belongs_to_property(101, 10101, TRUE) === TRUE
        && $rooms->category_belongs_to_property(101, 10201, TRUE) === FALSE);
    expect_true('uncategorized room remains NULL through booking model resolution',
        $customers->room_category_for_room(101, 10103) === NULL
        && $customers->get_booking_detail(101, 101, 11003) !== NULL
        && $customers->get_booking_detail(101, 101, 11003)->room_category === NULL);
    $p1_rooms = $rooms->get_filtered(101);
    expect_true('Room list cannot return another property row',
        ! empty($p1_rooms) && count(array_filter($p1_rooms, function ($room) {
            return (int) $room->property_id !== 101;
        })) === 0);
    expect_true('Customer profile is shared inside tenant only',
        $customers->get_by_id(101, 10101) !== NULL
        && $customers->get_by_id(201, 10101) === NULL
        && $customers->get_by_phone(101, '7000000101') !== NULL
        && $customers->get_by_phone(201, '7000000101') === NULL);
    expect_true('booking id is property-private despite shared customer',
        $customers->get_booking_detail(101, 101, 10101) !== NULL
        && $customers->get_booking_detail(101, 102, 10101) === NULL
        && $customers->get_booking_detail(201, 201, 10101) === NULL);
    expect_true('P2 booking cannot be read from P1 context',
        $customers->get_booking_detail(101, 102, 10201) !== NULL
        && $customers->get_booking_detail(101, 101, 10201) === NULL);
    expect_true('identity direct id is tenant/property scoped',
        $customers->get_identity(101, 101, 10101) !== NULL
        && $customers->get_identity(101, 102, 10101) === NULL
        && $customers->get_identity(201, 201, 10101) === NULL);
    $p1_bookings = $customers->get_bookings(101, 101);
    expect_true('booking list cannot return another property/tenant row',
        row_ids($p1_bookings) === array(10101,11001,11003));
} catch (Throwable $error) {
    fail_test('integration test aborted', get_class($error).': '.$error->getMessage());
} finally {
    if ($ci_db && method_exists($ci_db, 'close')) { $ci_db->close(); }
    if ($fixture_db instanceof mysqli) { $fixture_db->close(); }
    if ($server_db instanceof mysqli) { $server_db->close(); }
    if ($database_created && preg_match('/\Astay_management_test_[a-z0-9_]+\z/', $database)) {
        try {
            $cleanup = new mysqli('127.0.0.1', 'root', '');
            $cleanup->query('DROP DATABASE IF EXISTS `'.$database.'`');
            $cleanup->close();
            echo "\nDisposable database dropped: {$database}\n";
        } catch (Throwable $cleanup_error) {
            fail_test('disposable database cleanup', $cleanup_error->getMessage());
        }
    }
}

echo "\n{$passes} multitenancy integration checks passed.\n";
if ($failures) {
    fwrite(STDERR, count($failures)." check(s) failed:\n - ".implode("\n - ", $failures)."\n");
    exit(1);
}
echo "All multitenancy isolation integration checks passed.\n";
