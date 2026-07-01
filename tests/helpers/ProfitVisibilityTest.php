<?php
/**
 * Run with: php tests/helpers/ProfitVisibilityTest.php
 *
 * Locks admin_hides_profit(): Net Profit / Net Profit Margin are hidden from the
 * front-line roles SALES AGENT (20) and MARKETING (60) on the booking list, the
 * summary total, and the Excel export. Every other role keeps seeing profit.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require __DIR__ . '/../../application/helpers/profit_visibility_helper.php';

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

// ---- Hidden for Sales Agent (20) and Marketing (60) -------------------------
assert_eq('sales agent(20) hides profit',   true,  admin_hides_profit(20));
assert_eq('marketing(60) hides profit',      true,  admin_hides_profit(60));

// ---- Visible for every profit-trusted role ----------------------------------
assert_eq('owner(10) sees profit',           false, admin_hides_profit(10));
assert_eq('team lead(25) sees profit',       false, admin_hides_profit(25));
assert_eq('finance(30) sees profit',         false, admin_hides_profit(30));
assert_eq('op(40) sees profit',              false, admin_hides_profit(40));
assert_eq('op team lead(45) sees profit',    false, admin_hides_profit(45));
assert_eq('tc(50) sees profit',              false, admin_hides_profit(50));

// ---- Robustness: string levels + unknown ------------------------------------
assert_eq('string "20" hides profit',        true,  admin_hides_profit('20'));
assert_eq('string "60" hides profit',        true,  admin_hides_profit('60'));
assert_eq('string "10" sees profit',         false, admin_hides_profit('10'));
assert_eq('unknown level sees profit',       false, admin_hides_profit(99));

echo "\nAll assertions passed.\n";
