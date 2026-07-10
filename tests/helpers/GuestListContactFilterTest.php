<?php
/**
 * Run with: php tests/helpers/GuestListContactFilterTest.php
 *
 * Locks the Guest List "Contact Number" filter on the booking branch.
 *
 * Bug: the filter used to match ONLY the per-guest gl.Mobile. But a filled
 * booking usually stores each member's OWN number in gl.Mobile, while the
 * booking's main contact (the leader) lives in booking.Mobile /
 * customer.phone_number. Those filled bookings are skipped by the leader
 * fallback branch (it only fires when the guest list is empty), so the
 * booking's own contact number was searchable by NO branch — ~1.5k bookings
 * on prod could not be found by their contact number.
 *
 * Fix: the booking-branch contact clause matches gl.Mobile OR b.Mobile OR
 * c.phone_number, so searching either a member's own number or the booking's
 * contact surfaces the booking's guests — mirroring how the name search also
 * matches b.Customer to surface a whole team.
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

// ---- model the booking-branch join + contact WHERE in SQLite --------------
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE booking  (BookingID INTEGER, Mobile TEXT, CustomerID INTEGER)");
$pdo->exec("CREATE TABLE customer (CustomerID INTEGER, phone_number TEXT)");
$pdo->exec("CREATE TABLE guest_list (BookingID INTEGER, dedup TEXT, Mobile TEXT)");

// Booking 1: FILLED guest list. Leader/contact = 192281850 (in booking.Mobile
// and customer.phone_number), but the 3 guest rows carry their OWN numbers.
$pdo->exec("INSERT INTO booking  VALUES (1, '192281850', 100)");
$pdo->exec("INSERT INTO customer VALUES (100, '192281850')");
$pdo->exec("INSERT INTO guest_list VALUES (1,'g1a','93874576'),(1,'g1b','94934546'),(1,'g1c','67532048')");

// Booking 2: a member carries their own distinct mobile 0123456789.
$pdo->exec("INSERT INTO booking  VALUES (2, '0111222333', 200)");
$pdo->exec("INSERT INTO customer VALUES (200, '0111222333')");
$pdo->exec("INSERT INTO guest_list VALUES (2,'g2a','0123456789')");

// Count distinct guests the booking branch returns for a contact search.
// $columns is the list of columns the clause LIKE-matches (the fix widens it).
$search = function ($needle, array $columns) use ($pdo) {
    $ors    = array();
    $params = array();
    foreach ($columns as $col) {
        $ors[]    = "{$col} LIKE ?";
        $params[] = '%' . $needle . '%';
    }
    $sql = "SELECT COUNT(DISTINCT gl.dedup)
            FROM booking b
            JOIN guest_list gl ON gl.BookingID = b.BookingID
            LEFT JOIN customer c ON c.CustomerID = b.CustomerID
            WHERE (" . implode(' OR ', $ors) . ")";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
};

$OLD = array('gl.Mobile');                              // buggy: guest mobile only
$NEW = array('gl.Mobile', 'b.Mobile', 'c.phone_number'); // fixed: + booking contact

// The bug: the booking's own contact number finds nothing under the old clause.
assert_eq('OLD: booking contact 192281850 -> none (bug)', 0, $search('192281850', $OLD));

// The fix: the booking's contact surfaces that booking's 3 guests.
assert_eq('NEW: booking contact 192281850 -> 3 guests', 3, $search('192281850', $NEW));

// A member's own mobile still resolves to exactly that member, under both.
assert_eq('OLD: member mobile 0123456789 -> 1', 1, $search('0123456789', $OLD));
assert_eq('NEW: member mobile 0123456789 -> 1', 1, $search('0123456789', $NEW));

// Partial (trunk-0 stripped, as shown in the UI) still matches the fix.
assert_eq('NEW: partial 92281850 -> 3 guests', 3, $search('92281850', $NEW));

// A number belonging to nobody matches nothing.
assert_eq('NEW: unknown 555000 -> none', 0, $search('555000', $NEW));

echo "\nAll assertions passed.\n";
