<?php
/**
 * Run with: php tests/helpers/LeadReplyHourlyAgentMatrixTest.php
 *
 * Locks the pure layout logic behind the "Lead Reply Hourly — All Agents" page
 * (Report/Lead_Reply_Activity_Hourly_All). The SQL only returns (owner, hour)
 * buckets that actually had traffic, so ghl_message_log_hourly_agent_matrix()
 * must stitch the sparse rows into a full agent x 24-hour grid (Inbound and
 * Outbound sub-series), zero-fill the gaps, carry per-agent / per-hour / grand
 * totals, report the busiest single cell for heatmap shading, and order agents
 * busiest-first.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require_once __DIR__ . '/../../application/helpers/ghl_messages_log_helper.php';

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label}\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

// Two agents, sparse buckets. Ali is busier overall than Siti.
$rows = array(
    array('owner_user_id' => '10', 'owner_name' => 'Ali',  'hour_of_day' => 9,  'inbound_count' => 3, 'outbound_count' => 5),
    array('owner_user_id' => '10', 'owner_name' => 'Ali',  'hour_of_day' => 14, 'inbound_count' => 2, 'outbound_count' => 1),
    array('owner_user_id' => '20', 'owner_name' => 'Siti', 'hour_of_day' => 9,  'inbound_count' => 1, 'outbound_count' => 1),
    // Row with a missing name falls back to the owner id.
    array('owner_user_id' => '30',                          'hour_of_day' => 0,  'inbound_count' => 0, 'outbound_count' => 4),
);

$m = ghl_message_log_hourly_agent_matrix($rows);

// 24 hour columns, labelled on a 12-hour clock.
assert_eq('24 hour columns', 24, count($m['hours']));
assert_eq('hour 0 label', '12 AM', $m['hours'][0]['label']);
assert_eq('hour 9 label', '9 AM', $m['hours'][9]['label']);
assert_eq('hour 14 label', '2 PM', $m['hours'][14]['label']);

// Three agents, busiest first: Ali (11) > agent 30 (4) > Siti (2).
assert_eq('three agents', 3, count($m['agents']));
assert_eq('busiest agent first', 'Ali', $m['agents'][0]['owner_name']);
assert_eq('missing name falls back to id', '30', $m['agents'][1]['owner_name']);
assert_eq('least busy agent last', 'Siti', $m['agents'][2]['owner_name']);

// Ali's grid is zero-filled everywhere except hour 9 and 14.
$ali = $m['agents'][0];
assert_eq('Ali inbound is a 24-slot row', 24, count($ali['inbound']));
assert_eq('Ali inbound @9', 3, $ali['inbound'][9]);
assert_eq('Ali outbound @9', 5, $ali['outbound'][9]);
assert_eq('Ali inbound @14', 2, $ali['inbound'][14]);
assert_eq('Ali outbound @14', 1, $ali['outbound'][14]);
assert_eq('Ali empty hour zero-filled', 0, $ali['inbound'][0]);
assert_eq('Ali total inbound', 5, $ali['total_inbound']);
assert_eq('Ali total outbound', 6, $ali['total_outbound']);
assert_eq('Ali total', 11, $ali['total']);

// Per-hour column totals sum across every agent.
assert_eq('hour 9 inbound total (3+1)', 4, $m['hour_totals_inbound'][9]);
assert_eq('hour 9 outbound total (5+1)', 6, $m['hour_totals_outbound'][9]);
assert_eq('hour 0 outbound total (agent 30)', 4, $m['hour_totals_outbound'][0]);
assert_eq('hour 0 inbound total', 0, $m['hour_totals_inbound'][0]);

// Grand totals.
assert_eq('grand inbound (3+2+1+0)', 6, $m['grand_inbound']);
assert_eq('grand outbound (5+1+1+4)', 11, $m['grand_outbound']);
assert_eq('grand total', 17, $m['grand_total']);

// Busiest single inbound/outbound cell (Ali outbound @9 = 5).
assert_eq('max cell', 5, $m['max_cell']);

// Bad hours and blank owners are dropped, not counted.
$dirty = array(
    array('owner_user_id' => '', 'owner_name' => 'Ghost', 'hour_of_day' => 9, 'inbound_count' => 9, 'outbound_count' => 9),
    array('owner_user_id' => '10', 'owner_name' => 'Ali', 'hour_of_day' => 99, 'inbound_count' => 9, 'outbound_count' => 9),
    array('owner_user_id' => '10', 'owner_name' => 'Ali', 'hour_of_day' => -1, 'inbound_count' => 9, 'outbound_count' => 9),
);
$dm = ghl_message_log_hourly_agent_matrix($dirty);
// Blank owner is dropped; Ali's only rows are out-of-range hours, so no valid
// bucket ever creates his row -- the whole matrix ends up empty.
assert_eq('blank owner dropped, all-bad-hour agent never created', 0, count($dm['agents']));
assert_eq('dirty grand total is zero', 0, $dm['grand_total']);

// Empty input yields an empty-but-shaped matrix.
$empty = ghl_message_log_hourly_agent_matrix(array());
assert_eq('empty: no agents', 0, count($empty['agents']));
assert_eq('empty: still 24 hours', 24, count($empty['hours']));
assert_eq('empty: grand total zero', 0, $empty['grand_total']);
assert_eq('empty: max cell zero', 0, $empty['max_cell']);

echo "\nAll assertions passed.\n";
