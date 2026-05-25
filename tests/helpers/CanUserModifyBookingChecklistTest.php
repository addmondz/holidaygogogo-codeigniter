<?php
/**
 * Run with: php tests/helpers/CanUserModifyBookingChecklistTest.php
 *
 * Verifies the strict whitelist that gates booking-checklist ticking:
 *   - level 20 (TC1) iff user_id === booking.SalesAgent
 *   - level 40 (OP)  iff user_id === booking.BookingOP
 *   - level 25 (TL)  iff user_id matches the booking's TC1- or OP-TeamLeadID
 *   - all other levels (10/30/50/etc.) are blocked
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require_once __DIR__ . '/../../application/helpers/booking_flow_helper.php';

$booking_obj = (object) ['SalesAgent' => 7, 'BookingOP' => 9];
$booking_arr = ['SalesAgent' => 7, 'BookingOP' => 9];

// admin 7 (TC1) has team lead admin 11
// admin 9 (OP)  has team lead admin 13
$tc1_tl = 11;
$op_tl  = 13;

$assertions = [];

// Level 20 (TC1) — only the booking's SalesAgent may tick
$assertions['Level 20 + matching SalesAgent (object) -> allowed'] =
    can_user_modify_booking_checklist($booking_obj, 7, 20, $tc1_tl, $op_tl) === true;
$assertions['Level 20 + matching SalesAgent (array)  -> allowed'] =
    can_user_modify_booking_checklist($booking_arr, 7, 20, $tc1_tl, $op_tl) === true;
$assertions['Level 20 + non-matching SalesAgent -> blocked'] =
    can_user_modify_booking_checklist($booking_obj, 8, 20, $tc1_tl, $op_tl) === false;
$assertions['Level 20 + matches BookingOP (not SalesAgent) -> blocked'] =
    can_user_modify_booking_checklist($booking_obj, 9, 20, $tc1_tl, $op_tl) === false;

// Level 40 (OP) — only the booking's BookingOP may tick
$assertions['Level 40 + matching BookingOP (object) -> allowed'] =
    can_user_modify_booking_checklist($booking_obj, 9, 40, $tc1_tl, $op_tl) === true;
$assertions['Level 40 + matching BookingOP (array)  -> allowed'] =
    can_user_modify_booking_checklist($booking_arr, 9, 40, $tc1_tl, $op_tl) === true;
$assertions['Level 40 + non-matching BookingOP -> blocked'] =
    can_user_modify_booking_checklist($booking_obj, 8, 40, $tc1_tl, $op_tl) === false;
$assertions['Level 40 + matches SalesAgent (not BookingOP) -> blocked'] =
    can_user_modify_booking_checklist($booking_obj, 7, 40, $tc1_tl, $op_tl) === false;

// Level 25 (Team Lead) — must match the TL of the booking's TC1 or OP
$assertions['Level 25 + matches TC1 team lead -> allowed'] =
    can_user_modify_booking_checklist($booking_obj, 11, 25, $tc1_tl, $op_tl) === true;
$assertions['Level 25 + matches OP team lead -> allowed'] =
    can_user_modify_booking_checklist($booking_obj, 13, 25, $tc1_tl, $op_tl) === true;
$assertions['Level 25 + matches NEITHER team lead -> blocked'] =
    can_user_modify_booking_checklist($booking_obj, 99, 25, $tc1_tl, $op_tl) === false;
$assertions['Level 25 + both team-lead args null -> blocked'] =
    can_user_modify_booking_checklist($booking_obj, 11, 25, null, null) === false;
$assertions['Level 25 + only TC1 TL set, user matches TC1 TL -> allowed'] =
    can_user_modify_booking_checklist($booking_obj, 11, 25, 11, null) === true;
$assertions['Level 25 + only OP TL set, user matches OP TL -> allowed'] =
    can_user_modify_booking_checklist($booking_obj, 13, 25, null, 13) === true;

// Strict rule — Owner / Finance / TC2 are blocked (BEHAVIOR CHANGE)
$assertions['Level 10 (Owner) -> blocked'] =
    can_user_modify_booking_checklist($booking_obj, 99, 10, $tc1_tl, $op_tl) === false;
$assertions['Level 30 (Finance) -> blocked'] =
    can_user_modify_booking_checklist($booking_obj, 99, 30, $tc1_tl, $op_tl) === false;
$assertions['Level 50 (TC2) -> blocked'] =
    can_user_modify_booking_checklist($booking_obj, 99, 50, $tc1_tl, $op_tl) === false;
$assertions['Unknown level 60 -> blocked'] =
    can_user_modify_booking_checklist($booking_obj, 99, 60, $tc1_tl, $op_tl) === false;
$assertions['Null level -> blocked'] =
    can_user_modify_booking_checklist($booking_obj, 99, null, $tc1_tl, $op_tl) === false;

// CodeIgniter session sometimes returns numeric strings
$assertions['Level "20" string + matching SalesAgent -> allowed'] =
    can_user_modify_booking_checklist($booking_obj, '7', '20', $tc1_tl, $op_tl) === true;
$assertions['Level "40" string + non-matching BookingOP -> blocked'] =
    can_user_modify_booking_checklist($booking_obj, '8', '40', $tc1_tl, $op_tl) === false;
$assertions['Level "25" string + matching string TL -> allowed'] =
    can_user_modify_booking_checklist($booking_obj, '11', '25', '11', '13') === true;

// Defensive: empty / missing booking
$assertions['Null booking -> blocked'] =
    can_user_modify_booking_checklist(null, 7, 20, $tc1_tl, $op_tl) === false;
$assertions['Empty array booking + level 20 -> blocked (no SalesAgent to match)'] =
    can_user_modify_booking_checklist([], 7, 20, $tc1_tl, $op_tl) === false;
$assertions['Empty array booking + level 25 -> blocked'] =
    can_user_modify_booking_checklist([], 11, 25, $tc1_tl, $op_tl) === false;

// Unassigned booking — SalesAgent / BookingOP still NULL/0
$unassigned = ['SalesAgent' => null, 'BookingOP' => null];
$assertions['Level 20 + unassigned booking -> blocked'] =
    can_user_modify_booking_checklist($unassigned, 7, 20, null, null) === false;
$assertions['Level 40 + unassigned booking -> blocked'] =
    can_user_modify_booking_checklist($unassigned, 9, 40, null, null) === false;
$assertions['Owner + unassigned booking -> blocked'] =
    can_user_modify_booking_checklist($unassigned, 99, 10, null, null) === false;

// Defensive: zero team-lead IDs must NOT collide with user_id 0
$assertions['Level 25 + user_id 0 + TL ids 0 -> blocked'] =
    can_user_modify_booking_checklist($booking_obj, 0, 25, 0, 0) === false;
$assertions['Level 20 + user_id 0 + SalesAgent 0 -> blocked'] =
    can_user_modify_booking_checklist(['SalesAgent' => 0, 'BookingOP' => 0], 0, 20, null, null) === false;

$failed = 0;
foreach ($assertions as $label => $ok) {
    echo ($ok ? '  PASS  ' : '  FAIL  ') . $label . PHP_EOL;
    if (!$ok) {
        $failed++;
    }
}

echo PHP_EOL . ($failed === 0 ? "All assertions passed.\n" : "$failed assertion(s) failed.\n");
exit($failed === 0 ? 0 : 1);
