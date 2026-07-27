<?php
/**
 * Run with: php tests/helpers/ConversionCreditTc2SqlTest.php
 *
 * Locks lead_conversion_credit_sql_fragment_tc2() — the TC2-only credit used by
 * the dashboard's YTD "Conversion Rate" card. Unlike the standard fragment
 * (which switches TC1 pre-2026-06-01 / TC2 after), this one credits a
 * conversion ONLY when the lead's mapped admin holds booking.SalesAgent2 (TC2),
 * regardless of the booking date. Same per-agent identity match via
 * admin_lead_dashboard_agents and admin.Status='Y'.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}
require_once __DIR__ . '/../../application/helpers/lead_conversion_credit_helper.php';

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE booking (BookingID INTEGER PRIMARY KEY, SalesAgent INTEGER, SalesAgent2 INTEGER, InsertDate TEXT)");
$pdo->exec("CREATE TABLE ghl_processed_leads (id INTEGER PRIMARY KEY, assigned_to_user_id TEXT, is_converted INTEGER, booking_id INTEGER)");
$pdo->exec("CREATE TABLE admin (AdminID INTEGER PRIMARY KEY, Email TEXT, Status TEXT)");
$pdo->exec("CREATE TABLE admin_lead_dashboard_agents (AdminID INTEGER, GhlUserID TEXT)");

$pdo->exec("INSERT INTO admin VALUES (10,'alice@x','Y'),(20,'bob@x','Y'),(99,'hccs@x','Y'),(77,'gone@x','N')");
$pdo->exec("INSERT INTO admin_lead_dashboard_agents (AdminID, GhlUserID) VALUES
    (10,'ghl-alice'),(20,'ghl-bob'),(99,'ghl-hccs'),(77,'ghl-gone')");

// Same bookings as LeadConversionCreditSqlTest. Only SalesAgent2 matters here.
$pdo->exec("INSERT INTO booking VALUES
    (1001, 10,   99,   '2026-05-30 10:00:00'),  /* SA2=hccs */
    (1002, 20,   10,   '2026-05-30 10:00:00'),  /* SA2=alice */
    (1003, 10,   NULL, '2026-05-31 23:59:59'),  /* SA2 none */
    (1004, NULL, 10,   '2026-05-30 10:00:00'),  /* SA2=alice (pre-cutoff!) */
    (1005, 10,   99,   '2026-06-01 00:00:00'),  /* SA2=hccs */
    (1006, 20,   10,   '2026-06-15 12:30:00'),  /* SA2=alice */
    (1007, NULL, 10,   '2026-06-15 12:30:00'),  /* SA2=alice */
    (1008, 10,   NULL, '2026-06-15 12:30:00'),  /* SA2 none */
    (1009, 10,   0,    '2026-06-15 12:30:00'),  /* SA2=0 sentinel */
    (1010, 0,    99,   '2026-05-30 10:00:00')   /* SA2=hccs */
");

$pdo->exec("INSERT INTO ghl_processed_leads VALUES
    (1,  'ghl-alice', 1, 1001),  /* SA2=hccs            -> NO   */
    (2,  'ghl-alice', 1, 1002),  /* SA2=alice           -> YES  */
    (3,  'ghl-alice', 1, 1003),  /* SA2 none            -> NO   */
    (4,  'ghl-alice', 1, 1004),  /* SA2=alice pre-cutoff-> YES  */
    (5,  'ghl-alice', 1, 1005),  /* SA2=hccs            -> NO   */
    (6,  'ghl-alice', 1, 1006),  /* SA2=alice           -> YES  */
    (7,  'ghl-alice', 1, 1007),  /* SA2=alice           -> YES  */
    (8,  'ghl-alice', 1, 1008),  /* SA2 none            -> NO   */
    (9,  'ghl-alice', 1, 1009),  /* SA2=0 sentinel      -> NO   */
    (10, 'ghl-alice', 1, 1010),  /* SA2=hccs            -> NO   */
    (11, 'ghl-hccs',  1, 1001),  /* SA2=hccs            -> YES  */
    (12, 'ghl-hccs',  1, 1005),  /* SA2=hccs            -> YES  */
    (13, 'ghl-hccs',  1, 1004),  /* SA2=alice           -> NO   */
    (14, 'ghl-bob',   1, 1002),  /* SA2=alice (bob TC1) -> NO   */
    (15, 'ghl-bob',   1, 1006),  /* SA2=alice           -> NO   */
    (16, 'ghl-queue', 1, 1001),  /* no mapping          -> NO   */
    (17, 'ghl-gone',  1, 1001),  /* admin Status='N'    -> NO   */
    (18, 'ghl-alice', 0, NULL),  /* unconverted         -> ign  */
    (19, 'ghl-alice', 1, NULL),  /* no booking          -> ign  */
    (20, NULL,        1, 1001)   /* unassigned          -> NO   */
");

$fragment = lead_conversion_credit_sql_fragment_tc2();

$expected_per_lead = [
    1=>0, 2=>1, 3=>0, 4=>1, 5=>0, 6=>1, 7=>1, 8=>0, 9=>0, 10=>0,
    11=>1, 12=>1, 13=>0, 14=>0, 15=>0, 16=>0, 17=>0, 18=>0, 19=>0, 20=>0,
];

$assertions = [];
$detail_sql = "SELECT pl.id,
    CASE WHEN pl.is_converted = 1 AND pl.booking_id IS NOT NULL AND {$fragment} THEN 1 ELSE 0 END AS credited
FROM ghl_processed_leads pl ORDER BY pl.id";
foreach ($pdo->query($detail_sql) as $r) {
    $id = (int) $r['id'];
    $assertions["Lead #{$id} credit = {$expected_per_lead[$id]}"] = ((int) $r['credited']) === $expected_per_lead[$id];
}

// Per-agent: alice = 2,4,6,7 -> 4 ; hccs = 11,12 -> 2 ; bob = 0
$agent_sql = "SELECT COALESCE(NULLIF(pl.assigned_to_user_id,''),'__u__') AS agent_id,
    SUM(CASE WHEN pl.is_converted=1 AND pl.booking_id IS NOT NULL AND {$fragment} THEN 1 ELSE 0 END) AS c
FROM ghl_processed_leads pl GROUP BY agent_id";
$by_agent = [];
foreach ($pdo->query($agent_sql) as $r) { $by_agent[$r['agent_id']] = (int) $r['c']; }
$expected_per_agent = ['ghl-alice'=>4, 'ghl-hccs'=>2, 'ghl-bob'=>0, 'ghl-queue'=>0, 'ghl-gone'=>0];
foreach ($expected_per_agent as $a => $e) {
    $assertions["Agent {$a} TC2 converted = {$e}"] = (isset($by_agent[$a]) ? $by_agent[$a] : 0) === $e;
}

$failed = 0;
foreach ($assertions as $label => $ok) {
    echo ($ok ? '  PASS  ' : '  FAIL  ') . $label . PHP_EOL;
    if (!$ok) { $failed++; }
}
echo PHP_EOL . ($failed === 0 ? "All assertions passed.\n" : "$failed assertion(s) failed.\n");
exit($failed === 0 ? 0 : 1);
