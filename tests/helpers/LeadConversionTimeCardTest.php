<?php
/**
 * Run with: php tests/helpers/LeadConversionTimeCardTest.php
 *
 * Locks the SQL behind the two OP / OP Team Lead summary cards and their
 * clickable drill-down:
 *
 *   1. "Avg Conversion Time (Month)" -> submitted_payment_conversion_summary_sql()
 *      AVG(first_p_at - first_sad_at) over every booking saved as draft this
 *      month that has since reached payment.
 *   2. "Slow Conversions (> 24h)"    -> the slow_n count from the same query,
 *      clickable to the booking list filtered by ?slow_conversion=1&status=A
 *      (Booking_Model::apply_slow_conversion_filter(), which calls
 *      submitted_payment_slow_ids_sql_fragment()).
 *
 * Conversion time here = the SAD -> first-P gap (same definition as the TC
 * "Draft -> Payment Time" card) but COMPANY-WIDE:
 *   START : earliest TRANSITION into SAD ("SAVE AS DRAFT") in booking_status_log
 *   END   : earliest TRANSITION into P ("PENDING PAYMENT", from_status set)
 *
 * Rules verified (card + drill-down share one definition so the count and the
 * linked listing return the same bookings):
 *   - Company-wide: every agent's bookings count (OP / OP Team Lead oversee
 *     operations, not a sales-credit slot).
 *   - Only live bookings count (CancelStatus='N', Status!='N'), matching the
 *     drill-down's status=A scope.
 *   - Windowed by the SAD anchor (the draft-save date), cut off at today: the
 *     end bound is "today", never a future month-end.
 *   - Only drafts that have BOTH a SAD anchor and a genuine P transition are
 *     counted (a P creation row with from_status NULL is ignored).
 *   - "slow" = gap strictly greater than 24h (86400s); the drill-down returns
 *     exactly the slow BookingIDs the slow_count reports.
 *
 * Uses the real helper SQL against an in-memory SQLite copy of the schema.
 * SQLite lacks MySQL's UNIX_TIMESTAMP, so we register a UDF mapping to
 * strtotime() — the production SQL is portable once the UDF is in place.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}
define('SLOW_CONVERSION_THRESHOLD_SECONDS', 86400); // 24h

require_once __DIR__ . '/../../application/helpers/submitted_payment_response_helper.php';

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got "      . var_export($actual, true) . "\n";
        exit(1);
    }
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->sqliteCreateFunction('UNIX_TIMESTAMP', function ($t) {
    if ($t === null || $t === '') { return null; }
    return (int) strtotime($t);
});

$pdo->exec("CREATE TABLE booking (
    BookingID INTEGER PRIMARY KEY,
    CancelStatus TEXT,
    Status TEXT
)");
$pdo->exec("CREATE TABLE booking_status_log (
    id INTEGER PRIMARY KEY,
    booking_id INTEGER,
    from_status TEXT,
    to_status TEXT,
    created_at TEXT
)");

// BookingID, CancelStatus, Status
$bookings = array(
    array(1,  'N', 'P'),   // fast draft->payment (1h)
    array(2,  'N', 'P'),   // slow (48h)
    array(3,  'N', 'Y'),   // slow (72h), advanced past P -> still counts (P transition logged)
    array(4,  'N', 'P'),   // slow (72h), different agent — company-wide counts it
    array(5,  'Y', 'P'),   // slow but CANCELLED -> excluded (status=A drops it)
    array(6,  'N', 'N'),   // slow but Status='N' -> excluded
    array(7,  'N', 'P'),   // saved as draft LAST month -> out of window
    array(8,  'N', 'SAD'), // saved as draft but NEVER reached P -> excluded
    array(9,  'N', 'P'),   // saved as draft AFTER today's cutoff -> excluded
    array(10, 'N', 'P'),   // P creation row (NULL) ignored; real transition used (1h)
);
$ins = $pdo->prepare("INSERT INTO booking (BookingID, CancelStatus, Status) VALUES (?,?,?)");
foreach ($bookings as $b) { $ins->execute($b); }

// SAD rows anchor the START; P rows (TRANSITIONS only, from_status set) the END.
$log = array(
    // booking_id, from_status, to_status, created_at
    array(1,  'PBC', 'SAD', '2026-06-02 09:00:00'),
    array(1,  'PBC', 'P',   '2026-06-02 10:00:00'), // +3600   (1h)
    array(2,  'PBC', 'SAD', '2026-06-03 09:00:00'),
    array(2,  'PBC', 'P',   '2026-06-05 09:00:00'), // +172800 (48h)
    array(3,  'PBC', 'SAD', '2026-06-07 09:00:00'),
    array(3,  'PBC', 'P',   '2026-06-10 09:00:00'), // +259200 (72h)
    array(4,  'PBC', 'SAD', '2026-06-15 09:00:00'),
    array(4,  'PBC', 'P',   '2026-06-18 09:00:00'), // +259200 (72h)
    array(5,  'PBC', 'SAD', '2026-06-16 09:00:00'),
    array(5,  'PBC', 'P',   '2026-06-19 09:00:00'), // cancelled booking
    array(6,  'PBC', 'SAD', '2026-06-17 09:00:00'),
    array(6,  'PBC', 'P',   '2026-06-20 09:00:00'), // Status='N'
    array(7,  'PBC', 'SAD', '2026-05-28 09:00:00'),
    array(7,  'PBC', 'P',   '2026-06-01 09:00:00'), // SAD in May -> out of window
    array(8,  'PBC', 'SAD', '2026-06-19 09:00:00'), // never reaches P
    array(9,  'PBC', 'SAD', '2026-06-25 09:00:00'),
    array(9,  'PBC', 'P',   '2026-06-26 09:00:00'), // SAD after today's cutoff
    array(10, 'PBC', 'SAD', '2026-06-20 08:00:00'),
    array(10, NULL,  'P',   '2026-06-20 07:00:00'), // creation row -> ignored
    array(10, 'PBC', 'P',   '2026-06-20 09:00:00'), // real transition -> +3600 (1h)
);
$insL = $pdo->prepare("INSERT INTO booking_status_log
    (booking_id, from_status, to_status, created_at) VALUES (?,?,?,?)");
foreach ($log as $l) { $insL->execute($l); }

// ---- Card: submitted_payment_conversion_summary_sql() (company-wide) ----
$summary = function ($pdo, $start, $end, $threshold) {
    $stmt = $pdo->prepare(submitted_payment_conversion_summary_sql($threshold));
    $stmt->execute(array($start, $end));
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    return array(
        'avg_seconds' => $r['avg_seconds'] === null ? null : (int) round($r['avg_seconds']),
        'count'       => (int) $r['n'],
        'slow_count'  => (int) $r['slow_n'],
    );
};

// ---- Drill-down: apply_slow_conversion_filter() + status=A scope -------
// Mirrors the listing: the slow-ids subquery AND the status=A live scope
// (CancelStatus='N', Status!='N') the booking list applies alongside it.
$drilldown = function ($pdo, $start, $end, $threshold) {
    $sql = "SELECT booking.BookingID
            FROM booking
            WHERE booking.CancelStatus = 'N'
              AND booking.Status != 'N'
              AND booking.BookingID IN (" .
                submitted_payment_slow_ids_sql_fragment($start, $end, $threshold) .
            ") ORDER BY booking.BookingID ASC";
    return array_map('intval', array_column($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC), 'BookingID'));
};

$mStart = '2026-06-01 00:00:00';
$today  = '2026-06-24 23:59:59'; // window cut off at today, not the month-end
$T      = SLOW_CONVERSION_THRESHOLD_SECONDS;

// Drafts saved this month that reached payment: B1 (3600), B2 (172800),
// B3 (259200), B4 (259200), B10 (3600). avg = 698400 / 5 = 139680s.
$s = $summary($pdo, $mStart, $today, $T);
assert_eq('avg_seconds', 139680, $s['avg_seconds']);
assert_eq('count',            5, $s['count']);
assert_eq('slow_count',       3, $s['slow_count']); // B2, B3, B4 (> 24h)

// Drill-down lists exactly the slow live bookings -> matches slow_count.
$ids = $drilldown($pdo, $mStart, $today, $T);
assert_eq('drilldown ids', array(2, 3, 4), $ids);
assert_eq('drilldown count == slow_count', $s['slow_count'], count($ids));

// Cut off to today: a mid-month cutoff drops drafts saved after it (B4 on
// Jun 15, B10 on Jun 20). Qualifying: B1, B2, B3. avg = 435600 / 3 = 145200s.
$cut = $summary($pdo, $mStart, '2026-06-12 23:59:59', $T);
assert_eq('cutoff avg_seconds', 145200, $cut['avg_seconds']);
assert_eq('cutoff count',            3, $cut['count']);
assert_eq('cutoff slow_count',       2, $cut['slow_count']); // B2, B3
$cutIds = $drilldown($pdo, $mStart, '2026-06-12 23:59:59', $T);
assert_eq('cutoff drilldown ids', array(2, 3), $cutIds);

// Empty window -> null avg, zero counts (front-end renders an em-dash).
$empty = $summary($pdo, '2026-01-01 00:00:00', '2026-01-31 23:59:59', $T);
assert_eq('empty avg_seconds', null, $empty['avg_seconds']);
assert_eq('empty count',          0, $empty['count']);
assert_eq('empty slow_count',     0, $empty['slow_count']);

echo "\nAll assertions passed.\n";
