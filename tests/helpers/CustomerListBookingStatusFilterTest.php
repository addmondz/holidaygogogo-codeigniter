<?php
/**
 * Run with: php tests/helpers/CustomerListBookingStatusFilterTest.php
 *
 * Locks the Customer List default "Booking Status" gate (Build_Customer_Branch).
 * By default the list shows only customers with an active BOOKING CONFIRMATION —
 * customers whose bookings are ALL cancelled or ALL quotation/proforma are hidden,
 * like the booking listing. The customer_booking_status[] multi-select (and the
 * legacy cancelled=1 campaign toggle) reveal the hidden sets; no-booking customers
 * always stay visible. Covers the pure helper:
 *
 *   - guest_list_customer_status_tokens()     token => booking predicate map
 *   - guest_list_customer_status_predicate()  the OR-ed EXISTS WHERE fragment
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

function assert_not_contains($label, $needle, $haystack) {
    if (strpos($haystack, $needle) === false) {
        echo "  PASS  {$label} omits " . var_export($needle, true) . "\n";
    } else {
        echo "  FAIL  {$label}: unexpected " . var_export($needle, true)
           . " found in " . var_export($haystack, true) . "\n";
        exit(1);
    }
}

// ---- token map -------------------------------------------------------------
echo "status tokens:\n";
$tokens = guest_list_customer_status_tokens();
assert_eq('four tokens', array('bc', 'cancelled', 'quotation', 'proforma'), array_keys($tokens));
assert_contains('bc = active BOOKING CONFIRMATION', "b.BookingConfirmationTitle = 'BOOKING CONFIRMATION'", $tokens['bc']);
assert_contains('bc requires active',               "b.CancelStatus = 'N'",                                $tokens['bc']);
assert_contains('cancelled = CancelStatus <> N',    "b.CancelStatus <> 'N'",                               $tokens['cancelled']);
assert_contains('quotation title',                  "b.BookingConfirmationTitle = 'QUOTATION'",            $tokens['quotation']);
assert_contains('proforma title',                   "b.BookingConfirmationTitle = 'PROFORMA INVOICE'",     $tokens['proforma']);

// ---- default (nothing selected) → confirmed set only -----------------------
echo "default gate:\n";
$def = guest_list_customer_status_predicate(array());
assert_eq('default no params', array(), $def['params']);
assert_contains('default keeps no-booking customers', 'NOT EXISTS (SELECT 1 FROM booking b', $def['sql']);
assert_contains('default requires active BC', "b.BookingConfirmationTitle = 'BOOKING CONFIRMATION'", $def['sql']);
assert_not_contains('default hides cancelled', "b.CancelStatus <> 'N'", $def['sql']);
assert_not_contains('default hides quotation', 'QUOTATION', $def['sql']);
assert_not_contains('default hides proforma', 'PROFORMA INVOICE', $def['sql']);

// ---- picking a status REVEALS that set (replace, not add BC) ----------------
echo "reveal cancelled:\n";
$cx = guest_list_customer_status_predicate(array('customer_booking_status' => array('cancelled')));
assert_contains('cancelled revealed', "b.CancelStatus <> 'N'", $cx['sql']);
assert_not_contains('cancelled-only drops BC', 'BOOKING CONFIRMATION', $cx['sql']);
assert_contains('no-booking still kept', 'NOT EXISTS (SELECT 1 FROM booking b', $cx['sql']);

echo "reveal quotation + proforma:\n";
$qp = guest_list_customer_status_predicate(array('customer_booking_status' => array('quotation', 'proforma')));
assert_contains('quotation revealed', 'QUOTATION', $qp['sql']);
assert_contains('proforma revealed', 'PROFORMA INVOICE', $qp['sql']);

echo "bc + cancelled combined:\n";
$both = guest_list_customer_status_predicate(array('customer_booking_status' => array('bc', 'cancelled')));
assert_contains('bc kept', 'BOOKING CONFIRMATION', $both['sql']);
assert_contains('cancelled kept', "b.CancelStatus <> 'N'", $both['sql']);

// ---- legacy campaign toggle folds into cancelled ---------------------------
echo "legacy cancelled=1 toggle:\n";
$legacy = guest_list_customer_status_predicate(array('cancelled' => '1'));
assert_contains('cancelled=1 reveals cancelled', "b.CancelStatus <> 'N'", $legacy['sql']);
$legacy_off = guest_list_customer_status_predicate(array('cancelled' => '0'));
assert_not_contains('cancelled=0 stays default', "b.CancelStatus <> 'N'", $legacy_off['sql']);
assert_contains('cancelled=0 default BC', 'BOOKING CONFIRMATION', $legacy_off['sql']);

// ---- garbage tokens ignored, fall back to default --------------------------
echo "invalid token handling:\n";
$junk = guest_list_customer_status_predicate(array('customer_booking_status' => array('bogus', '')));
assert_contains('junk → default BC', 'BOOKING CONFIRMATION', $junk['sql']);
assert_not_contains('junk → no cancelled', "b.CancelStatus <> 'N'", $junk['sql']);
// Case-insensitive: uppercase token still matches.
$upper = guest_list_customer_status_predicate(array('customer_booking_status' => 'CANCELLED'));
assert_contains('uppercase CANCELLED matches', "b.CancelStatus <> 'N'", $upper['sql']);

echo "\nAll assertions passed.\n";
