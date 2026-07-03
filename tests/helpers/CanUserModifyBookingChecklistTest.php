<?php
/**
 * Run with: php tests/helpers/CanUserModifyBookingChecklistTest.php
 *
 * Verifies the identity-/team-based whitelist that gates booking-checklist
 * ticking. The user's declared admin.Level is ignored — a user can tick iff
 * their AdminID is one of the booking's assignees:
 *   - booking.SalesAgent  (TC),  OR
 *   - booking.SalesAgent2 (TC2), OR
 *   - booking.BookingOP   (OP),  OR
 * they appear in the $team_lead_ids set (active TEAM LEAD 25 / OP TEAM LEAD 45
 * sharing a Team with any assignee — pre-resolved by the caller via
 * resolve_booking_checklist_team_lead_ids()).
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require_once __DIR__ . '/../../application/helpers/booking_flow_helper.php';

$booking_obj = (object) ['SalesAgent' => 7, 'SalesAgent2' => 8, 'BookingOP' => 9];
$booking_arr = ['SalesAgent' => 7, 'SalesAgent2' => 8, 'BookingOP' => 9];

// Team leads (25/45) sharing a team with an assignee, as resolved by the caller.
$leads = [11, 13];

$assertions = [];

// Assignee identity — TC / TC2 / OP — level-agnostic.
$assertions['TC (SalesAgent), level 20 -> allowed'] =
    can_user_modify_booking_checklist($booking_obj, 7, 20, $leads) === true;
$assertions['TC (array), level 20 -> allowed'] =
    can_user_modify_booking_checklist($booking_arr, 7, 20, $leads) === true;
$assertions['TC but Owner-level (10) -> allowed'] =
    can_user_modify_booking_checklist($booking_obj, 7, 10, $leads) === true;
$assertions['TC2 (SalesAgent2), level 20 -> allowed'] =
    can_user_modify_booking_checklist($booking_obj, 8, 20, $leads) === true;
$assertions['TC2 (array) -> allowed'] =
    can_user_modify_booking_checklist($booking_arr, 8, 50, $leads) === true;
$assertions['OP (BookingOP), level 40 -> allowed'] =
    can_user_modify_booking_checklist($booking_obj, 9, 40, $leads) === true;
$assertions['OP, level 10 -> allowed'] =
    can_user_modify_booking_checklist($booking_obj, 9, 10, $leads) === true;
$assertions['TC + null level -> allowed (level not consulted)'] =
    can_user_modify_booking_checklist($booking_obj, 7, null, $leads) === true;

// Team-lead set — level-agnostic identity match against the resolved ids.
$assertions['in team-lead set (11) -> allowed'] =
    can_user_modify_booking_checklist($booking_obj, 11, 25, $leads) === true;
$assertions['in team-lead set (13) -> allowed'] =
    can_user_modify_booking_checklist($booking_obj, 13, 45, $leads) === true;
$assertions['in team-lead set, level 10 -> allowed (identity only)'] =
    can_user_modify_booking_checklist($booking_obj, 11, 10, $leads) === true;
$assertions['NOT an assignee, NOT in team-lead set -> blocked'] =
    can_user_modify_booking_checklist($booking_obj, 99, 25, $leads) === false;

// Empty team-lead set: only assignees can tick.
$assertions['empty leads, TC -> allowed'] =
    can_user_modify_booking_checklist($booking_obj, 7, 20, []) === true;
$assertions['empty leads, would-be lead -> blocked'] =
    can_user_modify_booking_checklist($booking_obj, 11, 25, []) === false;
$assertions['leads arg omitted, TC -> allowed'] =
    can_user_modify_booking_checklist($booking_obj, 7, 20) === true;
$assertions['leads arg omitted, non-assignee -> blocked'] =
    can_user_modify_booking_checklist($booking_obj, 11, 25) === false;

// Non-matching identity, any level -> blocked.
$assertions['Owner-level + no match -> blocked'] =
    can_user_modify_booking_checklist($booking_obj, 99, 10, $leads) === false;
$assertions['Finance-level + no match -> blocked'] =
    can_user_modify_booking_checklist($booking_obj, 99, 30, $leads) === false;
$assertions['Marketing-level (60) + no match -> blocked'] =
    can_user_modify_booking_checklist($booking_obj, 99, 60, $leads) === false;

// CodeIgniter session sometimes returns numeric strings.
$assertions['string user "7" matches TC -> allowed'] =
    can_user_modify_booking_checklist($booking_obj, '7', '20', $leads) === true;
$assertions['string user "8" matches TC2 -> allowed'] =
    can_user_modify_booking_checklist($booking_obj, '8', '20', $leads) === true;
$assertions['string user "11" matches string lead in set -> allowed'] =
    can_user_modify_booking_checklist($booking_obj, '11', '25', ['11', '13']) === true;
$assertions['string user "99" no match -> blocked'] =
    can_user_modify_booking_checklist($booking_obj, '99', '40', $leads) === false;

// Defensive: empty / missing booking.
$assertions['Null booking -> blocked'] =
    can_user_modify_booking_checklist(null, 7, 20, $leads) === false;
$assertions['Empty array booking -> blocked'] =
    can_user_modify_booking_checklist([], 7, 20, $leads) === false;

// Unassigned booking — all three slots NULL/0.
$unassigned = ['SalesAgent' => null, 'SalesAgent2' => null, 'BookingOP' => null];
$assertions['unassigned booking + any user -> blocked'] =
    can_user_modify_booking_checklist($unassigned, 7, 20, []) === false;

// Defensive: zero / non-positive user_id must NOT collide with zero fields.
$assertions['user_id 0 + zeroed slots -> blocked'] =
    can_user_modify_booking_checklist(['SalesAgent' => 0, 'SalesAgent2' => 0, 'BookingOP' => 0], 0, 20, [0]) === false;
$assertions['user_id -1 -> blocked'] =
    can_user_modify_booking_checklist($booking_obj, -1, 20, $leads) === false;

$failed = 0;
foreach ($assertions as $label => $ok) {
    echo ($ok ? '  PASS  ' : '  FAIL  ') . $label . PHP_EOL;
    if (!$ok) {
        $failed++;
    }
}

echo PHP_EOL . ($failed === 0 ? "All assertions passed.\n" : "$failed assertion(s) failed.\n");
exit($failed === 0 ? 0 : 1);
