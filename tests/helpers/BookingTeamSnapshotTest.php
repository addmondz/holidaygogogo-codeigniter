<?php
/**
 * Run with: php tests/helpers/BookingTeamSnapshotTest.php
 *
 * Locks booking_team_snapshot() (application/helpers/booking_team_snapshot_helper.php).
 *
 * A credited sale must stay attributed to the team its agent (SalesAgent / TC1)
 * belonged to AT THE TIME of the sale — so an agent who later switches team or
 * leaves the company does NOT retroactively move this year's sale out of the
 * team that earned it (which would corrupt the year-over-year benchmark).
 *
 * The helper decides the TeamID to persist on a booking row when it is written:
 *   - CREATE / agent first assigned / agent changed -> take a fresh snapshot of
 *     the (new) agent's CURRENT team.
 *   - UPDATE with an unchanged credited agent -> FREEZE: leave booking.TeamID
 *     untouched, even if that agent has since moved team. This is the whole
 *     point — an unrelated edit must not silently re-team an old sale.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require __DIR__ . '/../../application/helpers/booking_team_snapshot_helper.php';

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label}\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

// 1. CREATE with a real agent in Team 5 -> snapshot Team 5.
$d = booking_team_snapshot(11, null, 5);
assert_eq('create: writes snapshot',      true, $d['write']);
assert_eq('create: team is agent team',   5,    $d['team_id']);

// 2. CREATE with no agent yet (TC1 assigned later, posts 0) -> unassigned.
$d = booking_team_snapshot(0, null, null);
assert_eq('create no agent: writes',       true, $d['write']);
assert_eq('create no agent: team null',    null, $d['team_id']);

// 3. CREATE with a real agent who is in no team -> unassigned.
$d = booking_team_snapshot(11, null, null);
assert_eq('create teamless agent: null',   null, $d['team_id']);

// 4. UPDATE assigning TC1 for the first time (0 -> 11, agent now in Team 5).
$d = booking_team_snapshot(11, 0, 5);
assert_eq('assign TC1: writes',            true, $d['write']);
assert_eq('assign TC1: snapshots Team 5',  5,    $d['team_id']);

// 5. FREEZE: UPDATE with the SAME credited agent (11 -> 11). The agent has
//    since moved to Team 9, but an unrelated edit must NOT re-team the sale.
$d = booking_team_snapshot(11, 11, 9);
assert_eq('unchanged agent: does not write', false, $d['write']);
assert_eq('unchanged agent: no team value',  null,  $d['team_id']);

// 6. FREEZE holds even when ids arrive as strings from the POST payload.
$d = booking_team_snapshot('11', '11', 9);
assert_eq('unchanged agent (string ids): freeze', false, $d['write']);

// 7. RE-CREDIT: credited agent actually changes (11 -> 21, in Team 2) ->
//    re-snapshot to the new agent's current team.
$d = booking_team_snapshot(21, 11, 2);
assert_eq('agent changed: writes',         true, $d['write']);
assert_eq('agent changed: new team',       2,    $d['team_id']);

// 8. RE-CREDIT to unassigned: agent cleared (11 -> 0) -> snapshot null.
$d = booking_team_snapshot(0, 11, null);
assert_eq('agent cleared: writes',         true, $d['write']);
assert_eq('agent cleared: team null',      null, $d['team_id']);

echo "\nAll assertions passed.\n";
