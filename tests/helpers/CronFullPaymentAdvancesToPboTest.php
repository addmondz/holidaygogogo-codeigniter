<?php
/**
 * Run with: php tests/helpers/CronFullPaymentAdvancesToPboTest.php
 *
 * Locks the post-fix decision logic the daily cron (Cron::index() in
 * application/controllers/Cron.php) now defers to when a booking reaches
 * full-payment-received. The cron used to jump from P/PP straight to PTV,
 * skipping PBO (checklist) and PGL (guest-list lock) — which left bookings
 * showing "PENDING TRAVEL VOUCHER" with only a handful of checklist items
 * ticked (regression caught on BC-2601-0030).
 *
 * Post-fix the cron sets the booking to PBO and then calls
 * check_and_advance_status_if_no_checklist_or_all_completed(), which in
 * turn delegates to determine_booking_status_from_state(). This file
 * exercises that helper directly with stubbed predicates so the ladder
 * (cancelled -> PBC -> P -> PP -> PBO -> PGL -> PTV -> PT -> Y) is locked
 * against future drift.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

// Stub the booking-state predicates BEFORE requiring the helper so its
// function_exists() guards skip the real definitions and our doubles drive
// the decision purely from the scenario input.

function is_booking_cancelled($booking) {
    return !empty($booking->CancelStatus) && $booking->CancelStatus === 'Y';
}
function is_bc_approved($booking) {
    return !empty($booking->bc_approved) && $booking->bc_approved == 1;
}
function is_guest_list_locked($booking) {
    return !empty($booking->LockStatus) && $booking->LockStatus === 'Y';
}
function has_booking_payment($booking_id, $CI) {
    return $CI->_scenario['payment_received'];
}
function has_full_payment($booking_id, $booking, $CI) {
    return $CI->_scenario['full_payment'];
}
function are_all_checklists_completed($booking_id, $CI) {
    return $CI->_scenario['checklists_complete'];
}
function is_travel_voucher_sent($booking_id, $CI) {
    return $CI->_scenario['voucher_sent'];
}

require_once __DIR__ . '/../../application/helpers/booking_flow_helper.php';

function make_scenario(array $opts) {
    $defaults = [
        'CancelStatus'        => 'N',
        'bc_approved'         => 1,
        'LockStatus'          => 'N',
        'StartDate'           => '2099-01-01',
        'EndDate'             => '2099-01-05',
        'NetTotal'            => 1000,
        'payment_received'    => false,
        'full_payment'        => false,
        'checklists_complete' => false,
        'voucher_sent'        => false,
    ];
    $opts = array_merge($defaults, $opts);
    $booking = (object) [
        'CancelStatus' => $opts['CancelStatus'],
        'bc_approved'  => $opts['bc_approved'],
        'LockStatus'   => $opts['LockStatus'],
        'StartDate'    => $opts['StartDate'],
        'EndDate'      => $opts['EndDate'],
        'NetTotal'     => $opts['NetTotal'],
    ];
    $CI = new stdClass();
    $CI->_scenario = [
        'payment_received'    => $opts['payment_received'],
        'full_payment'        => $opts['full_payment'],
        'checklists_complete' => $opts['checklists_complete'],
        'voucher_sent'        => $opts['voucher_sent'],
    ];
    return [$booking, $CI];
}

$assertions = [];

// Core regression: full payment + checklists unticked must NOT skip to PTV
list($booking, $CI) = make_scenario([
    'payment_received'    => true,
    'full_payment'        => true,
    'checklists_complete' => false,
]);
$result = determine_booking_status_from_state(1, $booking, $CI);
$assertions['Full payment + checklists incomplete -> PBO (was PTV before fix)'] =
    ($result['status'] === 'PBO');

// Checklists done but guest list not locked -> PGL (not PTV)
list($booking, $CI) = make_scenario([
    'payment_received'    => true,
    'full_payment'        => true,
    'checklists_complete' => true,
    'LockStatus'          => 'N',
]);
$result = determine_booking_status_from_state(1, $booking, $CI);
$assertions['Checklists complete + GL not locked -> PGL'] =
    ($result['status'] === 'PGL');

// All gates satisfied + voucher unsent -> PTV (only valid path to PTV)
list($booking, $CI) = make_scenario([
    'payment_received'    => true,
    'full_payment'        => true,
    'checklists_complete' => true,
    'LockStatus'          => 'Y',
    'voucher_sent'        => false,
]);
$result = determine_booking_status_from_state(1, $booking, $CI);
$assertions['Checklists complete + GL locked + voucher unsent -> PTV'] =
    ($result['status'] === 'PTV');

// Cancelled trumps everything else
list($booking, $CI) = make_scenario([
    'CancelStatus'        => 'Y',
    'payment_received'    => true,
    'full_payment'        => true,
    'checklists_complete' => true,
    'LockStatus'          => 'Y',
]);
$result = determine_booking_status_from_state(1, $booking, $CI);
$assertions['Cancelled -> CANCELLED regardless of downstream state'] =
    ($result['status'] === 'CANCELLED');

// BC not approved -> PBC
list($booking, $CI) = make_scenario([
    'bc_approved'      => 0,
    'payment_received' => true,
    'full_payment'     => true,
]);
$result = determine_booking_status_from_state(1, $booking, $CI);
$assertions['BC not approved -> PBC'] = ($result['status'] === 'PBC');

// No payment -> P
list($booking, $CI) = make_scenario([
    'payment_received' => false,
]);
$result = determine_booking_status_from_state(1, $booking, $CI);
$assertions['No payment -> P'] = ($result['status'] === 'P');

// Partial payment only -> PP
list($booking, $CI) = make_scenario([
    'payment_received' => true,
    'full_payment'     => false,
]);
$result = determine_booking_status_from_state(1, $booking, $CI);
$assertions['Partial payment -> PP'] = ($result['status'] === 'PP');

// All gates plus voucher sent + travel still upcoming -> PT
list($booking, $CI) = make_scenario([
    'payment_received'    => true,
    'full_payment'        => true,
    'checklists_complete' => true,
    'LockStatus'          => 'Y',
    'voucher_sent'        => true,
    'StartDate'           => '2099-01-01',
    'EndDate'             => '2099-01-05',
]);
$result = determine_booking_status_from_state(1, $booking, $CI);
$assertions['Voucher sent + travel future -> PT'] = ($result['status'] === 'PT');

// All gates plus voucher sent + travel ended in past -> Y
list($booking, $CI) = make_scenario([
    'payment_received'    => true,
    'full_payment'        => true,
    'checklists_complete' => true,
    'LockStatus'          => 'Y',
    'voucher_sent'        => true,
    'StartDate'           => '2000-01-01',
    'EndDate'             => '2000-01-05',
]);
$result = determine_booking_status_from_state(1, $booking, $CI);
$assertions['Voucher sent + travel past -> Y'] = ($result['status'] === 'Y');

$failed = 0;
foreach ($assertions as $label => $ok) {
    echo ($ok ? '  PASS  ' : '  FAIL  ') . $label . PHP_EOL;
    if (!$ok) {
        $failed++;
    }
}

echo PHP_EOL . ($failed === 0 ? "All assertions passed.\n" : "$failed assertion(s) failed.\n");
exit($failed === 0 ? 0 : 1);
