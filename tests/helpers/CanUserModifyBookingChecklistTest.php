<?php
/**
 * Run with: php tests/helpers/CanUserModifyBookingChecklistTest.php
 *
 * Verifies the identity-based whitelist that gates booking-checklist ticking.
 * The user's declared admin.Level is ignored — only the per-booking assignments
 * matter. A user can tick iff their AdminID equals:
 *   - booking.SalesAgent, OR
 *   - booking.BookingOP, OR
 *   - the TeamLeadID of either of the above (passed in by the caller)
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

// SalesAgent identity — anyone whose AdminID matches the booking's SalesAgent
// can tick, regardless of their declared level
$assertions['matching SalesAgent (object), level 20 -> allowed'] =
    can_user_modify_booking_checklist($booking_obj, 7, 20, $tc1_tl, $op_tl) === true;
$assertions['matching SalesAgent (array), level 20 -> allowed'] =
    can_user_modify_booking_checklist($booking_arr, 7, 20, $tc1_tl, $op_tl) === true;
$assertions['matching SalesAgent but Owner-level (10) -> allowed'] =
    can_user_modify_booking_checklist($booking_obj, 7, 10, $tc1_tl, $op_tl) === true;
$assertions['matching SalesAgent but TC2-level (50) -> allowed'] =
    can_user_modify_booking_checklist($booking_obj, 7, 50, $tc1_tl, $op_tl) === true;
$assertions['matching SalesAgent + null level -> allowed (level not consulted)'] =
    can_user_modify_booking_checklist($booking_obj, 7, null, $tc1_tl, $op_tl) === true;
$assertions['non-matching SalesAgent, level 20 -> blocked'] =
    can_user_modify_booking_checklist($booking_obj, 8, 20, $tc1_tl, $op_tl) === false;

// BookingOP identity
$assertions['matching BookingOP (object), level 40 -> allowed'] =
    can_user_modify_booking_checklist($booking_obj, 9, 40, $tc1_tl, $op_tl) === true;
$assertions['matching BookingOP, level 10 -> allowed'] =
    can_user_modify_booking_checklist($booking_obj, 9, 10, $tc1_tl, $op_tl) === true;
$assertions['non-matching BookingOP, level 40 -> blocked'] =
    can_user_modify_booking_checklist($booking_obj, 8, 40, $tc1_tl, $op_tl) === false;

// Team Lead identity — also level-agnostic
$assertions['matches TC1 team lead -> allowed'] =
    can_user_modify_booking_checklist($booking_obj, 11, 25, $tc1_tl, $op_tl) === true;
$assertions['matches OP team lead -> allowed'] =
    can_user_modify_booking_checklist($booking_obj, 13, 25, $tc1_tl, $op_tl) === true;
$assertions['matches TC1 team lead, level 10 -> allowed (identity only)'] =
    can_user_modify_booking_checklist($booking_obj, 11, 10, $tc1_tl, $op_tl) === true;
$assertions['matches NEITHER team lead -> blocked'] =
    can_user_modify_booking_checklist($booking_obj, 99, 25, $tc1_tl, $op_tl) === false;

// OP TEAM LEAD role (level 45). Its team membership is resolved separately
// (admin.OpTeamLeadID) and passed in as $op_team_lead_id, so identity still
// decides: a level-45 lead over the booking's OP can tick; one who is not
// this booking's OP lead (and matches no other slot) cannot.
$assertions['OP team lead (level 45) over booking OP -> allowed'] =
    can_user_modify_booking_checklist($booking_obj, 13, 45, $tc1_tl, $op_tl) === true;
$assertions['OP team lead (level 45) NOT over this booking OP -> blocked'] =
    can_user_modify_booking_checklist($booking_obj, 99, 45, $tc1_tl, $op_tl) === false;
$assertions['level 45 also blocked when no op_tl resolved -> blocked'] =
    can_user_modify_booking_checklist($booking_obj, 13, 45, $tc1_tl, null) === false;
$assertions['both team-lead args null + non-matching user -> blocked'] =
    can_user_modify_booking_checklist($booking_obj, 11, 25, null, null) === false;
$assertions['only TC1 TL set, user matches TC1 TL -> allowed'] =
    can_user_modify_booking_checklist($booking_obj, 11, 25, 11, null) === true;
$assertions['only OP TL set, user matches OP TL -> allowed'] =
    can_user_modify_booking_checklist($booking_obj, 13, 25, null, 13) === true;

// Non-matching identity, any level -> blocked
$assertions['Owner-level + no identity match -> blocked'] =
    can_user_modify_booking_checklist($booking_obj, 99, 10, $tc1_tl, $op_tl) === false;
$assertions['Finance-level + no identity match -> blocked'] =
    can_user_modify_booking_checklist($booking_obj, 99, 30, $tc1_tl, $op_tl) === false;
$assertions['TC2-level + no identity match -> blocked'] =
    can_user_modify_booking_checklist($booking_obj, 99, 50, $tc1_tl, $op_tl) === false;
$assertions['Unknown level 60 + no identity match -> blocked'] =
    can_user_modify_booking_checklist($booking_obj, 99, 60, $tc1_tl, $op_tl) === false;

// CodeIgniter session sometimes returns numeric strings
$assertions['string user "7" matches SalesAgent -> allowed'] =
    can_user_modify_booking_checklist($booking_obj, '7', '20', $tc1_tl, $op_tl) === true;
$assertions['string user "8" no match -> blocked'] =
    can_user_modify_booking_checklist($booking_obj, '8', '40', $tc1_tl, $op_tl) === false;
$assertions['string user "11" matches string TL "11" -> allowed'] =
    can_user_modify_booking_checklist($booking_obj, '11', '25', '11', '13') === true;

// Defensive: empty / missing booking
$assertions['Null booking -> blocked'] =
    can_user_modify_booking_checklist(null, 7, 20, $tc1_tl, $op_tl) === false;
$assertions['Empty array booking -> blocked'] =
    can_user_modify_booking_checklist([], 7, 20, $tc1_tl, $op_tl) === false;

// Unassigned booking — SalesAgent / BookingOP still NULL/0
$unassigned = ['SalesAgent' => null, 'BookingOP' => null];
$assertions['unassigned booking + any user_id -> blocked'] =
    can_user_modify_booking_checklist($unassigned, 7, 20, null, null) === false;
$assertions['unassigned booking + Owner level -> blocked'] =
    can_user_modify_booking_checklist($unassigned, 99, 10, null, null) === false;

// Defensive: zero / non-positive user_id must NOT collide with zero fields
$assertions['user_id 0 + zeroed SalesAgent -> blocked'] =
    can_user_modify_booking_checklist(['SalesAgent' => 0, 'BookingOP' => 0], 0, 20, 0, 0) === false;
$assertions['user_id -1 -> blocked'] =
    can_user_modify_booking_checklist($booking_obj, -1, 20, $tc1_tl, $op_tl) === false;

$failed = 0;
foreach ($assertions as $label => $ok) {
    echo ($ok ? '  PASS  ' : '  FAIL  ') . $label . PHP_EOL;
    if (!$ok) {
        $failed++;
    }
}

echo PHP_EOL . ($failed === 0 ? "All assertions passed.\n" : "$failed assertion(s) failed.\n");
exit($failed === 0 ? 0 : 1);
