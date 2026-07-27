<?php
/**
 * Run with: php tests/helpers/GuestListTeamLeaderFilterTest.php
 *
 * Locks the Guest List "Team Leader" column filter — a text search on the
 * booking customer name (b.Customer), i.e. the group leader named on the BC
 * form. It is booking-only (a GHL lead has no booking customer), so like
 * Destination it drops the GHL branch; and it matches by LIKE, so partial
 * names find the guest whose booking that leader owns.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}
require __DIR__ . '/../../application/helpers/guest_contact_helper.php';

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

// ---- team_leader is booking-only: it suppresses the GHL branch -------------
assert_eq('team_leader drops GHL',       true,  guest_list_ghl_suppressed_by_filters(array('team_leader' => 'John')));
assert_eq('empty team_leader keeps GHL', false, guest_list_ghl_suppressed_by_filters(array('team_leader' => '  ')));
assert_eq('missing team_leader keeps',   false, guest_list_ghl_suppressed_by_filters(array()));

// team_leader never suppresses the booking branch (it selects from it).
assert_eq('team_leader keeps bookings',  false, guest_list_bookings_suppressed_by_filters(array('team_leader' => 'John')));

// ---- clause builder: typed box = LIKE, clicked name (exact flag) = equality -
assert_eq('no team_leader -> null',    null, guest_list_team_leader_clause(array()));
assert_eq('blank team_leader -> null', null, guest_list_team_leader_clause(array('team_leader' => '   ')));

$typed = guest_list_team_leader_clause(array('team_leader' => 'John'));
assert_eq('typed uses LIKE', ' AND b.Customer LIKE ? ', $typed['sql']);
assert_eq('typed wraps %',   '%John%',                  $typed['param']);

$clicked = guest_list_team_leader_clause(array('team_leader' => 'John Tan', 'team_leader_exact' => '1'));
assert_eq('clicked uses =',      ' AND b.Customer = ? ', $clicked['sql']);
assert_eq('clicked exact param', 'John Tan',             $clicked['param']);

// ---- integration: LIKE match on the booking customer name -----------------
// Mirrors the model's row-level WHERE: a guest appears when they belong to a
// booking whose Customer matches the entered team-leader name.
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE b (dedup TEXT, customer TEXT)");
$pdo->exec("INSERT INTO b (dedup, customer) VALUES
    ('alice','John Tan'),
    ('bob','Mary Lim'),
    ('cara','Johnny Wong')");

$match = function ($needle) use ($pdo) {
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT dedup) FROM b WHERE customer LIKE ?");
    $stmt->execute(array('%' . $needle . '%'));
    return (int) $stmt->fetchColumn();
};

assert_eq('"John" -> John Tan + Johnny Wong', 2, $match('John'));
assert_eq('"Mary" -> Mary Lim',               1, $match('Mary'));
assert_eq('"Tan" -> John Tan',                1, $match('Tan'));
assert_eq('"Zoe" -> none',                    0, $match('Zoe'));

// ---- integration: EXACT match (the clicked-name path) ---------------------
// Clicking a leader name filters on b.Customer = ?, so "John Tan" selects only
// that leader's team, and a partial "John" selects nobody.
$match_exact = function ($needle) use ($pdo) {
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT dedup) FROM b WHERE customer = ?");
    $stmt->execute(array($needle));
    return (int) $stmt->fetchColumn();
};

assert_eq('exact "John Tan" -> 1',    1, $match_exact('John Tan'));
assert_eq('exact "Johnny Wong" -> 1', 1, $match_exact('Johnny Wong'));
assert_eq('exact "John" -> none',     0, $match_exact('John'));

echo "\nAll assertions passed.\n";
