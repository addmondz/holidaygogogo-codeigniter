<?php
/**
 * Run with: php tests/helpers/GuestListLockFilterTest.php
 *
 * Locks the Guest List dashboard "locked only" rule: the listing shows guests
 * only from bookings whose guest list is CURRENTLY locked (LockStatus = 'Y').
 * Never-locked lists ('N'/NULL) and locked-then-unlocked lists (back to 'N')
 * are both excluded. The rule is scoped to the Guest List page (mode 'guest')
 * so the campaign picker ('all') and the GHL / Manual lead pages are untouched.
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

// ---- predicate is only active on the Guest List dashboard (mode 'guest') ----
assert_eq('guest mode filters to locked', " AND b.LockStatus = 'Y' ", guest_list_lock_predicate('guest', 'b.LockStatus'));
assert_eq('campaign picker unaffected',    '', guest_list_lock_predicate('all', 'b.LockStatus'));
assert_eq('ghl page unaffected',           '', guest_list_lock_predicate('ghl', 'b.LockStatus'));
assert_eq('manual page unaffected',        '', guest_list_lock_predicate('manual', 'b.LockStatus'));

// ---- members only: leaders are dropped from the Guest List page ------------
assert_eq('guest mode excludes leaders', ' AND NOT (LEADER) ', guest_list_member_only_predicate('guest', 'LEADER'));
assert_eq('campaign picker keeps leaders', '', guest_list_member_only_predicate('all', 'LEADER'));
assert_eq('ghl page unaffected (members)', '', guest_list_member_only_predicate('ghl', 'LEADER'));

// ---- integration: only currently-locked bookings survive -------------------
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE booking (BookingID INTEGER PRIMARY KEY, LockStatus TEXT)");
$pdo->exec("INSERT INTO booking VALUES
    (1, 'Y'),
    (2, 'N'),
    (3, NULL),
    (4, 'Y')");

$count = function ($mode) use ($pdo) {
    $sql = "SELECT COUNT(*) FROM booking b WHERE 1 = 1" . guest_list_lock_predicate($mode, 'b.LockStatus');
    return (int) $pdo->query($sql)->fetchColumn();
};

assert_eq('guest mode keeps only locked (Y)', 2, $count('guest'));
assert_eq('all mode keeps everything',        4, $count('all'));

// ---- integration: members-only drops leader rows (mode 'guest') ------------
$pdo->exec("CREATE TABLE g (id INTEGER PRIMARY KEY, is_leader INTEGER)");
$pdo->exec("INSERT INTO g VALUES (1, 1), (2, 0), (3, 0), (4, 1)");

$members = function ($mode) use ($pdo) {
    // Mirror the model: exclude rows where the leader-match expr is true.
    $sql = "SELECT COUNT(*) FROM g WHERE 1 = 1" . guest_list_member_only_predicate($mode, 'g.is_leader = 1');
    return (int) $pdo->query($sql)->fetchColumn();
};

assert_eq('guest mode keeps members only', 2, $members('guest'));
assert_eq('all mode keeps leaders too',    4, $members('all'));

echo "\nAll assertions passed.\n";
