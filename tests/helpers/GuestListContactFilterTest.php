<?php
/**
 * Run with: php tests/helpers/GuestListContactFilterTest.php
 *
 * Locks the Guest List "Contact Number" filter on the booking branch.
 *
 * The filter matches ONLY the per-guest gl.Mobile — the exact number shown in
 * the Contact Num column. This keeps search honest: every returned row visibly
 * contains the searched digits.
 *
 * A booking's leader/contact number lives on booking.Mobile /
 * customer.phone_number, which the Contact Num column never displays. Matching
 * those here surfaced a whole team of members whose OWN displayed numbers did
 * NOT contain the searched digits, so it was deliberately dropped. (Filled
 * bookings whose contact lives only on the booking/customer are still reachable
 * by name; the leader-fallback branch covers bookings with an empty guest list.)
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
// $columns is the list of columns the clause LIKE-matches.
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

$CLAUSE = array('gl.Mobile'); // the live clause: guest's own displayed number only

// The booking's leader/contact number matches NOBODY's displayed Contact Num,
// so it returns nothing — no rows whose visible number fails to match.
assert_eq('booking contact 192281850 -> none (not a displayed number)', 0, $search('192281850', $CLAUSE));

// A member's own mobile resolves to exactly that one member.
assert_eq('member mobile 0123456789 -> 1', 1, $search('0123456789', $CLAUSE));

// A member's own number matches that member (partial substring).
assert_eq('member partial 93874576 -> 1', 1, $search('93874576', $CLAUSE));

// A number belonging to nobody matches nothing.
assert_eq('unknown 555000 -> none', 0, $search('555000', $CLAUSE));

echo "\nAll assertions passed.\n";
