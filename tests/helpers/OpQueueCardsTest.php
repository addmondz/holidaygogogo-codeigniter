<?php
/**
 * Run with: php tests/helpers/OpQueueCardsTest.php
 *
 * Locks the SQL behind the five OP booking-summary "operational queue" cards
 * and their clickable drill-downs:
 *
 *   1. Pending BC                         -> Status = 'PB'   (link: status=PB)
 *   2. Pending BC Confirmation            -> Status = 'PBC'  (link: status=PBC)
 *   3. Travelling Tomorrow (any status)   -> StartDate = tomorrow
 *   4. Travelling Tomorrow & NOT Pending  -> StartDate = tomorrow AND Status != 'PT'  (red card)
 *   5. Travel Completed - Pending Review  -> Status = 'Y' AND AfterSalesService = 'PENDING' (link: status=PR)
 *
 * Cards 3 & 4 scope by *departure date* (StartDate = tomorrow), not the generic
 * travel_date OVERLAP filter — a trip merely spanning tomorrow must NOT count.
 * The drill-downs therefore use a dedicated travel_start_date filter plus
 * status=A (drops the list's default AfterSalesService='PENDING' gate) and the
 * BOOKING CONFIRMATION title. Card 4 additionally passes exclude_status=PT so
 * the already-ready Pending Travel BCs drop out and the list matches the count.
 *
 * Same card/drill-down parity discipline as the insurance/ferry/supplier cards
 * (CLAUDE memory feedback_card_drilldown_status).
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
    StartDate TEXT
)");

$today    = '2026-06-24';
$tomorrow = '2026-06-25';
$dayAfter = '2026-06-26';

// Cols: id, title, CancelStatus, Status, AfterSalesService, StartDate
$pdo->exec("INSERT INTO booking VALUES
    (1,  'BOOKING CONFIRMATION', 'N', 'PB',  'PENDING',  '2026-12-01'),   /* pending BC                       */
    (2,  'BOOKING CONFIRMATION', 'N', 'PBC', 'PENDING',  '2026-12-01'),   /* pending BC confirmation          */
    (3,  'BOOKING CONFIRMATION', 'Y', 'PB',  'PENDING',  '2026-12-01'),   /* PB but cancelled  -> none        */
    (4,  'BOOKING CONFIRMATION', 'N', 'PT',  'PENDING',  '{$tomorrow}'),  /* tomorrow, PT (ready)             */
    (5,  'BOOKING CONFIRMATION', 'N', 'PBO', 'PENDING',  '{$tomorrow}'),  /* tomorrow, not ready (PBO)        */
    (6,  'BOOKING CONFIRMATION', 'Y', 'P',   'PENDING',  '{$tomorrow}'),  /* tomorrow but cancelled -> none   */
    (7,  'BOOKING CONFIRMATION', 'N', 'PBO', 'PENDING',  '{$today}'),     /* travels TODAY -> today card      */
    (8,  'BOOKING CONFIRMATION', 'N', 'PBO', 'PENDING',  '{$dayAfter}'),  /* day after tomorrow -> none       */
    (9,  'QUOTATION',            'N', 'PBO', 'PENDING',  '{$tomorrow}'),  /* quotation -> none                */
    (10, 'BOOKING CONFIRMATION', 'N', 'N',   'PENDING',  '{$tomorrow}'),  /* draft (Status=N) -> none         */
    (11, 'BOOKING CONFIRMATION', 'N', 'Y',   'PENDING',  '2026-06-01'),   /* completed, pending review        */
    (12, 'BOOKING CONFIRMATION', 'N', 'Y',   'COMPLETE', '2026-06-01'),   /* completed, review done -> none   */
    (13, 'BOOKING CONFIRMATION', 'Y', 'Y',   'PENDING',  '2026-06-01'),   /* pending review but cancelled     */
    (14, 'BOOKING CONFIRMATION', 'N', 'PT',  'PENDING',  '{$tomorrow}')   /* tomorrow, PT (ready)             */
");

// ---------------------------------------------------------------------------
// Card count queries (mirror Booking::ajax_summary_cards OP block).
// ---------------------------------------------------------------------------
$count = function($where) use ($pdo) {
    return (int)$pdo->query("SELECT COUNT(*) FROM booking WHERE {$where}")->fetchColumn();
};

$pending_bc              = $count("CancelStatus='N' AND Status='PB'");
$pending_bc_confirmation = $count("CancelStatus='N' AND Status='PBC'");
$travel_today            = $count("BookingConfirmationTitle='BOOKING CONFIRMATION' AND CancelStatus='N' AND Status!='N' AND StartDate='{$today}'");
$travel_tomorrow         = $count("BookingConfirmationTitle='BOOKING CONFIRMATION' AND CancelStatus='N' AND Status!='N' AND StartDate='{$tomorrow}'");
$travel_tomorrow_nr      = $count("BookingConfirmationTitle='BOOKING CONFIRMATION' AND CancelStatus='N' AND Status!='N' AND Status!='PT' AND StartDate='{$tomorrow}'");
$pending_review          = $count("BookingConfirmationTitle='BOOKING CONFIRMATION' AND CancelStatus='N' AND AfterSalesService='PENDING' AND Status='Y'");

// ---------------------------------------------------------------------------
// Drill-down parity: replicate the booking-list WHERE each card link produces
// (status= via booking_status_filter, plus travel_start_date / exclude_status /
// booking_confirmation_title applied by apply_booking_filters).
// ---------------------------------------------------------------------------
$ids = function($where) use ($pdo) {
    $sql = "SELECT BookingID FROM booking WHERE {$where} ORDER BY BookingID";
    return array_map('intval', $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN));
};

// status=PB  ->  CancelStatus='N' AND Status='PB'
$dd_pending_bc = $ids("CancelStatus='N' AND Status='PB'");
// status=PBC ->  CancelStatus='N' AND Status='PBC'
$dd_pending_bc_confirmation = $ids("CancelStatus='N' AND Status='PBC'");
// status=A + travel_start_date=today..today + title=BOOKING CONFIRMATION
$dd_travel_today = $ids(
    "(CancelStatus='N' AND Status!='N')"
    . " AND StartDate>='{$today}' AND StartDate<='{$today}'"
    . " AND BookingConfirmationTitle='BOOKING CONFIRMATION'"
);
// status=A + travel_start_date=tomorrow..tomorrow + title=BOOKING CONFIRMATION
$dd_travel_tomorrow = $ids(
    "(CancelStatus='N' AND Status!='N')"
    . " AND StartDate>='{$tomorrow}' AND StartDate<='{$tomorrow}'"
    . " AND BookingConfirmationTitle='BOOKING CONFIRMATION'"
);
// ... plus exclude_status=PT  ->  Status NOT IN ('PT')
$dd_travel_tomorrow_nr = $ids(
    "(CancelStatus='N' AND Status!='N')"
    . " AND StartDate>='{$tomorrow}' AND StartDate<='{$tomorrow}'"
    . " AND Status NOT IN ('PT')"
    . " AND BookingConfirmationTitle='BOOKING CONFIRMATION'"
);
// status=PR  ->  CancelStatus='N' AND AfterSalesService='PENDING' AND Status='Y'
$dd_pending_review = $ids("CancelStatus='N' AND AfterSalesService='PENDING' AND Status='Y'");

// ---------------------------------------------------------------------------
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

// Card counts.
assert_eq('pending BC count',                1, $pending_bc);
assert_eq('pending BC confirmation count',   1, $pending_bc_confirmation);
assert_eq('travelling today count',          1, $travel_today);
assert_eq('travelling tomorrow count',       3, $travel_tomorrow);
assert_eq('travelling tomorrow NOT ready',   1, $travel_tomorrow_nr);
assert_eq('pending review count',            1, $pending_review);

// Drill-down BC sets — must align with the counts above.
assert_eq('pending BC drill-down',              array(1),       $dd_pending_bc);
assert_eq('pending BC confirmation drill-down', array(2),       $dd_pending_bc_confirmation);
assert_eq('travelling today drill-down',        array(7),        $dd_travel_today);
assert_eq('travelling tomorrow drill-down',     array(4, 5, 14),$dd_travel_tomorrow);
assert_eq('travelling tomorrow NR drill-down',  array(5),       $dd_travel_tomorrow_nr);
assert_eq('pending review drill-down',          array(11),      $dd_pending_review);

// Count vs drill-down agreement (the parity contract).
assert_eq('pending BC parity',              $pending_bc,              count($dd_pending_bc));
assert_eq('pending BC confirmation parity', $pending_bc_confirmation, count($dd_pending_bc_confirmation));
assert_eq('travelling today parity',        $travel_today,            count($dd_travel_today));
assert_eq('travelling tomorrow parity',     $travel_tomorrow,         count($dd_travel_tomorrow));
assert_eq('travelling tomorrow NR parity',  $travel_tomorrow_nr,      count($dd_travel_tomorrow_nr));
assert_eq('pending review parity',          $pending_review,          count($dd_pending_review));

// ---------------------------------------------------------------------------
// Source-code locks: model wires the two new generic filters; controller
// builds the five cards with the exact drill-down link params; view renders
// each card with its element ids and marks card 4 as a red card.
// ---------------------------------------------------------------------------
$model = file_get_contents(__DIR__ . '/../../application/models/Booking_Model.php');
assert_true('model handles travel_start_date filter',
    strpos($model, "input->get('travel_start_date')") !== false);
assert_true('model handles exclude_status filter',
    strpos($model, "input->get('exclude_status')") !== false
    && strpos($model, 'where_not_in') !== false);

$controller = file_get_contents(__DIR__ . '/../../application/controllers/Booking.php');
assert_true('controller builds pending_bc_op card',
    strpos($controller, "\$cards['pending_bc_op']") !== false);
assert_true('controller builds pending_bc_confirmation_op card',
    strpos($controller, "\$cards['pending_bc_confirmation_op']") !== false);
assert_true('controller builds travel_today_op card',
    strpos($controller, "\$cards['travel_today_op']") !== false);
assert_true('controller builds travel_tomorrow_op card',
    strpos($controller, "\$cards['travel_tomorrow_op']") !== false);
assert_true('controller builds travel_tomorrow_not_ready_op card',
    strpos($controller, "\$cards['travel_tomorrow_not_ready_op']") !== false);
assert_true('controller builds pending_review_op card',
    strpos($controller, "\$cards['pending_review_op']") !== false);

// Card 4's link must carry travel_start_date + status=A + exclude_status=PT + title.
$start = strpos($controller, "\$cards['travel_tomorrow_not_ready_op']");
$link_block = $start !== false ? substr($controller, $start, 500) : '';
assert_true('travel_tomorrow_not_ready link carries travel_start_date',
    strpos($link_block, "'travel_start_date'") !== false);
assert_true('travel_tomorrow_not_ready link carries status=A',
    strpos($link_block, "'status'") !== false && strpos($link_block, "'A'") !== false);
assert_true('travel_tomorrow_not_ready link carries exclude_status=PT',
    strpos($link_block, "'exclude_status'") !== false && strpos($link_block, "'PT'") !== false);
assert_true('travel_tomorrow_not_ready link constrains booking_confirmation_title',
    strpos($link_block, "'booking_confirmation_title'") !== false);

// Card 3's link must carry travel_start_date + status=A + title but NOT exclude_status.
$start3 = strpos($controller, "\$cards['travel_tomorrow_op']");
$link_block3 = $start3 !== false ? substr($controller, $start3, 500) : '';
assert_true('travel_tomorrow link carries travel_start_date',
    strpos($link_block3, "'travel_start_date'") !== false);
assert_true('travel_tomorrow link carries status=A',
    strpos($link_block3, "'status'") !== false && strpos($link_block3, "'A'") !== false);

// Travelling Today link mirrors card 3: travel_start_date + status=A + title.
$startTd = strpos($controller, "\$cards['travel_today_op']");
$link_blockTd = $startTd !== false ? substr($controller, $startTd, 500) : '';
assert_true('travel_today link carries travel_start_date',
    strpos($link_blockTd, "'travel_start_date'") !== false);
assert_true('travel_today link carries status=A',
    strpos($link_blockTd, "'status'") !== false && strpos($link_blockTd, "'A'") !== false);

// Pending BC / Confirmation / Review links use the shared status filter.
$startPb = strpos($controller, "\$cards['pending_bc_op']");
$lb = substr($controller, $startPb, 300);
assert_true('pending_bc_op link uses status=PB', strpos($lb, "'status' => 'PB'") !== false);
$startPbc = strpos($controller, "\$cards['pending_bc_confirmation_op']");
$lbc = substr($controller, $startPbc, 300);
assert_true('pending_bc_confirmation_op link uses status=PBC', strpos($lbc, "'status' => 'PBC'") !== false);
$startPr = strpos($controller, "\$cards['pending_review_op']");
$lpr = substr($controller, $startPr, 300);
assert_true('pending_review_op link uses status=PR', strpos($lpr, "'status' => 'PR'") !== false);

$view = file_get_contents(__DIR__ . '/../../application/views/booking/_summary_cards.php');
foreach (array(
    'sc-pending-bc-op-count', 'sc-pending-bc-op-link',
    'sc-pending-bc-confirmation-op-count', 'sc-pending-bc-confirmation-op-link',
    'sc-travel-today-op-count', 'sc-travel-today-op-link',
    'sc-travel-tomorrow-op-count', 'sc-travel-tomorrow-op-link',
    'sc-travel-tomorrow-not-ready-op-count', 'sc-travel-tomorrow-not-ready-op-link',
    'sc-pending-review-op-count', 'sc-pending-review-op-link',
) as $id) {
    assert_true("view renders #{$id}", strpos($view, $id) !== false);
}
assert_true('view marks the not-ready card as a red card',
    strpos($view, 'summary-card-red') !== false);
assert_true('view wires the five OP queue cards in JS',
    strpos($view, 'c.travel_tomorrow_not_ready_op') !== false
    && strpos($view, 'c.pending_review_op') !== false);
assert_true('view wires the travel_today_op card in JS',
    strpos($view, 'c.travel_today_op') !== false);

// The booking list forwards only an allowlist of URL params into its DataTables
// AJAX (ajax_list) and summary-totals AJAX (ajax_summary). Both new drill-down
// params MUST be on those lists or the list silently drops them and shows every
// active BC instead of just tomorrow's — the count/drill-down disagree.
$list = file_get_contents(__DIR__ . '/../../application/views/booking/index.php');
assert_true('booking list forwards travel_start_date to AJAX (both allowlists)',
    substr_count($list, "'travel_start_date'") >= 2);
assert_true('booking list forwards exclude_status to AJAX (both allowlists)',
    substr_count($list, "'exclude_status'") >= 2);

echo "\nAll assertions passed.\n";
