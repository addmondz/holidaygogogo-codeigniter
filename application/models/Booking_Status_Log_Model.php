<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Booking_Status_Log_Model extends CI_Model
{
    /**
     * Create a status log entry
     * 
     * @param int $booking_id Booking ID
     * @param string $to_status New status
     * @param string|null $from_status Previous status (NULL for initial creation)
     * @param int $created_by Admin ID (0 for system)
     * @param string|null $description Optional description
     * @param bool $show_to_customer Whether to show in customer portal
     * @return int|bool Insert ID on success, false on failure
     */
    function create($booking_id, $to_status, $from_status = null, $created_by = 0, $description = null, $show_to_customer = true)
    {
        // Validate booking_id
        if (empty($booking_id)) {
            log_message('error', 'Booking_Status_Log_Model::create() called with empty booking_id');
            return false;
        }
        
        $data = array(
            'booking_id' => $booking_id,
            'from_status' => $from_status,
            'to_status' => $to_status,
            'created_by' => $created_by,
            'description' => $description,
            'show_to_customer' => $show_to_customer ? 1 : 0,
            'created_at' => date('Y-m-d H:i:s')
        );

        $this->db->insert('booking_status_log', $data);
        return $this->db->insert_id();
    }

    /**
     * Get all status logs for a booking
     * 
     * @param int $booking_id Booking ID
     * @param bool $include_hidden Include logs marked as hidden from customers
     * @return array Array of status log objects
     */
    function get_by_booking_id($booking_id, $include_hidden = false)
    {
        $this->db->select('booking_status_log.*, admin.Name AS created_by_name');
        $this->db->from('booking_status_log');
        $this->db->join('admin', 'admin.AdminID = booking_status_log.created_by', 'left');
        $this->db->where('booking_status_log.booking_id', $booking_id);
        
        if (!$include_hidden) {
            $this->db->where('booking_status_log.show_to_customer', 1);
        }
        
        $this->db->order_by('booking_status_log.created_at', 'ASC');
        
        return $this->db->get()->result();
    }

    /**
     * Get status logs for customer portal (only visible ones)
     * 
     * @param int $booking_id Booking ID
     * @return array Array of status log objects
     */
    function get_for_customer($booking_id)
    {
        return $this->get_by_booking_id($booking_id, false);
    }

    /**
     * Get latest status log for a booking
     * 
     * @param int $booking_id Booking ID
     * @return object|null Status log object or null
     */
    function get_latest($booking_id)
    {
        $this->db->select('booking_status_log.*, admin.Name AS created_by_name');
        $this->db->from('booking_status_log');
        $this->db->join('admin', 'admin.AdminID = booking_status_log.created_by', 'left');
        $this->db->where('booking_status_log.booking_id', $booking_id);
        $this->db->order_by('booking_status_log.created_at', 'DESC');
        $this->db->limit(1);
        
        return $this->db->get()->row();
    }

    /**
     * Get status logs with formatted data for timeline display
     * 
     * @param int $booking_id Booking ID
     * @param bool $for_customer Whether this is for customer portal
     * @return array Array of formatted status log data
     */
    function get_timeline_data($booking_id, $for_customer = false)
    {
        $logs = $for_customer 
            ? $this->get_for_customer($booking_id)
            : $this->get_by_booking_id($booking_id, true);

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
            'C' => 'CANCELLED',
            'PO' => 'PAYMENT OVERDUE',
            'PR' => 'PENDING REVIEW'
        );

        $timeline_data = array();
        foreach ($logs as $log) {
            $from_label = !empty($log->from_status) && isset($status_labels[$log->from_status]) 
                ? $status_labels[$log->from_status] 
                : ($log->from_status ?? 'New Booking');
            
            $to_label = isset($status_labels[$log->to_status]) 
                ? $status_labels[$log->to_status] 
                : $log->to_status;

            $description = !empty($log->description) 
                ? $log->description 
                : ($log->from_status 
                    ? "Status changed from {$from_label} to {$to_label}" 
                    : "Booking created with status {$to_label}");

            $created_by_name = !empty($log->created_by_name) 
                ? $log->created_by_name 
                : ($log->created_by == 0 ? 'System' : 'Unknown');

            $timeline_data[] = array(
                'id' => $log->id,
                'date' => date('d/m/y H:i', strtotime($log->created_at)),
                'date_raw' => $log->created_at,
                'title' => $to_label,
                'description' => $description,
                'from_status' => $log->from_status,
                'to_status' => $log->to_status,
                'created_by' => $created_by_name,
                'created_by_id' => $log->created_by,
                'icon' => $this->get_status_icon($log->to_status),
                'status' => $this->get_status_class($log->to_status)
            );
        }

        return $timeline_data;
    }

    /**
     * Get icon for status
     * 
     * @param string $status Status code
     * @return string Icon class
     */
    private function get_status_icon($status)
    {
        $icons = array(
            'PBC' => 'la la-file-alt',
            'P' => 'la la-exclamation-circle',
            'PP' => 'la la-dollar',
            'PBO' => 'la la-cog',
            'PGL' => 'la la-user-friends',
            'PTV' => 'la la-file-alt',
            'PT' => 'la la-suitcase',
            'OG' => 'la la-luggage-cart',
            'Y' => 'la la-check-circle',
            'C' => 'la la-times-circle',
            'PO' => 'la la-exclamation-triangle',
            'PR' => 'la la-user-edit'
        );

        return isset($icons[$status]) ? $icons[$status] : 'la la-info-circle';
    }

    /**
     * Get CSS class for status
     * 
     * @param string $status Status code
     * @return string CSS class
     */
    private function get_status_class($status)
    {
        $classes = array(
            'PBC' => 'pending',
            'P' => 'pending',
            'PP' => 'pending',
            'PBO' => 'pending',
            'PGL' => 'pending',
            'PTV' => 'pending',
            'PT' => 'pending',
            'OG' => 'completed',
            'Y' => 'completed',
            'C' => 'cancelled',
            'PO' => 'pending',
            'PR' => 'pending'
        );

        return isset($classes[$status]) ? $classes[$status] : 'pending';
    }

    /**
     * Delete status logs for a booking (used when booking is deleted)
     * 
     * @param int $booking_id Booking ID
     * @return bool Success status
     */
    function delete_by_booking_id($booking_id)
    {
        $this->db->where('booking_id', $booking_id);
        return $this->db->delete('booking_status_log');
    }

    /**
     * Get the maximum status reached for a booking (excluding PBC and derived statuses)
     * This is used to restore booking to the highest status it previously reached
     * 
     * @param int $booking_id Booking ID
     * @return string|null Maximum status code or null if none found
     */
    function get_maximum_status_reached($booking_id)
    {
        // Get all status logs for this booking
        $logs = $this->get_by_booking_id($booking_id, true);
        
        // Define status priority (higher number = higher priority/more advanced)
        $status_priority = array(
            'PBC' => 0,
            'P' => 1,
            'PP' => 1, // Same as P
            'PBO' => 2,
            'PTV' => 3,
            'PT' => 4,
            'OG' => 4, // Same as PT
            'Y' => 5,
            'PR' => 5, // Derived from Y
            'PGL' => 3, // Derived from PTV
            'PO' => 1, // Derived from P/PP
            'CANCELLED' => -1 // Exclude cancelled
        );
        
        $max_status = null;
        $max_priority = -1;
        
        foreach ($logs as $log) {
            $status = $log->to_status;
            
            // Skip cancelled and derived statuses that shouldn't be restored
            if ($status == 'CANCELLED' || $status == 'PO' || $status == 'PR' || $status == 'PGL' || $status == 'OG') {
                continue;
            }
            
            $priority = isset($status_priority[$status]) ? $status_priority[$status] : 0;
            
            if ($priority > $max_priority) {
                $max_priority = $priority;
                $max_status = $status;
            }
        }
        
        return $max_status;
    }
}
