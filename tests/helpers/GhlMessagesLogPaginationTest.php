<?php
/**
 * Run with: php tests/helpers/GhlMessagesLogPaginationTest.php
 *
 * Locks the pagination math for the GHL Message Log. The log renders raw
 * messages with a hard LIMIT/OFFSET, so this resolver must:
 *   - clamp an out-of-range page back into 1..total_pages (never a blank page
 *     or a negative OFFSET that would error the SQL),
 *   - report an honest "showing X-Y of Z" window,
 *   - degrade safely to a single empty page when there are no rows.
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

// 120 rows, 50/page -> 3 pages.
$p1 = ghl_messages_log_pagination(120, 1, 50);
assert_eq('p1 total_pages', 3, $p1['total_pages']);
assert_eq('p1 page',        1, $p1['page']);
assert_eq('p1 offset',      0, $p1['offset']);
assert_eq('p1 from_row',    1, $p1['from_row']);
assert_eq('p1 to_row',     50, $p1['to_row']);
assert_eq('p1 has_prev', false, $p1['has_prev']);
assert_eq('p1 has_next', true,  $p1['has_next']);

// Last page is partial: 101..120.
$p3 = ghl_messages_log_pagination(120, 3, 50);
assert_eq('p3 offset',    100, $p3['offset']);
assert_eq('p3 from_row',  101, $p3['from_row']);
assert_eq('p3 to_row',    120, $p3['to_row']);
assert_eq('p3 has_next', false, $p3['has_next']);
assert_eq('p3 has_prev', true,  $p3['has_prev']);

// Overflow page clamps down to the last page (no blank page, no bad offset).
$over = ghl_messages_log_pagination(120, 99, 50);
assert_eq('overflow page clamped',   3,   $over['page']);
assert_eq('overflow offset clamped', 100, $over['offset']);

// Page 0 / negative clamps up to 1.
$under = ghl_messages_log_pagination(120, 0, 50);
assert_eq('underflow page clamped', 1, $under['page']);
assert_eq('underflow offset',       0, $under['offset']);

// Non-positive per_page falls back to 50.
$badPer = ghl_messages_log_pagination(120, 1, 0);
assert_eq('per_page fallback', 50, $badPer['per_page']);

// Empty result -> a single empty page, never has_next/prev, never crashes.
$empty = ghl_messages_log_pagination(0, 1, 50);
assert_eq('empty total_pages', 1, $empty['total_pages']);
assert_eq('empty page',        1, $empty['page']);
assert_eq('empty offset',      0, $empty['offset']);
assert_eq('empty from_row',    0, $empty['from_row']);
assert_eq('empty to_row',      0, $empty['to_row']);
assert_eq('empty has_prev', false, $empty['has_prev']);
assert_eq('empty has_next', false, $empty['has_next']);

// Exact multiple: 100 rows / 50 -> exactly 2 full pages.
$exact = ghl_messages_log_pagination(100, 2, 50);
assert_eq('exact total_pages', 2,   $exact['total_pages']);
assert_eq('exact to_row',      100, $exact['to_row']);
assert_eq('exact has_next',  false, $exact['has_next']);

echo "\nAll assertions passed.\n";
