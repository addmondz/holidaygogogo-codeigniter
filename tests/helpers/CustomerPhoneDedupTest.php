<?php
/**
 * Run with: php tests/helpers/CustomerPhoneDedupTest.php
 *
 * Locks the contract for the customer duplicate-detection helper. Duplicate
 * customers are detected by PHONE only, normalised to the last-9-digit key
 * (same key as guest_list.dedup_key via guest_contact_normalize_key), so the
 * same person saved as "0122983045" / "122983045" / "+60122983045" collapses
 * to one match regardless of the format entered.
 *
 * Business rule (confirmed 2026-08-07):
 *   - Same normalised phone      -> duplicate (match), regardless of name.
 *   - Same NAME, different phone  -> NOT a duplicate (real namesakes allowed).
 *   - Empty / digitless phone     -> no matches (never block a blank phone).
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require_once __DIR__ . '/../../application/helpers/customer_dedup_helper.php';

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

// --- customer_phone_dedup_key(): the normaliser used on both sides ----------
check('key strips leading 0',   '122983045', customer_phone_dedup_key('0122983045'));
check('key strips country 60',   '122983045', customer_phone_dedup_key('60122983045'));
check('key strips +60 & spaces', '122983045', customer_phone_dedup_key('+60 122983045'));
check('key already 9 digits',    '122983045', customer_phone_dedup_key('122983045'));
check('key empty -> ""',         '',          customer_phone_dedup_key(''));
check('key letters only -> ""',  '',          customer_phone_dedup_key('N/A'));

// --- customer_filter_phone_duplicates(): pure row filter --------------------
$rows = array(
    array('CustomerID' => 8738,  'name' => 'SALINA ANN YAHYA', 'phone_number' => '0122983045',   'CustomerCode' => '303-S006'),
    array('CustomerID' => 9898,  'name' => 'SALINA ANN YAHYA', 'phone_number' => '122983045',     'CustomerCode' => '303-S092'),
    array('CustomerID' => 7242,  'name' => 'SALINA ANN YAHYA', 'phone_number' => '+60122881518',  'CustomerCode' => '301-S176'),
    array('CustomerID' => 5000,  'name' => 'JOHN TAN',         'phone_number' => '60129998888',   'CustomerCode' => '303-J001'),
);

// Same person, differently formatted phone -> both 012298304x rows match, the
// different-phone same-name row (301-S176) does NOT.
$hits = customer_filter_phone_duplicates($rows, '012-2983045');
check('phone match count',    2,             count($hits));
$ids = array_map(function ($r) { return $r['CustomerID']; }, $hits);
sort($ids);
check('phone match ids',      array(8738, 9898), $ids);

// Same NAME but a different phone must never match (real namesakes allowed).
// '018-0000000' is a phone no row has, yet a "SALINA ANN YAHYA" row exists.
check('namesake no match',    0,             count(customer_filter_phone_duplicates($rows, '018-0000000')));

// A blank / digitless phone never blocks.
check('blank phone no match', 0,             count(customer_filter_phone_duplicates($rows, '')));
check('N/A phone no match',   0,             count(customer_filter_phone_duplicates($rows, 'N/A')));

// --- customer_group_names_differ(): family/verify badge --------------------
check('same name -> false', false, customer_group_names_differ(array(
    array('name' => 'SALINA ANN YAHYA'),
    array('name' => ' salina ann yahya '), // case + whitespace ignored
)));
check('diff name -> true',  true,  customer_group_names_differ(array(
    array('name' => 'AHMAD'),
    array('name' => 'SITI'),
)));

// --- customer_default_keeper_id(): most bookings, then code, then oldest -----
check('most bookings wins', 8738, customer_default_keeper_id(array(
    array('CustomerID' => 9898, 'booking_count' => 1,  'CustomerCode' => '303-S092'),
    array('CustomerID' => 8738, 'booking_count' => 12, 'CustomerCode' => '303-S006'),
    array('CustomerID' => 7236, 'booking_count' => 0,  'CustomerCode' => '301-S170'),
)));
check('tie -> has code wins', 200, customer_default_keeper_id(array(
    array('CustomerID' => 100, 'booking_count' => 2, 'CustomerCode' => null),
    array('CustomerID' => 200, 'booking_count' => 2, 'CustomerCode' => '303-X001'),
)));
check('tie -> oldest wins',   50, customer_default_keeper_id(array(
    array('CustomerID' => 90, 'booking_count' => 0, 'CustomerCode' => null),
    array('CustomerID' => 50, 'booking_count' => 0, 'CustomerCode' => null),
)));

if ($failures === 0) {
    echo "\nAll CustomerPhoneDedup assertions passed.\n";
    exit(0);
}
echo "\n{$failures} assertion(s) failed.\n";
exit(1);
