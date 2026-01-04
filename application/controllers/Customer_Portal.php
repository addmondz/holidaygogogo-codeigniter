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

        // Get customer bookings separated into upcoming and completed
        $bookings_data = $this->get_customer_bookings_by_category($customer['CustomerID']);

        // Format pax information for each booking
        foreach ($bookings_data['upcoming'] as &$booking) {
            $booking['PaxInfo'] = $this->format_pax_info($booking['Adult'] ?? 0, $booking['Children'] ?? 0, $booking['Infant'] ?? 0);
        }
        foreach ($bookings_data['completed'] as &$booking) {
            $booking['PaxInfo'] = $this->format_pax_info($booking['Adult'] ?? 0, $booking['Children'] ?? 0, $booking['Infant'] ?? 0);
        }

        // Prepare data for view
        $data = [
            'customer' => $customer,
            'upcoming_bookings' => $bookings_data['upcoming'],
            'completed_bookings' => $bookings_data['completed'],
            'hash' => $hash
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
     * Find customer by verifying HMAC hash against all customer IDs
     * 
     * @param string $hash The HMAC hash from URL
     * @return array|null Customer data if found, null otherwise
     */
    private function find_customer_by_hash($hash)
    {
        // Get all active customers
        $this->db->select('CustomerID, CustomerCode, name, phone_number, ChatLanguage, Status');
        $this->db->where('Status', 'Y');
        $customers = $this->db->get('customer')->result_array();

        // Try each customer ID until we find a match
        foreach ($customers as $customer) {
            if (verify_customer_portal_hash($hash, $customer['CustomerID'])) {
                // Get customer email from guest_list (get first email from their bookings)
                $this->db->select('guest_list.Email');
                $this->db->from('guest_list');
                $this->db->join('booking', 'booking.BookingID = guest_list.BookingID', 'left');
                $this->db->where('booking.CustomerID', $customer['CustomerID']);
                $this->db->where('guest_list.Email IS NOT NULL', null, false);
                $this->db->where('guest_list.Email !=', '');
                $this->db->where('guest_list.Status', 'Y');
                $this->db->order_by('guest_list.GuestListID', 'ASC');
                $this->db->limit(1);
                $email_result = $this->db->get()->row_array();
                
                $customer['email'] = !empty($email_result['Email']) ? $email_result['Email'] : null;
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
     * Get customer bookings separated into upcoming and completed
     * 
     * @param int $customer_id
     * @return array ['upcoming' => [], 'completed' => []]
     */
    private function get_customer_bookings_by_category($customer_id)
    {
        $this->db->select('booking.BookingID, BookingNumber, DepositDeadline, FullPaymentDeadline, 
                          Customer, booking.Mobile As CustomerMobile, StartDate, EndDate, NetTotal, 
                          booking.ChatLanguage, Token, booking.BookingConfirmationTitle, CancelStatus, 
                          LockStatus, AfterSalesService, booking.Status, booking.InsertDate,
                          booking.Adult, booking.Children, booking.Infant,
                          category.Name As DestinationName, CountryCode');
        $this->db->from('booking');
        $this->db->join('category', 'category.CategoryID = booking.Destination', 'left');
        $this->db->join('country_code', 'country_code.CountryCodeID = booking.CountryCodeID', 'left');
        $this->db->where('booking.CustomerID', $customer_id);
        $this->db->where('booking.Status !=', 'N');
        $this->db->where('CancelStatus', 'N'); // Always exclude cancelled
        // Hide pending bookings (Status = 'P') from customers - only show confirmed bookings
        $this->db->where('booking.Status !=', 'P'); // Exclude pending bookings
        $this->db->order_by('booking.StartDate', 'DESC');
        $this->db->order_by('booking.BookingID', 'DESC');
        
        $all_bookings = $this->db->get()->result_array();
        
        $upcoming = [];
        $completed = [];
        
        $today = date('Y-m-d');
        
        foreach ($all_bookings as $booking) {
            // Check if travel date has passed
            // Use EndDate if available, otherwise use StartDate
            $travel_date_passed = false;
            $travel_end_date = !empty($booking['EndDate']) ? $booking['EndDate'] : $booking['StartDate'];
            if (!empty($travel_end_date)) {
                // Compare dates (ignore time)
                $travel_date = date('Y-m-d', strtotime($travel_end_date));
                $travel_date_passed = $travel_date < $today;
            }
            
            // Completed: 
            // 1. Status = 'Y' AND AfterSalesService = 'COMPLETE', OR
            // 2. Travel date has passed (EndDate < today)
            if (($booking['Status'] == 'Y' && $booking['AfterSalesService'] == 'COMPLETE') || $travel_date_passed) {
                $completed[] = $booking;
            } else {
                // Upcoming: Confirmed bookings (PP, PTV, PT, OG, etc.) where travel date hasn't passed
                // Note: Pending (P) bookings are already filtered out in the query above
                $upcoming[] = $booking;
            }
        }
        
        return [
            'upcoming' => $upcoming,
            'completed' => $completed
        ];
    }

    /**
     * Format passenger information (Adult, Children, Infant)
     * 
     * @param int $adult
     * @param int $children
     * @param int $infant
     * @return string Formatted pax information
     */
    private function format_pax_info($adult = 0, $children = 0, $infant = 0)
    {
        $parts = [];
        
        if (!empty($adult) && $adult > 0) {
            $parts[] = $adult . ($adult == 1 ? ' Adult' : ' Adults');
        }
        
        if (!empty($children) && $children > 0) {
            $parts[] = $children . ($children == 1 ? ' Child' : ' Children');
        }
        
        if (!empty($infant) && $infant > 0) {
            $parts[] = $infant . ($infant == 1 ? ' Infant' : ' Infants');
        }
        
        if (empty($parts)) {
            return 'No pax info';
        }
        
        return implode(', ', $parts);
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

    /**
     * Booking Details Page - Shows booking details and documents
     * 
     * @param string $hashed_bc Booking token (hashed booking confirmation)
     */
    public function booking_details($hashed_bc = null)
    {
        if (empty($hashed_bc)) {
            show_404();
            return;
        }

        // Get booking by token
        $this->db->select('booking.BookingID, BookingNumber, ReservationNumber, DepositDeadline, 
                          FullPaymentDeadline, AdditionalPaymentDeadline, Customer, booking.Mobile As CustomerMobile, 
                          StartDate, EndDate, Adult, Children, Infant, BookingRemark, Subtotal, Discount, NetTotal, 
                          booking.ChatLanguage, Token, booking.BookingConfirmationTitle, CancelStatus, LockStatus, 
                          AfterSalesService, booking.Status, booking.InsertDate, booking.CustomerID,
                          booking.AllowReview, booking.CustomerReview, booking.CustomerReviewTimestamp,
                          category.Name As DestinationName, CountryCode, admin.Name As SalesAgentName');
        $this->db->from('booking');
        $this->db->join('category', 'category.CategoryID = booking.Destination', 'left');
        $this->db->join('country_code', 'country_code.CountryCodeID = booking.CountryCodeID', 'left');
        $this->db->join('admin', 'admin.AdminID = booking.SalesAgent', 'left');
        $this->db->where('booking.Token', $hashed_bc);
        $this->db->where('booking.Status !=', 'N');
        $booking = $this->db->get()->row_array();

        if (empty($booking)) {
            show_404();
            return;
        }

        // Get customer info
        $customer = null;
        if (!empty($booking['CustomerID'])) {
            $this->db->select('CustomerID, CustomerCode, name, phone_number, ChatLanguage');
            $this->db->where('CustomerID', $booking['CustomerID']);
            $customer = $this->db->get('customer')->row_array();
        }

        // Store raw dates before formatting (for timeline calculations)
        $booking['StartDateRaw'] = !empty($booking['StartDate']) ? $booking['StartDate'] : null;
        $booking['EndDateRaw'] = !empty($booking['EndDate']) ? $booking['EndDate'] : null;
        $booking['DepositDeadlineRaw'] = !empty($booking['DepositDeadline']) ? $booking['DepositDeadline'] : null;
        $booking['FullPaymentDeadlineRaw'] = !empty($booking['FullPaymentDeadline']) ? $booking['FullPaymentDeadline'] : null;
        $booking['InsertDateRaw'] = !empty($booking['InsertDate']) ? $booking['InsertDate'] : null;

        // Format booking data
        $booking['DepositDeadline'] = !empty($booking['DepositDeadline']) ? date('d M Y', strtotime($booking['DepositDeadline'])) : null;
        $booking['FullPaymentDeadline'] = !empty($booking['FullPaymentDeadline']) ? date('d M Y', strtotime($booking['FullPaymentDeadline'])) : null;
        $booking['AdditionalPaymentDeadline'] = !empty($booking['AdditionalPaymentDeadline']) ? date('d M Y', strtotime($booking['AdditionalPaymentDeadline'])) : null;
        $booking['StartDate'] = !empty($booking['StartDate']) ? date('d M Y', strtotime($booking['StartDate'])) : null;
        $booking['EndDate'] = !empty($booking['EndDate']) ? date('d M Y', strtotime($booking['EndDate'])) : null;
        $booking['InsertDate'] = !empty($booking['InsertDate']) ? date('d M Y', strtotime($booking['InsertDate'])) : null;
        $booking['CustomerMobile'] = $booking['CountryCode'] . $booking['CustomerMobile'];
        $booking['PaxInfo'] = $this->format_pax_info($booking['Adult'] ?? 0, $booking['Children'] ?? 0, $booking['Infant'] ?? 0);

        // Get booking products
        $this->db->select('*');
        $this->db->where('BookingID', $booking['BookingID']);
        $this->db->where('Status', 'Y');
        $this->db->order_by('BookingProductID', 'ASC');
        $booking['products'] = $this->db->get('booking_product')->result_array();

        // Get payment history (exclude SUPPLIER PAYMENT)
        $this->db->select('Date, Type, Credit, ReferenceNumber, Debit, Deadline, payment.Status, PaymentRemark, DebitRemark, payment.Bank, payment.BankAccount, payment.BankHolder, supplier.Name As SupplierName');
        $this->db->from('payment');
        $this->db->join('supplier', 'supplier.SupplierID = payment.SupplierID', 'left');
        $this->db->where('payment.BookingID', $booking['BookingID']);
        $this->db->where('payment.Status !=', 'N');
        $this->db->where('payment.Type !=', 'SUPPLIER PAYMENT');
        $this->db->order_by('Date', 'ASC');
        $this->db->order_by('PaymentID', 'ASC');
        $payments = $this->db->get()->result_array();

        // Format payments and calculate totals
        $total_credit = 0;
        $total_debit = 0;
        foreach ($payments as &$payment) {
            // Store raw date before formatting
            $payment['DateRaw'] = !empty($payment['Date']) ? $payment['Date'] : null;
            
            if (!empty($payment['Date'])) {
                $payment['Date'] = date('d M Y', strtotime($payment['Date']));
            }
            if (!empty($payment['Deadline'])) {
                $payment['Deadline'] = date('d M Y', strtotime($payment['Deadline']));
            }
            if ($payment['Status'] == 'Y' || $payment['Status'] == 'P') {
                if (!empty($payment['Credit']) && $payment['Credit'] > 0) {
                    $total_credit += $payment['Credit'];
                }
                if (!empty($payment['Debit']) && $payment['Debit'] > 0) {
                    $total_debit += $payment['Debit'];
                }
            }
        }
        $booking['payments'] = $payments;
        $booking['total_paid'] = $total_credit;
        $booking['total_debit'] = $total_debit;
        $booking['balance_due'] = $booking['NetTotal'] - $total_credit;

        // Prepare document URLs
        $base_url = base_url();
        $booking['documents'] = [
            'bc' => [
                'name' => 'Booking Confirmation',
                'url' => $base_url . 'Booking_Confirmation?token=' . $hashed_bc,
                'icon' => 'file-text',
                'available' => true
            ],
            'tv' => [
                'name' => 'Travel Voucher',
                'url' => $base_url . 'Travel_Voucher?token=' . $hashed_bc,
                'icon' => 'plane',
                'available' => true
            ],
            'or' => [
                'name' => 'Official Receipt',
                'url' => $base_url . 'Receipt?token=' . $hashed_bc,
                'icon' => 'receipt',
                'available' => true
            ],
            'gl' => [
                'name' => 'Guest List',
                'url' => $base_url . 'Guest_List?gl=' . $hashed_bc,
                'icon' => 'users',
                'available' => true
            ]
        ];

        // Get custom uploads
        $this->db->select('custom_upload.*');
        $this->db->from('custom_upload');
        $this->db->where('custom_upload.booking_id', $booking['BookingID']);
        $this->db->order_by('custom_upload.created_at', 'DESC');
        $custom_uploads = $this->db->get()->result_array();
        
        // Add custom uploads to documents array
        foreach ($custom_uploads as $upload) {
            $booking['documents']['cu_' . $upload['id']] = [
                'name' => $upload['upload_name'],
                'url' => $base_url . $upload['upload_content'],
                'icon' => 'download',
                'available' => true
            ];
        }

        // Check if guest list exists
        $this->db->select('COUNT(*) as count');
        $this->db->where('BookingID', $booking['BookingID']);
        $this->db->where('Status', 'Y');
        $guest_list_result = $this->db->get('guest_list')->row_array();
        $booking['has_guest_list'] = !empty($guest_list_result['count']) && $guest_list_result['count'] > 0;

        // Get status display info
        $booking['status_display'] = $this->get_booking_status_display($booking);

        // Generate customer hash for back button
        $customer_hash = '';
        if (!empty($customer) && !empty($customer['CustomerID'])) {
            $customer_hash = generate_customer_portal_hash($customer['CustomerID']);
        }

        // Prepare data for view
        $data = [
            'booking' => $booking,
            'customer' => $customer,
            'customer_hash' => $customer_hash
        ];

        // Load booking details view
        $this->load->view('customer_portal/booking_details', $data);
    }

    /**
     * Get booking status display information based on BC stage visibility rules
     * 
     * @param array $booking
     * @return array
     */
    private function get_booking_status_display($booking)
    {
        $status = $booking['Status'];
        $cancel_status = $booking['CancelStatus'];
        $after_sales = $booking['AfterSalesService'];

        if ($cancel_status == 'Y') {
            return [
                'text' => 'Cancelled',
                'class' => 'status-cancelled',
                'color' => '#E0115F'
            ];
        }

        // Determine display status based on BC stage visibility rules
        // Pending: Status = 'P' (Pending Payment - before booking confirmation)
        // Confirmed: Status IN ('PP', 'PTV', 'PT', 'OG') - after booking confirmation
        // Completed: Status = 'Y' AND AfterSalesService = 'COMPLETE'
        
        if ($status == 'Y' && $after_sales == 'COMPLETE') {
            // Completed
            return [
                'text' => 'Completed',
                'class' => 'status-completed',
                'color' => '#50C878'
            ];
        } elseif (in_array($status, ['PP', 'PTV', 'PT', 'OG'])) {
            // Confirmed - after booking confirmation
            return [
                'text' => 'Confirmed',
                'class' => 'status-partial-payment',
                'color' => '#A7C7E7'
            ];
        } elseif ($status == 'P') {
            // Pending - before booking confirmation
            return [
                'text' => 'Pending',
                'class' => 'status-pending-payment',
                'color' => '#FFBF00'
            ];
        }

        // Fallback for any other status
        return [
            'text' => 'Unknown',
            'class' => 'status-unknown',
            'color' => '#999'
        ];
    }

    /**
     * Submit customer review for a booking
     * 
     * @param string $hashed_bc Booking token
     */
    public function submit_review($hashed_bc = null)
    {
        // Set JSON response header
        $this->output->set_content_type('application/json');

        if (empty($hashed_bc)) {
            $this->output->set_output(json_encode([
                'success' => false,
                'message' => 'Invalid booking token'
            ]));
            return;
        }

        // Verify booking exists
        $this->db->select('BookingID, Token, AllowReview, Status');
        $this->db->where('Token', $hashed_bc);
        $this->db->where('Status !=', 'N');
        $booking = $this->db->get('booking')->row_array();

        if (empty($booking)) {
            $this->output->set_output(json_encode([
                'success' => false,
                'message' => 'Booking not found'
            ]));
            return;
        }

        // Check if review is allowed
        if (empty($booking['AllowReview']) || $booking['AllowReview'] == 0) {
            $this->output->set_output(json_encode([
                'success' => false,
                'message' => 'Review submission is not allowed for this booking'
            ]));
            return;
        }

        // Get review text from POST
        $review_text = $this->input->post('review_text');
        
        if (empty($review_text) || trim($review_text) === '') {
            $this->output->set_output(json_encode([
                'success' => false,
                'message' => 'Please enter your review'
            ]));
            return;
        }

        // Update booking with review
        $update_data = [
            'CustomerReview' => trim($review_text),
            'CustomerReviewTimestamp' => date('Y-m-d H:i:s')
        ];

        $this->db->where('BookingID', $booking['BookingID']);
        $result = $this->db->update('booking', $update_data);

        if ($result) {
            $this->output->set_output(json_encode([
                'success' => true,
                'message' => 'Review submitted successfully'
            ]));
        } else {
            $this->output->set_output(json_encode([
                'success' => false,
                'message' => 'Failed to submit review. Please try again.'
            ]));
        }
    }
}

