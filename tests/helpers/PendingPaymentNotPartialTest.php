<?php
/**
 * Run with: php tests/helpers/PendingPaymentNotPartialTest.php
 *
 * Regression scenario (BC-2606-0057): a *duplicated* booking carries over a
 * FULL payment line equal to NetTotal, but that line is still PENDING
 * (Status='P') — no customer money has actually been received. When BC was
 * approved the booking jumped to PP ("PARTIAL PAYMENT") even though nothing
 * was paid.
 *
 * Root cause: has_booking_payment() treated a pending credit (Status='P') as
 * "payment received", so determine_booking_status_from_state() cleared the
 * step-3 gate and fell through to step 4 (PARTIAL PAYMENT) instead of staying
 * at step 3 (PENDING PAYMENT).
 *
 * Correct behaviour: only an APPROVED credit (Status='Y') counts as money
 * received. A booking whose only payment lines are pending must report
 * P ("PENDING PAYMENT"), never PP.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require_once __DIR__ . '/../../application/helpers/booking_flow_helper.php';

// --- Test doubles ------------------------------------------------------

class FakeBookingModel {
    public $payments;
    public function __construct($payments) { $this->payments = $payments; }
    public function Read_Payments($booking_id) { return $this->payments; }
}

class FakeStatusLogModel {
    public $logs;
    public function __construct($logs) { $this->logs = $logs; }
    public function get_by_booking_id($booking_id, $flag = false) { return $this->logs; }
}

class FakeLoader {
    public function model($name) { /* no-op */ }
    public function helper($name) { /* no-op */ }
}

class FakeCI {
    public $load;
    public $Booking_Model;
    public $Booking_Status_Log_Model;
    public function __construct($payments, $logs = []) {
        $this->load = new FakeLoader();
        $this->Booking_Model = new FakeBookingModel($payments);
        $this->Booking_Status_Log_Model = new FakeStatusLogModel($logs);
    }
}

function pay($type, $credit, $status, $debit = 0) {
    return (object) [
        'Type'   => $type,
        'Credit' => $credit,
        'Debit'  => $debit,
        'Status' => $status,
    ];
}

// --- Assertions --------------------------------------------------------

$assertions = [];

// 1) Duplicate shape: pending FULL credit only -> NOT counted as received.
$CI = new FakeCI([
    pay('FULL', 2541.00, 'P'),
    pay('SUPPLIER PAYMENT (FULL)', 0, 'P', 1921.00),
]);
$assertions['pending-only FULL: has_booking_payment=false'] =
    (has_booking_payment(9540, $CI) === false);

// 2) Approved deposit -> counted as received.
$CI = new FakeCI([pay('DEPOSIT', 500.00, 'Y')]);
$assertions['approved deposit: has_booking_payment=true'] =
    (has_booking_payment(9540, $CI) === true);

// 3) Approved FULL -> counted as received.
$CI = new FakeCI([pay('FULL', 2541.00, 'Y')]);
$assertions['approved full: has_booking_payment=true'] =
    (has_booking_payment(9540, $CI) === true);

// 4) No payments at all -> not received.
$CI = new FakeCI([]);
$assertions['no payments: has_booking_payment=false'] =
    (has_booking_payment(9540, $CI) === false);

// 5) Headline: BC-approved duplicate with pending-only FULL must be P, not PP.
$booking = (object) [
    'BookingID'    => 9540,
    'bc_approved'  => 1,
    'CancelStatus' => 'N',
    'NetTotal'     => 2541.00,
    'Status'       => 'PBC',
];
$CI = new FakeCI([
    pay('FULL', 2541.00, 'P'),
    pay('SUPPLIER PAYMENT (FULL)', 0, 'P', 1921.00),
]);
$result = determine_booking_status_from_state(9540, $booking, $CI);
$assertions['BC-2606-0057: status=P (PENDING PAYMENT)'] = ($result['status'] === 'P');

// 6) A genuine partial payment (approved deposit < NetTotal) is still PP.
$booking->Status = 'P';
$CI = new FakeCI([pay('DEPOSIT', 500.00, 'Y')]);
$result = determine_booking_status_from_state(9540, $booking, $CI);
$assertions['genuine partial deposit: status=PP'] = ($result['status'] === 'PP');

// --- Report ------------------------------------------------------------

$failed = 0;
foreach ($assertions as $label => $ok) {
    echo ($ok ? '  PASS  ' : '  FAIL  ') . $label . PHP_EOL;
    if (!$ok) { $failed++; }
}

echo PHP_EOL . ($failed === 0 ? "All assertions passed.\n" : "$failed assertion(s) failed.\n");
exit($failed === 0 ? 0 : 1);
