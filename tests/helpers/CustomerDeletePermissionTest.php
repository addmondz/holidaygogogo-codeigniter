<?php
/**
 * Run with: php tests/helpers/CustomerDeletePermissionTest.php
 *
 * Locks WHO may delete (soft-delete) a customer master. Business rule:
 *   - OWNER (level 10) always may.
 *   - ERNIDA (Finance, AdminID 7) was additionally granted delete rights
 *     (2026-08-03) so Finance can retire duplicate/bad customer masters.
 * Everyone else may not, regardless of their level.
 *
 * Pure helper — no session/DB — so the Delete() controller guard and both
 * listing views (customer + guests) can share one source of truth.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}
require_once __DIR__ . '/../../application/helpers/leads_customer_access_helper.php';

$failed = 0;
$passed = 0;
function check($label, $expected, $actual)
{
    global $failed, $passed;
    if ($expected === $actual) {
        $passed++;
        echo "  PASS: $label\n";
    } else {
        $failed++;
        echo "  FAIL: $label\n";
        echo "        expected: " . var_export($expected, true) . "\n";
        echo "        actual:   " . var_export($actual, true) . "\n";
    }
}

echo "can_delete_customer()\n";
// Owner may, whatever their admin id.
check('owner (level 10) may delete', true, can_delete_customer(10, 1));
check('owner level as string may delete', true, can_delete_customer('10', 1));

// Ernida (AdminID 7) may, regardless of her non-owner level (30 = Finance).
check('Ernida (30/7) may delete', true, can_delete_customer(30, 7));
check('Ernida with string admin id may', true, can_delete_customer(30, '7'));
check('Ernida even at some other level may', true, can_delete_customer(20, 7));

// Nobody else may.
check('finance non-Ernida (30/9) may not', false, can_delete_customer(30, 9));
check('sales agent (20/22) may not', false, can_delete_customer(20, 22));
check('null admin id, non-owner may not', false, can_delete_customer(20, null));
check('empty admin id, non-owner may not', false, can_delete_customer(20, ''));

echo "\n$passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);
