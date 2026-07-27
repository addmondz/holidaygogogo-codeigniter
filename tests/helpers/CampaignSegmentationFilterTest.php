<?php
/**
 * Run with: php tests/helpers/CampaignSegmentationFilterTest.php
 *
 * Locks the Campaign guest-picker segmentation filters added for advanced
 * audience targeting. Covers the PURE helpers that build their SQL / decide
 * which UNION branch (booking / GHL / leader-fallback) a filter can match:
 *
 *   - guest_list_purchase_count_min()      "purchased N× and above"
 *   - guest_list_parse_bucket_bounds()     lifetime value + booking-lead buckets
 *   - guest_list_flag_on()                 boolean toggles
 *   - guest_list_cancel_predicate()        cancelled-BC flip
 *   - suppression: leads-only (Tag/Race) drop booking + fallback;
 *                  booking-only segments drop the GHL branch.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}
require __DIR__ . '/../../application/helpers/guest_contact_helper.php';

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

// ---- purchase count (2x+) --------------------------------------------------
echo "purchase count min:\n";
assert_eq('empty → null',   null, guest_list_purchase_count_min(''));
assert_eq('blank → null',   null, guest_list_purchase_count_min('  '));
assert_eq('junk → null',    null, guest_list_purchase_count_min('lots'));
assert_eq('1 → null (not a repeat)', null, guest_list_purchase_count_min('1'));
assert_eq('0 → null',       null, guest_list_purchase_count_min('0'));
assert_eq('2 → 2',          2,    guest_list_purchase_count_min('2'));
assert_eq('5 → 5',          5,    guest_list_purchase_count_min('5'));

// ---- bucket bounds (LTV + booking-lead) ------------------------------------
echo "bucket bounds:\n";
assert_eq('empty → null',        null,                  guest_list_parse_bucket_bounds(''));
assert_eq('junk → null',         null,                  guest_list_parse_bucket_bounds('abc'));
assert_eq('range 10k-20k',       array(10000.0, 20000.0), guest_list_parse_bucket_bounds('10000-20000'));
assert_eq('range <10k as 0-10k', array(0.0, 10000.0),   guest_list_parse_bucket_bounds('0-10000'));
assert_eq('open 50000+',         array(50000.0, null),  guest_list_parse_bucket_bounds('50000+'));
assert_eq('open 50000-',         array(50000.0, null),  guest_list_parse_bucket_bounds('50000-'));
assert_eq('reversed → null',     null,                  guest_list_parse_bucket_bounds('20000-10000'));
assert_eq('equal → null',        null,                  guest_list_parse_bucket_bounds('10000-10000'));
assert_eq('months 1-2',          array(1.0, 2.0),       guest_list_parse_bucket_bounds('1-2'));
assert_eq('months 6+',           array(6.0, null),      guest_list_parse_bucket_bounds('6+'));

// ---- boolean toggle --------------------------------------------------------
echo "flag on:\n";
assert_eq('absent → off',  false, guest_list_flag_on(array(), 'cancelled'));
assert_eq('0 → off',       false, guest_list_flag_on(array('cancelled' => '0'), 'cancelled'));
assert_eq('empty → off',   false, guest_list_flag_on(array('cancelled' => ''), 'cancelled'));
assert_eq('1 → on',        true,  guest_list_flag_on(array('cancelled' => '1'), 'cancelled'));

// ---- cancel predicate flip -------------------------------------------------
echo "cancel predicate:\n";
assert_eq('default hides cancelled', "b.CancelStatus = 'N'",  guest_list_cancel_predicate(array(), 'b.CancelStatus'));
assert_eq('flag shows only cancelled', "b.CancelStatus != 'N'", guest_list_cancel_predicate(array('cancelled' => '1'), 'b.CancelStatus'));

// ---- suppression: leads-only filters drop booking + fallback ---------------
echo "leads-only (Tag/Race) suppression:\n";
assert_eq('tags drops booking',   true,  guest_list_bookings_suppressed_by_filters(array('tags' => array('VIP'))));
assert_eq('race drops booking',   true,  guest_list_bookings_suppressed_by_filters(array('race' => array('Chinese'))));
assert_eq('tags drops fallback',  true,  guest_list_leader_fallback_suppressed_by_filters(array('tags' => array('VIP'))));
assert_eq('race drops fallback',  true,  guest_list_leader_fallback_suppressed_by_filters(array('race' => array('Malay'))));
assert_eq('tags keeps GHL',       false, guest_list_ghl_suppressed_by_filters(array('tags' => array('VIP'))));
assert_eq('empty tags no drop',   false, guest_list_bookings_suppressed_by_filters(array('tags' => array())));

// ---- suppression: booking-only segments drop the GHL branch ----------------
echo "booking-only segment suppression:\n";
foreach (array('min_purchases' => '2', 'ltv' => '10000-20000', 'booking_lead' => '1-2',
    'consecutive_years' => '1', 'family_kids' => '1', 'cancelled' => '1') as $k => $v) {
    assert_eq("{$k} drops GHL", true, guest_list_ghl_suppressed_by_filters(array($k => $v)));
    assert_eq("{$k} keeps booking", false, guest_list_bookings_suppressed_by_filters(array($k => $v)));
    assert_eq("{$k} keeps fallback", false, guest_list_leader_fallback_suppressed_by_filters(array($k => $v)));
}
// An off toggle ('0') must NOT suppress anything.
assert_eq('cancelled=0 keeps GHL', false, guest_list_ghl_suppressed_by_filters(array('cancelled' => '0')));

// ---- branch routing end-to-end ---------------------------------------------
echo "branches_to_run integration:\n";
$b = guest_list_branches_to_run('all', array('race' => array('Chinese')));
assert_eq('race: only GHL runs', array('bookings' => false, 'ghl' => true), $b);
$b = guest_list_branches_to_run('all', array('min_purchases' => '2'));
assert_eq('2x+: only bookings run', array('bookings' => true, 'ghl' => false), $b);

echo "\nAll assertions passed.\n";
