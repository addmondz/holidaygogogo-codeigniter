<?php
/**
 * Run with: php tests/helpers/GhlReplyOwnerThresholdTest.php
 *
 * Locks the reply-owner threshold and the assigned-vs-reply dashboard rule.
 *
 * Reply-owner rule: an agent who sends MORE THAN 3 outbound replies in a lead's
 * window is a reply owner. So:
 *   1-2 replies -> No,  3 replies -> No,  4 replies -> Yes,  5+ -> Yes.
 *
 * Dashboard rule (no double counting):
 *   - The assigned owner counts as assigned_lead = 1 and is NEVER also counted
 *     as a reply lead, even if they replied a lot.
 *   - A non-assigned agent above the threshold counts as reply_lead = 1.
 *   - A non-assigned agent at/below the threshold counts as nothing.
 *
 * This mirrors:
 *   - Ghl_Lead_Ownership_Model::get_reply_owners_for_leads  (HAVING COUNT(*) > 3)
 *   - Report_Model dashboard SUM(CASE WHEN is_assigned_owner = 0
 *                                       AND is_reply_owner = 1 ...)
 *
 * Note: CAST(? AS INTEGER) is only needed for SQLite in this test (PDO binds the
 * param as text, and SQLite would compare COUNT(*) against text). Production runs
 * on MySQL, which coerces the value, so the live query uses a plain "> ?".
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

const REPLY_THRESHOLD = 3; // "more than N" replies => reply owner (so 4+)

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE messages (lead_id INT, user_id TEXT, direction TEXT)");

// Lead 1 assigned to agent-A.
//   agent-A: assignee + 5 replies   -> assigned only (never double-counted)
//   agent-B: not assigned + 4 replies -> reply owner (above threshold)
//   agent-C: not assigned + 3 replies -> nothing (AT threshold, not above)
//   agent-D: not assigned + 1 reply   -> nothing (below threshold)
$rows = array(
    array(1, 'agent-A', 'outbound'), array(1, 'agent-A', 'outbound'), array(1, 'agent-A', 'outbound'),
    array(1, 'agent-A', 'outbound'), array(1, 'agent-A', 'outbound'),
    array(1, 'agent-B', 'outbound'), array(1, 'agent-B', 'outbound'), array(1, 'agent-B', 'outbound'),
    array(1, 'agent-B', 'outbound'),
    array(1, 'agent-C', 'outbound'), array(1, 'agent-C', 'outbound'), array(1, 'agent-C', 'outbound'),
    array(1, 'agent-D', 'outbound'),
    array(1, 'cust',    'inbound'),  // inbound must be ignored by the reply count
);
$ins = $pdo->prepare("INSERT INTO messages (lead_id, user_id, direction) VALUES (?,?,?)");
foreach ($rows as $r) { $ins->execute($r); }

$assignedTo = 'agent-A';

// Step 1: who qualifies as a reply owner (MORE THAN threshold outbound replies)?
$replyOwners = $pdo->prepare("
    SELECT user_id, COUNT(*) AS replies
    FROM messages
    WHERE lead_id = 1 AND direction = 'outbound'
    GROUP BY user_id
    HAVING COUNT(*) > CAST(? AS INTEGER)
");
$replyOwners->execute(array(REPLY_THRESHOLD));
$replyOwnerIds = array();
foreach ($replyOwners->fetchAll(PDO::FETCH_ASSOC) as $r) { $replyOwnerIds[$r['user_id']] = (int) $r['replies']; }

// Step 2: build the ownership rows (assignee always an owner; reply owners added).
$owners = array(); // user_id => [is_assigned_owner, is_reply_owner]
$owners[$assignedTo] = array('assigned' => 1, 'reply' => isset($replyOwnerIds[$assignedTo]) ? 1 : 0);
foreach ($replyOwnerIds as $uid => $n) {
    if (!isset($owners[$uid])) { $owners[$uid] = array('assigned' => 0, 'reply' => 1); }
    else { $owners[$uid]['reply'] = 1; }
}

// Step 3: apply the dashboard counting rule per owner.
function dashboard_counts($flags) {
    $assigned = ($flags['assigned'] === 1) ? 1 : 0;
    // reply only counts when NOT the assigned owner
    $reply = ($flags['assigned'] === 0 && $flags['reply'] === 1) ? 1 : 0;
    return array($assigned, $reply);
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

// --- threshold behaviour ---
assert_eq('agent-A (assignee, 5 replies) is a raw reply owner', true, isset($replyOwnerIds['agent-A']));
assert_eq('agent-B (4 replies) is above threshold',             true, isset($replyOwnerIds['agent-B']));
assert_eq('agent-C (3 replies) is NOT above threshold',         false, isset($replyOwnerIds['agent-C']));
assert_eq('agent-D (1 reply) is NOT above threshold',           false, isset($replyOwnerIds['agent-D']));

// --- dashboard counting (no double counting) ---
list($aA, $rA) = dashboard_counts($owners['agent-A']);
assert_eq('agent-A assigned_lead', 1, $aA);
assert_eq('agent-A reply_lead (must be 0 despite replies)', 0, $rA);

list($aB, $rB) = dashboard_counts($owners['agent-B']);
assert_eq('agent-B assigned_lead', 0, $aB);
assert_eq('agent-B reply_lead', 1, $rB);

// agent-C / agent-D produced no ownership row at all.
assert_eq('agent-C has no ownership row', false, isset($owners['agent-C']));
assert_eq('agent-D has no ownership row', false, isset($owners['agent-D']));

// --- boundary: exactly 3 replies by a non-assignee must NOT become a reply owner ---
$three = $pdo->prepare("SELECT COUNT(*) FROM (
    SELECT user_id FROM messages WHERE lead_id=1 AND direction='outbound'
    GROUP BY user_id HAVING COUNT(*) > CAST(? AS INTEGER)
) t WHERE user_id = 'agent-C'");
$three->execute(array(REPLY_THRESHOLD));
assert_eq('boundary: exactly 3 replies excluded', 0, (int) $three->fetchColumn());

echo "\nAll assertions passed.\n";
