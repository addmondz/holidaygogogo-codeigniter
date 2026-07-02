<?php
/**
 * Run with: php tests/helpers/CompletedBookingPaymentBlockedTest.php
 *
 * Verifies the single-source-of-truth predicate that decides whether a role
 * must be blocked from a booking's Payment history because the trip is
 * finished (Status='Y' AND AfterSalesService='COMPLETE').
 *
 * Sales Agent (20), OP (40) and TC (50) lose access once the trip completes;
 * Finance/Owner keep it. Used by Payment::is_completed_booking_blocked()
 * (index, ajax_list, ajax_summary).
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require_once __DIR__ . '/../../application/helpers/booking_flow_helper.php';

$assertions = [];

// Blocked roles on a fully-completed booking -> blocked
$assertions['SA (20) + Status=Y + COMPLETE -> blocked'] =
    is_completed_booking_payment_blocked(20, 'Y', 'COMPLETE') === true;
$assertions['OP (40) + Status=Y + COMPLETE -> blocked'] =
    is_completed_booking_payment_blocked(40, 'Y', 'COMPLETE') === true;
$assertions['TC (50) + Status=Y + COMPLETE -> blocked'] =
    is_completed_booking_payment_blocked(50, 'Y', 'COMPLETE') === true;

// Completed booking still pending post-travel review -> allowed
$assertions['OP (40) + Status=Y + PENDING -> allowed'] =
    is_completed_booking_payment_blocked(40, 'Y', 'PENDING') === false;

// Non-completed statuses -> allowed
foreach (['PBC', 'P', 'PP', 'PGL', 'PBO', 'PTV', 'PT', 'OG'] as $status) {
    $assertions["OP (40) + Status=$status -> allowed"] =
        is_completed_booking_payment_blocked(40, $status, 'PENDING') === false;
}

// OP Team Lead (45) is NOT in scope of this restriction -> allowed
$assertions['OP Lead (45) + completed -> allowed'] =
    is_completed_booking_payment_blocked(45, 'Y', 'COMPLETE') === false;

// Finance / Owner keep access on completed bookings
$assertions['Finance (30) + completed -> allowed'] =
    is_completed_booking_payment_blocked(30, 'Y', 'COMPLETE') === false;
$assertions['Owner (10) + completed -> allowed'] =
    is_completed_booking_payment_blocked(10, 'Y', 'COMPLETE') === false;
$assertions['Marketing (60) + completed -> allowed'] =
    is_completed_booking_payment_blocked(60, 'Y', 'COMPLETE') === false;

// Null / unauthenticated level -> allowed (not this gate's concern)
$assertions['Null level + completed -> allowed'] =
    is_completed_booking_payment_blocked(null, 'Y', 'COMPLETE') === false;

// Defensive: null fields must not block or error
$assertions['OP (40) + null Status -> allowed'] =
    is_completed_booking_payment_blocked(40, null, 'COMPLETE') === false;
$assertions['OP (40) + null AfterSalesService -> allowed'] =
    is_completed_booking_payment_blocked(40, 'Y', null) === false;

// Casing: match exact stored uppercase value, no implicit normalisation
$assertions['OP (40) + completed lowercase -> allowed'] =
    is_completed_booking_payment_blocked(40, 'y', 'complete') === false;

// Numeric-string level (session may store as string) -> still blocked
$assertions['OP ("40" string) + completed -> blocked'] =
    is_completed_booking_payment_blocked('40', 'Y', 'COMPLETE') === true;

$failed = 0;
foreach ($assertions as $name => $ok) {
    if ($ok) {
        echo "PASS: $name\n";
    } else {
        echo "FAIL: $name\n";
        $failed++;
    }
}
echo "\n" . (count($assertions) - $failed) . '/' . count($assertions) . " passed\n";
exit($failed === 0 ? 0 : 1);
