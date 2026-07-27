<?php
/**
 * Run with: php tests/helpers/MyCardAdminGhlUidResolverTest.php
 *
 * Locks the admin_id -> GHL UserID(s) resolution used by the sales-agent
 * summary cards (Conversion Rate Month + My Leads + My Response Time).
 *
 * The canonical source is admin_lead_dashboard_agents. Email match on
 * admin.Email <-> ghl_users.Email is kept ONLY as a fallback for admins
 * whose mapping table has no rows yet. Team-inbox GHL users use a shared
 * Gmail that differs from the corporate admin.Email, so email match alone
 * misses them and the cards silently show zeros -- which is the bug this
 * resolver is here to prevent.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE admin (
    AdminID INTEGER PRIMARY KEY,
    Email   TEXT
)");
$pdo->exec("CREATE TABLE ghl_users (
    UserID TEXT PRIMARY KEY,
    Email  TEXT
)");
$pdo->exec("CREATE TABLE admin_lead_dashboard_agents (
    AdminID   INTEGER,
    GhlUserID TEXT,
    PRIMARY KEY (AdminID, GhlUserID)
)");

$pdo->exec("INSERT INTO admin VALUES
    (1, 'alice@corp.com'),
    (2, 'bob@corp.com'),
    (3, 'carol@corp.com'),
    (4, 'dave@corp.com'),
    (5, '  ERIN@CORP.COM  ')
");
$pdo->exec("INSERT INTO ghl_users VALUES
    ('UID-ALICE',  'alice@corp.com'),
    ('UID-BOB',    'team-inbox@gmail.com'),
    ('UID-CAROL',  'carol@corp.com'),
    ('UID-CAROL2', 'carol@corp.com'),
    ('UID-DAVE',   'shared@gmail.com'),
    ('UID-ERIN',   'erin@corp.com')
");
$pdo->exec("INSERT INTO admin_lead_dashboard_agents VALUES
    (2, 'UID-BOB'),
    (3, 'UID-CAROL'),
    (4, 'UID-DAVE'),
    (4, 'UID-DAVE-2')
");

$resolve = function ($pdo, $admin_id) {
    $stmt = $pdo->prepare(
        "SELECT GhlUserID FROM admin_lead_dashboard_agents WHERE AdminID = ?"
    );
    $stmt->execute(array($admin_id));
    $uids = array_values(array_filter(array_map(function ($r) {
        return (string) $r['GhlUserID'];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC)), 'strlen'));
    if (!empty($uids)) return $uids;

    $stmt = $pdo->prepare(
        "SELECT gu.UserID
         FROM admin a
         INNER JOIN ghl_users gu
           ON LOWER(TRIM(gu.Email)) = LOWER(TRIM(a.Email))
         WHERE a.AdminID = ?"
    );
    $stmt->execute(array($admin_id));
    return array_values(array_filter(array_map(function ($r) {
        return (string) $r['UserID'];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC)), 'strlen'));
};

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

// Alice: no mapping rows, but email matches ghl_users -> fallback hits.
assert_eq('alice falls back to email match', array('UID-ALICE'), $resolve($pdo, 1));

// Bob: team-inbox case. Corporate email 'bob@corp.com' does NOT match
// 'team-inbox@gmail.com', so the legacy email-only lookup returned [], which
// is the bug. The mapping table must drive resolution here.
assert_eq('bob uses mapping table (team inbox)', array('UID-BOB'), $resolve($pdo, 2));

// Carol: mapping AND email match both populated. Mapping wins so the admin's
// explicit choice is respected -- we must NOT also append UID-CAROL2.
assert_eq('carol mapping wins, no email append', array('UID-CAROL'), $resolve($pdo, 3));

// Dave: multiple mapped UIDs -- must return both so the cards aggregate
// across the admin's full GHL identity set.
assert_eq('dave returns all mapped uids', array('UID-DAVE', 'UID-DAVE-2'), $resolve($pdo, 4));

// Erin: whitespace + case differences on admin.Email -- the LOWER(TRIM(...))
// match must still resolve so the fallback isn't fragile to data hygiene.
assert_eq('erin email matched after lower/trim', array('UID-ERIN'), $resolve($pdo, 5));

// Unknown admin -> empty result so the controller emits the zero payload.
assert_eq('unknown admin returns empty', array(), $resolve($pdo, 99));

echo "\nAll assertions passed.\n";
