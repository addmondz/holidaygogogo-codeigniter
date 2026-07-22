<?php
/**
 * Run with: php tests/helpers/OwnerOutboundByAgentTest.php
 *
 * Locks the SQL behind the OWNER matrix "Outbound Messages" column. The Booking
 * controller builds this via Report_Model->Outbound_Messages_By_Agent($start,
 * $end), which counts agent-sent (direction='outbound') GHL messages per
 * sending user over the period. This test replicates that query shape so a
 * regression in the direction filter / null-user guard / windowing / grouping
 * shows up here without booting CI.
 *
 * Rules verified:
 *   - Only direction='outbound' rows counted (inbound excluded).
 *   - user_id NULL or '' excluded.
 *   - date_added window is inclusive of both ends; outside excluded.
 *   - One row per user_id with the correct count.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE ghl_messages (
    id INTEGER PRIMARY KEY,
    user_id TEXT,
    direction TEXT,
    date_added TEXT
)");

// Window under test: 2026-06-01 .. 2026-06-30.
$pdo->exec("INSERT INTO ghl_messages (id, user_id, direction, date_added) VALUES
    (1,  'UID-A', 'outbound', '2026-06-01 00:00:00'),  /* lower bound inclusive, A */
    (2,  'UID-A', 'outbound', '2026-06-15 09:00:00'),  /* A */
    (3,  'UID-A', 'outbound', '2026-06-30 23:59:59'),  /* upper bound inclusive, A */
    (4,  'UID-A', 'inbound',  '2026-06-10 09:00:00'),  /* inbound -> excluded */
    (5,  'UID-B', 'outbound', '2026-06-12 09:00:00'),  /* B */
    (6,  'UID-B', 'outbound', '2026-06-13 09:00:00'),  /* B */
    (7,  '',      'outbound', '2026-06-14 09:00:00'),  /* empty user -> excluded */
    (8,  NULL,    'outbound', '2026-06-14 09:00:00'),  /* null user -> excluded */
    (9,  'UID-A', 'outbound', '2026-05-31 23:59:59'),  /* before window -> excluded */
    (10, 'UID-A', 'outbound', '2026-07-01 00:00:00')   /* after window -> excluded */
");

// Mirror of Report_Model::Outbound_Messages_By_Agent().
$run = function ($pdo, $start, $end) {
    $sql = "
        SELECT gm.user_id AS agent_id,
               COUNT(*) AS outbound_count
        FROM ghl_messages gm
        WHERE gm.direction='outbound'
          AND NULLIF(gm.user_id,'') IS NOT NULL
          AND gm.date_added BETWEEN ? AND ?
        GROUP BY gm.user_id
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array($start . ' 00:00:00', $end . ' 23:59:59'));
    $out = array();
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $out[$r['agent_id']] = (int) $r['outbound_count'];
    }
    return $out;
};

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got "      . var_export($actual, true) . "\n";
        exit(1);
    }
}

$rows = $run($pdo, '2026-06-01', '2026-06-30');

assert_eq('two agents (no null/empty)', 2, count($rows));
assert_eq('A outbound (incl bounds, excl inbound/out-of-window)', 3, $rows['UID-A']);
assert_eq('B outbound', 2, $rows['UID-B']);
assert_eq('empty user excluded', false, isset($rows['']));

echo "\nAll assertions passed.\n";
