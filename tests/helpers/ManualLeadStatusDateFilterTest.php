<?php
/**
 * Run with: php tests/helpers/ManualLeadStatusDateFilterTest.php
 *
 * Locks the Manual Leads "Lead Status Date" filter (Guests_Model::Build_Branches).
 * Unlike the "Lead Status" filter (which matches only a lead's LATEST status),
 * the date filter matches a lead that had ANY active lead_status_log entry whose
 * StatusDate falls inside the picked range [start, end] (inclusive) — even if the
 * status later changed. When a Lead Status is ALSO picked, the two combine into a
 * SINGLE any-entry test: that dated entry must carry one of the picked statuses
 * (deliberately NOT the latest-status rule, so "moved to X during this window"
 * still matches even if X is no longer current).
 *
 * This mirrors the exact WHERE the model emits (portable ANSI SQL runs unchanged
 * on SQLite) against real rows.
 */

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE ghl_contacts (
    id INTEGER PRIMARY KEY, dedup_key TEXT)");
$pdo->exec("CREATE TABLE lead_status_log (
    LogID INTEGER PRIMARY KEY, dedup_key TEXT, StatusDate TEXT,
    LeadStatus TEXT, Status TEXT)");

// --- leads --------------------------------------------------------------------
// A: Interested 08-01 -> Closed 08-05    (latest = Closed)
// B: no log at all                        (never matches)
// C: Won 08-10                            (single entry, later window)
// D: Interested 08-02 -> Closed 08-20(N)  (08-20 soft-deleted, ignored)
$pdo->exec("INSERT INTO ghl_contacts (id, dedup_key) VALUES
    (1, 'k-a'),
    (2, 'k-b'),
    (3, 'k-c'),
    (4, 'k-d')");
$pdo->exec("INSERT INTO lead_status_log (LogID, dedup_key, StatusDate, LeadStatus, Status) VALUES
    (10, 'k-a', '2026-08-01', 'Interested', 'Y'),
    (11, 'k-a', '2026-08-05', 'Closed',     'Y'),
    (13, 'k-c', '2026-08-10', 'Won',        'Y'),
    (14, 'k-d', '2026-08-02', 'Interested', 'Y'),
    (15, 'k-d', '2026-08-20', 'Closed',     'N')"); // soft-deleted, ignored

$gc_dedup = 'gc.dedup_key'; // stand-in for Ghl_Dedup_Key_Expr()

// The exact filter the model builds for a date range + optional picked statuses.
function matched_ids(PDO $pdo, $gc_dedup, array $range, array $picked) {
    $sql = "SELECT gc.id FROM ghl_contacts gc WHERE (
        EXISTS (SELECT 1 FROM lead_status_log lsl
            WHERE lsl.Status = 'Y' AND lsl.dedup_key = {$gc_dedup}
            AND lsl.StatusDate >= ? AND lsl.StatusDate <= ? ";
    $params = array($range[0], $range[1]);
    if (!empty($picked)) {
        $ph = implode(',', array_fill(0, count($picked), '?'));
        $sql .= " AND lsl.LeadStatus IN ({$ph}) ";
        foreach ($picked as $p) { $params[] = $p; }
    }
    $sql .= ") ) ORDER BY gc.id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
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

// Date-only: any entry in [08-01, 08-05]. A (08-01 & 08-05) and D (08-02) match;
// C (08-10) is outside; B has no log.
assert_eq('Date only 08-01..08-05: A + D',
    array(1, 4), matched_ids($pdo, $gc_dedup, array('2026-08-01', '2026-08-05'), array()));

// Inclusive end boundary: a range ending exactly on C's 08-10 includes C.
assert_eq('Date only 08-06..08-10: C (inclusive end)',
    array(3), matched_ids($pdo, $gc_dedup, array('2026-08-06', '2026-08-10'), array()));

// Date + status matches ANY dated entry with that status, not the latest.
// A held Interested on 08-01 even though its latest is Closed -> A matches.
assert_eq('08-01..08-05 + Interested: A + D (any entry, not latest)',
    array(1, 4), matched_ids($pdo, $gc_dedup, array('2026-08-01', '2026-08-05'), array('Interested')));

// Closed within window: only A's 08-05 (D's Closed on 08-20 is soft-deleted AND out of range).
assert_eq('08-01..08-05 + Closed: A only',
    array(1), matched_ids($pdo, $gc_dedup, array('2026-08-01', '2026-08-05'), array('Closed')));

// Soft-deleted entries never match, even inside the range.
assert_eq('08-15..08-25 + Closed: nobody (D 08-20 soft-deleted)',
    array(), matched_ids($pdo, $gc_dedup, array('2026-08-15', '2026-08-25'), array('Closed')));

// Multi-select statuses union within the window.
assert_eq('08-01..08-10 + Interested,Won: A, C, D',
    array(1, 3, 4), matched_ids($pdo, $gc_dedup, array('2026-08-01', '2026-08-10'), array('Interested', 'Won')));

echo "\nAll assertions passed.\n";
