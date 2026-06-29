<?php
/**
 * Run with: php tests/helpers/GhlMessageLogHourlyBreakdownTest.php
 *
 * Locks ghl_message_log_hourly_breakdown() -- the normaliser behind the Lead
 * Reply Hourly page. The SQL only returns hours that actually had traffic, so
 * this helper must:
 *   - expand any sparse rows into a full ordered 0..23 series (gaps = zeros),
 *   - sum inbound/outbound per hour and across the whole day,
 *   - label each hour in 12-hour clock form (12 AM, 7 AM, 12 PM, 10 PM),
 *   - ignore out-of-range / malformed hour rows.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require __DIR__ . '/../../application/helpers/ghl_messages_log_helper.php';

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

// --- Always 24 ordered hour buckets, even from a single populated hour. ---
$out = ghl_message_log_hourly_breakdown(array(
    array('hour_of_day' => 9, 'inbound_count' => 3, 'outbound_count' => 5),
));
assert_eq('full 24-hour series', 24, count($out['hours']));
assert_eq('first bucket is hour 0', 0, $out['hours'][0]['hour']);
assert_eq('last bucket is hour 23', 23, $out['hours'][23]['hour']);
assert_eq('empty hour 0 inbound = 0', 0, $out['hours'][0]['inbound']);
assert_eq('hour 9 inbound', 3, $out['hours'][9]['inbound']);
assert_eq('hour 9 outbound', 5, $out['hours'][9]['outbound']);
assert_eq('hour 9 total', 8, $out['hours'][9]['total']);

// --- Day totals sum across every hour. ---
$out = ghl_message_log_hourly_breakdown(array(
    array('hour_of_day' => 7, 'inbound_count' => 2, 'outbound_count' => 4),
    array('hour_of_day' => 22, 'inbound_count' => 1, 'outbound_count' => 6),
));
assert_eq('total inbound', 3, $out['total_inbound']);
assert_eq('total outbound', 10, $out['total_outbound']);
assert_eq('grand total', 13, $out['total']);

// --- 12-hour clock labels. ---
$out = ghl_message_log_hourly_breakdown(array());
assert_eq('midnight label', '12 AM', $out['hours'][0]['label']);
assert_eq('7am label', '7 AM', $out['hours'][7]['label']);
assert_eq('noon label', '12 PM', $out['hours'][12]['label']);
assert_eq('10pm label', '10 PM', $out['hours'][22]['label']);
assert_eq('range label', '09:00 - 09:59', $out['hours'][9]['range_label']);

// --- Two rows on the same hour accumulate (defensive against un-grouped input). ---
$out = ghl_message_log_hourly_breakdown(array(
    array('hour_of_day' => 14, 'inbound_count' => 1, 'outbound_count' => 1),
    array('hour_of_day' => 14, 'inbound_count' => 2, 'outbound_count' => 3),
));
assert_eq('same-hour inbound merged', 3, $out['hours'][14]['inbound']);
assert_eq('same-hour outbound merged', 4, $out['hours'][14]['outbound']);

// --- Out-of-range / malformed hours are ignored. ---
$out = ghl_message_log_hourly_breakdown(array(
    array('hour_of_day' => 24, 'inbound_count' => 9, 'outbound_count' => 9),
    array('hour_of_day' => -1, 'inbound_count' => 9, 'outbound_count' => 9),
    array('inbound_count' => 9, 'outbound_count' => 9),
));
assert_eq('garbage rows dropped from totals', 0, $out['total']);

echo "\nAll ghl_message_log_hourly_breakdown tests passed.\n";
