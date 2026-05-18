<?php
/**
 * Run with: php tests/helpers/LeadConversionCreditSqlTest.php
 *
 * End-to-end stress of the SQL fragment from lead_conversion_credit_helper.php:
 * seeds a SQLite :memory: schema mirroring booking / ghl_processed_leads,
 * plugs the fragment into the same SUM(CASE WHEN ...) shape used by
 * Report_Model::Lead_Dashboard_Summary, and verifies the cutoff rule —
 * pre-2026-06-01 conversions require SalesAgent set; on/after require
 * SalesAgent2 set.
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

// Bookings on both sides of the 2026-06-01 cutoff.
$pdo->exec("INSERT INTO booking VALUES
    (1001, 7,    99,   '2026-05-30 10:00:00'),  /* pre-cutoff: TC1 set -> counts */
    (1002, NULL, 99,   '2026-05-30 10:00:00'),  /* pre-cutoff: TC1 NULL -> NOT counts */
    (1003, 7,    NULL, '2026-05-31 23:59:59'),  /* boundary: TC1 set -> counts */
    (1004, 7,    99,   '2026-06-01 00:00:00'),  /* on cutoff: TC2 set -> counts */
    (1005, 7,    NULL, '2026-06-15 12:30:00'),  /* post-cutoff: TC2 NULL -> NOT counts */
    (1006, NULL, 99,   '2026-06-15 12:30:00'),  /* post-cutoff: TC2 set -> counts (TC1 NULL is fine post-cutoff) */
    (1007, 7,    0,    '2026-06-15 12:30:00'),  /* post-cutoff: TC2=0 sentinel -> NOT counts */
    (1008, 0,    99,   '2026-05-30 10:00:00')   /* pre-cutoff: TC1=0 sentinel -> NOT counts */
");

// Leads — pl.id pairs with the booking storyline:
//  1: lead -> booking 1001 (pre, TC1 set).            Expect: counts.
//  2: lead -> booking 1002 (pre, TC1 NULL).           Expect: does NOT count.
//  3: lead -> booking 1003 (boundary, TC1 set).       Expect: counts.
//  4: lead -> booking 1004 (cutoff, TC2 set).         Expect: counts.
//  5: lead -> booking 1005 (post, TC2 NULL).          Expect: does NOT count.
//  6: lead -> booking 1006 (post, TC2 set).           Expect: counts.
//  7: lead -> booking 1007 (post, TC2=0).             Expect: does NOT count.
//  8: lead -> booking 1008 (pre, TC1=0).              Expect: does NOT count.
//  9: unconverted lead.                                Expect: ignored.
// 10: converted lead with NULL booking_id (defensive). Expect: ignored.
$pdo->exec("INSERT INTO ghl_processed_leads VALUES
    (1,  'ghl-alice', 1, 1001),
    (2,  'ghl-alice', 1, 1002),
    (3,  'ghl-alice', 1, 1003),
    (4,  'ghl-alice', 1, 1004),
    (5,  'ghl-alice', 1, 1005),
    (6,  'ghl-alice', 1, 1006),
    (7,  'ghl-alice', 1, 1007),
    (8,  'ghl-alice', 1, 1008),
    (9,  'ghl-alice', 0, NULL),
    (10, 'ghl-alice', 1, NULL)
");

$fragment = lead_conversion_credit_sql_fragment();

// 1) Aggregate count — mirrors the production SUM(CASE WHEN ...) shape.
$sql = "SELECT
    COUNT(*) AS total_leads,
    SUM(CASE WHEN pl.is_converted = 1 AND pl.booking_id IS NOT NULL AND {$fragment} THEN 1 ELSE 0 END) AS credited_leads
FROM ghl_processed_leads pl";
$row = $pdo->query($sql)->fetch(PDO::FETCH_ASSOC);

// Per the storyline: leads 1, 3, 4, 6 count. That's 4 of 10.
$assertions = [];
$assertions['Total rows scanned = 10'] = ((int) $row['total_leads']) === 10;
$assertions['Credited (slot-set) rows = 4'] = ((int) $row['credited_leads']) === 4;

// 2) Per-lead breakdown — useful debug if the aggregate is off.
$expected_per_lead = [
    1  => 1, 2  => 0, 3  => 1, 4  => 1, 5  => 0,
    6  => 1, 7  => 0, 8  => 0, 9  => 0, 10 => 0,
];
$detail_sql = "SELECT pl.id,
    CASE WHEN pl.is_converted = 1 AND pl.booking_id IS NOT NULL AND {$fragment} THEN 1 ELSE 0 END AS credited
FROM ghl_processed_leads pl ORDER BY pl.id";
foreach ($pdo->query($detail_sql) as $r) {
    $id = (int) $r['id'];
    $assertions["Lead #{$id} credit = {$expected_per_lead[$id]}"] =
        ((int) $r['credited']) === $expected_per_lead[$id];
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
