<?php
/**
 * Run with: php tests/helpers/PaymentReceivedCreditTest.php
 *
 * Contract: "Received (RM)" and "Customer Outstanding" must count a money-in
 * "AGENT COMMISSION FROM SUPPLIER" (Credit > 0), same as the "In (RM)" /
 * "Total Payment In" totals already do. Bug it locks: a commission-fee booking
 * whose only payment was that credit used to show Received 0 / Outstanding =
 * full NetTotal because the type was filtered out of the received set.
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

$obj = function ($type, $credit, $debit) {
    return (object) array('Type' => $type, 'Credit' => $credit, 'Debit' => $debit);
};

// --- 1. Received set includes the money-in commission type ------------------
$types = payment_received_credit_types();
check('DEPOSIT counts as received', in_array('DEPOSIT', $types, true));
check('FULL counts as received', in_array('FULL', $types, true));
check('ADDITIONAL PAYMENT counts as received', in_array('ADDITIONAL PAYMENT', $types, true));
check('CUSTOMER REFUND is in the set (subtracts)', in_array('CUSTOMER REFUND', $types, true));
check('AGENT COMMISSION FROM SUPPLIER counts as received', in_array('AGENT COMMISSION FROM SUPPLIER', $types, true));
check('unrelated payout type is NOT received', !in_array('SUPPLIER PAYMENT (FULL)', $types, true));

// --- 2. The bug scenario: commission-fee booking, only a 600 credit ---------
$only_commission = array($obj('AGENT COMMISSION FROM SUPPLIER', 600.00, 0.00));
check('commission credit is counted as received (600)',
    payment_received_credit_total($only_commission) === 600.0);

$net_total = 600.0;
check('outstanding is zero once the commission is received',
    ($net_total - payment_received_credit_total($only_commission)) === 0.0);

// --- 3. Mixed rows still net correctly --------------------------------------
$mixed = array(
    $obj('DEPOSIT', 200.00, 0.00),
    $obj('AGENT COMMISSION FROM SUPPLIER', 100.00, 0.00),
    $obj('CUSTOMER REFUND', 0.00, 50.00),   // gives money back -> subtracts
);
check('mixed received total = 200 + 100 - 50 = 250',
    payment_received_credit_total($mixed) === 250.0);

// --- 4. Money-OUT commission (Credit 0) adds nothing ------------------------
$payout = array($obj('AGENT COMMISSION FROM SUPPLIER', 0.00, 500.00));
check('money-out commission contributes 0 to received',
    payment_received_credit_total($payout) === 0.0);

// --- 5. Array rows work too (defensive) -------------------------------------
$as_array = array(array('Type' => 'FULL', 'Credit' => 300.00, 'Debit' => 0.00));
check('array-shaped rows are supported',
    payment_received_credit_total($as_array) === 300.0);

echo "\n" . ($failures === 0 ? "ALL TESTS PASSED\n" : "{$failures} TEST(S) FAILED\n");
exit($failures === 0 ? 0 : 1);
