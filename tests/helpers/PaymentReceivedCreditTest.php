<?php
/**
 * Run with: php tests/helpers/PaymentReceivedCreditTest.php
 *
 * Contract: a money-in "AGENT COMMISSION FROM SUPPLIER" settles a booking ONLY
 * when the booking has no customer payment of its own (a commission-fee booking
 * like BC-2608-0141). On a NORMAL booking the commission is extra income on top
 * of the customer's payment, so it must NOT reduce Received / Outstanding, nor
 * mark the booking paid. This guard keeps the 158 live mixed bookings unchanged.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require_once __DIR__ . '/../../application/helpers/payment_type_helper.php';

$failures = 0;
function check($label, $cond) {
    global $failures;
    if ($cond) { echo "  PASS: {$label}\n"; }
    else { echo "  FAIL: {$label}\n"; $failures++; }
}

$obj = function ($type, $credit, $debit, $status = 'Y') {
    return (object) array('Type' => $type, 'Credit' => $credit, 'Debit' => $debit, 'Status' => $status);
};

// --- 1. Received set includes the money-in commission type ------------------
$types = payment_received_credit_types();
check('DEPOSIT counts as received', in_array('DEPOSIT', $types, true));
check('AGENT COMMISSION FROM SUPPLIER is in the received set', in_array('AGENT COMMISSION FROM SUPPLIER', $types, true));

// --- 2. Commission-fee booking: only a 600 commission credit ----------------
$only_commission = array($obj('AGENT COMMISSION FROM SUPPLIER', 600.00, 0.00));
check('commission-only: counted as received (600)',
    payment_received_credit_total($only_commission) === 600.0);
check('commission-only: settled credit is 600',
    payment_approved_customer_credit($only_commission) === 600.0);
$net = 600.0;
check('commission-only: outstanding is zero',
    ($net - payment_approved_customer_credit($only_commission)) === 0.0);

// --- 3. NORMAL booking (the 158 live rows): commission must NOT count --------
// Customer paid 600 (full), plus a 200 supplier commission sitting on top.
$normal = array(
    $obj('FULL', 600.00, 0.00),
    $obj('AGENT COMMISSION FROM SUPPLIER', 200.00, 0.00),
);
check('normal: received excludes the commission (600, not 800)',
    payment_received_credit_total($normal) === 600.0);
check('normal: settled credit excludes the commission (600)',
    payment_approved_customer_credit($normal) === 600.0);
check('normal: outstanding stays 0 (not negative)',
    (600.0 - payment_approved_customer_credit($normal)) === 0.0);

// Partial customer payment + commission: commission still ignored.
$partial = array(
    $obj('DEPOSIT', 200.00, 0.00),
    $obj('AGENT COMMISSION FROM SUPPLIER', 500.00, 0.00),
);
check('normal partial: settled = 200 (commission ignored, not 700)',
    payment_approved_customer_credit($partial) === 200.0);

// --- 4. has_customer_credit guard -------------------------------------------
check('customer credit present when a DEPOSIT exists',
    payment_rows_have_customer_credit($partial) === true);
check('no customer credit when only a commission exists',
    payment_rows_have_customer_credit($only_commission) === false);

// --- 5. Status/approval filters: pending & refunds --------------------------
$pending = array($obj('AGENT COMMISSION FROM SUPPLIER', 600.00, 0.00, 'P'));
check('pending commission does not settle (Status != Y)',
    payment_approved_customer_credit($pending) === 0.0);

$with_refund = array(
    $obj('DEPOSIT', 300.00, 0.00),
    $obj('CUSTOMER REFUND', 0.00, 50.00),
);
check('received subtracts a customer refund (300 - 50 = 250)',
    payment_received_credit_total($with_refund) === 250.0);
check('settled credit ignores refund debit (300)',
    payment_approved_customer_credit($with_refund) === 300.0);

// --- 6. Money-OUT commission (Credit 0) contributes nothing -----------------
$payout = array($obj('AGENT COMMISSION FROM SUPPLIER', 0.00, 500.00));
check('money-out commission contributes 0',
    payment_approved_customer_credit($payout) === 0.0);

// --- 7. SQL mirror stays in lock-step with the PHP guard --------------------
$sql = booking_settled_credit_sql();
check('SQL adds commission only when customer credit = 0 (CASE WHEN ... = 0)',
    strpos($sql, "= 0 THEN") !== false);
check('SQL customer branch excludes the commission type',
    strpos($sql, "!= 'AGENT COMMISSION FROM SUPPLIER'") !== false);

echo "\n" . ($failures === 0 ? "ALL TESTS PASSED\n" : "{$failures} TEST(S) FAILED\n");
exit($failures === 0 ? 0 : 1);
