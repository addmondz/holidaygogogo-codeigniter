<?php
/**
 * Run with: php tests/helpers/OpCardsTc1ScopeTest.php
 *
 * Locks the rule: OP / OP TEAM LEAD (level 40/45) only see BCs where they are
 * the TC1 (booking.SalesAgent — the "Sales Agent" column in the booking
 * listing) across the summary cards AND the booking listing — with ONE
 * exception, the "Pending BC" card (status PB), which stays team-wide because
 * pending BCs have no TC1 assigned yet.
 *
 * Two coordinated guarantees:
 *   - Card counts (Booking::ajax_summary_cards, $is_op block) carry
 *     `AND booking.SalesAgent = ?` everywhere EXCEPT pending_bc_op.
 *   - The booking listing (Booking_Model::apply_booking_filters) restricts
 *     level 40/45 to `SalesAgent = admin_id`, bypassed when status=PB, so each
 *     drill-down agrees with its card (same parity discipline as
 *     CLAUDE memory feedback_card_drilldown_status).
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
    SalesAgent INTEGER
)");

$ME    = 100;  // logged-in OP user (as TC1)
$OTHER = 200;  // another agent

$tomorrow = '2026-06-25';

// Cols: id, title, CancelStatus, Status, AfterSalesService, StartDate, SalesAgent
$pdo->exec("INSERT INTO booking VALUES
    (1,  'BOOKING CONFIRMATION', 'N', 'PB',  'PENDING', '2026-12-01', {$ME}),    /* PB, mine                 */
    (2,  'BOOKING CONFIRMATION', 'N', 'PB',  'PENDING', '2026-12-01', {$OTHER}), /* PB, other agent          */
    (3,  'BOOKING CONFIRMATION', 'N', 'PB',  'PENDING', '2026-12-01', NULL),     /* PB, unassigned (no TC1)  */
    (4,  'BOOKING CONFIRMATION', 'N', 'PBC', 'PENDING', '2026-12-01', {$ME}),    /* PBC, mine                */
    (5,  'BOOKING CONFIRMATION', 'N', 'PBC', 'PENDING', '2026-12-01', {$OTHER}), /* PBC, other agent         */
    (6,  'BOOKING CONFIRMATION', 'N', 'PBO', 'PENDING', '{$tomorrow}', {$ME}),   /* tomorrow, mine           */
    (7,  'BOOKING CONFIRMATION', 'N', 'PBO', 'PENDING', '{$tomorrow}', {$OTHER}) /* tomorrow, other agent    */
");

$count = function($where) use ($pdo) {
    return (int)$pdo->query("SELECT COUNT(*) FROM booking WHERE {$where}")->fetchColumn();
};

// Pending BC card: UNTOUCHED — team-wide, all PB regardless of SalesAgent.
$pending_bc = $count("CancelStatus='N' AND Status='PB'");

// Every other OP card: scoped to the logged-in user's TC1 slot.
$pending_bc_confirmation = $count("CancelStatus='N' AND Status='PBC' AND SalesAgent={$ME}");
$travel_tomorrow         = $count("BookingConfirmationTitle='BOOKING CONFIRMATION' AND CancelStatus='N' AND Status!='N' AND StartDate='{$tomorrow}' AND SalesAgent={$ME}");

// Listing parity: level 40/45 drill-down adds SalesAgent=ME, except status=PB.
$dd_pending_bc_confirmation = $count("CancelStatus='N' AND Status='PBC' AND SalesAgent={$ME}");
$dd_pending_bc              = $count("CancelStatus='N' AND Status='PB'"); // status=PB bypass -> team-wide

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

// Behavioural: Pending BC stays team-wide; everything else is TC1-scoped.
assert_eq('pending BC team-wide (all PB incl. unassigned)', 3, $pending_bc);
assert_eq('pending BC confirmation scoped to me',           1, $pending_bc_confirmation);
assert_eq('travelling tomorrow scoped to me',               1, $travel_tomorrow);

// Parity: card count == drill-down list count.
assert_eq('pending BC confirmation parity', $pending_bc_confirmation, $dd_pending_bc_confirmation);
assert_eq('pending BC parity (team-wide)',  $pending_bc,              $dd_pending_bc);

// ---------------------------------------------------------------------------
// Source-code locks.
// ---------------------------------------------------------------------------
$model = file_get_contents(__DIR__ . '/../../application/models/Booking_Model.php');
// The OP listing scope: a level 40/45 branch that restricts SalesAgent and
// bypasses on status=PB.
assert_true('model scopes level 40/45 listing to SalesAgent',
    preg_match('/\[40,\s*45\][\s\S]{0,260}?SalesAgent/', $model) === 1);
assert_true('model bypasses OP listing scope when status=PB',
    preg_match('/\[40,\s*45\][\s\S]{0,260}?(status[\'"\)\s]*[\s\S]{0,40}?PB|PB[\s\S]{0,40}?status)/', $model) === 1);

$controller = file_get_contents(__DIR__ . '/../../application/controllers/Booking.php');
// Isolate the OP block so the locks below don't accidentally match TC-block code.
$op_start = strpos($controller, 'if($is_op) {');
$fin_start = strpos($controller, 'if($is_finance) {');
assert_true('controller has an $is_op block', $op_start !== false && $fin_start !== false && $fin_start > $op_start);
$op_block = substr($controller, $op_start, $fin_start - $op_start);

// pending_bc_op stays team-wide: its own query must NOT carry a SalesAgent filter.
$pb_pos = strpos($op_block, "\$cards['pending_bc_op']");
$pb_query = $pb_pos !== false ? substr($op_block, max(0, $pb_pos - 220), 220) : '';
assert_true('pending_bc_op query stays team-wide (no SalesAgent filter)',
    strpos($pb_query, 'SalesAgent') === false);

// The scoped cards must reference SalesAgent in the OP block.
foreach (array(
    "pending_bc_confirmation_op", "travel_tomorrow_op", "travel_tomorrow_not_ready_op",
    "pending_review_op", "gl_submitted", "insurance_pending", "ferry_pending",
    "customer_payment_due_soon", "supplier_due_soon", "checklist_payout_due_soon",
) as $card) {
    $pos = strpos($op_block, "\$cards['{$card}']");
    assert_true("controller builds {$card} card", $pos !== false);
}
assert_true('OP block scopes queries by booking.SalesAgent',
    substr_count($op_block, 'SalesAgent') >= 10);

// Conversion helper accepts an optional SalesAgent scope.
$helper = file_get_contents(__DIR__ . '/../../application/helpers/submitted_payment_response_helper.php');
$cs_pos = strpos($helper, 'function submitted_payment_conversion_summary_sql');
$cs_sig = $cs_pos !== false ? substr($helper, $cs_pos, 120) : '';
assert_true('conversion summary helper takes a sales-agent scope param',
    strpos($cs_sig, '$sales_agent_only') !== false);
assert_true('conversion summary helper appends b.SalesAgent filter',
    preg_match('/submitted_payment_conversion_summary_sql[\s\S]{0,900}?b\.SalesAgent\s*=\s*\?/', $helper) === 1);

echo "\nAll assertions passed.\n";
