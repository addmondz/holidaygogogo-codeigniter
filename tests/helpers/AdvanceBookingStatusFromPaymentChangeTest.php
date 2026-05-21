<?php
/**
 * Run with: php tests/helpers/AdvanceBookingStatusFromPaymentChangeTest.php
 *
 * Locks the post-fix behaviour of
 * advance_booking_status_from_payment_change() in
 * application/helpers/booking_flow_helper.php.
 *
 * Regression scenario (BC-2605-0184): a booking with no required
 * checklists and LockStatus='Y' had all customer payments approved via
 * Payment::Bulk_Update() at 09:55:02 but stayed on 'P' (PENDING PAYMENT)
 * until 10:44:55, when a Dashboard visit triggered Recalculate. Root
 * cause: Bulk_Update's old guard only allowed P -> PP/PBO; PTV was
 * rejected, so the booking sat there. The helper under test must
 * advance the booking forward to wherever determine_booking_status_from_state()
 * says it belongs, in one step, while never regressing.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

// Stub determine_booking_status_from_state() BEFORE requiring the helper
// so the function_exists() guard in booking_flow_helper.php skips the
// real definition. Drives the recalculated target purely from scenario
// input so we test the helper's decision logic, not the predicate chain.
function determine_booking_status_from_state($booking_id, $booking, $CI) {
    return [
        'status'      => $CI->_scenario['target_status'],
        'description' => $CI->_scenario['target_description'] ?? 'stub',
    ];
}

require_once __DIR__ . '/../../application/helpers/booking_flow_helper.php';

// --- Test doubles ------------------------------------------------------

class FakeBookingModel {
    public $booking;
    public $update_status_calls = [];
    public $booking_log_calls = [];

    public function __construct($booking) { $this->booking = $booking; }
    public function getBookingById($id) { return $this->booking; }
    public function Update_Status($status, $id) {
        $this->update_status_calls[] = ['status' => $status, 'id' => $id];
        if ($this->booking) { $this->booking->Status = $status; }
    }
    public function Create_Booking_Log2($from, $to, $id) {
        $this->booking_log_calls[] = ['from' => $from, 'to' => $to, 'id' => $id];
    }
}

class FakeLoader {
    public function model($name) { /* no-op */ }
    public function helper($name) { /* no-op */ }
}

class FakeCI {
    public $load;
    public $Booking_Model;
    public $_scenario;
    public function __construct($booking, $scenario) {
        $this->load = new FakeLoader();
        $this->Booking_Model = new FakeBookingModel($booking);
        $this->_scenario = $scenario;
    }
}

// Capture log_booking_status_change() calls for assertions.
$GLOBALS['__log_calls'] = [];
if (!function_exists('log_booking_status_change')) {
    function log_booking_status_change($booking_id, $to_status, $from_status = null, $created_by = 0, $description = null, $show_to_customer = true) {
        $GLOBALS['__log_calls'][] = compact('booking_id', 'to_status', 'from_status', 'created_by', 'description', 'show_to_customer');
        return 1;
    }
}

function make_ci($current_status, $target_status, $cancel = 'N') {
    $booking = (object) [
        'BookingID'    => 9405,
        'Status'       => $current_status,
        'CancelStatus' => $cancel,
        'NetTotal'     => 5540,
    ];
    return new FakeCI($booking, [
        'target_status'      => $target_status,
        'target_description' => 'stub-description',
    ]);
}

function reset_log_calls() {
    $GLOBALS['__log_calls'] = [];
}

// --- Assertions --------------------------------------------------------

$assertions = [];

// 1) BC-2605-0184 shape: P + target PTV (no checklists, GL locked, voucher unsent)
//    -> must advance directly to PTV in ONE step (the bug fix).
reset_log_calls();
$CI = make_ci('P', 'PTV');
$result = advance_booking_status_from_payment_change(9405, 42, $CI);
$assertions['P -> PTV: advanced=true']  = ($result['advanced'] === true);
$assertions['P -> PTV: status=PTV']     = ($result['status'] === 'PTV');
$assertions['P -> PTV: from=P']         = ($result['from'] === 'P');
$assertions['P -> PTV: Update_Status(PTV) called once'] =
    (count($CI->Booking_Model->update_status_calls) === 1
     && $CI->Booking_Model->update_status_calls[0]['status'] === 'PTV');
$assertions['P -> PTV: one booking_status_log row written'] =
    (count($GLOBALS['__log_calls']) === 1
     && $GLOBALS['__log_calls'][0]['to_status'] === 'PTV'
     && $GLOBALS['__log_calls'][0]['from_status'] === 'P');
$assertions['P -> PTV: description uses Full Payment Received wording'] =
    (strpos($GLOBALS['__log_calls'][0]['description'], 'Full Payment Received') === 0);

// 2) P -> PP (partial deposit only). Must advance and use partial wording.
reset_log_calls();
$CI = make_ci('P', 'PP');
$result = advance_booking_status_from_payment_change(9405, 42, $CI);
$assertions['P -> PP: advanced=true'] = ($result['advanced'] === true);
$assertions['P -> PP: description=Partial payment received'] =
    ($GLOBALS['__log_calls'][0]['description'] === 'Partial payment received');

// 3) P -> PGL (no checklists, GL unlocked, full paid).
reset_log_calls();
$CI = make_ci('P', 'PGL');
$result = advance_booking_status_from_payment_change(9405, 42, $CI);
$assertions['P -> PGL: advanced=true'] = ($result['advanced'] === true);
$assertions['P -> PGL: status=PGL']    = ($result['status'] === 'PGL');

// 4) P -> PBO (checklists required, incomplete).
reset_log_calls();
$CI = make_ci('P', 'PBO');
$result = advance_booking_status_from_payment_change(9405, 42, $CI);
$assertions['P -> PBO: advanced=true'] = ($result['advanced'] === true);
$assertions['P -> PBO: status=PBO']    = ($result['status'] === 'PBO');

// 5) PP -> PBO (additional payment promotes a partially-paid booking).
reset_log_calls();
$CI = make_ci('PP', 'PBO');
$result = advance_booking_status_from_payment_change(9405, 42, $CI);
$assertions['PP -> PBO: advanced=true'] = ($result['advanced'] === true);

// 6) No-op: already at target status.
reset_log_calls();
$CI = make_ci('PTV', 'PTV');
$result = advance_booking_status_from_payment_change(9405, 42, $CI);
$assertions['PTV -> PTV: advanced=false'] = ($result['advanced'] === false);
$assertions['PTV -> PTV: no Update_Status'] = (count($CI->Booking_Model->update_status_calls) === 0);
$assertions['PTV -> PTV: no log row']       = (count($GLOBALS['__log_calls']) === 0);

// 7) Never regress: PBO would not be downgraded to PP.
reset_log_calls();
$CI = make_ci('PBO', 'PP');
$result = advance_booking_status_from_payment_change(9405, 42, $CI);
$assertions['PBO -/-> PP: advanced=false']  = ($result['advanced'] === false);
$assertions['PBO -/-> PP: status stays PBO'] = ($result['status'] === 'PBO');
$assertions['PBO -/-> PP: no Update_Status'] = (count($CI->Booking_Model->update_status_calls) === 0);

// 8) Never regress: PT would not be downgraded to PTV.
reset_log_calls();
$CI = make_ci('PT', 'PTV');
$result = advance_booking_status_from_payment_change(9405, 42, $CI);
$assertions['PT -/-> PTV: advanced=false']  = ($result['advanced'] === false);
$assertions['PT -/-> PTV: no Update_Status'] = (count($CI->Booking_Model->update_status_calls) === 0);

// 9) Cancelled booking: short-circuit, never touch Status.
reset_log_calls();
$CI = make_ci('P', 'PTV', 'Y');
$result = advance_booking_status_from_payment_change(9405, 42, $CI);
$assertions['Cancelled: advanced=false'] = ($result['advanced'] === false);
$assertions['Cancelled: no Update_Status'] = (count($CI->Booking_Model->update_status_calls) === 0);

// 10) Missing booking: helper must not crash, returns advanced=false.
reset_log_calls();
$CI = new FakeCI(null, ['target_status' => 'PTV']);
$result = advance_booking_status_from_payment_change(9999, 42, $CI);
$assertions['Missing booking: advanced=false'] = ($result['advanced'] === false);
$assertions['Missing booking: status=null']    = ($result['status'] === null);

// 11) CANCELLED as recalculated target on a P booking: rank lookup misses,
//     helper must not advance, must not write a log row.
reset_log_calls();
$CI = make_ci('P', 'CANCELLED');
$result = advance_booking_status_from_payment_change(9405, 42, $CI);
$assertions['Target CANCELLED: advanced=false']  = ($result['advanced'] === false);
$assertions['Target CANCELLED: no Update_Status'] = (count($CI->Booking_Model->update_status_calls) === 0);

// 12) Forward jump P -> Y (travel already passed, all gates met).
reset_log_calls();
$CI = make_ci('P', 'Y');
$result = advance_booking_status_from_payment_change(9405, 42, $CI);
$assertions['P -> Y: advanced=true']  = ($result['advanced'] === true);
$assertions['P -> Y: status=Y']       = ($result['status'] === 'Y');

// --- Report ------------------------------------------------------------

$failed = 0;
foreach ($assertions as $label => $ok) {
    echo ($ok ? '  PASS  ' : '  FAIL  ') . $label . PHP_EOL;
    if (!$ok) { $failed++; }
}

echo PHP_EOL . ($failed === 0 ? "All assertions passed.\n" : "$failed assertion(s) failed.\n");
exit($failed === 0 ? 0 : 1);
