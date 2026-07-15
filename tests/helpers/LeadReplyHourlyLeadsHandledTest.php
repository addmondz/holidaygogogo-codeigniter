<?php
/**
 * Run with: php tests/helpers/LeadReplyHourlyLeadsHandledTest.php
 *
 * Locks the "Leads Handled" semantics of Report_Model::Lead_Reply_Activity_Hourly_By_Owner().
 * That runs on MySQL; here we mirror its leads_count expression in portable
 * SQLite so the rule is pinned without a live DB.
 *
 * Rule: Leads Handled = distinct conversations this owner actually REPLIED to in
 * the hour -- i.e. where the owner (glo.owner_user_id) sent an OUTBOUND message.
 *   - a lead that only sent inbound (owner never replied) is NOT counted,
 *   - an outbound sent by some OTHER user (not the owner) does NOT count,
 *   - the same lead replied to in two hours counts once per hour.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
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

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE ghl_lead_ownership (
    owner_user_id TEXT,
    conversation_id TEXT,
    lead_started_at TEXT,
    lead_ended_at TEXT
)");
$pdo->exec("CREATE TABLE ghl_messages (
    id INTEGER PRIMARY KEY,
    conversation_id TEXT,
    user_id TEXT,
    direction TEXT,
    date_added TEXT
)");

// Owner 'owner-1' owns three leads for the whole day.
$pdo->exec("INSERT INTO ghl_lead_ownership (owner_user_id, conversation_id, lead_started_at, lead_ended_at) VALUES
    ('owner-1', 'cv-replied',   '2026-07-15 00:00:00', NULL),
    ('owner-1', 'cv-silent',    '2026-07-15 00:00:00', NULL),
    ('owner-1', 'cv-otheruser', '2026-07-15 00:00:00', NULL)
");

$pdo->exec("INSERT INTO ghl_messages (id, conversation_id, user_id, direction, date_added) VALUES
    -- cv-replied @11: inbound then owner's outbound reply -> COUNTED
    (1, 'cv-replied', NULL,      'inbound',  '2026-07-15 11:05:00'),
    (2, 'cv-replied', 'owner-1', 'outbound', '2026-07-15 11:40:00'),
    -- cv-silent @11: inbound only, no reply -> NOT counted
    (3, 'cv-silent',  NULL,      'inbound',  '2026-07-15 11:15:00'),
    -- cv-otheruser @11: outbound by a DIFFERENT user (not the owner) -> NOT counted
    (4, 'cv-otheruser', 'someone-else', 'outbound', '2026-07-15 11:20:00'),
    -- cv-replied again @12: owner replies in a second hour -> counts once for 12
    (5, 'cv-replied', 'owner-1', 'outbound', '2026-07-15 12:10:00')
");

$start = '2026-07-15 00:00:00';
$end   = '2026-07-15 23:59:59';

// Portable mirror of the model's SELECT: HOUR() -> strftime('%H') integer.
$sql = "SELECT
            CAST(strftime('%H', gm.date_added) AS INTEGER) AS hour_of_day,
            COUNT(DISTINCT CASE WHEN gm.direction = 'inbound' THEN gm.id END) AS inbound_count,
            COUNT(DISTINCT CASE WHEN gm.direction = 'outbound' AND gm.user_id = glo.owner_user_id THEN gm.id END) AS outbound_count,
            COUNT(DISTINCT CASE WHEN gm.direction = 'outbound' AND gm.user_id = glo.owner_user_id THEN glo.conversation_id END) AS leads_count
        FROM ghl_lead_ownership glo
        INNER JOIN ghl_messages gm
            ON gm.conversation_id = glo.conversation_id
           AND gm.date_added >= glo.lead_started_at
           AND (glo.lead_ended_at IS NULL OR gm.date_added < glo.lead_ended_at)
        WHERE glo.owner_user_id = :owner
          AND gm.date_added BETWEEN :s AND :e
          AND gm.direction IN ('inbound', 'outbound')
        GROUP BY hour_of_day
        ORDER BY hour_of_day ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute(array(':owner' => 'owner-1', ':s' => $start, ':e' => $end));
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$byHour = array();
foreach ($rows as $r) {
    $byHour[(int) $r['hour_of_day']] = $r;
}

// Hour 11: three leads had activity, but only cv-replied got an owner reply.
assert_eq('hour 11 inbound = 2 (cv-replied + cv-silent)', 2, (int) $byHour[11]['inbound_count']);
assert_eq('hour 11 leads handled = 1 (only the replied lead)', 1, (int) $byHour[11]['leads_count']);

// Hour 12: owner replied to cv-replied again -> counts once in its own hour.
assert_eq('hour 12 leads handled = 1 (same lead, second hour)', 1, (int) $byHour[12]['leads_count']);

echo "\nAll assertions passed.\n";
