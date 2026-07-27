<?php
/**
 * Run with: php tests/helpers/GhlMessageLogMultiSelectTest.php
 *
 * Locks the MULTI-select contact + agent Message Log filters:
 *
 *   - ghl_message_log_normalize_contacts(): array OR comma/newline string ->
 *     de-duplicated list of digits-only numbers (single number's own spaces /
 *     dashes / '+' are kept inside that one number).
 *   - ghl_message_log_normalize_agents(): array OR single string ->
 *     de-duplicated list of trimmed agent names.
 *   - the SQL contract: several contacts OR together (each still matches both
 *     from_number and to_number), and several agents become an IN list on the
 *     resolved Agent column. Mirrored in portable SQLite (the model runs MySQL).
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require __DIR__ . '/../../application/helpers/ghl_messages_log_helper.php';

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

// --- normalize contacts ------------------------------------------------------
assert_eq('contacts: single number', array('0123456789'),
    ghl_message_log_normalize_contacts('0123456789'));
assert_eq('contacts: comma separated', array('012', '013'),
    ghl_message_log_normalize_contacts('012, 013'));
assert_eq('contacts: newline / semicolon separated', array('012', '013', '014'),
    ghl_message_log_normalize_contacts("012;013\n014"));
assert_eq('contacts: strips format inside one number', array('6012345'),
    ghl_message_log_normalize_contacts('+60 12-345'));
assert_eq('contacts: de-dupes repeats', array('012', '013'),
    ghl_message_log_normalize_contacts('012, 013, 012'));
assert_eq('contacts: drops blanks', array('012'),
    ghl_message_log_normalize_contacts('012, , ,'));
assert_eq('contacts: accepts an array', array('012', '013'),
    ghl_message_log_normalize_contacts(array('012', '0-1-3')));
assert_eq('contacts: empty input -> empty list', array(),
    ghl_message_log_normalize_contacts(''));

// --- normalize agents --------------------------------------------------------
assert_eq('agents: single string (legacy link)', array('Agent Alice'),
    ghl_message_log_normalize_agents('Agent Alice'));
assert_eq('agents: array from multi-select', array('Agent Alice', 'Agent Bob'),
    ghl_message_log_normalize_agents(array('Agent Alice', 'Agent Bob')));
assert_eq('agents: trims + drops blanks', array('Agent Alice'),
    ghl_message_log_normalize_agents(array('  Agent Alice  ', '', '   ')));
assert_eq('agents: de-dupes repeats', array('Agent Alice', 'Agent Bob'),
    ghl_message_log_normalize_agents(array('Agent Alice', 'Agent Bob', 'Agent Alice')));
assert_eq('agents: empty -> empty list', array(),
    ghl_message_log_normalize_agents(''));

// --- SQL contract in SQLite --------------------------------------------------
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE ghl_messages (
    id INTEGER PRIMARY KEY, conversation_id TEXT, from_number TEXT, to_number TEXT,
    user_id TEXT, direction TEXT, body TEXT, date_added TEXT
)");
$pdo->exec("CREATE TABLE ghl_users (UserID TEXT, Name TEXT)");
$pdo->exec("INSERT INTO ghl_users (UserID, Name) VALUES ('u-1','Agent Alice'),('u-2','Agent Bob'),('u-3','Agent Cara')");
$pdo->exec("CREATE TABLE ghl_conversations (conversation_id TEXT, contact_id TEXT, contact_name TEXT, full_name TEXT, assigned_to TEXT)");
$pdo->exec("INSERT INTO ghl_conversations (conversation_id, assigned_to) VALUES ('cv-a','u-1'),('cv-b','u-2'),('cv-c','u-3')");

// Three leads, one per chatroom. Lead A=+60111, B=+60222, C=+60333 (as the
// customer); +60999 is our office line on the other leg.
$pdo->exec("INSERT INTO ghl_messages (id, conversation_id, from_number, to_number, user_id, direction, body, date_added) VALUES
    (10,'cv-a','+60111','+60999',NULL,'inbound','a-in','2026-06-14 08:00:00'),
    (11,'cv-a','+60999','+60111','u-1','outbound','a-out','2026-06-14 08:05:00'),
    (12,'cv-b','+60222','+60999',NULL,'inbound','b-in','2026-06-14 09:00:00'),
    (13,'cv-b','+60999','+60222','u-2','outbound','b-out','2026-06-14 09:05:00'),
    (14,'cv-c','+60333','+60999',NULL,'inbound','c-in','2026-06-14 10:00:00'),
    (15,'cv-c','+60999','+60333','u-3','outbound','c-out','2026-06-14 10:05:00')
");

$start = '2026-06-14 00:00:00';
$end   = '2026-06-14 23:59:59';
$normExpr = function ($col) {
    return "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE($col,'+',''),' ',''),'-',''),'(',''),')','')";
};

/**
 * Mirror of Report_Model::ghl_message_contact_clause() for a list of contacts:
 * each number gets its own (from LIKE ? OR to LIKE ?), all OR-ed together.
 */
function build_contact_clause(array $digitsList, $normExpr, array &$binds) {
    if (empty($digitsList)) { return ''; }
    $ors = array();
    foreach ($digitsList as $d) {
        $binds[] = '%' . $d . '%';
        $binds[] = '%' . $d . '%';
        $ors[] = "({$normExpr('gm.from_number')} LIKE ? OR {$normExpr('gm.to_number')} LIKE ?)";
    }
    return ' AND (' . implode(' OR ', $ors) . ')';
}

// Two contacts -> both leads' full two-way threads (4 rows: 10,11,12,13).
$binds = array($start, $end);
$clause = build_contact_clause(array('60111', '60222'), $normExpr, $binds);
$sql = "SELECT gm.id FROM ghl_messages gm
        WHERE gm.date_added >= ? AND gm.date_added <= ? {$clause}
        ORDER BY gm.id ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($binds);
$ids = array_map('intval', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id'));
assert_eq('two contacts OR into both threads (both directions)', array(10, 11, 12, 13), $ids);

// A single contact still returns only its own thread.
$binds = array($start, $end);
$clause = build_contact_clause(array('60333'), $normExpr, $binds);
$stmt = $pdo->prepare("SELECT gm.id FROM ghl_messages gm WHERE gm.date_added >= ? AND gm.date_added <= ? {$clause} ORDER BY gm.id ASC");
$stmt->execute($binds);
$ids = array_map('intval', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id'));
assert_eq('single contact still narrows to one thread', array(14, 15), $ids);

/**
 * Mirror of Report_Model::ghl_message_agent_clause() for a list of agents:
 * exact match on the resolved Agent column via an IN list.
 */
function build_agent_clause(array $agents, array &$binds) {
    if (empty($agents)) { return ''; }
    $ph = array();
    foreach ($agents as $a) { $binds[] = $a; $ph[] = '?'; }
    return " AND COALESCE(NULLIF(gu.Name,''), NULLIF(gu_assigned.Name,'')) IN (" . implode(',', $ph) . ")";
}

$agentBase = "SELECT gm.id FROM ghl_messages gm
    LEFT JOIN ghl_users gu ON gu.UserID = gm.user_id
    LEFT JOIN ghl_conversations gc ON gc.conversation_id = gm.conversation_id
    LEFT JOIN ghl_users gu_assigned ON gu_assigned.UserID = gc.assigned_to
    WHERE gm.date_added >= ? AND gm.date_added <= ? %s ORDER BY gm.id ASC";

// Two agents -> both their threads (Alice cv-a: 10,11 ; Cara cv-c: 14,15).
$binds = array($start, $end);
$clause = build_agent_clause(array('Agent Alice', 'Agent Cara'), $binds);
$stmt = $pdo->prepare(sprintf($agentBase, $clause));
$stmt->execute($binds);
$ids = array_map('intval', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id'));
assert_eq('two agents IN-match both their threads', array(10, 11, 14, 15), $ids);

// One agent still narrows to just that agent.
$binds = array($start, $end);
$clause = build_agent_clause(array('Agent Bob'), $binds);
$stmt = $pdo->prepare(sprintf($agentBase, $clause));
$stmt->execute($binds);
$ids = array_map('intval', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id'));
assert_eq('single agent still narrows to one thread', array(12, 13), $ids);

// Contact + agent compose: Alice's thread narrowed to lead B's number is empty.
$binds = array($start, $end);
$cClause = build_contact_clause(array('60222'), $normExpr, $binds);
$aClause = build_agent_clause(array('Agent Alice'), $binds);
$stmt = $pdo->prepare("SELECT gm.id FROM ghl_messages gm
    LEFT JOIN ghl_users gu ON gu.UserID = gm.user_id
    LEFT JOIN ghl_conversations gc ON gc.conversation_id = gm.conversation_id
    LEFT JOIN ghl_users gu_assigned ON gu_assigned.UserID = gc.assigned_to
    WHERE gm.date_added >= ? AND gm.date_added <= ? {$cClause} {$aClause} ORDER BY gm.id ASC");
$stmt->execute($binds);
$ids = array_map('intval', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id'));
assert_eq('contact + agent still AND together', array(), $ids);

echo "\nAll assertions passed.\n";
