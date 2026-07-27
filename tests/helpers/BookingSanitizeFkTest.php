<?php
/**
 * Run with: php tests/helpers/BookingSanitizeFkTest.php
 *
 * Locks nullify_empty_booking_fk() (application/helpers/booking_sanitize_helper.php).
 *
 * When staff clear the "Booking OP" (or second sales agent) dropdown, the
 * browser posts the value as an empty string ''. booking.BookingOP / SalesAgent2
 * are nullable INT foreign keys, and MySQL under STRICT_TRANS_TABLES rejects ''
 * for an INT column ("Incorrect integer value: '' for column 'BookingOP'"),
 * which surfaced as "Booking Record ... Could Not Be Updated".
 *
 * The helper must coerce those '' values to NULL before the update_batch write,
 * while leaving real ids and unrelated fields untouched.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require __DIR__ . '/../../application/helpers/booking_sanitize_helper.php';

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label}\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

// 1. Cleared BookingOP + SalesAgent2 ('') -> NULL.
$out = nullify_empty_booking_fk([[
    'BookingID'   => 10,
    'BookingOP'   => '',
    'SalesAgent2' => '',
    'Customer'    => 'Ada',
]]);
assert_eq('cleared BookingOP becomes null',   null, $out[0]['BookingOP']);
assert_eq('cleared SalesAgent2 becomes null', null, $out[0]['SalesAgent2']);
assert_eq('unrelated field untouched',        'Ada', $out[0]['Customer']);

// 2. Real integer ids are left exactly as-is (===, no type coercion).
$out = nullify_empty_booking_fk([[
    'BookingOP'   => 26,
    'SalesAgent2' => '31',
]]);
assert_eq('real BookingOP id kept',   26,   $out[0]['BookingOP']);
assert_eq('real SalesAgent2 id kept', '31', $out[0]['SalesAgent2']);

// 3. Absent keys are not invented.
$out = nullify_empty_booking_fk([['Customer' => 'Bob']]);
assert_eq('missing BookingOP not added',   false, array_key_exists('BookingOP', $out[0]));
assert_eq('missing SalesAgent2 not added', false, array_key_exists('SalesAgent2', $out[0]));

// 4. Only '' is nullified — '0' / 0 are not treated as empty.
$out = nullify_empty_booking_fk([['BookingOP' => '0']]);
assert_eq("string '0' is not nullified", '0', $out[0]['BookingOP']);

// 5. Empty / non-array payloads pass through untouched.
assert_eq('empty array unchanged', [], nullify_empty_booking_fk([]));
assert_eq('null payload unchanged', null, nullify_empty_booking_fk(null));

// 6. Multi-row payloads each get sanitised.
$out = nullify_empty_booking_fk([
    ['BookingOP' => ''],
    ['BookingOP' => 5],
]);
assert_eq('row 0 nullified', null, $out[0]['BookingOP']);
assert_eq('row 1 kept',      5,    $out[1]['BookingOP']);

echo "\nAll assertions passed.\n";
