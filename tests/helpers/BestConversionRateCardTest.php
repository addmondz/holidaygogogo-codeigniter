<?php
/**
 * Run with: php tests/helpers/BestConversionRateCardTest.php
 *
 * The Conversion Rate "Compare the Best" sub-line reuses
 * Report_Model::Lead_Dashboard_By_Agent() (already encodes the TC1/TC2 credit
 * rule inside its converted_leads SUM). To keep tests independent of the
 * full Report_Model + GHL fixture surface, the controller funnels the rows
 * returned by Lead_Dashboard_By_Agent() through best_conversion_rate_agent()
 * — a tiny pure-PHP picker that owns the post-processing rules. This test
 * locks that picker.
 *
 * Rules locked here:
 *   - drop the "__unassigned__" bucket
 *   - drop agents with fewer than min_total_leads leads in the window
 *   - sort by conversion_rate DESC, agent_name ASC
 *   - return null when nothing qualifies
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require_once __DIR__ . '/../../application/helpers/best_agent_helper.php';

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

// Fixture: mix of unassigned bucket, low-volume agent (below threshold),
// and two agents tied on conversion_rate.
$rows = array(
    array('agent_id' => '__unassigned__', 'agent_name' => 'Unassigned', 'total_leads' => 50, 'conversion_rate' => 99.0),
    array('agent_id' => 1,                'agent_name' => 'Alice',      'total_leads' => 10, 'conversion_rate' => 60.0),
    array('agent_id' => 2,                'agent_name' => 'Bob',        'total_leads' => 2,  'conversion_rate' => 100.0), /* below threshold */
    array('agent_id' => 3,                'agent_name' => 'Carol',      'total_leads' => 8,  'conversion_rate' => 70.0),
    array('agent_id' => 4,                'agent_name' => 'Daria',      'total_leads' => 5,  'conversion_rate' => 70.0), /* tied with Carol */
);

$best = best_conversion_rate_agent($rows, 3);
assert_eq('best agent name',  'Carol', (string) $best['agent_name']);
assert_eq('best agent rate',  70.0,    (float)  $best['conversion_rate']);

// Without enough valid agents, picker returns null.
$only_low = array(
    array('agent_id' => 1, 'agent_name' => 'Lonely', 'total_leads' => 1, 'conversion_rate' => 100.0),
    array('agent_id' => '__unassigned__', 'agent_name' => 'Unassigned', 'total_leads' => 99, 'conversion_rate' => 100.0),
);
assert_eq('null when no rows qualify', null, best_conversion_rate_agent($only_low, 3));

// Empty input -> null.
assert_eq('null on empty input', null, best_conversion_rate_agent(array(), 3));

// Single qualifying agent -> picked.
$one = array(
    array('agent_id' => 7, 'agent_name' => 'Solo', 'total_leads' => 3, 'conversion_rate' => 33.3),
);
$best = best_conversion_rate_agent($one, 3);
assert_eq('single agent picked', 'Solo', (string) $best['agent_name']);

echo "\nAll assertions passed.\n";
