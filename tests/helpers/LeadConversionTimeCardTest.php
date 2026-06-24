<?php
/**
 * Run with: php tests/helpers/LeadConversionTimeCardTest.php
 *
 * Locks the SQL behind the two OP / OP Team Lead "Conversion Time" summary cards
 * and their clickable drill-down:
 *
 *   1. "Avg Conversion Time (Month)" -> Report_Model->Lead_Conversion_Time_Summary()
 *      AVG(converted_at - lead_started_at) over every converted BC this month.
 *   2. "Slow Conversions (> 24h)"    -> the slow_n count from the same method,
 *      clickable to the booking list filtered by ?slow_conversion=1&status=A
 *      (Booking_Model::apply_slow_conversion_filter()).
 *
 * Conversion time = wall-clock gap from when the lead opened the conversation
 * (pl.lead_started_at) to when it was marked converted (pl.converted_at).
 *
 * Rules verified (both card + drill-down share one definition so the count and
 * the linked listing return the same BCs):
 *   - Company-wide: every agent's BCs count (OP / OP Team Lead oversee
 *     operations, not a sales-credit slot).
 *   - Only BOOKING CONFIRMATIONs that are live (CancelStatus='N', Status!='N')
 *     count, matching the drill-down's status=A scope.
 *   - Windowed by lead_started_at within the month.
 *   - Only converted leads with a booking_id, a converted_at, and a
 *     non-negative gap are averaged (clock-skew rows dropped).
 *   - count / slow_count are DISTINCT by BookingID so a BC with two leads is
 *     one BC; "slow" = gap strictly greater than 24h (86400s).
 *   - The drill-down returns exactly the slow BookingIDs the slow_count reports.
 *
 * NOTE: production runs on MySQL with UNIX_TIMESTAMP(); SQLite has no such
 * function, so this mirror uses strftime('%s', ...). Only the LOGIC is locked
 * here, matching the convention of the other lead-dashboard mirror tests.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}
define('SLOW_CONVERSION_THRESHOLD_SECONDS', 86400); // 24h

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE booking (
    BookingID INTEGER PRIMARY KEY,
    InsertDate TEXT,
    CancelStatus TEXT,
    Status TEXT,
    BookingConfirmationTitle TEXT
)");
$pdo->exec("CREATE TABLE ghl_processed_leads (
    id INTEGER PRIMARY KEY,
    booking_id INTEGER,
    is_converted INTEGER,
    lead_started_at TEXT,
    converted_at TEXT
)");

// BookingID, InsertDate, CancelStatus, Status, Title
$bookings = array(
    // B1: June BC; fast lead (1h).
    array(1, '2026-06-10', 'N', 'P',  'BOOKING CONFIRMATION'),
    // B2: June BC; slow lead (48h).
    array(2, '2026-06-12', 'N', 'PP', 'BOOKING CONFIRMATION'),
    // B3: pre-cutoff BC; slow lead (72h), lead started in June -> still in window.
    array(3, '2026-05-20', 'N', 'Y',  'BOOKING CONFIRMATION'),
    // B4: June BC (different agent — company-wide still counts it); slow (72h).
    array(4, '2026-06-15', 'N', 'P',  'BOOKING CONFIRMATION'),
    // B5: June BC but CANCELLED -> excluded (status=A drops it).
    array(5, '2026-06-16', 'Y', 'P',  'BOOKING CONFIRMATION'),
    // B6: June "BC" but a QUOTATION -> excluded.
    array(6, '2026-06-17', 'N', 'P',  'QUOTATION'),
    // B7: June BC; lead started LAST month -> out of window.
    array(7, '2026-06-18', 'N', 'P',  'BOOKING CONFIRMATION'),
    // B8: June BC; lead NOT converted -> excluded.
    array(8, '2026-06-19', 'N', 'P',  'BOOKING CONFIRMATION'),
    // B9: June BC; converted but clock-skew (converted before start) -> dropped.
    array(9, '2026-06-20', 'N', 'P',  'BOOKING CONFIRMATION'),
);
$ins = $pdo->prepare("INSERT INTO booking
    (BookingID, InsertDate, CancelStatus, Status, BookingConfirmationTitle)
    VALUES (?,?,?,?,?)");
foreach ($bookings as $b) { $ins->execute($b); }

// id, booking_id, is_converted, lead_started_at, converted_at
$leads = array(
    array(1, 1, 1, '2026-06-02 09:00:00', '2026-06-02 10:00:00'), // +3600   (1h)
    array(2, 2, 1, '2026-06-03 08:00:00', '2026-06-05 08:00:00'), // +172800 (48h)
    array(3, 3, 1, '2026-06-04 08:00:00', '2026-06-07 08:00:00'), // +259200 (72h)
    array(4, 4, 1, '2026-06-15 08:00:00', '2026-06-18 08:00:00'), // +259200 (72h)
    array(5, 5, 1, '2026-06-16 08:00:00', '2026-06-19 08:00:00'), // cancelled BC
    array(6, 6, 1, '2026-06-17 08:00:00', '2026-06-20 08:00:00'), // quotation
    array(7, 7, 1, '2026-05-25 08:00:00', '2026-05-28 08:00:00'), // prev-month lead start
    array(8, 8, 0, '2026-06-19 08:00:00', NULL),                  // not converted
    array(9, 9, 1, '2026-06-20 10:00:00', '2026-06-20 09:00:00'), // skew (negative)
);
$insL = $pdo->prepare("INSERT INTO ghl_processed_leads
    (id, booking_id, is_converted, lead_started_at, converted_at) VALUES (?,?,?,?,?)");
foreach ($leads as $l) { $insL->execute($l); }

$gap = "(strftime('%s', pl.converted_at) - strftime('%s', pl.lead_started_at))";

// ---- Mirror of Report_Model::Lead_Conversion_Time_Summary() (company-wide) ----
// $threshold is a server-side constant (24h), inlined as an integer literal:
// the real model binds it via numeric UNIX_TIMESTAMP arithmetic, but SQLite's
// strftime() returns strings, so a bound param would compare lexicographically.
$summary = function ($pdo, $start, $end, $threshold) use ($gap) {
    $threshold = (int) $threshold;
    $qual = "pl.is_converted = 1
             AND pl.booking_id IS NOT NULL
             AND pl.converted_at IS NOT NULL
             AND pl.lead_started_at IS NOT NULL
             AND {$gap} >= 0";
    $sql = "
        SELECT
            AVG(CASE WHEN {$qual} THEN {$gap} END) AS avg_seconds,
            COUNT(DISTINCT CASE WHEN {$qual} THEN b.BookingID END) AS n,
            COUNT(DISTINCT CASE WHEN {$qual} AND {$gap} > {$threshold} THEN b.BookingID END) AS slow_n
        FROM ghl_processed_leads pl
        INNER JOIN booking b ON b.BookingID = pl.booking_id
        WHERE b.CancelStatus = 'N'
          AND b.Status != 'N'
          AND b.BookingConfirmationTitle = 'BOOKING CONFIRMATION'
          AND pl.lead_started_at >= ?
          AND pl.lead_started_at <= ?
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array($start . ' 00:00:00', $end . ' 23:59:59'));
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    return array(
        'avg_seconds' => $r['avg_seconds'] === null ? null : (int) round($r['avg_seconds']),
        'count'       => (int) $r['n'],
        'slow_count'  => (int) $r['slow_n'],
    );
};

// ---- Mirror of Booking_Model::apply_slow_conversion_filter() drill-down ----
// Returns the BookingIDs the listing would show for ?slow_conversion=1&status=A.
$drilldown = function ($pdo, $monthStart, $monthEnd, $threshold) use ($gap) {
    $threshold = (int) $threshold;
    // status=A scope (CancelStatus='N', Status!='N') + BC-only + the time-gap
    // subquery — exactly what apply_slow_conversion_filter() + the status filter
    // add to the booking list query. Company-wide: no agent scoping.
    $sql = "
        SELECT booking.BookingID
        FROM booking
        WHERE booking.CancelStatus = 'N'
          AND booking.Status != 'N'
          AND booking.BookingConfirmationTitle = 'BOOKING CONFIRMATION'
          AND booking.BookingID IN (
                SELECT pl.booking_id
                FROM ghl_processed_leads pl
                WHERE pl.is_converted = 1
                  AND pl.booking_id IS NOT NULL
                  AND pl.converted_at IS NOT NULL
                  AND pl.lead_started_at IS NOT NULL
                  AND pl.lead_started_at >= ?
                  AND pl.lead_started_at <= ?
                  AND {$gap} > {$threshold}
          )
        ORDER BY booking.BookingID ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array($monthStart . ' 00:00:00', $monthEnd . ' 23:59:59'));
    return array_map('intval', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'BookingID'));
};

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got "      . var_export($actual, true) . "\n";
        exit(1);
    }
}

$mStart = '2026-06-01';
$mEnd   = '2026-06-30';
$T      = SLOW_CONVERSION_THRESHOLD_SECONDS;

// Qualifying BCs this month, all agents: B1 (3600), B2 (172800), B3 (259200),
// B4 (259200). avg = 694800 / 4 = 173700s.
$s = $summary($pdo, $mStart, $mEnd, $T);
assert_eq('avg_seconds', 173700, $s['avg_seconds']);
assert_eq('count',            4, $s['count']);
assert_eq('slow_count',       3, $s['slow_count']); // B2, B3, B4 (> 24h)

// Drill-down lists exactly the slow BCs -> matches slow_count.
$ids = $drilldown($pdo, $mStart, $mEnd, $T);
assert_eq('drilldown ids', array(2, 3, 4), $ids);
assert_eq('drilldown count == slow_count', $s['slow_count'], count($ids));

// Empty window -> null avg, zero counts (front-end renders an em-dash).
$empty = $summary($pdo, '2026-01-01', '2026-01-31', $T);
assert_eq('empty avg_seconds', null, $empty['avg_seconds']);
assert_eq('empty count',          0, $empty['count']);
assert_eq('empty slow_count',     0, $empty['slow_count']);

echo "\nAll assertions passed.\n";
