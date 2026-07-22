<?php
/**
 * Run with: php tests/helpers/LeadConversionCreditSqlTest.php
 *
 * End-to-end stress of the SQL fragment from lead_conversion_credit_helper.php:
 * seeds a SQLite :memory: schema mirroring booking / ghl_processed_leads /
 * admin_lead_dashboard_agents / admin, plugs the fragment into the same
 * SUM(CASE WHEN ...) shape used by Report_Model::Lead_Dashboard_Summary, and
 * verifies the cutoff rule plus the per-agent identity match — pre-2026-06-01
 * conversions require the lead's assigned GHL agent (resolved via the
 * admin_lead_dashboard_agents mapping table maintained from the Admin form)
 * to be on booking.SalesAgent (TC1); on/after, on booking.SalesAgent2 (TC2).
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require_once __DIR__ . '/../../application/helpers/lead_conversion_credit_helper.php';

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE booking (
    BookingID INTEGER PRIMARY KEY,
    SalesAgent INTEGER,
    SalesAgent2 INTEGER,
    InsertDate TEXT
)");
$pdo->exec("CREATE TABLE ghl_processed_leads (
    id INTEGER PRIMARY KEY,
    assigned_to_user_id TEXT,
    is_converted INTEGER,
    booking_id INTEGER
)");
$pdo->exec("CREATE TABLE admin (
    AdminID INTEGER PRIMARY KEY,
    Email TEXT,
    Status TEXT
)");
$pdo->exec("CREATE TABLE admin_lead_dashboard_agents (
    AdminID INTEGER,
    GhlUserID TEXT
)");

// admin: three real users + one inactive (should never satisfy Status='Y')
$pdo->exec("INSERT INTO admin VALUES
    (10, 'alice@x', 'Y'),
    (20, 'bob@x',   'Y'),
    (99, 'hccs@x',  'Y'),
    (77, 'gone@x',  'N')
");

// admin_lead_dashboard_agents: explicit GHL UserID -> AdminID linkage maintained
// from the Admin / Update form (Admin_Model::_Sync_Lead_Dashboard_Agents).
// ghl-queue intentionally has no link to simulate a queue inbox that's not
// owned by any admin yet.
$pdo->exec("INSERT INTO admin_lead_dashboard_agents (AdminID, GhlUserID) VALUES
    (10, 'ghl-alice'),
    (20, 'ghl-bob'),
    (99, 'ghl-hccs'),
    (77, 'ghl-gone')
");

// Bookings on both sides of the 2026-06-01 cutoff.
// Convention: SA = TC1, SA2 = TC2. Pre-cutoff credits TC1, post credits TC2.
$pdo->exec("INSERT INTO booking VALUES
    (1001, 10,   99,   '2026-05-30 10:00:00'),  /* pre: alice TC1, hccs TC2 */
    (1002, 20,   10,   '2026-05-30 10:00:00'),  /* pre: bob TC1, alice TC2 */
    (1003, 10,   NULL, '2026-05-31 23:59:59'),  /* pre boundary: alice TC1 only */
    (1004, NULL, 10,   '2026-05-30 10:00:00'),  /* pre: alice TC2 only, no TC1 */
    (1005, 10,   99,   '2026-06-01 00:00:00'),  /* cutoff: alice TC1, hccs TC2 */
    (1006, 20,   10,   '2026-06-15 12:30:00'),  /* post: bob TC1, alice TC2 */
    (1007, NULL, 10,   '2026-06-15 12:30:00'),  /* post: alice TC2 only */
    (1008, 10,   NULL, '2026-06-15 12:30:00'),  /* post: alice TC1 only, no TC2 */
    (1009, 10,   0,    '2026-06-15 12:30:00'),  /* post: TC2=0 sentinel */
    (1010, 0,    99,   '2026-05-30 10:00:00')   /* pre: TC1=0 sentinel */
");

// Leads:
//   alice (ghl-alice): leads where alice is or isn't the credited TC across cutoff
//   hccs  (ghl-hccs):  TC2-only on bookings — must be 0 pre-cutoff
//   bob   (ghl-bob):   used to prove pre-cutoff TC1 credit also gates by agent
//   ghl-queue: lead assigned to an agent that has no admin_lead_dashboard_agents
//              row (a queue/cold-lead inbox not owned by any admin)
//   ghl-gone:  linked to admin 77 whose Status='N' (must be excluded)
$pdo->exec("INSERT INTO ghl_processed_leads VALUES
    /* alice */
    (1,  'ghl-alice', 1, 1001),  /* pre,  alice=TC1                 -> COUNT */
    (2,  'ghl-alice', 1, 1002),  /* pre,  alice=TC2                 -> NO   */
    (3,  'ghl-alice', 1, 1003),  /* pre,  alice=TC1 only            -> COUNT */
    (4,  'ghl-alice', 1, 1004),  /* pre,  alice=TC2 only            -> NO   */
    (5,  'ghl-alice', 1, 1005),  /* post, alice=TC1                 -> NO   */
    (6,  'ghl-alice', 1, 1006),  /* post, alice=TC2                 -> COUNT */
    (7,  'ghl-alice', 1, 1007),  /* post, alice=TC2 only            -> COUNT */
    (8,  'ghl-alice', 1, 1008),  /* post, alice=TC1 only            -> NO   */
    (9,  'ghl-alice', 1, 1009),  /* post, TC2=0 sentinel            -> NO   */
    (10, 'ghl-alice', 1, 1010),  /* pre,  TC1=0 sentinel            -> NO   */

    /* hccs (always TC2 on bookings) */
    (11, 'ghl-hccs',  1, 1001),  /* pre,  hccs=TC2                  -> NO   */
    (12, 'ghl-hccs',  1, 1005),  /* post, hccs=TC2                  -> COUNT */
    (13, 'ghl-hccs',  1, 1004),  /* pre,  alice=TC2 (hccs not on bk)-> NO   */

    /* bob */
    (14, 'ghl-bob',   1, 1002),  /* pre,  bob=TC1                   -> COUNT */
    (15, 'ghl-bob',   1, 1006),  /* post, bob=TC1                   -> NO   */

    /* queue / no admin mapping */
    (16, 'ghl-queue', 1, 1001),  /* no ghl_users row                -> NO   */
    (17, 'ghl-gone',  1, 1001),  /* admin Status='N'                -> NO   */

    /* defensive */
    (18, 'ghl-alice', 0, NULL),  /* unconverted                     -> ignored */
    (19, 'ghl-alice', 1, NULL),  /* converted but no booking        -> ignored */
    (20, NULL,        1, 1001)   /* unassigned                      -> NO   */
");

$fragment = lead_conversion_credit_sql_fragment();

// 1) Aggregate count — mirrors the production SUM(CASE WHEN ...) shape.
$sql = "SELECT
    COUNT(*) AS total_leads,
    SUM(CASE WHEN pl.is_converted = 1 AND pl.booking_id IS NOT NULL AND {$fragment} THEN 1 ELSE 0 END) AS credited_leads
FROM ghl_processed_leads pl";
$row = $pdo->query($sql)->fetch(PDO::FETCH_ASSOC);

// Credited storyline: leads 1, 3, 6, 7, 12, 14 -> 6 total
$assertions = [];
$assertions['Total rows scanned = 20'] = ((int) $row['total_leads']) === 20;
$assertions['Credited (identity-match) rows = 6'] = ((int) $row['credited_leads']) === 6;

// 2) Per-lead breakdown — useful debug if the aggregate is off.
$expected_per_lead = [
    1  => 1, 2  => 0, 3  => 1, 4  => 0, 5  => 0,
    6  => 1, 7  => 1, 8  => 0, 9  => 0, 10 => 0,
    11 => 0, 12 => 1, 13 => 0, 14 => 1, 15 => 0,
    16 => 0, 17 => 0, 18 => 0, 19 => 0, 20 => 0,
];
$detail_sql = "SELECT pl.id,
    CASE WHEN pl.is_converted = 1 AND pl.booking_id IS NOT NULL AND {$fragment} THEN 1 ELSE 0 END AS credited
FROM ghl_processed_leads pl ORDER BY pl.id";
foreach ($pdo->query($detail_sql) as $r) {
    $id = (int) $r['id'];
    $assertions["Lead #{$id} credit = {$expected_per_lead[$id]}"] =
        ((int) $r['credited']) === $expected_per_lead[$id];
}

// 3) Per-agent breakdown — mirrors Lead_Dashboard_By_Agent
//    alice converted = 4 (1, 3, 6, 7); hccs = 1 (12); bob = 1 (14); queue/gone/null = 0
$agent_sql = "SELECT
    COALESCE(NULLIF(pl.assigned_to_user_id, ''), '__unassigned__') AS agent_id,
    SUM(CASE WHEN pl.is_converted = 1 AND pl.booking_id IS NOT NULL AND {$fragment} THEN 1 ELSE 0 END) AS converted_leads
FROM ghl_processed_leads pl
GROUP BY agent_id";
$by_agent = [];
foreach ($pdo->query($agent_sql) as $r) {
    $by_agent[$r['agent_id']] = (int) $r['converted_leads'];
}
$expected_per_agent = [
    'ghl-alice'     => 4,
    'ghl-hccs'      => 1,  // HC CS only credited post-cutoff (lead 12)
    'ghl-bob'       => 1,
    'ghl-queue'     => 0,
    'ghl-gone'      => 0,
    '__unassigned__'=> 0,
];
foreach ($expected_per_agent as $agent => $expected) {
    $actual = isset($by_agent[$agent]) ? $by_agent[$agent] : 0;
    $assertions["Agent {$agent} converted_leads = {$expected}"] = $actual === $expected;
}

$failed = 0;
foreach ($assertions as $label => $ok) {
    echo ($ok ? '  PASS  ' : '  FAIL  ') . $label . PHP_EOL;
    if (!$ok) {
        $failed++;
    }
}

echo PHP_EOL . ($failed === 0 ? "All assertions passed.\n" : "$failed assertion(s) failed.\n");
exit($failed === 0 ? 0 : 1);
