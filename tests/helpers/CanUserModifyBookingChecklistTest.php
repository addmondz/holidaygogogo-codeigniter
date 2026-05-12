<?php
/**
 * Run with: php tests/helpers/CanUserModifyBookingChecklistTest.php
 *
 * Verifies the helper that gates booking-checklist ticking to TC1
 * (level 20 = booking.SalesAgent) and OP (level 40 = booking.BookingOP)
 * for that specific booking. Mirrors the scoping used by
 * Notification_Model::_apply_visibility_filter so the set of users who
 * receive the booking-update notification equals the set who can tick
 * the checklist (excluding admins/managers, who can do both).
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require_once __DIR__ . '/../../application/helpers/booking_flow_helper.php';

$booking_obj = (object) ['SalesAgent' => 7, 'BookingOP' => 9];
$booking_arr = ['SalesAgent' => 7, 'BookingOP' => 9];

$assertions = [];

// Level 20 (TC1) — only the booking's SalesAgent may tick
$assertions['Level 20 + matching SalesAgent (object) -> allowed'] =
    can_user_modify_booking_checklist($booking_obj, 7, 20) === true;
$assertions['Level 20 + matching SalesAgent (array)  -> allowed'] =
    can_user_modify_booking_checklist($booking_arr, 7, 20) === true;
$assertions['Level 20 + non-matching SalesAgent -> blocked'] =
    can_user_modify_booking_checklist($booking_obj, 8, 20) === false;
$assertions['Level 20 + matches BookingOP (not SalesAgent) -> blocked'] =
    can_user_modify_booking_checklist($booking_obj, 9, 20) === false;

// Level 40 (OP) — only the booking's BookingOP may tick
$assertions['Level 40 + matching BookingOP (object) -> allowed'] =
    can_user_modify_booking_checklist($booking_obj, 9, 40) === true;
$assertions['Level 40 + matching BookingOP (array)  -> allowed'] =
    can_user_modify_booking_checklist($booking_arr, 9, 40) === true;
$assertions['Level 40 + non-matching BookingOP -> blocked'] =
    can_user_modify_booking_checklist($booking_obj, 8, 40) === false;
$assertions['Level 40 + matches SalesAgent (not BookingOP) -> blocked'] =
    can_user_modify_booking_checklist($booking_obj, 7, 40) === false;

// Other levels bypass — same as notification logic (no scoping for them)
$assertions['Level 10 (admin) -> allowed regardless'] =
    can_user_modify_booking_checklist($booking_obj, 99, 10) === true;
$assertions['Level 50 (TC2) -> allowed regardless'] =
    can_user_modify_booking_checklist($booking_obj, 99, 50) === true;
$assertions['Level 60 (manager) -> allowed regardless'] =
    can_user_modify_booking_checklist($booking_obj, 99, 60) === true;
$assertions['Null level -> allowed (treated as bypass)'] =
    can_user_modify_booking_checklist($booking_obj, 99, null) === true;

// CodeIgniter session sometimes returns numeric strings
$assertions['Level "20" string + matching SalesAgent -> allowed'] =
    can_user_modify_booking_checklist($booking_obj, '7', '20') === true;
$assertions['Level "40" string + non-matching BookingOP -> blocked'] =
    can_user_modify_booking_checklist($booking_obj, '8', '40') === false;

// Defensive: empty / missing booking
$assertions['Null booking -> blocked'] =
    can_user_modify_booking_checklist(null, 7, 20) === false;
$assertions['Empty array booking + level 20 -> blocked (no SalesAgent to match)'] =
    can_user_modify_booking_checklist([], 7, 20) === false;
// Empty booking payload is treated as "nothing to act on" and denied even
// for admins — keeps the helper safe if called with a stale/null lookup.
$assertions['Empty array booking + admin level -> blocked'] =
    can_user_modify_booking_checklist([], 99, 10) === false;

// Unassigned booking — SalesAgent / BookingOP still NULL/0
$unassigned = ['SalesAgent' => null, 'BookingOP' => null];
$assertions['Level 20 + unassigned booking -> blocked'] =
    can_user_modify_booking_checklist($unassigned, 7, 20) === false;
$assertions['Level 40 + unassigned booking -> blocked'] =
    can_user_modify_booking_checklist($unassigned, 9, 40) === false;
$assertions['Admin + unassigned booking -> allowed'] =
    can_user_modify_booking_checklist($unassigned, 99, 10) === true;

$failed = 0;
foreach ($assertions as $label => $ok) {
    echo ($ok ? '  PASS  ' : '  FAIL  ') . $label . PHP_EOL;
    if (!$ok) {
        $failed++;
    }
}

echo PHP_EOL . ($failed === 0 ? "All assertions passed.\n" : "$failed assertion(s) failed.\n");
exit($failed === 0 ? 0 : 1);
