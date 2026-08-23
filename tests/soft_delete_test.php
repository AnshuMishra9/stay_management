<?php
/**
 * Disposable-DB regression for the SOFT-DELETE policy.
 *
 * Rule under test: a delete request NEVER removes rows. It only flips
 * status to 0 (inactive), the record disappears from default reads,
 * and unique business keys (phone/room_no/code/mobile) become reusable.
 *
 * Run: C:\xampp\php\php.exe tests\soft_delete_test.php
 */

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$root = dirname(__DIR__);
$snapshot_path = $root.DIRECTORY_SEPARATOR.'db'.DIRECTORY_SEPARATOR.'stay_management.sql';
$database = 'stay_management_softdel_'.getmypid().'_'.bin2hex(random_bytes(4));
$database_created = FALSE;
$fixture_db = NULL;
$ci_db = NULL;
$failures = array();
$passes = 0;

function pass_test($label) { global $passes; $passes++; echo "PASS: {$label}\n"; }
function fail_test($label, $detail = '')
{
    global $failures;
    $m = $label.($detail !== '' ? ' — '.$detail : '');
    $failures[] = $m;
    echo "FAIL: {$m}\n";
}
function expect_true($label, $condition, $detail = '') { $condition ? pass_test($label) : fail_test($label, $detail); }
function scalar_value(mysqli $db, $sql)
{
    $r = $db->query($sql); $row = $r->fetch_row(); $r->free(); return $row ? $row[0] : NULL;
}

if ( ! function_exists('get_instance')) {
    function &get_instance() { return $GLOBALS['CI_TEST_INSTANCE']; }
}

function import_snapshot(mysqli $server, $snapshot_path, $database)
{
    if ( ! preg_match('/\Astay_management_[a-z0-9_]+\z/', $database)) {
        throw new RuntimeException('Unsafe disposable database name refused.');
    }
    $sql = file_get_contents($snapshot_path);
    $sql = str_replace('`stay_management`', '`'.$database.'`', $sql, $replacements);
    if ($replacements < 3) { throw new RuntimeException('Snapshot markers missing.'); }
    $server->multi_query($sql);
    do { if ($result = $server->store_result()) { $result->free(); } } while ($server->more_results() && $server->next_result());
}

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
        'db'         => $connection,
        'users'      => new User_model(),
        'plants'     => new Tenant_model(),
        'properties' => new Property_model(),
        'rooms'      => new Room_model(),
        'customers'  => new Customer_model(),
    );
}

try {
    if ( ! preg_match('/\Astay_management_[a-z0-9_]+\z/', $database)) { throw new RuntimeException('Unsafe name.'); }
    $server_db = new mysqli('127.0.0.1', 'root', '');
    $server_db->set_charset('utf8mb4');
    import_snapshot($server_db, $snapshot_path, $database);
    $database_created = TRUE;
    $fixture_db = new mysqli('127.0.0.1', 'root', '', $database);
    $fixture_db->set_charset('utf8mb4');

    // ---- Seed: plant 901 / admin 901 / property 901 / category+room / customer+identity
    $fixture_db->query("INSERT INTO plants (plant_id,plant_name,plant_status,plant_ad_by,plant_ad_dt) VALUES (901,'Soft Plant',1,NULL,NOW())");
    $fixture_db->query("INSERT INTO users (user_id,name,mobile_no,role,fk_plant,status,added_by,created_at) VALUES (901,'Soft Admin','9111100001','admin',901,1,NULL,NOW()),(902,'Soft User','9111100002','user',901,1,901,NOW())");
    $fixture_db->query("INSERT INTO properties (property_id,fk_plant,property_code,property_name,status,added_by,created_at) VALUES (901,901,'SOFT01','Soft Property',1,NULL,NOW())");
    $fixture_db->query("INSERT INTO room_categories (category_id,property_id,category_name,max_adults,max_children,status) VALUES (90101,901,'Soft Suite',2,1,1)");
    $fixture_db->query("INSERT INTO rooms (id,property_id,room_code,room_no,category_id,status) VALUES (90101,901,'ROOM9001','901',90101,1)");
    $fixture_db->query("INSERT INTO user_property_access (fk_plant,user_id,property_id,assigned_by,created_at) VALUES (901,902,901,901,NOW())");
    $fixture_db->query("INSERT INTO customers (id,fk_plant,customer_code,customer_name,phone,status,created_at) VALUES (90101,901,'CUST90001','Soft Guest','9222200001',1,NOW())");
    $fixture_db->query("INSERT INTO customer_identities (id,fk_plant,property_id,customer_id,identity_type,identity_number,status,created_at) VALUES (90101,901,901,90101,'aadhar','SOFT-AADHAR-1',1,NOW())");

    echo "\nSoft-delete: customer\n";
    $models = bootstrap_models($root, $database);
    $customers = $models['customers'];
    expect_true('seeded customer is visible in filtered list',
        count($customers->get_filtered(901)) === 1);

    expect_true('customer delete() succeeds (soft)',
        $customers->delete(901, 90101) === TRUE);
    expect_true('customer ROW still exists in DB with status=0',
        (int) scalar_value($fixture_db, "SELECT status FROM customers WHERE id=90101") === 0);
    expect_true('deleted customer hidden from default list',
        count($customers->get_filtered(901)) === 0);
    expect_true('deleted customer visible under Inactive filter',
        count($customers->get_filtered(901, array('status' => '0'))) === 1);
    expect_true('deleted customer visible under All filter',
        count($customers->get_filtered(901, array('status' => 'all'))) === 1);
    expect_true('same phone is REUSABLE for a new active customer',
        $fixture_db->query("INSERT INTO customers (id,fk_plant,customer_code,customer_name,phone,status,created_at) VALUES (90102,901,'CUST90002','Reused Phone Guest','9222200001',1,NOW())") === TRUE);
    expect_true('history still counts after identity removal later (has_history uses bookings/identities incl inactive)',
        $customers->has_history(901, 90101) === TRUE);

    echo "\nSoft-delete: customer identity proofs\n";
    expect_true('active identity listed',
        count($customers->get_identities(901, 901, 90102)) === 0
        && count($customers->get_identities(901, 901, 90101)) === 1);
    expect_true('identity soft-delete succeeds',
        $customers->delete_identity(901, 901, 90101, 90101) === TRUE);
    expect_true('identity ROW still exists with status=0',
        (int) scalar_value($fixture_db, "SELECT status FROM customer_identities WHERE id=90101") === 0);
    expect_true('removed identity no longer listed',
        count($customers->get_identities(901, 901, 90101)) === 0);
    expect_true('removed identity not returned by identities_to_remove',
        count($customers->identities_to_remove(901, 901, 90101, array())) === 0);

    echo "\nSoft-delete: room\n";
    $rooms = $models['rooms'];
    expect_true('unused-room delete deactivates (never hard-deletes)',
        $rooms->delete_or_deactivate(901, 90101) === 'deactivated');
    expect_true('room ROW still exists with status=0',
        (int) scalar_value($fixture_db, "SELECT status FROM rooms WHERE id=90101") === 0);
    expect_true('same room_no is REUSABLE for a new active room',
        $fixture_db->query("INSERT INTO rooms (id,property_id,room_code,room_no,category_id,status) VALUES (90102,901,'ROOM9001','901',NULL,1)") === TRUE);
    expect_true('deactivated room hidden from default list',
        count(array_filter($rooms->get_filtered(901), function ($r) { return (int) $r->id === 90101; })) === 0);

    echo "\nSoft-delete: property / plant / users\n";
    $properties = $models['properties'];
    $users      = $models['users'];
    $plants     = $models['plants'];

    $admin_a = $users->get_active_by_mobile('9111100001');
    expect_true('seeded admin resolves before deletion', $admin_a !== NULL && (int) $admin_a->tenant_id === 901);

    expect_true('user deactivate keeps row',
        $users->set_active(902, 0) === TRUE
        && (int) scalar_value($fixture_db, "SELECT status FROM users WHERE user_id=902") === 0);
    expect_true('deactivated user mobile becomes reusable (mobile_exists=false)',
        $users->mobile_exists('9111100002') === FALSE);
    expect_true('deactivated user cannot log in', $users->get_active_by_mobile('9111100002') === NULL);

    expect_true('property soft-delete keeps row',
        $properties->set_active(901, 0) === TRUE
        && (int) scalar_value($fixture_db, "SELECT status FROM properties WHERE property_id=901") === 0);
    expect_true('same property code is REUSABLE in plant',
        $properties->code_exists(901, 'SOFT01') === FALSE);
    expect_true('deactivated property excluded from management list',
        row_ids_of($properties->list_for_management($admin_a)) === array());

    expect_true('plant soft-delete keeps both rows',
        $plants->delete_empty_admin_tenant(901, 901) === TRUE
        && (int) scalar_value($fixture_db, "SELECT plant_status FROM plants WHERE plant_id=901") === 0
        && (int) scalar_value($fixture_db, "SELECT COUNT(*) FROM plants WHERE plant_id=901") === 1
        && (int) scalar_value($fixture_db, "SELECT COUNT(*) FROM users WHERE user_id=901") === 1);
    expect_true('soft-deleted plant blocks admin login',
        $users->get_active_by_mobile('9111100001') === NULL);

    echo "\nHard-delete guards\n";
    foreach (array(
        array('customers', 90101, 'id'),
        array('customer_identities', 90101, 'id'),
        array('rooms', 90101, 'id'),
        array('properties', 901, 'property_id'),
        array('plants', 901, 'plant_id'),
        array('users', 901, 'user_id'),
        array('users', 902, 'user_id'),
    ) as $check) {
        expect_true("{$check[0]}#{$check[1]} was NOT hard-deleted",
            (int) scalar_value($fixture_db, "SELECT COUNT(*) FROM {$check[0]} WHERE {$check[2]}=".$check[1]) === 1);
    }

} catch (Throwable $error) {
    fail_test('soft-delete test aborted', get_class($error).': '.$error->getMessage());
} finally {
    if ($ci_db && method_exists($ci_db, 'close')) { $ci_db->close(); }
    if ($fixture_db instanceof mysqli) { $fixture_db->close(); }
    if (isset($server_db) && $server_db instanceof mysqli) { $server_db->close(); }
    if ($database_created) {
        try {
            $cleanup = new mysqli('127.0.0.1', 'root', '');
            $cleanup->query('DROP DATABASE IF EXISTS `'.$database.'`');
            $cleanup->close();
            echo "\nDisposable database dropped: {$database}\n";
        } catch (Throwable $e) { /* ignore */ }
    }
}

echo "\n{$passes} soft-delete checks passed.\n";
if ($failures) {
    fwrite(STDERR, count($failures)." check(s) failed:\n - ".implode("\n - ", $failures)."\n");
    exit(1);
}
echo "All soft-delete checks passed.\n";

function row_ids_of(array $rows)
{
    $ids = array_map(function ($r) { return (int) $r->id; }, $rows);
    sort($ids);
    return $ids;
}
