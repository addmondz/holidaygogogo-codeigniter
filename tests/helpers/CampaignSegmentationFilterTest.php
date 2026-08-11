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

function assert_contains($label, $needle, $haystack) {
    if (strpos($haystack, $needle) !== false) {
        echo "  PASS  {$label} contains " . var_export($needle, true) . "\n";
    } else {
        echo "  FAIL  {$label}: " . var_export($needle, true)
           . " not found in " . var_export($haystack, true) . "\n";
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

// ---- suppression: customer-only segments drop EVERY UNION branch -----------
// The value segments now target the customer master, so a booking guest, a
// synthesized leader-fallback row and a GHL lead can none of them satisfy one:
// each drops its branch on the booking+GHL UNION path (leaving zero rows unless
// the picker's Type = Customer, which runs the separate customer query).
echo "customer-only segment suppression:\n";
foreach (array('min_purchases' => '2', 'ltv' => '10000-20000', 'booking_lead' => '1-2',
    'consecutive_years' => '1', 'family_kids' => '1', 'cancelled' => '1') as $k => $v) {
    assert_eq("{$k} drops GHL",      true, guest_list_ghl_suppressed_by_filters(array($k => $v)));
    assert_eq("{$k} drops booking",  true, guest_list_bookings_suppressed_by_filters(array($k => $v)));
    assert_eq("{$k} drops fallback", true, guest_list_leader_fallback_suppressed_by_filters(array($k => $v)));
}
// An off toggle ('0') must NOT suppress anything.
assert_eq('cancelled=0 keeps GHL',     false, guest_list_ghl_suppressed_by_filters(array('cancelled' => '0')));
assert_eq('cancelled=0 keeps booking', false, guest_list_bookings_suppressed_by_filters(array('cancelled' => '0')));

// ---- "Has email address" filter --------------------------------------------
// The rows-with-email toggle keeps booking guests + GHL leads (both carry an
// email column) but drops the leader-fallback branch — a synthesized leader row
// has no email (its Email column is always NULL), so it can never match.
echo "has_email suppression:\n";
assert_eq('has_email drops fallback',   true,  guest_list_leader_fallback_suppressed_by_filters(array('has_email' => '1')));
assert_eq('has_email keeps booking',    false, guest_list_bookings_suppressed_by_filters(array('has_email' => '1')));
assert_eq('has_email keeps GHL',        false, guest_list_ghl_suppressed_by_filters(array('has_email' => '1')));
assert_eq('has_email=0 keeps fallback', false, guest_list_leader_fallback_suppressed_by_filters(array('has_email' => '0')));

// ---- branch routing end-to-end ---------------------------------------------
echo "branches_to_run integration:\n";
$b = guest_list_branches_to_run('all', array('race' => array('Chinese')));
assert_eq('race: only GHL runs', array('bookings' => false, 'ghl' => true), $b);
$b = guest_list_branches_to_run('all', array('min_purchases' => '2'));
assert_eq('2x+: nothing runs on UNION (customer-only now)', array('bookings' => false, 'ghl' => false), $b);

// ---- customer-path segment SQL builder -------------------------------------
// The pure builder that turns the value segments into a correlated WHERE
// fragment over the customer's own bookings (b.CustomerID = c.CustomerID).
echo "customer segment sql:\n";
$none = guest_list_customer_segment_sql(array());
assert_eq('no segment → empty sql',    '',          $none['sql']);
assert_eq('no segment → empty params', array(),     $none['params']);

$mp = guest_list_customer_segment_sql(array('min_purchases' => '2'));
assert_eq('min_purchases params', array(2), $mp['params']);
assert_contains('min_purchases sql', 'COUNT(DISTINCT b.BookingID)', $mp['sql']);
assert_contains('min_purchases active set', "b.CancelStatus = 'N'", $mp['sql']);

$lv = guest_list_customer_segment_sql(array('ltv' => '10000-20000'));
assert_eq('ltv params', array(10000.0, 20000.0), $lv['params']);
assert_contains('ltv sql', 'SUM(COALESCE(b.NetTotal', $lv['sql']);

$ll = guest_list_customer_segment_sql(array('ltv' => '50000+'));
assert_eq('ltv open-ended params', array(50000.0), $ll['params']);

$bl = guest_list_customer_segment_sql(array('booking_lead' => '1-2'));
assert_eq('booking_lead params', array(1.0, 2.0), $bl['params']);
assert_contains('booking_lead sql', 'TIMESTAMPDIFF(MONTH', $bl['sql']);

$fk = guest_list_customer_segment_sql(array('family_kids' => '1'));
assert_eq('family_kids params', array(), $fk['params']);
assert_contains('family_kids sql', 'b.Children', $fk['sql']);

$cy = guest_list_customer_segment_sql(array('consecutive_years' => '1'));
assert_eq('consecutive_years params', array(), $cy['params']);
assert_contains('consecutive_years sql', 'BIT_OR', $cy['sql']);

$cn = guest_list_customer_segment_sql(array('cancelled' => '1'));
assert_eq('cancelled params', array(), $cn['params']);
assert_contains('cancelled requires cancelled BC', "b.CancelStatus <> 'N'", $cn['sql']);

// Cancelled flips the booking set the OTHER segments measure over.
$mix = guest_list_customer_segment_sql(array('cancelled' => '1', 'min_purchases' => '2'));
assert_eq('cancelled+min params', array(2), $mix['params']);
assert_contains('cancelled flips the set', "b.CancelStatus <> 'N'", $mix['sql']);

// ---- Campaign picker "force customer branch" routing contract --------------
// Bug: with Type left at "--ALL TYPES--", any customer-value segment suppressed
// EVERY UNION branch (asserted above) so the picker returned "No matching
// guests". Campaign::Search_Guests now forces the customer query whenever
//   guest_list_any_filter_set($get, guest_list_customer_only_segment_keys())
// is true. This locks that exact predicate so all six segments route to the
// customer branch, and nothing else trips it.
echo "force-customer routing:\n";
$seg_keys = guest_list_customer_only_segment_keys();
assert_eq('all six segment keys present', array('min_purchases', 'ltv', 'booking_lead', 'consecutive_years', 'family_kids', 'cancelled'), $seg_keys);

foreach (array('min_purchases' => '2', 'ltv' => '10000-20000', 'booking_lead' => '1-2',
    'consecutive_years' => '1', 'family_kids' => '1', 'cancelled' => '1') as $k => $v) {
    assert_eq("{$k} forces customer branch", true, guest_list_any_filter_set(array($k => $v), $seg_keys));
}

// Nothing set → do NOT force (normal booking/GHL routing). Off toggles ('0'),
// blank dropdowns and empty multi-selects must not trip the customer branch.
assert_eq('no segment → no force',        false, guest_list_any_filter_set(array(), $seg_keys));
assert_eq('off cancelled toggle no force', false, guest_list_any_filter_set(array('cancelled' => '0'), $seg_keys));
assert_eq('blank dropdown no force',       false, guest_list_any_filter_set(array('min_purchases' => '', 'ltv' => ''), $seg_keys));
assert_eq('empty multiselect no force',    false, guest_list_any_filter_set(array('family_kids' => array()), $seg_keys));
// A non-segment filter (e.g. Destination) must NOT force the customer branch.
assert_eq('destination no force',          false, guest_list_any_filter_set(array('destination' => array('5')), $seg_keys));

echo "\nAll assertions passed.\n";
