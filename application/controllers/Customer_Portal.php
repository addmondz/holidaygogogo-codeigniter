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
        $this->load->model('Remark_Model');
        $this->load->model('Notification_Model');
        $this->load->helper('utils');
        $this->load->helper('booking_flow');
        $this->load->library('session');
        $this->config->load('features');
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
        $customer = $this->find_customer_by_slug($hash);
        
        if (!$customer) {
            show_404();
            return;
        }

        // Check phone verification (skip if no phone number on file).
        // Staff (admin_id in session) bypass — they preview from the admin listing
        // and shouldn't need the customer's mobile digits.
        $is_staff = !empty($this->session->userdata('admin_id'));
        if ($this->config->item('enable_phone_verification') && !$is_staff) {
            if (!empty($customer['phone_number']) && !$this->is_customer_verified($customer['CustomerID'])) {
                redirect('customer/' . $hash . '/verify');
                return;
            }
        }

        // Get customer bookings separated into upcoming and completed
        $bookings_data = $this->get_customer_bookings_by_category($customer['CustomerID']);

        // Format pax information and status display for each booking. Pax is sourced
        // from room totals (via Booking_Model->Compute_Pax_Counts) so the portal matches
        // the BC / Travel Voucher the customer receives.
        foreach ($bookings_data['upcoming'] as &$booking) {
            $pax = $this->Booking_Model->Compute_Pax_Counts($booking['BookingID']);
            $booking['PaxInfo'] = $this->format_pax_info($pax['adult'], $pax['child'], $pax['infant']);
            $booking['status_display'] = $this->get_booking_status_display_for_list($booking);
        }
        foreach ($bookings_data['completed'] as &$booking) {
            $pax = $this->Booking_Model->Compute_Pax_Counts($booking['BookingID']);
            $booking['PaxInfo'] = $this->format_pax_info($pax['adult'], $pax['child'], $pax['infant']);
            $booking['status_display'] = $this->get_booking_status_display_for_list($booking);
        }
        foreach ($bookings_data['cancelled'] as &$booking) {
            $pax = $this->Booking_Model->Compute_Pax_Counts($booking['BookingID']);
            $booking['PaxInfo'] = $this->format_pax_info($pax['adult'], $pax['child'], $pax['infant']);
            $booking['status_display'] = $this->get_booking_status_display_for_list($booking);
        }

        // Prepare data for view
        $data = [
            'customer' => $customer,
            'upcoming_bookings' => $bookings_data['upcoming'],
            'completed_bookings' => $bookings_data['completed'],
            'cancelled_bookings' => $bookings_data['cancelled'],
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
     * Phone verification page
     *
     * @param string $hash HMAC hash from URL
     */
    public function verify_phone($hash = null)
    {
        if (empty($hash)) {
            show_404();
            return;
        }

        $customer = $this->find_customer_by_slug($hash);

        if (!$customer) {
            show_404();
            return;
        }

        // Post-verification destination. Accepts `next` from GET (initial landing
        // e.g. scanned QR) or POST (hidden field, survives form submission).
        // Sanitised to prevent open-redirect abuse.
        $next = $this->input->post('next');
        if (empty($next)) {
            $next = $this->input->get('next');
        }
        $next = $this->sanitize_portal_next($next);
        $post_verify_redirect = $next !== null ? $next : base_url('customer/' . $hash);

        // If phone verification is disabled, skip straight to destination
        if (!$this->config->item('enable_phone_verification')) {
            redirect($post_verify_redirect);
            return;
        }

        // No phone number on file or already verified — skip straight to destination
        if (empty($customer['phone_number']) || $this->is_customer_verified($customer['CustomerID'])) {
            redirect($post_verify_redirect);
            return;
        }

        $data = [
            'customer' => $customer,
            'hash' => $hash,
            'error' => null,
            'customer_name' => $customer['name'],
            'next' => $next,
        ];

        // Handle POST submission
        if ($this->input->method() === 'post') {
            $input_digits = $this->input->post('phone_last4');
            $phone_clean = preg_replace('/[^0-9]/', '', $customer['phone_number']);
            $last4 = substr($phone_clean, -4);

            $internal_code = $this->config->item('internal_access_code');
            if ($input_digits === $last4 || ($internal_code && $input_digits === $internal_code)) {
                $this->session->set_userdata('customer_verified_' . $customer['CustomerID'], true);
                redirect($post_verify_redirect);
                return;
            } else {
                $data['error'] = 'Incorrect digits. Please try again.';
            }
        }

        $this->load->view('customer_portal/verify_phone', $data);
    }

    // Only accept portal-internal paths as post-verify redirects. Guards against
    // attackers crafting verify links with off-site `next` URLs.
    private function sanitize_portal_next($next)
    {
        if (empty($next) || !is_string($next)) return null;
        if (strpos($next, '//') === 0) return null;            // protocol-relative
        if (stripos($next, 'javascript:') !== false) return null;
        if (strpos($next, '/customer/') !== 0) return null;    // portal-internal only
        return $next;
    }

    /**
     * Check if a customer has been verified in the current session
     */
    private function is_customer_verified($customer_id)
    {
        return $this->session->userdata('customer_verified_' . $customer_id) === true;
    }

    /**
     * Find customer by matching their name-based slug
     *
     * @param string $slug The slug from URL (e.g. "john-doe-89")
     * @return array|null Customer data if found, null otherwise
     */
    private function find_customer_by_slug($slug)
    {
        // Get all active customers ordered by ID so lower IDs get the 2-digit slug
        $this->db->select('CustomerID, CustomerCode, name, phone_number, ChatLanguage, Status');
        $this->db->where('Status', 'Y');
        $this->db->order_by('CustomerID', 'ASC');
        $customers = $this->db->get('customer')->result_array();

        // Build slug for each customer and detect duplicates
        $slug_map = []; // slug => customer
        foreach ($customers as $customer) {
            if (empty($customer['name']) || empty($customer['phone_number'])) {
                continue;
            }

            $customer_slug = build_customer_slug($customer['name'], $customer['phone_number'], 2);

            if (isset($slug_map[$customer_slug])) {
                // Duplicate: this customer (higher ID) gets 3-digit slug
                $customer_slug = build_customer_slug($customer['name'], $customer['phone_number'], 3);
            }

            $slug_map[$customer_slug] = $customer;
        }

        if (!isset($slug_map[$slug])) {
            return null;
        }

        $customer = $slug_map[$slug];

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
        // Show bookings if BC is currently approved OR was approved at least once before
        $this->db->group_start();
        $this->db->where('booking.bc_approved', 1);
        $this->db->or_where('booking.customer_portal_visible', 1);
        $this->db->group_end();
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
                          booking.PartialRefund,
                          LockStatus, AfterSalesService, booking.Status, booking.InsertDate,
                          booking.Adult, booking.Children, booking.Infant,
                          booking.DepositMode, booking.DepositPercentage, booking.DepositFixedAmount,
                          category.Name As DestinationName, CountryCode');
        $this->db->from('booking');
        $this->db->join('category', 'category.CategoryID = booking.Destination', 'left');
        $this->db->join('country_code', 'country_code.CountryCodeID = booking.CountryCodeID', 'left');
        $this->db->where('booking.CustomerID', $customer_id);
        $this->db->where('booking.Status !=', 'N');
        // CancelStatus filter removed - cancelled bookings now shown in their own tab
        // Show bookings if BC is currently approved OR was approved at least once before
        $this->db->group_start();
        $this->db->where('booking.bc_approved', 1);
        $this->db->or_where('booking.customer_portal_visible', 1);
        $this->db->group_end();
        $this->db->order_by('booking.StartDate', 'DESC');
        $this->db->order_by('booking.BookingID', 'DESC');

        $all_bookings = $this->db->get()->result_array();

        // Compute total_paid per booking in one grouped query (approved customer credits only)
        $paid_by_booking = [];
        if (!empty($all_bookings)) {
            $booking_ids = array_column($all_bookings, 'BookingID');
            $this->db->select('BookingID, SUM(Credit) As TotalPaid');
            $this->db->from('payment');
            $this->db->where_in('BookingID', $booking_ids);
            $this->db->where('Status', 'Y');
            $this->db->where('Credit >', 0);
            $this->db->where_not_in('Type', array('SUPPLIER PAYMENT (DEPOSIT)', 'SUPPLIER PAYMENT (FULL)', 'SUPPLIER PAYMENT (ADDITIONAL)', 'AGENT COMMISSION FROM SUPPLIER'));
            $this->db->group_by('BookingID');
            foreach ($this->db->get()->result_array() as $row) {
                $paid_by_booking[$row['BookingID']] = floatval($row['TotalPaid']);
            }
        }
        
        $upcoming = [];
        $completed = [];
        $cancelled = [];

        $today = date('Y-m-d');

        foreach ($all_bookings as $booking) {
            // Attach payment-derived fields used by status display
            $total_paid = isset($paid_by_booking[$booking['BookingID']]) ? $paid_by_booking[$booking['BookingID']] : 0;
            $net_total = floatval($booking['NetTotal']);
            $deposit_mode = !empty($booking['DepositMode']) ? $booking['DepositMode'] : 'percentage';
            if ($deposit_mode == 'fixed') {
                $deposit_total = isset($booking['DepositFixedAmount']) ? floatval($booking['DepositFixedAmount']) : 0;
            } else {
                $deposit_percentage = isset($booking['DepositPercentage']) ? floatval($booking['DepositPercentage']) : 0;
                $deposit_total = ceil(($net_total * $deposit_percentage) / 100);
            }
            $booking['total_paid'] = $total_paid;
            $booking['balance_due'] = $net_total - $total_paid;
            $has_deposit_deadline = !empty($booking['DepositDeadline']);
            $booking['deposit_complete'] = compute_deposit_complete($deposit_total, $total_paid, $has_deposit_deadline);

            if ($booking['CancelStatus'] == 'Y' || (isset($booking['PartialRefund']) && $booking['PartialRefund'] == 'Y')) {
                $cancelled[] = $booking;
                continue;
            }

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
            'completed' => $completed,
            'cancelled' => $cancelled
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
                          StartDate, EndDate, Adult, Children, Infant, BookingRemark, Subtotal, Discount, NetTotal, DepositPercentage, DepositMode, DepositFixedAmount,
                          booking.ChatLanguage, Token, booking.BookingConfirmationTitle, CancelStatus, LockStatus, 
                          AfterSalesService, booking.Status, booking.InsertDate, booking.UpdateDate, booking.CustomerID,
                          booking.AllowReview, booking.CustomerReview, booking.CustomerReviewTimestamp,
                          booking.bc_approved, booking.bc_approval_admin_id, booking.bc_approval_date,
                          category.Name As DestinationName, CountryCode, admin.Name As SalesAgentName, admin.Mobile As SalesAgentMobile');
        $this->db->from('booking');
        $this->db->join('category', 'category.CategoryID = booking.Destination', 'left');
        $this->db->join('country_code', 'country_code.CountryCodeID = booking.CountryCodeID', 'left');
        $this->db->join('admin', 'admin.AdminID = booking.SalesAgent', 'left');
        $this->db->where('booking.Token', $hashed_bc);
        $this->db->where('booking.Status !=', 'N');
        // Customer-facing visibility: only show approved bookings to customers.
        // Logged-in staff (admin_id in session) can preview any booking regardless
        // of approval state — used by the admin booking listing's "Go to Booking Page" link.
        $is_staff = !empty($this->session->userdata('admin_id'));
        if (!$is_staff) {
            $this->db->group_start();
            $this->db->where('booking.bc_approved', 1);
            $this->db->or_where('booking.customer_portal_visible', 1);
            $this->db->group_end();
        }
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

        // Check phone verification for booking details (skip if no phone number on file).
        // Pass the booking URL as `next` so the user returns here after verifying
        // instead of landing on the customer dashboard. Staff (admin_id in session)
        // bypass verification — they don't need the customer's mobile digits to preview.
        if ($this->config->item('enable_phone_verification') && !$is_staff) {
            if ($customer && !empty($customer['phone_number']) && !$this->is_customer_verified($customer['CustomerID'])) {
                $customer_hash = generate_customer_portal_slug($customer['CustomerID']);
                $next_url = '/customer/booking/' . $hashed_bc;
                redirect('customer/' . $customer_hash . '/verify?next=' . urlencode($next_url));
                return;
            }
        }

        // Store raw dates before formatting (for timeline calculations)
        $booking['StartDateRaw'] = !empty($booking['StartDate']) ? $booking['StartDate'] : null;
        $booking['EndDateRaw'] = !empty($booking['EndDate']) ? $booking['EndDate'] : null;
        $booking['DepositDeadlineRaw'] = !empty($booking['DepositDeadline']) ? $booking['DepositDeadline'] : null;
        $booking['FullPaymentDeadlineRaw'] = !empty($booking['FullPaymentDeadline']) ? $booking['FullPaymentDeadline'] : null;
        $booking['AdditionalPaymentDeadlineRaw'] = !empty($booking['AdditionalPaymentDeadline']) ? $booking['AdditionalPaymentDeadline'] : null;
        $booking['InsertDateRaw'] = !empty($booking['InsertDate']) ? $booking['InsertDate'] : null;

        // Format booking data
        $booking['DepositDeadline'] = !empty($booking['DepositDeadline']) ? date('d M Y', strtotime($booking['DepositDeadline'])) : null;
        $booking['FullPaymentDeadline'] = !empty($booking['FullPaymentDeadline']) ? date('d M Y', strtotime($booking['FullPaymentDeadline'])) : null;
        $booking['AdditionalPaymentDeadline'] = !empty($booking['AdditionalPaymentDeadline']) ? date('d M Y', strtotime($booking['AdditionalPaymentDeadline'])) : null;
        $booking['StartDate'] = !empty($booking['StartDate']) ? date('d M Y', strtotime($booking['StartDate'])) : null;
        $booking['EndDate'] = !empty($booking['EndDate']) ? date('d M Y', strtotime($booking['EndDate'])) : null;
        $booking['InsertDate'] = !empty($booking['InsertDate']) ? date('d M Y', strtotime($booking['InsertDate'])) : null;
        $booking['CustomerMobile'] = $booking['CountryCode'] . $booking['CustomerMobile'];
        $pax = $this->Booking_Model->Compute_Pax_Counts($booking['BookingID']);
        $booking['PaxInfo'] = $this->format_pax_info($pax['adult'], $pax['child'], $pax['infant']);
        $booking['ComputedPaxTotal'] = (int)$pax['adult'] + (int)$pax['child'] + (int)$pax['infant'];

        // Get booking products
        $this->db->select('*');
        $this->db->where('BookingID', $booking['BookingID']);
        $this->db->where('Status', 'Y');
        $this->db->order_by('BookingProductID', 'ASC');
        $booking['products'] = $this->db->get('booking_product')->result_array();

        // Get invoice split data
        $this->load->model('Invoice_Split_Model');
        $booking['invoice_split'] = $this->Invoice_Split_Model->Get_Pax_By_Booking($booking['BookingID']);
        $booking['einvoice_submit_status'] = $this->Invoice_Split_Model->Get_Submit_Status($booking['BookingID']);

        // Get payment history (customer-facing Types only)
        $this->db->select('payment.PaymentID, Date, Type, Credit, ReferenceNumber, Debit, Deadline, payment.Status, PaymentRemark, DebitRemark, payment.Bank, payment.BankAccount, payment.BankHolder, supplier.Name As SupplierName');
        $this->db->from('payment');
        $this->db->join('supplier', 'supplier.SupplierID = payment.SupplierID', 'left');
        $this->db->where('payment.BookingID', $booking['BookingID']);
        $this->db->where('payment.Status !=', 'N');
        $this->db->where_in('payment.Type', array('DEPOSIT', 'FULL', 'ADDITIONAL PAYMENT', 'CUSTOMER REFUND'));
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
            if ($payment['Status'] == 'Y') {
                if (!empty($payment['Credit']) && $payment['Credit'] > 0 && $payment['Type'] != 'AGENT COMMISSION FROM SUPPLIER') {
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

        // Deposit completion (mirrors booking_details.php deposit_complete rule)
        $deposit_mode = !empty($booking['DepositMode']) ? $booking['DepositMode'] : 'percentage';
        if ($deposit_mode == 'fixed') {
            $deposit_total_required = isset($booking['DepositFixedAmount']) ? floatval($booking['DepositFixedAmount']) : 0;
        } else {
            $deposit_percentage = isset($booking['DepositPercentage']) ? floatval($booking['DepositPercentage']) : 0;
            $deposit_total_required = ceil((floatval($booking['NetTotal']) * $deposit_percentage) / 100);
        }
        $has_deposit_deadline_detail = !empty($booking['DepositDeadline']);
        $booking['deposit_complete'] = compute_deposit_complete($deposit_total_required, $total_credit, $has_deposit_deadline_detail);

        // Prepare document URLs
        $base_url = base_url();
        $booking['documents'] = [
            'bc' => [
                'name' => 'Booking Confirmation',
                'url' => $base_url . 'Booking_Confirmation?token=' . $hashed_bc,
                'icon' => 'file-text',
                'available' => true
            ],
            'gl' => [
                'name' => 'Guest List',
                'url' => $base_url . 'Guest_List?gl=' . $hashed_bc,
                'icon' => 'users',
                'available' => true
            ]
        ];
        
        // Only show Travel Voucher if guest list is submitted (LockStatus = 'Y') AND travel voucher has been sent (Status = 'PT' or later)
        $guest_list_submitted = !empty($booking['LockStatus']) && $booking['LockStatus'] == 'Y';
        $travel_voucher_sent = in_array($booking['Status'], array('PT', 'OG', 'Y', 'PR'));
        
        if ($guest_list_submitted && $travel_voucher_sent) {
            $booking['documents']['tv'] = [
                'name' => 'Travel Voucher',
                'url' => $base_url . 'Travel_Voucher?token=' . $hashed_bc,
                'icon' => 'plane',
                'available' => true
            ];
        }

        // Only show Official Receipt if booking is completed (Status = 'Y') AND guest list is submitted (LockStatus = 'Y')
        if($booking['Status'] == 'Y' && !empty($booking['LockStatus']) && $booking['LockStatus'] == 'Y') {
            $booking['documents']['or'] = [
                'name' => 'Official Receipt',
                'url' => $base_url . 'Receipt?token=' . $hashed_bc,
                'icon' => 'receipt',
                'available' => true
            ];
        }

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

        // Get booking status logs to find dates for timeline events
        $this->load->model('Booking_Status_Log_Model');
        $status_logs = $this->Booking_Status_Log_Model->get_by_booking_id($booking['BookingID'], true);
        
        // Use bc_approval_date from database if available
        // Otherwise, find BC approval date from status logs (when status changed from PBC to P or when BC was approved)
        if (empty($booking['bc_approval_date'])) {
            $bc_approval_date = null;
            foreach ($status_logs as $log) {
                if ($log->from_status == 'PBC' && ($log->to_status == 'P' || $log->to_status == 'PBO')) {
                    $bc_approval_date = $log->created_at;
                    break;
                }
                if (stripos($log->description, 'BC Approved') !== false) {
                    $bc_approval_date = $log->created_at;
                    break;
                }
            }
            // Final fallback to InsertDate
            if (empty($bc_approval_date)) {
                $bc_approval_date = $booking['InsertDateRaw'];
            }
            $booking['bc_approval_date'] = $bc_approval_date;
        }
        
        // Find status change dates for timeline
        $status_change_dates = [];
        foreach ($status_logs as $log) {
            if (!empty($log->to_status)) {
                $status_change_dates[$log->to_status] = $log->created_at;
            }
        }
        $booking['status_change_dates'] = $status_change_dates;

        // Generate customer hash for back button
        $customer_hash = '';
        if (!empty($customer) && !empty($customer['CustomerID'])) {
            $customer_hash = generate_customer_portal_slug($customer['CustomerID']);
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
        $today = date('Y-m-d');

        if ($cancel_status == 'Y') {
            return [
                'text' => 'Cancelled',
                'class' => 'status-cancelled',
                'color' => '#E0115F'
            ];
        }

        // Check for payment overdue status (only for pending/partial payment statuses)
        if (in_array($status, ['P', 'PP'])) {
            $has_deposit_deadline = !empty($booking['DepositDeadlineRaw']);
            $has_full_payment_deadline = !empty($booking['FullPaymentDeadlineRaw']);
            $balance_due = isset($booking['balance_due']) ? $booking['balance_due'] : $booking['NetTotal'];
            $deposit_complete = !empty($booking['deposit_complete']);

            // Check if payment is overdue
            $is_payment_overdue = false;

            if ($has_deposit_deadline && !$deposit_complete) {
                // Deposit deadline passed and deposit requirement not yet met
                $deposit_deadline = date('Y-m-d', strtotime($booking['DepositDeadlineRaw']));
                if ($today > $deposit_deadline && $balance_due > 0) {
                    $is_payment_overdue = true;
                }
            }
            
            if ($has_full_payment_deadline && !$is_payment_overdue) {
                // Check full payment deadline
                $full_payment_deadline = date('Y-m-d', strtotime($booking['FullPaymentDeadlineRaw']));
                if ($today > $full_payment_deadline && $balance_due > 0) {
                    $is_payment_overdue = true;
                }
            }
            
            if ($is_payment_overdue) {
                return [
                    'text' => 'Payment Overdue',
                    'class' => 'status-overdue',
                    'color' => '#DC3545'
                ];
            }
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
        } elseif (in_array($status, ['PP', 'PBO', 'PGL', 'PTV', 'PT', 'OG'])) {
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
     * Get booking status display information for list view (dashboard)
     * Simplified version that doesn't require payment data
     * 
     * @param array $booking
     * @return array
     */
    private function get_booking_status_display_for_list($booking)
    {
        $status = $booking['Status'];
        $cancel_status = $booking['CancelStatus'] ?? 'N';
        $partial_refund = $booking['PartialRefund'] ?? 'N';
        $after_sales = $booking['AfterSalesService'] ?? '';
        $today = date('Y-m-d');

        if ($cancel_status == 'Y') {
            return [
                'text' => 'Cancelled',
                'class' => 'status-cancelled',
                'color' => '#E0115F'
            ];
        }

        if ($partial_refund == 'Y') {
            return [
                'text' => 'Partial Refund',
                'class' => 'status-cancelled',
                'color' => '#E0115F'
            ];
        }

        // Check if travel date has passed
        $travel_end_date = !empty($booking['EndDate']) ? $booking['EndDate'] : $booking['StartDate'];
        $travel_date_passed = false;
        if (!empty($travel_end_date)) {
            $travel_date = date('Y-m-d', strtotime($travel_end_date));
            $travel_date_passed = $travel_date < $today;
        }

        // Check for payment overdue status (only for pending/partial payment statuses)
        if (in_array($status, ['P', 'PP'])) {
            $has_deposit_deadline = !empty($booking['DepositDeadline']);
            $has_full_payment_deadline = !empty($booking['FullPaymentDeadline']);
            $deposit_complete = !empty($booking['deposit_complete']);
            $balance_due = isset($booking['balance_due']) ? floatval($booking['balance_due']) : floatval($booking['NetTotal']);

            // Check if payment is overdue
            $is_payment_overdue = false;

            if ($has_deposit_deadline && !$deposit_complete) {
                $deposit_deadline = date('Y-m-d', strtotime($booking['DepositDeadline']));
                if ($today > $deposit_deadline) {
                    $is_payment_overdue = true;
                }
            }

            if ($has_full_payment_deadline && !$is_payment_overdue && $balance_due > 0) {
                $full_payment_deadline = date('Y-m-d', strtotime($booking['FullPaymentDeadline']));
                if ($today > $full_payment_deadline) {
                    $is_payment_overdue = true;
                }
            }
            
            if ($is_payment_overdue) {
                return [
                    'text' => 'Payment Overdue',
                    'class' => 'status-overdue',
                    'color' => '#DC3545'
                ];
            }
        }

        // Completed: Status = 'Y' AND AfterSalesService = 'COMPLETE' OR travel date passed
        if (($status == 'Y' && $after_sales == 'COMPLETE') || $travel_date_passed) {
            return [
                'text' => 'Completed',
                'class' => 'status-completed',
                'color' => '#50C878'
            ];
        } elseif (in_array($status, ['PP', 'PBO', 'PGL', 'PTV', 'PT', 'OG'])) {
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
        $this->db->select('BookingID, Token, AllowReview, CustomerReview, Status, Customer, SalesAgent, BookingOP');
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

        // Reviews are view-only after submission
        if (!empty($booking['CustomerReview'])) {
            $this->output->set_output(json_encode([
                'success' => false,
                'message' => 'A review has already been submitted for this booking'
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
            // Notify SalesAgent (TC) and BookingOP that a review was submitted
            if (!empty($booking['SalesAgent']) || !empty($booking['BookingOP'])) {
                $this->Notification_Model->Create_Review_Submitted_Notification(
                    $booking['BookingID'],
                    $booking['Customer']
                );
            }

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

    /**
     * Get customer remarks for a booking (AJAX)
     * 
     * @param string $hashed_bc Booking token
     */
    public function get_customer_remarks($hashed_bc = null)
    {
        $this->output->set_content_type('application/json');

        if (empty($hashed_bc)) {
            $this->output->set_output(json_encode([
                'success' => false,
                'message' => 'Invalid booking token'
            ]));
            return;
        }

        // Verify booking exists
        $this->db->select('BookingID');
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

        // Get customer remarks (type 2)
        $remarks = $this->Remark_Model->Read_Remarks('booking', $booking['BookingID'], REMARK_TYPE::CUSTOMER);
        
        // Format remarks for JSON response
        $formatted_remarks = array();
        foreach ($remarks as $remark) {
            // Determine commenter name - if CommenterName exists, it's an admin, otherwise it's the customer
            $commenter_name = 'Customer';
            $initials = '';
            
            if (!empty($remark->CommenterName)) {
                // Admin created this remark - use admin name from join
                $commenter_name = $remark->CommenterName;
            } else {
                // Customer created this remark - get customer name from booking
                $this->db->select('Customer');
                $this->db->where('BookingID', $booking['BookingID']);
                $booking_info = $this->db->get('booking')->row();
                $commenter_name = !empty($booking_info) ? $booking_info->Customer : 'Customer';
            }
            
            // Get initials for avatar
            if (!empty($commenter_name)) {
                $name_parts = explode(' ', $commenter_name);
                if (count($name_parts) >= 2) {
                    $initials = strtoupper(substr($name_parts[0], 0, 1) . substr($name_parts[count($name_parts) - 1], 0, 1));
                } else {
                    $initials = strtoupper(substr($commenter_name, 0, 2));
                }
            }
            
            $formatted_remarks[] = array(
                'RemarkID' => $remark->RemarkID,
                'content' => $remark->content,
                'commenter_name' => $commenter_name,
                'commenter_initials' => $initials,
                'created_at' => return_timestamp_output($remark->created_at),
                'created_at_relative' => time_ago($remark->created_at),
                'created_at_raw' => $remark->created_at
            );
        }

        $this->output->set_output(json_encode([
            'success' => true,
            'remarks' => $formatted_remarks
        ]));
    }

    /**
     * Add customer remark (AJAX)
     * 
     * @param string $hashed_bc Booking token
     */
    public function add_customer_remark($hashed_bc = null)
    {
        $this->output->set_content_type('application/json');

        if (empty($hashed_bc)) {
            $this->output->set_output(json_encode([
                'success' => false,
                'message' => 'Invalid booking token'
            ]));
            return;
        }

        $content = trim($this->input->post('content'));

        if (empty($content)) {
            $this->output->set_output(json_encode([
                'success' => false,
                'message' => 'Comment content is required'
            ]));
            return;
        }

        // Verify booking exists and get details
        $this->db->select('BookingID, Customer, SalesAgent, BookingOP');
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

        // Create remark with type 2 (CUSTOMER)
        $remark_data = array(
            'owner_type' => 'booking',
            'owner_id' => $booking['BookingID'],
            'commenter_id' => 0, // Customer comments don't have admin ID
            'content' => $content,
            'type' => REMARK_TYPE::CUSTOMER
        );

        $remark_id = $this->Remark_Model->Create($remark_data);

        if ($remark_id) {
            // Create notification for Sales Agent and BookingOP
            if (!empty($booking['SalesAgent']) || !empty($booking['BookingOP'])) {
                $this->Notification_Model->Create_Customer_Remark_Notification(
                    $booking['BookingID'],
                    $remark_id,
                    $booking['SalesAgent'],
                    $booking['Customer'],
                    $content,
                    $booking['BookingOP']
                );
            }

            // Get the newly created remark
            $remark = $this->Remark_Model->Read_Remark($remark_id);
            
            // Get customer name
            $customer_name = $booking['Customer'];
            $name_parts = explode(' ', $customer_name);
            $initials = count($name_parts) >= 2 
                ? strtoupper(substr($name_parts[0], 0, 1) . substr($name_parts[count($name_parts) - 1], 0, 1))
                : strtoupper(substr($customer_name, 0, 2));
            
            $this->output->set_output(json_encode([
                'success' => true,
                'message' => 'Comment added successfully',
                'remark' => array(
                    'RemarkID' => $remark->RemarkID,
                    'content' => $remark->content,
                    'commenter_name' => $customer_name,
                    'commenter_initials' => $initials,
                    'created_at' => date('d/m/Y H:i:s', strtotime($remark->created_at)),
                    'created_at_raw' => $remark->created_at
                )
            ]));
        } else {
            $this->output->set_output(json_encode([
                'success' => false,
                'message' => 'Failed to add comment. Please try again.'
            ]));
        }
    }

    /**
     * Helper function to calculate time ago
     */
    private function time_ago($datetime)
    {
        $timestamp = strtotime($datetime);
        $diff = time() - $timestamp;

        if ($diff < 60) {
            return 'just now';
        } elseif ($diff < 3600) {
            $mins = floor($diff / 60);
            return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 604800) {
            $days = floor($diff / 86400);
            return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
        } else {
            return date('d/m/Y H:i', $timestamp);
        }
    }

    /**
     * Get invoice split data for a booking (AJAX)
     */
    public function get_invoice_split($hashed_bc = null)
    {
        $this->output->set_content_type('application/json');

        if (empty($hashed_bc)) {
            $this->output->set_output(json_encode(['success' => false, 'message' => 'Invalid booking token']));
            return;
        }

        $this->db->select('BookingID');
        $this->db->where('Token', $hashed_bc);
        $this->db->where('Status !=', 'N');
        $booking = $this->db->get('booking')->row_array();

        if (empty($booking)) {
            $this->output->set_output(json_encode(['success' => false, 'message' => 'Booking not found']));
            return;
        }

        $this->load->model('Invoice_Split_Model');
        $pax = $this->Invoice_Split_Model->Get_Pax_By_Booking($booking['BookingID']);

        $this->output->set_output(json_encode(['success' => true, 'pax' => $pax]));
    }

    /**
     * Save invoice split data as a DRAFT (AJAX).
     * Customer can continue to edit after save.
     */
    public function save_invoice_split($hashed_bc = null)
    {
        $this->_persist_invoice_split($hashed_bc, 'D');
    }

    /**
     * Submit invoice split data (AJAX). Rejects if already submitted.
     * Once submitted, the customer-portal form is locked.
     */
    public function submit_invoice_split($hashed_bc = null)
    {
        $this->output->set_content_type('application/json');

        if (!empty($hashed_bc)) {
            $this->db->select('BookingID');
            $this->db->where('Token', $hashed_bc);
            $this->db->where('Status !=', 'N');
            $existing = $this->db->get('booking')->row_array();
            if (!empty($existing)) {
                $this->load->model('Invoice_Split_Model');
                if ($this->Invoice_Split_Model->Get_Submit_Status($existing['BookingID']) === 'S') {
                    $this->output->set_output(json_encode([
                        'success' => false,
                        'message' => 'This e-invoice request has already been submitted and cannot be changed.'
                    ]));
                    return;
                }
            }
        }

        $this->_persist_invoice_split($hashed_bc, 'S');
    }

    /**
     * Shared implementation for save_invoice_split (draft) and submit_invoice_split.
     * Applies the same validation rules regardless of submit status.
     */
    private function _persist_invoice_split($hashed_bc, $submit_status)
    {
        $this->output->set_content_type('application/json');

        if (empty($hashed_bc)) {
            $this->output->set_output(json_encode(['success' => false, 'message' => 'Invalid booking token']));
            return;
        }

        // Verify booking
        $this->db->select('BookingID, Subtotal, Discount, NetTotal');
        $this->db->where('Token', $hashed_bc);
        $this->db->where('Status !=', 'N');
        $booking = $this->db->get('booking')->row_array();

        if (empty($booking)) {
            $this->output->set_output(json_encode(['success' => false, 'message' => 'Booking not found']));
            return;
        }

        // Parse POST body and run the shared validator. Draft saves allow
        // partial allocation; full submits require every booking_product to
        // be fully allocated across pax.
        $json = $this->input->raw_input_stream;
        $data = json_decode($json, true);

        $this->load->model('Invoice_Split_Model');
        $validation = $this->Invoice_Split_Model->Validate_Pax_Input(
            $data,
            $booking['BookingID'],
            $submit_status === 'S'
        );
        if (!$validation['ok']) {
            $this->output->set_output(json_encode(['success' => false, 'message' => $validation['message']]));
            return;
        }
        $pax_data = $validation['pax_data'];

        $booking_subtotal = floatval($booking['Subtotal']);
        $booking_discount = floatval($booking['Discount']);

        $result = $this->Invoice_Split_Model->Save_Split(
            $booking['BookingID'],
            $pax_data,
            $booking_subtotal,
            $booking_discount,
            $submit_status
        );

        if ($result) {
            $pax = $this->Invoice_Split_Model->Get_Pax_By_Booking($booking['BookingID']);
            $message = ($submit_status === 'S')
                ? 'E-Invoice request submitted successfully'
                : 'E-Invoice request saved as draft';

            // Notify finance only on Submit, not on Draft. Send is best-effort:
            // failures are logged but never surfaced to the customer, so a
            // mail-provider outage cannot break the submission flow.
            if ($submit_status === 'S') {
                $this->Invoice_Split_Model->Send_Finance_Notification($booking['BookingID']);
            }

            $this->output->set_output(json_encode([
                'success' => true,
                'message' => $message,
                'submit_status' => $submit_status,
                'pax' => $pax
            ]));
        } else {
            $this->output->set_output(json_encode([
                'success' => false,
                'message' => 'Failed to save invoice split'
            ]));
        }
    }

}
