<?php
/**
 * Run with: php tests/helpers/IsSaActingAsTc2Test.php
 *
 * Verifies is_sa_acting_as_tc2(): true when a level-20 Sales Agent matches
 * the booking's SalesAgent2 slot. Drives the booking-listing gating that
 * hides BC link, GL actions, and Customer actions for rows where the SA
 * is only the secondary consultant.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require_once __DIR__ . '/../../application/helpers/booking_flow_helper.php';

$assertions = [];

// Core case: SA whose admin_id equals SalesAgent2 -> true
$assertions['SA + matching SalesAgent2 -> true'] =
    is_sa_acting_as_tc2(20, 7, 7) === true;

// SA with non-matching SalesAgent2 -> false (different user is the TC2)
$assertions['SA + non-matching SalesAgent2 -> false'] =
    is_sa_acting_as_tc2(20, 7, 99) === false;

// SA on a booking with no SalesAgent2 assigned -> false
$assertions['SA + null SalesAgent2 -> false'] =
    is_sa_acting_as_tc2(20, 7, null) === false;
$assertions['SA + empty-string SalesAgent2 -> false'] =
    is_sa_acting_as_tc2(20, 7, '') === false;
$assertions['SA + zero SalesAgent2 -> false'] =
    is_sa_acting_as_tc2(20, 7, 0) === false;

// Non-SA levels must NOT trigger the TC2 gating even if they happen to
// match SalesAgent2 (admins/managers/TC keep full row access)
$assertions['OP (level=40) + matching SalesAgent2 -> false'] =
    is_sa_acting_as_tc2(40, 7, 7) === false;
$assertions['TC (level=50) + matching SalesAgent2 -> false'] =
    is_sa_acting_as_tc2(50, 7, 7) === false;
$assertions['Manager (level=60) + matching SalesAgent2 -> false'] =
    is_sa_acting_as_tc2(60, 7, 7) === false;
$assertions['Super admin (level=99) + matching SalesAgent2 -> false'] =
    is_sa_acting_as_tc2(99, 7, 7) === false;

// Null/unauthenticated level -> false (no session, not the gate's concern)
$assertions['Null level + matching SalesAgent2 -> false'] =
    is_sa_acting_as_tc2(null, 7, 7) === false;

// CodeIgniter session sometimes returns numeric strings — verify casts work
$assertions['SA level="20" string + matching SalesAgent2 -> true'] =
    is_sa_acting_as_tc2('20', 7, 7) === true;
$assertions['SA admin_id="7" string + SalesAgent2="7" string -> true'] =
    is_sa_acting_as_tc2(20, '7', '7') === true;
$assertions['SA admin_id="7" string + SalesAgent2 7 int -> true'] =
    is_sa_acting_as_tc2(20, '7', 7) === true;

$failed = 0;
foreach ($assertions as $label => $ok) {
    echo ($ok ? '  PASS  ' : '  FAIL  ') . $label . PHP_EOL;
    if (!$ok) {
        $failed++;
    }
}

echo PHP_EOL . ($failed === 0 ? "All assertions passed.\n" : "$failed assertion(s) failed.\n");
exit($failed === 0 ? 0 : 1);
