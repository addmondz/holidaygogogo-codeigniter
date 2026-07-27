<?php
/**
 * Run with: php tests/helpers/GuestListMultiSelectFilterTest.php
 *
 * Pins the Guest List filter changes:
 *   1. Dropdown filters are MULTI-SELECT — a value may arrive as a single
 *      string or an array (`name[]=a&name[]=b`). guest_list_multi_values()
 *      normalizes both into a clean list the model turns into an IN (...) clause.
 *   2. The suppression predicates understand multi-select Guest Role (and any
 *      booking-only filter given as an array).
 *   3. "Search Name" (q) now matches the booking's TEAM LEADER (b.Customer)
 *      as well as the guest's own name, so searching a leader surfaces their
 *      whole team.
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

// ---- 1. guest_list_multi_values() normalization ---------------------------
assert_eq('null -> []',            array(),               guest_list_multi_values(null));
assert_eq('empty string -> []',    array(),               guest_list_multi_values('  '));
assert_eq('single string kept',    array('7'),            guest_list_multi_values('7'));
assert_eq('array trimmed + kept',  array('3', '9'),       guest_list_multi_values(array(' 3 ', '9')));
assert_eq('blanks dropped',        array('Male'),         guest_list_multi_values(array('Male', '', '   ')));
assert_eq('dupes collapsed',       array('Team Leader'),  guest_list_multi_values(array('Team Leader', 'Team Leader')));

// ---- 2a. GHL suppression with multi-select role ---------------------------
// Any booking-only filter (even a one-element array) drops the GHL branch.
assert_eq('array sales_agent drops GHL', true,  guest_list_ghl_suppressed_by_filters(array('sales_agent' => array('5'))));
assert_eq('empty array keeps GHL',       false, guest_list_ghl_suppressed_by_filters(array('sales_agent' => array())));
// Role including "Lead" never suppresses GHL (leads ARE the Lead role)...
assert_eq('role Lead keeps GHL',         false, guest_list_ghl_suppressed_by_filters(array('role' => array('Lead'))));
assert_eq('role Lead+TL keeps GHL',      false, guest_list_ghl_suppressed_by_filters(array('role' => array('Lead', 'Team Leader'))));
// ...but a role filter WITHOUT "Lead" can never match a lead -> suppress GHL.
assert_eq('role TL only drops GHL',      true,  guest_list_ghl_suppressed_by_filters(array('role' => array('Team Leader'))));
assert_eq('role TL+TM drops GHL',        true,  guest_list_ghl_suppressed_by_filters(array('role' => array('Team Leader', 'Team Member'))));

// ---- 2b. Booking suppression with multi-select role -----------------------
// Booking guests are never "Lead", so ONLY a role filter that is exclusively
// Lead drops the booking branch. Add any booking role and bookings come back.
assert_eq('role Lead only drops bookings', true,  guest_list_bookings_suppressed_by_filters(array('role' => array('Lead'))));
assert_eq('role Lead+TL keeps bookings',   false, guest_list_bookings_suppressed_by_filters(array('role' => array('Lead', 'Team Leader'))));
assert_eq('role TM keeps bookings',        false, guest_list_bookings_suppressed_by_filters(array('role' => array('Team Member'))));
assert_eq('no role keeps bookings',        false, guest_list_bookings_suppressed_by_filters(array()));

// ---- 3. integration: q matches guest name OR team leader (b.Customer) ------
// Mirrors the model's booking-branch q clause (CONCAT_WS rewritten with || for
// SQLite): gl.Name / gl.LastName / full name / b.Customer all LIKE %q%.
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE booking (BookingID INTEGER PRIMARY KEY, Customer TEXT)");
$pdo->exec("CREATE TABLE guest_list (GuestListID INTEGER PRIMARY KEY, BookingID INTEGER, Name TEXT, LastName TEXT)");
// Booking 1 leader = "John Tan"; booking 2 leader = "Mary Lim".
$pdo->exec("INSERT INTO booking (BookingID, Customer) VALUES
    (1, 'John Tan'),
    (2, 'Mary Lim')");
// Guest 1 = the leader John; guest 2 = Alice (member, name has no 'John');
// guest 3 = Bob on an unrelated booking.
$pdo->exec("INSERT INTO guest_list (GuestListID, BookingID, Name, LastName) VALUES
    (1, 1, 'John',  'Tan'),
    (2, 1, 'Alice', 'Wong'),
    (3, 2, 'Bob',   'Lee')");

$q_sql =
    "SELECT gl.GuestListID
     FROM booking b
     JOIN guest_list gl ON gl.BookingID = b.BookingID
     WHERE ( gl.Name LIKE :q OR gl.LastName LIKE :q
             OR (gl.Name || ' ' || gl.LastName) LIKE :q
             OR b.Customer LIKE :q )
     ORDER BY gl.GuestListID";

$stmt = $pdo->prepare($q_sql);
$stmt->execute(array(':q' => '%John%'));
$ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
// John himself (1) AND Alice (2) — Alice matches only via her leader b.Customer.
assert_eq('q "John" returns leader + team member', array(1, 2), $ids);

$stmt->execute(array(':q' => '%Bob%'));
$ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
assert_eq('q "Bob" still matches own name', array(3), $ids);

// ---- 4. integration: multi-select IN (...) filter over a dropdown column ---
$pdo->exec("CREATE TABLE g (id INTEGER PRIMARY KEY, Gender TEXT)");
$pdo->exec("INSERT INTO g (id, Gender) VALUES (1,'Male'),(2,'Female'),(3,'Other'),(4,'Male')");

$genders      = guest_list_multi_values(array('Male', 'Other'));
$placeholders = implode(',', array_fill(0, count($genders), '?'));
$stmt = $pdo->prepare("SELECT id FROM g WHERE Gender IN ({$placeholders}) ORDER BY id");
$stmt->execute($genders);
$ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
assert_eq('gender IN (Male,Other) matches 3 rows', array(1, 3, 4), $ids);

echo "\nAll assertions passed.\n";
