<?php
/**
 * Run with: php tests/helpers/CustomerCodeHelperTest.php
 *
 * Locks next_customer_code() — the generator behind customer.CustomerCode
 * (AutoCount debtor account number, e.g. "303-T126"). Regression target:
 * two customers must NEVER be handed the same code, which previously caused
 * AutoCount sync to fail with `AccNo "303-T126" exists in Chart of Account`.
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

echo "next_customer_code()\n";

// Empty / whitespace name yields no code.
check('empty name -> null', null, next_customer_code('', []));
check('whitespace name -> null', null, next_customer_code('   ', []));

// First customer of a fresh series.
check('fresh series starts at 001', '303-T001', next_customer_code('Tang Li Choo', []));

// Lowercase first letter is upper-cased.
check('lowercase name upper-cased', '303-T001', next_customer_code('tang li choo', []));

// Non-alphabetic first character is kept verbatim.
check('numeric first char', '303-1001', next_customer_code('123 Travel', []));

// Steps past the highest existing number in the series.
$used = [];
for ($i = 1; $i <= 125; $i++) {
    $used[] = '303-T' . sprintf('%03d', $i);
}
check('next after 125 used -> 126', '303-T126', next_customer_code('Tang Li Choo', $used));

// The reported production collision: 303-T126 already lives in AutoCount/locally,
// so the NEXT customer must NOT be handed 303-T126 again.
$used[] = '303-T126';
check('303-T126 taken -> 127', '303-T127', next_customer_code('Tan Ah Kow', $used));

// Respects an out-of-band higher code (e.g. one pulled back from AutoCount via
// docNo) even when lower numbers are missing locally — never re-issues below max.
check('honours sparse higher code', '303-S151', next_customer_code('Soh', ['303-S150']));

// Only the matching prefix/letter influences the number — other series ignored.
check('other letters ignored', '303-T001', next_customer_code('Tang', ['303-S999', '303-A042']));

// Prefix rolls over from 303 to 304 once 303-X999 is reached.
$full = [];
for ($i = 1; $i <= 999; $i++) {
    $full[] = '303-T' . sprintf('%03d', $i);
}
check('303 series full -> 304', '304-T001', next_customer_code('Tang', $full));

// Garbage tail in a stored code does not corrupt the max calculation.
check('non-numeric tail ignored', '303-T001', next_customer_code('Tang', ['303-Tabc']));

echo "\n$passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);
