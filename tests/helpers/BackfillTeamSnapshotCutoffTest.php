<?php
/**
 * Run with: php tests/helpers/BackfillTeamSnapshotCutoffTest.php
 *
 * Locks the recompute/decision behind the one-time backfill that re-freezes
 * booking.TeamID for POST-cutoff bookings (InsertDate >= 2026-06-01) onto the
 * TC2's (SalesAgent2) current team — the same attribution the write path now
 * produces (see Backfill_Team_Snapshot_Cutoff controller).
 *
 * Rules locked here:
 *   - In scope: InsertDate >= cutoff AND Status <> 'N' (voided excluded).
 *   - New TeamID = SalesAgent2's current admin.TeamID; NULL when TC2 is
 *     empty/absent or teamless -> Unassigned.
 *   - Only rows whose current TeamID DIFFERS from the recomputed value change.
 *   - Pre-cutoff rows are never touched (they stay credited to TC1).
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

$cutoff = '2026-06-01';

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE booking (
    BookingID INTEGER PRIMARY KEY,
    SalesAgent INTEGER,
    SalesAgent2 INTEGER,
    TeamID INTEGER,
    Status TEXT,
    InsertDate TEXT
)");
$pdo->exec("CREATE TABLE admin (AdminID INTEGER PRIMARY KEY, TeamID INTEGER)");

// TC1 11 -> Team 5, TC2 22 -> Team 9, TC2 44 -> Team 2, TC2 55 -> teamless.
$pdo->exec("INSERT INTO admin VALUES (11,5),(22,9),(44,2),(55,NULL)");

$pdo->exec("INSERT INTO booking VALUES
    /* 1: post-cutoff, still frozen to TC1 Team 5 -> must move to TC2 Team 9 */
    (1, 11, 22, 5,    'P', '2026-06-15'),
    /* 2: post-cutoff, already correct (TC2 Team 9) -> no change */
    (2, 11, 22, 9,    'P', '2026-06-20'),
    /* 3: post-cutoff, no TC2 -> must become Unassigned (NULL) */
    (3, 11, 0,  5,    'P', '2026-06-25'),
    /* 4: post-cutoff, TC2 teamless -> Unassigned (NULL) */
    (4, 11, 55, 5,    'P', '2026-06-26'),
    /* 5: PRE-cutoff -> out of scope, keep TC1 Team 5 */
    (5, 11, 22, 5,    'P', '2026-05-30'),
    /* 6: post-cutoff but VOIDED (Status N) -> out of scope */
    (6, 11, 44, 5,    'N', '2026-06-10'),
    /* 7: post-cutoff, TC2 44 Team 2, currently NULL -> must move to Team 2 */
    (7, 11, 44, NULL, 'P', '2026-06-11')
");

// The backfill's core query: recompute the credited (TC2) team for in-scope rows.
$rows = $pdo->query("
    SELECT b.BookingID,
           b.TeamID AS CurrentTeamID,
           a2.TeamID AS CreditTeamID
    FROM booking b
    LEFT JOIN admin a2 ON a2.AdminID = b.SalesAgent2 AND b.SalesAgent2 > 0
    WHERE b.InsertDate >= '{$cutoff}'
      AND b.Status <> 'N'
    ORDER BY b.BookingID
")->fetchAll(PDO::FETCH_ASSOC);

// Decision: normalise to int-or-null, update only when it differs.
function norm($v) { return ($v === null || $v === '') ? null : (int) $v; }

$updates = array();   // BookingID => new TeamID (int|null)
foreach ($rows as $r) {
    $cur = norm($r['CurrentTeamID']);
    $new = norm($r['CreditTeamID']);
    if ($cur !== $new) {
        $updates[(int) $r['BookingID']] = $new;
    }
}

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label}\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

// Row 1 corrected to Team 9; row 7 corrected to Team 2; rows 3 & 4 -> NULL.
assert_eq('row1 -> TC2 Team 9',        9,    isset($updates[1]) ? $updates[1] : 'unchanged');
assert_eq('row7 -> TC2 Team 2',        2,    isset($updates[7]) ? $updates[7] : 'unchanged');
assert_eq('row3 no TC2 -> Unassigned', null, array_key_exists(3, $updates) ? $updates[3] : 'unchanged');
assert_eq('row4 teamless -> Unassigned', null, array_key_exists(4, $updates) ? $updates[4] : 'unchanged');

// Row 2 already correct -> not in the update set.
assert_eq('row2 already correct: no update', false, array_key_exists(2, $updates));
// Rows 5 (pre-cutoff) and 6 (voided) never appear.
assert_eq('row5 pre-cutoff: out of scope',   false, array_key_exists(5, $updates));
assert_eq('row6 voided: out of scope',       false, array_key_exists(6, $updates));

// Exactly four rows change.
assert_eq('exactly 4 rows updated', 4, count($updates));

echo "\nAll assertions passed.\n";
