<?php
/**
 * Run with: php tests/helpers/GuestListColumnFiltersTest.php
 *
 * Locks the Guest List column filters added on top of the original set:
 *   - Contact Number / Email : text search on BOTH branches (booking guests
 *     have gl.Mobile/gl.Email, GHL leads have gc.phone/gc.email), so they do
 *     NOT suppress the GHL branch.
 *   - Destination            : booking-only (b.Destination = CategoryID) — a
 *     GHL lead has no destination, so it drops the GHL branch.
 *   - Guest Role             : "Team Leader"/"Team Member" are booking-only and
 *     drop GHL; "Lead" selects GHL leads only and drops the booking branch.
 *   - Num of Pax (min/max)   : booking-only aggregate (HAVING on grouped pax).
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

// ---- which new filters suppress the GHL branch ----------------------------
assert_eq('contact_number keeps GHL', false, guest_list_ghl_suppressed_by_filters(array('contact_number' => '012')));
assert_eq('email keeps GHL',          false, guest_list_ghl_suppressed_by_filters(array('email' => 'a@b.com')));
assert_eq('empty destination keeps',  false, guest_list_ghl_suppressed_by_filters(array('destination' => '  ')));
assert_eq('destination drops GHL',    true,  guest_list_ghl_suppressed_by_filters(array('destination' => '7')));
assert_eq('pax_min drops GHL',        true,  guest_list_ghl_suppressed_by_filters(array('pax_min' => '2')));
assert_eq('pax_max drops GHL',        true,  guest_list_ghl_suppressed_by_filters(array('pax_max' => '5')));
assert_eq('role=Team Leader drops',   true,  guest_list_ghl_suppressed_by_filters(array('role' => 'Team Leader')));
assert_eq('role=Team Member drops',   true,  guest_list_ghl_suppressed_by_filters(array('role' => 'Team Member')));
assert_eq('role=Lead keeps GHL',      false, guest_list_ghl_suppressed_by_filters(array('role' => 'Lead')));

// ---- which new filters suppress the booking branch ------------------------
assert_eq('no role keeps bookings',       false, guest_list_bookings_suppressed_by_filters(array()));
assert_eq('role=Lead drops bookings',     true,  guest_list_bookings_suppressed_by_filters(array('role' => 'Lead')));
assert_eq('role=Team Leader keeps bookings', false, guest_list_bookings_suppressed_by_filters(array('role' => 'Team Leader')));
assert_eq('empty role keeps bookings',    false, guest_list_bookings_suppressed_by_filters(array('role' => '  ')));

// ---- integration: pax HAVING range over grouped bookings ------------------
// Mirrors the model: one row per (dedup, booking) carries that booking's pax;
// a guest's Num of Pax is the SUM across their bookings, then range-filtered.
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE g (dedup TEXT, booking INTEGER, pax INTEGER)");
// alice total 5 (2+3), bob total 1, cara total 8
$pdo->exec("INSERT INTO g (dedup, booking, pax) VALUES ('alice',1,2),('alice',2,3),('bob',3,1),('cara',4,8)");

$pax_in_range = function ($min, $max) use ($pdo) {
    $having = array();
    $params = array();
    if ($min !== null) { $having[] = "SUM(pax) >= ?"; $params[] = (int) $min; }
    if ($max !== null) { $having[] = "SUM(pax) <= ?"; $params[] = (int) $max; }
    $sql = "SELECT dedup FROM g GROUP BY dedup";
    if ($having) { $sql .= " HAVING " . implode(' AND ', $having); }
    $sql = "SELECT COUNT(*) FROM ($sql) z";
    $stmt = $pdo->prepare($sql);
    // Bind as INTEGER so the SUM (integer) compares numerically — SQLite orders
    // text above all numbers, so a string-bound param would never match. MySQL
    // (prod) coerces, and the model casts pax bounds to int.
    foreach ($params as $i => $v) { $stmt->bindValue($i + 1, $v, PDO::PARAM_INT); }
    $stmt->execute();
    return (int) $stmt->fetchColumn();
};

assert_eq('pax >= 2 -> alice,cara',    2, $pax_in_range(2, null));
assert_eq('pax <= 5 -> alice,bob',     2, $pax_in_range(null, 5));
assert_eq('pax 2..5 -> alice',         1, $pax_in_range(2, 5));
assert_eq('pax 10..20 -> none',        0, $pax_in_range(10, 20));

// ---- integration: role grouping (MAX(is_leader)) --------------------------
// A guest is "Team Leader" if they lead ANY booking (MAX(is_leader)=1),
// otherwise "Team Member" (MAX(is_leader)=0).
$pdo->exec("CREATE TABLE r (dedup TEXT, is_leader INTEGER)");
// alice leads one booking -> Team Leader; bob never leads -> Team Member
$pdo->exec("INSERT INTO r (dedup, is_leader) VALUES ('alice',0),('alice',1),('bob',0),('bob',0)");

$role_count = function ($want_leader) use ($pdo) {
    // Literal 1/0 mirrors the model's HAVING (no bound param), sidestepping the
    // SQLite text-vs-integer ordering pitfall.
    $lit = $want_leader ? 1 : 0;
    $sql = "SELECT COUNT(*) FROM (
        SELECT dedup FROM r GROUP BY dedup HAVING MAX(is_leader) = {$lit}
    ) z";
    return (int) $pdo->query($sql)->fetchColumn();
};
assert_eq('Team Leader -> alice', 1, $role_count(1));
assert_eq('Team Member -> bob',   1, $role_count(0));

echo "\nAll assertions passed.\n";
