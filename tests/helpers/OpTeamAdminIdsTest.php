<?php
/**
 * Run with: php tests/helpers/OpTeamAdminIdsTest.php
 *
 * Locks op_team_admin_ids(): the OP summary cards scope to everyone under the
 * same OP TEAM LEAD (admin.OpTeamLeadID, level 45). Example from the spec:
 * Hani (lead) leads Chen + Jesika — each of the three sees all three.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}
require __DIR__ . '/../../application/helpers/op_team_helper.php';

// Active OP-side admins (level 40/45). OpTeamLeadID points members -> their lead.
$row = function ($id, $level, $tl) {
    return (object) array('AdminID' => $id, 'Level' => (string) $level, 'OpTeamLeadID' => $tl);
};
$admins = array(
    $row(1, 45, null), // Hani  — OP TEAM LEAD
    $row(2, 40, 1),    // Chen  — under Hani
    $row(3, 40, 1),    // Jesika — under Hani
    $row(4, 45, null), // other OP TEAM LEAD
    $row(5, 40, 4),    // member under the other lead
    $row(6, 40, null), // loner — no lead
);

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . json_encode($actual) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . json_encode($expected)
           . ", got " . json_encode($actual) . "\n";
        exit(1);
    }
}

// Hani (lead) sees herself + Chen + Jesika.
assert_eq('Hani (lead 45) team',  array(1, 2, 3), op_team_admin_ids(1, 45, $admins));
// Chen (member) sees Hani + herself + Jesika.
assert_eq('Chen (member 40) team', array(1, 2, 3), op_team_admin_ids(2, 40, $admins));
// Jesika (member) — same team.
assert_eq('Jesika (member 40) team', array(1, 2, 3), op_team_admin_ids(3, 40, $admins));
// The other team is isolated.
assert_eq('other lead 45 team', array(4, 5), op_team_admin_ids(4, 45, $admins));
assert_eq('other member 40 team', array(4, 5), op_team_admin_ids(5, 40, $admins));
// A member with no lead is a team of one.
assert_eq('loner 40 team', array(6), op_team_admin_ids(6, 40, $admins));
// Self is always included even when the user row is absent / unknown.
assert_eq('unknown member falls back to self', array(99), op_team_admin_ids(99, 40, $admins));
// A lead with no members still sees themselves.
assert_eq('lead with no members', array(7), op_team_admin_ids(7, 45, $admins));

// Result is always non-empty (guards against an `IN ()` SQL error).
foreach (array(array(1,45), array(2,40), array(6,40), array(7,45)) as $c) {
    $ids = op_team_admin_ids($c[0], $c[1], $admins);
    if (count($ids) < 1) { echo "  FAIL  non-empty for {$c[0]}\n"; exit(1); }
}
echo "  PASS  team set is never empty\n";

echo "\nAll assertions passed.\n";
