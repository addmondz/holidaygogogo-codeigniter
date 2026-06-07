<?php
/**
 * Run with: php tests/helpers/UpcomingTravel14DaysWindowTest.php
 *
 * Locks the window semantics that distinguish the two "Not Yet Ready" cards:
 *
 *   Travel in 7 Days  -> StartDate BETWEEN tomorrow AND +7  days
 *   Travel in 14 Days -> StartDate BETWEEN tomorrow AND +14 days  (cumulative)
 *
 * The 14-day window is a SUPERSET of the 7-day window (same start, later end),
 * so every BC counted in the 7-day card is also in the 14-day card, plus the
 * day 8-14 ones. Both share the same "not yet ready" status set
 * (P / PBO / PGL / PTV), not cancelled, BC only.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE booking (
    BookingID INTEGER PRIMARY KEY, CancelStatus TEXT, BookingConfirmationTitle TEXT,
    Status TEXT, StartDate TEXT
)");

// Reference "today" = 2026-06-15 -> tomorrow 06-16, +7 06-22, +14 06-29.
$today    = '2026-06-15';
$next_start = date('Y-m-d', strtotime($today . ' +1 day'));   // 2026-06-16
$next7_end  = date('Y-m-d', strtotime($today . ' +7 days'));  // 2026-06-22
$next14_end = date('Y-m-d', strtotime($today . ' +14 days')); // 2026-06-29

$pdo->exec("INSERT INTO booking VALUES
    /* 1 day +3  (P)   -> both windows                */ (1,'N','BOOKING CONFIRMATION','P',  '2026-06-18'),
    /* 2 day +7  (PBO) -> both (7-day inclusive end)  */ (2,'N','BOOKING CONFIRMATION','PBO','2026-06-22'),
    /* 3 day +10 (PGL) -> 14-day only                 */ (3,'N','BOOKING CONFIRMATION','PGL','2026-06-25'),
    /* 4 day +14 (PTV) -> 14-day only (inclusive end) */ (4,'N','BOOKING CONFIRMATION','PTV','2026-06-29'),
    /* 5 day +20 (P)   -> neither (beyond 14)         */ (5,'N','BOOKING CONFIRMATION','P',  '2026-07-05'),
    /* 6 today    (P)   -> neither (window starts tmrw)*/ (6,'N','BOOKING CONFIRMATION','P',  '2026-06-15'),
    /* 7 day +5  (Y)   -> neither (already ready)      */ (7,'N','BOOKING CONFIRMATION','Y',  '2026-06-20'),
    /* 8 day +5  (P) cancelled -> neither              */ (8,'Y','BOOKING CONFIRMATION','P',  '2026-06-20'),
    /* 9 day +5  (P) quotation -> neither              */ (9,'N','QUOTATION',           'P',  '2026-06-20')");

$countWindow = function ($end) use ($pdo, $next_start) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM booking
        WHERE BookingConfirmationTitle='BOOKING CONFIRMATION' AND CancelStatus='N'
          AND Status IN ('P','PBO','PGL','PTV')
          AND StartDate BETWEEN :s AND :e");
    $stmt->execute(array(':s' => $next_start, ':e' => $end));
    return (int) $stmt->fetchColumn();
};

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

$c7  = $countWindow($next7_end);
$c14 = $countWindow($next14_end);

assert_eq('7-day window count',  2, $c7);   // rows 1,2
assert_eq('14-day window count', 4, $c14);  // rows 1,2,3,4
assert_eq('14-day is superset (>= 7-day)', true, $c14 >= $c7);
assert_eq('extra in 8-14 band', 2, $c14 - $c7); // rows 3,4

echo "\nAll assertions passed.\n";
