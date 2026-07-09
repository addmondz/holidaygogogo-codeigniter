<?php
/**
 * Run with: php tests/helpers/LeadReplyActivityLeadsModalTest.php
 *
 * Pins the per-number drill-down modal on the Lead Reply Activity dashboard.
 * Clicking "New Lead Picked Up" or "Lead Responded" for an owner opens a modal
 * listing the individual leads behind that number. For the count and the list
 * to agree, the list queries MUST reuse the exact same universe as the count
 * queries:
 *
 *   New Lead Picked Up (build_lead_reply_assignment_where_clause):
 *     is_assigned_owner = 1
 *     AND NULLIF(assigned_to_user_id,'') = owner_user_id
 *     AND never-contacted-before (no earlier inbound for the contact)
 *     AND owner IN (:uids) AND lead_started_at BETWEEN :start AND :end
 *     count = COUNT(DISTINCT processed_lead_id)
 *
 *   Lead Responded (build_lead_reply_created_where_clause + message join):
 *     is_reply_owner = 1 AND owner IN (:uids)
 *     INNER JOIN outbound owner replies inside the ownership window
 *     AND message time BETWEEN :start AND :end AND in 07:00-22:00 hours
 *     count = COUNT(DISTINCT processed_lead_id)
 *
 * Part A mirrors both list queries in SQLite and asserts the list row-count
 * equals the count query (they cannot drift). Part B feeds rows through the real
 * lead_reply_activity_leads_format() helper to lock the modal's JSON shape.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require_once __DIR__ . '/../../application/helpers/lead_reply_activity_leads_helper.php';

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
    owner_user_id       TEXT,
    processed_lead_id   INTEGER,
    conversation_id     TEXT,
    contact_id          TEXT,
    is_assigned_owner   INTEGER,
    is_reply_owner      INTEGER,
    assigned_to_user_id TEXT,
    lead_started_at     TEXT,
    lead_ended_at       TEXT
)");

$pdo->exec("CREATE TABLE ghl_messages (
    conversation_id TEXT,
    contact_id      TEXT,
    user_id         TEXT,
    direction       TEXT,
    date_added      TEXT
)");

$pdo->exec("CREATE TABLE ghl_conversations (
    conversation_id TEXT,
    contact_name    TEXT,
    full_name       TEXT,
    phone           TEXT
)");

$pdo->exec("CREATE TABLE ghl_users (UserID TEXT, Name TEXT)");

// ---- Owner u1, window 2026-06-19 ----
// PICKED-UP universe (assigned + brand-new customer):
//   lead 1 cvA cA : brand-new, assigned=u1                 COUNT
//   lead 2 cvB cB : brand-new, assigned=u1                 COUNT
//   lead 3 cvC cC : re-engaged (earlier inbound)           EXCLUDED
//   lead 6 cvF cF : assigned_to_user_id=u2 (owner!=assignee) EXCLUDED
// RESPONDED universe (is_reply_owner + owner outbound in-hours in window):
//   lead 10 cv10 : u1 outbound 09:00 & 09:05               COUNT (reply_count 2)
//   lead 11 cv11 : u1 outbound only 23:30 (out of hours)   EXCLUDED
//   lead 12 cv12 : outbound by u2 (not owner)              EXCLUDED
//   lead 13 cv13 : u1 outbound 10:00                       COUNT (reply_count 1)
$pdo->exec("INSERT INTO ghl_lead_ownership
    (owner_user_id, processed_lead_id, conversation_id, contact_id, is_assigned_owner, is_reply_owner, assigned_to_user_id, lead_started_at, lead_ended_at) VALUES
    ('u1',  1, 'cvA', 'cA', 1, 0, 'u1', '2026-06-19 09:00:00', NULL),
    ('u1',  2, 'cvB', 'cB', 1, 0, 'u1', '2026-06-19 09:45:00', NULL),
    ('u1',  3, 'cvC', 'cC', 1, 0, 'u1', '2026-06-19 14:30:00', NULL),
    ('u1',  6, 'cvF', 'cF', 1, 0, 'u2', '2026-06-19 11:00:00', NULL),
    ('u1', 10, 'cv10','c10',0, 1, 'u1', '2026-06-19 08:00:00', NULL),
    ('u1', 11, 'cv11','c11',0, 1, 'u1', '2026-06-19 08:00:00', NULL),
    ('u1', 12, 'cv12','c12',0, 1, 'u1', '2026-06-19 08:00:00', NULL),
    ('u1', 13, 'cv13','c13',0, 1, 'u1', '2026-06-19 08:00:00', NULL)");

$pdo->exec("INSERT INTO ghl_messages (conversation_id, contact_id, user_id, direction, date_added) VALUES
    ('cvA', 'cA', NULL, 'inbound',  '2026-06-19 09:00:00'),
    ('cvB', 'cB', NULL, 'inbound',  '2026-06-19 09:45:00'),
    ('cvC', 'cC', NULL, 'inbound',  '2026-06-05 08:00:00'),
    ('cvC', 'cC', NULL, 'inbound',  '2026-06-19 14:30:00'),
    ('cvF', 'cF', NULL, 'inbound',  '2026-06-19 11:00:00'),
    ('cv10','c10','u1', 'outbound', '2026-06-19 09:00:00'),
    ('cv10','c10','u1', 'outbound', '2026-06-19 09:05:00'),
    ('cv11','c11','u1', 'outbound', '2026-06-19 23:30:00'),
    ('cv12','c12','u2', 'outbound', '2026-06-19 10:00:00'),
    ('cv13','c13','u1', 'outbound', '2026-06-19 10:00:00')");

$pdo->exec("INSERT INTO ghl_conversations (conversation_id, contact_name, full_name, phone) VALUES
    ('cvA', 'Alice',  '', '0111'),
    ('cvB', '',       'Bob Full', '0222'),
    ('cv10','Carol',  '', ''),
    ('cv13','Dave',   '', '0444')");

$pdo->exec("INSERT INTO ghl_users (UserID, Name) VALUES ('u1', 'Nur TC')");

$start = '2026-06-19';
$end = '2026-06-19';

// ---------- Part A1: picked-up count vs list parity ----------
$assignmentWhere = "
    WHERE glo.is_assigned_owner = 1
      AND NULLIF(glo.assigned_to_user_id, '') = glo.owner_user_id
      AND NOT EXISTS (
          SELECT 1 FROM ghl_messages gm_prior
          WHERE gm_prior.contact_id = glo.contact_id
            AND gm_prior.direction = 'inbound'
            AND gm_prior.date_added < glo.lead_started_at
      )
      AND glo.owner_user_id IN ('u1')
      AND glo.lead_started_at >= '{$start} 00:00:00'
      AND glo.lead_started_at <= '{$end} 23:59:59'
";

$pickedCount = (int) $pdo->query("
    SELECT COUNT(DISTINCT glo.processed_lead_id) FROM ghl_lead_ownership glo {$assignmentWhere}
")->fetchColumn();

$pickedListRows = $pdo->query("
    SELECT
        glo.processed_lead_id,
        glo.conversation_id,
        COALESCE(NULLIF(gc.contact_name, ''), NULLIF(gc.full_name, ''), 'Unknown Contact') AS contact_name,
        gc.phone,
        COALESCE(NULLIF(gu.Name, ''), glo.owner_user_id) AS owner_name,
        glo.lead_started_at,
        glo.lead_ended_at,
        (SELECT COUNT(*) FROM ghl_messages gm_count
           WHERE gm_count.conversation_id = glo.conversation_id
             AND gm_count.date_added >= glo.lead_started_at
             AND (glo.lead_ended_at IS NULL OR gm_count.date_added < glo.lead_ended_at)) AS message_count
    FROM ghl_lead_ownership glo
    LEFT JOIN ghl_conversations gc ON gc.conversation_id = glo.conversation_id
    LEFT JOIN ghl_users gu ON gu.UserID = glo.owner_user_id
    {$assignmentWhere}
    GROUP BY glo.processed_lead_id
    ORDER BY glo.lead_started_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

assert_eq('picked_up count', 2, $pickedCount);
assert_eq('picked_up list length == count', $pickedCount, count($pickedListRows));

// ---------- Part A2: responded count vs list parity ----------
$respondedJoin = "
    FROM ghl_lead_ownership glo
    INNER JOIN ghl_messages gm
        ON gm.conversation_id = glo.conversation_id
       AND gm.user_id = glo.owner_user_id
       AND gm.direction = 'outbound'
       AND gm.date_added >= glo.lead_started_at
       AND (glo.lead_ended_at IS NULL OR gm.date_added < glo.lead_ended_at)
    WHERE glo.is_reply_owner = 1
      AND glo.owner_user_id IN ('u1')
      AND gm.date_added >= '{$start} 00:00:00'
      AND gm.date_added <= '{$end} 23:59:59'
      AND (TIME(gm.date_added) BETWEEN '07:00:00' AND '22:00:00')
";

$respondedCount = (int) $pdo->query("
    SELECT COUNT(DISTINCT glo.processed_lead_id) {$respondedJoin}
")->fetchColumn();

$respondedListRows = $pdo->query("
    SELECT
        glo.processed_lead_id,
        glo.conversation_id,
        COALESCE(NULLIF(gc.contact_name, ''), NULLIF(gc.full_name, ''), 'Unknown Contact') AS contact_name,
        gc.phone,
        COALESCE(NULLIF(gu.Name, ''), glo.owner_user_id) AS owner_name,
        glo.lead_started_at,
        glo.lead_ended_at,
        MIN(gm.date_added) AS first_reply_at,
        COUNT(*) AS reply_count
    FROM ghl_lead_ownership glo
    INNER JOIN ghl_messages gm
        ON gm.conversation_id = glo.conversation_id
       AND gm.user_id = glo.owner_user_id
       AND gm.direction = 'outbound'
       AND gm.date_added >= glo.lead_started_at
       AND (glo.lead_ended_at IS NULL OR gm.date_added < glo.lead_ended_at)
    LEFT JOIN ghl_conversations gc ON gc.conversation_id = glo.conversation_id
    LEFT JOIN ghl_users gu ON gu.UserID = glo.owner_user_id
    WHERE glo.is_reply_owner = 1
      AND glo.owner_user_id IN ('u1')
      AND gm.date_added >= '{$start} 00:00:00'
      AND gm.date_added <= '{$end} 23:59:59'
      AND (TIME(gm.date_added) BETWEEN '07:00:00' AND '22:00:00')
    GROUP BY glo.processed_lead_id
    ORDER BY first_reply_at ASC
")->fetchAll(PDO::FETCH_ASSOC);

assert_eq('responded count', 2, $respondedCount);
assert_eq('responded list length == count', $respondedCount, count($respondedListRows));

// lead 10 replied twice, lead 13 once; both first replies land in-hours.
$replyCountsByLead = array();
foreach ($respondedListRows as $r) {
    $replyCountsByLead[(int) $r['processed_lead_id']] = (int) $r['reply_count'];
}
assert_eq('lead 10 reply_count', 2, $replyCountsByLead[10]);
assert_eq('lead 13 reply_count', 1, $replyCountsByLead[13]);

// ---------- Part B: helper formatting ----------
$pickedFmt = lead_reply_activity_leads_format($pickedListRows, 'picked_up');
assert_eq('picked_up formatted length', 2, count($pickedFmt));

// Bob's contact_name is empty -> falls back to full_name via the SQL COALESCE.
$byContact = array();
foreach ($pickedFmt as $r) { $byContact[$r['contact_name']] = $r; }
assert_eq('Alice phone kept', '0111', $byContact['Alice']['phone']);
assert_eq('Bob full_name fallback', true, isset($byContact['Bob Full']));
// picked_up activity = first contact (lead_started_at).
assert_eq('Alice activity_label = first contact', '19 Jun 2026 09:00 AM', $byContact['Alice']['activity_label']);

$respondedFmt = lead_reply_activity_leads_format($respondedListRows, 'responded');
$carol = null;
foreach ($respondedFmt as $r) { if ($r['conversation_id'] === 'cv10') { $carol = $r; } }
assert_eq('Carol empty phone -> No phone', 'No phone', $carol['phone']);
assert_eq('Carol activity_label = first reply', '19 Jun 2026 09:00 AM', $carol['activity_label']);

// ---------- Part C: metric -> activity field mapping ----------
assert_eq('picked_up field', 'lead_started_at', lead_reply_activity_leads_activity_field('picked_up'));
assert_eq('responded field', 'first_reply_at', lead_reply_activity_leads_activity_field('responded'));
assert_eq('transfer_out field', 'reply_created_at', lead_reply_activity_leads_activity_field('transfer_out'));
assert_eq('today_handling field', 'first_reply_at', lead_reply_activity_leads_activity_field('today_handling'));
assert_eq('unknown metric field default', 'lead_started_at', lead_reply_activity_leads_activity_field('nope'));

// transfer_out reads reply_created_at for its date column.
$transferFmt = lead_reply_activity_leads_format(array(
    array('contact_name' => 'Zed', 'phone' => '0999', 'conversation_id' => 'cvZ', 'reply_created_at' => '2026-06-19 15:30:00'),
), 'transfer_out');
assert_eq('transfer_out activity_label = reply_created_at', '19 Jun 2026 03:30 PM', $transferFmt[0]['activity_label']);

// ---------- Part D: today-handling = responded minus transferred-out ----------
$responded = array(
    array('processed_lead_id' => 10, 'first_reply_at' => '2026-06-19 09:00:00'),
    array('processed_lead_id' => 13, 'first_reply_at' => '2026-06-19 10:00:00'),
    array('processed_lead_id' => 20, 'first_reply_at' => '2026-06-19 11:00:00'),
);
$transferred = array(
    array('processed_lead_id' => 13),
    array('processed_lead_id' => 99), // a transfer that was never in responded -> ignored
);
$handling = lead_reply_activity_today_handling_leads($responded, $transferred);
$handlingIds = array_map(function ($r) { return (int) $r['processed_lead_id']; }, $handling);
sort($handlingIds);
assert_eq('today handling drops transferred lead 13', array(10, 20), $handlingIds);

// ---------- Part E: edge cases ----------
$edge = lead_reply_activity_leads_format(array(array()), 'picked_up');
assert_eq('edge contact fallback', 'Unknown Contact', $edge[0]['contact_name']);
assert_eq('edge phone fallback', 'No phone', $edge[0]['phone']);
assert_eq('edge missing activity -> dash', '-', $edge[0]['activity_label']);
assert_eq('bad date -> dash', '-', lead_reply_activity_leads_datetime_label('not-a-date'));

echo "\nAll LeadReplyActivityLeadsModal assertions passed.\n";
