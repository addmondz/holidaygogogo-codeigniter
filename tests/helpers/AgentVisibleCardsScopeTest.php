<?php
/**
 * Run with: php tests/helpers/AgentVisibleCardsScopeTest.php
 *
 * Locks the "scope to visible page rows" behaviour that _agent_upcoming_cards()
 * adds for the three sales-agent listing cards (Travel in 7/14 Days – Not Yet
 * Ready, Payment From Customer Due Soon).
 *
 * The extra `AND booking.BookingID IN (...visible ids...)` is layered on TOP of
 * the existing conditions, never a replacement:
 *   - null  scope -> whole DB (dashboard / full payload)   [unchanged behaviour]
 *   - id list      -> count only the rows on the current DataTables page
 *   - empty list   -> count nothing (never falls back to whole DB)
 * The int-sanitising (drop non-ints / <=0, de-dupe) matches the controller.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

// Mirrors the $id_clause builder in Booking::_agent_upcoming_cards().
function agent_scope_id_clause($scope_ids) {
    if (!is_array($scope_ids)) return '';
    $ids = array_values(array_unique(array_filter(
        array_map('intval', $scope_ids),
        function ($v) { return $v > 0; }
    )));
    return empty($ids) ? ' AND 1=0' : ' AND booking.BookingID IN (' . implode(',', $ids) . ')';
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE booking (
    BookingID INTEGER PRIMARY KEY, CancelStatus TEXT, BookingConfirmationTitle TEXT,
    Status TEXT, StartDate TEXT, SalesAgent INTEGER, SalesAgent2 INTEGER
)");

$AGENT = 7;
$today      = '2026-06-15';
$next_start = date('Y-m-d', strtotime($today . ' +1 day'));   // 2026-06-16
$next14_end = date('Y-m-d', strtotime($today . ' +14 days')); // 2026-06-29

// All four qualify for the 14-day window + own slot; ids 2 and 4 are on OTHER
// listing pages (not visible), ids 1 and 3 are on the current page.
$pdo->exec("INSERT INTO booking VALUES
    (1,'N','BOOKING CONFIRMATION','P',  '2026-06-18',7,0),
    (2,'N','BOOKING CONFIRMATION','PBO','2026-06-20',7,0),
    (3,'N','BOOKING CONFIRMATION','PGL','2026-06-25',7,0),
    (4,'N','BOOKING CONFIRMATION','PTV','2026-06-28',7,0)");

$countTravel = function ($scope) use ($pdo, $next_start, $next14_end, $AGENT) {
    $sql = "SELECT COUNT(*) FROM booking
        WHERE BookingConfirmationTitle='BOOKING CONFIRMATION' AND CancelStatus='N'
          AND Status IN ('P','PBO','PGL','PTV')
          AND StartDate BETWEEN :s AND :e
          AND (booking.SalesAgent = :a OR booking.SalesAgent2 = :a)"
        . agent_scope_id_clause($scope);
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array(':s' => $next_start, ':e' => $next14_end, ':a' => $AGENT));
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

assert_eq('null scope -> whole DB',        4, $countTravel(null));
assert_eq('visible page [1,3]',            2, $countTravel(array(1, 3)));
assert_eq('empty page -> nothing',         0, $countTravel(array()));
assert_eq('sanitises junk/<=0/dupes',      1, $countTravel(array(1, 'x', 0, -5, 1)));

echo "\nAll assertions passed.\n";
