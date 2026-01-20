<?php
defined('BASEPATH') OR exit('No direct script access allowed');

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
            'PP',   // PARTIAL PAYMENT (derived from P based on payment amount)
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
        if($booking->Status != 'PBO') {
            return false;
        }
        
        // Use simplified status determination
        $status_info = determine_booking_status_from_state($booking_id, $booking, $CI);
        
        // If determined status is different from PBO, advance
        if($status_info['status'] != 'PBO') {
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
                'P' => '#FFBF00',       // PENDING PAYMENT - Orange
                'PP' => '#A7C7E7',      // PARTIAL PAYMENT - Light Blue
                'PTV' => '#F89880',     // PENDING TRAVEL VOUCHER - Light Red
                'PGL' => '#FAC898',     // PENDING GUEST LIST - Peach
                'PT' => '#F8C8DC',      // PENDING TRAVEL - Pink
                'OG' => '#CCCCFF',      // ON-GOING - Lavender
                'PO' => '#DA70D6',      // PAYMENT OVERDUE - Orchid
                'PBC' => '#FFD700',     // PENDING BC CONFIRMATION - Gold
                'PBO' => '#87CEEB',     // PENDING BOOKING OPERATION - Sky Blue
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
                'CANCELLED' => 'CANCELLED'
            ]
        ];
    }
}

if (!function_exists('display_booking_status')) {
    /**
     * Calculate and return the display status for a booking with proper priority
     * 
     * Priority order (highest to lowest):
     * 1. CANCELLED (CancelStatus = 'Y')
     * 2. PO (Payment Overdue)
     * 3. PR (Pending Review)
     * 4. PGL (Pending Guest List)
     * 5. OG (On-Going)
     * 6. Base Status (from database)
     * 
     * @param object|array $booking Booking object or array with required fields:
     *   - Status (base status from DB)
     *   - CancelStatus ('Y' or 'N')
     *   - LockStatus ('Y' or 'N')
     *   - AfterSalesService ('PENDING' or 'COMPLETE')
     *   - DepositDeadline (date string or null)
     *   - FullPaymentDeadline (date string)
     *   - StartDate (date string or null)
     *   - EndDate (date string or null)
     * @param bool $return_all_statuses If true, returns all applicable statuses, not just the highest priority one
     * @return array Array with keys:
     *   - 'status_code': The status code (e.g., 'PGL', 'PO', 'PR', etc.)
     *   - 'status_text': Human-readable status text
     *   - 'status_color': Color code for the status
     *   - 'all_statuses': (if $return_all_statuses is true) Array of all applicable statuses
     */
    function display_booking_status($booking, $return_all_statuses = false)
    {
        // Convert array to object if needed
        if (is_array($booking)) {
            $booking = (object)$booking;
        }
        
        // Get status info
        $status_info = get_booking_status_info();
        $status_colors = $status_info['colors'];
        $status_texts = $status_info['texts'];
        
        // Initialize applicable statuses array
        $applicable_statuses = [];
        
        // Check if Status field exists, default to empty if not
        $base_status = !empty($booking->Status) ? $booking->Status : '';
        $display_status = $base_status; // Start with base status
        
        // If no status at all, return unknown
        if (empty($base_status)) {
            return [
                'status_code' => 'UNKNOWN',
                'status_text' => 'UNKNOWN',
                'status_color' => '#DA70D6',
                'all_statuses' => []
            ];
        }
        
        // Priority 1: CANCELLED (highest priority - always wins)
        if (!empty($booking->CancelStatus) && $booking->CancelStatus == 'Y') {
            $display_status = 'CANCELLED';
            $applicable_statuses[] = 'CANCELLED';
            
            if (!$return_all_statuses) {
                return [
                    'status_code' => 'CANCELLED',
                    'status_text' => $status_texts['CANCELLED'],
                    'status_color' => $status_colors['CANCELLED']
                ];
            }
        }
        
        // Priority 2: PO (Payment Overdue) - Critical issue
        $is_payment_overdue = false;
        if (!empty($booking->Status) && ($booking->Status == 'P' || $booking->Status == 'PP')) {
            if (empty($booking->DepositDeadline)) {
                // No deposit deadline, check full payment deadline
                if (!empty($booking->FullPaymentDeadline) && 
                    date('Y-m-d') > date('Y-m-d', strtotime($booking->FullPaymentDeadline))) {
                    $is_payment_overdue = true;
                }
            } else {
                // Has deposit deadline
                $deposit_deadline = date('Y-m-d', strtotime($booking->DepositDeadline));
                $full_payment_deadline = !empty($booking->FullPaymentDeadline) ? date('Y-m-d', strtotime($booking->FullPaymentDeadline)) : null;
                $today = date('Y-m-d');
                
                if (($today > $deposit_deadline && $booking->Status == 'P') ||
                    ($full_payment_deadline && $today > $full_payment_deadline && 
                     ($booking->Status == 'P' || $booking->Status == 'PP'))) {
                    $is_payment_overdue = true;
                }
            }
        }
        
        if ($is_payment_overdue) {
            $applicable_statuses[] = 'PO';
            if (empty($applicable_statuses) || !in_array('CANCELLED', $applicable_statuses)) {
                $display_status = 'PO';
            }
        }
        
        // Priority 3: PR (Pending Review) - After completion
        if (!empty($booking->AfterSalesService) && 
            $booking->AfterSalesService == 'PENDING' && 
            $booking->Status == 'Y') {
            $applicable_statuses[] = 'PR';
            if (!in_array('CANCELLED', $applicable_statuses) && !in_array('PO', $applicable_statuses)) {
                $display_status = 'PR';
            }
        }
        
        // Priority 4: PGL (Pending Guest List) - When guest list is unlocked
        if (!empty($booking->LockStatus) && 
            $booking->LockStatus == 'N' && 
            $booking->Status == 'PTV') {
            $applicable_statuses[] = 'PGL';
            if (!in_array('CANCELLED', $applicable_statuses) && 
                !in_array('PO', $applicable_statuses) && 
                !in_array('PR', $applicable_statuses)) {
                $display_status = 'PGL';
            }
        }
        
        // Priority 5: OG (On-Going) - During travel dates
        if (!empty($booking->Status) && ($booking->Status == 'PT' || $booking->Status == 'Y') &&
            !empty($booking->StartDate) && 
            !empty($booking->EndDate)) {
            try {
                $current_date = date('Y-m-d');
                $start_date = date('Y-m-d', strtotime($booking->StartDate));
                $end_date = date('Y-m-d', strtotime($booking->EndDate));
                
                if ($start_date && $end_date && $current_date >= $start_date && $current_date <= $end_date) {
                    $applicable_statuses[] = 'OG';
                    if (!in_array('CANCELLED', $applicable_statuses) && 
                        !in_array('PO', $applicable_statuses) && 
                        !in_array('PR', $applicable_statuses) && 
                        !in_array('PGL', $applicable_statuses)) {
                        $display_status = 'OG';
                    }
                }
            } catch (Exception $e) {
                // Invalid date format, skip OG check
            }
        }
        
        // Add base status if not already in list
        if (!in_array($display_status, $applicable_statuses) && 
            !in_array('CANCELLED', $applicable_statuses)) {
            $applicable_statuses[] = $display_status;
        }
        
        // Get status text and color
        $status_code = $display_status;
        $status_text = isset($status_texts[$status_code]) ? $status_texts[$status_code] : 'UNKNOWN';
        $status_color = isset($status_colors[$status_code]) ? $status_colors[$status_code] : '#DA70D6';
        
        $result = [
            'status_code' => $status_code,
            'status_text' => $status_text,
            'status_color' => $status_color
        ];
        
        if ($return_all_statuses) {
            $result['all_statuses'] = $applicable_statuses;
        }
        
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
        
        if (!$current_booking || $current_booking->Status == 'PBC') {
            return false; // Already PBC or booking not found
        }
        
        $needs_revert = false;
        $revert_reason = '';
        
        // Check if NetTotal changed
        if ($new_net_total !== null) {
            $old_net_total = isset($current_booking->NetTotal) ? floatval($current_booking->NetTotal) : null;
            if ($old_net_total !== null && abs($new_net_total - $old_net_total) > 0.01) {
                $needs_revert = true;
                $revert_reason = 'Booking total price changed from RM ' . number_format($old_net_total, 2) . ' to RM ' . number_format($new_net_total, 2);
            }
        }
        
        // Check if travel dates changed
        $normalize_date = function($date) {
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
                ($new_end_normalized && $new_end_normalized != $old_end_normalized)) {
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
        
        // If revert is needed, revert to PBC
        if ($needs_revert) {
            $CI->load->helper('booking_status_log');
            $admin_id = $CI->session->userdata('admin_id') ?: 0;
            
            // Update status to PBC
            $CI->Booking_Model->Update_Status('PBC', $booking_id);
            
            // Log the revert with reason
            log_booking_status_change(
                $booking_id,
                'PBC',
                $current_booking->Status,
                $admin_id,
                'Status reverted to PENDING BC CONFIRMATION - ' . $revert_reason,
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
        
        // Check for any approved payment (Status = 'Y', Credit > 0, Type != 'SUPPLIER REFUND')
        if (!empty($payments)) {
            foreach ($payments as $payment) {
                $credit_amount = !empty($payment->Credit) ? floatval($payment->Credit) : 0;
                if ($payment->Type != 'SUPPLIER REFUND' && $credit_amount > 0 && $payment->Status == 'Y') {
                    return true;
                }
            }
        }
        
        // Fallback: Check status log for payment history
        $CI->load->model('Booking_Status_Log_Model');
        $status_logs = $CI->Booking_Status_Log_Model->get_by_booking_id($booking_id, true);
        foreach ($status_logs as $log) {
            if (stripos($log->description, 'payment received') !== false || 
                stripos($log->description, 'full payment') !== false ||
                stripos($log->description, 'deposit') !== false ||
                ($log->from_status == 'P' && $log->to_status == 'PBO') ||
                ($log->from_status == 'PBC' && $log->to_status == 'PBO')) {
                return true;
            }
        }
        
        return false;
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
        
        // Get all booking checklists
        $all_checklists = array();
        $all_checklist_ids = array();
        
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
        
        // Process each booking product
        foreach ($booking_products as $booking_product) {
            $product_id = $booking_product->ProductID;
            
            // Get checklists for this product
            $product_checklist_ids = $CI->Product_Package_Checklist_Model->Get_Checklists_For_Product($product_id);
            
            // If product doesn't have checklists, use required ones
            if (empty($product_checklist_ids)) {
                $product_checklist_ids = $required_ids;
            }
            
            // Build checklist list in order
            foreach ($product_checklist_ids as $checklist_id) {
                if (isset($checklist_map[$checklist_id]) && !in_array($checklist_id, $all_checklist_ids)) {
                    $all_checklists[] = $checklist_map[$checklist_id];
                    $all_checklist_ids[] = $checklist_id;
                }
            }
        }
        
        // If no checklists, consider as completed
        if (empty($all_checklists)) {
            return true;
        }
        
        // Get completed checklists
        $completion_map = $CI->Booking_Checklist_Completion_Model->Read_Completion_Map($booking_id);
        $completed_count = count($completion_map);
        $total_count = count($all_checklists);
        
        // All checklists are completed
        return ($completed_count >= $total_count && $total_count > 0);
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
            if (stripos($log->description, 'travel voucher sent') !== false || 
                stripos($log->description, 'sent travel voucher') !== false) {
                return true;
            }
        }
        
        return false;
    }
}

if (!function_exists('determine_booking_status_from_state')) {
    /**
     * Determine booking status based on payment, checklist, and travel voucher state
     * Simple and easy to understand logic:
     * - No payment → P (PENDING PAYMENT)
     * - Has payment → Check checklist → Not all completed → PBO (PENDING BOOKING OPERATION)
     * - Has payment → All checklists completed → Check travel voucher → Not sent → PTV (PENDING TRAVEL VOUCHER)
     * - Has payment → All checklists completed → Travel voucher sent → PT (PENDING TRAVEL)
     * 
     * @param int $booking_id Booking ID
     * @param object $booking Booking object
     * @param CI_Controller $CI CodeIgniter instance
     * @return array Array with 'status' and 'description' keys
     */
    function determine_booking_status_from_state($booking_id, $booking, $CI)
    {
        // Step 1: Check payment
        if (!has_booking_payment($booking_id, $CI)) {
            return array(
                'status' => 'P',
                'description' => 'No payment received - Status: PENDING PAYMENT'
            );
        }
        
        // Step 2: Check checklist
        if (!are_all_checklists_completed($booking_id, $CI)) {
            return array(
                'status' => 'PBO',
                'description' => 'Payment received but checklists not completed - Status: PENDING BOOKING OPERATION'
            );
        }
        
        // Step 3: Check travel voucher
        if (!is_travel_voucher_sent($booking_id, $CI)) {
            return array(
                'status' => 'PTV',
                'description' => 'All checklists completed but travel voucher not sent - Status: PENDING TRAVEL VOUCHER'
            );
        }
        
        // All conditions met
        return array(
            'status' => 'PT',
            'description' => 'All conditions met - Status: PENDING TRAVEL'
        );
    }
}

if (!function_exists('determine_status_after_bc_approval')) {
    /**
     * Determine the appropriate status after BC approval based on current booking state
     * Uses simplified determine_booking_status_from_state function
     * 
     * @param int $booking_id Booking ID
     * @param object $booking Booking object
     * @param CI_Controller $CI CodeIgniter instance
     * @return array Array with 'status' and 'description' keys
     */
    function determine_status_after_bc_approval($booking_id, $booking, $CI)
    {
        // Use simplified status determination function
        $result = determine_booking_status_from_state($booking_id, $booking, $CI);
        
        // Update description for BC approval context
        $status_labels = array(
            'P' => 'PENDING PAYMENT',
            'PBO' => 'PENDING BOOKING OPERATION',
            'PTV' => 'PENDING TRAVEL VOUCHER',
            'PT' => 'PENDING TRAVEL'
        );
        $status_label = isset($status_labels[$result['status']]) ? $status_labels[$result['status']] : $result['status'];
        
        $result['description'] = 'BC Approved - Status advanced to ' . $status_label;
        
        return $result;
    }
}
