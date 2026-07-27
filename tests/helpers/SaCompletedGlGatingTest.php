<?php
/**
 * Run with: php tests/helpers/SaCompletedGlGatingTest.php
 *
 * Verifies the single-source-of-truth predicate that decides whether
 * a Sales Agent (level=20) must be blocked from the Guest List page
 * and GL downloads because the booking has reached the post-travel
 * completed state (Status='Y' AND AfterSalesService='COMPLETE').
 *
 * Mirrors the existing inline rule at Booking.php:805/1412 and
 * Payment.php:1861 but lifted to a helper so the four call sites
 * (Guest_List::index, Guest_List::Download, Guest_List::Download_ZIP,
 * Booking dropdown HTML, booking/payment views) stay consistent.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require_once __DIR__ . '/../../application/helpers/booking_flow_helper.php';

$assertions = [];

// SA on fully-completed booking -> blocked
$assertions['SA + Status=Y + AfterSalesService=COMPLETE -> blocked'] =
    is_sa_blocked_from_completed_booking(20, 'Y', 'COMPLETE') === true;

// SA on completed booking pending post-travel review -> allowed (still working on AfterSalesService)
$assertions['SA + Status=Y + AfterSalesService=PENDING -> allowed'] =
    is_sa_blocked_from_completed_booking(20, 'Y', 'PENDING') === false;

// SA on non-completed bookings -> allowed (covers each upstream status)
foreach (['PBC', 'P', 'PP', 'PGL', 'PBO', 'PTV', 'PT', 'OG'] as $status) {
    $assertions["SA + Status=$status -> allowed"] =
        is_sa_blocked_from_completed_booking(20, $status, 'PENDING') === false;
}

// Non-SA admins must keep access on completed bookings (TC level 50, manager 60+)
$assertions['TC (level=50) + completed -> allowed'] =
    is_sa_blocked_from_completed_booking(50, 'Y', 'COMPLETE') === false;
$assertions['Manager (level=60) + completed -> allowed'] =
    is_sa_blocked_from_completed_booking(60, 'Y', 'COMPLETE') === false;
$assertions['Super admin (level=99) + completed -> allowed'] =
    is_sa_blocked_from_completed_booking(99, 'Y', 'COMPLETE') === false;

// Customer / unauthenticated session (no level) -> not the SA gate's concern
$assertions['Null level + completed -> allowed (customer flow handled elsewhere)'] =
    is_sa_blocked_from_completed_booking(null, 'Y', 'COMPLETE') === false;

// Defensive: null fields should not blow up and should not block
$assertions['SA + null Status -> allowed'] =
    is_sa_blocked_from_completed_booking(20, null, 'COMPLETE') === false;
$assertions['SA + null AfterSalesService -> allowed'] =
    is_sa_blocked_from_completed_booking(20, 'Y', null) === false;

// Casing: AfterSalesService stored uppercase per Booking_Model — sanity-check we
// match exact value (any drift would be a data-layer change, not the helper's job)
$assertions['SA + completed lowercase -> allowed (no implicit normalisation)'] =
    is_sa_blocked_from_completed_booking(20, 'y', 'complete') === false;

// Level passed as numeric string (CodeIgniter session sometimes returns strings)
$assertions['SA level="20" string + completed -> blocked'] =
    is_sa_blocked_from_completed_booking('20', 'Y', 'COMPLETE') === true;

$failed = 0;
foreach ($assertions as $label => $ok) {
    echo ($ok ? '  PASS  ' : '  FAIL  ') . $label . PHP_EOL;
    if (!$ok) {
        $failed++;
    }
}

echo PHP_EOL . ($failed === 0 ? "All assertions passed.\n" : "$failed assertion(s) failed.\n");
exit($failed === 0 ? 0 : 1);
