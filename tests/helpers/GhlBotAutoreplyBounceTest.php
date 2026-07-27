<?php
/**
 * Run with: php tests/helpers/GhlBotAutoreplyBounceTest.php
 *
 * Locks the time-gap bot-bounce filter for blasted WhatsApp leads:
 *   - An inbound that lands within GHL_BOT_AUTOREPLY_GAP_SECONDS of OUR
 *     immediately-preceding outbound blast is a bot auto-reply (is_bot_bounce),
 *     NOT a real new lead.
 *   - Organic inbounds (no preceding outbound) and human-paced replies are kept.
 *
 * Two layers:
 *   1. The pure decision helper ghl_is_bot_autoreply_bounce().
 *   2. A SQLite mirror proving the "New Lead Picked Up" count excludes flagged
 *      bounces once the report adds `is_bot_bounce = 0`.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require __DIR__ . '/../../application/helpers/ghl_bot_autoreply_helper.php';

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

$blast = strtotime('2026-07-15 09:00:07');

// --- Bounces: instant inbound right after our outbound blast. ---
assert_eq('10s after blast is a bounce', true,
    ghl_is_bot_autoreply_bounce('outbound', $blast, $blast + 10));
assert_eq('exactly at threshold (20s) is a bounce', true,
    ghl_is_bot_autoreply_bounce('outbound', $blast, $blast + 20));
assert_eq('0s (same second) is a bounce', true,
    ghl_is_bot_autoreply_bounce('outbound', $blast, $blast));

// --- Kept: human-paced or organic. ---
assert_eq('21s after blast is NOT a bounce', false,
    ghl_is_bot_autoreply_bounce('outbound', $blast, $blast + 21));
assert_eq('3 minutes after blast is NOT a bounce', false,
    ghl_is_bot_autoreply_bounce('outbound', $blast, $blast + 180));
assert_eq('organic opener (preceding inbound) is NOT a bounce', false,
    ghl_is_bot_autoreply_bounce('inbound', $blast, $blast + 5));
assert_eq('first message of conversation (no preceding msg) is NOT a bounce', false,
    ghl_is_bot_autoreply_bounce(null, null, $blast + 5));
assert_eq('negative gap (clock skew) is NOT a bounce', false,
    ghl_is_bot_autoreply_bounce('outbound', $blast, $blast - 30));

// --- Threshold override. ---
assert_eq('custom 60s threshold: 45s is a bounce', true,
    ghl_is_bot_autoreply_bounce('outbound', $blast, $blast + 45, 60));
assert_eq('custom 5s threshold: 10s is NOT a bounce', false,
    ghl_is_bot_autoreply_bounce('outbound', $blast, $blast + 10, 5));

// --- SQLite mirror: the report filter drops flagged bounces from the count. ---
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE ghl_lead_ownership (
    owner_user_id TEXT, processed_lead_id INTEGER, conversation_id TEXT,
    assigned_to_user_id TEXT, is_assigned_owner INTEGER, lead_started_at TEXT,
    is_bot_bounce INTEGER DEFAULT 0
)");
// Owner u1 on 2026-07-15: 3 assigned leads that landed, 2 of them bot bounces.
$pdo->exec("INSERT INTO ghl_lead_ownership
    (owner_user_id, processed_lead_id, conversation_id, assigned_to_user_id, is_assigned_owner, lead_started_at, is_bot_bounce) VALUES
    ('u1', 100, 'a', 'u1', 1, '2026-07-15 09:00:17', 1),
    ('u1', 101, 'b', 'u1', 1, '2026-07-15 09:28:16', 1),
    ('u1', 102, 'c', 'u1', 1, '2026-07-15 11:40:00', 0)");

$countSql = "
    SELECT COUNT(*) AS c
    FROM ghl_lead_ownership glo
    WHERE glo.is_assigned_owner = 1
      AND glo.assigned_to_user_id = glo.owner_user_id
      AND glo.owner_user_id = 'u1'
      AND glo.lead_started_at BETWEEN '2026-07-15 00:00:00' AND '2026-07-15 23:59:59'
";
$before = (int) $pdo->query($countSql)->fetch(PDO::FETCH_ASSOC)['c'];
$after  = (int) $pdo->query($countSql . " AND glo.is_bot_bounce = 0")->fetch(PDO::FETCH_ASSOC)['c'];

assert_eq('picked-up count before filter counts bounces', 3, $before);
assert_eq('picked-up count after filter excludes bounces', 1, $after);

echo "\nAll assertions passed.\n";
