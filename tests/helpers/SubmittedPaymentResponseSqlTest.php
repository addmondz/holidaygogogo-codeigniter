<?php
/**
 * Run with: php tests/helpers/SubmittedPaymentResponseSqlTest.php
 *
 * Locks the SQL behind the "Draft -> Payment Time" summary card. The card
 * measures, per booking, the gap between:
 *
 *   START : earliest TRANSITION into SAD ("SAVE AS DRAFT") in booking_status_log
 *           (the moment the booking was saved as draft)
 *   END   : earliest TRANSITION into P ("PENDING PAYMENT", from_status set)
 *
 * windowed on the START anchor. Three variants:
 *
 *   - Team / company average + count for a period
 *   - TC-only average + count (filtered by the credited TC slot)
 *   - "Best" footer: which TC has the lowest (fastest) average
 *
 * Tested against an in-memory SQLite copy of the schema. SQLite lacks MySQL's
 * UNIX_TIMESTAMP, so we register a UDF that maps to strtotime() — the
 * production SQL uses UNIX_TIMESTAMP, portable between engines once the UDF is
 * in place.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}
if (!defined('LEAD_CONVERSION_TC2_CUTOFF_DATE')) {
    define('LEAD_CONVERSION_TC2_CUTOFF_DATE', '2026-06-01');
}

require_once __DIR__ . '/../../application/helpers/lead_conversion_credit_helper.php';
require_once __DIR__ . '/../../application/helpers/submitted_payment_response_helper.php';

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

$avg_sql_team = submitted_payment_avg_response_sql(false);
$avg_sql_tc   = submitted_payment_avg_response_sql(true);
$best_sql     = submitted_payment_best_agent_sql();

assert_eq('team SQL is string', true, is_string($avg_sql_team) && $avg_sql_team !== '');
assert_eq('team SQL anchors start on first_sad_at', true, strpos($avg_sql_team, 'd.first_sad_at') !== false);
assert_eq("team SQL reads SAD from booking_status_log", true, strpos($avg_sql_team, "to_status = 'SAD'") !== false);
assert_eq("team SQL anchors end on to_status='P'",   true, strpos($avg_sql_team, "to_status = 'P'") !== false);
assert_eq("team SQL excludes P creation rows",       true, strpos($avg_sql_team, 'from_status IS NOT NULL') !== false);
assert_eq("team SQL excludes b.Status='N'",          true, strpos($avg_sql_team, "b.Status != 'N'") !== false);
assert_eq('team SQL does NOT include credit clause', false, strpos($avg_sql_team, 'SalesAgent') !== false);
assert_eq('TC SQL DOES include credit clause',       true,  strpos($avg_sql_tc,   'SalesAgent') !== false);
assert_eq("best SQL has GROUP BY",                   true,  stripos($best_sql, 'GROUP BY') !== false);
assert_eq("best SQL has HAVING for min-sample",      true,  stripos($best_sql, 'HAVING') !== false);
assert_eq("best SQL orders by avg_seconds ASC",      true,  stripos($best_sql, 'ORDER BY avg_seconds ASC') !== false);

// -- Run SQL against SQLite ---------------------------------------------

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->sqliteCreateFunction('UNIX_TIMESTAMP', function ($t) {
    if ($t === null || $t === '') { return null; }
    return (int) strtotime($t);
});

// Mirror only the columns the production SQL touches. InsertDate stays
// pre-cutoff so the credited slot is TC1 (SalesAgent).
$pdo->exec("CREATE TABLE booking (
    BookingID INTEGER PRIMARY KEY,
    SalesAgent INTEGER,
    SalesAgent2 INTEGER,
    InsertDate TEXT,
    Status TEXT
)");
$pdo->exec("CREATE TABLE booking_status_log (
    id INTEGER PRIMARY KEY,
    booking_id INTEGER,
    from_status TEXT,
    to_status TEXT,
    created_at TEXT
)");

$win_start = '2026-05-01 00:00:00';
$win_end   = '2026-06-01 00:00:00';

// AdminID 4 = NATASHA, AdminID 5 = AISYAH. All pre-cutoff -> TC1 credited.
//
// 100: SAD 05-10 09:00 -> P 05-10 11:00 = 7200s.  TC1=4.
// 101: SAD 05-12 09:00 -> P 05-12 12:00 = 10800s. TC1=4.
// 102: SAD 05-15 09:00 -> P 05-15 10:00 = 3600s.  TC1=5.
// 103: SAD 05-15 09:00 -> P 05-15 11:00 = 7200s.  TC1=5.
// 200: SAD 04-30 09:00 -> P 05-01 09:00 — saved as draft PREVIOUS month -> excluded by window.
// 300: SAD 05-20 09:00 -> NO P transition -> excluded.
// 400: SAD 05-21 09:00 -> P 05-21 09:30, but Status='N' (soft-deleted) -> excluded.
// 500: SAD 05-22 09:00 -> P creation row (NULL) 08:00 + real P transition 10:00 = 3600s (picks transition). TC1=4.
$pdo->exec("INSERT INTO booking (BookingID, SalesAgent, SalesAgent2, InsertDate, Status) VALUES
    (100, 4, NULL, '2026-05-10 08:00:00', 'P'),
    (101, 4, NULL, '2026-05-12 08:00:00', 'P'),
    (102, 5, NULL, '2026-05-15 08:00:00', 'P'),
    (103, 5, NULL, '2026-05-15 08:00:00', 'P'),
    (200, 4, NULL, '2026-04-30 08:00:00', 'P'),
    (300, 4, NULL, '2026-05-20 08:00:00', 'SAD'),
    (400, 4, NULL, '2026-05-21 08:00:00', 'N'),
    (500, 4, NULL, '2026-05-22 08:00:00', 'P')
");
// SAD rows anchor the START; P rows (TRANSITIONS only, from_status set) anchor the END.
$pdo->exec("INSERT INTO booking_status_log (booking_id, from_status, to_status, created_at) VALUES
    (100, 'PBC', 'SAD', '2026-05-10 09:00:00'),
    (101, 'PBC', 'SAD', '2026-05-12 09:00:00'),
    (102, 'PBC', 'SAD', '2026-05-15 09:00:00'),
    (103, 'PBC', 'SAD', '2026-05-15 09:00:00'),
    (200, 'PBC', 'SAD', '2026-04-30 09:00:00'),
    (300, 'PBC', 'SAD', '2026-05-20 09:00:00'),
    (400, 'PBC', 'SAD', '2026-05-21 09:00:00'),
    (500, 'PBC', 'SAD', '2026-05-22 09:00:00'),
    (100, 'PBC', 'P', '2026-05-10 11:00:00'),
    (101, 'PBC', 'P', '2026-05-12 12:00:00'),
    (102, 'PBC', 'P', '2026-05-15 10:00:00'),
    (103, 'PBC', 'P', '2026-05-15 11:00:00'),
    (200, 'PBC', 'P', '2026-05-01 09:00:00'),
    /* 300 never reaches P */
    (400, 'PBC', 'P', '2026-05-21 09:30:00'),
    (500, NULL,  'P', '2026-05-22 08:00:00'),
    (500, 'PBC', 'P', '2026-05-22 10:00:00')
");

// -- 1) Team average (no credit clause) ---------------------------------
// Qualifying: 100(7200), 101(10800), 102(3600), 103(7200), 500(3600)
// avg = (7200+10800+3600+7200+3600)/5 = 32400/5 = 6480s, n = 5
$stmt = $pdo->prepare($avg_sql_team);
$stmt->execute(array($win_start, $win_end));
$row = $stmt->fetch(PDO::FETCH_ASSOC);
assert_close('team avg_seconds = 6480', 6480, (float) $row['avg_seconds']);
assert_eq('team n = 5', 5, (int) $row['n']);

// -- 2) TC variant (credited slot = NATASHA AdminID 4) ------------------
// AdminID 4 holds TC1 on 100/101/500. 102/103 credited to 5.
// avg = (7200+10800+3600)/3 = 21600/3 = 7200s, n = 3
$stmt = $pdo->prepare($avg_sql_tc);
$stmt->execute(array($win_start, $win_end, 4, 4));
$row = $stmt->fetch(PDO::FETCH_ASSOC);
assert_close('TC(4) avg_seconds = 7200', 7200, (float) $row['avg_seconds']);
assert_eq('TC(4) n = 3', 3, (int) $row['n']);

// -- 3) Best agent (lowest avg, min 2 bookings) -------------------------
// AdminID 4: 100/101/500 -> avg 7200 (n=3)
// AdminID 5: 102/103     -> avg 5400 (n=2)
// Lowest avg -> AdminID 5 wins.
$stmt = $pdo->prepare($best_sql);
$stmt->execute(array($win_start, $win_end));
$best = $stmt->fetch(PDO::FETCH_ASSOC);
assert_eq('best AdminID = 5', 5, (int) $best['AdminID']);
assert_close('best avg_seconds = 5400', 5400, (float) $best['avg_seconds']);
assert_eq('best n = 2', 2, (int) $best['n']);

// -- 4) Min-sample guard ------------------------------------------------
// Drop 101/500 so AdminID 4 has only booking 100 (n=1). AdminID 5 still n=2.
$pdo->exec("DELETE FROM booking WHERE BookingID IN (101, 500)");
$pdo->exec("DELETE FROM booking_status_log WHERE booking_id IN (101, 500)");
$stmt = $pdo->prepare($best_sql);
$stmt->execute(array($win_start, $win_end));
$best = $stmt->fetch(PDO::FETCH_ASSOC);
assert_eq('min-sample guard: AdminID = 5', 5, (int) $best['AdminID']);
assert_eq('min-sample guard: n = 2',       2, (int) $best['n']);

// -- 5) Empty window ----------------------------------------------------
$pdo->exec("DELETE FROM booking_status_log");
$stmt = $pdo->prepare($avg_sql_team);
$stmt->execute(array($win_start, $win_end));
$row = $stmt->fetch(PDO::FETCH_ASSOC);
assert_eq('empty window: n = 0', 0, (int) $row['n']);
assert_eq('empty window: avg_seconds is NULL-ish', true, $row['avg_seconds'] === null || $row['avg_seconds'] === '');

echo "\nAll assertions passed.\n";
