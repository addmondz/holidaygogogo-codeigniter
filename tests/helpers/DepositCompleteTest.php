<?php
/**
 * Run with: php tests/helpers/DepositCompleteTest.php
 *
 * Verifies compute_deposit_complete() correctly classifies deposit-flow
 * and non-deposit-flow bookings. Regression coverage for BC-2601-0153,
 * where a deposit-flow booking with DepositPercentage=0 + DepositFixedAmount=0
 * was wrongly reported as "Deposit Received".
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require_once __DIR__ . '/../../application/helpers/booking_flow_helper.php';

$cases = [
    // [label, deposit_total, total_paid, has_deposit_deadline, full_paid, expected]
    ['BC-2601-0153 regression: deadline set, deposit_total=0, nothing paid', 0,    0,    true,  false, false],
    ['Deposit fully paid',                                                     500,  500,  true,  false, true],
    ['Deposit overpaid',                                                       500,  600,  true,  false, true],
    ['Deposit partially paid',                                                 500,  200,  true,  false, false],
    ['Deposit deadline set, nothing configured, nothing paid',                 0,    0,    true,  false, false],
    ['Deposit deadline set, deposit_total=0, full payment received',           0,    1000, true,  true,  true],
    ['No deposit deadline (full-payment booking) — deposit step is skipped',   0,    0,    false, false, true],
    ['No deposit deadline, full payment received',                             0,    1000, false, true,  true],
    ['Deposit deadline set, partial paid, full_paid flag overrides',           500,  300,  true,  true,  true],
];

$failed = 0;
foreach ($cases as $case) {
    [$label, $deposit_total, $total_paid, $has_deadline, $full_paid, $expected] = $case;
    $actual = compute_deposit_complete($deposit_total, $total_paid, $has_deadline, $full_paid);
    $ok = ($actual === $expected);
    echo ($ok ? '  PASS  ' : '  FAIL  ') . $label
        . ' (got ' . var_export($actual, true) . ', expected ' . var_export($expected, true) . ')'
        . PHP_EOL;
    if (!$ok) {
        $failed++;
    }
}

echo PHP_EOL . ($failed === 0 ? "All assertions passed.\n" : "$failed assertion(s) failed.\n");
exit($failed === 0 ? 0 : 1);
