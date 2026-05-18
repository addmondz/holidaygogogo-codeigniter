<?php
/**
 * Run with: php tests/helpers/UpcomingTravelNotReadyCountTest.php
 *
 * Locks the predicate used by the TC dashboard "Travel in 7 Days – Not Yet
 * Ready" card: count a BC when StartDate falls within the next-7-day window,
 * CancelStatus is 'N', and Status is one of the upstream pending values
 * ('P','PBO','PGL','PTV'). PT itself is excluded — that's the readiness state
 * the card is alerting people *to reach*, not *to chase*.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require_once __DIR__ . '/../../application/helpers/booking_flow_helper.php';

$today = '2026-05-18';
$week  = '2026-05-25';

$assertions = [];

// Each upstream pending status inside the window counts
$assertions['P inside window: true'] =
    is_upcoming_travel_not_ready('P', 'N', '2026-05-22', $today, $week) === true;
$assertions['PBO inside window: true'] =
    is_upcoming_travel_not_ready('PBO', 'N', '2026-05-22', $today, $week) === true;
$assertions['PGL inside window: true'] =
    is_upcoming_travel_not_ready('PGL', 'N', '2026-05-22', $today, $week) === true;
$assertions['PTV inside window: true'] =
    is_upcoming_travel_not_ready('PTV', 'N', '2026-05-22', $today, $week) === true;

// The flip: PT in window is NOT counted (this is the change)
$assertions['PT inside window: false (the flip)'] =
    is_upcoming_travel_not_ready('PT', 'N', '2026-05-22', $today, $week) === false;

// Other statuses excluded per the scoping decision
$assertions['PBC inside window: false'] =
    is_upcoming_travel_not_ready('PBC', 'N', '2026-05-22', $today, $week) === false;
$assertions['OG inside window: false'] =
    is_upcoming_travel_not_ready('OG', 'N', '2026-05-22', $today, $week) === false;
$assertions['Y inside window: false'] =
    is_upcoming_travel_not_ready('Y', 'N', '2026-05-22', $today, $week) === false;
$assertions['PP inside window: false'] =
    is_upcoming_travel_not_ready('PP', 'N', '2026-05-22', $today, $week) === false;

// Cancelled rows never count, even if status matches
$assertions['P cancelled inside window: false'] =
    is_upcoming_travel_not_ready('P', 'Y', '2026-05-22', $today, $week) === false;
$assertions['PBO cancelled inside window: false'] =
    is_upcoming_travel_not_ready('PBO', 'Y', '2026-05-22', $today, $week) === false;

// Out-of-window rows never count
$assertions['P day before window: false'] =
    is_upcoming_travel_not_ready('P', 'N', '2026-05-17', $today, $week) === false;
$assertions['P day after window: false'] =
    is_upcoming_travel_not_ready('P', 'N', '2026-05-26', $today, $week) === false;
$assertions['P way in the future: false'] =
    is_upcoming_travel_not_ready('P', 'N', '2026-06-30', $today, $week) === false;

// Window boundaries are inclusive (matches SQL BETWEEN semantics in controller)
$assertions['PTV on window_start: true (boundary inclusive)'] =
    is_upcoming_travel_not_ready('PTV', 'N', $today, $today, $week) === true;
$assertions['PTV on window_end: true (boundary inclusive)'] =
    is_upcoming_travel_not_ready('PTV', 'N', $week, $today, $week) === true;

$failed = 0;
foreach ($assertions as $label => $ok) {
    echo ($ok ? '  PASS  ' : '  FAIL  ') . $label . PHP_EOL;
    if (!$ok) {
        $failed++;
    }
}

echo PHP_EOL . ($failed === 0 ? "All assertions passed.\n" : "$failed assertion(s) failed.\n");
exit($failed === 0 ? 0 : 1);
