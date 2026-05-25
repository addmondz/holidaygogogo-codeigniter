<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Booking Flow Helper
 * 
 * Manages booking status transitions according to the defined flow:
 * PBC -> P -> PBO -> PGL (derived) -> PTV -> PT -> OG (derived) -> Y
 * C (CANCELLED) is separate and handled via CancelStatus field
 */

if (!function_exists('get_booking_flow')) {
    /**
     * Get the booking status flow sequence
     *
     * @return array Ordered array of status codes in flow sequence
     */
    function get_booking_flow()
    {
        return [
            'PBC',  // PENDING BC CONFIRMATION
            'P',    // PENDING PAYMENT
            'PBO',  // PENDING BOOKING OPERATION
            'PTV',  // PENDING TRAVEL VOUCHER (PGL is derived from this when LockStatus = 'N')
            'PT',   // PENDING TRAVEL
            'Y'     // COMPLETED
        ];
    }
}

if (!function_exists('compute_deposit_complete')) {
    /**
     * Whether a booking's deposit step is satisfied.
     *
     * A deposit-flow booking (DepositDeadline set) is complete only when an
     * actual deposit amount is configured (>0) AND that amount has been paid,
     * OR a full payment has been recorded. A non-deposit-flow booking has no
     * deposit step, so it is reported complete (the consumers gate on
     * has_deposit_deadline before rendering anything deposit-specific).
     *
     * deposit_total = 0 with a DepositDeadline means "not configured yet" — it
     * must NOT short-circuit to "received" (BC-2601-0153 regression).
     */
    function compute_deposit_complete($deposit_total, $total_paid, $has_deposit_deadline, $full_paid = false)
    {
        if (!$has_deposit_deadline) {
            return true;
        }
        if ($full_paid) {
            return true;
        }
        return floatval($deposit_total) > 0 && floatval($total_paid) >= floatval($deposit_total);
    }
}

if (!function_exists('get_derived_statuses')) {
    /**
     * Get list of derived statuses (not stored in DB)
     * 
     * @return array Array of derived status codes
     */
    function get_derived_statuses()
    {
        return [
            'PGL',  // PENDING GUEST LIST (derived from PTV + LockStatus = 'N')
            'OG',   // ON-GOING (derived from PT/Y based on travel dates)
            'PO',   // PAYMENT OVERDUE (derived from P/PP based on deadlines)
            'PR'    // PENDING REVIEW (derived from Y + AfterSalesService = 'PENDING')
        ];
    }
}

if (!function_exists('is_valid_status_transition')) {
    /**
     * Check if a status transition is allowed
     * 
     * @param string $from_status Current status
     * @param string $to_status Target status
     * @return bool True if transition is allowed
     */
    function is_valid_status_transition($from_status, $to_status)
    {
        // Cancelled status cannot transition (must use CancelStatus field)
        if ($from_status === 'C' || $to_status === 'C') {
            return false;
        }

        // Lost status cannot transition
        if ($from_status === 'N' || $to_status === 'N') {
            return false;
        }

        // Same status is always allowed
        if ($from_status === $to_status) {
            return true;
        }

        // Derived statuses cannot be stored (they're computed)
        $derived = get_derived_statuses();
        if (in_array($to_status, $derived)) {
            return false; // Cannot transition TO a derived status
        }

        // Get flow sequence
        $flow = get_booking_flow();

        // Check if both statuses are in the flow
        $from_index = array_search($from_status, $flow);
        $to_index = array_search($to_status, $flow);

        if ($from_index === false || $to_index === false) {
            // If status not in flow, allow transition (for backward compatibility)
            // But log a warning
            log_message('debug', "Status transition check: '{$from_status}' or '{$to_status}' not in flow");
            return true;
        }

        // Special case: PBC can transition to any forward status
        // This allows BC approval to restore booking to previous advanced status
        // (e.g., if booking was reverted to PBC but had payment/checklists completed)
        if ($from_status === 'PBC' && $to_index > $from_index) {
            return true; // Allow PBC → P, PBO, PTV, PT, Y
        }

        // Special case: Y (Completed) can revert to P (Pending Payment) for additional payment
        if ($from_status === 'Y' && $to_status === 'P') {
            return true;
        }

        // Special case: PBO can skip to PT (Approve Travel Voucher from PBO)
        if ($from_status === 'PBO' && $to_status === 'PT') {
            return true;
        }

        // Allow forward movement (next step) or backward movement (previous step)
        // For now, we allow both forward and backward, but you can restrict to forward only
        $diff = $to_index - $from_index;

        // Allow: same, next step (+1), or previous step (-1)
        return ($diff >= -1 && $diff <= 1);
    }
}

if (!function_exists('get_next_status_in_flow')) {
    /**
     * Get the next status in the booking flow
     * 
     * @param string $current_status Current status
     * @return string|null Next status or null if at end of flow
     */
    function get_next_status_in_flow($current_status)
    {
        $flow = get_booking_flow();
        $index = array_search($current_status, $flow);

        if ($index === false || !isset($flow[$index + 1])) {
            return null;
        }

        return $flow[$index + 1];
    }
}

if (!function_exists('get_previous_status_in_flow')) {
    /**
     * Get the previous status in the booking flow
     * 
     * @param string $current_status Current status
     * @return string|null Previous status or null if at start of flow
     */
    function get_previous_status_in_flow($current_status)
    {
        $flow = get_booking_flow();
        $index = array_search($current_status, $flow);

        if ($index === false || $index === 0) {
            return null;
        }

        return $flow[$index - 1];
    }
}

if (!function_exists('get_initial_booking_status')) {
    /**
     * Get the initial status for a new booking
     * 
     * @return string Initial status code
     */
    function get_initial_booking_status()
    {
        return 'PBC'; // PENDING BC CONFIRMATION
    }
}

if (!function_exists('validate_booking_status_flow')) {
    /**
     * Validate and enforce booking status flow
     * 
     * @param string $from_status Current status
     * @param string $to_status Target status
     * @param bool $strict If true, only allow forward movement
     * @return array ['valid' => bool, 'message' => string]
     */
    function validate_booking_status_flow($from_status, $to_status, $strict = false)
    {
        // Check if transition is valid
        if (!is_valid_status_transition($from_status, $to_status)) {
            return [
                'valid' => false,
                'message' => "Invalid status transition from '{$from_status}' to '{$to_status}'"
            ];
        }

        // If strict mode, only allow forward movement
        if ($strict) {
            $flow = get_booking_flow();
            $from_index = array_search($from_status, $flow);
            $to_index = array_search($to_status, $flow);

            if ($from_index !== false && $to_index !== false) {
                if ($to_index <= $from_index) {
                    return [
                        'valid' => false,
                        'message' => "Strict mode: Cannot move backward from '{$from_status}' to '{$to_status}'"
                    ];
                }
            }
        }

        return [
            'valid' => true,
            'message' => "Status transition from '{$from_status}' to '{$to_status}' is valid"
        ];
    }
}

if (!function_exists('advance_booking_status_from_payment_change')) {
    /**
     * Forward-only booking-status advance after a payment.Status change.
     *
     * Re-runs determine_booking_status_from_state() and applies the result
     * iff it represents forward motion along the canonical flow:
     *   PBC < P < PP < PBO < PGL < PTV < PT < OG < Y
     * Never regresses; never touches CANCELLED bookings.
     *
     * Used by both Payment::Update() (single approval) and
     * Payment::Bulk_Update() (bulk approval) so the booking lands on its
     * correct terminal status immediately, including skipping PBO when a
     * booking has no required checklists, guest list locked, etc. Before
     * centralising this, Bulk_Update's whitelisted guard rejected anything
     * past PBO and left bookings stuck at PENDING PAYMENT until a Dashboard
     * visit (or cron) ran Recalculate (regression: BC-2605-0184).
     *
     * @param int $booking_id Booking ID
     * @param int $created_by Admin ID making the change (0 for system)
     * @param CI_Controller $CI CodeIgniter instance
     * @return array {advanced: bool, status: string|null, from: string|null}
     */
    function advance_booking_status_from_payment_change($booking_id, $created_by, $CI)
    {
        static $rank = array(
            'PBC' => 1,
            'P'   => 2,
            'PP'  => 3,
            'PBO' => 4,
            'PGL' => 5,
            'PTV' => 6,
            'PT'  => 7,
            'OG'  => 8,
            'Y'   => 9,
        );

        $CI->load->model('Booking_Model');
        $booking = $CI->Booking_Model->getBookingById($booking_id);
        if (!$booking) {
            return array('advanced' => false, 'status' => null, 'from' => null);
        }
        if (isset($booking->CancelStatus) && $booking->CancelStatus == 'Y') {
            return array('advanced' => false, 'status' => $booking->Status, 'from' => $booking->Status);
        }

        $status_info = determine_booking_status_from_state($booking_id, $booking, $CI);
        $new_status = $status_info['status'];
        $current_status = $booking->Status;

        if ($new_status === $current_status
            || !isset($rank[$new_status])
            || !isset($rank[$current_status])
            || $rank[$new_status] <= $rank[$current_status]) {
            return array('advanced' => false, 'status' => $current_status, 'from' => $current_status);
        }

        $status_labels = array(
            'PP'  => 'PARTIAL PAYMENT',
            'PBO' => 'PENDING BOOKING OPERATION',
            'PGL' => 'PENDING GUEST LIST',
            'PTV' => 'PENDING TRAVEL VOUCHER',
            'PT'  => 'PENDING TRAVEL',
            'OG'  => 'ON-GOING',
            'Y'   => 'COMPLETED',
        );
        if ($new_status === 'PP') {
            $description = 'Partial payment received';
        } else {
            $label = isset($status_labels[$new_status]) ? $status_labels[$new_status] : $new_status;
            $description = 'Full Payment Received - Ready for ' . $label;
        }

        $CI->load->helper('booking_status_log');
        $CI->Booking_Model->Update_Status($new_status, $booking_id);
        $CI->Booking_Model->Create_Booking_Log2($current_status, $new_status, $booking_id);
        log_booking_status_change(
            $booking_id,
            $new_status,
            $current_status,
            $created_by,
            $description,
            true
        );

        return array('advanced' => true, 'status' => $new_status, 'from' => $current_status);
    }
}

if (!function_exists('check_and_advance_status_if_no_checklist_or_all_completed')) {
    /**
     * Check if booking has no checklists or all checklists are completed
     * If so, and status is PBO, advance to next status using simplified logic
     * 
     * @param int $booking_id Booking ID
     * @param object $booking Booking object
     * @param int $created_by Admin ID who made the change
     * @param CI_Controller $CI CodeIgniter instance
     * @return bool True if status was advanced
     */
    function check_and_advance_status_if_no_checklist_or_all_completed($booking_id, $booking, $created_by, $CI)
    {
        // Only check when status is PBO
        if ($booking->Status != 'PBO') {
            return false;
        }

        // Use simplified status determination
        $status_info = determine_booking_status_from_state($booking_id, $booking, $CI);

        // If determined status is different from PBO, advance
        if ($status_info['status'] != 'PBO') {
            $CI->load->model('Booking_Model');
            $CI->load->helper('booking_status_log');

            $CI->Booking_Model->Update_Status($status_info['status'], $booking_id);
            $CI->Booking_Model->Create_Booking_Log2($booking->Status, $status_info['status'], $booking_id);

            log_booking_status_change(
                $booking_id,
                $status_info['status'],
                $booking->Status,
                $created_by,
                "All Booking Checklists Completed - Status changed to " . $status_info['status'],
                true
            );
            return true;
        }

        return false;
    }
}

if (!function_exists('get_booking_status_info')) {
    /**
     * Get status color and text mapping
     * 
     * @return array Array with 'colors' and 'texts' keys
     */
    function get_booking_status_info()
    {
        return [
            'colors' => [
                'Y' => '#50C878',      // COMPLETED - Green
                'PR' => '#C3B1E1',      // PENDING REVIEW - Purple
                'P' => '#DC143C',       // PENDING PAYMENT - Crimson Red
                'PP' => '#A7C7E7',      // PARTIAL PAYMENT - Light Blue
                'PTV' => '#F89880',     // PENDING TRAVEL VOUCHER - Light Red
                'PGL' => '#FAC898',     // PENDING GUEST LIST - Peach
                'PT' => '#F8C8DC',      // PENDING TRAVEL - Pink
                'OG' => '#CCCCFF',      // ON-GOING - Lavender
                'PO' => '#DA70D6',      // PAYMENT OVERDUE - Orchid
                'PBC' => '#FFD700',     // PENDING BC CONFIRMATION - Gold
                'PBO' => '#87CEEB',     // PENDING BOOKING OPERATION - Sky Blue
                'PCI' => '#B8B8FF',     // PENDING CUSTOMER INFO - Soft Indigo
                'CANCELLED' => '#FF69B4' // CANCELLED - Hot Pink
            ],
            'texts' => [
                'Y' => 'COMPLETED',
                'PR' => 'PENDING REVIEW',
                'P' => 'PENDING PAYMENT',
                'PP' => 'PARTIAL PAYMENT',
                'PTV' => 'PENDING TRAVEL VOUCHER',
                'PGL' => 'PENDING GUEST LIST',
                'PT' => 'PENDING TRAVEL',
                'OG' => 'ON-GOING',
                'PO' => 'PAYMENT OVERDUE',
                'PBC' => 'PENDING BC CONFIRMATION',
                'PBO' => 'PENDING BOOKING OPERATION',
                'PCI' => 'PENDING CUSTOMER INFO',
                'CANCELLED' => 'CANCELLED'
            ]
        ];
    }
}

if (!function_exists('display_booking_status')) {
    function display_booking_status($booking, $return_all_statuses = false)
    {
        // Convert array to object if needed
        if (is_array($booking)) {
            $booking = (object)$booking;
        }

        // Determine display status
        $display_status = $booking->Status;
        
        // If guest list is locked and status is PGL, it should be PTV
        if ($booking->LockStatus == 'Y' && $booking->Status == 'PGL') {
            $display_status = 'PTV';
        }
        // If guest list is unlocked and status is PTV, show as PGL
        if ($booking->LockStatus == 'N' && $booking->Status == 'PTV') {
            $display_status = 'PGL';
        }
        // If after sales service is pending and status is Y, show as PR
        if ($booking->AfterSalesService == 'PENDING' && $booking->Status == 'Y') {
            $display_status = 'PR';
        }
        if (($booking->Status == 'P' || $booking->Status == 'PP')
            && isset($booking->balance_due) && isset($booking->deposit_complete)) {
            $today = date('Y-m-d');
            // Prefer raw Y-m-d copies when callers have pre-formatted the display fields
            // (e.g. Booking::Update reformats DepositDeadline to d/m/Y for display).
            $deposit_deadline_raw = !empty($booking->DepositDeadlineRaw)
                ? $booking->DepositDeadlineRaw
                : (!empty($booking->DepositDeadline) ? $booking->DepositDeadline : '');
            $full_payment_deadline_raw = !empty($booking->FullPaymentDeadlineRaw)
                ? $booking->FullPaymentDeadlineRaw
                : (!empty($booking->FullPaymentDeadline) ? $booking->FullPaymentDeadline : '');

            $balance_due = floatval($booking->balance_due);
            $deposit_complete = (bool)$booking->deposit_complete;

            $is_payment_overdue = false;

            if (!empty($deposit_deadline_raw) && !$deposit_complete && $booking->Status == 'P') {
                $deposit_deadline = date('Y-m-d', strtotime($deposit_deadline_raw));
                if ($today > $deposit_deadline && $balance_due > 0) {
                    $is_payment_overdue = true;
                }
            }

            if (!$is_payment_overdue && !empty($full_payment_deadline_raw) && $balance_due > 0) {
                $full_payment_deadline = date('Y-m-d', strtotime($full_payment_deadline_raw));
                if ($today > $full_payment_deadline) {
                    $is_payment_overdue = true;
                }
            }

            if ($is_payment_overdue) {
                $display_status = 'PO';
            }
        }

        $status_colors = array(
            'Y' => '#50C878',
            'PR' => '#C3B1E1',
            'P' => '#DC143C',
            'PP' => '#A7C7E7',
            'PTV' => '#F89880',
            'PGL' => '#FAC898',
            'PT' => '#F8C8DC',
            'OG' => '#CCCCFF',
            'PO' => '#DA70D6',
            'PBC' => '#FFD700',
            'PBO' => '#87CEEB',
            'PCI' => '#B8B8FF'
        );
        $status_texts = array(
            'Y' => 'COMPLETED',
            'PR' => 'PENDING REVIEW',
            'P' => 'PENDING PAYMENT',
            'PP' => 'PARTIAL PAYMENT',
            'PTV' => 'PENDING TRAVEL VOUCHER',
            'PGL' => 'PENDING GUEST LIST',
            'PT' => 'PENDING TRAVEL',
            'OG' => 'ON-GOING',
            'PO' => 'PAYMENT OVERDUE',
            'PBC' => 'PENDING BC CONFIRMATION',
            'PBO' => 'PENDING BOOKING OPERATION',
            'PCI' => 'PENDING CUSTOMER INFO'
        );
        $status_color = $booking->CancelStatus == 'Y' ? '#FF69B4' : (isset($status_colors[$display_status]) ? $status_colors[$display_status] : '#DA70D6');
        $status_text = $booking->CancelStatus == 'Y' ? 'CANCELLED' : (isset($status_texts[$display_status]) ? $status_texts[$display_status] : 'UNKNOWN');

        $result = [
            'status_code' => $display_status,
            'status_text' => $status_text,
            'status_color' => $status_color
        ];

        return $result;
    }
}

if (!function_exists('check_and_revert_status_if_price_or_date_changed')) {
    /**
     * Check if booking price or travel dates changed and revert status to PBC if needed
     * 
     * @param int $booking_id Booking ID
     * @param float|null $new_net_total New net total (null to skip check)
     * @param string|null $new_start_date New start date (null to skip check)
     * @param string|null $new_end_date New end date (null to skip check)
     * @param CI_Controller $CI CodeIgniter instance
     * @return bool True if status was reverted, false otherwise
     */
    function check_and_revert_status_if_price_or_date_changed($booking_id, $new_net_total = null, $new_start_date = null, $new_end_date = null, $CI)
    {
        // Get current booking
        $CI->load->model('Booking_Model');
        $current_booking = $CI->Booking_Model->getBookingById($booking_id);

        if (!$current_booking || $current_booking->Status !== 'PT') {
            return false; // Only revert when status is PT (Pending Travel)
        }

        $needs_revert = false;
        $revert_reason = '';
        $price_increased = false;

        // Check if NetTotal changed
        if ($new_net_total !== null) {
            $old_net_total = isset($current_booking->NetTotal) ? floatval($current_booking->NetTotal) : null;
            if ($old_net_total !== null && abs($new_net_total - $old_net_total) > 0.01) {
                $needs_revert = true;
                $price_increased = ($new_net_total > $old_net_total);
                $revert_reason = 'Booking total price changed from RM ' . number_format($old_net_total, 2) . ' to RM ' . number_format($new_net_total, 2);
            }
        }

        // Check if travel dates changed
        $normalize_date = function ($date) {
            if (empty($date) || $date == '0000-00-00' || $date == '0000-00-00 00:00:00') return null;
            $parsed = strtotime($date);
            return $parsed ? date('Y-m-d', $parsed) : null;
        };

        if ($new_start_date !== null || $new_end_date !== null) {
            $new_start_normalized = $new_start_date ? $normalize_date($new_start_date) : null;
            $new_end_normalized = $new_end_date ? $normalize_date($new_end_date) : null;
            $old_start_normalized = $normalize_date($current_booking->StartDate);
            $old_end_normalized = $normalize_date($current_booking->EndDate);

            if (($new_start_normalized && $new_start_normalized != $old_start_normalized) ||
                ($new_end_normalized && $new_end_normalized != $old_end_normalized)
            ) {
                $needs_revert = true;
                $date_change_desc = '';
                if ($new_start_normalized && $new_start_normalized != $old_start_normalized) {
                    $date_change_desc .= 'Start date changed from ' . ($old_start_normalized ? date('d/m/Y', strtotime($old_start_normalized)) : 'N/A') .
                        ' to ' . date('d/m/Y', strtotime($new_start_normalized));
                }
                if ($new_end_normalized && $new_end_normalized != $old_end_normalized) {
                    if ($date_change_desc) $date_change_desc .= '; ';
                    $date_change_desc .= 'End date changed from ' . ($old_end_normalized ? date('d/m/Y', strtotime($old_end_normalized)) : 'N/A') .
                        ' to ' . date('d/m/Y', strtotime($new_end_normalized));
                }
                $revert_reason = $revert_reason ? $revert_reason . '; ' . $date_change_desc : $date_change_desc;
            }
        }

        // PT → PTV: TC must re-approve the Travel Voucher
        if ($needs_revert) {
            $CI->load->helper('booking_status_log');
            $admin_id = $CI->session->userdata('admin_id') ?: 0;

            $CI->Booking_Model->Update_Status('PTV', $booking_id);

            log_booking_status_change(
                $booking_id,
                'PTV',
                $current_booking->Status,
                $admin_id,
                'Status reverted to PENDING TRAVEL VOUCHER - TC must re-approve voucher: ' . $revert_reason,
                true
            );

            return true;
        }

        return false;
    }
}

if (!function_exists('has_booking_payment')) {
    /**
     * Check if booking has any approved payment
     * 
     * @param int $booking_id Booking ID
     * @param CI_Controller $CI CodeIgniter instance
     * @return bool True if booking has approved payment
     */
    function has_booking_payment($booking_id, $CI)
    {
        $CI->load->model('Booking_Model');
        $payments = $CI->Booking_Model->Read_Payments($booking_id);

        // Check for any approved or pending payment (Status = 'Y' or 'P', Credit > 0, Type != 'SUPPLIER REFUND')
        // If payment exists (even pending), don't show as overdue
        if (!empty($payments)) {
            foreach ($payments as $payment) {
                $credit_amount = !empty($payment->Credit) ? floatval($payment->Credit) : 0;
                // Check for approved (Y) or pending (P) payments
                if ($payment->Type != 'SUPPLIER REFUND' && $credit_amount > 0 && ($payment->Status == 'Y' || $payment->Status == 'P')) {
                    return true;
                }
            }
        }

        // Fallback: Check status log for payment history
        $CI->load->model('Booking_Status_Log_Model');
        $status_logs = $CI->Booking_Status_Log_Model->get_by_booking_id($booking_id, true);
        foreach ($status_logs as $log) {
            if (
                stripos($log->description, 'payment received') !== false ||
                stripos($log->description, 'full payment') !== false ||
                stripos($log->description, 'deposit') !== false ||
                ($log->from_status == 'P' && $log->to_status == 'PBO') ||
                ($log->from_status == 'PBC' && $log->to_status == 'PBO')
            ) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('has_full_payment')) {
    /**
     * Check if booking has full payment (total approved credit >= NetTotal)
     *
     * @param int $booking_id Booking ID
     * @param object $booking Booking object (must have NetTotal)
     * @param CI_Controller $CI CodeIgniter instance
     * @return bool True if booking has full payment
     */
    function has_full_payment($booking_id, $booking, $CI)
    {
        $CI->load->model('Booking_Model');
        $payments = $CI->Booking_Model->Read_Payments($booking_id);

        $total_approved_credit = 0;
        $has_full_type = false;
        if (!empty($payments)) {
            foreach ($payments as $payment) {
                $credit_amount = !empty($payment->Credit) ? floatval($payment->Credit) : 0;
                if ($payment->Type != 'SUPPLIER REFUND' && $payment->Type != 'AGENT COMMISSION FROM SUPPLIER' && $credit_amount > 0 && $payment->Status == 'Y') {
                    $total_approved_credit += $credit_amount;
                }
                if ($payment->Type == 'FULL' && $payment->Status == 'Y') {
                    $has_full_type = true;
                }
            }
        }

        return $has_full_type && round($total_approved_credit, 2) >= round(floatval($booking->NetTotal), 2);
    }
}

if (!function_exists('are_all_checklists_completed')) {
    /**
     * Check if all booking checklists are completed
     * 
     * @param int $booking_id Booking ID
     * @param CI_Controller $CI CodeIgniter instance
     * @return bool True if all checklists are completed or no checklists exist
     */
    function are_all_checklists_completed($booking_id, $CI)
    {
        $CI->load->model('Booking_Product_Model');
        $CI->load->model('Booking_Checklist_Completion_Model');
        $CI->load->model('Package_Checklist_Model');
        $CI->load->model('Product_Package_Checklist_Model');

        // Get booking products
        $_GET['booking_id'] = $booking_id;
        $booking_products = $CI->Booking_Product_Model->Read();
        unset($_GET['booking_id']);

        // Get all package checklists
        $package_checklists = $CI->Package_Checklist_Model->Read_Package_Checklists();
        $checklist_map = array();
        foreach ($package_checklists as $pc) {
            $checklist_map[$pc->ID] = $pc;
        }

        // Get required checklist IDs
        $required_ids = array();
        foreach ($package_checklists as $pc) {
            if (isset($pc->is_required) && $pc->is_required == 1) {
                $required_ids[] = $pc->ID;
            }
        }

        // Get deposit checklist ID
        $deposit_checklist_id = null;
        foreach ($package_checklists as $pc) {
            if (strpos($pc->name, 'Payment Out To Supplier (deposit)') !== false) {
                $deposit_checklist_id = $pc->ID;
                break;
            }
        }

        // Group by product (collapse duplicates)
        $product_groups = array();
        foreach ($booking_products as $booking_product) {
            $product_id = $booking_product->ProductID;
            if (!isset($product_groups[$product_id])) {
                $product_groups[$product_id] = $booking_product;
            }
        }

        // Count total checklists per-product (not deduplicated)
        $total_count = 0;
        $required_completions = array(); // [[product_id, checklist_id], ...]
        foreach ($product_groups as $product_id => $booking_product) {
            // Skip products with disable_checklist_payment_out enabled
            if (isset($booking_product->disable_checklist_payment_out) && $booking_product->disable_checklist_payment_out == 1) {
                continue;
            }

            // Skip child/infant products — they don't require checklist completion
            $product_row = $CI->db->select('is_child_or_infant')->where('ProductID', $product_id)->get('product')->row();
            if ($product_row && $product_row->is_child_or_infant == 1) {
                continue;
            }

            $product_checklist_ids = $CI->Product_Package_Checklist_Model->Get_Checklists_For_Product($product_id);
            if (empty($product_checklist_ids)) {
                $product_checklist_ids = $required_ids;
            }

            // Auto-add deposit checklist if booking product has a PaymentOutSupplierDeposit date
            $has_deposit_date = !empty($booking_product->PaymentOutSupplierDeposit)
                && $booking_product->PaymentOutSupplierDeposit != '0000-00-00';
            if ($deposit_checklist_id && $has_deposit_date) {
                if (!in_array($deposit_checklist_id, $product_checklist_ids)) {
                    $product_checklist_ids[] = $deposit_checklist_id;
                }
            }

            foreach ($product_checklist_ids as $checklist_id) {
                if (isset($checklist_map[$checklist_id])) {
                    $total_count++;
                    $required_completions[] = array($product_id, $checklist_id);
                }
            }
        }

        // If no checklists, consider as completed
        if ($total_count == 0) {
            return true;
        }

        // Get completed checklists (nested map: product_id => checklist_id => info)
        $completion_map = $CI->Booking_Checklist_Completion_Model->Read_Completion_Map($booking_id);

        // Check each product's checklists are individually completed
        foreach ($required_completions as $pair) {
            if (!isset($completion_map[$pair[0]][$pair[1]])) {
                return false;
            }
        }

        return true;
    }
}

if (!function_exists('is_travel_voucher_sent')) {
    /**
     * Check if travel voucher has been sent
     * 
     * @param int $booking_id Booking ID
     * @param CI_Controller $CI CodeIgniter instance
     * @return bool True if travel voucher was sent
     */
    function is_travel_voucher_sent($booking_id, $CI)
    {
        $CI->load->model('Booking_Status_Log_Model');
        $status_logs = $CI->Booking_Status_Log_Model->get_by_booking_id($booking_id, true);

        foreach ($status_logs as $log) {
            // Check if there was a transition from PTV to PT
            if ($log->from_status == 'PTV' && $log->to_status == 'PT') {
                return true;
            }
            // Also check if description mentions travel voucher sent
            if (
                stripos($log->description, 'travel voucher sent') !== false ||
                stripos($log->description, 'approve travel voucher') !== false
            ) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('is_bc_approved')) {
    /**
     * Check if BC (Booking Confirmation) is approved
     * BC is approved if bc_approved field is 1 (true)
     * 
     * @param object $booking Booking object
     * @return bool True if BC is approved
     */
    function is_bc_approved($booking)
    {
        return (!empty($booking->bc_approved) && $booking->bc_approved == 1);
    }
}

if (!function_exists('is_guest_list_locked')) {
    /**
     * Check if guest list is locked
     * Guest list is locked if LockStatus is 'Y'
     * 
     * @param object $booking Booking object
     * @return bool True if guest list is locked
     */
    function is_guest_list_locked($booking)
    {
        return (!empty($booking->LockStatus) && $booking->LockStatus == 'Y');
    }
}

if (!function_exists('is_travel_completed')) {
    /**
     * Check if travel is completed
     * Travel is completed if status is Y (COMPLETED)
     * 
     * @param object $booking Booking object
     * @return bool True if travel is completed
     */
    function is_travel_completed($booking)
    {
        return (!empty($booking->Status) && $booking->Status == 'Y');
    }
}

if (!function_exists('is_booking_cancelled')) {
    /**
     * Check if booking is cancelled
     * Booking is cancelled if CancelStatus is 'Y'
     * 
     * @param object $booking Booking object
     * @return bool True if booking is cancelled
     */
    function is_booking_cancelled($booking)
    {
        return (!empty($booking->CancelStatus) && $booking->CancelStatus == 'Y');
    }
}

if (!function_exists('determine_booking_status_from_state')) {
    /**
     * Determine booking status based on sequential checks
     * Checks in order and returns first condition that fails:
     * 1. Cancelled → return CANCELLED (highest priority - check first)
     * 2. BC approved → if not, return PBC (PENDING BC CONFIRMATION)
     * 3. Payment received → if not, return P (PENDING PAYMENT)
     * 4. Full payment received → if not, return PP (PARTIAL PAYMENT)
     * 5. Checklist completed → if not, return PBO (PENDING BOOKING OPERATION)
     * 6. Guest list locked → if not, return PGL (PENDING GUEST LIST)
     * 7. Travel voucher sent → if not, return PTV (PENDING TRAVEL VOUCHER)
     * 8. Travel completed → if not, return PT (PENDING TRAVEL)
     * 9. Completed → return Y (COMPLETED)
     * 
     * @param int $booking_id Booking ID
     * @param object $booking Booking object
     * @param CI_Controller $CI CodeIgniter instance
     * @return array Array with 'status' and 'description' keys
     */
    function determine_booking_status_from_state($booking_id, $booking, $CI)
    {
        // Step 1: Check if cancelled (highest priority - check first)
        if (is_booking_cancelled($booking)) {
            return array(
                'status' => 'CANCELLED',
                'description' => 'Booking is cancelled'
            );
        }

        // Step 2: Check if BC approved
        if (!is_bc_approved($booking)) {
            return array(
                'status' => 'PBC',
                'description' => 'BC not approved - Status: PENDING BC CONFIRMATION'
            );
        }

        // Step 3: Check if payment received
        if (!has_booking_payment($booking_id, $CI)) {
            return array(
                'status' => 'P',
                'description' => 'No payment received - Status: PENDING PAYMENT'
            );
        }

        // Step 4: Check if full payment received
        if (!has_full_payment($booking_id, $booking, $CI)) {
            return array(
                'status' => 'PP',
                'description' => 'Partial payment received but not full payment - Status: PARTIAL PAYMENT'
            );
        }

        // Step 5: Check if checklist completed
        if (!are_all_checklists_completed($booking_id, $CI)) {
            return array(
                'status' => 'PBO',
                'description' => 'Full payment received but checklists not completed - Status: PENDING BOOKING OPERATION'
            );
        }

        // Step 6: Check if guest list locked
        if (!is_guest_list_locked($booking)) {
            return array(
                'status' => 'PGL',
                'description' => 'All checklists completed but guest list not locked - Status: PENDING GUEST LIST'
            );
        }

        // Step 7: Check if travel voucher sent
        if (!is_travel_voucher_sent($booking_id, $CI)) {
            return array(
                'status' => 'PTV',
                'description' => 'Guest list locked but travel voucher not sent - Status: PENDING TRAVEL VOUCHER'
            );
        }

        // Step 8: Check if travel completed (travel dates have passed)
        $travel_date_passed = false;
        $travel_end_date = !empty($booking->EndDate) ? $booking->EndDate : $booking->StartDate;
        if (!empty($travel_end_date)) {
            // Compare dates (ignore time)
            $travel_date = date('Y-m-d', strtotime($travel_end_date));
            $today = date('Y-m-d');
            $travel_date_passed = $travel_date < $today;
        }

        if (!$travel_date_passed) {
            return array(
                'status' => 'PT',
                'description' => 'All conditions met but travel not completed - Status: PENDING TRAVEL'
            );
        }

        // Step 9: Completed (travel dates have passed)
        return array(
            'status' => 'Y',
            'description' => 'Travel completed - Status: COMPLETED'
        );
    }
}

if (!function_exists('determine_status_after_bc_approval')) {
    /**
     * Determine the appropriate status after BC approval based on current booking state
     * Uses simplified determine_booking_status_from_state function
     * Note: When BC is approved, we skip the PBC check since we know BC is being approved
     * 
     * @param int $booking_id Booking ID
     * @param object $booking Booking object
     * @param CI_Controller $CI CodeIgniter instance
     * @return array Array with 'status' and 'description' keys
     */
    function determine_status_after_bc_approval($booking_id, $booking, $CI)
    {
        // Simulate BC being approved so determine_booking_status_from_state() skips the PBC check
        $simulated_booking = clone $booking;
        $simulated_booking->bc_approved = 1;

        $result = determine_booking_status_from_state($booking_id, $simulated_booking, $CI);

        // Update description for BC approval context
        $status_labels = array(
            'P' => 'PENDING PAYMENT',
            'PP' => 'PARTIAL PAYMENT',
            'PBO' => 'PENDING BOOKING OPERATION',
            'PGL' => 'PENDING GUEST LIST',
            'PTV' => 'PENDING TRAVEL VOUCHER',
            'PT' => 'PENDING TRAVEL',
            'Y' => 'COMPLETED',
            'CANCELLED' => 'CANCELLED'
        );
        $status_label = isset($status_labels[$result['status']]) ? $status_labels[$result['status']] : $result['status'];

        if ($result['status'] != 'CANCELLED' && $result['status'] != 'Y') {
            $result['description'] = 'BC Approved - Status advanced to ' . $status_label;
        }

        return $result;
    }
}

if (!function_exists('is_sa_blocked_from_completed_booking')) {
    /**
     * Whether a Sales Agent (level=20) must be denied access to Guest List
     * surfaces (page + downloads + entry buttons) for the given booking.
     *
     * Completed = Status='Y' AND AfterSalesService='COMPLETE' (matches the
     * existing SA/TC denial pattern at Booking.php:805/1412 and Payment.php:1861).
     * Higher-level roles (TC, manager, super admin) keep access for any
     * after-sales corrections they still need to make.
     */
    function is_sa_blocked_from_completed_booking($level, $status, $after_sales_service)
    {
        return (int)$level === 20
            && $status === 'Y'
            && $after_sales_service === 'COMPLETE';
    }
}

if (!function_exists('is_sa_acting_as_tc2')) {
    /**
     * True when a level-20 Sales Agent matches the booking's SalesAgent2 (TC2)
     * slot. Used in the booking listing to hide the BC link, GL actions, and
     * Customer actions for rows where the SA is only the secondary consultant
     * — those belong to TC1.
     */
    function is_sa_acting_as_tc2($user_level, $user_id, $booking_sales_agent_2)
    {
        return (int)$user_level === 20
            && !empty($booking_sales_agent_2)
            && (int)$booking_sales_agent_2 === (int)$user_id;
    }
}

if (!function_exists('is_upcoming_travel_not_ready')) {
    /**
     * Whether a booking should appear in the TC "Travel in 7 Days – Not Yet
     * Ready" dashboard card: travel starts inside the window but the booking
     * is still upstream of PT (PENDING TRAVEL). PT itself is the target state,
     * so it's excluded. Window boundaries are inclusive — matches the SQL
     * BETWEEN used by the count query in Booking::ajax_summary_cards.
     */
    function is_upcoming_travel_not_ready($status, $cancelStatus, $startDate, $window_start, $window_end)
    {
        if ($cancelStatus !== 'N') {
            return false;
        }
        if (!in_array($status, array('P', 'PBO', 'PGL', 'PTV'), true)) {
            return false;
        }
        return ($startDate >= $window_start && $startDate <= $window_end);
    }
}

if (!function_exists('can_user_modify_booking_checklist')) {
    /**
     * Strict whitelist for ticking a booking's checklist:
     *   - level 20 (TC1)  iff user_id === booking.SalesAgent
     *   - level 40 (OP)   iff user_id === booking.BookingOP
     *   - level 25 (TL)   iff user_id is the TeamLeadID of the booking's
     *                     SalesAgent OR BookingOP (callers pre-resolve and
     *                     pass these in as $tc1_team_lead_id / $op_team_lead_id)
     * All other levels (Owner/Finance/TC2/etc.) are blocked.
     *
     * $booking accepts either the object returned by getBookingById() or an
     * array with SalesAgent / BookingOP keys.
     */
    function can_user_modify_booking_checklist(
        $booking,
        $user_id,
        $user_level,
        $tc1_team_lead_id = null,
        $op_team_lead_id = null
    ) {
        if (empty($booking)) {
            return false;
        }
        $sales_agent = is_object($booking)
            ? (isset($booking->SalesAgent) ? $booking->SalesAgent : null)
            : (isset($booking['SalesAgent']) ? $booking['SalesAgent'] : null);
        $booking_op = is_object($booking)
            ? (isset($booking->BookingOP) ? $booking->BookingOP : null)
            : (isset($booking['BookingOP']) ? $booking['BookingOP'] : null);

        $uid = (int)$user_id;
        $lvl = (int)$user_level;

        if ($lvl === 20) {
            return (int)$sales_agent > 0 && (int)$sales_agent === $uid;
        }
        if ($lvl === 40) {
            return (int)$booking_op > 0 && (int)$booking_op === $uid;
        }
        if ($lvl === 25) {
            $tc1_tl = (int)$tc1_team_lead_id;
            $op_tl  = (int)$op_team_lead_id;
            if ($tc1_tl > 0 && $tc1_tl === $uid) {
                return true;
            }
            if ($op_tl > 0 && $op_tl === $uid) {
                return true;
            }
            return false;
        }
        return false;
    }
}

if (!function_exists('resolve_booking_checklist_team_leads')) {
    /**
     * Looks up admin.TeamLeadID for the booking's SalesAgent and BookingOP in
     * a single query. Returns ['tc1_tl' => int|null, 'op_tl' => int|null]
     * suitable for passing into can_user_modify_booking_checklist().
     */
    function resolve_booking_checklist_team_leads($booking)
    {
        $out = array('tc1_tl' => null, 'op_tl' => null);
        if (empty($booking)) {
            return $out;
        }
        $sales_agent = (int)(is_object($booking)
            ? (isset($booking->SalesAgent) ? $booking->SalesAgent : 0)
            : (isset($booking['SalesAgent']) ? $booking['SalesAgent'] : 0));
        $booking_op = (int)(is_object($booking)
            ? (isset($booking->BookingOP) ? $booking->BookingOP : 0)
            : (isset($booking['BookingOP']) ? $booking['BookingOP'] : 0));

        $ids = array_values(array_unique(array_filter(array($sales_agent, $booking_op))));
        if (empty($ids)) {
            return $out;
        }

        $CI =& get_instance();
        $CI->db->select('AdminID, TeamLeadID');
        $CI->db->where_in('AdminID', $ids);
        foreach ($CI->db->get('admin')->result() as $row) {
            if ((int)$row->AdminID === $sales_agent) {
                $out['tc1_tl'] = (int)$row->TeamLeadID;
            }
            if ((int)$row->AdminID === $booking_op) {
                $out['op_tl'] = (int)$row->TeamLeadID;
            }
        }
        return $out;
    }
}
