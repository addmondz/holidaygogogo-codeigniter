<?php
/**
 * Run with: php tests/helpers/LeadReplyExportMatrixTest.php
 *
 * Locks the pure layout logic behind the Lead Reply Activity Excel export:
 * the on-screen dashboard is DAILY, but the export takes a date RANGE and lays
 * each day out as its own column group (owner rows, day columns). These helpers
 * expand the range into days and stitch the per-day rows into an owner x day
 * matrix -- all DB-free so they can be unit-tested here.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require_once __DIR__ . '/../../application/helpers/lead_reply_export_helper.php';

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label}\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

// ---------------------------------------------------------------------------
// 1. Range expansion
// ---------------------------------------------------------------------------

assert_eq('inclusive 3-day range', array('2026-06-01', '2026-06-02', '2026-06-03'),
    lead_reply_export_dates('2026-06-01', '2026-06-03'));

assert_eq('single day range', array('2026-06-10'),
    lead_reply_export_dates('2026-06-10', '2026-06-10'));

// Reversed range is swapped, not emptied.
assert_eq('reversed range is swapped', array('2026-06-01', '2026-06-02', '2026-06-03'),
    lead_reply_export_dates('2026-06-03', '2026-06-01'));

// Invalid input -> empty (controller treats as nothing to export).
assert_eq('invalid start -> empty', array(),
    lead_reply_export_dates('not-a-date', '2026-06-03'));

// Cap keeps the most recent N days (window ends on $end).
$capped = lead_reply_export_dates('2026-01-01', '2026-12-31', 3);
assert_eq('cap length', 3, count($capped));
assert_eq('cap keeps the tail (ends on end date)', '2026-12-31', $capped[2]);

// ---------------------------------------------------------------------------
// 2. Owner x day matrix
// ---------------------------------------------------------------------------

$dates = array('2026-06-01', '2026-06-02');
$perDay = array(
    '2026-06-01' => array(
        array('owner_user_id' => 'U-AMY', 'owner_name' => 'Amy', 'lead_responded' => 12, 'transfer_out_leads' => 3, 'today_handling_leads' => 9),
        array('owner_user_id' => 'U-BEN', 'owner_name' => 'Ben', 'lead_responded' => 8,  'transfer_out_leads' => 1, 'today_handling_leads' => 7),
    ),
    '2026-06-02' => array(
        // Amy again; Ben absent this day; Cara appears only on day 2.
        array('owner_user_id' => 'U-AMY', 'owner_name' => 'Amy',  'lead_responded' => 10, 'transfer_out_leads' => 2, 'today_handling_leads' => 8),
        array('owner_user_id' => 'U-CARA', 'owner_name' => 'Cara', 'lead_responded' => 20, 'transfer_out_leads' => 5, 'today_handling_leads' => 15),
    ),
);

$matrix = lead_reply_export_build_matrix($dates, $perDay);

// Owners are the union across days, sorted by total responded desc.
// Totals: Cara 20, Amy 22, Ben 8  -> Amy(22), Cara(20), Ben(8)
$ownerOrder = array_map(function ($o) { return $o['owner_user_id']; }, $matrix['owners']);
assert_eq('owner union + sort by total responded desc', array('U-AMY', 'U-CARA', 'U-BEN'), $ownerOrder);

assert_eq('Amy total responded across range', 22, $matrix['owners'][0]['total_responded']);
assert_eq('Amy total transfer out across range', 5, $matrix['owners'][0]['total_transfer_out']);

// Per-day cells: [responded, transfer_out, handling].
assert_eq('Amy day 1 cell', array(12, 3, 9), $matrix['lookup']['U-AMY']['2026-06-01']);
assert_eq('Amy day 2 cell', array(10, 2, 8), $matrix['lookup']['U-AMY']['2026-06-02']);

// Ben has no day-2 entry -> writer renders blank/zero for that column group.
assert_eq('Ben has no day-2 cell', false, isset($matrix['lookup']['U-BEN']['2026-06-02']));

// Cara has no day-1 entry.
assert_eq('Cara has no day-1 cell', false, isset($matrix['lookup']['U-CARA']['2026-06-01']));
assert_eq('Cara day 2 cell', array(20, 5, 15), $matrix['lookup']['U-CARA']['2026-06-02']);

// ---------------------------------------------------------------------------
// 3. Filename
// ---------------------------------------------------------------------------

assert_eq('range filename', 'LEAD_REPLY_ACTIVITY_20260601_20260630.xlsx',
    lead_reply_export_filename('2026-06-01', '2026-06-30'));
assert_eq('single-day filename collapses', 'LEAD_REPLY_ACTIVITY_20260610.xlsx',
    lead_reply_export_filename('2026-06-10', '2026-06-10'));

echo "\nAll assertions passed.\n";
