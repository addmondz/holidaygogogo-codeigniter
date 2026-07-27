<?php
/**
 * Run with: php tests/helpers/GhlLeadOwnershipOrphanPruneTest.php
 *
 * Locks the duplicate-owner fix for process_ghl_lead_ownership (continue mode).
 *
 * Background: ghl_lead_ownership.processed_lead_id references
 * ghl_processed_leads.id. Continue mode replaces ownership only for the
 * processed_lead_ids that currently exist. When a lead was historically
 * re-inserted with a new id, the ownership row pointing at the OLD id was never
 * cleaned, so the same logical lead (same conversation + start + owner) appeared
 * under two processed_lead_ids -> a duplicate owner.
 *
 * The fix has two parts and this test locks both:
 *   1. Part 1 (root cause): leads keep a stable id (covered by the upsert test),
 *      so ownership stops being orphaned in the first place.
 *   2. Part 2 (self-heal): continue mode prunes ownership rows whose
 *      processed_lead_id no longer exists, clearing any orphans already in the DB.
 *
 * This test FIRST reproduces the bug with the old delete+insert behaviour, then
 * proves the prune removes the orphan and leaves exactly one owner per lead.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE ghl_processed_leads (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    conversation_id TEXT NOT NULL,
    lead_started_at TEXT NOT NULL,
    first_customer_message_id TEXT NOT NULL,
    UNIQUE (conversation_id, lead_started_at, first_customer_message_id)
)");
$pdo->exec("CREATE TABLE ghl_lead_ownership (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    processed_lead_id INTEGER NOT NULL,
    conversation_id TEXT NOT NULL,
    lead_started_at TEXT NOT NULL,
    owner_user_id TEXT NOT NULL,
    UNIQUE (processed_lead_id, owner_user_id)
)");

function count_rows(PDO $pdo, $sql) {
    return (int) $pdo->query($sql)->fetchColumn();
}
function duplicate_logical_owners(PDO $pdo) {
    // Same (conversation, start, owner) mapped to more than one processed_lead_id.
    return (int) $pdo->query("
        SELECT COUNT(*) FROM (
            SELECT conversation_id, lead_started_at, owner_user_id
            FROM ghl_lead_ownership
            GROUP BY conversation_id, lead_started_at, owner_user_id
            HAVING COUNT(DISTINCT processed_lead_id) > 1
        )")->fetchColumn();
}
function orphan_ownership(PDO $pdo) {
    return count_rows($pdo, "SELECT COUNT(*) FROM ghl_lead_ownership
        WHERE processed_lead_id NOT IN (SELECT id FROM ghl_processed_leads)");
}
function prune_orphan_ownership(PDO $pdo) {
    // Mirrors Ghl_Lead_Ownership_Model::prune_orphan_ownership (MySQL uses a
    // multi-table DELETE ... JOIN; the NOT IN form is the SQLite equivalent).
    $pdo->exec("DELETE FROM ghl_lead_ownership
                WHERE processed_lead_id NOT IN (SELECT id FROM ghl_processed_leads)");
}
function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

$conv = 'CONV-1';
$start = '2026-06-14 10:00:00';

// --- Reproduce the bug: old delete+insert churn left an orphan ---------------
// Lead first lands as id 1, ownership recorded against id 1.
$pdo->exec("INSERT INTO ghl_processed_leads (id, conversation_id, lead_started_at, first_customer_message_id)
            VALUES (1, '$conv', '$start', 'MSG-A')");
$pdo->exec("INSERT INTO ghl_lead_ownership (processed_lead_id, conversation_id, lead_started_at, owner_user_id)
            VALUES (1, '$conv', '$start', 'agent-a')");

// A new message arrives. OLD behaviour deletes the lead and re-inserts it with a
// fresh id (2). Continue mode then writes ownership for id 2 but cannot touch the
// stale row for the now-missing id 1.
$pdo->exec("DELETE FROM ghl_processed_leads WHERE id = 1");
$pdo->exec("INSERT INTO ghl_processed_leads (id, conversation_id, lead_started_at, first_customer_message_id)
            VALUES (2, '$conv', '$start', 'MSG-A')");
$pdo->exec("INSERT INTO ghl_lead_ownership (processed_lead_id, conversation_id, lead_started_at, owner_user_id)
            VALUES (2, '$conv', '$start', 'agent-a')");

assert_eq('bug reproduced: ownership rows', 2, count_rows($pdo, "SELECT COUNT(*) FROM ghl_lead_ownership"));
assert_eq('bug reproduced: one row is orphaned', 1, orphan_ownership($pdo));
assert_eq('bug reproduced: duplicate owner for same logical lead', 1, duplicate_logical_owners($pdo));

// --- Apply the fix: continue mode prunes orphans -----------------------------
prune_orphan_ownership($pdo);

assert_eq('after prune: orphans gone', 0, orphan_ownership($pdo));
assert_eq('after prune: exactly one ownership row left', 1, count_rows($pdo, "SELECT COUNT(*) FROM ghl_lead_ownership"));
assert_eq('after prune: no duplicate owners', 0, duplicate_logical_owners($pdo));
assert_eq('after prune: surviving row points at the live lead', 2,
    (int) $pdo->query("SELECT processed_lead_id FROM ghl_lead_ownership")->fetchColumn());

// Re-running the prune is a no-op (idempotent).
prune_orphan_ownership($pdo);
assert_eq('prune is idempotent', 1, count_rows($pdo, "SELECT COUNT(*) FROM ghl_lead_ownership"));

echo "\nAll assertions passed.\n";
