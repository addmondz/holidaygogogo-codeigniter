<?php
/**
 * Run with: php tests/helpers/CustomerIntakeFormatDurationTest.php
 *
 * Locks the format used for the "Response Time" column in the booking list,
 * surfaced by format_response_duration(). The shape (s / m / h Xm / '-' on
 * missing) is the contract the view consumes, so any regression silently
 * changes what staff see.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require_once __DIR__ . '/../../application/helpers/customer_intake_helper.php';

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

assert_eq('null -> dash',          '-',     format_response_duration(null));
assert_eq('negative -> dash',      '-',     format_response_duration(-5));
assert_eq('non-numeric -> dash',   '-',     format_response_duration('abc'));
assert_eq('0 seconds',             '0s',    format_response_duration(0));
assert_eq('59 seconds',            '59s',   format_response_duration(59));
assert_eq('60 seconds = 1m',       '1m',    format_response_duration(60));
assert_eq('90 seconds = 1m',       '1m',    format_response_duration(90));
assert_eq('3599 seconds = 59m',    '59m',   format_response_duration(3599));
assert_eq('3600 seconds = 1h 0m',  '1h 0m', format_response_duration(3600));
assert_eq('3661 seconds = 1h 1m',  '1h 1m', format_response_duration(3661));
assert_eq('4500 seconds = 1h 15m', '1h 15m',format_response_duration(4500));
assert_eq('86400 seconds = 24h 0m','24h 0m',format_response_duration(86400));

echo "\nAll assertions passed.\n";
