<?php
/**
 * Run with: php tests/helpers/PartialRefundTreatedAsCancelledTest.php
 *
 * Bug: BC-2602-0031 had "Cancel With Partial Refund" clicked, yet its status
 * still showed PAYMENT OVERDUE and it stayed on the BC list. Root cause: the
 * partial-refund action only wrote booking.PartialRefund='Y'; every status
 * derivation and list filter keys off CancelStatus and ignored PartialRefund.
 *
 * Decision: treat "Cancel With Partial Refund" exactly as CANCELLED, i.e. the
 * action must ALSO set booking.CancelStatus. This test locks two things:
 *   1. The model writes CancelStatus alongside PartialRefund (apply -> 'Y',
 *      undo -> the same N/Y value as PartialRefund).
 *   2. With CancelStatus='Y' set, the row is excluded from the active / default
 *      / PO list filters, included in the cancelled filter, and renders as
 *      CANCELLED (not PAYMENT OVERDUE) via display_booking_status().
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}
require __DIR__ . '/../../application/helpers/booking_flow_helper.php';

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

// ---- 1) model-write contract ----------------------------------------------
// The partial-refund methods must keep CancelStatus in lockstep with
// PartialRefund so all existing CancelStatus-based logic applies.
$model_src = file_get_contents(__DIR__ . '/../../application/models/Booking_Model.php');

function method_body($src, $name) {
    $pos = strpos($src, "function {$name}(");
    if ($pos === false) { return ''; }
    $brace = strpos($src, '{', $pos);
    $depth = 0; $i = $brace; $len = strlen($src);
    for (; $i < $len; $i++) {
        if ($src[$i] === '{') { $depth++; }
        elseif ($src[$i] === '}') { $depth--; if ($depth === 0) { break; } }
    }
    return substr($src, $brace, $i - $brace + 1);
}

$apply = method_body($model_src, 'Update_Partial_Refund_Status_With_Reason');
assert_eq('apply sets PartialRefund=Y', true, strpos($apply, "'PartialRefund' => 'Y'") !== false);
assert_eq('apply sets CancelStatus=Y',  true, strpos($apply, "'CancelStatus' => 'Y'") !== false);

$undo = method_body($model_src, 'Update_Partial_Refund_Status');
assert_eq('undo writes PartialRefund', true, strpos($undo, "'PartialRefund'") !== false);
assert_eq('undo also writes CancelStatus', true, strpos($undo, "'CancelStatus'") !== false);
// Undo must mirror the toggle value, not hard-code 'Y', so Undo clears it.
assert_eq('undo CancelStatus mirrors toggle', true,
    strpos($undo, "'CancelStatus' => \$this->input->get('new_partial_refund_status')") !== false);

// ---- 2) resulting row behaves exactly as cancelled ------------------------
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE booking (
    BookingID INTEGER PRIMARY KEY, CancelStatus TEXT, AfterSalesService TEXT,
    Status TEXT, FullPaymentDeadline TEXT, DepositDeadline TEXT, PartialRefund TEXT
)");
$pdo->exec("INSERT INTO booking VALUES
    /* row 1: overdue P booking, then Cancel-With-Partial-Refund applied
       (CancelStatus flipped to 'Y' by the fix) */
    (1, 'Y', 'PENDING', 'P', '2026-06-14', '2026-06-14', 'Y'),
    /* row 2: a normal active overdue P booking (control) */
    (2, 'N', 'PENDING', 'P', '2026-06-14', '2026-06-14', 'N')");

$count = function ($where) use ($pdo) {
    return (int) $pdo->query("SELECT COUNT(*) FROM booking WHERE {$where}")->fetchColumn();
};
$has = function ($where, $id) use ($pdo) {
    return (int) $pdo->query("SELECT COUNT(*) FROM booking WHERE ({$where}) AND BookingID={$id}")->fetchColumn() === 1;
};

// Active list filter (status=A): CancelStatus='N' AND Status!='N'
$active = "CancelStatus='N' AND Status!='N'";
assert_eq('refunded row excluded from active list', false, $has($active, 1));
assert_eq('control row stays on active list',        true,  $has($active, 2));

// Default list: CancelStatus='N' AND AfterSalesService='PENDING'
$default = "CancelStatus='N' AND AfterSalesService='PENDING'";
assert_eq('refunded row excluded from default list', false, $has($default, 1));

// PO drill-down: CancelStatus='N' AND overdue predicate
$po = "CancelStatus='N' AND ((FullPaymentDeadline < '2026-06-26' AND Status IN ('P','PP'))"
    . " OR (DepositDeadline < '2026-06-26' AND Status='P'))";
assert_eq('refunded row excluded from PO list', false, $has($po, 1));
assert_eq('control row shows on PO list',        true,  $has($po, 2));

// Cancelled filter (status=C): CancelStatus='Y' AND Status!='N'
$cancelled = "CancelStatus='Y' AND Status!='N'";
assert_eq('refunded row appears under cancelled', true, $has($cancelled, 1));

// ---- 3) status text renders CANCELLED, not PAYMENT OVERDUE ----------------
$refunded = (object) array(
    'Status' => 'P', 'CancelStatus' => 'Y', 'PartialRefund' => 'Y',
    'LockStatus' => 'N', 'AfterSalesService' => 'PENDING',
    'DepositDeadline' => '2026-06-14', 'FullPaymentDeadline' => '2026-06-14',
    'balance_due' => 500.0, 'deposit_complete' => false,
);
$info = display_booking_status($refunded);
assert_eq('refunded status text is CANCELLED', 'CANCELLED', $info['status_text']);

echo "\nAll assertions passed.\n";
