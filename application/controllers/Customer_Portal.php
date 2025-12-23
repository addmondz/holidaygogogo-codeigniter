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

        // Format pax information for each booking
        foreach ($bookings as &$booking) {
            $booking['PaxInfo'] = $this->format_pax_info($booking['Adult'] ?? 0, $booking['Children'] ?? 0, $booking['Infant'] ?? 0);
        }

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
                          booking.Adult, booking.Children, booking.Infant,
                          category.Name As DestinationName, CountryCode');
        $this->db->from('booking');
        $this->db->join('category', 'category.CategoryID = booking.Destination', 'left');
        $this->db->join('country_code', 'country_code.CountryCodeID = booking.CountryCodeID', 'left');
        $this->db->where('booking.CustomerID', $customer_id);
        $this->db->where('booking.Status !=', 'N');
        $this->db->where('CancelStatus', 'N'); // Always exclude cancelled

        // Apply status filter based on BC stage visibility rules
        if (!empty($status_filter)) {
            if ($status_filter == 'pending') {
                // Pending: Status = 'P' (Pending Payment - before booking confirmation)
                $this->db->where('booking.Status', 'P');
            } elseif ($status_filter == 'confirmed') {
                // Confirmed: Status IN ('PP', 'PTV', 'PT', 'OG') - after booking confirmation
                $this->db->where_in('booking.Status', ['PP', 'PTV', 'PT', 'OG']);
            } elseif ($status_filter == 'completed') {
                // Completed: Status = 'Y' AND AfterSalesService = 'COMPLETE'
                $this->db->where('booking.Status', 'Y');
                $this->db->where('AfterSalesService', 'COMPLETE');
            }
        } else {
            // Default: Show only Confirmed and Completed (hide Pending)
            // Pending (Status = 'P') is hidden from customers
            $this->db->where("(booking.Status IN ('PP', 'PTV', 'PT', 'OG') OR (booking.Status = 'Y' AND AfterSalesService = 'COMPLETE'))", null, false);
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
            '' => 'All Bookings',
            'pending' => 'Pending',
            'confirmed' => 'Confirmed',
            'completed' => 'Completed'
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

        // Get status display info
        $booking['status_display'] = $this->get_booking_status_display($booking);

        // Generate customer hash for back button
        $customer_hash = '';
        if (!empty($customer) && !empty($customer['CustomerCode'])) {
            $customer_hash = generate_customer_portal_hash($customer['CustomerCode']);
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
}

