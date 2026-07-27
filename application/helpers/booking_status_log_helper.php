<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Booking Status Log Helper
 * 
 * Provides convenient functions to log booking status changes
 */

if (!function_exists('log_booking_status_change')) {
    /**
     * Log a booking status change
     * 
     * @param int $booking_id Booking ID
     * @param string $to_status New status
     * @param string|null $from_status Previous status (NULL for initial creation)
     * @param int $created_by Admin ID (0 for system)
     * @param string|null $description Optional description
     * @param bool $show_to_customer Whether to show in customer portal
     * @return int|bool Insert ID on success, false on failure
     */
    function log_booking_status_change($booking_id, $to_status, $from_status = null, $created_by = 0, $description = null, $show_to_customer = true)
    {
        $CI =& get_instance();
        $CI->load->model('Booking_Status_Log_Model');
        
        return $CI->Booking_Status_Log_Model->create(
            $booking_id,
            $to_status,
            $from_status,
            $created_by,
            $description,
            $show_to_customer
        );
    }
}

if (!function_exists('log_booking_creation')) {
    /**
     * Log booking creation with initial status
     * 
     * @param int $booking_id Booking ID
     * @param string $initial_status Initial status (default: PBC)
     * @param string|null $description Optional description
     * @param int $created_by Admin ID who created the booking (0 for system)
     * @return int|bool Insert ID on success, false on failure
     */
    function log_booking_creation($booking_id, $initial_status = 'PBC', $description = null, $created_by = 0)
    {
        if (empty($description)) {
            $description = "Booking created with status " . $initial_status;
        }
        
        return log_booking_status_change(
            $booking_id,
            $initial_status,
            null, // No from_status for creation
            $created_by, // Creator admin ID
            $description,
            true  // Show to customer
        );
    }
}

if (!function_exists('log_booking_status_update')) {
    /**
     * Log booking status update (manual change by admin)
     * 
     * @param int $booking_id Booking ID
     * @param string $from_status Previous status
     * @param string $to_status New status
     * @param int $admin_id Admin ID making the change
     * @param string|null $description Optional description
     * @param bool $show_to_customer Whether to show in customer portal
     * @return int|bool Insert ID on success, false on failure
     */
    function log_booking_status_update($booking_id, $from_status, $to_status, $admin_id, $description = null, $show_to_customer = true)
    {
        return;

        // ignore these status changes, because will cause double log
        if ($from_status == 'PBC' && $to_status == 'P') {
            return;
        }

        if (empty($description)) {
            $status_labels = array(
                'PBC' => 'PENDING BC CONFIRMATION',
                'P' => 'PENDING PAYMENT',
                'PP' => 'PARTIAL PAYMENT',
                'PBO' => 'PENDING BOOKING OPERATION',
                'PGL' => 'PENDING GUEST LIST',
                'PTV' => 'PENDING TRAVEL VOUCHER',
                'PT' => 'PENDING TRAVEL',
                'OG' => 'ON-GOING',
                'Y' => 'COMPLETED',
                'C' => 'CANCELLED'
            );
            
            $from_label = isset($status_labels[$from_status]) ? $status_labels[$from_status] : $from_status;
            $to_label = isset($status_labels[$to_status]) ? $status_labels[$to_status] : $to_status;
            $description = "Status updated from {$from_label} to {$to_label}";
        }
        
        return log_booking_status_change(
            $booking_id,
            $to_status,
            $from_status,
            $admin_id,
            $description,
            $show_to_customer
        );
    }
}

if (!function_exists('log_booking_automatic_status_change')) {
    /**
     * Log automatic status change (system-triggered)
     * 
     * @param int $booking_id Booking ID
     * @param string $from_status Previous status
     * @param string $to_status New status
     * @param string|null $reason Reason for automatic change
     * @param bool $show_to_customer Whether to show in customer portal
     * @return int|bool Insert ID on success, false on failure
     */
    function log_booking_automatic_status_change($booking_id, $from_status, $to_status, $reason = null, $show_to_customer = false)
    {
        $description = $reason;
        if (empty($description)) {
            $status_labels = array(
                'PBC' => 'PENDING BC CONFIRMATION',
                'P' => 'PENDING PAYMENT',
                'PP' => 'PARTIAL PAYMENT',
                'PBO' => 'PENDING BOOKING OPERATION',
                'PGL' => 'PENDING GUEST LIST',
                'PTV' => 'PENDING TRAVEL VOUCHER',
                'PT' => 'PENDING TRAVEL',
                'OG' => 'ON-GOING',
                'Y' => 'COMPLETED',
                'C' => 'CANCELLED'
            );
            
            $from_label = isset($status_labels[$from_status]) ? $status_labels[$from_status] : $from_status;
            $to_label = isset($status_labels[$to_status]) ? $status_labels[$to_status] : $to_status;
            $description = "Status automatically changed from {$from_label} to {$to_label}";
        }
        
        return log_booking_status_change(
            $booking_id,
            $to_status,
            $from_status,
            0, // System
            $description,
            $show_to_customer
        );
    }
}
