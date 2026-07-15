<?php
/**
 * Run with: php tests/helpers/CustomerCodeEditPermissionTest.php
 *
 * Locks who may CHANGE an existing customer.CustomerCode (AutoCount debtor
 * account number). Business rule: only ERNIDA (Finance, AdminID 7) may edit a
 * code that is already set. Everyone may still set a code the FIRST time (when
 * the stored code is blank) — that behaviour is unchanged.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}
require_once __DIR__ . '/../../application/helpers/customer_code_helper.php';

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

echo "can_edit_customer_code()\n";
check('Ernida (7) may edit', true, can_edit_customer_code(7));
check('Ernida as string may edit', true, can_edit_customer_code('7'));
check('owner Simon (1) may not', false, can_edit_customer_code(1));
check('random agent (22) may not', false, can_edit_customer_code(22));
check('null admin may not', false, can_edit_customer_code(null));

echo "customer_code_change_allowed()\n";
// No change at all is always fine.
check('unchanged code allowed for anyone', true, customer_code_change_allowed(22, '303-T126', '303-T126'));
check('whitespace-only diff = unchanged', true, customer_code_change_allowed(22, '303-T126', ' 303-T126 '));

// First-time set (stored blank) allowed for everyone.
check('set from blank allowed for agent', true, customer_code_change_allowed(22, '', '303-T126'));
check('set from null allowed for agent', true, customer_code_change_allowed(22, null, '303-T126'));

// Overwriting an existing code: Ernida only.
check('agent cannot overwrite existing', false, customer_code_change_allowed(22, '303-T126', '303-T127'));
check('owner cannot overwrite existing', false, customer_code_change_allowed(1, '303-T126', '303-T127'));
check('Ernida can overwrite existing', true, customer_code_change_allowed(7, '303-T126', '303-T127'));
check('Ernida can clear existing', true, customer_code_change_allowed(7, '303-T126', ''));
check('agent cannot clear existing', false, customer_code_change_allowed(22, '303-T126', ''));

echo "\n$passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);
