<?php
/**
 * Run with: php tests/helpers/LcCustomerLookupMethodTest.php
 *
 * Locks lc_customer_lookup_method(): the pure whitelist that lets the Customer
 * controller constructor skip the lc_can_view('customer') page gate for the
 * lightweight booking-time lookup endpoints (customer type-ahead search +
 * duplicate-phone check). Without this, a sales agent who lacks Customer-module
 * view access gets their /customer/search AJAX bounced to /Booking, so the
 * booking form's customer dropdown silently shows nothing.
 *
 * Runs without a DB or CI — the helper function it tests is pure.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}
require_once __DIR__ . '/../../application/helpers/leads_customer_access_helper.php';

$failures = 0;
function check($label, $expected, $actual) {
    global $failures;
    if ($expected === $actual) {
        echo "PASS  {$label}\n";
    } else {
        $failures++;
        echo "FAIL  {$label}\n";
        echo "      expected: " . json_encode($expected) . "\n";
        echo "      actual:   " . json_encode($actual) . "\n";
    }
}

// Booking needs these for every agent -> exempt from the gate.
check('search is a lookup method',          true, lc_customer_lookup_method('search'));
check('search1 is a lookup method',         true, lc_customer_lookup_method('search1'));
check('check_duplicate is a lookup method', true, lc_customer_lookup_method('check_duplicate'));

// Case-insensitive: CI can dispatch the method in any case.
check('Search (mixed case) still matches',  true, lc_customer_lookup_method('Search'));
check('CHECK_DUPLICATE (upper) matches',     true, lc_customer_lookup_method('CHECK_DUPLICATE'));

// Everything else stays behind the page gate.
check('index stays gated',   false, lc_customer_lookup_method('index'));
check('Delete stays gated',  false, lc_customer_lookup_method('Delete'));
check('Download stays gated', false, lc_customer_lookup_method('Download'));
check('empty stays gated',   false, lc_customer_lookup_method(''));
check('null stays gated',    false, lc_customer_lookup_method(null));

echo $failures === 0 ? "\nALL PASS\n" : "\n{$failures} FAILURE(S)\n";
exit($failures === 0 ? 0 : 1);
