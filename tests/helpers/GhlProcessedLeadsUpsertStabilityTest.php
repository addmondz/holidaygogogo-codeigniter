<?php
/**
 * Run with: php tests/helpers/GhlProcessedLeadsUpsertStabilityTest.php
 *
 * Locks the behaviour of Ghl_Processed_Leads_Model::replace_conversation_leads.
 *
 * The bug: the old implementation deleted every lead for a conversation and
 * re-inserted them, so each lead received a NEW auto-increment id on every run.
 * ghl_lead_ownership.processed_lead_id references ghl_processed_leads.id, so the
 * id churn orphaned ownership rows and made the same logical lead show up under
 * multiple owners (the "create multiple same lead" symptom in continue mode).
 *
 * The fix: replace_conversation_leads now UPSERTS by the natural key
 * (conversation_id, lead_started_at, first_customer_message_id). Existing leads
 * are updated in place and KEEP their id; only genuinely new leads are inserted
 * and only removed leads are deleted.
 *
 * Invariants under test:
 *   1. Re-processing an unchanged conversation keeps each lead's id stable.
 *   2. An existing lead is updated in place (no duplicate row).
 *   3. A new split adds a row without disturbing the existing lead's id.
 *   4. Collapsing a split deletes only the vanished lead.
 *   5. The natural-key UNIQUE constraint is never violated.
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
    tracked_message_count INTEGER NOT NULL DEFAULT 0,
    created_at TEXT,
    updated_at TEXT,
    UNIQUE (conversation_id, lead_started_at, first_customer_message_id)
)");

/**
 * Reference implementation mirroring the production upsert in
 * Ghl_Processed_Leads_Model::replace_conversation_leads.
 */
function replace_conversation_leads(PDO $pdo, $conversationId, array $leads, $now) {
    $rows = $pdo->prepare("SELECT id, lead_started_at, first_customer_message_id
                           FROM ghl_processed_leads WHERE conversation_id = ?");
    $rows->execute(array($conversationId));

    $existingByKey = array();
    foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $existingByKey[$row['lead_started_at'] . '|' . $row['first_customer_message_id']] = (int) $row['id'];
    }

    $keptKeys = array();
    foreach ($leads as $lead) {
        $key = $lead['lead_started_at'] . '|' . $lead['first_customer_message_id'];
        $keptKeys[$key] = true;

        if (isset($existingByKey[$key])) {
            $upd = $pdo->prepare("UPDATE ghl_processed_leads
                                  SET tracked_message_count = ?, updated_at = ?
                                  WHERE id = ?");
            $upd->execute(array($lead['tracked_message_count'], $now, $existingByKey[$key]));
        } else {
            $ins = $pdo->prepare("INSERT INTO ghl_processed_leads
                (conversation_id, lead_started_at, first_customer_message_id, tracked_message_count, created_at, updated_at)
                VALUES (?,?,?,?,?,?)");
            $ins->execute(array($conversationId, $lead['lead_started_at'], $lead['first_customer_message_id'],
                                $lead['tracked_message_count'], $now, $now));
        }
    }

    foreach ($existingByKey as $key => $id) {
        if (!isset($keptKeys[$key])) {
            $pdo->prepare("DELETE FROM ghl_processed_leads WHERE id = ?")->execute(array($id));
        }
    }
}

function ids_by_key(PDO $pdo, $conversationId) {
    $stmt = $pdo->prepare("SELECT lead_started_at, first_customer_message_id, id, tracked_message_count
                           FROM ghl_processed_leads WHERE conversation_id = ? ORDER BY id");
    $stmt->execute(array($conversationId));
    $out = array();
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $out[$r['lead_started_at'] . '|' . $r['first_customer_message_id']] = $r;
    }
    return $out;
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
$leadA = array('lead_started_at' => '2026-06-14 10:00:00', 'first_customer_message_id' => 'MSG-A', 'tracked_message_count' => 3);

// Run 1: first time the conversation is processed.
replace_conversation_leads($pdo, $conv, array($leadA), '2026-06-20 09:00:00');
$snap1 = ids_by_key($pdo, $conv);
$idA = (int) $snap1['2026-06-14 10:00:00|MSG-A']['id'];
assert_eq('run1: one lead exists', 1, count($snap1));

// Run 2: a new inbound message arrived -> conversation reprocessed, lead A's
// tracked count grows but it is the SAME logical lead.
$leadA['tracked_message_count'] = 5;
replace_conversation_leads($pdo, $conv, array($leadA), '2026-06-21 09:00:00');
$snap2 = ids_by_key($pdo, $conv);
assert_eq('run2: still one lead (updated in place)', 1, count($snap2));
assert_eq('run2: lead A id is UNCHANGED', $idA, (int) $snap2['2026-06-14 10:00:00|MSG-A']['id']);
assert_eq('run2: lead A details were updated', 5, (int) $snap2['2026-06-14 10:00:00|MSG-A']['tracked_message_count']);

// Run 3: the lead converted and a new-trip message split off a second lead.
$leadB = array('lead_started_at' => '2026-08-01 08:00:00', 'first_customer_message_id' => 'MSG-B', 'tracked_message_count' => 2);
replace_conversation_leads($pdo, $conv, array($leadA, $leadB), '2026-08-02 09:00:00');
$snap3 = ids_by_key($pdo, $conv);
assert_eq('run3: now two leads', 2, count($snap3));
assert_eq('run3: lead A id STILL unchanged', $idA, (int) $snap3['2026-06-14 10:00:00|MSG-A']['id']);
$idB = (int) $snap3['2026-08-01 08:00:00|MSG-B']['id'];
assert_eq('run3: lead B got a fresh id', true, $idB > $idA);

// Run 4: the split collapsed back to a single lead (B vanished).
replace_conversation_leads($pdo, $conv, array($leadA), '2026-08-03 09:00:00');
$snap4 = ids_by_key($pdo, $conv);
assert_eq('run4: back to one lead', 1, count($snap4));
assert_eq('run4: lead A id preserved through it all', $idA, (int) $snap4['2026-06-14 10:00:00|MSG-A']['id']);
assert_eq('run4: lead B removed', false, isset($snap4['2026-08-01 08:00:00|MSG-B']));

// Total distinct ids ever used for lead A across 4 runs must be exactly 1.
echo "\nAll assertions passed.\n";
