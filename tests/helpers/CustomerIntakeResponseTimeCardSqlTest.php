<?php
/**
 * Run with: php tests/helpers/CustomerIntakeResponseTimeCardSqlTest.php
 *
 * Locks the SQL behind the "Intake -> BC Response Time (Month)" summary
 * card. The card has three variants:
 *
 *   - Team / company average + count for the current month
 *   - TC-only average + count (filtered by the credited TC slot)
 *   - "Best" footer: which TC has the lowest average in the month
 *
 * Tested against an in-memory SQLite copy of the schema. SQLite doesn't
 * have MySQL's TIMESTAMPDIFF/UNIX_TIMESTAMP, so we register a
 * UNIX_TIMESTAMP UDF that maps to strtotime() — the production SQL uses
 * `(UNIX_TIMESTAMP(pbc.first_pbc_at) - UNIX_TIMESTAMP(ci.submitted_at))`
 * which is portable between the two engines once that UDF is in place.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}
if (!defined('LEAD_CONVERSION_TC2_CUTOFF_DATE')) {
    define('LEAD_CONVERSION_TC2_CUTOFF_DATE', '2026-06-01');
}

require_once __DIR__ . '/../../application/helpers/lead_conversion_credit_helper.php';
require_once __DIR__ . '/../../application/helpers/customer_intake_helper.php';

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}
function assert_close($label, $expected, $actual, $eps = 0.5) {
    if (abs($expected - $actual) < $eps) {
        echo "  PASS  {$label} ≈ " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected ≈" . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

// -- String-level shape assertions --------------------------------------

$avg_sql_team = customer_intake_avg_response_sql(false);
$avg_sql_tc   = customer_intake_avg_response_sql(true);
$best_sql     = customer_intake_best_agent_sql();

assert_eq('team SQL is string', true, is_string($avg_sql_team) && $avg_sql_team !== '');
assert_eq('team SQL mentions booking_customer_intake', true, strpos($avg_sql_team, 'booking_customer_intake') !== false);
assert_eq('team SQL mentions booking_status_log',      true, strpos($avg_sql_team, 'booking_status_log') !== false);
assert_eq("team SQL filters to_status='PBC'",          true, strpos($avg_sql_team, "to_status = 'PBC'") !== false);
assert_eq("team SQL excludes creation rows",           true, strpos($avg_sql_team, 'from_status IS NOT NULL') !== false);
assert_eq("team SQL excludes b.Status='N'",            true, strpos($avg_sql_team, "b.Status != 'N'") !== false);
assert_eq('team SQL does NOT include credit clause',   false, strpos($avg_sql_team, 'SalesAgent') !== false);
assert_eq('TC SQL DOES include credit clause',         true,  strpos($avg_sql_tc,   'SalesAgent') !== false);
assert_eq("best SQL has GROUP BY",                     true,  stripos($best_sql, 'GROUP BY') !== false);
assert_eq("best SQL has HAVING for min-sample",        true,  stripos($best_sql, 'HAVING') !== false);
assert_eq("best SQL orders by avg_seconds ASC",        true,  stripos($best_sql, 'ORDER BY avg_seconds ASC') !== false);

// -- Run SQL against SQLite ---------------------------------------------

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->sqliteCreateFunction('UNIX_TIMESTAMP', function ($t) {
    if ($t === null || $t === '') { return null; }
    return (int) strtotime($t);
});

// Mirror only the columns the production SQL touches.
$pdo->exec("CREATE TABLE booking (
    BookingID INTEGER PRIMARY KEY,
    SalesAgent INTEGER,
    SalesAgent2 INTEGER,
    InsertDate TEXT,
    Status TEXT
)");
$pdo->exec("CREATE TABLE booking_customer_intake (
    id INTEGER PRIMARY KEY,
    booking_id INTEGER,
    submitted_at TEXT
)");
$pdo->exec("CREATE TABLE booking_status_log (
    id INTEGER PRIMARY KEY,
    booking_id INTEGER,
    from_status TEXT,
    to_status TEXT,
    created_at TEXT
)");

$month_start = '2026-05-01 00:00:00';
$month_end   = '2026-06-01 00:00:00';

// AdminID 4 = NATASHA, AdminID 5 = AISYAH.
//
// Booking 100: intake 2026-05-10 09:00, PBC 2026-05-10 10:00 -> 3600s. TC1=4. (in window, qualifies)
// Booking 101: intake 2026-05-12 09:00, PBC 2026-05-12 13:00 -> 14400s. TC1=4. (in window, qualifies)
// Booking 102: intake 2026-05-15 09:00, PBC 2026-05-15 10:30 -> 5400s. TC1=5. (in window, qualifies)
// Booking 103: intake 2026-05-15 09:00, PBC 2026-05-15 11:00 -> 7200s. TC1=5. (in window, qualifies)
// Booking 200: intake 2026-04-30 09:00, PBC 2026-05-01 09:00 -> in PREVIOUS month - excluded.
// Booking 300: intake 2026-05-20 09:00, NO PBC log -> excluded.
// Booking 400: intake 2026-05-21 09:00, PBC 2026-05-21 09:30 -> 1800s, but Status='N' (soft-deleted) -> excluded.
// Booking 500: intake 2026-05-22 09:00, PBC 2026-05-22 11:00 + extra PBC log at 12:00 -> picks earliest = 7200s. TC1=4.
$pdo->exec("INSERT INTO booking (BookingID, SalesAgent, SalesAgent2, InsertDate, Status) VALUES
    (100, 4, NULL, '2026-05-10 08:00:00', 'PCI'),
    (101, 4, NULL, '2026-05-12 08:00:00', 'PCI'),
    (102, 5, NULL, '2026-05-15 08:00:00', 'PCI'),
    (103, 5, NULL, '2026-05-15 08:00:00', 'PCI'),
    (200, 4, NULL, '2026-04-30 08:00:00', 'PCI'),
    (300, 4, NULL, '2026-05-20 08:00:00', 'PCI'),
    (400, 4, NULL, '2026-05-21 08:00:00', 'N'),
    (500, 4, NULL, '2026-05-22 08:00:00', 'PCI')
");
$pdo->exec("INSERT INTO booking_customer_intake (booking_id, submitted_at) VALUES
    (100, '2026-05-10 09:00:00'),
    (101, '2026-05-12 09:00:00'),
    (102, '2026-05-15 09:00:00'),
    (103, '2026-05-15 09:00:00'),
    (200, '2026-04-30 09:00:00'),
    (300, '2026-05-20 09:00:00'),
    (400, '2026-05-21 09:00:00'),
    (500, '2026-05-22 09:00:00')
");
// Graduation rows are TRANSITIONS into PBC (from_status set). Booking 100 also
// carries a draft CREATION row (from_status NULL) timestamped BEFORE its intake
// submission — it must be excluded, otherwise the gap would be negative.
$pdo->exec("INSERT INTO booking_status_log (booking_id, from_status, to_status, created_at) VALUES
    (100, NULL,  'PBC', '2026-05-10 07:00:00'),
    (100, 'SAD', 'PBC', '2026-05-10 10:00:00'),
    (101, 'SAD', 'PBC', '2026-05-12 13:00:00'),
    (102, 'SAD', 'PBC', '2026-05-15 10:30:00'),
    (103, 'SAD', 'PBC', '2026-05-15 11:00:00'),
    (200, 'SAD', 'PBC', '2026-05-01 09:00:00'),
    /* 300 has no PBC transition log */
    (400, 'SAD', 'PBC', '2026-05-21 09:30:00'),
    (500, 'PB',  'PBC', '2026-05-22 12:00:00'),
    (500, 'PB',  'PBC', '2026-05-22 11:00:00')
");

// -- 1) Team average (no credit clause) ---------------------------------
// Qualifying bookings: 100 (3600), 101 (14400), 102 (5400), 103 (7200), 500 (7200 — picks earliest PBC)
// avg = (3600 + 14400 + 5400 + 7200 + 7200) / 5 = 37800 / 5 = 7560s
// n = 5
$stmt = $pdo->prepare($avg_sql_team);
$stmt->execute(array($month_start, $month_end));
$row = $stmt->fetch(PDO::FETCH_ASSOC);
assert_close('team avg_seconds = 7560', 7560, (float) $row['avg_seconds']);
assert_eq('team n = 5', 5, (int) $row['n']);

// -- 2) TC variant (credited slot = NATASHA AdminID 4) ------------------
// Bookings 200/300/400 are excluded by the same filters as the team query.
// Of bookings 100/101/102/103/500, AdminID 4 holds the credited slot for
// 100/101/500 (TC1, pre-cutoff). 102/103 are credited to 5.
// avg = (3600 + 14400 + 7200) / 3 = 25200 / 3 = 8400s, n = 3
$stmt = $pdo->prepare($avg_sql_tc);
$stmt->execute(array($month_start, $month_end, 4, 4));
$row = $stmt->fetch(PDO::FETCH_ASSOC);
assert_close('TC(4) avg_seconds = 8400', 8400, (float) $row['avg_seconds']);
assert_eq('TC(4) n = 3', 3, (int) $row['n']);

// -- 3) Best agent (lowest avg in the month, min 2 bookings) ------------
// AdminID 4: 100/101/500 -> avg 8400 (n=3)
// AdminID 5: 102/103     -> avg 6300 (n=2)
// Lowest avg -> AdminID 5 wins.
$stmt = $pdo->prepare($best_sql);
$stmt->execute(array($month_start, $month_end));
$best = $stmt->fetch(PDO::FETCH_ASSOC);
assert_eq('best AdminID = 5', 5, (int) $best['AdminID']);
assert_close('best avg_seconds = 6300', 6300, (float) $best['avg_seconds']);
assert_eq('best n = 2', 2, (int) $best['n']);

// -- 4) Min-sample guard --------------------------------------------------
// Wipe bookings 100/101 (which belonged to AdminID 4) and replace with a
// single very-fast row so AdminID 4 now has only n=1 with a super-low avg.
// AdminID 5 still has n=2. The HAVING n >= 2 should skip 4 and pick 5.
$pdo->exec("DELETE FROM booking WHERE BookingID IN (101, 500)");
$pdo->exec("DELETE FROM booking_customer_intake WHERE booking_id IN (101, 500)");
$pdo->exec("DELETE FROM booking_status_log WHERE booking_id IN (101, 500)");
// AdminID 4 now has only booking 100: avg 3600s, n=1. AdminID 5 has 102/103: avg 6300s, n=2.
$stmt = $pdo->prepare($best_sql);
$stmt->execute(array($month_start, $month_end));
$best = $stmt->fetch(PDO::FETCH_ASSOC);
assert_eq('min-sample guard: AdminID = 5', 5, (int) $best['AdminID']);
assert_eq('min-sample guard: n = 2',       2, (int) $best['n']);

// -- 5) Empty window ----------------------------------------------------
$pdo->exec("DELETE FROM booking_customer_intake");
$pdo->exec("DELETE FROM booking_status_log");
$stmt = $pdo->prepare($avg_sql_team);
$stmt->execute(array($month_start, $month_end));
$row = $stmt->fetch(PDO::FETCH_ASSOC);
assert_eq('empty window: n = 0', 0, (int) $row['n']);
// avg_seconds is NULL when no rows; some PDO drivers return null, some empty string.
assert_eq('empty window: avg_seconds is NULL-ish', true, $row['avg_seconds'] === null || $row['avg_seconds'] === '');

echo "\nAll assertions passed.\n";
