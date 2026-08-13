<?php
/**
 * Run with: php tests/helpers/ManualLeadLatestStatusFilterTest.php
 *
 * Locks the Manual Leads "Lead Status" filter (Guests_Model::Build_Branches).
 * A lead's status now lives ONLY in the dated lead_status_log (the single
 * "current status" field gc.lead_status was dropped). A lead matches ONLY on
 * its LATEST status — the newest active log entry, ordered by StatusDate then
 * LogID (tie-break). A lead with NO log entries has no status and never matches.
 *
 * This mirrors the exact WHERE the model emits (portable ANSI SQL — the
 * correlated EXISTS / NOT EXISTS run unchanged on SQLite) against real rows.
 */

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE ghl_contacts (
    id INTEGER PRIMARY KEY, dedup_key TEXT)");
$pdo->exec("CREATE TABLE lead_status_log (
    LogID INTEGER PRIMARY KEY, dedup_key TEXT, StatusDate TEXT,
    LeadStatus TEXT, Status TEXT)");

// --- leads --------------------------------------------------------------------
// A: log Interested (old) -> Closed (new)  => latest = Closed
// B: no log at all                          => never matches
// C: two entries SAME date, LogID tie-break => latest = higher LogID (Won)
// D: newest entry soft-deleted (Status=N)   => latest active = Interested
// E: no log at all                          => never matches
$pdo->exec("INSERT INTO ghl_contacts (id, dedup_key) VALUES
    (1, 'k-a'),
    (2, 'k-b'),
    (3, 'k-c'),
    (4, 'k-d'),
    (5, 'k-e')");
$pdo->exec("INSERT INTO lead_status_log (LogID, dedup_key, StatusDate, LeadStatus, Status) VALUES
    (10, 'k-a', '2026-08-01', 'Interested', 'Y'),
    (11, 'k-a', '2026-08-05', 'Closed',     'Y'),
    (12, 'k-c', '2026-08-03', 'Interested', 'Y'),
    (13, 'k-c', '2026-08-03', 'Won',        'Y'),   -- same date, higher LogID wins
    (14, 'k-d', '2026-08-02', 'Interested', 'Y'),
    (15, 'k-d', '2026-08-09', 'Closed',     'N')"); // LogID 15 soft-deleted, ignored

$gc_dedup = 'gc.dedup_key'; // stand-in for Ghl_Dedup_Key_Expr()

// The exact filter the model builds, for a multi-select of $picked statuses.
function matched_ids(PDO $pdo, $gc_dedup, array $picked) {
    $ph = implode(',', array_fill(0, count($picked), '?'));
    $sql = "SELECT gc.id FROM ghl_contacts gc WHERE (
        EXISTS (SELECT 1 FROM lead_status_log lsl
            WHERE lsl.Status = 'Y' AND lsl.dedup_key = {$gc_dedup}
            AND lsl.LeadStatus IN ({$ph})
            AND NOT EXISTS (SELECT 1 FROM lead_status_log lsl2
                WHERE lsl2.Status = 'Y' AND lsl2.dedup_key = {$gc_dedup}
                AND (lsl2.StatusDate > lsl.StatusDate
                    OR (lsl2.StatusDate = lsl.StatusDate AND lsl2.LogID > lsl.LogID)))) )
        ORDER BY gc.id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($picked); // latest-log IN only (no current-status fallback)
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . json_encode($actual) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . json_encode($expected)
           . ", got " . json_encode($actual) . "\n";
        exit(1);
    }
}

// A's latest is Closed, not Interested; only D's latest active entry is Interested.
// B (no log) never matches now that the current-status fallback is gone.
assert_eq('Interested: soft-delete survivor (D) only',
    array(4), matched_ids($pdo, $gc_dedup, array('Interested')));

// A surfaces only under its LATEST status.
assert_eq('Closed: latest of A only', array(1), matched_ids($pdo, $gc_dedup, array('Closed')));

// C tie-break: same date, higher LogID (Won) wins over Interested.
assert_eq('Won: C tie-break by LogID', array(3), matched_ids($pdo, $gc_dedup, array('Won')));

// Multi-select unions the per-status latest matches.
assert_eq('Closed+Won multi-select', array(1, 3),
    matched_ids($pdo, $gc_dedup, array('Closed', 'Won')));

// A status never held in any log matches nobody.
assert_eq('New: matches nobody', array(), matched_ids($pdo, $gc_dedup, array('New')));

echo "\nAll assertions passed.\n";
