<?php
/**
 * Run with: php tests/helpers/GuestListDobFilterTest.php
 *
 * Locks the Guest List "DOB" (Date of Birth) filter — a born-between calendar
 * range on gl.DateOfBirth, entered via the same daterangepicker the Booking /
 * Travel Date filters use and parsed by guest_list_parse_date_range().
 *
 * DOB is booking-only: a GHL lead carries no date of birth, so any dob filter
 * drops the GHL branch entirely (like sales_agent / gender / destination do).
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

// ---- DOB is booking-only: it suppresses the GHL branch ---------------------
assert_eq('empty dob keeps GHL',     false, guest_list_ghl_suppressed_by_filters(array('dob' => '   ')));
assert_eq('no dob keeps GHL',        false, guest_list_ghl_suppressed_by_filters(array()));
assert_eq('dob range drops GHL',     true,  guest_list_ghl_suppressed_by_filters(array('dob' => '01/01/1990 - 31/12/1999')));
// DOB never suppresses the booking branch (that is only Guest Role = Lead).
assert_eq('dob keeps bookings',      false, guest_list_bookings_suppressed_by_filters(array('dob' => '01/01/1990 - 31/12/1999')));

// ---- integration: born-between range over gl.DateOfBirth -------------------
// Mirrors the model's booking-branch clause:
//   AND gl.DateOfBirth >= ? AND gl.DateOfBirth <= ?
// on a DATE column (inclusive both ends). NULL / 0000-00-00 birthdays never match.
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE guest_list (GuestListID INTEGER PRIMARY KEY, DateOfBirth TEXT)");
$pdo->exec("INSERT INTO guest_list (GuestListID, DateOfBirth) VALUES
    (1, '1990-01-01'),  -- start edge (inside)
    (2, '1995-06-15'),  -- inside
    (3, '1999-12-31'),  -- end edge (inside)
    (4, '1989-12-31'),  -- just before range
    (5, '2000-01-01'),  -- just after range
    (6, NULL),          -- no DOB
    (7, '0000-00-00')   -- placeholder DOB
");

$dob_range = guest_list_parse_date_range('01/01/1990 - 31/12/1999');
assert_eq('range parsed', array('1990-01-01', '1999-12-31'), $dob_range);

$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM guest_list gl
     WHERE gl.DateOfBirth >= :s AND gl.DateOfBirth <= :e");
$stmt->execute(array(':s' => $dob_range[0], ':e' => $dob_range[1]));
assert_eq('only the 1990s cohort survives (3)', 3, (int) $stmt->fetchColumn());

// A single-day range matches an exact birthday.
$one = guest_list_parse_date_range('15/06/1995 - 15/06/1995');
$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM guest_list gl
     WHERE gl.DateOfBirth >= :s AND gl.DateOfBirth <= :e");
$stmt->execute(array(':s' => $one[0], ':e' => $one[1]));
assert_eq('exact birthday matches 1', 1, (int) $stmt->fetchColumn());

echo "\nAll assertions passed.\n";
