<?php
/**
 * Run with: php tests/helpers/LeadReplyActivityAgentAccessTest.php
 *
 * Locks the rule that a SALES AGENT (admin.Level = 20) may open the Lead Reply
 * Activity dashboard + hourly drill-down WITHOUT the VIEW REPORT ('VR')
 * permission, while every other Report route still requires 'VR'. The agent's
 * data is self-scoped to their OWN GHL identity, resolved mapping-table-first
 * with an email fallback -- the same bridge the sales-agent summary cards use
 * (see MyCardAdminGhlUidResolverTest). It must NOT widen to a team-view set.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require_once __DIR__ . '/../../application/helpers/report_access_helper.php';

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

// ---------------------------------------------------------------------------
// 1. Access gate: report_route_requires_redirect()
//    true  => redirect to Dashboard (blocked)
//    false => allowed through
// ---------------------------------------------------------------------------

// Sales agent (20), no VR: the 3 reply-activity routes are allowed.
assert_eq('agent reaches reply dashboard', false,
    report_route_requires_redirect('Lead_Reply_Activity_Dashboard', '20', array()));
assert_eq('agent reaches reply dashboard data (ajax)', false,
    report_route_requires_redirect('Lead_Reply_Activity_Dashboard_Data', '20', array()));
assert_eq('agent reaches reply hourly chart', false,
    report_route_requires_redirect('Lead_Reply_Activity_Hourly', '20', array()));
// The export shares the dashboard's self-scope, so an agent may download their
// own range without VR.
assert_eq('agent reaches reply excel export', false,
    report_route_requires_redirect('Lead_Reply_Activity_Export', '20', array()));

// Sales agent (20), no VR: any OTHER report route stays blocked.
assert_eq('agent blocked from lead ownership', true,
    report_route_requires_redirect('Lead_Ownership_Dashboard', '20', array()));
assert_eq('agent blocked from lead data', true,
    report_route_requires_redirect('Lead_Data', '20', array()));
// The details drill-down was NOT requested for agents -> stays VR-gated.
assert_eq('agent blocked from reply details', true,
    report_route_requires_redirect('Lead_Reply_Activity_Dashboard_Details', '20', array()));

// Non-agent without VR is still blocked from the reply dashboard (e.g. a
// finance/level-30 user) -- the bypass is scoped to the sales-agent role.
assert_eq('non-agent without VR blocked from reply dashboard', true,
    report_route_requires_redirect('Lead_Reply_Activity_Dashboard', '30', array()));

// VR holders keep full access regardless of level.
assert_eq('VR user reaches reply dashboard', false,
    report_route_requires_redirect('Lead_Reply_Activity_Dashboard', '50', array('VR')));
assert_eq('VR user reaches lead ownership', false,
    report_route_requires_redirect('Lead_Ownership_Dashboard', '50', array('VR')));

// Message Log keeps its own ML gate (handled per-method) -> never redirected here.
assert_eq('message log not redirected by this gate', false,
    report_route_requires_redirect('Ghl_Message_Log', '20', array()));

// Defensive: non-array access_control must not fatal.
assert_eq('null access_control treated as empty', true,
    report_route_requires_redirect('Lead_Data', '50', null));

// ---------------------------------------------------------------------------
// 2. Self-scope resolution for a sales agent: mapping table first, email
//    fallback. Mirrors Report_Model::Resolve_Self_Ghl_Agents().
// ---------------------------------------------------------------------------
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE admin (AdminID INTEGER PRIMARY KEY, Email TEXT, Level TEXT)");
$pdo->exec("CREATE TABLE ghl_users (UserID TEXT, Email TEXT)");
$pdo->exec("CREATE TABLE admin_lead_dashboard_agents (AdminID INTEGER, GhlUserID TEXT)");

$pdo->exec("INSERT INTO admin VALUES
    (1, 'alice@corp.com', '20'),   /* mapped agent          */
    (2, 'bob@corp.com',   '20'),   /* unmapped, email match  */
    (3, 'carol@corp.com', '20')    /* unmapped, no ghl user  */
");
$pdo->exec("INSERT INTO ghl_users VALUES
    ('UID-ALICE', 'alice@corp.com'),
    ('UID-ALT',   'alice@corp.com'),  /* would email-match but mapping wins */
    ('UID-BOB',   'bob@corp.com')
");
$pdo->exec("INSERT INTO admin_lead_dashboard_agents VALUES (1, 'UID-ALICE')");

$resolve = function ($pdo, $admin_id) {
    $stmt = $pdo->prepare("SELECT GhlUserID FROM admin_lead_dashboard_agents WHERE AdminID = ?");
    $stmt->execute(array($admin_id));
    $uids = array_values(array_filter(array_map(function ($r) {
        return (string) $r['GhlUserID'];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC)), 'strlen'));
    if (!empty($uids)) return $uids;

    $stmt = $pdo->prepare(
        "SELECT gu.UserID FROM admin a
         INNER JOIN ghl_users gu ON LOWER(TRIM(gu.Email)) = LOWER(TRIM(a.Email))
         WHERE a.AdminID = ?"
    );
    $stmt->execute(array($admin_id));
    return array_values(array_filter(array_map(function ($r) {
        return (string) $r['UserID'];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC)), 'strlen'));
};

// Alice: mapping wins, the email-matching UID-ALT must NOT be appended.
assert_eq('mapped agent -> own mapped uid only', array('UID-ALICE'), $resolve($pdo, 1));
// Bob: no mapping -> email fallback finds his own GHL user.
assert_eq('unmapped agent -> email fallback uid', array('UID-BOB'), $resolve($pdo, 2));
// Carol: no mapping, no GHL identity -> empty (downstream WHERE 1=0, sees nothing).
assert_eq('agent with no identity -> empty (sees nothing)', array(), $resolve($pdo, 3));

echo "\nAll assertions passed.\n";
