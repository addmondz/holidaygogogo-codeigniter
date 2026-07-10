<?php
/**
 * Run with: php tests/helpers/GuestListLeaderFallbackTest.php
 *
 * Pins the "leader fallback" branch of the Guest List.
 *
 * Problem: a booking's guest_list rows start blank (one per pax) and only get a
 * name/phone/email when someone fills the guest list form. Until then the whole
 * booking — leader included — vanishes from the Guest List (the listing needs a
 * non-null gl.dedup_key), so its leader can't be picked for a WhatsApp campaign
 * even though the booking already carries the leader's phone (booking.Mobile /
 * customer.phone_number).
 *
 * Fix: synthesize a Team Leader row straight from the booking contact for every
 * active booking whose leader phone is NOT already a filled guest_list row. This
 * test pins two things:
 *   1. guest_list_leader_fallback_suppressed_by_filters() — the fallback can only
 *      show LEADERS with a booking-level identity, so any guest-only filter
 *      (nationality / gender / dob / email / pax) or a role filter that excludes
 *      "Team Leader" drops the branch.
 *   2. The anti-join core (portable SQL): a leader is synthesized only when its
 *      phone key is absent from the filled guest_list set, one row per leader key,
 *      never for a phone-less booking — so no duplicate vs the normal branch.
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

// ---- 1. suppression: a synthetic leader has no per-guest attributes ---------
// No filter → fallback runs.
assert_eq('no filter keeps fallback',        false, guest_list_leader_fallback_suppressed_by_filters(array()));
// Guest-only filters can never match a booking-sourced leader → suppress.
assert_eq('nationality drops fallback',       true, guest_list_leader_fallback_suppressed_by_filters(array('nationality' => array('Malaysia'))));
assert_eq('gender drops fallback',            true, guest_list_leader_fallback_suppressed_by_filters(array('gender' => array('Male'))));
assert_eq('dob drops fallback',               true, guest_list_leader_fallback_suppressed_by_filters(array('dob' => '1990-01-01 - 1990-12-31')));
assert_eq('email drops fallback',             true, guest_list_leader_fallback_suppressed_by_filters(array('email' => 'a@b.com')));
assert_eq('pax_min drops fallback',           true, guest_list_leader_fallback_suppressed_by_filters(array('pax_min' => '2')));
assert_eq('empty guest-only keeps fallback',  false, guest_list_leader_fallback_suppressed_by_filters(array('nationality' => array(), 'gender' => '')));
// Booking-level filters are fine — the leader carries them.
assert_eq('sales_agent keeps fallback',       false, guest_list_leader_fallback_suppressed_by_filters(array('sales_agent' => array('5'))));
assert_eq('booking_date keeps fallback',      false, guest_list_leader_fallback_suppressed_by_filters(array('booking_date' => '2026-05-01 - 2026-05-31')));
// Role: only "Team Leader" (or nothing) keeps the leader-only branch.
assert_eq('role Team Leader keeps fallback',  false, guest_list_leader_fallback_suppressed_by_filters(array('role' => array('Team Leader'))));
assert_eq('role TL+TM keeps fallback',        false, guest_list_leader_fallback_suppressed_by_filters(array('role' => array('Team Leader', 'Team Member'))));
assert_eq('role Team Member drops fallback',  true, guest_list_leader_fallback_suppressed_by_filters(array('role' => array('Team Member'))));
assert_eq('role Lead drops fallback',         true, guest_list_leader_fallback_suppressed_by_filters(array('role' => array('Lead'))));

// ---- 2. anti-join core (portable SQL mirrors the model's fallback WHERE) -----
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE booking (BookingID INTEGER PRIMARY KEY, Customer TEXT, Mobile TEXT, Status TEXT, CancelStatus TEXT)");
$pdo->exec("CREATE TABLE guest_list (GuestListID INTEGER PRIMARY KEY, BookingID INTEGER, Status TEXT, dedup_key TEXT)");

// B1: leader phone 111, guest_list all blank        -> synthesize
// B2: leader phone 222, but a filled guest has 222   -> already visible, skip
// B3: leader phone NULL                              -> can't target, skip
// B4/B5: same leader phone 444, both blank           -> one grouped leader row
$pdo->exec("INSERT INTO booking (BookingID, Customer, Mobile, Status, CancelStatus) VALUES
    (1, 'Alpha One',  '111', 'A', 'N'),
    (2, 'Beta Two',   '222', 'A', 'N'),
    (3, 'Gamma Three', NULL, 'A', 'N'),
    (4, 'Delta Four', '444', 'A', 'N'),
    (5, 'Delta Four', '444', 'A', 'N'),
    (6, 'Void Six',   '666', 'N', 'N')");
$pdo->exec("INSERT INTO guest_list (GuestListID, BookingID, Status, dedup_key) VALUES
    (10, 1, 'Y', NULL),
    (20, 2, 'Y', '222'),
    (30, 3, 'Y', NULL),
    (40, 4, 'Y', NULL),
    (50, 5, 'Y', NULL)");

// Mirror: active booking, leader key present, NOT already a filled guest, grouped.
$sql =
    "SELECT lk AS dedup_key, COUNT(*) AS bookings
     FROM (
        SELECT b.BookingID, b.Mobile AS lk
        FROM booking b
        WHERE b.Status <> 'N' AND b.CancelStatus = 'N'
          AND b.Mobile IS NOT NULL AND b.Mobile <> ''
          AND NOT EXISTS (
              SELECT 1 FROM guest_list gl
              WHERE gl.Status = 'Y' AND gl.dedup_key = b.Mobile
          )
     ) x
     GROUP BY lk
     ORDER BY lk";
$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$keys = array_map(function ($r) { return $r['dedup_key']; }, $rows);
assert_eq('synthesized leader keys', array('111', '444'), $keys);

$by_key = array();
foreach ($rows as $r) { $by_key[$r['dedup_key']] = (int) $r['bookings']; }
assert_eq('B1 leader synthesized once',       1, $by_key['111']);
assert_eq('B4+B5 same leader grouped to one', 2, $by_key['444']); // 2 bookings, 1 row
assert_eq('filled leader 222 not duplicated',  false, in_array('222', $keys, true));
assert_eq('phone-less booking skipped',        false, in_array(null,  $keys, true));

echo "\nAll assertions passed.\n";
