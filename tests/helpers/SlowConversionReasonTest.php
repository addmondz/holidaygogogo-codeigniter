<?php
/**
 * Run with: php tests/helpers/SlowConversionReasonTest.php
 *
 * Locks the two pure pieces behind the "Slow Conversion Reasons" booking card:
 *
 *   1) is_slow_conversion($total_seconds): the card only appears for a booking
 *      that has CONVERTED (reached Pending Payment, so total is non-null) AND
 *      whose saved-as-draft -> Pending Payment gap exceeded 24h (> 86400s).
 *      24h exactly is NOT slow (strictly greater than the threshold).
 *
 *   2) diff_selected_reason_ids($current, $submitted): turns the user's new
 *      multi-select into the minimal junction-table writes - which reason ids
 *      to INSERT and which to DELETE - normalising both sides to unique ints so
 *      string posts, duplicates, and blanks don't churn rows.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require_once __DIR__ . '/../../application/helpers/slow_conversion_helper.php';

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

// -- 1) is_slow_conversion ------------------------------------------------

// Not converted yet -> null total -> never slow (card hidden).
assert_eq('null total not slow', false, is_slow_conversion(null));

// Converted fast: 1h.
assert_eq('1h not slow', false, is_slow_conversion(3600));

// Exactly 24h is the boundary and is NOT slow (strictly greater).
assert_eq('exactly 24h not slow', false, is_slow_conversion(86400));

// 24h + 1s is slow.
assert_eq('24h+1s slow', true, is_slow_conversion(86401));

// Way over.
assert_eq('3 days slow', true, is_slow_conversion(259200));

// Defensive: negative (clock skew) is not slow.
assert_eq('negative not slow', false, is_slow_conversion(-100));

// Non-numeric guard.
assert_eq('non-numeric not slow', false, is_slow_conversion('abc'));

// Custom threshold (12h) still honoured.
assert_eq('custom 12h threshold', true, is_slow_conversion(43201, 43200));

// -- 2) diff_selected_reason_ids ------------------------------------------

// First-time save: nothing stored, user picks two reasons -> both inserted.
$d = diff_selected_reason_ids(array(), array(3, 5));
assert_eq('fresh add', array(3, 5), $d['add']);
assert_eq('fresh remove empty', array(), $d['remove']);

// No change (order/duplication/strings shouldn't churn rows).
$d = diff_selected_reason_ids(array(3, 5), array('5', '3', '3'));
assert_eq('no change add', array(), $d['add']);
assert_eq('no change remove', array(), $d['remove']);

// Swap one out, add one in.
$d = diff_selected_reason_ids(array(3, 5), array(5, 8));
assert_eq('swap add', array(8), $d['add']);
assert_eq('swap remove', array(3), $d['remove']);

// Clear everything -> all removed.
$d = diff_selected_reason_ids(array(3, 5), array());
assert_eq('clear add', array(), $d['add']);
assert_eq('clear remove', array(3, 5), $d['remove']);

// Blanks / zero / non-numeric in the submitted set are dropped.
$d = diff_selected_reason_ids(array(), array('', '0', 'x', '7'));
assert_eq('blanks dropped add', array(7), $d['add']);

echo "\nAll assertions passed.\n";
