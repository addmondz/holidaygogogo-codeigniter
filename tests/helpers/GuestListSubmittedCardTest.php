<?php
/**
 * Run with: php tests/helpers/GuestListSubmittedCardTest.php
 *
 * Locks the contract that the "Guest List Submitted" summary card count and the
 * drill-down listing (?guest_list_status=submitted) resolve to the SAME set of
 * bookings, so the card never shows 11 while the list shows 9 again.
 *
 * The single source of truth is guest_list_submitted_where() in
 * application/helpers/guest_list_status_filter_helper.php. A submitted guest
 * list counts regardless of after-sales state (a COMPLETE booking can still have
 * a submitted list), so the predicate deliberately carries NO AfterSalesService
 * clause:
 *
 *   BookingConfirmationTitle = 'BOOKING CONFIRMATION'
 *   AND CancelStatus = 'N'
 *   AND Status != 'N'
 *   AND is_submitted = 1
 *   AND LockStatus = 'N'
 *
 * Three pieces must agree:
 *   1) the helper returns exactly those five predicates and no AfterSalesService;
 *   2) the card count query (Booking::ajax_summary_cards) is built from the
 *      helper, and the submitted branch of Booking_Model::apply_guest_list_status_filter()
 *      is built from the helper;
 *   3) apply_status_filter() drops the default AfterSalesService='PENDING' scope
 *      (keeping CancelStatus='N') when a guest_list_status drill-down is active,
 *      so the after-sales gate cannot silently shrink the submitted list.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

$root = __DIR__ . '/../..';

$paths = [
    'helper'     => $root . '/application/helpers/guest_list_status_filter_helper.php',
    'controller' => $root . '/application/controllers/Booking.php',
    'model'      => $root . '/application/models/Booking_Model.php',
];
foreach ($paths as $k => $p) {
    if (!is_file($p)) {
        echo "  FAIL  cannot locate {$k} at {$p}\n";
        exit(1);
    }
}

require_once $paths['helper'];
$controller = file_get_contents($paths['controller']);
$model      = file_get_contents($paths['model']);

$assertions = [];

// --- 1) The shared predicate helper --------------------------------------
$assertions['guest_list_submitted_where() is defined'] =
    function_exists('guest_list_submitted_where');

if (function_exists('guest_list_submitted_where')) {
    $where = guest_list_submitted_where();
    $assertions['helper requires BOOKING CONFIRMATION'] =
        strpos($where, "BookingConfirmationTitle = 'BOOKING CONFIRMATION'") !== false;
    $assertions['helper requires CancelStatus N'] =
        (bool) preg_match("/CancelStatus\s*=\s*'N'/", $where);
    $assertions['helper excludes draft (Status != N)'] =
        (bool) preg_match("/Status\s*!=\s*'N'/", $where);
    $assertions['helper requires is_submitted = 1'] =
        (bool) preg_match("/is_submitted\s*=\s*1/", $where);
    $assertions['helper requires LockStatus N'] =
        (bool) preg_match("/LockStatus\s*=\s*'N'/", $where);
    // The whole point of the fix: NO after-sales gate on a submitted guest list.
    $assertions['helper carries NO AfterSalesService predicate'] =
        stripos($where, 'AfterSalesService') === false;
}

// --- 2) Card count and listing both built from the helper ----------------
$assertions['card count query uses guest_list_submitted_where()'] =
    (bool) preg_match('/gl_submitted/', $controller)
    && strpos($controller, 'guest_list_submitted_where()') !== false;

// The submitted branch of the guest-list filter must delegate to the helper.
if (preg_match("/\\\$status\s*==\s*'submitted'\s*\)\s*\{(.*?)\}/s", $model, $m)) {
    $assertions["submitted branch uses guest_list_submitted_where()"] =
        strpos($m[1], 'guest_list_submitted_where()') !== false;
} else {
    $assertions["submitted branch is present in apply_guest_list_status_filter()"] = false;
}

// --- 3) Default after-sales scope is suppressed under a GL drill-down -----
if (preg_match('/function\s+apply_status_filter\s*\(\s*\)\s*\{(.*?)\n\t\}/s', $model, $fm)) {
    $body = $fm[1];
    $assertions['apply_status_filter() checks guest_list_status'] =
        strpos($body, 'guest_list_status') !== false;
    // When a GL filter is active it keeps cancelled-exclusion but NOT the
    // AfterSalesService gate; assert the cancelled-exclusion is still applied.
    $assertions['apply_status_filter() still excludes cancelled under GL filter'] =
        (bool) preg_match("/CancelStatus['\"]?\s*,\s*'N'/", $body)
        || strpos($body, "CancelStatus = 'N'") !== false;
} else {
    $assertions['apply_status_filter() body is locatable'] = false;
}

// --- Report --------------------------------------------------------------
$failed = 0;
foreach ($assertions as $label => $ok) {
    echo ($ok ? '  PASS  ' : '  FAIL  ') . $label . PHP_EOL;
    if (!$ok) {
        $failed++;
    }
}

echo PHP_EOL . ($failed === 0 ? "All assertions passed.\n" : "{$failed} assertion(s) failed.\n");
exit($failed === 0 ? 0 : 1);
