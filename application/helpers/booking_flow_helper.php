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
     * If so, and status is PBO, advance to next status (PTV)
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
        
        // Load required models
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
        foreach($package_checklists as $pc) {
            $checklist_map[$pc->ID] = $pc;
        }
        
        // Get required checklist IDs
        $required_ids = array();
        foreach($package_checklists as $pc) {
            if(isset($pc->is_required) && $pc->is_required == 1) {
                $required_ids[] = $pc->ID;
            }
        }
        
        // Process each booking product
        foreach($booking_products as $booking_product) {
            $product_id = $booking_product->ProductID;
            
            // Get checklists for this product
            $product_checklist_ids = $CI->Product_Package_Checklist_Model->Get_Checklists_For_Product($product_id);
            
            // If product doesn't have checklists, use required ones
            if(empty($product_checklist_ids)) {
                $product_checklist_ids = $required_ids;
            }
            
            // Build checklist list in order
            foreach($product_checklist_ids as $checklist_id) {
                if(isset($checklist_map[$checklist_id]) && !in_array($checklist_id, $all_checklist_ids)) {
                    $all_checklists[] = $checklist_map[$checklist_id];
                    $all_checklist_ids[] = $checklist_id;
                }
            }
        }
        
        // If no checklists, advance to next status
        if(empty($all_checklists)) {
            $next_status = get_next_status_in_flow($booking->Status);
            if($next_status && $next_status != $booking->Status) {
                $CI->load->model('Booking_Model');
                $CI->load->helper('booking_status_log');
                
                $CI->Booking_Model->Update_Status($next_status, $booking_id);
                $CI->Booking_Model->Create_Booking_Log2($booking->Status, $next_status, $booking_id);
                
                log_booking_status_change(
                    $booking_id,
                    $next_status,
                    $booking->Status,
                    $created_by,
                    "No booking checklists required - status advanced to " . $next_status,
                    true
                );
                return true;
            }
            return false;
        }
        
        // Get completed checklists
        $completion_map = $CI->Booking_Checklist_Completion_Model->Read_Completion_Map($booking_id);
        $completed_count = count($completion_map);
        $total_count = count($all_checklists);
        
        // If all checklists are completed, advance to next status
        if($completed_count >= $total_count && $total_count > 0) {
            $next_status = get_next_status_in_flow($booking->Status);
            if($next_status && $next_status != $booking->Status) {
                $CI->load->model('Booking_Model');
                $CI->load->helper('booking_status_log');
                
                $CI->Booking_Model->Update_Status($next_status, $booking_id);
                $CI->Booking_Model->Create_Booking_Log2($booking->Status, $next_status, $booking_id);
                
                log_booking_status_change(
                    $booking_id,
                    $next_status,
                    $booking->Status,
                    $created_by,
                    "All booking checklists completed - status advanced to " . $next_status,
                    true
                );
                return true;
            }
        }
        
        return false;
    }
}
