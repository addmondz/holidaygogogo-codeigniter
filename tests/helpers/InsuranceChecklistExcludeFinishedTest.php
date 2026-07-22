<?php
/**
 * Run with: php tests/helpers/InsuranceChecklistExcludeFinishedTest.php
 *
 * Locks the "exclude completed & pending-review BCs" toggle on the OP
 * "Pending Insurance Checklist" summary card.
 *
 * The card (Booking::ajax_summary_cards) counts confirmed BCs, travel from
 * 1 March of the current year onwards, that carry an Insurance package
 * checklist with no completion record on at least one active, payable line
 * (booking_product.disable_checklist_payment_out = 0). Its drill-down
 * (?checklist_filter=<insurance ids>&travel_date=<window>&status=A&...) lists
 * exactly those BCs.
 *
 * When the toggle is ON the card carries ?insurance_exclude_finished=1, which
 * the controller turns into "AND booking.Status != 'Y'" on the count and an
 * extra ?exclude_finished=1 on the drill-down link. Status='Y' is precisely a
 * finished trip — COMPLETE (after-sales done) or PENDING REVIEW (after-sales
 * pending) — so the switch drops both at once. The drill-down honours
 * exclude_finished via Booking_Model::apply_booking_filters().
 *
 * This test uses LIKE-on-JSON instead of JSON_CONTAINS because SQLite doesn't
 * ship JSON_CONTAINS; the production query uses JSON_CONTAINS on MySQL. Both
 * pick the same rows for these fixtures.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE booking (
    BookingID INTEGER PRIMARY KEY,
    BookingConfirmationTitle TEXT,
    CancelStatus TEXT,
    Status TEXT,
    AfterSalesService TEXT,
    StartDate TEXT,
    EndDate TEXT
)");
$pdo->exec("CREATE TABLE booking_product (
    BookingProductID INTEGER PRIMARY KEY,
    BookingID INTEGER,
    ProductID INTEGER,
    Status TEXT,
    disable_checklist_payment_out INTEGER
)");
$pdo->exec("CREATE TABLE product (
    ProductID INTEGER PRIMARY KEY,
    is_child_or_infant INTEGER
)");
$pdo->exec("CREATE TABLE product_package_checklist (
    id INTEGER PRIMARY KEY,
    product_id INTEGER,
    package_checklist_json TEXT
)");
$pdo->exec("CREATE TABLE booking_checklist_completion (
    id INTEGER PRIMARY KEY,
    booking_id INTEGER,
    product_id INTEGER,
    package_checklist_id INTEGER
)");

$ins_id = 7; // an "Insurance" package_checklist id

// Travel window: 1 Mar current year .. far future (mirrors the card).
$win_start = '2026-03-01';
$win_end   = '2031-12-31';
$travel    = '2026-07-01'; // inside the window
$travel2   = '2026-04-15'; // inside the window

// Product 100 carries the insurance checklist; 300 is child/infant.
$pdo->exec("INSERT INTO product VALUES (100, 0), (300, 1)");
$pdo->exec("INSERT INTO product_package_checklist VALUES
    (1, 100, '[{$ins_id}]'),
    (2, 300, '[{$ins_id}]')
");

// Bookings — all confirmed, not cancelled, not draft, insurance unticked,
// travelling inside the window, EXCEPT where noted.
$pdo->exec("INSERT INTO booking VALUES
    (1, 'BOOKING CONFIRMATION', 'N', 'P',  'PENDING',  '{$travel}',  '{$travel}'),   /* live (pending payment)      -> always           */
    (2, 'BOOKING CONFIRMATION', 'N', 'PTV','PENDING',  '{$travel}',  '{$travel}'),   /* live (pending travel)       -> always           */
    (3, 'BOOKING CONFIRMATION', 'N', 'Y',  'COMPLETE', '{$travel2}', '{$travel2}'),  /* finished + after-sales done -> hidden when ON   */
    (4, 'BOOKING CONFIRMATION', 'N', 'Y',  'PENDING',  '{$travel2}', '{$travel2}'),  /* finished + pending review   -> hidden when ON   */
    (5, 'BOOKING CONFIRMATION', 'Y', 'P',  'PENDING',  '{$travel}',  '{$travel}'),   /* cancelled                   -> never            */
    (6, 'BOOKING CONFIRMATION', 'N', 'N',  'PENDING',  '{$travel}',  '{$travel}'),   /* draft                       -> never            */
    (7, 'QUOTATION',            'N', 'P',  'PENDING',  '{$travel}',  '{$travel}')    /* quotation                   -> never            */
");

$pdo->exec("INSERT INTO booking_product VALUES
    (1, 1, 100, 'Y', 0),
    (2, 2, 100, 'Y', 0),
    (3, 3, 100, 'Y', 0),
    (4, 4, 100, 'Y', 0),
    (5, 5, 100, 'Y', 0),
    (6, 6, 100, 'Y', 0),
    (7, 7, 100, 'Y', 0)
");
// No booking_checklist_completion rows: every line's insurance is unticked.

// ---------------------------------------------------------------------------
// Card count — production query, parametrised by the exclude-finished switch.
// ---------------------------------------------------------------------------
function insurance_count($pdo, $ins_id, $win_start, $win_end, $exclude_finished) {
    $finished = $exclude_finished ? " AND booking.Status != 'Y'" : '';
    $sql = "SELECT COUNT(DISTINCT booking.BookingID) AS cnt
        FROM booking
        WHERE booking.BookingConfirmationTitle='BOOKING CONFIRMATION'
          AND booking.CancelStatus='N' AND booking.Status!='N'{$finished}
          AND ((booking.StartDate <= '{$win_end}' AND booking.EndDate >= '{$win_start}')
               OR (booking.StartDate >= '{$win_start}' AND booking.StartDate <= '{$win_end}')
               OR (booking.EndDate >= '{$win_start}' AND booking.EndDate <= '{$win_end}'))
          AND booking.BookingID IN (
            SELECT DISTINCT bp.BookingID
            FROM booking_product bp
            JOIN product p ON p.ProductID = bp.ProductID AND p.is_child_or_infant = 0
            JOIN product_package_checklist ppc ON ppc.product_id = bp.ProductID
              AND ppc.package_checklist_json LIKE '%{$ins_id}%'
            WHERE bp.Status = 'Y'
              AND bp.disable_checklist_payment_out = 0
              AND NOT EXISTS (
                SELECT 1 FROM booking_checklist_completion bcc
                WHERE bcc.booking_id = bp.BookingID
                  AND bcc.product_id = bp.ProductID
                  AND bcc.package_checklist_id = {$ins_id}
              )
          )";
    return (int)$pdo->query($sql)->fetch(PDO::FETCH_ASSOC)['cnt'];
}

// ---------------------------------------------------------------------------
// Drill-down BC set — mirrors apply_checklist_filter() + status=A, with the
// optional exclude_finished clause apply_booking_filters() adds.
// ---------------------------------------------------------------------------
function drilldown_bcs($pdo, $ins_id, $win_start, $win_end, $exclude_finished) {
    $finished = $exclude_finished ? " AND booking.Status != 'Y'" : '';
    $sql = "SELECT BookingID FROM booking
        WHERE booking.BookingConfirmationTitle='BOOKING CONFIRMATION'
          AND booking.CancelStatus='N' AND booking.Status!='N'{$finished}
          AND ((booking.StartDate <= '{$win_end}' AND booking.EndDate >= '{$win_start}')
               OR (booking.StartDate >= '{$win_start}' AND booking.StartDate <= '{$win_end}')
               OR (booking.EndDate >= '{$win_start}' AND booking.EndDate <= '{$win_end}'))
          AND booking.BookingID IN (
            SELECT DISTINCT bp.BookingID
            FROM booking_product bp
            JOIN product p ON p.ProductID = bp.ProductID AND p.is_child_or_infant = 0
            JOIN product_package_checklist ppc ON ppc.product_id = bp.ProductID
              AND ppc.package_checklist_json LIKE '%{$ins_id}%'
            WHERE bp.Status = 'Y'
              AND bp.disable_checklist_payment_out = 0
              AND NOT EXISTS (
                SELECT 1 FROM booking_checklist_completion bcc
                WHERE bcc.booking_id = bp.BookingID
                  AND bcc.product_id = bp.ProductID
                  AND bcc.package_checklist_id = {$ins_id}
              )
          )
        ORDER BY BookingID";
    return array_map('intval', $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN));
}

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . json_encode($actual) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . json_encode($expected)
           . ", got " . json_encode($actual) . "\n";
        exit(1);
    }
}
function assert_true($label, $cond) {
    if ($cond) { echo "  PASS  {$label}\n"; }
    else { echo "  FAIL  {$label}\n"; exit(1); }
}

// Toggle OFF: BC1, BC2 (live) + BC3, BC4 (finished) = 4.
assert_eq('count, toggle OFF (BC1,2,3,4)', 4, insurance_count($pdo, $ins_id, $win_start, $win_end, false));
assert_eq('drill-down, toggle OFF',       array(1, 2, 3, 4), drilldown_bcs($pdo, $ins_id, $win_start, $win_end, false));

// Toggle ON: completed (BC3) and pending-review (BC4) drop out -> BC1, BC2 = 2.
assert_eq('count, toggle ON (BC1,2)',     2, insurance_count($pdo, $ins_id, $win_start, $win_end, true));
assert_eq('drill-down, toggle ON',        array(1, 2), drilldown_bcs($pdo, $ins_id, $win_start, $win_end, true));

// Card count and its drill-down agree row-for-row in both toggle states.
assert_eq('parity OFF', insurance_count($pdo, $ins_id, $win_start, $win_end, false), count(drilldown_bcs($pdo, $ins_id, $win_start, $win_end, false)));
assert_eq('parity ON',  insurance_count($pdo, $ins_id, $win_start, $win_end, true),  count(drilldown_bcs($pdo, $ins_id, $win_start, $win_end, true)));

// ---------------------------------------------------------------------------
// Source-code locks: the model honours exclude_finished, the controller reads
// the toggle and threads it onto both the count and the drill-down link.
// ---------------------------------------------------------------------------
$model = file_get_contents(__DIR__ . '/../../application/models/Booking_Model.php');
assert_true('model honours exclude_finished',
    strpos($model, "get('exclude_finished')") !== false
    && strpos($model, "booking.Status !=") !== false);

$controller = file_get_contents(__DIR__ . '/../../application/controllers/Booking.php');
assert_true('controller reads insurance_exclude_finished toggle',
    strpos($controller, "insurance_exclude_finished") !== false);
// Lock the wiring within the insurance card block.
$start = strpos($controller, "// Pending Insurance Checklist");
$block = $start !== false ? substr($controller, $start, 4000) : '';
assert_true('insurance count adds Status != Y when toggle on',
    strpos($block, "booking.Status != 'Y'") !== false);
assert_true('insurance drill-down link carries exclude_finished',
    strpos($block, "'exclude_finished'") !== false);

// The booking list's DataTables AJAX only forwards a whitelist of URL params to
// ajax_list; exclude_finished must be on it or the drill-down silently drops the
// toggle and the list over-counts versus the card.
$index = file_get_contents(__DIR__ . '/../../application/views/booking/index.php');
assert_true('booking list forwards exclude_finished to ajax_list',
    strpos($index, "'exclude_finished'") !== false);

echo "\nAll assertions passed.\n";
