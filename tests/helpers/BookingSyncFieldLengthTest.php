<?php
/**
 * Run with: php tests/helpers/BookingSyncFieldLengthTest.php
 *
 * Locks the AutoCount field-length guard added to BookingSync::clip().
 *
 * Bug reproduced (booking BC-2606-0178): AutoCount rejected the whole
 * quotation with
 *   "The field YourRef must be a string with a maximum length of 20.
 *    The field Description must be a string with a maximum length of 80."
 *
 * Cause: enrichBooking() sets yourRef = ReservationNumber and the master/detail
 * Description comes from the product name — either can exceed AutoCount's limits.
 *
 * clip() must cap yourRef at 20 and Description at 80 while leaving null/empty
 * untouched, so an over-long reference or product name no longer fails the sync.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require __DIR__ . '/../../application/libraries/BookingSync.php';

$ref = new ReflectionClass('BookingSync');

// Bypass the constructor (it needs the CodeIgniter super-object) — clip() is pure.
$sync = $ref->newInstanceWithoutConstructor();

$clip = $ref->getMethod('clip');
$clip->setAccessible(true);

$MAX_YOUR_REF    = $ref->getConstant('MAX_YOUR_REF');
$MAX_DESCRIPTION = $ref->getConstant('MAX_DESCRIPTION');

$failed = 0;
function check($label, $expected, $actual)
{
    global $failed;
    if ($expected === $actual) {
        echo "PASS: $label\n";
    } else {
        $failed++;
        echo "FAIL: $label\n  expected: " . var_export($expected, true) . "\n  actual:   " . var_export($actual, true) . "\n";
    }
}

// Limits are the values AutoCount enforces.
check('MAX_YOUR_REF is 20', 20, $MAX_YOUR_REF);
check('MAX_DESCRIPTION is 80', 80, $MAX_DESCRIPTION);

// yourRef: a 25-char reservation number must be cut to 20.
$longRef = 'TBPLH230517309941871234567';
check(
    'over-long yourRef clipped to 20',
    mb_substr($longRef, 0, 20),
    $clip->invoke($sync, $longRef, $MAX_YOUR_REF)
);
check(
    'clipped yourRef length == 20',
    20,
    mb_strlen($clip->invoke($sync, $longRef, $MAX_YOUR_REF))
);

// description: a product name well over 80 chars must be cut to 80.
$longName = str_repeat('A', 130);
check(
    'over-long description clipped to 80',
    str_repeat('A', 80),
    $clip->invoke($sync, $longName, $MAX_DESCRIPTION)
);

// Within-limit values pass through unchanged.
check('short yourRef untouched', 'RBRQ9NLP', $clip->invoke($sync, 'RBRQ9NLP', $MAX_YOUR_REF));
check('exactly-20 yourRef untouched', str_repeat('B', 20), $clip->invoke($sync, str_repeat('B', 20), $MAX_YOUR_REF));

// Null / empty must stay null / empty (so AutoCount keeps its own behaviour).
check('null stays null', null, $clip->invoke($sync, null, $MAX_YOUR_REF));
check('empty string stays empty', '', $clip->invoke($sync, '', $MAX_DESCRIPTION));

// Multibyte safety: clip counts characters, never splits a byte.
$multibyte = str_repeat('é', 25); // 25 chars, 50 bytes in UTF-8
check('multibyte clipped by characters not bytes', str_repeat('é', 20), $clip->invoke($sync, $multibyte, $MAX_YOUR_REF));

echo "\n" . ($failed === 0 ? "ALL TESTS PASSED\n" : "$failed TEST(S) FAILED\n");
exit($failed === 0 ? 0 : 1);
