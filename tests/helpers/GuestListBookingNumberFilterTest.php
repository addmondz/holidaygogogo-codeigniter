<?php
/**
 * Run with: php tests/helpers/GuestListBookingNumberFilterTest.php
 *
 * Locks the Guest List "Booking Number" filter behaviour. A booking number is
 * a booking-only attribute — GHL leads carry no booking number — so filtering
 * by it must drop the GHL branch entirely (like sales_agent, source, etc.).
 * The booking branch matches BookingNumber with a partial (LIKE) search so a
 * user can paste a full number or just the trailing digits.
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

// ---- booking_number is a booking-only filter that suppresses GHL leads -----
assert_eq('no filters -> keep GHL',          false, guest_list_ghl_suppressed_by_filters(array()));
assert_eq('empty booking_number keeps GHL',  false, guest_list_ghl_suppressed_by_filters(array('booking_number' => '   ')));
assert_eq('booking_number drops GHL',        true,  guest_list_ghl_suppressed_by_filters(array('booking_number' => '2606-001-0001')));

// ---- integration: LIKE match against BookingNumber -------------------------
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE booking (BookingID INTEGER PRIMARY KEY, BookingNumber TEXT)");
$pdo->exec("INSERT INTO booking VALUES
    (1, '2606-001-0001'),
    (2, '2606-001-0002'),
    (3, '2505-007-0099')");

// Mirror the model's booking_number clause: AND b.BookingNumber LIKE ?
$like_match = function ($needle) use ($pdo) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM booking b WHERE b.BookingNumber LIKE :n");
    $stmt->execute(array(':n' => '%' . $needle . '%'));
    return (int) $stmt->fetchColumn();
};

assert_eq('full number matches one',   1, $like_match('2606-001-0001'));
assert_eq('trailing digits match one', 1, $like_match('0099'));
assert_eq('shared prefix matches two', 2, $like_match('2606-001'));
assert_eq('no match -> zero',          0, $like_match('9999'));

echo "\nAll assertions passed.\n";
