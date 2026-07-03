<?php
/**
 * Run with: php tests/helpers/LeadsByHourMatrixTest.php
 *
 * Locks the pure layout logic behind the "Leads By Hour" report. The SQL only
 * returns (date, hour) pairs that actually had leads, so these DB-free helpers
 * must expand the range into every day, stitch sparse rows into a full
 * date x 24-hour grid, and carry per-day / per-hour / grand totals.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require_once __DIR__ . '/../../application/helpers/leads_by_hour_helper.php';

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
// 1. Hour labels (12-hour clock)
// ---------------------------------------------------------------------------
assert_eq('midnight label', '12 AM', leads_by_hour_hour_label(0));
assert_eq('9am label', '9 AM', leads_by_hour_hour_label(9));
assert_eq('noon label', '12 PM', leads_by_hour_hour_label(12));
assert_eq('1pm label', '1 PM', leads_by_hour_hour_label(13));
assert_eq('11pm label', '11 PM', leads_by_hour_hour_label(23));
assert_eq('out-of-range label', '', leads_by_hour_hour_label(24));

// ---------------------------------------------------------------------------
// 2. Range expansion
// ---------------------------------------------------------------------------
assert_eq('inclusive 3-day range', array('2026-06-01', '2026-06-02', '2026-06-03'),
    leads_by_hour_expand_dates('2026-06-01', '2026-06-03'));
assert_eq('single day range', array('2026-06-10'),
    leads_by_hour_expand_dates('2026-06-10', '2026-06-10'));
assert_eq('reversed range swapped', array('2026-06-01', '2026-06-02', '2026-06-03'),
    leads_by_hour_expand_dates('2026-06-03', '2026-06-01'));
assert_eq('invalid start -> empty', array(),
    leads_by_hour_expand_dates('not-a-date', '2026-06-03'));
$capped = leads_by_hour_expand_dates('2026-01-01', '2026-12-31', 3);
assert_eq('cap length', 3, count($capped));
assert_eq('cap keeps the tail', '2026-12-31', $capped[2]);

// ---------------------------------------------------------------------------
// 3. Matrix: full 24 hour columns, empty days still present, gaps = zero
// ---------------------------------------------------------------------------
$dates = leads_by_hour_expand_dates('2026-06-01', '2026-06-02');
$matrix = leads_by_hour_build_matrix(array(
    array('lead_date' => '2026-06-01', 'hour_of_day' => 9, 'lead_count' => 4),
    array('lead_date' => '2026-06-01', 'hour_of_day' => 10, 'lead_count' => 7),
    array('lead_date' => '2026-06-02', 'hour_of_day' => 9, 'lead_count' => 3),
), $dates);

assert_eq('24 hour columns', 24, count($matrix['hours']));
assert_eq('first hour col label', '12 AM', $matrix['hours'][0]['label']);
assert_eq('two date rows', 2, count($matrix['rows']));
assert_eq('row 1 date label', '01 Jun 2026 (Mon)', $matrix['rows'][0]['date_label']);
assert_eq('row 1 hour 9 count', 4, $matrix['rows'][0]['counts'][9]);
assert_eq('row 1 empty hour 0', 0, $matrix['rows'][0]['counts'][0]);
assert_eq('row 1 total', 11, $matrix['rows'][0]['total']);
assert_eq('row 2 total', 3, $matrix['rows'][1]['total']);

// ---------------------------------------------------------------------------
// 4. Totals: per-hour column totals + grand total
// ---------------------------------------------------------------------------
assert_eq('hour 9 column total', 7, $matrix['hour_totals'][9]);
assert_eq('hour 10 column total', 7, $matrix['hour_totals'][10]);
assert_eq('empty hour column total', 0, $matrix['hour_totals'][0]);
assert_eq('grand total', 14, $matrix['grand_total']);

// ---------------------------------------------------------------------------
// 5. Empty day with no leads still renders as a zero row
// ---------------------------------------------------------------------------
$emptyDates = leads_by_hour_expand_dates('2026-07-01', '2026-07-01');
$emptyMatrix = leads_by_hour_build_matrix(array(), $emptyDates);
assert_eq('empty day row present', 1, count($emptyMatrix['rows']));
assert_eq('empty day total zero', 0, $emptyMatrix['rows'][0]['total']);
assert_eq('empty grand total', 0, $emptyMatrix['grand_total']);

// ---------------------------------------------------------------------------
// 6. Defensive: rows for an out-of-range date/hour are ignored, objects work
// ---------------------------------------------------------------------------
$objRow = new stdClass();
$objRow->lead_date = '2026-06-01';
$objRow->hour_of_day = 9;
$objRow->lead_count = 2;
$defensive = leads_by_hour_build_matrix(array(
    $objRow,
    array('lead_date' => '2099-01-01', 'hour_of_day' => 9, 'lead_count' => 99), // out of range date
    array('lead_date' => '2026-06-01', 'hour_of_day' => 30, 'lead_count' => 5),  // bad hour
), leads_by_hour_expand_dates('2026-06-01', '2026-06-01'));
assert_eq('object row counted', 2, $defensive['rows'][0]['counts'][9]);
assert_eq('out-of-range date ignored, bad hour ignored', 2, $defensive['grand_total']);

echo "\nAll LeadsByHourMatrix tests passed.\n";
