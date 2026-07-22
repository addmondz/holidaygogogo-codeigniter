<?php
/**
 * Run with: php tests/helpers/OwnerAgentMatrixMergeTest.php
 *
 * Locks owner_agent_matrix_build() — the pure fold behind the OWNER per-agent
 * matrix. The Booking controller fetches the seven per-agent sources (DB), then
 * hands them to this helper which folds GHL-keyed and admin-keyed metrics onto
 * one row per credited AdminID, derives each of the 11 metric values, and grafts
 * the composite Agent Score from agent_score_helper.
 *
 * Rules verified:
 *   - GHL-keyed and admin-keyed rows for the same AdminID land on ONE matrix row.
 *   - A multi-inbox agent (two GHL uids -> one AdminID) aggregates with reply /
 *     pickup correctly lead/n-weighted.
 *   - Unmapped GHL uid and '__unassigned__' rows are dropped.
 *   - Gated vs ungated conversion are tracked independently and can differ.
 *   - An admin-keyed-only agent (sales/cancellation, no GHL leads) still appears.
 *   - Every included agent named in $name_by_admin shows a row even with NO
 *     activity in the window; metrics with no underlying source fold to null so
 *     the view renders "—" (a genuine measured 0 still shows as 0).
 *   - Owner-excluded agents are DROPPED from the rows entirely (like hidden),
 *     matching their removal from the TC leaderboard.
 *   - agent_score equals agent_score_compute() for the same derived inputs.
 *   - Rows sorted by agent_score DESC, then name ASC.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}
require __DIR__ . '/../../application/helpers/agent_score_helper.php';
require __DIR__ . '/../../application/helpers/owner_agent_matrix_helper.php';

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got "      . var_export($actual, true) . "\n";
        exit(1);
    }
}

// Jane (10) owns two GHL inboxes (UID-A, UID-A2). Ben (11) owns UID-B.
// Carol (12) has booking-side sales/cancellation only (no GHL mapping needed).
$map = array('UID-A' => 10, 'UID-A2' => 10, 'UID-B' => 11);
$name_by_admin = array(10 => 'Jane', 11 => 'Ben');

$sources = array(
    // Ungated leads -> metrics 3 (new leads), 6 (ungated conversion).
    'leads_ungated' => array(
        array('agent_id' => 'UID-A',  'total_leads' => 10, 'converted_leads' => 5),
        array('agent_id' => 'UID-A2', 'total_leads' => 10, 'converted_leads' => 3),
        array('agent_id' => 'UID-B',  'total_leads' => 4,  'converted_leads' => 1),
        array('agent_id' => '__unassigned__', 'total_leads' => 100, 'converted_leads' => 50),
        array('agent_id' => 'UID-X',  'total_leads' => 7,  'converted_leads' => 7), // unmapped
    ),
    // Gated leads -> metric 5 (gated conversion numerator only).
    'leads_gated' => array(
        array('agent_id' => 'UID-A',  'converted_leads' => 4),
        array('agent_id' => 'UID-A2', 'converted_leads' => 2),
        array('agent_id' => 'UID-B',  'converted_leads' => 0),
    ),
    // Reply time (Message-Log) -> metric 1. Weighted by total_leads per uid.
    'reply' => array(
        array('agent_id' => 'UID-A',  'avg_response_time_seconds' => 100, 'total_leads' => 10),
        array('agent_id' => 'UID-A2', 'avg_response_time_seconds' => 200, 'total_leads' => 10),
        array('agent_id' => 'UID-B',  'avg_response_time_seconds' => 50,  'total_leads' => 4),
    ),
    // Pickup speed -> metric 2.
    'pickup' => array(
        array('agent_id' => 'UID-A',  'avg_seconds' => 120, 'n' => 6),
        array('agent_id' => 'UID-A2', 'avg_seconds' => 80,  'n' => 4),
        array('agent_id' => 'UID-B',  'avg_seconds' => 60,  'n' => 2),
    ),
    // Ownership -> metric 4 (served) + metric 9 (follow-up).
    'followup' => array(
        array('owner_user_id' => 'UID-A',  'owned_leads' => 12, 'follow_up_leads' => 9, 'owner_name' => 'Jane'),
        array('owner_user_id' => 'UID-A2', 'owned_leads' => 8,  'follow_up_leads' => 5, 'owner_name' => 'Jane'),
        array('owner_user_id' => 'UID-B',  'owned_leads' => 4,  'follow_up_leads' => 1, 'owner_name' => 'Ben'),
    ),
    // Outbound -> metric 7.
    'outbound' => array(
        array('agent_id' => 'UID-A',  'outbound_count' => 300),
        array('agent_id' => 'UID-A2', 'outbound_count' => 200),
        array('agent_id' => 'UID-B',  'outbound_count' => 50),
    ),
    // Sales (admin-keyed) -> metric 8.
    'sales' => array(
        array('admin_id' => 10, 'agent_name' => 'Jane',  'total_sales' => 50000),
        array('admin_id' => 11, 'agent_name' => 'Ben',   'total_sales' => 10000),
        array('admin_id' => 12, 'agent_name' => 'Carol', 'total_sales' => 30000),
    ),
    // Cancellation (admin-keyed) -> metric 10.
    'cancellation' => array(
        array('admin_id' => 10, 'total' => 20, 'cancelled' => 1),
        array('admin_id' => 11, 'total' => 4,  'cancelled' => 1),
        array('admin_id' => 12, 'total' => 5,  'cancelled' => 0),
    ),
);

$matrix = owner_agent_matrix_build($sources, $map, $name_by_admin);

// Index by admin for assertions.
$by = array();
foreach ($matrix as $r) { $by[$r['admin_id']] = $r; }

// Unmapped (UID-X) and __unassigned__ dropped; Jane/Ben/Carol remain.
assert_eq('three agents', 3, count($matrix));
assert_eq('UID-X dropped (no admin 0)', false, isset($by[0]));

// --- Jane: multi-inbox aggregation ---
assert_eq('Jane name',          'Jane', $by[10]['agent_name']);
assert_eq('Jane new_leads',     20,     $by[10]['new_leads']);          // 10+10
assert_eq('Jane reply weighted',150,    $by[10]['reply_secs']);         // (100*10+200*10)/20
assert_eq('Jane pickup weighted',104,   $by[10]['pickup_secs']);        // (120*6+80*4)/10
assert_eq('Jane pickup_n',      10,      $by[10]['pickup_n']);
assert_eq('Jane served_leads',  20,     $by[10]['served_leads']);       // 12+8
assert_eq('Jane outbound',      500,    $by[10]['outbound_count']);     // 300+200
assert_eq('Jane sales',         50000.0,$by[10]['sales_total']);

// Gated vs ungated conversion differ (8/20 vs 6/20).
assert_eq('Jane conv ungated',  40.0,   $by[10]['conv_rate_ungated']);  // (5+3)/20
assert_eq('Jane conv gated',    30.0,   $by[10]['conv_rate_gated']);    // (4+2)/20
assert_eq('Jane followup',      70.0,   $by[10]['followup_rate']);      // 14/20
assert_eq('Jane cancel',        5.0,    $by[10]['cancel_rate']);        // 1/20

// --- Carol: admin-keyed only, still present; no-data metrics dash (null) but
//     her measured 0 cancellation (0 of 5) still shows as 0.0 ---
assert_eq('Carol name from sales row', 'Carol', $by[12]['agent_name']);
assert_eq('Carol new_leads null (no leads source)', null, $by[12]['new_leads']);
assert_eq('Carol reply null',      null, $by[12]['reply_secs']);
assert_eq('Carol pickup null',     null, $by[12]['pickup_secs']);
assert_eq('Carol conv ungated null (no leads)', null, $by[12]['conv_rate_ungated']);
assert_eq('Carol sales',           30000.0, $by[12]['sales_total']);
assert_eq('Carol cancel 0 (0/5, source present)',  0.0,  $by[12]['cancel_rate']);

// --- agent_score must equal agent_score_compute() on the same derived inputs ---
$expected = agent_score_compute(array(
    array('admin_id' => 10, 'name' => 'Jane',  'reply_secs' => 150, 'pickup_secs' => 104, 'conv_rate' => 40.0, 'sales' => 50000, 'followup_rate' => 70.0, 'served_leads' => 20, 'pickup_n' => 10, 'leads_n' => 20, 'owned_n' => 20),
    array('admin_id' => 11, 'name' => 'Ben',   'reply_secs' => 50,  'pickup_secs' => 60,  'conv_rate' => 25.0, 'sales' => 10000, 'followup_rate' => 25.0, 'served_leads' => 4,  'pickup_n' => 2,  'leads_n' => 4,  'owned_n' => 4),
    array('admin_id' => 12, 'name' => 'Carol', 'reply_secs' => null,'pickup_secs' => null,'conv_rate' => null, 'sales' => 30000, 'followup_rate' => null, 'served_leads' => null,'pickup_n' => 0,  'leads_n' => 0,  'owned_n' => 0),
));
assert_eq('Jane score matches compute',  $expected['by_admin'][10]['composite'], $by[10]['agent_score']);
assert_eq('Ben score matches compute',   $expected['by_admin'][11]['composite'], $by[11]['agent_score']);
assert_eq('Carol score matches compute', $expected['by_admin'][12]['composite'], $by[12]['agent_score']);

// --- sorted by score DESC, then name ASC ---
assert_eq('row0 is top scorer', $expected['top']['admin_id'], $matrix[0]['admin_id']);
$scores = array_map(function ($r) { return $r['agent_score']; }, $matrix);
$sorted = $scores;
rsort($sorted);
assert_eq('rows are score-descending', $sorted, $scores);

// --- benchmark scoping: exclude Jane (10) from the 100-anchors (as if Level 50);
//     she is still scored/shown, but only Ben & Carol set the anchors. Mirrors the
//     owner matrix's "benchmark against the Level-20 pool only" rule. ---
$matrixB = owner_agent_matrix_build($sources, $map, $name_by_admin, array(11 => true, 12 => true));
$byB = array();
foreach ($matrixB as $r) { $byB[$r['admin_id']] = $r; }
assert_eq('benchmark scope: all three rows still present', 3, count($matrixB));
$expectedB = agent_score_compute(array(
    array('admin_id' => 10, 'name' => 'Jane',  'reply_secs' => 150, 'pickup_secs' => 104, 'conv_rate' => 40.0, 'sales' => 50000, 'followup_rate' => 70.0, 'served_leads' => 20, 'pickup_n' => 10, 'leads_n' => 20, 'owned_n' => 20, 'benchmark' => false),
    array('admin_id' => 11, 'name' => 'Ben',   'reply_secs' => 50,  'pickup_secs' => 60,  'conv_rate' => 25.0, 'sales' => 10000, 'followup_rate' => 25.0, 'served_leads' => 4,  'pickup_n' => 2,  'leads_n' => 4,  'owned_n' => 4,  'benchmark' => true),
    array('admin_id' => 12, 'name' => 'Carol', 'reply_secs' => null,'pickup_secs' => null,'conv_rate' => null, 'sales' => 30000, 'followup_rate' => null, 'served_leads' => null,'pickup_n' => 0,  'leads_n' => 0,  'owned_n' => 0,  'benchmark' => true),
));
assert_eq('benchmark scope: Jane scored vs L20 anchors',  $expectedB['by_admin'][10]['composite'], $byB[10]['agent_score']);
assert_eq('benchmark scope: Ben score',                   $expectedB['by_admin'][11]['composite'], $byB[11]['agent_score']);
// Sales anchor now excludes Jane's 50000 -> Carol's 30000 is the anchor (norm 100).
assert_eq('benchmark scope: Jane excluded from sales anchor (capped 100)', 100.0, $byB[10]['agent_score'] === null ? null : $expectedB['by_admin'][10]['norm']['sales']);

// --- compute_score = false (the Year period, which has no reply / follow-up /
//     served source): no scoring is done, every agent_score is null, and rows
//     fall back to name-ASC order. ---
$matrixNS = owner_agent_matrix_build($sources, $map, $name_by_admin, null, false);
$nsScores = array_map(function ($r) { return $r['agent_score']; }, $matrixNS);
$nsNames  = array_map(function ($r) { return $r['agent_name']; }, $matrixNS);
assert_eq('no-score: every agent_score is null', array(null, null, null), $nsScores);
$sortedNames = $nsNames; sort($sortedNames);
assert_eq('no-score: rows fall back to name-ASC', $sortedNames, $nsNames);
// Raw metric columns are still computed (only the Score is skipped).
$nsBy = array(); foreach ($matrixNS as $r) { $nsBy[$r['admin_id']] = $r; }
assert_eq('no-score: New Leads still computed', 20, $nsBy[10]['new_leads']);
assert_eq('no-score: Outbound still computed',  500, $nsBy[10]['outbound_count']);

// --- owner exclusion: Jane (10) is on the owner's exclude list. She is DROPPED
//     from the matrix rows entirely (like the TC leaderboard) and no longer
//     anchors the benchmark — so Carol's 30000 becomes the sales anchor and the
//     remaining agents score exactly as if Jane were absent. ---
$matrixX = owner_agent_matrix_build($sources, $map, $name_by_admin, null, true, array(10 => true));
$byX = array(); foreach ($matrixX as $r) { $byX[$r['admin_id']] = $r; }
assert_eq('exclusion: Jane dropped from rows', false, isset($byX[10]));
assert_eq('exclusion: two rows remain (Ben, Carol)', 2, count($matrixX));
$expectedX = agent_score_compute(array(
    array('admin_id' => 11, 'name' => 'Ben',   'reply_secs' => 50,  'pickup_secs' => 60,  'conv_rate' => 25.0, 'sales' => 10000, 'followup_rate' => 25.0, 'served_leads' => 4,  'pickup_n' => 2,  'leads_n' => 4,  'owned_n' => 4),
    array('admin_id' => 12, 'name' => 'Carol', 'reply_secs' => null,'pickup_secs' => null,'conv_rate' => null, 'sales' => 30000, 'followup_rate' => null, 'served_leads' => null,'pickup_n' => 0,  'leads_n' => 0,  'owned_n' => 0),
));
assert_eq('exclusion: Ben re-anchored without Jane',   $expectedX['by_admin'][11]['composite'], $byX[11]['agent_score']);
assert_eq('exclusion: Carol re-anchored without Jane', $expectedX['by_admin'][12]['composite'], $byX[12]['agent_score']);

// --- hidden agents: Jane (10) is included in the score CALCULATION but NOT
//     shown (Owner / TC Lead rule). She anchors the benchmark (her 50000 sales
//     keep setting the bar) yet is dropped from the matrix rows entirely. Ben &
//     Carol remain as rows, scored against the pool that still includes Jane. ---
$matrixH = owner_agent_matrix_build($sources, $map, $name_by_admin, null, true, null, array(10 => true));
$byH = array(); foreach ($matrixH as $r) { $byH[$r['admin_id']] = $r; }
assert_eq('hidden: Jane dropped from rows', false, isset($byH[10]));
assert_eq('hidden: two rows remain (Ben, Carol)', 2, count($matrixH));
// Jane still anchored, so Ben & Carol score exactly as in the full (shown) build.
assert_eq('hidden: Ben scored vs pool incl. hidden Jane',   $by[11]['agent_score'], $byH[11]['agent_score']);
assert_eq('hidden: Carol scored vs pool incl. hidden Jane', $by[12]['agent_score'], $byH[12]['agent_score']);

// --- Served display override: when a 'responded' source is supplied, the SERVED
//     column shows those distinct-leads-replied counts (Lead Reply Activity
//     "Lead Responded"), while Follow-up % and the Agent Score keep using
//     owned-leads (fu_owned) unchanged. Display-only. ---
$sourcesR = $sources;
$sourcesR['responded'] = array(
    array('admin_id' => 10, 'responded_leads' => 33), // Jane: owned 20, replied 33
    array('admin_id' => 11, 'responded_leads' => 2),  // Ben:  owned 4,  replied 2
    // Carol (12): no responded row -> Served dashes (null, she replied to nothing).
);
$matrixR = owner_agent_matrix_build($sourcesR, $map, $name_by_admin);
$byR = array(); foreach ($matrixR as $r) { $byR[$r['admin_id']] = $r; }
assert_eq('responded: Jane Served = responded (not owned)', 33, $byR[10]['served_leads']);
assert_eq('responded: Ben Served = responded',              2,  $byR[11]['served_leads']);
assert_eq('responded: Carol Served = null (no replies)',    null, $byR[12]['served_leads']);
// Follow-up % unchanged — still owned-based (Jane 14/20 = 70%).
assert_eq('responded: Jane Follow-up % still owned-based',  70.0, $byR[10]['followup_rate']);
// Agent Score unchanged — served input still owned-leads, so score equals the
// no-responded build for the same agent.
assert_eq('responded: Jane Agent Score unchanged', $by[10]['agent_score'], $byR[10]['agent_score']);
assert_eq('responded: Ben Agent Score unchanged',  $by[11]['agent_score'], $byR[11]['agent_score']);

// Without a 'responded' source, Served falls back to owned-leads (legacy).
assert_eq('no responded source: Jane Served falls back to owned', 20, $by[10]['served_leads']);

// --- Day / Week shape: those windows carry NO gated-conversion or cancellation
//     source (both Year-only), but DO carry sales now, and score is computed. The
//     Agent Score must still be a real (non-null) number for eligible agents, and
//     since Sales (45% of the composite) is present it must not collapse to the
//     ~55 ceiling a sales-less build would hit. Guards the "Score on Day/Week"
//     behaviour that the Booking controller now enables. ---
$sourcesDW = $sources;
unset($sourcesDW['leads_gated'], $sourcesDW['cancellation']); // absent on Day/Week
$matrixDW = owner_agent_matrix_build($sourcesDW, $map, $name_by_admin, null, true);
$dwBy = array(); foreach ($matrixDW as $r) { $dwBy[$r['admin_id']] = $r; }
assert_eq('day/week: Jane Agent Score is non-null', true, $dwBy[10]['agent_score'] !== null);
assert_eq('day/week: Jane score exceeds the sales-less 55 ceiling', true, (float) $dwBy[10]['agent_score'] > 55.0);
// Gated conversion still 0 (leads source present, just no gated conv); cancellation
// dashes (null) because its source is absent. Score stays real either way.
assert_eq('day/week: gated conversion 0 (leads present, no conv)', 0.0, $dwBy[10]['conv_rate_gated']);
assert_eq('day/week: cancellation dash without source',           null, $dwBy[10]['cancel_rate']);

// --- Full roster: an INCLUDED agent with NO activity in the window still shows a
//     row so the matrix lists the same agents every period; every metric folds to
//     null (=> the view renders "—") and, being ineligible, the agent is not scored.
//     Dave (13) is named in the scored pool but appears in none of the sources. ---
$nameRoster = array(10 => 'Jane', 11 => 'Ben', 13 => 'Dave');
$matrixFull = owner_agent_matrix_build($sources, $map, $nameRoster);
$byFull = array(); foreach ($matrixFull as $r) { $byFull[$r['admin_id']] = $r; }
assert_eq('roster: zero-activity Dave still shown', true, isset($byFull[13]));
assert_eq('roster: Dave name',            'Dave', $byFull[13]['agent_name']);
assert_eq('roster: Dave new_leads dash',  null,   $byFull[13]['new_leads']);
assert_eq('roster: Dave served dash',     null,   $byFull[13]['served_leads']);
assert_eq('roster: Dave sales dash',      null,   $byFull[13]['sales_total']);
assert_eq('roster: Dave cancel dash',     null,   $byFull[13]['cancel_rate']);
assert_eq('roster: Dave outbound dash',   null,   $byFull[13]['outbound_count']);
assert_eq('roster: Dave reply dash',      null,   $byFull[13]['reply_secs']);
assert_eq('roster: Dave Agent Score dash (not scored)', null, $byFull[13]['agent_score']);
// Carol (12) still appears via her admin-keyed sales/cancellation source even
// though she is not named in this roster.
assert_eq('roster: Carol still present via source', true, isset($byFull[12]));

// --- Score breakdown: each scored row carries the six normalised 0-100 criteria
//     (score_norm) that the owner's Score-cell popover renders. It must equal the
//     norm agent_score_compute() produced, and an unscored agent's breakdown is
//     null (nothing to show). ---
assert_eq('breakdown: Jane score_norm equals compute norm',
    $expected['by_admin'][10]['norm'], $by[10]['score_norm']);
assert_eq('breakdown: Jane norm has six criteria', 6, count($by[10]['score_norm']));
assert_eq('breakdown: Jane weighted norm sums to composite',
    $by[10]['agent_score'],
    round(
        0.15 * $by[10]['score_norm']['reply']
      + 0.10 * $by[10]['score_norm']['pickup']
      + 0.10 * $by[10]['score_norm']['conv']
      + 0.45 * $by[10]['score_norm']['sales']
      + 0.10 * $by[10]['score_norm']['followup']
      + 0.10 * $by[10]['score_norm']['served'],
    1));
// Zero-activity Dave (unscored) has no breakdown to render.
assert_eq('breakdown: unscored Dave score_norm null', null, $byFull[13]['score_norm']);
// Score skipped entirely (Year): breakdown null even for active agents.
assert_eq('breakdown: no-score build score_norm null', null, $nsBy[10]['score_norm']);

// --- Score calc: each scored row also carries the RAW value + benchmark (best)
//     per criterion, so the popover can show the numbers and the arithmetic. The
//     value is the exact score input (served = owned-leads), and best equals the
//     anchors agent_score_compute() reported. ---
assert_eq('calc: Jane reply value = weighted avg reply', 150.0, $by[10]['score_calc']['reply']['value']);
assert_eq('calc: Jane served value = owned-leads (not displayed Served)', 20, $by[10]['score_calc']['served']['value']);
assert_eq('calc: Jane sales value', 50000.0, $by[10]['score_calc']['sales']['value']);
// Best anchors: reply is the fastest (min) time; sales/served the highest (max).
assert_eq('calc: reply best = fastest (Ben 50)',  $expected['anchors']['reply'],  $by[10]['score_calc']['reply']['best']);
assert_eq('calc: sales best = highest (Jane 50000)', $expected['anchors']['sales'], $by[10]['score_calc']['sales']['best']);
assert_eq('calc: served best = highest (Jane 20)',   $expected['anchors']['served'], $by[10]['score_calc']['served']['best']);
// value ÷ best × 100 (higher-better) reproduces the shown norm for sales.
assert_eq('calc: sales value/best*100 = norm',
    $by[10]['score_norm']['sales'],
    round($by[10]['score_calc']['sales']['value'] / $by[10]['score_calc']['sales']['best'] * 100, 1));
// best ÷ value × 100 (lower-better) reproduces the shown norm for reply.
assert_eq('calc: reply best/value*100 = norm',
    $by[10]['score_norm']['reply'],
    round($by[10]['score_calc']['reply']['best'] / $by[10]['score_calc']['reply']['value'] * 100, 1));
// Carol has no reply data -> value null, but sales value present.
assert_eq('calc: Carol reply value null (no data)', null, $by[12]['score_calc']['reply']['value']);
assert_eq('calc: Carol sales value', 30000.0, $by[12]['score_calc']['sales']['value']);
// Unscored / no-score builds carry no calc either.
assert_eq('calc: unscored Dave score_calc null', null, $byFull[13]['score_calc']);
assert_eq('calc: no-score build score_calc null', null, $nsBy[10]['score_calc']);

echo "\nAll assertions passed.\n";
