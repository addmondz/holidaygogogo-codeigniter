<?php
/**
 * Run with: php tests/helpers/AgentScoreNormalizeTest.php
 *
 * Locks the pure scoring/normalisation behind the TC "Agent Score" card
 * (application/helpers/agent_score_helper.php):
 *
 *   score = reply 30% + pickup 20% + conversion 20% + sales 30%
 *
 * Verified rules:
 *   - The best performer on a metric normalises to 100 (lower-better inverts,
 *     higher-better is a straight ratio).
 *   - A missing/zero metric normalises to 0 (no credit, weights still sum 100%).
 *   - The benchmark anchor respects min-sample (pickup >=2 leads, reply/conv >=3
 *     leads) so a one-lead agent can't define the 100 anchor; sub-min-sample
 *     agents are still scored against it and capped at 100.
 *   - best = 0 (nobody sold / converted) -> that metric is 0 for everyone, no
 *     division-by-zero / NaN reaches the payload.
 *   - Ranking is composite DESC, name ASC tie-break; rank is 1-based positional.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}
require __DIR__ . '/../../application/helpers/agent_score_helper.php';

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

// ---------------------------------------------------------------------------
// Unit: the two normalisers.
// ---------------------------------------------------------------------------
assert_eq('lower: best is 100',            100.0, agent_score_norm_lower(60, 60));
assert_eq('lower: 2x slower is 50',         50.0, agent_score_norm_lower(120, 60));
assert_eq('lower: null -> 0',                0.0, agent_score_norm_lower(null, 60));
assert_eq('lower: faster than anchor caps', 100.0, agent_score_norm_lower(30, 60));
assert_eq('lower: best 0 -> 0',              0.0, agent_score_norm_lower(60, 0));

assert_eq('higher: best is 100',           100.0, agent_score_norm_higher(50, 50));
assert_eq('higher: half is 50',             50.0, agent_score_norm_higher(25, 50));
assert_eq('higher: null -> 0',               0.0, agent_score_norm_higher(null, 50));
assert_eq('higher: above anchor caps',     100.0, agent_score_norm_higher(80, 50));
assert_eq('higher: best 0 -> 0',             0.0, agent_score_norm_higher(50, 0));

// ---------------------------------------------------------------------------
// Composite + ranking.
// Alice is best on every metric -> composite 100, rank 1.
// Bob is exactly half on every metric -> composite 50.
// Carol has sales only -> composite = 30% * (sales ratio).
// ---------------------------------------------------------------------------
$agents = array(
    array('admin_id'=>10,'name'=>'Alice','reply_secs'=>60, 'pickup_secs'=>30, 'conv_rate'=>40,'sales'=>1000,'pickup_n'=>5,'leads_n'=>5),
    array('admin_id'=>20,'name'=>'Bob',  'reply_secs'=>120,'pickup_secs'=>60, 'conv_rate'=>20,'sales'=>500, 'pickup_n'=>5,'leads_n'=>5),
    array('admin_id'=>30,'name'=>'Carol','reply_secs'=>null,'pickup_secs'=>null,'conv_rate'=>null,'sales'=>250,'pickup_n'=>0,'leads_n'=>0),
);
$res = agent_score_compute($agents);

assert_eq('total agents', 3, $res['total']);
assert_eq('rank1 name', 'Alice', $res['top']['name']);
assert_eq('Alice composite 100', 100.0, $res['by_admin'][10]['composite']);
assert_eq('Bob composite 50',     50.0, $res['by_admin'][20]['composite']);
// Carol: only sales 250/1000 = 25 -> *0.30 = 7.5
assert_eq('Carol composite 7.5',   7.5, $res['by_admin'][30]['composite']);
assert_eq('Alice rank', 1, $res['by_admin'][10]['rank']);
assert_eq('Bob rank',   2, $res['by_admin'][20]['rank']);
assert_eq('Carol rank', 3, $res['by_admin'][30]['rank']);

// ---------------------------------------------------------------------------
// Min-sample anchor: a one-lead agent (leads_n/pickup_n below threshold) must
// NOT define the 100 anchor, but is still scored (and capped) against it.
// Eve has the fastest reply (10s) but only 1 lead -> anchor stays Dan's 50s.
// Eve's reply norm = 50/10 = 500 -> capped 100.
// ---------------------------------------------------------------------------
$agents2 = array(
    array('admin_id'=>40,'name'=>'Dan','reply_secs'=>50,'pickup_secs'=>40,'conv_rate'=>30,'sales'=>800,'pickup_n'=>4,'leads_n'=>4),
    array('admin_id'=>50,'name'=>'Eve','reply_secs'=>10,'pickup_secs'=>20,'conv_rate'=>90,'sales'=>100,'pickup_n'=>1,'leads_n'=>1),
);
$res2 = agent_score_compute($agents2);
assert_eq('anchor ignores Eve: Dan reply norm 100', 100.0, $res2['by_admin'][40]['norm']['reply']);
assert_eq('Eve reply capped at 100', 100.0, $res2['by_admin'][50]['norm']['reply']);
assert_eq('anchor ignores Eve: Dan conv norm 100',  100.0, $res2['by_admin'][40]['norm']['conv']);

// ---------------------------------------------------------------------------
// Division-by-zero: nobody sold and nobody converted -> those metrics 0 for all.
// Only reply/pickup carry weight (0.30 + 0.20 = 0.50). Frank best on both -> 50.
// ---------------------------------------------------------------------------
$agents3 = array(
    array('admin_id'=>60,'name'=>'Frank','reply_secs'=>30,'pickup_secs'=>15,'conv_rate'=>0,'sales'=>0,'pickup_n'=>3,'leads_n'=>3),
    array('admin_id'=>70,'name'=>'Gina', 'reply_secs'=>60,'pickup_secs'=>30,'conv_rate'=>0,'sales'=>0,'pickup_n'=>3,'leads_n'=>3),
);
$res3 = agent_score_compute($agents3);
assert_eq('no sales/conv: Frank composite 50', 50.0, $res3['by_admin'][60]['composite']);
assert_eq('no sales/conv: Gina composite 25',  25.0, $res3['by_admin'][70]['composite']);

// ---------------------------------------------------------------------------
// Tie-break: equal composite -> name ASC. Both identical metrics -> Hank before Ivy.
// ---------------------------------------------------------------------------
$agents4 = array(
    array('admin_id'=>80,'name'=>'Ivy', 'reply_secs'=>40,'pickup_secs'=>40,'conv_rate'=>40,'sales'=>400,'pickup_n'=>3,'leads_n'=>3),
    array('admin_id'=>90,'name'=>'Hank','reply_secs'=>40,'pickup_secs'=>40,'conv_rate'=>40,'sales'=>400,'pickup_n'=>3,'leads_n'=>3),
);
$res4 = agent_score_compute($agents4);
assert_eq('tie-break rank1 name', 'Hank', $res4['top']['name']);
assert_eq('tie-break Hank rank', 1, $res4['by_admin'][90]['rank']);
assert_eq('tie-break Ivy rank',  2, $res4['by_admin'][80]['rank']);

// Empty input -> empty, no top.
$res5 = agent_score_compute(array());
assert_eq('empty total', 0, $res5['total']);
assert_eq('empty top', null, $res5['top']);

echo "\nAll assertions passed.\n";
