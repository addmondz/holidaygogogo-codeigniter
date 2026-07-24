<?php
/**
 * Run with: php tests/helpers/GhlMessageLogDirectionFilterTest.php
 *
 * Locks the Direction column FILTER on the Message Log report (distinct from the
 * sort-by-direction toggle, which only groups rows):
 *
 *   1. ghl_message_log_normalize_direction() — only an explicit 'inbound' or
 *      'outbound' narrows the log; everything else is '' (all directions), so
 *      the value reaching the WHERE builder is always one of three safe literals.
 *   2. The "AND gm.direction = ?" fragment keeps exactly the matching rows,
 *      mirrored here in portable SQLite (the model runs on MySQL).
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

// --- 1) normalize_direction: default '', only inbound/outbound pass through. ---
assert_eq('normalize "inbound" => inbound',   'inbound',  ghl_message_log_normalize_direction('inbound'));
assert_eq('normalize "INBOUND" => inbound',   'inbound',  ghl_message_log_normalize_direction('INBOUND'));
assert_eq('normalize " Outbound " => outbound', 'outbound', ghl_message_log_normalize_direction(' Outbound '));
assert_eq('normalize "outbound" => outbound', 'outbound', ghl_message_log_normalize_direction('outbound'));
assert_eq('normalize "" => (all)',            '',         ghl_message_log_normalize_direction(''));
assert_eq('normalize junk => (all)',          '',         ghl_message_log_normalize_direction('inbound; DROP TABLE'));
assert_eq('normalize null => (all)',          '',         ghl_message_log_normalize_direction(null));

// --- 2) "AND gm.direction = ?" keeps only the matching rows, in SQLite. ---
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE ghl_messages (id INTEGER PRIMARY KEY, direction TEXT)");
$pdo->exec("INSERT INTO ghl_messages (id, direction) VALUES
    (10, 'outbound'),
    (11, 'inbound'),
    (12, 'outbound'),
    (13, 'inbound')
");

/** Mirror of the model's direction WHERE fragment for a normalized value. */
$filtered_ids = function ($raw) use ($pdo) {
    $direction = ghl_message_log_normalize_direction($raw);
    $sql = "SELECT id FROM ghl_messages";
    $params = array();
    if ($direction !== '') {
        $sql .= " WHERE direction = ?";
        $params[] = $direction;
    }
    $sql .= " ORDER BY id ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return array_map('intval', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id'));
};

assert_eq('inbound filter keeps only inbound rows',   array(11, 13), $filtered_ids('inbound'));
assert_eq('outbound filter keeps only outbound rows', array(10, 12), $filtered_ids('outbound'));
assert_eq('no filter keeps every row',                array(10, 11, 12, 13), $filtered_ids(''));
assert_eq('junk direction keeps every row',           array(10, 11, 12, 13), $filtered_ids('junk'));

echo "\nAll assertions passed.\n";
