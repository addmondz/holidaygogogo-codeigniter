<?php
/**
 * Run with: php tests/helpers/UpcomingTravelStatusBreakdownTest.php
 *
 * Locks the per-status breakdown surfaced by the "Travel in 7 Days – Not Yet
 * Ready" popover. The card shows a single count; the popover splits it by
 * Status (P / PBO / PGL / PTV). The invariant under test: the four per-status
 * SUM(CASE WHEN) buckets must sum to the same total as the original
 * COUNT(*) query — so the displayed card value can never disagree with the
 * tooltip breakdown.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE booking (
    BookingID INTEGER PRIMARY KEY,
    CancelStatus TEXT,
    BookingConfirmationTitle TEXT,
    Status TEXT,
    StartDate TEXT
)");

// Window: 2026-05-20 .. 2026-05-26 (tomorrow .. today+7 relative to 2026-05-19)
$start = '2026-05-20';
$end   = '2026-05-26';

$pdo->exec("INSERT INTO booking VALUES
    /* P x3 */
    ( 1, 'N', 'BOOKING CONFIRMATION', 'P',   '2026-05-20'),
    ( 2, 'N', 'BOOKING CONFIRMATION', 'P',   '2026-05-23'),
    ( 3, 'N', 'BOOKING CONFIRMATION', 'P',   '2026-05-26'),
    /* PBO x2 */
    ( 4, 'N', 'BOOKING CONFIRMATION', 'PBO', '2026-05-21'),
    ( 5, 'N', 'BOOKING CONFIRMATION', 'PBO', '2026-05-25'),
    /* PGL x1 */
    ( 6, 'N', 'BOOKING CONFIRMATION', 'PGL', '2026-05-22'),
    /* PTV x1 */
    ( 7, 'N', 'BOOKING CONFIRMATION', 'PTV', '2026-05-24'),
    /* excluded: PT (already 'ready') */
    ( 8, 'N', 'BOOKING CONFIRMATION', 'PT',  '2026-05-22'),
    /* excluded: cancelled */
    ( 9, 'Y', 'BOOKING CONFIRMATION', 'P',   '2026-05-22'),
    /* excluded: outside window */
    (10, 'N', 'BOOKING CONFIRMATION', 'P',   '2026-06-01'),
    /* excluded: quotation */
    (11, 'N', 'QUOTATION',            'P',   '2026-05-22')
");

// Original total query
$totalStmt = $pdo->prepare("
    SELECT COUNT(*) AS cnt FROM booking
    WHERE BookingConfirmationTitle='BOOKING CONFIRMATION'
      AND CancelStatus='N'
      AND Status IN ('P','PBO','PGL','PTV')
      AND StartDate BETWEEN :a AND :b
");
$totalStmt->execute(array(':a' => $start, ':b' => $end));
$total = (int) $totalStmt->fetchColumn();

// Breakdown query (what the popover surfaces)
$breakStmt = $pdo->prepare("
    SELECT
      SUM(CASE WHEN Status='P'   THEN 1 ELSE 0 END) AS s_p,
      SUM(CASE WHEN Status='PBO' THEN 1 ELSE 0 END) AS s_pbo,
      SUM(CASE WHEN Status='PGL' THEN 1 ELSE 0 END) AS s_pgl,
      SUM(CASE WHEN Status='PTV' THEN 1 ELSE 0 END) AS s_ptv
    FROM booking
    WHERE BookingConfirmationTitle='BOOKING CONFIRMATION'
      AND CancelStatus='N'
      AND Status IN ('P','PBO','PGL','PTV')
      AND StartDate BETWEEN :a AND :b
");
$breakStmt->execute(array(':a' => $start, ':b' => $end));
$row = $breakStmt->fetch(PDO::FETCH_ASSOC);

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

$p   = (int) $row['s_p'];
$pbo = (int) $row['s_pbo'];
$pgl = (int) $row['s_pgl'];
$ptv = (int) $row['s_ptv'];

assert_eq('total (original COUNT)', 7, $total);
assert_eq('P',                       3, $p);
assert_eq('PBO',                     2, $pbo);
assert_eq('PGL',                     1, $pgl);
assert_eq('PTV',                     1, $ptv);
assert_eq('breakdown sums to total', $total, $p + $pbo + $pgl + $ptv);

echo "\nAll assertions passed.\n";
