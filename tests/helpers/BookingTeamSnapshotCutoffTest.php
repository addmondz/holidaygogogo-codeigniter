<?php
/**
 * Run with: php tests/helpers/BookingTeamSnapshotCutoffTest.php
 *
 * Locks booking_team_snapshot_by_credit() — the write-time team snapshot AFTER
 * layering the TC1/TC2 Jun-1 cutoff on top of booking_team_snapshot().
 *
 * The team a sale is frozen to must follow the SAME credited agent that the rest
 * of the app uses: TC1 (booking.SalesAgent) for bookings BEFORE 2026-06-01, and
 * TC2 (booking.SalesAgent2) on/after it. So a post-cutoff sale is credited to the
 * TC2's team, and editing TC1 on that booking must NOT re-team it (the credited
 * agent — TC2 — did not change).
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require __DIR__ . '/../../application/helpers/lead_conversion_credit_helper.php';
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

// Teams (looked up by the model): TC1(11)=Team 5, TC2(22)=Team 9, TC2(44)=Team 2.

// 1. PRE-cutoff CREATE -> credited agent is TC1 (11) -> snapshot TC1's team (5),
//    even though a TC2 is also set.
$d = booking_team_snapshot_by_credit('2026-05-20', 11, 22, null, /*credit_team*/ 5);
assert_eq('pre-cutoff create: writes',        true, $d['write']);
assert_eq('pre-cutoff create: credited TC1',  11,   $d['credited']);
assert_eq('pre-cutoff create: TC1 team',      5,    $d['team_id']);

// 2. POST-cutoff CREATE -> credited agent is TC2 (22) -> snapshot TC2's team (9).
$d = booking_team_snapshot_by_credit('2026-06-15', 11, 22, null, /*credit_team*/ 9);
assert_eq('post-cutoff create: credited TC2', 22,   $d['credited']);
assert_eq('post-cutoff create: TC2 team',     9,    $d['team_id']);

// 3. POST-cutoff, edit TC1 only (11 -> 33), TC2 unchanged (22) -> credited agent
//    (TC2=22) did NOT change -> FREEZE, do not re-team.
$prev = array('InsertDate' => '2026-06-15', 'SalesAgent' => 11, 'SalesAgent2' => 22);
$d = booking_team_snapshot_by_credit('2026-06-15', 33, 22, $prev, /*credit_team*/ 9);
assert_eq('post-cutoff edit TC1: freeze',     false, $d['write']);

// 4. POST-cutoff, edit TC2 (22 -> 44, in Team 2) -> credited agent changed ->
//    re-snapshot to TC2's current team (2).
$d = booking_team_snapshot_by_credit('2026-06-15', 11, 44, $prev, /*credit_team*/ 2);
assert_eq('post-cutoff edit TC2: writes',     true, $d['write']);
assert_eq('post-cutoff edit TC2: new team',   2,    $d['team_id']);

// 5. PRE-cutoff, edit TC2 only -> credited agent is TC1 (unchanged) -> FREEZE.
$prev_pre = array('InsertDate' => '2026-05-20', 'SalesAgent' => 11, 'SalesAgent2' => 22);
$d = booking_team_snapshot_by_credit('2026-05-20', 11, 99, $prev_pre, /*credit_team*/ 5);
assert_eq('pre-cutoff edit TC2: freeze',      false, $d['write']);

// 6. POST-cutoff CREATE with no TC2 yet (assigned later) -> credited null ->
//    unassigned (team null).
$d = booking_team_snapshot_by_credit('2026-06-15', 11, 0, null, /*credit_team*/ null);
assert_eq('post-cutoff no TC2: credited null', null, $d['credited']);
assert_eq('post-cutoff no TC2: team null',     null, $d['team_id']);
assert_eq('post-cutoff no TC2: still writes',  true, $d['write']);

// 7. POST-cutoff, assign TC2 later (null -> 22, Team 9): credited changes from
//    null to 22 -> snapshot Team 9.
$prev_no_tc2 = array('InsertDate' => '2026-06-15', 'SalesAgent' => 11, 'SalesAgent2' => 0);
$d = booking_team_snapshot_by_credit('2026-06-15', 11, 22, $prev_no_tc2, /*credit_team*/ 9);
assert_eq('assign TC2 later: writes',          true, $d['write']);
assert_eq('assign TC2 later: TC2 team',        9,    $d['team_id']);

// 8. Ids arriving as strings from the POST payload still freeze correctly.
$prev_str = array('InsertDate' => '2026-06-15', 'SalesAgent' => '11', 'SalesAgent2' => '22');
$d = booking_team_snapshot_by_credit('2026-06-15', '33', '22', $prev_str, /*credit_team*/ 9);
assert_eq('string ids post-cutoff edit TC1: freeze', false, $d['write']);

echo "\nAll assertions passed.\n";
