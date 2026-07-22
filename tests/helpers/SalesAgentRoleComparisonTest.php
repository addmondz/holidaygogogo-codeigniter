<?php
/**
 * Run with: php tests/helpers/SalesAgentRoleComparisonTest.php
 *
 * Locks the "compare only against the SALES AGENT role" rule for the TC
 * summary-card "Compare the Best" leaderboards. The comparison universe must
 * be restricted to admins whose Level is 20 (SALES AGENT) so a non-sales-agent
 * (OP, Finance, Owner, Team Lead, TC) who happens to hold a credited booking
 * slot can never appear as the benchmark "Best:".
 *
 * Two leaderboard shapes are exercised:
 *   1. Booking-table credited-agent leaderboard (BC / Sales / Cancellation):
 *      the admin JOIN gains `AND admin.Level = '20'` and becomes an INNER JOIN
 *      so non-level-20 credited agents drop out entirely.
 *   2. GHL-user-keyed leaderboard (Reply time / Conversion / Pickup / Leads):
 *      restricted in PHP to the set of GHL UserIDs that map to a level-20
 *      admin (mapping table first, email fallback) -- mirrors
 *      sales_agent_ghl_uids() in the controller.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require_once __DIR__ . '/../../application/helpers/lead_conversion_credit_helper.php';

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Level mirrors the production ENUM('10','20',...) — a TEXT/string column, so the
// scope filter must use the string literal '20' (an integer literal would match
// the ENUM index position, not the value, and silently exclude everyone).
$pdo->exec("CREATE TABLE admin (
    AdminID INTEGER PRIMARY KEY,
    Name TEXT,
    Email TEXT,
    Level TEXT
)");
$pdo->exec("CREATE TABLE booking (
    BookingID INTEGER PRIMARY KEY,
    SalesAgent INTEGER,
    SalesAgent2 INTEGER,
    InsertDate TEXT,
    NetTotal REAL,
    BookingConfirmationTitle TEXT,
    CancelStatus TEXT,
    Status TEXT
)");
$pdo->exec("CREATE TABLE admin_lead_dashboard_agents (
    AdminID INTEGER,
    GhlUserID TEXT
)");
$pdo->exec("CREATE TABLE ghl_users (
    UserID TEXT,
    Name TEXT,
    Email TEXT
)");

// Levels: 20 = SALES AGENT (in scope), everything else out of scope.
$pdo->exec("INSERT INTO admin VALUES
    (10, 'Alice', 'alice@x.com',  '20'),   /* sales agent */
    (20, 'Bob',   'bob@x.com',    '20'),   /* sales agent */
    (30, 'Carol', 'carol@x.com',  '50'),   /* TC          */
    (40, 'Diana', 'diana@x.com',  '40')    /* OP          */
");

// ---------------------------------------------------------------------------
// 1. Booking-table credited-agent leaderboard (BC Created, pre-cutoff = SA).
//    Carol (TC, level 50) is the raw top with 3 BCs but must be excluded;
//    Diana (OP, level 40) with 2 BCs must be excluded; the winner among
//    sales agents is Alice (2) over Bob (1).
// ---------------------------------------------------------------------------
$pdo->exec("INSERT INTO booking VALUES
    (1, 30, 0, '2026-05-01', 100, 'BOOKING CONFIRMATION', 'N', 'P'),  /* Carol - excluded */
    (2, 30, 0, '2026-05-02', 100, 'BOOKING CONFIRMATION', 'N', 'P'),  /* Carol - excluded */
    (3, 30, 0, '2026-05-03', 100, 'BOOKING CONFIRMATION', 'N', 'P'),  /* Carol - excluded */
    (4, 40, 0, '2026-05-04', 100, 'BOOKING CONFIRMATION', 'N', 'P'),  /* Diana - excluded */
    (5, 40, 0, '2026-05-05', 100, 'BOOKING CONFIRMATION', 'N', 'P'),  /* Diana - excluded */
    (6, 10, 0, '2026-05-06', 100, 'BOOKING CONFIRMATION', 'N', 'P'),  /* Alice */
    (7, 10, 0, '2026-05-07', 100, 'BOOKING CONFIRMATION', 'N', 'P'),  /* Alice */
    (8, 20, 0, '2026-05-08', 100, 'BOOKING CONFIRMATION', 'N', 'P')   /* Bob   */
");

$agent_expr = lead_conversion_credit_agent_expr();

// New leaderboard SQL: INNER JOIN admin ... AND admin.Level = '20'.
$sql = "
    SELECT
        {$agent_expr} AS credited_agent_id,
        admin.Name AS agent_name,
        COUNT(*) AS bc_count
    FROM booking
    INNER JOIN admin ON admin.AdminID = {$agent_expr} AND admin.Level = '20'
    WHERE booking.BookingConfirmationTitle = 'BOOKING CONFIRMATION'
      AND booking.CancelStatus = 'N'
      AND booking.Status != 'N'
      AND booking.InsertDate BETWEEN :ms AND :me
    GROUP BY credited_agent_id, agent_name
    HAVING credited_agent_id IS NOT NULL AND credited_agent_id > 0
    ORDER BY bc_count DESC, agent_name ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([':ms' => '2026-05-01', ':me' => '2026-05-31']);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

assert_eq('booking leaderboard: only sales agents', 2, count($rows));
assert_eq('booking leaderboard: best is Alice (not Carol/Diana)', 'Alice', (string) $rows[0]['agent_name']);
assert_eq('booking leaderboard: best count', 2, (int) $rows[0]['bc_count']);
assert_eq('booking leaderboard: 2nd is Bob', 'Bob', (string) $rows[1]['agent_name']);
$names = array_map(function ($r) { return (string) $r['agent_name']; }, $rows);
assert_eq('booking leaderboard: Carol (TC) excluded', false, in_array('Carol', $names, true));
assert_eq('booking leaderboard: Diana (OP) excluded', false, in_array('Diana', $names, true));

// ---------------------------------------------------------------------------
// 2. GHL-user-keyed eligible set: mirror sales_agent_ghl_uids().
//    g-alice + g-bob map (via mapping table / email) to level-20 admins;
//    g-carol (TC) and g-diana (OP) must NOT be eligible.
// ---------------------------------------------------------------------------
$pdo->exec("INSERT INTO ghl_users VALUES
    ('g-alice', 'Alice GHL', 'alice@x.com'),
    ('g-bob',   'Bob GHL',   'bob@x.com'),
    ('g-carol', 'Carol GHL', 'carol@x.com'),
    ('g-diana', 'Diana GHL', 'diana@x.com')
");
// Alice mapped via mapping table; Bob/Carol/Diana fall back to email match.
$pdo->exec("INSERT INTO admin_lead_dashboard_agents VALUES (10, 'g-alice')");

$eligible = array();
foreach ($pdo->query(
    "SELECT alda.GhlUserID
     FROM admin_lead_dashboard_agents alda
     INNER JOIN admin a ON a.AdminID = alda.AdminID
     WHERE a.Level = '20' AND NULLIF(alda.GhlUserID, '') IS NOT NULL"
)->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $eligible[(string) $r['GhlUserID']] = true;
}
foreach ($pdo->query(
    "SELECT gu.UserID
     FROM admin a
     INNER JOIN ghl_users gu ON LOWER(TRIM(gu.Email)) = LOWER(TRIM(a.Email))
     WHERE a.Level = '20'"
)->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $uid = (string) $r['UserID'];
    if ($uid !== '') { $eligible[$uid] = true; }
}

assert_eq('ghl eligible count (sales agents only)', 2, count($eligible));
assert_eq('ghl eligible: g-alice (mapping table)', true, isset($eligible['g-alice']));
assert_eq('ghl eligible: g-bob (email fallback)', true, isset($eligible['g-bob']));
assert_eq('ghl eligible: g-carol (TC) excluded', false, isset($eligible['g-carol']));
assert_eq('ghl eligible: g-diana (OP) excluded', false, isset($eligible['g-diana']));

echo "\nAll assertions passed.\n";
