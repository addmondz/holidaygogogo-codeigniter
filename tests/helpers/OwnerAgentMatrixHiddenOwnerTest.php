<?php
/**
 * Run with: php tests/helpers/OwnerAgentMatrixHiddenOwnerTest.php
 *
 * Locks owner_agent_matrix_hidden_owner_ids() — the pure rule the Booking
 * controller uses to decide which OWNER (Level-10) admins are anchored-but-hidden
 * on the owner per-agent matrix.
 *
 * Rule verified:
 *   - An owner (Level 10) with NO own sales in the window stays hidden (anchors
 *     the benchmark, dropped from the rendered rows).
 *   - An owner (Level 10) who personally SOLD in the window (their AdminID is in
 *     the sold set — own-account credited sales only) is NOT hidden, so they show
 *     as a normal agent row.
 *   - Sales agents (20 / 50) and the TC Lead (25) are never hidden by this rule,
 *     regardless of whether they sold.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}
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

function admin_row($id, $level) {
    $o = new stdClass();
    $o->AdminID = $id;
    $o->Level   = $level;
    return $o;
}

echo "OwnerAgentMatrixHiddenOwnerTest\n";

// Roster: two owners (1 sold, 2 did not), a sales agent, a super agent, a TC Lead.
$roster = array(
    admin_row(1, '10'),  // owner SIMON — sold this window
    admin_row(2, '10'),  // owner CHIN TENG — no own sales this window
    admin_row(3, '20'),  // sales agent
    admin_row(4, '50'),  // super agent — no sales this window
    admin_row(5, '25'),  // TC Lead
);
// Own-window credited sales present for owner 1 and agent 3 only.
$sold = array(1 => true, 3 => true);

$hidden = owner_agent_matrix_hidden_owner_ids($roster, $sold);

assert_eq('selling owner (1) NOT hidden',      false, isset($hidden[1]));
assert_eq('non-selling owner (2) hidden',      true,  isset($hidden[2]));
assert_eq('sales agent (3) never hidden',      false, isset($hidden[3]));
assert_eq('non-selling super agent (4) not hidden', false, isset($hidden[4]));
assert_eq('TC Lead (5) never hidden',          false, isset($hidden[5]));
assert_eq('exactly one owner hidden',          1,     count($hidden));

// Empty sold set: every owner hidden (original behaviour — nobody sold).
$hiddenNone = owner_agent_matrix_hidden_owner_ids($roster, array());
assert_eq('no sales: both owners hidden', true, isset($hiddenNone[1]) && isset($hiddenNone[2]));
assert_eq('no sales: only owners hidden', 2, count($hiddenNone));

echo "ALL PASS\n";
