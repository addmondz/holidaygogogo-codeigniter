<?php
/**
 * Run with: php tests/helpers/ChecklistPayoutDueSoonCardTest.php
 *
 * Locks the SQL behind the OP "Supplier Pay-out Checklist Due Soon" summary
 * card and its clickable Overdue / Today / Tomorrow drill-downs
 * (?checklist_payout=overdue|today|tomorrow, handled by
 * Booking_Model::apply_checklist_payout_filter()).
 *
 * Unlike "Supplier Pay-out Due Soon" (which reads the payment table — payouts
 * already CREATED), this card is driven by the CHECKLIST: a booking line whose
 * "Payment Out To Supplier (full|deposit)" checklist is NOT ticked yet, with
 * the payout DEADLINE taken from booking_product.PaymentOutSupplierFull /
 * PaymentOutSupplierDeposit. This is the same signal the cron reminders use
 * (Cronjob_Model::get_bookings_with_supplier_date + is_checklist_incomplete).
 *
 * A booking line qualifies when ALL hold:
 *   - The product is assigned the full (id=1) / deposit (id=2) checklist via
 *     product_package_checklist.package_checklist_json (JSON array of IDs)
 *   - The product is not child/infant
 *   - booking_product.Status = 'Y' (active line)
 *   - booking_product.disable_checklist_payment_out = 0   (mirrors the
 *     modal/filter rule — see CLAUDE memory feedback_checklist_filter)
 *   - The matching deadline column is set (full -> PaymentOutSupplierFull,
 *     deposit -> PaymentOutSupplierDeposit)
 *   - No booking_checklist_completion row for (booking, product, that checklist)
 *   - The booking is a non-cancelled, non-draft BOOKING CONFIRMATION
 *
 * Buckets (deadline within floor=1 Mar of current year .. tomorrow):
 *   overdue  : floor <= deadline < today
 *   today    : deadline = today
 *   tomorrow : deadline = today + 1
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
    Status TEXT
)");
$pdo->exec("CREATE TABLE booking_product (
    BookingProductID INTEGER PRIMARY KEY,
    BookingID INTEGER,
    ProductID INTEGER,
    Status TEXT,
    disable_checklist_payment_out INTEGER,
    PaymentOutSupplierFull TEXT,
    PaymentOutSupplierDeposit TEXT
)");
$pdo->exec("CREATE TABLE product (
    ProductID INTEGER PRIMARY KEY,
    SupplierID INTEGER,
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
$pdo->exec("CREATE TABLE supplier (
    SupplierID INTEGER PRIMARY KEY,
    Name TEXT
)");

$full_id = 1;
$dep_id  = 2;

$today    = '2026-05-20';
$tomorrow = '2026-05-21';
$minus1   = '2026-05-19';
$floor    = '2026-03-01';
$mar1     = '2026-03-01';
$preMar   = '2026-02-10';

// Products: 100/200 supplier-linked with full+deposit checklist; 300 child/infant.
$pdo->exec("INSERT INTO product VALUES (100, 10, 0), (200, 11, 0), (300, 10, 1)");
$pdo->exec("INSERT INTO product_package_checklist VALUES
    (1, 100, '[1, 2, 4]'),
    (2, 200, '[1, 2]'),
    (3, 300, '[1]')
");
$pdo->exec("INSERT INTO supplier VALUES (10, 'REDANG BAY'), (11, 'LAGUNA')");

$pdo->exec("INSERT INTO booking VALUES
    (1,  'BOOKING CONFIRMATION', 'N', 'P'),  /* full overdue, unticked        -> OVERDUE          */
    (2,  'BOOKING CONFIRMATION', 'N', 'P'),  /* deposit today, unticked       -> TODAY            */
    (3,  'BOOKING CONFIRMATION', 'N', 'P'),  /* full tomorrow, unticked       -> TOMORROW         */
    (4,  'BOOKING CONFIRMATION', 'N', 'P'),  /* full overdue but completed    -> none             */
    (5,  'BOOKING CONFIRMATION', 'N', 'P'),  /* full today, disable flag=1    -> none             */
    (6,  'BOOKING CONFIRMATION', 'Y', 'P'),  /* full overdue, cancelled       -> none             */
    (7,  'BOOKING CONFIRMATION', 'N', 'P'),  /* full BEFORE floor (Feb)       -> none             */
    (8,  'BOOKING CONFIRMATION', 'N', 'P'),  /* full overdue + deposit tomorrow -> OVERDUE+TMR     */
    (9,  'BOOKING CONFIRMATION', 'N', 'P'),  /* child/infant product          -> none             */
    (10, 'BOOKING CONFIRMATION', 'N', 'N'),  /* draft                         -> none             */
    (11, 'QUOTATION',            'N', 'P'),  /* quotation                     -> none             */
    (12, 'BOOKING CONFIRMATION', 'N', 'P')   /* full on the floor (1 Mar)     -> OVERDUE          */
");

$pdo->exec("INSERT INTO booking_product VALUES
    (1,  1,  100, 'Y', 0, '{$minus1}',  NULL),
    (2,  2,  200, 'Y', 0, NULL,         '{$today}'),
    (3,  3,  100, 'Y', 0, '{$tomorrow}',NULL),
    (4,  4,  100, 'Y', 0, '{$minus1}',  NULL),
    (5,  5,  100, 'Y', 1, '{$today}',   NULL),
    (6,  6,  100, 'Y', 0, '{$minus1}',  NULL),
    (7,  7,  100, 'Y', 0, '{$preMar}',  NULL),
    (8,  8,  100, 'Y', 0, '{$minus1}',  '{$tomorrow}'),
    (9,  9,  300, 'Y', 0, '{$minus1}',  NULL),
    (10, 10, 100, 'Y', 0, '{$today}',   NULL),
    (11, 11, 100, 'Y', 0, '{$today}',   NULL),
    (12, 12, 100, 'Y', 0, '{$mar1}',    NULL)
");

// Completion: booking 4's full checklist is ticked.
$pdo->exec("INSERT INTO booking_checklist_completion VALUES (1, 4, 100, 1)");

// ---------------------------------------------------------------------------
// Build the qualifying "due line" set the same way production does (UNION of a
// full branch and a deposit branch). Production uses JSON_CONTAINS; we
// approximate with LIKE on the JSON literal — identical row selection here.
// ---------------------------------------------------------------------------
function due_lines_union($full_id, $dep_id) {
    $branch = function($checklist_id, $date_col) {
        return "
        SELECT bp.BookingID AS bid, p.SupplierID AS sid, bp.{$date_col} AS dl
        FROM booking_product bp
        JOIN product p ON p.ProductID = bp.ProductID AND p.is_child_or_infant = 0
        JOIN product_package_checklist ppc ON ppc.product_id = bp.ProductID
            AND (ppc.package_checklist_json LIKE '%[{$checklist_id}]%'
              OR ppc.package_checklist_json LIKE '%[{$checklist_id},%'
              OR ppc.package_checklist_json LIKE '%, {$checklist_id}]%'
              OR ppc.package_checklist_json LIKE '%, {$checklist_id},%')
        JOIN booking b ON b.BookingID = bp.BookingID
        WHERE bp.Status = 'Y' AND bp.disable_checklist_payment_out = 0
          AND bp.{$date_col} IS NOT NULL
          AND b.BookingConfirmationTitle = 'BOOKING CONFIRMATION'
          AND b.CancelStatus = 'N' AND b.Status != 'N'
          AND NOT EXISTS (
            SELECT 1 FROM booking_checklist_completion bcc
            WHERE bcc.booking_id = bp.BookingID
              AND bcc.product_id = bp.ProductID
              AND bcc.package_checklist_id = {$checklist_id}
          )";
    };
    return $branch($full_id, 'PaymentOutSupplierFull')
         . "\nUNION ALL\n"
         . $branch($dep_id, 'PaymentOutSupplierDeposit');
}

$union = due_lines_union($full_id, $dep_id);

// Bucket counts (distinct BookingID per bucket) — backs the card headline.
$sql = "SELECT
    COUNT(DISTINCT CASE WHEN dl < '{$today}'    THEN bid END) AS overdue_cnt,
    COUNT(DISTINCT CASE WHEN dl = '{$today}'    THEN bid END) AS today_cnt,
    COUNT(DISTINCT CASE WHEN dl = '{$tomorrow}' THEN bid END) AS tomorrow_cnt
    FROM ({$union}) due
    WHERE dl BETWEEN '{$floor}' AND '{$tomorrow}'";
$counts = $pdo->query($sql)->fetch(PDO::FETCH_ASSOC);

// Supplier-grouped table (top suppliers across the window, earliest first).
$tbl_sql = "SELECT s.SupplierID AS sid, s.Name AS name, COUNT(*) AS cnt, MIN(due.dl) AS earliest
    FROM ({$union}) due
    JOIN supplier s ON s.SupplierID = due.sid
    WHERE due.dl BETWEEN '{$floor}' AND '{$tomorrow}'
    GROUP BY s.SupplierID, s.Name
    ORDER BY MIN(due.dl) ASC, COUNT(*) DESC";
$tbl = $pdo->query($tbl_sql)->fetchAll(PDO::FETCH_ASSOC);

// ---------------------------------------------------------------------------
// Drill-down parity: apply_checklist_payout_filter() lists every BOOKING that
// has at least one qualifying line in the bucket — must equal the card's
// distinct-BC count per bucket.
// ---------------------------------------------------------------------------
function bcs_for_bucket($pdo, $bucket, $full_id, $dep_id, $today, $tomorrow, $floor) {
    $range = function($col) use ($bucket, $today, $tomorrow, $floor) {
        if ($bucket === 'overdue')  return "{$col} >= '{$floor}' AND {$col} < '{$today}'";
        if ($bucket === 'today')    return "{$col} = '{$today}'";
        if ($bucket === 'tomorrow') return "{$col} = '{$tomorrow}'";
        return null;
    };
    $exists = function($checklist_id, $date_col) use ($range) {
        $r = $range("bp.{$date_col}");
        return "EXISTS (
            SELECT 1 FROM booking_product bp
            JOIN product p ON p.ProductID = bp.ProductID AND p.is_child_or_infant = 0
            JOIN product_package_checklist ppc ON ppc.product_id = bp.ProductID
                AND (ppc.package_checklist_json LIKE '%[{$checklist_id}]%'
                  OR ppc.package_checklist_json LIKE '%[{$checklist_id},%'
                  OR ppc.package_checklist_json LIKE '%, {$checklist_id}]%'
                  OR ppc.package_checklist_json LIKE '%, {$checklist_id},%')
            WHERE bp.BookingID = booking.BookingID
              AND bp.Status = 'Y' AND bp.disable_checklist_payment_out = 0
              AND bp.{$date_col} IS NOT NULL
              AND {$r}
              AND NOT EXISTS (
                SELECT 1 FROM booking_checklist_completion bcc
                WHERE bcc.booking_id = bp.BookingID
                  AND bcc.product_id = bp.ProductID
                  AND bcc.package_checklist_id = {$checklist_id}
              )
        )";
    };
    // Booking-level filters supplied by the drill-down (status=A + title).
    $sql = "SELECT BookingID FROM booking
            WHERE BookingConfirmationTitle = 'BOOKING CONFIRMATION'
              AND CancelStatus = 'N' AND Status != 'N'
              AND ((" . $exists($full_id, 'PaymentOutSupplierFull')
                 . ") OR (" . $exists($dep_id, 'PaymentOutSupplierDeposit') . "))
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

// Card bucket counts.
assert_eq('overdue count (BC1 + BC8 + BC12)', 3, (int)$counts['overdue_cnt']);
assert_eq('today count (BC2)',                1, (int)$counts['today_cnt']);
assert_eq('tomorrow count (BC3 + BC8)',       2, (int)$counts['tomorrow_cnt']);

// Drill-down BC sets per bucket — must align with the counts above.
assert_eq('overdue BCs',  array(1, 8, 12), bcs_for_bucket($pdo, 'overdue',  $full_id, $dep_id, $today, $tomorrow, $floor));
assert_eq('today BCs',    array(2),        bcs_for_bucket($pdo, 'today',    $full_id, $dep_id, $today, $tomorrow, $floor));
assert_eq('tomorrow BCs', array(3, 8),     bcs_for_bucket($pdo, 'tomorrow', $full_id, $dep_id, $today, $tomorrow, $floor));

// Supplier table: REDANG BAY (BC1 full, BC3 full, BC8 full+deposit, BC12 full
// = 5 lines) then LAGUNA (BC2 deposit = 1 line). Earliest deadline first
// (floor = 1 Mar from BC12) puts REDANG BAY ahead.
assert_eq('supplier table size', 2, count($tbl));
assert_eq('top supplier is REDANG BAY', 'REDANG BAY', $tbl[0]['name']);
assert_eq('REDANG BAY line count', 5, (int)$tbl[0]['cnt']);
assert_eq('second supplier is LAGUNA', 'LAGUNA', $tbl[1]['name']);
assert_eq('LAGUNA line count', 1, (int)$tbl[1]['cnt']);

// ---------------------------------------------------------------------------
// Source-code locks: the model registers the filter and the controller link
// carries checklist_payout + status=A + booking_confirmation_title so the card
// count and its drill-down list can never disagree (same parity rule as the
// insurance/ferry/supplier-due-soon cards — CLAUDE memory
// feedback_card_drilldown_status).
// ---------------------------------------------------------------------------
$model = file_get_contents(__DIR__ . '/../../application/models/Booking_Model.php');
assert_true('model defines apply_checklist_payout_filter()',
    strpos($model, 'function apply_checklist_payout_filter') !== false);
assert_true('model wires apply_checklist_payout_filter() into the filter chain',
    substr_count($model, '$this->apply_checklist_payout_filter()') >= 2);

$controller = file_get_contents(__DIR__ . '/../../application/controllers/Booking.php');
assert_true('controller builds checklist_payout_due_soon card',
    strpos($controller, "\$cards['checklist_payout_due_soon']") !== false);
// The drill-down link params are assembled in the $cp_link closure just above
// the card array; lock them there.
$start = strpos($controller, '$cp_link = function');
$link_block = $start !== false ? substr($controller, $start, 400) : '';
assert_true('drill-down link carries checklist_payout bucket',
    strpos($link_block, "'checklist_payout'") !== false);
assert_true('drill-down link carries status=A',
    strpos($link_block, "'status'") !== false && strpos($link_block, "'A'") !== false);
assert_true('drill-down link constrains booking_confirmation_title',
    strpos($link_block, "'booking_confirmation_title'") !== false
    && strpos($link_block, 'BOOKING CONFIRMATION') !== false);

echo "\nAll assertions passed.\n";
