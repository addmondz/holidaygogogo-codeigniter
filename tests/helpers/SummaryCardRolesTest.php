<?php
/**
 * Run with: php tests/helpers/SummaryCardRolesTest.php
 *
 * Locks the role gating behind the booking-listing summary cards
 * (application/helpers/summary_card_roles_helper.php). Two page contexts share
 * the same partial, so the gate must key off BOTH the admin level and whether
 * the partial was loaded on the booking listing (`$owner_as_agent = true`) or
 * on the Owner Dashboard (`false`):
 *
 *   - Booking listing: Sales Agent (20), TC (50), Team Lead (25) AND Owner (10)
 *     all see the sales-agent card set; no matrix is shown.
 *   - Dashboard: Owner (10) AND Team Lead (25) see ONLY the per-agent matrix,
 *     not the agent card set (the Team Lead's matrix is team-scoped downstream).
 *   - Other roles (OP 40/45, Finance 30, Marketing 60) never see either set
 *     through this helper.
 */
if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}
require __DIR__ . '/../../application/helpers/summary_card_roles_helper.php';

$failures = 0;
function assert_eq($label, $expected, $actual) {
    global $failures;
    if ($expected === $actual) {
        echo "  PASS  $label\n";
    } else {
        $failures++;
        echo "  FAIL  $label — expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
    }
}

// ---- Booking listing (owner_as_agent = true) ----
echo "Agent card set — booking listing:\n";
assert_eq('Sales Agent (20) sees agent cards',  true,  summary_cards_show_agent_set(20, true));
assert_eq('TC (50) sees agent cards',           true,  summary_cards_show_agent_set(50, true));
assert_eq('TC Lead (25) sees agent cards',      true,  summary_cards_show_agent_set(25, true));
assert_eq('Owner (10) sees agent cards',        true,  summary_cards_show_agent_set(10, true));
assert_eq('OP (40) does NOT see agent cards',   false, summary_cards_show_agent_set(40, true));
assert_eq('OP Lead (45) does NOT',              false, summary_cards_show_agent_set(45, true));
assert_eq('Finance (30) does NOT',              false, summary_cards_show_agent_set(30, true));
assert_eq('Marketing (60) does NOT',            false, summary_cards_show_agent_set(60, true));

// ---- Dashboard (owner_as_agent = false) ----
echo "Agent card set — Dashboard:\n";
assert_eq('Owner (10) does NOT see agent cards on dashboard',     false, summary_cards_show_agent_set(10, false));
assert_eq('Team Lead (25) does NOT see agent cards on dashboard', false, summary_cards_show_agent_set(25, false));
assert_eq('Sales Agent (20) still sees agent cards',              true,  summary_cards_show_agent_set(20, false));

// ---- Owner matrix ----
echo "Owner matrix gate:\n";
assert_eq('Owner (10) sees matrix on dashboard',        true,  summary_cards_show_owner_matrix(10, false));
assert_eq('Owner (10) does NOT see matrix on listing',  false, summary_cards_show_owner_matrix(10, true));
assert_eq('Team Lead (25) never sees OWNER matrix',     false, summary_cards_show_owner_matrix(25, false));
assert_eq('Sales Agent (20) never sees matrix',         false, summary_cards_show_owner_matrix(20, true));

// ---- Team Lead matrix (team-scoped, dashboard only) ----
echo "Team Lead matrix gate:\n";
assert_eq('Team Lead (25) sees matrix on dashboard',        true,  summary_cards_show_team_lead_matrix(25, false));
assert_eq('Team Lead (25) does NOT see matrix on listing',  false, summary_cards_show_team_lead_matrix(25, true));
assert_eq('Owner (10) does NOT use team-lead matrix gate',  false, summary_cards_show_team_lead_matrix(10, false));
assert_eq('Sales Agent (20) never sees team-lead matrix',   false, summary_cards_show_team_lead_matrix(20, false));
assert_eq("Team Lead '25' string on dashboard sees matrix", true,  summary_cards_show_team_lead_matrix('25', false));

// ---- Nobody is in BOTH sets at once (no double render) ----
echo "Mutual exclusivity (agent set vs matrix):\n";
foreach (array(true, false) as $ctx) {
    $owner_both = summary_cards_show_agent_set(10, $ctx) && summary_cards_show_owner_matrix(10, $ctx);
    assert_eq('Owner sees exactly one set (context=' . var_export($ctx, true) . ')', false, $owner_both);
    $tl_both = summary_cards_show_agent_set(25, $ctx) && summary_cards_show_team_lead_matrix(25, $ctx);
    assert_eq('Team Lead sees exactly one set (context=' . var_export($ctx, true) . ')', false, $tl_both);
    // And a Team Lead always sees exactly one of the two (never neither).
    $tl_one = summary_cards_show_agent_set(25, $ctx) || summary_cards_show_team_lead_matrix(25, $ctx);
    assert_eq('Team Lead always sees one set (context=' . var_export($ctx, true) . ')', true, $tl_one);
}

// String levels (session values arrive as strings) behave like ints.
echo "String-level coercion:\n";
assert_eq("Owner '10' string on listing sees agent cards", true, summary_cards_show_agent_set('10', true));

echo "\n" . ($failures === 0 ? "All assertions passed.\n" : "$failures assertion(s) FAILED.\n");
exit($failures === 0 ? 0 : 1);
