<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Customer Portal Controller
 * 
 * Provides public access to customer portal via HMAC-signed URLs.
 * Format: /customer-portal/{hash}
 * 
 * No authentication required - access is controlled via HMAC hash verification.
 */
class Customer_Portal extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Customer_Model');
        $this->load->model('Booking_Model');
        $this->load->model('Payment_Model');
        $this->load->helper('utils');
    }

    /**
     * Customer Dashboard - First screen customer sees
     * Lists all customer bookings with filters
     * 
     * @param string $hash HMAC hash from URL
     */
    public function dashboard($hash = null)
    {
        if (empty($hash)) {
            show_404();
            return;
        }

        // Find customer by verifying hash against all customer codes
        $customer = $this->find_customer_by_hash($hash);
        
        if (!$customer) {
            show_404();
            return;
        }

        // Get filter parameters
        $status_filter = $this->input->get('status');
        $travel_date_from = $this->input->get('travel_date_from');
        $travel_date_to = $this->input->get('travel_date_to');

        // Get customer bookings with filters
        $bookings = $this->get_customer_bookings_filtered(
            $customer['CustomerID'], 
            $status_filter, 
            $travel_date_from, 
            $travel_date_to
        );

        // Get booking status options
        $status_options = $this->get_booking_status_options();

        // Prepare data for view
        $data = [
            'customer' => $customer,
            'bookings' => $bookings,
            'hash' => $hash,
            'status_filter' => $status_filter,
            'travel_date_from' => $travel_date_from,
            'travel_date_to' => $travel_date_to,
            'status_options' => $status_options
        ];

        // Load dashboard view
        $this->load->view('customer_portal/dashboard', $data);
    }

    /**
     * Main portal entry point (legacy - redirects to dashboard)
     * 
     * @param string $hash HMAC hash from URL
     */
    public function index($hash = null)
    {
        // Redirect to dashboard
        redirect('customer/' . $hash);
    }

    /**
     * Find customer by verifying HMAC hash against all customer codes
     * 
     * @param string $hash The HMAC hash from URL
     * @return array|null Customer data if found, null otherwise
     */
    private function find_customer_by_hash($hash)
    {
        // Get all active customers with CustomerCode
        $this->db->select('CustomerID, CustomerCode, name, phone_number, ChatLanguage, Status');
        $this->db->where('Status', 'Y');
        $this->db->where('CustomerCode IS NOT NULL', null, false);
        $this->db->where('CustomerCode !=', '');
        $customers = $this->db->get('customer')->result_array();

        // Try each customer code until we find a match
        foreach ($customers as $customer) {
            if (verify_customer_portal_hash($hash, $customer['CustomerCode'])) {
                return $customer;
            }
        }

        return null;
    }

    /**
     * Get all bookings for a customer
     * 
     * @param int $customer_id
     * @return array
     */
    private function get_customer_bookings($customer_id)
    {
        $this->db->select('booking.BookingID, BookingNumber, DepositDeadline, FullPaymentDeadline, 
                          Customer, booking.Mobile As CustomerMobile, StartDate, EndDate, NetTotal, 
                          booking.ChatLanguage, Token, booking.BookingConfirmationTitle, CancelStatus, 
                          LockStatus, AfterSalesService, booking.Status, booking.InsertDate,
                          category.Name As DestinationName, CountryCode');
        $this->db->from('booking');
        $this->db->join('category', 'category.CategoryID = booking.Destination', 'left');
        $this->db->join('country_code', 'country_code.CountryCodeID = booking.CountryCodeID', 'left');
        $this->db->where('booking.CustomerID', $customer_id);
        $this->db->where('booking.Status !=', 'N');
        $this->db->order_by('booking.BookingID', 'DESC');
        
        return $this->db->get()->result_array();
    }

    /**
     * Get filtered bookings for a customer
     * 
     * @param int $customer_id
     * @param string|null $status_filter
     * @param string|null $travel_date_from
     * @param string|null $travel_date_to
     * @return array
     */
    private function get_customer_bookings_filtered($customer_id, $status_filter = null, $travel_date_from = null, $travel_date_to = null)
    {
        $this->db->select('booking.BookingID, BookingNumber, DepositDeadline, FullPaymentDeadline, 
                          Customer, booking.Mobile As CustomerMobile, StartDate, EndDate, NetTotal, 
                          booking.ChatLanguage, Token, booking.BookingConfirmationTitle, CancelStatus, 
                          LockStatus, AfterSalesService, booking.Status, booking.InsertDate,
                          category.Name As DestinationName, CountryCode');
        $this->db->from('booking');
        $this->db->join('category', 'category.CategoryID = booking.Destination', 'left');
        $this->db->join('country_code', 'country_code.CountryCodeID = booking.CountryCodeID', 'left');
        $this->db->where('booking.CustomerID', $customer_id);
        $this->db->where('booking.Status !=', 'N');

        // Apply status filter
        if (!empty($status_filter)) {
            if ($status_filter == 'C') {
                // Cancelled
                $this->db->where('CancelStatus', 'Y');
            } elseif ($status_filter == 'Y') {
                // Completed
                $this->db->where('CancelStatus', 'N');
                $this->db->where('AfterSalesService', 'COMPLETE');
                $this->db->where('booking.Status', 'Y');
            } elseif ($status_filter == 'OG') {
                // On-going
                $this->db->where('CancelStatus', 'N');
                $this->db->where('booking.Status', 'OG');
            } elseif ($status_filter == 'PP') {
                // Partial Payment
                $this->db->where('CancelStatus', 'N');
                $this->db->where('booking.Status', 'PP');
            } elseif ($status_filter == 'P') {
                // Pending Payment
                $this->db->where('CancelStatus', 'N');
                $this->db->where('booking.Status', 'P');
            } elseif ($status_filter == 'PT') {
                // Pending Travel
                $this->db->where('CancelStatus', 'N');
                $this->db->where('booking.Status', 'PT');
            } elseif ($status_filter == 'PTV') {
                // Pending Travel Voucher
                $this->db->where('CancelStatus', 'N');
                $this->db->where('booking.Status', 'PTV');
            } elseif ($status_filter == 'PO') {
                // Payment Overdue
                $this->db->where('CancelStatus', 'N');
                $this->db->where("((FullPaymentDeadline < '" . date('Y-m-d') . "' AND booking.Status IN ('P','PP')) OR (DepositDeadline < '" . date('Y-m-d') . "' AND booking.Status = 'P'))");
            }
        } else {
            // Default: exclude cancelled
            $this->db->where('CancelStatus', 'N');
        }

        // Apply travel date filter
        if (!empty($travel_date_from)) {
            $this->db->where('StartDate >=', date('Y-m-d', strtotime($travel_date_from)));
        }
        if (!empty($travel_date_to)) {
            $this->db->where('StartDate <=', date('Y-m-d', strtotime($travel_date_to)));
        }

        $this->db->order_by('booking.StartDate', 'DESC');
        $this->db->order_by('booking.BookingID', 'DESC');
        
        return $this->db->get()->result_array();
    }

    /**
     * Get booking status options for filter
     * 
     * @return array
     */
    private function get_booking_status_options()
    {
        return [
            '' => 'All Statuses',
            'P' => 'Pending Payment',
            'PP' => 'Partial Payment',
            'PT' => 'Pending Travel',
            'PTV' => 'Pending Travel Voucher',
            'OG' => 'On-Going',
            'Y' => 'Completed',
            'PO' => 'Payment Overdue',
            'C' => 'Cancelled'
        ];
    }

    /**
     * Get all payments for a customer (via bookings)
     * 
     * @param int $customer_id
     * @return array
     */
    private function get_customer_payments($customer_id)
    {
        $this->db->select('payment.PaymentID, payment.BookingID, payment.SupplierID, payment.Date, 
                          payment.Type, payment.Credit, payment.ReferenceNumber, payment.Debit, 
                          payment.Deadline, payment.BankHolder, payment.Status, 
                          booking.BookingNumber, booking.Customer, booking.StartDate, booking.EndDate,
                          supplier.Name As SupplierName');
        $this->db->from('payment');
        $this->db->join('booking', 'booking.BookingID = payment.BookingID', 'left');
        $this->db->join('supplier', 'supplier.SupplierID = payment.SupplierID', 'left');
        $this->db->where('booking.CustomerID', $customer_id);
        $this->db->where('payment.Status !=', 'N');
        $this->db->order_by('payment.PaymentID', 'DESC');
        
        return $this->db->get()->result_array();
    }
}

