<?php
/**
 * Run with: php tests/helpers/TopAgentsConversionUngatedTest.php
 *
 * The "Top Agents – Conversion" panel (Booking summary cards) must count a
 * conversion exactly like the Lead Ownership dashboard's "Converted" column:
 * a lead is converted iff is_converted = 1 AND booking_id IS NOT NULL — with NO
 * TC1/TC2 credit gate. Report_Model::Lead_Dashboard_By_Agent() achieves this by
 * receiving the tautology credit-fragment override ('1=1') from the controller.
 *
 * This test plugs that tautology into the same SUM(CASE WHEN ...) shape the
 * model builds and proves two things:
 *   (a) the ungated converted count == a plain count with no fragment at all, and
 *   (b) per-agent it credits every owned+converted lead regardless of TC slot
 *       (contrast LeadConversionCreditSqlTest.php, which gates the same fixture).
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE ghl_processed_leads (
    id INTEGER PRIMARY KEY,
    assigned_to_user_id TEXT,
    is_converted INTEGER,
    booking_id INTEGER
)");

// Same lead fixture as LeadConversionCreditSqlTest.php so the two tests read as
// a before/after pair. Booking/admin/mapping tables are intentionally absent —
// without the credit gate the converted count never touches them.
$pdo->exec("INSERT INTO ghl_processed_leads VALUES
    (1,  'ghl-alice', 1, 1001),
    (2,  'ghl-alice', 1, 1002),
    (3,  'ghl-alice', 1, 1003),
    (4,  'ghl-alice', 1, 1004),
    (5,  'ghl-alice', 1, 1005),
    (6,  'ghl-alice', 1, 1006),
    (7,  'ghl-alice', 1, 1007),
    (8,  'ghl-alice', 1, 1008),
    (9,  'ghl-alice', 1, 1009),
    (10, 'ghl-alice', 1, 1010),
    (11, 'ghl-hccs',  1, 1001),
    (12, 'ghl-hccs',  1, 1005),
    (13, 'ghl-hccs',  1, 1004),
    (14, 'ghl-bob',   1, 1002),
    (15, 'ghl-bob',   1, 1006),
    (16, 'ghl-queue', 1, 1001),
    (17, 'ghl-gone',  1, 1001),
    (18, 'ghl-alice', 0, NULL),  /* unconverted              -> excluded */
    (19, 'ghl-alice', 1, NULL),  /* converted, no booking    -> excluded */
    (20, NULL,        1, 1001)   /* unassigned bucket        -> counted  */
");

// The exact fragment the controller injects for the Top Agents panel.
$fragment = '1=1';

$assertions = [];

// (a) Aggregate: ungated count == plain count with no fragment.
$ungated = (int) $pdo->query(
    "SELECT SUM(CASE WHEN pl.is_converted = 1 AND pl.booking_id IS NOT NULL AND {$fragment} THEN 1 ELSE 0 END)
     FROM ghl_processed_leads pl"
)->fetchColumn();
$plain = (int) $pdo->query(
    "SELECT COUNT(*) FROM ghl_processed_leads pl
     WHERE pl.is_converted = 1 AND pl.booking_id IS NOT NULL"
)->fetchColumn();
$assertions['Ungated count equals plain is_converted+booking count'] = $ungated === $plain;
$assertions['Ungated converted total = 18'] = $ungated === 18;

// (b) Per-agent — mirrors Lead_Dashboard_By_Agent's GROUP BY. Every owned+
//     converted lead counts; alice now shows 10 (gated test showed 4).
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
    'ghl-alice'      => 10,
    'ghl-hccs'       => 3,
    'ghl-bob'        => 2,
    'ghl-queue'      => 1,
    'ghl-gone'       => 1,
    '__unassigned__' => 1,
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
