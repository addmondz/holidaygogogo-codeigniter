<?php

require FCPATH.'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;  
use PhpOffice\PhpSpreadsheet\Writer\Xlxs;

class Booking extends MY_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->load->model('Booking_Model');
		$this->load->model('Booking_Product_Model');
		$this->load->model('Payment_Model');
		$this->load->model('Universal_Model');
		$this->load->model('Customer_Model');
		$this->load->model('Remark_Model');
		$this->load->model('Notification_Model');
		$this->load->model('Booking_Checklist_Completion_Model');
		$this->load->model('Product_Package_Checklist_Model');
		$this->load->model('Package_Checklist_Model');
		$this->load->model('Guest_list_lock_model');
		$this->load->model('Cancellation_Reason_Model');
		$this->load->model('Customer_Type_Model');
		$this->load->model('Quick_Filter_Model');
		$this->load->model('Booking_Supplier_Invoice_Model');
		$this->config->load('autocount'); // load config/autocount.php
	}

	function index()
	{
		$startTime = date("Y-m-d H:i:s.u");
		if(in_array('VB', $this->session->access_control)) {
			$titles = array('tab_title' => 'HolidayGoGoGo | Booking', 'breadcrumb_title' => 'Booking');
			$array = array();

			// Load filter dropdowns data
			$array['admins'] = $this->Booking_Model->Read_Admins();
			$array['notify_admins'] = $this->Notification_Model->Build_Admin_Handles($this->Booking_Model->Read_Notify_Admins());
			$array['booking_op_admins'] = $this->Booking_Model->Read_Booking_OP_Admins();
			$array['categories'] = $this->Booking_Model->Read_Categories();
			$array['tags'] = $this->Booking_Model->Read_Tags();
			$array['sources'] = $this->Booking_Model->Read_Sources();
			$array['customer_types'] = $this->Customer_Type_Model->Read_Customer_Types();
			$array['filter_checklists'] = $this->Package_Checklist_Model->Read_Booking_Filter_Checklists();
			$array['cancellation_reasons'] = $this->Cancellation_Reason_Model->Read_Cancellation_Reasons();
			$array['quick_filters'] = $this->Quick_Filter_Model->Read_Quick_Filters();

			// Hydrate filter values from query string for the shared filter partial
			$filter_field_names = [
				'customer', 'booking_number', 'reservation_number', 'mobile',
				'destination', 'travel_date', 'deadline', 'source',
				'chat_language', 'booking_date', 'status', 'booking_confirmation_title',
				'tag', 'customer_type', 'sales_agent', 'sales_agent_2', 'booking_op',
				'autocount_status', 'guest_list_status', 'checklist_filter',
				'cancellation_reason', 'einvoice_status',
			];
			$filter_values = [];
			foreach($filter_field_names as $fname) {
				$filter_values[$fname] = $this->input->get($fname);
			}
			$array['filter_values'] = $filter_values;

			$this->load->helper('autocount');
			$config = get_autocount_config();

			$array['bulkBookingSyncToAutocount'] = !empty($config['bulkBookingSyncToAutocount']) ? $config['bulkBookingSyncToAutocount'] : false;

			// Load status log helper
			$this->load->helper('booking_status_log');
			
			// Load utils helper for date formatting
			$this->load->helper('utils');
			
			// Update status for all bookings (this runs before the AJAX calls)
			$bookings = $this->Booking_Model->Read_All_Bookings();
			foreach($bookings as $booking) {
				if((date('Y-m-d') >= $booking->StartDate && date('Y-m-d') <= $booking->EndDate) && ($booking->Status == 'PT')) {
					$this->Booking_Model->Update_After_Sales_Service2($booking->BookingID);
					$this->Booking_Model->Update_Status('OG', $booking->BookingID);
					$this->Booking_Model->Create_Booking_Log2($booking->Status, 'OG', $booking->BookingID);
					log_booking_automatic_status_change($booking->BookingID, $booking->Status, 'OG', "Travel date reached - booking is now on-going", true);
				} else {
					if((date('Y-m-d') < $booking->StartDate && ($booking->Status == 'OG'))) {
						$this->Booking_Model->Update_After_Sales_Service2($booking->BookingID);
						$this->Booking_Model->Update_Status('PT', $booking->BookingID);
						$this->Booking_Model->Create_Booking_Log2($booking->Status, 'PT', $booking->BookingID);
						log_booking_automatic_status_change($booking->BookingID, $booking->Status, 'PT', "Travel date not yet reached - status reverted to pending travel", true);
					} else {
						if((date('Y-m-d') > $booking->EndDate && ($booking->Status == 'PT' || $booking->Status == 'OG'))) {
							$this->Booking_Model->Update_After_Sales_Service2($booking->BookingID);
							$this->Booking_Model->Update_Status('Y', $booking->BookingID);
							$this->Booking_Model->Create_Booking_Log2($booking->Status, 'Y', $booking->BookingID);
							log_booking_automatic_status_change($booking->BookingID, $booking->Status, 'Y', "Travel completed - booking marked as completed", true);
						}
					}
				}

				// Payment-based status transitions (respecting new flow)
				// Note: Payment approval status history is now handled in Payment controller
				// when payment status is changed to 'Y' (approved)
				$payments = $this->Booking_Model->Read_Payments($booking->BookingID);
				if(empty($payments)) {
					// No payments at all, set to PBC if not already in flow
					if($booking->Status != 'PBC' && $booking->Status != 'P') {
						// Only reset if not already in a recognised flow status. SAD
						// (Save as Draft) is the customer-intake draft anchor and PB
						// (Pending BC) is an early flow status — neither must be
						// silently flipped to PBC by this sweep.
						if(!in_array($booking->Status, ['PBC', 'PB', 'P', 'PBO', 'PTV', 'PT', 'Y', 'OG', 'SAD'])) {
							$this->Booking_Model->Update_Status('PBC', $booking->BookingID);
							$this->Booking_Model->Create_Booking_Log2($booking->Status, 'PBC', $booking->BookingID);
						}
					}
				}
			}

			// Bookings data is now loaded via AJAX (ajax_list method)
			// Summary totals are loaded via AJAX (ajax_summary method)

			$this->load->view('layout/header', $titles);
			$this->load->view('booking/index', $array);
			$this->load->view('layout/footer');

		} else {
			redirect('Dashboard');
		}
	}

	/**
	 * AJAX endpoint for DataTables server-side processing
	 * Returns paginated booking data as JSON
	 */
	function ajax_list()
	{
		// Set JSON header first to prevent any output issues
		header('Content-Type: application/json');
		
		try {
			// Ensure access_control is an array to prevent warnings
			$access_control = $this->session->access_control ?? array();
			if(!in_array('VB', $access_control)) {
				echo json_encode(array('error' => 'Access denied'));
				return;
			}

			$is_sales_agent = $this->session->userdata('level') == 20;

		$this->load->helper('booking_flow');

		// DataTables parameters
		$draw = intval($this->input->get('draw'));
		$start = intval($this->input->get('start'));
		$length = intval($this->input->get('length'));

		// Order parameters
		$order_column_index = $this->input->get('order[0][column]');
		$order_dir = $this->input->get('order[0][dir]') == 'asc' ? 'ASC' : 'DESC';

		// Map column index to database column
		// Note: Column indices must match the frontend DataTables columns array.
		// Sales agents (level 20) do NOT see the Net Profit / Net Profit Margin columns,
		// so the indices for BC Status onwards shift left by 2 for them.
		if($is_sales_agent) {
			$columns = array(
				0 => 'booking.BookingID',             // row number
				1 => 'booking.BookingID',             // checkbox (placeholder)
				2 => 'admin.Name',                    // sales agent
				3 => 'sa2_admin.Name',                // sales agent 2
				4 => 'op_admin.Name',                 // OP
				5 => 'booking.InsertDate',            // creation date
				6 => 'BookingNumber',                 // BC number
				7 => 'booking.BookingConfirmationTitle', // BC title
				8 => 'Customer',                      // customer
				9 => 'source.Name',                   // source
				10 => 'booking.ChatLanguage',          // chat
				11 => 'booking.Mobile',               // mobile
				12 => 'StartDate',                    // start
				13 => 'EndDate',                      // end
				14 => 'category.Name',                // destination
				15 => 'NetTotal',                     // net sales
				16 => "status_sort_priority",          // BC status
				17 => 'LockStatus',                   // GL status
				18 => 'booking.AutocountSyncStatus',  // autocount status
				19 => 'booking.BookingID'             // action
			);
		} else {
			$columns = array(
				0 => 'booking.BookingID',             // row number
				1 => 'booking.BookingID',             // checkbox (placeholder)
				2 => 'admin.Name',                    // sales agent
				3 => 'sa2_admin.Name',                // sales agent 2
				4 => 'op_admin.Name',                 // OP
				5 => 'booking.InsertDate',            // creation date
				6 => 'BookingNumber',                 // BC number
				7 => 'booking.BookingConfirmationTitle', // BC title
				8 => 'Customer',                      // customer
				9 => 'source.Name',                   // source
				10 => 'booking.ChatLanguage',          // chat
				11 => 'booking.Mobile',               // mobile
				12 => 'StartDate',                    // start
				13 => 'EndDate',                      // end
				14 => 'category.Name',                // destination
				15 => 'NetTotal',                     // net sales
				16 => 'NetTotal',                     // profit
				17 => 'NetTotal',                     // profit margin
				18 => "status_sort_priority",          // BC status
				19 => 'LockStatus',                   // GL status
				20 => 'booking.AutocountSyncStatus',  // autocount status
				21 => 'booking.BookingID'             // action
			);
		}

		$order_column = isset($columns[$order_column_index]) ? $columns[$order_column_index] : 'booking.BookingID';

		// Get counts
		$records_total = $this->Booking_Model->Count_Bookings_Total();
		$records_filtered = $this->Booking_Model->Count_Bookings_Filtered();

		// Get paginated data
		$bookings = $this->Booking_Model->Read_Bookings_Paginated($start, $length, $order_column, $order_dir);

		// Process bookings for display
		$data = array();
		$count = $start + 1;
		$current_url = base_url($_SERVER['REQUEST_URI']);

		foreach($bookings as $booking) {
			// SA-as-TC2 gating: when current SA is only the TC2 (SalesAgent2) of this
			// booking, hide BC link, GL actions, and Customer actions in the row.
			$sa_as_tc2 = is_sa_acting_as_tc2(
				$this->session->userdata('level'),
				$this->session->userdata('admin_id'),
				$booking->SalesAgent2,
				$booking->SalesAgentID
			);

			// Format mobile
			$booking->CustomerMobile = $booking->CountryCode . str_replace([' ', '-'], '', $booking->CustomerMobile);

			// Format dates
			$start_date_formatted = !empty($booking->StartDate) ? strtoupper(date('j M Y', strtotime($booking->StartDate))) : null;
			$end_date_formatted = !empty($booking->EndDate) ? strtoupper(date('j M Y', strtotime($booking->EndDate))) : null;
			$insert_date_formatted = strtoupper(date('j M Y', strtotime($booking->InsertDate)));

			// Format BC title
			if($booking->BookingConfirmationTitle == 'BOOKING CONFIRMATION') {
				$bc_title = 'BC';
			} else if($booking->BookingConfirmationTitle == 'QUOTATION') {
				$bc_title = 'QU';
			} else {
				$bc_title = 'PI';
			}

			// Calculate profit
			$payments = $this->Booking_Model->Read_Payments($booking->BookingID);
			$total_credit = 0;
			$total_debit = 0;
			$net_profit = 0;
			$profit_margin = 0;
			$total_credit_approved = 0;
			if(!empty($payments)) {
				foreach($payments as $payment) {
					if($payment->Status == 'Y' || $payment->Status == 'P') {
						if($payment->Credit != 0.00) {
							$total_credit += $payment->Credit;
						} else {
							$total_debit += $payment->Debit;
						}
					}
					if($payment->Status == 'Y' && !empty($payment->Credit) && $payment->Credit > 0
						&& (!isset($payment->Type) || $payment->Type != 'AGENT COMMISSION FROM SUPPLIER')) {
						$total_credit_approved += floatval($payment->Credit);
					}
				}
				$net_profit = $total_credit - $total_debit;
				if($net_profit != 0 && $booking->NetTotal != 0) {
					$profit_margin = round(($net_profit / $booking->NetTotal) * 100);
				}
			}

			// Determine display status via shared helper (honours paid/deposit guards)
			$deposit_mode_row = !empty($booking->DepositMode) ? $booking->DepositMode : 'percentage';
			if($deposit_mode_row == 'fixed') {
				$deposit_required_row = isset($booking->DepositFixedAmount) ? floatval($booking->DepositFixedAmount) : 0;
			} else {
				$deposit_pct_row = isset($booking->DepositPercentage) ? floatval($booking->DepositPercentage) : 0;
				$deposit_required_row = ceil((floatval($booking->NetTotal) * $deposit_pct_row) / 100);
			}
			$booking->balance_due = floatval($booking->NetTotal) - $total_credit_approved;
			$this->load->helper('booking_flow');
			$has_deposit_deadline_row = !empty($booking->DepositDeadline);
			$booking->deposit_complete = compute_deposit_complete($deposit_required_row, $total_credit_approved, $has_deposit_deadline_row);
			$status_info_row = display_booking_status($booking);
			$display_status = $status_info_row['status_code'];
			$status_color = $status_info_row['status_color'];
			$status_text = $status_info_row['status_text'];

			// Profit color
			$profit_color = $net_profit < 0 ? '#FF2400' : ($net_profit == 0 ? '#F4BB44' : '#00A36C');

			// Autocount status
			$autocount_status_map = array('P' => array('text' => 'P', 'color' => '#FFBF00'), 'S' => array('text' => 'S', 'color' => '#50C878'), 'F' => array('text' => 'F', 'color' => '#FF2400'));
			$autocount_info = isset($autocount_status_map[$booking->AutocountSyncStatus]) ? $autocount_status_map[$booking->AutocountSyncStatus] : array('text' => '-', 'color' => '#000');

			// Build tooltip for autocount
			$tooltip_attr = '';
			if(!empty($booking->AutocountSyncMessage)) {
				$decoded = json_decode($booking->AutocountSyncMessage, true);
				if(json_last_error() === JSON_ERROR_NONE) {
					if(isset($decoded['error']) && $decoded['error'] == null) {
						$tooltip_text = 'SUCCESS';
					} elseif(isset($decoded['error']) && $decoded['error'] !== null) {
						$tooltip_text = 'ERROR: ' . (is_string($decoded['error']) ? $decoded['error'] : json_encode($decoded['error']));
					} else {
						$tooltip_text = $booking->AutocountSyncMessage;
					}
				} else {
					$tooltip_text = $booking->AutocountSyncMessage;
				}
				$tooltip_attr = ' data-toggle="tooltip" data-placement="top" title="' . htmlspecialchars($tooltip_text) . '"';
			}

			// Build row data
			$row = array();

			// Checkbox - show for Failed and Pending autocount status
			if (in_array($booking->AutocountSyncStatus, ['F', 'P'])) {
				$row['checkbox'] = '<input type="checkbox" class="check_item" value="' . $booking->BookingID . '">';
			} else {
				$row['checkbox'] = ''; // Empty for Synced status
			}

			// Row number
			$row['row_number'] = $count;

			// Sales agent, Sales agent 2 and OP
			$row['sales_agent'] = $booking->SalesAgentName;
			$row['sales_agent_2'] = $booking->SalesAgent2Name;
			$row['booking_op'] = $booking->BookingOPName;

			// Insert date
			$row['insert_date'] = $insert_date_formatted;

			// BC Number with link (plain text when SA is only the TC2 of this booking)
			if($sa_as_tc2) {
				$row['booking_number'] = $booking->BookingNumber;
			} else {
				$row['booking_number'] = '<a href="' . base_url('Payment?booking_number=') . $booking->BookingNumber . '&customer=' . str_replace('&', '%26', $booking->Customer) . '" target="_blank">' . $booking->BookingNumber . '</a>';
			}

			// BC Title
			$row['bc_title'] = $bc_title;

			// Customer
			$row['customer'] = $booking->Customer;
			$row['has_einvoice'] = $booking->has_einvoice > 0;

			// Source
			$row['source'] = $booking->SourceName ?? '-';

			// Source
			$row['source'] = $booking->SourceName ?? '-';

			// Customer Code
			$row['customer_code'] = $booking->CustomerCode;

			// Chat Language
			$row['chat_language'] = $booking->ChatLanguage;

			// Mobile (WhatsApp link)
			$row['mobile'] = '<a href="https://wa.me/' . $booking->CustomerMobile . '" target="_blank" class="btn btn-light-success d-inline-flex align-items-center btn-sm"><i class="la la-whatsapp"></i></a>';

			// Start Date
			$row['start_date'] = $start_date_formatted;

			// End Date
			$row['end_date'] = $end_date_formatted;

			// Destination
			$row['destination'] = $booking->DestinationName;

			// Net Total
			$row['net_total'] = number_format($booking->NetTotal, 2, '.', ',');

			// Profit (hidden from sales agents)
			if(!$is_sales_agent) {
				$row['profit'] = '<span style="color:' . $profit_color . '">' . number_format($net_profit, 2, '.', ',') . '</span>';
				$row['profit_margin'] = '<span style="color:' . $profit_color . '">' . $profit_margin . '</span>';
			}

			// Status
			if($booking->CancelStatus == 'Y' && !empty($booking->CancellationReasonName)) {
				$row['status'] = '<span class="font-weight-bold" style="color:' . $status_color . ';" data-toggle="tooltip" data-placement="top" title="Reason: ' . htmlspecialchars($booking->CancellationReasonName) . '">' . $status_text . '</span>';
			} else {
				$row['status'] = '<span class="font-weight-bold" style="color:' . $status_color . ';">' . $status_text . '</span>';
			}

			// Customer intake annotations next to the status cell:
			// - "Customer Submitted" badge while booking still sits in SAD but the
			//   intake has been completed by the customer.
			// - "Response: 1h 15m" once the booking has reached PENDING PAYMENT —
			//   submission -> P, matching the "Submitted -> Payment Time" card.
			$this->load->helper('customer_intake');
			$intake_row = $this->db->select('submitted_at')
				->where('booking_id', (int) $booking->BookingID)
				->get('booking_customer_intake')->row();
			if (!empty($intake_row)) {
				if ($display_status === 'SAD') {
					$row['status'] .= ' <span class="badge badge-info" style="font-size:9px; margin-left:4px;" data-toggle="tooltip" data-placement="top" title="Customer submitted intake form">Customer Submitted</span>';
				}
				$resp_seconds = calculate_submitted_to_payment_seconds((int) $booking->BookingID);
				if ($resp_seconds !== null) {
					$row['status'] .= '<br><small style="color:#6b7385;">Response: <strong>' . htmlspecialchars(format_response_duration($resp_seconds)) . '</strong></small>';
				}
			}

			// GL Status rules:
			// 1. If hard-locked (LockStatus = 'Y') -> locked icon (red)
			// 2. Else if active soft-lock exists -> loading spinner (blue)
			// 3. Else if guest list has submitted data -> submitted icon (orange)
			// 4. Else -> default unlocked icon (green)
			$gl_status_icon = '';
			
			// 1) Hard lock
			if ($booking->LockStatus == 'Y') {
				$gl_status_icon = '<i class="la la-lock text-danger" data-toggle="tooltip" data-placement="top" title="Guest list is locked"></i>';
			} else {
				$has_active_editor_lock = false;

				// 2) Active soft lock (someone is filling)
				if (!empty($booking->Token)) {
					$lock = $this->Guest_list_lock_model->getByHash($booking->Token);
					if (!empty($lock)) {
						$is_expired = $this->Guest_list_lock_model->isExpired($lock);
						if (!$is_expired) {
							$has_active_editor_lock = true;
							$gl_status_icon = '<i class="la la-spinner la-spin text-primary" data-toggle="tooltip" data-placement="top" title="Guest list is being edited"></i>';
						}
					}
				}

				if (!$has_active_editor_lock) {
					// 3) Submitted
					if (!empty($booking->is_submitted) && intval($booking->is_submitted) === 1) {
						$gl_status_icon = '<i class="la la-check-circle text-warning" data-toggle="tooltip" data-placement="top" title="Guest list submitted"></i>';
					} else {
						// 4) Default unlocked
						$gl_status_icon = '<i class="la la-unlock text-success" data-toggle="tooltip" data-placement="top" title="Guest list is unlocked"></i>';
					}
				}
			}
			$row['gl_status'] = $gl_status_icon;

			// Autocount Status
			$row['autocount_status'] = '<span class="font-weight-bold" style="color:' . $autocount_info['color'] . '"' . $tooltip_attr . '>' . $autocount_info['text'] . '</span>';

			// Action dropdown - simplified for AJAX response
			$row['action'] = $this->build_action_dropdown($booking, $current_url, $is_sales_agent, $sa_as_tc2);

			$data[] = $row;
			$count++;
		}

			$output = array(
				'draw' => $draw,
				'recordsTotal' => $records_total,
				'recordsFiltered' => $records_filtered,
				'data' => $data
			);

			// Header already set at the beginning, just output JSON
			echo json_encode($output);
			exit; // Prevent any additional output
		} catch (Exception $e) {
			// Log error and return JSON error response
			log_message('error', 'Booking ajax_list error: ' . $e->getMessage());
			echo json_encode(array(
				'error' => 'An error occurred while loading bookings',
				'draw' => intval($this->input->get('draw') ?? 0),
				'recordsTotal' => 0,
				'recordsFiltered' => 0,
				'data' => array()
			));
			exit;
		}
	}

	/**
	 * Build action dropdown HTML for a booking row
	 */
	private function build_action_dropdown($booking, $current_url, $is_sales_agent, $sa_as_tc2 = false)
	{
		$shown_approve_bc = false;
		$shown_update_booking = false;
		$html = '<div class="btn-group">';
		$html .= '<button type="button" data-toggle="dropdown" class="btn btn-light-primary btn-sm dropdown-toggle" style="padding-left:3px;"></button>';
		$html .= '<div class="dropdown-menu">';

		if(!$sa_as_tc2 && ($booking->Status != 'Y' || (!$is_sales_agent && $booking->Status == 'Y'))) {
			$access_control = $this->session->access_control ?? array();
			if(in_array('RB', $access_control)) {
				$html .= '<button onclick="Delete_Record(\'' . base_url('assets/image/sweetalert.jpg') . '\', \'Booking Record : ' . $booking->BookingNumber . '\', \'' . base_url('Booking/Delete') . '\', \'booking_id\', ' . $booking->BookingID . ', \'' . $booking->Status . '\', \'' . (strpos($current_url, '?') ? base_url('Booking?') . explode('?', $current_url)[1] : base_url('Booking')) . '\')" class="dropdown-item" style="color:#E37383; font-size:11px;">Delete Booking</button>';
			}
			if(in_array('AB', $access_control)) {
				if($booking->CancelStatus == 'Y') {
					$html .= '<a href="' . base_url('Booking/Update_Cancel_Status?booking_id=') . $booking->BookingID . '&current_cancel_status=' . $booking->CancelStatus . '&new_cancel_status=N&param=' . urlencode($current_url) . '" class="dropdown-item" style="color:#93C572; font-size:11px;">Activate Booking</a>';
				} else {
					$html .= '<button onclick="Cancel_Booking(\'' . base_url('assets/image/sweetalert.jpg') . '\', \'' . $booking->BookingNumber . '\', ' . $booking->BookingID . ', \'' . urlencode($current_url) . '\')" class="dropdown-item" style="color:#E0115F; font-size:11px;">Cancel Booking</button>';
					if(!empty($booking->PartialRefund) && $booking->PartialRefund == 'Y') {
						$html .= '<a href="' . base_url('Booking/Update_Partial_Refund_Status?booking_id=') . $booking->BookingID . '&new_partial_refund_status=N&param=' . urlencode($current_url) . '" class="dropdown-item" style="color:#93C572; font-size:11px;">Undo Partial Refund</a>';
					} else {
						$html .= '<button onclick="Cancel_With_Partial_Refund(\'' . base_url('assets/image/sweetalert.jpg') . '\', \'' . $booking->BookingNumber . '\', ' . $booking->BookingID . ', \'' . urlencode($current_url) . '\')" class="dropdown-item" style="color:#E0115F; font-size:11px;">Cancel With Partial Refund</button>';
					}
				}
				// Approve BC - only show when BC is not approved
				if(empty($booking->bc_approved) || $booking->bc_approved == 0) {
					$html .= '<a href="' . base_url('Booking/Approve_BC?booking_id=') . $booking->BookingID . '&param=' . urlencode($current_url) . '" class="dropdown-item" style="color:#50C878; font-size:11px;">Approve BC</a>';
					$shown_approve_bc = true;
				}
				// PGL with LockStatus=Y is displayed as PTV (see display_booking_status); treat it the same here.
				$effective_status = ($booking->Status == 'PGL' && $booking->LockStatus == 'Y') ? 'PTV' : $booking->Status;
				if($effective_status == 'PBO' || $effective_status == 'PTV' || $effective_status == 'PT') {
					if($effective_status == 'PBO' || $effective_status == 'PTV') {
						$html .= '<a href="' . base_url('Booking/Update_Status?booking_id=') . $booking->BookingID . '&current_status=' . $booking->Status . '&new_status=PT&param=' . urlencode($current_url) . '" class="dropdown-item" style="color:#6082B6; font-size:11px;">Approve Travel Voucher ?</a>';
					} else {
						$html .= '<a href="' . base_url('Booking/Update_Status?booking_id=') . $booking->BookingID . '&current_status=' . $booking->Status . '&new_status=PTV&param=' . urlencode($current_url) . '" class="dropdown-item" style="color:#F4BB44; font-size:11px;">Revert Pending Travel Voucher</a>';
					}
				}
				// Generic Revert Status button
				if($booking->CancelStatus != 'Y') {
					$prev_status = get_previous_status_in_flow($booking->Status);
					if($prev_status !== null) {
						$status_info = get_booking_status_info();
						$display_status = display_booking_status($booking);
						$from_text = $display_status['status_text'];
						$to_text = isset($status_info['texts'][$prev_status]) ? $status_info['texts'][$prev_status] : $prev_status;
						$revert_url = base_url('Booking/Update_Status?booking_id=') . $booking->BookingID . '&current_status=' . $booking->Status . '&new_status=' . $prev_status . '&param=' . urlencode($current_url);
						$html .= '<button onclick="Revert_Booking_Status(\'' . base_url('assets/image/sweetalert.jpg') . '\', \'' . $booking->BookingNumber . '\', \'' . $revert_url . '\', \'' . $from_text . '\', \'' . $to_text . '\')" class="dropdown-item" style="color:#F4BB44; font-size:11px;">Revert Status</button>';
					}
				}
				if(!$sa_as_tc2) {
					$html .= '<a href="' . (strpos($current_url, '?') ? base_url('Booking/Update?booking_id=') . $booking->BookingID . '&' . explode('?', $current_url)[1] : base_url('Booking/Update?booking_id=') . $booking->BookingID) . '" class="dropdown-item" style="font-size:11px;">Update Booking</a>';
					$shown_update_booking = true;
				}
			}
		}

		if(!$sa_as_tc2) {
			// View Booking - Allow Sales Agents without AB access to view their own bookings
			if($is_sales_agent && !$shown_update_booking && !empty($booking->SalesAgentID) && $booking->SalesAgentID == $this->session->userdata('admin_id')) {
				if(!empty($booking->SalesAgentID) && $booking->SalesAgentID == $this->session->userdata('admin_id')) {
					$html .= '<a href="' . (strpos($current_url, '?') ? base_url('Booking/View?booking_id=') . $booking->BookingID . '&' . explode('?', $current_url)[1] : base_url('Booking/View?booking_id=') . $booking->BookingID) . '" class="dropdown-item" style="font-size:11px;">View Booking</a>';
				}
			}
			// Approve BC - Allow SA users to approve their own bookings
			if($is_sales_agent && (empty($booking->bc_approved) || $booking->bc_approved == 0) && !empty($booking->SalesAgentID) && $booking->SalesAgentID == $this->session->userdata('admin_id') && !$shown_approve_bc) {
				$html .= '<a href="' . base_url('Booking/Approve_BC?booking_id=') . $booking->BookingID . '&param=' . urlencode($current_url) . '" class="dropdown-item" style="color:#50C878; font-size:11px;">Approve BC</a>';
			}
			// Complete Booking / Revert Pending Review - Allow SA users to complete after-sales service
			$access_control = $this->session->access_control ?? array();
			if(in_array('AB', $access_control)) {
				if($booking->Status == 'Y' || $booking->Status == 'PR') {
					if($booking->AfterSalesService == 'PENDING') {
						$html .= '<a href="' . base_url('Booking/Update_After_Sales_Service?booking_id=') . $booking->BookingID . '&current_after_sales_service=' . $booking->AfterSalesService . '&new_after_sales_service=COMPLETE&param=' . urlencode($current_url) . '" class="dropdown-item" style="color:#50C878; font-size:11px;">Complete Booking</a>';
					} else if(!$is_sales_agent) {
						// Only non-SA can revert to Pending Review
						$html .= '<a href="' . base_url('Booking/Update_After_Sales_Service?booking_id=') . $booking->BookingID . '&current_after_sales_service=' . $booking->AfterSalesService . '&new_after_sales_service=PENDING&param=' . urlencode($current_url) . '" class="dropdown-item" style="color:#702963; font-size:11px;">Revert Pending Review</a>';
					}
				}
			}
			if(in_array('GB', $this->session->access_control)) {
				$html .= '<a href="' . (strpos($current_url, '?') ? base_url('Booking/Duplicate?booking_id=') . $booking->BookingID . '&' . explode('?', $current_url)[1] : base_url('Booking/Duplicate?booking_id=') . $booking->BookingID) . '" class="dropdown-item" style="font-size:11px;">Duplicate Booking</a>';
			}
			// Edit Checklist - AB users, SA viewing own booking, the sales Team
			// Lead (level 25), or the OP TEAM LEAD (level 45). The modal/save
			// flow further scopes via can_user_modify_booking_checklist() so
			// only the matching TC1/OP TL can actually tick; non-matching leads
			// see a read-only modal.
			$is_team_lead_user = (int)$this->session->userdata('level') === 25;
			$is_op_team_lead_user = (int)$this->session->userdata('level') === 45;
			if(in_array('AB', $access_control) || ($is_sales_agent && !empty($booking->SalesAgentID) && $booking->SalesAgentID == $this->session->userdata('admin_id')) || $is_team_lead_user || $is_op_team_lead_user) {
				$html .= '<button onclick="openChecklistModal(' . $booking->BookingID . ')" class="dropdown-item" style="font-size:11px;">Edit Checklist</button>';
			}
		}
		$sales_agent_id = isset($booking->SalesAgentID) ? $booking->SalesAgentID : '';
		$booking_op_id = isset($booking->BookingOP) ? $booking->BookingOP : '';
		$html .= '<button onclick="openRemarksModal(' . $booking->BookingID . ', \'' . addslashes($booking->BookingNumber) . '\', \'' . $sales_agent_id . '\', \'' . $booking_op_id . '\')" class="dropdown-item" style="font-size:11px;">View Remarks</button>';
		$html .= '<div class="dropdown-divider"></div>';
		$html .= '<a href="' . base_url('Booking_Confirmation?token=') . $booking->Token . '" target="_blank" class="dropdown-item" style="font-size:11px;">Booking Confirmation</a>';
		$html .= '<button id="bc_url-' . $booking->BookingID . '" value="' . base_url('Booking_Confirmation?token=') . $booking->Token . '" onclick="Copy_URL(\'BC URL\', ' . $booking->BookingID . ')" class="dropdown-item" style="font-size:11px;">Copy BC Link</button>';
		if(!$sa_as_tc2) {
			$html .= '<div class="dropdown-divider"></div>';
			$sa_blocked_from_gl = is_sa_blocked_from_completed_booking($this->session->userdata('level'), $booking->Status, $booking->AfterSalesService);
			if($booking->Status != 'Y' || (!$is_sales_agent && $booking->Status == 'Y')) {
				$html .= '<a href="' . base_url('Guest_List?gl=') . $booking->Token . '" target="_blank" class="dropdown-item" style="font-size:11px;">Guest List</a>';
			}
			if(!$sa_blocked_from_gl) {
				$html .= '<a href="' . base_url('Guest_List/Download?booking_id=') . $booking->BookingID . '" class="dropdown-item" style="font-size:11px;">Download Guest List</a>';
				$html .= '<a href="' . base_url('Guest_List/Download_ZIP?booking_id=') . $booking->BookingID . '" class="dropdown-item" style="font-size:11px;">Download Guestlist ZIP</a>';
				$html .= '<button id="gl_url-' . $booking->BookingID . '" value="' . base_url('Guest_List?gl=') . $booking->Token . '" onclick="Copy_URL(\'GL URL\', ' . $booking->BookingID . ')" class="dropdown-item" style="font-size:11px;">Copy GL Link</button>';
			}
		}
		$html .= '<div class="dropdown-divider"></div>';
		$html .= '<a href="' . base_url('Travel_Voucher?token=') . $booking->Token . '" target="_blank" class="dropdown-item" style="font-size:11px;">Travel Voucher</a>';
		$html .= '<button id="tv_url-' . $booking->BookingID . '" value="' . base_url('Travel_Voucher?token=') . $booking->Token . '" onclick="Copy_URL(\'TV URL\', ' . $booking->BookingID . ')" class="dropdown-item" style="font-size:11px;">Copy TV Link</button>';
		$html .= '<div class="dropdown-divider"></div>';
		$html .= '<button id="customer_name-' . $booking->BookingID . '" value="' . $booking->Customer . '" onclick="Copy_URL(\'CUSTOMER NAME\', ' . $booking->BookingID . ')" class="dropdown-item" style="font-size:11px;">Copy Customer Name</button>';
		$html .= '<button id="customer_mobile-' . $booking->BookingID . '" value="' . $booking->CustomerMobile . '" onclick="Copy_URL(\'CUSTOMER MOBILE\', ' . $booking->BookingID . ')" class="dropdown-item" style="font-size:11px;">Copy Customer Mobile</button>';
		if(!$sa_as_tc2) {
			$html .= '<div class="dropdown-divider"></div>';
			if($booking->CustomerID != null) {
				// Load helper for generating portal hash
				$this->load->helper('utils');
				$customer_hash = generate_customer_portal_slug($booking->CustomerID);
				if (!empty($customer_hash)) {
					$portal_url = base_url('customer/' . urlencode($customer_hash));
					$html .= '<a href="' . $portal_url . '" target="_blank" class="dropdown-item" style="font-size:11px;">Go to Customer Portal</a>';
					$html .= '<button id="portal_url-' . $booking->BookingID . '" value="' . $portal_url . '" onclick="Copy_URL(\'CUSTOMER PORTAL LINK\', ' . $booking->BookingID . ')" class="dropdown-item" style="font-size:11px;">Copy Customer Portal Link</button>';
				}
			}
			else {
				$html .= '<a href="#" class="dropdown-item" style="font-size:11px; cursor:not-allowed; color:#6c757d;" disabled>Customer ID not found</a>';
			}
		}
		if(!$sa_as_tc2 && !empty($booking->Token)) {
			$booking_page_url = base_url('customer/booking/' . $booking->Token);
			$html .= '<a href="' . $booking_page_url . '" target="_blank" class="dropdown-item" style="font-size:11px;">Go to Booking Page</a>';
			$html .= '<button id="booking_page_url-' . $booking->BookingID . '" value="' . $booking_page_url . '" onclick="Copy_URL(\'BOOKING PAGE LINK\', ' . $booking->BookingID . ')" class="dropdown-item" style="font-size:11px;">Copy Booking Page Link</button>';
		}
		if(!empty($booking->Token)) {
			$intake_url = base_url('customer-intake/' . $booking->Token);
			$html .= '<div class="dropdown-divider"></div>';
			$html .= '<a href="' . $intake_url . '" target="_blank" class="dropdown-item" style="font-size:11px;">Customer Intake Form</a>';
			$html .= '<button id="customer_intake_url-' . $booking->BookingID . '" value="' . $intake_url . '" onclick="Copy_URL(\'CUSTOMER INTAKE LINK\', ' . $booking->BookingID . ')" class="dropdown-item" style="font-size:11px;">Copy Customer Intake Link</button>';
		}
		$html .= '</div></div>';

		return $html;
	}

	/**
	 * AJAX endpoint for summary totals
	 * Returns total sales and net profit for all filtered bookings
	 */
	function ajax_summary()
	{
		if(!in_array('VB', $this->session->access_control)) {
			echo json_encode(array('error' => 'Access denied'));
			return;
		}

		$is_sales_agent = $this->session->userdata('level') == 20;

		$summary = $this->Booking_Model->Calculate_Summary();

		$total_sales = $summary['total_sales'];

		// Format output
		$total_sales_formatted = number_format($total_sales, 2, '.', ',');

		$output = array(
			'total_sales' => $total_sales_formatted,
			'is_sales_agent' => $is_sales_agent
		);

		// Net profit is restricted to non-sales-agents
		if(!$is_sales_agent) {
			$total_net_profit = $summary['total_net_profit'];
			if($total_net_profit != 0 && $total_sales != 0) {
				$profit_percentage = round(($total_net_profit / $total_sales) * 100);
				$total_net_profit_formatted = number_format($total_net_profit, 2, '.', ',') . ' (' . $profit_percentage . '%)';
			} else {
				$total_net_profit_formatted = number_format($total_net_profit, 2, '.', ',') . ' (0%)';
			}
			$output['total_net_profit'] = $total_net_profit_formatted;
		}

		header('Content-Type: application/json');
		echo json_encode($output);
	}

	/**
	 * AJAX endpoint for role-based summary cards rendered above the booking listing.
	 * Returns counts/values/links keyed per card; the view partial fills placeholders.
	 */
	function ajax_summary_cards()
	{
		if(!in_array('VB', $this->session->access_control)) {
			header('Content-Type: application/json');
			echo json_encode(array('error' => 'Access denied'));
			return;
		}

		$level    = (int) $this->session->userdata('level');
		$admin_id = (int) $this->session->userdata('admin_id');

		$today        = date('Y-m-d');
		$month_start  = date('Y-m-01');
		$month_end    = date('Y-m-t');
		$week_start   = date('Y-m-d', strtotime('monday this week'));
		$week_end     = date('Y-m-d', strtotime('sunday this week'));
		$next7_start  = date('Y-m-d', strtotime('+1 day'));
		$next7_end    = date('Y-m-d', strtotime('+7 days'));
		// Cumulative 14-day window shares the 7-day start (tomorrow) and extends
		// to +14, so "within 14 days" is a superset of "within 7 days".
		$next14_start = $next7_start;
		$next14_end   = date('Y-m-d', strtotime('+14 days'));
		// Current calendar quarter (used for owner-only conversion cards):
		// Q1 Jan–Mar · Q2 Apr–Jun · Q3 Jul–Sep · Q4 Oct–Dec.
		$q_idx         = (int) ceil(((int) date('n')) / 3);
		$q_start_month = ($q_idx - 1) * 3 + 1;
		$quarter_start = date('Y-' . sprintf('%02d', $q_start_month) . '-01');
		$quarter_end   = date('Y-m-t', strtotime($quarter_start . ' +2 months'));

		// Selected reporting period for the TC summary cards' month filter
		// (?month=YYYY-MM). Falls back to the current month. Re-scopes every
		// TC "(Month)" / "(Year)" card; the rolling Today/Week/Month cards and
		// the other roles keep the live current-month windows above.
		$this->load->helper('summary_period_helper');
		$period = summary_resolve_month($this->input->get('month'), $today);

		$base = base_url('Booking');
		$fmt_dmy = function($d) { return date('d/m/Y', strtotime($d)); };
		$money = function($v) { return 'RM ' . number_format((float)$v, 2, '.', ','); };
		$qs = function($params) { return '?' . http_build_query($params); };

		$cards  = array();
		$tables = array();

		$is_tc      = ($level == 20 || $level == 50);
		$is_tclead  = ($level == 25);
		$is_op      = ($level == 40);
		$is_finance = ($level == 30);
		$is_owner   = ($level == 10);

		// ---------- TC / TC2 (own bookings) ----------
		// Credited-slot rule: a booking counts for this TC only when they hold
		// the credited slot for InsertDate (TC1 pre-2026-06-01, TC2 on/after).
		// Mirrors lead_conversion_credit_sql_fragment() so summary cards agree
		// with the Lead Dashboard's converted-leads attribution.
		if($is_tc) {
			$this->load->helper('lead_conversion_credit');
			$credit_clause = lead_conversion_credit_booking_clause();

			// Re-scope every TC "(Month)" card to the selected period. Keep the
			// live current month for the rolling Today/Week/Month cards below.
			$cur_month_start = $month_start;
			$cur_month_end   = $month_end;
			$month_start = $period['month_start'];
			$month_end   = $period['month_end'];
			$year_start  = $period['year_start'];
			$year_end    = $period['year_end'];

			$row = $this->db->query(
				"SELECT COUNT(*) AS cnt FROM booking
				 WHERE {$credit_clause}
				   AND BookingConfirmationTitle='BOOKING CONFIRMATION'
				   AND CancelStatus='N' AND Status!='N'
				   AND CAST(InsertDate AS DATE) BETWEEN ? AND ?",
				array($admin_id, $admin_id, $month_start, $month_end)
			)->row();
			$cards['bc_month'] = array(
				'count' => (int)$row->cnt,
				'link'  => $base . $qs(array('booking_date' => $fmt_dmy($month_start) . ' - ' . $fmt_dmy($month_end))),
			);

			// Total Sales: only fully-paid BCs count. "Fully paid" = sum of approved
			// customer payments (Status='Y', Credit>0, excluding agent commission)
			// >= NetTotal. Matches the approved-credits pattern in Booking_Model
			// (status='PO' filter) so this card agrees with the payment-overdue view.
			$paid_subquery = "COALESCE((
				SELECT SUM(p.Credit) FROM payment p
				WHERE p.BookingID = booking.BookingID
				  AND p.Status = 'Y' AND p.Credit > 0
				  AND (p.Type IS NULL OR p.Type != 'AGENT COMMISSION FROM SUPPLIER')
			), 0)";

			$fully_paid_sales_sql =
				"SELECT COALESCE(SUM(NetTotal),0) AS total FROM booking
				 WHERE {$credit_clause}
				   AND BookingConfirmationTitle='BOOKING CONFIRMATION'
				   AND CancelStatus='N' AND Status!='N'
				   AND booking.NetTotal > 0
				   AND {$paid_subquery} >= booking.NetTotal
				   AND CAST(InsertDate AS DATE) BETWEEN ? AND ?";

			$row = $this->db->query(
				$fully_paid_sales_sql,
				array($admin_id, $admin_id, $month_start, $month_end)
			)->row();
			$sales_month_actual = (float)$row->total;

			$row_year = $this->db->query(
				$fully_paid_sales_sql,
				array($admin_id, $admin_id, $year_start, $year_end)
			)->row();
			$sales_year_actual = (float)$row_year->total;

			// Target lookups for the selected period — set by Owner / Team Lead
			// via Admin (sales_targets monthly, sales_target_year yearly).
			// Missing row => target 0 => percent rendered "—".
			$this->load->model('Sales_Target_Model');
			$target_amount = $this->Sales_Target_Model->get_amount(
				$admin_id, $period['year'], $period['month']
			);
			$year_target_amount = $this->Sales_Target_Model->get_year_amount(
				$admin_id, $period['year']
			);

			$pct_month = $target_amount > 0
				? round(($sales_month_actual / $target_amount) * 100, 1)
				: null;
			$pct_year = $year_target_amount > 0
				? round(($sales_year_actual / $year_target_amount) * 100, 1)
				: null;

			// raw / raw_target feed the Actual-vs-Target bar charts on the front
			// end; the chart caps the actual bar at 100% of target visually but
			// keeps the true percent in the label (over-achievement allowed).
			$cards['sales_month'] = array(
				'value'      => $money($sales_month_actual),
				'target'     => $money($target_amount),
				'percent'    => $pct_month === null ? '—' : ($pct_month . '%'),
				'has_target' => $target_amount > 0,
				'raw'        => $sales_month_actual,
				'raw_target' => $target_amount,
			);
			$cards['sales_year'] = array(
				'value'      => $money($sales_year_actual),
				'target'     => $money($year_target_amount),
				'percent'    => $pct_year === null ? '—' : ($pct_year . '%'),
				'has_target' => $year_target_amount > 0,
				'raw'        => $sales_year_actual,
				'raw_target' => $year_target_amount,
			);

			$row = $this->db->query(
				"SELECT COUNT(*) AS total,
				        SUM(CASE WHEN CancelStatus='Y' THEN 1 ELSE 0 END) AS cancelled
				 FROM booking
				 WHERE {$credit_clause}
				   AND BookingConfirmationTitle='BOOKING CONFIRMATION'
				   AND Status!='N'
				   AND CAST(InsertDate AS DATE) BETWEEN ? AND ?",
				array($admin_id, $admin_id, $month_start, $month_end)
			)->row();
			$rate = (int)$row->total > 0 ? round(((int)$row->cancelled / (int)$row->total) * 100, 1) : 0;
			$cards['cancellation_rate'] = array(
				'value'  => $rate . '%',
				'detail' => (int)$row->cancelled . ' / ' . (int)$row->total,
				'link'   => $base . $qs(array('status' => 'C', 'booking_date' => $fmt_dmy($month_start) . ' - ' . $fmt_dmy($month_end))),
			);

			// Disjoint breakdown so full_overdue + deposit_only_overdue = total.
			// $po_cutoff applies the 3pm rule: a deadline falling today counts as
			// overdue from 3:00pm onward (cutoff rolls to tomorrow). Shared with
			// the booking list's PO filter so the card and listing agree.
			$this->load->helper('booking_status_filter');
			$po_cutoff = payment_overdue_cutoff_date();
			$row = $this->db->query(
				"SELECT
				   SUM(CASE WHEN FullPaymentDeadline < ? AND Status IN ('P','PP') THEN 1 ELSE 0 END) AS full_overdue,
				   SUM(CASE WHEN DepositDeadline < ? AND Status='P'
				             AND NOT (FullPaymentDeadline < ? AND Status IN ('P','PP'))
				            THEN 1 ELSE 0 END) AS deposit_only_overdue
				 FROM booking
				 WHERE {$credit_clause}
				   AND BookingConfirmationTitle='BOOKING CONFIRMATION'
				   AND CancelStatus='N'
				   AND (
				        (FullPaymentDeadline < ? AND Status IN ('P','PP'))
				     OR (DepositDeadline < ? AND Status='P')
				   )",
				array($po_cutoff, $po_cutoff, $po_cutoff, $admin_id, $admin_id, $po_cutoff, $po_cutoff)
			)->row();
			$po_full    = (int)$row->full_overdue;
			$po_deposit = (int)$row->deposit_only_overdue;
			$cards['payment_overdue'] = array(
				'count'                => $po_full + $po_deposit,
				'full_overdue'         => $po_full,
				'deposit_only_overdue' => $po_deposit,
				'link'                 => $base . $qs(array('status' => 'PO')),
			);

			$row = $this->db->query(
				"SELECT
				   SUM(CASE WHEN Status='P'   THEN 1 ELSE 0 END) AS s_p,
				   SUM(CASE WHEN Status='PBO' THEN 1 ELSE 0 END) AS s_pbo,
				   SUM(CASE WHEN Status='PGL' THEN 1 ELSE 0 END) AS s_pgl,
				   SUM(CASE WHEN Status='PTV' THEN 1 ELSE 0 END) AS s_ptv
				 FROM booking
				 WHERE {$credit_clause}
				   AND BookingConfirmationTitle='BOOKING CONFIRMATION'
				   AND CancelStatus='N'
				   AND Status IN ('P','PBO','PGL','PTV')
				   AND StartDate BETWEEN ? AND ?",
				array($admin_id, $admin_id, $next7_start, $next7_end)
			)->row();
			$un_p   = (int)$row->s_p;
			$un_pbo = (int)$row->s_pbo;
			$un_pgl = (int)$row->s_pgl;
			$un_ptv = (int)$row->s_ptv;
			$cards['upcoming_travel_not_ready'] = array(
				'count'  => $un_p + $un_pbo + $un_pgl + $un_ptv,
				'by_p'   => $un_p,
				'by_pbo' => $un_pbo,
				'by_pgl' => $un_pgl,
				'by_ptv' => $un_ptv,
				'link'   => $base . $qs(array(
					'upcoming_not_ready' => 1,
					'travel_date'        => $fmt_dmy($next7_start) . ' - ' . $fmt_dmy($next7_end),
				)),
			);

			// Same "not yet ready" set over the cumulative 14-day window.
			$row = $this->db->query(
				"SELECT
				   SUM(CASE WHEN Status='P'   THEN 1 ELSE 0 END) AS s_p,
				   SUM(CASE WHEN Status='PBO' THEN 1 ELSE 0 END) AS s_pbo,
				   SUM(CASE WHEN Status='PGL' THEN 1 ELSE 0 END) AS s_pgl,
				   SUM(CASE WHEN Status='PTV' THEN 1 ELSE 0 END) AS s_ptv
				 FROM booking
				 WHERE {$credit_clause}
				   AND BookingConfirmationTitle='BOOKING CONFIRMATION'
				   AND CancelStatus='N'
				   AND Status IN ('P','PBO','PGL','PTV')
				   AND StartDate BETWEEN ? AND ?",
				array($admin_id, $admin_id, $next14_start, $next14_end)
			)->row();
			$un14_p   = (int)$row->s_p;
			$un14_pbo = (int)$row->s_pbo;
			$un14_pgl = (int)$row->s_pgl;
			$un14_ptv = (int)$row->s_ptv;
			$cards['upcoming_travel_not_ready_14'] = array(
				'count'  => $un14_p + $un14_pbo + $un14_pgl + $un14_ptv,
				'by_p'   => $un14_p,
				'by_pbo' => $un14_pbo,
				'by_pgl' => $un14_pgl,
				'by_ptv' => $un14_ptv,
				'link'   => $base . $qs(array(
					'upcoming_not_ready' => 1,
					'travel_date'        => $fmt_dmy($next14_start) . ' - ' . $fmt_dmy($next14_end),
				)),
			);

			// Travel Completed - Pending Review: BCs the TC owns where travel
			// has ended (Status='Y') but after-sales review is still pending.
			// Filter mirrors the PR status code in booking_status_filter_helper
			// so the count and the linked listing return the same set.
			$row = $this->db->query(
				"SELECT COUNT(*) AS cnt FROM booking
				 WHERE SalesAgent = ?
				   AND BookingConfirmationTitle='BOOKING CONFIRMATION'
				   AND CancelStatus='N'
				   AND AfterSalesService='PENDING'
				   AND Status='Y'",
				array($admin_id)
			)->row();
			$cards['pending_review'] = array(
				'count' => (int)$row->cnt,
				'link'  => $base . $qs(array('status' => 'PR')),
			);

			// ---------- "Compare the Best" sub-lines for the TC KPI cards ----------
			// Aggregate across every agent under the same TC1/TC2 credited-slot
			// rule that the agent's own cards use, so the comparison universe is
			// apples-to-apples. When the logged-in TC IS the best, the sub-line
			// shows "Best: You" so they recognise themselves at a glance.
			$this->load->helper('best_agent');
			$agent_expr = lead_conversion_credit_agent_expr();

			$pick_best = function($rows, $metric_key, $sort_desc) use ($admin_id) {
				if(empty($rows)) { return null; }
				usort($rows, function($a, $b) use ($metric_key, $sort_desc) {
					$av = (float)$a[$metric_key];
					$bv = (float)$b[$metric_key];
					if($av !== $bv) { return $sort_desc ? ($bv <=> $av) : ($av <=> $bv); }
					return strcmp((string)$a['agent_name'], (string)$b['agent_name']);
				});
				$top = $rows[0];
				if((int)$top['credited_agent_id'] === (int)$admin_id) {
					$top['agent_name'] = 'You';
				}
				return $top;
			};

			// BC count leaderboard counts ALL credited BCs (not just fully paid)
			// so it stays consistent with the bc_month card's own count.
			$best_bc_rows = $this->db->query(
				"SELECT
				   {$agent_expr} AS credited_agent_id,
				   admin.Name AS agent_name,
				   COUNT(*) AS bc_count
				 FROM booking
				 LEFT JOIN admin ON admin.AdminID = {$agent_expr}
				 WHERE booking.BookingConfirmationTitle='BOOKING CONFIRMATION'
				   AND booking.CancelStatus='N'
				   AND booking.Status!='N'
				   AND CAST(booking.InsertDate AS DATE) BETWEEN ? AND ?
				 GROUP BY credited_agent_id, agent_name
				 HAVING credited_agent_id IS NOT NULL AND credited_agent_id > 0",
				array($month_start, $month_end)
			)->result_array();
			$best_bc = $pick_best($best_bc_rows, 'bc_count', true);
			$cards['bc_month']['best'] = $best_bc
				? array('name' => $best_bc['agent_name'], 'value' => (string)(int)$best_bc['bc_count'])
				: null;

			// Total Sales leaderboard applies the same fully-paid filter as the
			// agent's own card so "Best" is apples-to-apples.
			$best_sales_rows = $this->db->query(
				"SELECT
				   {$agent_expr} AS credited_agent_id,
				   admin.Name AS agent_name,
				   COALESCE(SUM(booking.NetTotal), 0) AS total_sales
				 FROM booking
				 LEFT JOIN admin ON admin.AdminID = {$agent_expr}
				 WHERE booking.BookingConfirmationTitle='BOOKING CONFIRMATION'
				   AND booking.CancelStatus='N'
				   AND booking.Status!='N'
				   AND booking.NetTotal > 0
				   AND {$paid_subquery} >= booking.NetTotal
				   AND CAST(booking.InsertDate AS DATE) BETWEEN ? AND ?
				 GROUP BY credited_agent_id, agent_name
				 HAVING credited_agent_id IS NOT NULL AND credited_agent_id > 0",
				array($month_start, $month_end)
			)->result_array();
			$best_sales = $pick_best($best_sales_rows, 'total_sales', true);
			$cards['sales_month']['best'] = $best_sales
				? array('name' => $best_sales['agent_name'], 'value' => $money($best_sales['total_sales']))
				: null;

			// Year leaderboard — same fully-paid filter over the selected year so
			// the "Best" figure on the Year card is apples-to-apples with Month.
			$best_sales_year_rows = $this->db->query(
				"SELECT
				   {$agent_expr} AS credited_agent_id,
				   admin.Name AS agent_name,
				   COALESCE(SUM(booking.NetTotal), 0) AS total_sales
				 FROM booking
				 LEFT JOIN admin ON admin.AdminID = {$agent_expr}
				 WHERE booking.BookingConfirmationTitle='BOOKING CONFIRMATION'
				   AND booking.CancelStatus='N'
				   AND booking.Status!='N'
				   AND booking.NetTotal > 0
				   AND {$paid_subquery} >= booking.NetTotal
				   AND CAST(booking.InsertDate AS DATE) BETWEEN ? AND ?
				 GROUP BY credited_agent_id, agent_name
				 HAVING credited_agent_id IS NOT NULL AND credited_agent_id > 0",
				array($year_start, $year_end)
			)->result_array();
			$best_sales_year = $pick_best($best_sales_year_rows, 'total_sales', true);
			$cards['sales_year']['best'] = $best_sales_year
				? array('name' => $best_sales_year['agent_name'], 'value' => $money($best_sales_year['total_sales']))
				: null;

			// Cancellation Rate: drop the CancelStatus filter because the
			// numerator needs the cancelled rows; min-sample 3 keeps a single-BC
			// agent at 0% from dominating.
			$cancel_rows = $this->db->query(
				"SELECT
				   {$agent_expr} AS credited_agent_id,
				   admin.Name AS agent_name,
				   COUNT(*) AS total,
				   SUM(CASE WHEN booking.CancelStatus='Y' THEN 1 ELSE 0 END) AS cancelled
				 FROM booking
				 LEFT JOIN admin ON admin.AdminID = {$agent_expr}
				 WHERE booking.BookingConfirmationTitle='BOOKING CONFIRMATION'
				   AND booking.Status!='N'
				   AND CAST(booking.InsertDate AS DATE) BETWEEN ? AND ?
				 GROUP BY credited_agent_id, agent_name
				 HAVING credited_agent_id IS NOT NULL AND credited_agent_id > 0 AND COUNT(*) >= 3",
				array($month_start, $month_end)
			)->result_array();
			foreach($cancel_rows as &$cr) {
				$cr['rate'] = ((float)$cr['cancelled'] / (float)$cr['total']) * 100;
			}
			unset($cr);
			$best_cancel = $pick_best($cancel_rows, 'rate', false);
			$cards['cancellation_rate']['best'] = $best_cancel
				? array(
					'name'  => $best_cancel['agent_name'],
					'value' => round((float)$best_cancel['rate'], 1) . '%',
				)
				: null;

			// Conversion Rate (YTD) — TC card. Fixed year-to-date window (Jan 1 of
			// the current year through today, independent of the month filter) and
			// credited via SalesAgent2 (TC2) for the whole year — see
			// lead_conversion_credit_sql_fragment_tc2().
			$this->load->model('Report_Model');
			$this->load->helper('lead_conversion_credit');
			$ytd_start = date('Y-01-01');
			$ytd_end   = $today;
			$by_agent = $this->Report_Model->Lead_Dashboard_By_Agent(
				array('start_date' => $ytd_start, 'end_date' => $ytd_end),
				lead_conversion_credit_sql_fragment_tc2()
			);

			// Resolve the logged-in admin to their GHL UserID(s). Canonical source
			// is admin_lead_dashboard_agents (maintained from Admin / Update);
			// email match is a fallback for admins whose mapping hasn't been
			// configured yet. Team-inbox GHL users use shared Gmail addresses
			// that don't match admin.Email, so an email-only lookup silently
			// drops them — see lead_conversion_credit_helper.php.
			$mapping_rows = $this->db->select('GhlUserID')
				->where('AdminID', $admin_id)
				->get('admin_lead_dashboard_agents')->result();
			$my_ghl_uids = array_values(array_filter(array_map(function($r) {
				return (string)$r->GhlUserID;
			}, $mapping_rows), 'strlen'));
			if(empty($my_ghl_uids)) {
				$email_rows = $this->db->query(
					"SELECT gu.UserID
					 FROM admin a
					 INNER JOIN ghl_users gu
					   ON LOWER(TRIM(gu.Email)) = LOWER(TRIM(a.Email))
					 WHERE a.AdminID = ?",
					array($admin_id)
				)->result();
				$my_ghl_uids = array_values(array_filter(array_map(function($r) {
					return (string)$r->UserID;
				}, $email_rows), 'strlen'));
			}

			$own_rate        = null;
			$own_total_leads = 0;
			$own_converted   = 0;
			if(!empty($my_ghl_uids)) {
				$uid_set = array_flip($my_ghl_uids);
				foreach($by_agent as $a) {
					if(isset($uid_set[(string)$a['agent_id']])) {
						$own_total_leads += (int)$a['total_leads'];
						$own_converted   += (int)$a['converted_leads'];
					}
				}
				$own_rate = $own_total_leads > 0
					? round(($own_converted / $own_total_leads) * 100, 1)
					: null;
			}
			$best_conv = best_conversion_rate_agent($by_agent, 3);
			if($best_conv && !empty($my_ghl_uids) && in_array((string)$best_conv['agent_id'], $my_ghl_uids, true)) {
				$best_conv['agent_name'] = 'You';
			}
			$cards['conversion_rate_ytd'] = array(
				'value'  => $own_rate === null ? '-' : (round($own_rate, 1) . '%'),
				'detail' => $own_converted . ' / ' . $own_total_leads,
				'best'   => $best_conv
					? array(
						'name'  => $best_conv['agent_name'],
						'value' => round((float)$best_conv['conversion_rate'], 1) . '%',
					)
					: null,
			);

			// "My Leads (Today / Week / Month)" card. Scoped to the logged-in TC
			// via Lead_Dashboard_Summary's agent_id filter (NULLIF in_user IN (?)).
			// When the TC isn't linked to GHL we emit a stable empty payload so
			// the front-end still renders zeros instead of "...".
			$fmt_seconds = function($secs) {
				if($secs === null) { return '-'; }
				if($secs >= 3600) { return round($secs / 3600, 1) . 'h'; }
				if($secs >= 60)   { return round($secs / 60,   1) . 'm'; }
				return $secs . 's';
			};
			if(!empty($my_ghl_uids)) {
				$mine_day   = $this->Report_Model->Lead_Dashboard_Summary(array(
					'agent_id'   => $my_ghl_uids,
					'start_date' => $today,       'end_date' => $today,
				));
				$mine_week  = $this->Report_Model->Lead_Dashboard_Summary(array(
					'agent_id'   => $my_ghl_uids,
					'start_date' => $week_start,  'end_date' => $week_end,
				));
				$mine_month = $this->Report_Model->Lead_Dashboard_Summary(array(
					'agent_id'   => $my_ghl_uids,
					'start_date' => $cur_month_start, 'end_date' => $cur_month_end,
				));
				$cards['tc_leads_dwm'] = array(
					'day'   => (int)$mine_day['total_leads'],
					'week'  => (int)$mine_week['total_leads'],
					'month' => (int)$mine_month['total_leads'],
				);
				$cards['tc_response_time_dwm'] = array(
					'day'           => $fmt_seconds($mine_day['avg_response_time_seconds']),
					'week'          => $fmt_seconds($mine_week['avg_response_time_seconds']),
					'month'         => $fmt_seconds($mine_month['avg_response_time_seconds']),
					'day_seconds'   => $mine_day['avg_response_time_seconds'],
					'week_seconds'  => $mine_week['avg_response_time_seconds'],
					'month_seconds' => $mine_month['avg_response_time_seconds'],
				);
			} else {
				$cards['tc_leads_dwm'] = array(
					'day' => 0, 'week' => 0, 'month' => 0,
				);
				$cards['tc_response_time_dwm'] = array(
					'day' => '-', 'week' => '-', 'month' => '-',
					'day_seconds' => null, 'week_seconds' => null, 'month_seconds' => null,
				);
			}

			// Pending BC (self) — current backlog of the TC's bookings parked at
			// PB ("PENDING BC"). Not period-scoped (it's a live to-do count, like
			// Payment Overdue); credited via the same TC1/TC2 slot rule.
			$row = $this->db->query(
				"SELECT COUNT(*) AS cnt FROM booking
				 WHERE {$credit_clause}
				   AND CancelStatus='N' AND Status='PB'",
				array($admin_id, $admin_id)
			)->row();
			$cards['pending_bc'] = array(
				'count' => (int)$row->cnt,
				'link'  => $base . $qs(array('status' => 'PB')),
			);

			// Submitted -> Payment Time (self, selected month). Average gap from
			// the customer submitting their intake to the booking first reaching
			// PENDING PAYMENT (P), windowed on the submission date — the same
			// start anchor as the Intake -> BC Response Time card. "Best:" footer
			// ranks the fastest TC team-wide ("You" when that's the logged-in agent).
			$this->load->helper(array('submitted_payment_response', 'customer_intake'));
			$sp_start = $month_start . ' 00:00:00';
			$sp_next  = date('Y-m-01 00:00:00', strtotime($month_start . ' +1 month'));
			$sp_row = $this->db->query(
				submitted_payment_avg_response_sql(true),
				array($sp_start, $sp_next, $admin_id, $admin_id)
			)->row();
			$sp_n    = !empty($sp_row) ? (int)$sp_row->n : 0;
			$sp_secs = ($sp_n > 0 && $sp_row->avg_seconds !== null)
				? (int)round((float)$sp_row->avg_seconds) : null;
			$best_sp_row = $this->db->query(
				submitted_payment_best_agent_sql(),
				array($sp_start, $sp_next)
			)->row();
			$best_sp = null;
			if(!empty($best_sp_row) && !empty($best_sp_row->AdminID)) {
				if((int)$best_sp_row->AdminID === (int)$admin_id) {
					$bd_name = 'You';
				} else {
					$bd_admin = $this->db->select('Name')
						->where('AdminID', (int)$best_sp_row->AdminID)
						->get('admin')->row();
					$bd_name = $bd_admin ? $bd_admin->Name : '#' . (int)$best_sp_row->AdminID;
				}
				$best_sp = array(
					'name'  => $bd_name,
					'value' => format_response_duration((int)round((float)$best_sp_row->avg_seconds)),
				);
			}
			$cards['submitted_payment_response_month'] = array(
				'value'   => format_response_duration($sp_secs),
				'count'   => $sp_n,
				'seconds' => $sp_secs,
				'best'    => $best_sp,
			);
		}

		// ---------- TC LEAD / Owner (team-wide lead + booking metrics) ----------
		// Owner is scoped to the three lead-conversion cards only (Leads,
		// Conversion & Response, Top Agents). BC Created (Week+Month) and the
		// team-wide cancellation rate stay TC-Lead-only, so their queries are
		// gated on $is_tclead inside this block.
		if($is_tclead || $is_owner) {
			if($is_tclead) {
				$row = $this->db->query(
					"SELECT
					   SUM(CASE WHEN CAST(InsertDate AS DATE) BETWEEN ? AND ? THEN 1 ELSE 0 END) AS month_cnt,
					   SUM(CASE WHEN CAST(InsertDate AS DATE) BETWEEN ? AND ? THEN 1 ELSE 0 END) AS week_cnt
					 FROM booking
					 WHERE BookingConfirmationTitle='BOOKING CONFIRMATION'
					   AND CancelStatus='N' AND Status!='N'",
					array($month_start, $month_end, $week_start, $week_end)
				)->row();
				$cards['bc_week_month'] = array(
					'week'       => (int)$row->week_cnt,
					'month'      => (int)$row->month_cnt,
					'link_month' => $base . $qs(array('booking_date' => $fmt_dmy($month_start) . ' - ' . $fmt_dmy($month_end))),
					'link_week'  => $base . $qs(array('booking_date' => $fmt_dmy($week_start) . ' - ' . $fmt_dmy($week_end))),
				);

				$row = $this->db->query(
					"SELECT COUNT(*) AS total,
					        SUM(CASE WHEN CancelStatus='Y' THEN 1 ELSE 0 END) AS cancelled
					 FROM booking
					 WHERE BookingConfirmationTitle='BOOKING CONFIRMATION' AND Status!='N'
					   AND CAST(InsertDate AS DATE) BETWEEN ? AND ?",
					array($month_start, $month_end)
				)->row();
				$rate = (int)$row->total > 0 ? round(((int)$row->cancelled / (int)$row->total) * 100, 1) : 0;
				$cards['cancellation_rate'] = array(
					'value'  => $rate . '%',
					'detail' => (int)$row->cancelled . ' / ' . (int)$row->total,
					'link'   => $base . $qs(array('status' => 'C', 'booking_date' => $fmt_dmy($month_start) . ' - ' . $fmt_dmy($month_end))),
				);
			}

			$this->load->model('Report_Model');
			$lead_month = $this->Report_Model->Lead_Dashboard_Summary(array('start_date' => $month_start, 'end_date' => $month_end));
			$lead_week  = $this->Report_Model->Lead_Dashboard_Summary(array('start_date' => $week_start,  'end_date' => $week_end));
			$lead_day   = $this->Report_Model->Lead_Dashboard_Summary(array('start_date' => $today,       'end_date' => $today));

			// Owner uses the full calendar quarter for the conversion-related
			// metrics (Cards 8 & 9). A month is too short a lens for owner-
			// level review and resets every 1st. Card 7 ("Leads" total) keeps
			// its month window via $lead_month.
			$conv_start = $is_owner ? $quarter_start : $month_start;
			$conv_end   = $is_owner ? $quarter_end   : $month_end;
			$lead_conv  = $this->Report_Model->Lead_Dashboard_Summary(array('start_date' => $conv_start, 'end_date' => $conv_end));

			$secs = $lead_conv['avg_response_time_seconds'];
			if($secs === null) {
				$resp_label = '-';
			} elseif($secs >= 3600) {
				$resp_label = round($secs / 3600, 1) . 'h';
			} elseif($secs >= 60) {
				$resp_label = round($secs / 60, 1) . 'm';
			} else {
				$resp_label = $secs . 's';
			}

			$cards['leads_dwm'] = array(
				'day'                   => (int)$lead_day['total_leads'],
				'week'                  => (int)$lead_week['total_leads'],
				'month'                 => (int)$lead_month['total_leads'],
				// Card 8 metrics — computed over $conv_start..$conv_end.
				'converted_month'       => (int)$lead_conv['converted_leads'],
				'responded_month'       => (int)$lead_conv['responded_leads'],
				'total_conv'            => (int)$lead_conv['total_leads'],
				'avg_response_seconds'  => $lead_conv['avg_response_time_seconds'],
				'conversion_rate'       => $lead_conv['conversion_rate'] . '%',
				'response_rate'         => $lead_conv['response_rate'] . '%',
				'avg_response_time'     => $resp_label,
			);

			// Active Leads (Day/Week/Month) — unconverted leads still in
			// agents' GHL inboxes, windowed by lead_started_at. is_converted=0
			// is the only "still open" signal we have today; converted_at is
			// not yet trustworthy enough to distinguish closed-won from closed-
			// lost. Headline matches the leads_dwm windows so users can read
			// "X new this month, Y still open" off the same row.
			$row = $this->db->query(
				"SELECT
				   SUM(CASE WHEN pl.lead_started_at BETWEEN ? AND ? THEN 1 ELSE 0 END) AS day_active,
				   SUM(CASE WHEN pl.lead_started_at BETWEEN ? AND ? THEN 1 ELSE 0 END) AS week_active,
				   SUM(CASE WHEN pl.lead_started_at BETWEEN ? AND ? THEN 1 ELSE 0 END) AS month_active
				 FROM ghl_processed_leads pl
				 WHERE pl.is_converted = 0",
				array(
					$today       . ' 00:00:00', $today       . ' 23:59:59',
					$week_start  . ' 00:00:00', $week_end    . ' 23:59:59',
					$month_start . ' 00:00:00', $month_end   . ' 23:59:59',
				)
			)->row();
			$cards['active_leads_dwm'] = array(
				'day'   => (int)$row->day_active,
				'week'  => (int)$row->week_active,
				'month' => (int)$row->month_active,
			);

			$by_agent = $this->Report_Model->Lead_Dashboard_By_Agent(array('start_date' => $conv_start, 'end_date' => $conv_end));
			$top_agents = array();
			foreach(array_slice($by_agent, 0, 10) as $a) {
				$top_agents[] = array(
					'agent_name'      => $a['agent_name'],
					'total_leads'     => (int)$a['total_leads'],
					'converted_leads' => (int)$a['converted_leads'],
					'conversion_rate' => $a['conversion_rate'] . '%',
				);
			}
			$tables['agent_conversion'] = $top_agents;

			// Leads by Agent (Today / Week / Month) — OWNER only. The same
			// new-lead total as the "Leads" card, broken out one row per agent
			// so the owner can read volume agent-by-agent. Windowed by lead
			// start date, identical to the Leads card.
			if($is_owner) {
				$tables['leads_by_agent'] = $this->Report_Model->Lead_Dashboard_Leads_By_Agent_DWM(
					$today, $week_start, $week_end, $month_start, $month_end
				);

				// Pending BC (team) — every booking currently parked at PB
				// ("PENDING BC"), across all agents. Live backlog count.
				$row = $this->db->query(
					"SELECT COUNT(*) AS cnt FROM booking
					 WHERE CancelStatus='N' AND Status='PB'"
				)->row();
				$cards['pending_bc'] = array(
					'count' => (int)$row->cnt,
					'link'  => $base . $qs(array('status' => 'PB')),
				);

				// Submitted -> Payment Time (team, this month). Same submitted_at
				// -> first-P metric as the TC card but company-wide, windowed on
				// the submission date. "Best:" footer = fastest TC this month.
				$this->load->helper(array('submitted_payment_response', 'customer_intake'));
				$sp_start = $month_start . ' 00:00:00';
				$sp_next  = date('Y-m-01 00:00:00', strtotime($month_start . ' +1 month'));
				$sp_row = $this->db->query(
					submitted_payment_avg_response_sql(false),
					array($sp_start, $sp_next)
				)->row();
				$sp_n    = !empty($sp_row) ? (int)$sp_row->n : 0;
				$sp_secs = ($sp_n > 0 && $sp_row->avg_seconds !== null)
					? (int)round((float)$sp_row->avg_seconds) : null;
				$best_sp_row = $this->db->query(
					submitted_payment_best_agent_sql(),
					array($sp_start, $sp_next)
				)->row();
				$best_sp = null;
				if(!empty($best_sp_row) && !empty($best_sp_row->AdminID)) {
					$bd_admin = $this->db->select('Name')
						->where('AdminID', (int)$best_sp_row->AdminID)
						->get('admin')->row();
					$best_sp = array(
						'name'  => $bd_admin ? $bd_admin->Name : '#' . (int)$best_sp_row->AdminID,
						'value' => format_response_duration((int)round((float)$best_sp_row->avg_seconds)),
					);
				}
				$cards['submitted_payment_response_month'] = array(
					'value'   => format_response_duration($sp_secs),
					'count'   => $sp_n,
					'seconds' => $sp_secs,
					'best'    => $best_sp,
				);
			}

			// Closed Sales by Destination (Month) — fully-paid BCs only, ranked
			// by revenue. "Fully paid" matches the TC Total Sales card at
			// line ~716–721 so the two reconcile.
			$paid_subquery = "COALESCE((
				SELECT SUM(p.Credit) FROM payment p
				WHERE p.BookingID = booking.BookingID
				  AND p.Status = 'Y' AND p.Credit > 0
				  AND (p.Type IS NULL OR p.Type != 'AGENT COMMISSION FROM SUPPLIER')
			), 0)";

			$dest_closed_rows = $this->db->query(
				"SELECT category.Name AS destination, category.CategoryID AS id,
				        COUNT(BookingID) AS cnt, COALESCE(SUM(NetTotal),0) AS total
				 FROM booking
				 LEFT JOIN category ON category.CategoryID = booking.Destination
				 WHERE booking.BookingConfirmationTitle='BOOKING CONFIRMATION'
				   AND CancelStatus='N' AND booking.Status!='N'
				   AND booking.NetTotal > 0
				   AND {$paid_subquery} >= booking.NetTotal
				   AND CAST(booking.InsertDate AS DATE) BETWEEN ? AND ?
				 GROUP BY category.Name, category.CategoryID
				 ORDER BY total DESC
				 LIMIT 5",
				array($month_start, $month_end)
			)->result();
			$dest_closed_out = array();
			foreach($dest_closed_rows as $r) {
				$dest_closed_out[] = array(
					'destination' => $r->destination,
					'count'       => (int)$r->cnt,
					'total'       => $money($r->total),
					'link'        => $base . $qs(array(
						'destination'  => $r->id,
						'booking_date' => $fmt_dmy($month_start) . ' - ' . $fmt_dmy($month_end),
					)),
				);
			}
			$tables['destination_closed_sales'] = $dest_closed_out;

			// Active Leads by Tag — unconverted leads bucketed by GHL tag into
			// destination / language / race. Allowlist lives in
			// application/helpers/ghl_tag_categories_helper.php so it can be
			// edited without a migration. Same "active = is_converted=0"
			// scope as the Active Leads card, but sliced by tag instead of
			// by window.
			$this->load->helper('ghl_tag_categories');
			$tables['active_leads_by_tag'] = $this->Report_Model->Active_Leads_By_Tag(
				ghl_tag_categories()
			);

			// Self Gen vs Company (Month) — split BC count + NetTotal by whether
			// booking.Source = 'SELF GEN' (agent's own lead) versus any other
			// source or NULL (company-generated). Attribution is by primary
			// SalesAgent (TC1) regardless of date — NOT the TC1/TC2 credited-slot
			// rule used by Top Agents – Conversion; self-generation is about who
			// hunted the lead, so the primary salesperson is what matters.
			$self_gen = SELF_GEN_SOURCE_NAME;
			$row = $this->db->query(
				"SELECT
				   SUM(CASE WHEN UPPER(TRIM(s.Name)) = ? THEN 1 ELSE 0 END) AS self_gen_cnt,
				   COALESCE(SUM(CASE WHEN UPPER(TRIM(s.Name)) = ? THEN b.NetTotal ELSE 0 END), 0) AS self_gen_total,
				   SUM(CASE WHEN UPPER(TRIM(s.Name)) <> ? OR s.Name IS NULL THEN 1 ELSE 0 END) AS company_cnt,
				   COALESCE(SUM(CASE WHEN UPPER(TRIM(s.Name)) <> ? OR s.Name IS NULL THEN b.NetTotal ELSE 0 END), 0) AS company_total
				 FROM booking b
				 LEFT JOIN source s ON s.SourceID = b.Source
				 WHERE b.BookingConfirmationTitle='BOOKING CONFIRMATION'
				   AND b.CancelStatus='N' AND b.Status!='N'
				   AND CAST(b.InsertDate AS DATE) BETWEEN ? AND ?",
				array($self_gen, $self_gen, $self_gen, $self_gen, $month_start, $month_end)
			)->row();
			$cards['lead_source_split'] = array(
				'self_gen_count' => (int)$row->self_gen_cnt,
				'self_gen_total' => $money($row->self_gen_total),
				'company_count'  => (int)$row->company_cnt,
				'company_total'  => $money($row->company_total),
			);

			// Sales by Agent — Self Gen vs Company (Month). One row per
			// SalesAgent that created at least one BC this month; ordered so
			// agents sharing a team lead appear in consecutive rows (agents
			// without a TeamLeadID sort last). Used as the per-agent / per-team
			// breakdown for the headline Self Gen vs Company card.
			$agent_rows = $this->db->query(
				"SELECT
				   a.AdminID,
				   a.Name AS agent_name,
				   a.TeamLeadID,
				   tl.Name AS team_lead_name,
				   SUM(CASE WHEN UPPER(TRIM(s.Name)) = ? THEN 1 ELSE 0 END) AS self_gen_cnt,
				   COALESCE(SUM(CASE WHEN UPPER(TRIM(s.Name)) = ? THEN b.NetTotal ELSE 0 END), 0) AS self_gen_total,
				   SUM(CASE WHEN UPPER(TRIM(s.Name)) <> ? OR s.Name IS NULL THEN 1 ELSE 0 END) AS company_cnt,
				   COALESCE(SUM(CASE WHEN UPPER(TRIM(s.Name)) <> ? OR s.Name IS NULL THEN b.NetTotal ELSE 0 END), 0) AS company_total,
				   COUNT(*) AS total_cnt
				 FROM booking b
				 INNER JOIN admin a ON a.AdminID = b.SalesAgent
				 LEFT JOIN admin tl ON tl.AdminID = a.TeamLeadID
				 LEFT JOIN source s ON s.SourceID = b.Source
				 WHERE b.BookingConfirmationTitle='BOOKING CONFIRMATION'
				   AND b.CancelStatus='N' AND b.Status!='N'
				   AND CAST(b.InsertDate AS DATE) BETWEEN ? AND ?
				   AND b.SalesAgent IS NOT NULL AND b.SalesAgent > 0
				 GROUP BY a.AdminID, a.Name, a.TeamLeadID, tl.Name
				 ORDER BY (tl.Name IS NULL), tl.Name ASC, a.Name ASC",
				array($self_gen, $self_gen, $self_gen, $self_gen, $month_start, $month_end)
			)->result();
			$rows_out = array();
			foreach($agent_rows as $r) {
				$tot = (int)$r->total_cnt;
				$sg_pct = $tot > 0 ? round(((int)$r->self_gen_cnt / $tot) * 100, 1) : 0;
				$rows_out[] = array(
					'agent_name'      => $r->agent_name,
					'team_lead_name'  => $r->team_lead_name ?: 'Unassigned',
					'self_gen_count'  => (int)$r->self_gen_cnt,
					'self_gen_total'  => $money($r->self_gen_total),
					'company_count'   => (int)$r->company_cnt,
					'company_total'   => $money($r->company_total),
					'self_gen_pct'    => $sg_pct . '%',
				);
			}
			$tables['agent_source_split'] = $rows_out;
		}

		// ---------- OP ----------
		// Owner is scoped to lead-conversion cards only, so they no longer
		// trigger this block.
		if($is_op) {
			if(!isset($cards['bc_week_month'])) {
				$row = $this->db->query(
					"SELECT
					   SUM(CASE WHEN CAST(InsertDate AS DATE) BETWEEN ? AND ? THEN 1 ELSE 0 END) AS month_cnt,
					   SUM(CASE WHEN CAST(InsertDate AS DATE) BETWEEN ? AND ? THEN 1 ELSE 0 END) AS week_cnt
					 FROM booking
					 WHERE BookingConfirmationTitle='BOOKING CONFIRMATION'
					   AND CancelStatus='N' AND Status!='N'",
					array($month_start, $month_end, $week_start, $week_end)
				)->row();
				$cards['bc_week_month'] = array(
					'week'       => (int)$row->week_cnt,
					'month'      => (int)$row->month_cnt,
					'link_month' => $base . $qs(array('booking_date' => $fmt_dmy($month_start) . ' - ' . $fmt_dmy($month_end))),
					'link_week'  => $base . $qs(array('booking_date' => $fmt_dmy($week_start) . ' - ' . $fmt_dmy($week_end))),
				);
			}

			$row = $this->db->query(
				"SELECT
				   SUM(CASE WHEN Status='P'   THEN 1 ELSE 0 END) AS s_p,
				   SUM(CASE WHEN Status='PBO' THEN 1 ELSE 0 END) AS s_pbo,
				   SUM(CASE WHEN Status='PGL' THEN 1 ELSE 0 END) AS s_pgl,
				   SUM(CASE WHEN Status='PTV' THEN 1 ELSE 0 END) AS s_ptv
				 FROM booking
				 WHERE BookingConfirmationTitle='BOOKING CONFIRMATION'
				   AND CancelStatus='N'
				   AND Status IN ('P','PBO','PGL','PTV')
				   AND StartDate BETWEEN ? AND ?",
				array($next7_start, $next7_end)
			)->row();
			$un_op_p   = (int)$row->s_p;
			$un_op_pbo = (int)$row->s_pbo;
			$un_op_pgl = (int)$row->s_pgl;
			$un_op_ptv = (int)$row->s_ptv;
			$cards['upcoming_travel_not_ready_op'] = array(
				'count'  => $un_op_p + $un_op_pbo + $un_op_pgl + $un_op_ptv,
				'by_p'   => $un_op_p,
				'by_pbo' => $un_op_pbo,
				'by_pgl' => $un_op_pgl,
				'by_ptv' => $un_op_ptv,
				'link'   => $base . $qs(array(
					'upcoming_not_ready' => 1,
					'travel_date'        => $fmt_dmy($next7_start) . ' - ' . $fmt_dmy($next7_end),
				)),
			);

			// Team-wide "not yet ready" over the cumulative 14-day window.
			$row = $this->db->query(
				"SELECT
				   SUM(CASE WHEN Status='P'   THEN 1 ELSE 0 END) AS s_p,
				   SUM(CASE WHEN Status='PBO' THEN 1 ELSE 0 END) AS s_pbo,
				   SUM(CASE WHEN Status='PGL' THEN 1 ELSE 0 END) AS s_pgl,
				   SUM(CASE WHEN Status='PTV' THEN 1 ELSE 0 END) AS s_ptv
				 FROM booking
				 WHERE BookingConfirmationTitle='BOOKING CONFIRMATION'
				   AND CancelStatus='N'
				   AND Status IN ('P','PBO','PGL','PTV')
				   AND StartDate BETWEEN ? AND ?",
				array($next14_start, $next14_end)
			)->row();
			$un_op14_p   = (int)$row->s_p;
			$un_op14_pbo = (int)$row->s_pbo;
			$un_op14_pgl = (int)$row->s_pgl;
			$un_op14_ptv = (int)$row->s_ptv;
			$cards['upcoming_travel_not_ready_op_14'] = array(
				'count'  => $un_op14_p + $un_op14_pbo + $un_op14_pgl + $un_op14_ptv,
				'by_p'   => $un_op14_p,
				'by_pbo' => $un_op14_pbo,
				'by_pgl' => $un_op14_pgl,
				'by_ptv' => $un_op14_ptv,
				'link'   => $base . $qs(array(
					'upcoming_not_ready' => 1,
					'travel_date'        => $fmt_dmy($next14_start) . ' - ' . $fmt_dmy($next14_end),
				)),
			);

			// Intake → BC Response Time (Month) — team-wide SLA card for OP.
			// Window uses [start, next-month-start) so submissions on the last
			// day of the month are included.
			// lead_conversion_credit_helper provides lead_conversion_credit_agent_expr()
			// which customer_intake_best_agent_sql() depends on. The TC and TC Lead
			// blocks load it transitively earlier, but the OP block doesn't, so
			// load it explicitly here.
			$this->load->helper('lead_conversion_credit');
			$this->load->helper('customer_intake');
			$intake_month_start_op = $month_start . ' 00:00:00';
			$intake_month_next_op  = date('Y-m-01 00:00:00', strtotime($month_start . ' +1 month'));
			$intake_resp_row = $this->db->query(
				customer_intake_avg_response_sql(false),
				array($intake_month_start_op, $intake_month_next_op)
			)->row();
			$op_intake_n       = !empty($intake_resp_row) ? (int) $intake_resp_row->n : 0;
			$op_intake_seconds = ($op_intake_n > 0 && $intake_resp_row->avg_seconds !== null)
				? (int) round((float) $intake_resp_row->avg_seconds)
				: null;
			$best_intake_row = $this->db->query(
				customer_intake_best_agent_sql(),
				array($intake_month_start_op, $intake_month_next_op)
			)->row();
			$best_intake = null;
			if (!empty($best_intake_row) && !empty($best_intake_row->AdminID)) {
				$best_admin = $this->db->select('Name')
					->where('AdminID', (int) $best_intake_row->AdminID)
					->get('admin')->row();
				$best_intake = array(
					'name'  => $best_admin ? $best_admin->Name : '#' . (int) $best_intake_row->AdminID,
					'value' => format_response_duration((int) round((float) $best_intake_row->avg_seconds)),
				);
			}
			$cards['intake_response_month'] = array(
				'value'   => format_response_duration($op_intake_seconds),
				'count'   => $op_intake_n,
				'seconds' => $op_intake_seconds,
				'best'    => $best_intake,
			);

			$row = $this->db->query(
				"SELECT COUNT(*) AS cnt FROM booking
				 WHERE BookingConfirmationTitle='BOOKING CONFIRMATION'
				   AND CancelStatus='N' AND Status!='N'
				   AND is_submitted=1 AND LockStatus='N'"
			)->row();
			$cards['gl_submitted'] = array(
				'count' => (int)$row->cnt,
				'link'  => $base . $qs(array('guest_list_status' => 'submitted')),
			);

			// Pending Insurance Checklist — bookings with an active line whose
			// product carries an "Insurance" package_checklist that hasn't yet
			// been ticked. disable_checklist_payment_out=0 mirrors the
			// modal/filter rule (see CLAUDE memory feedback_checklist_filter)
			// so the card and the drill-down list agree row-for-row.
			$insurance_ids = $this->db
				->select('ID')
				->from('package_checklist')
				->like('name', 'insurance', 'both')
				->get()
				->result_array();
			$insurance_ids = array_map(function($r){ return (int)$r['ID']; }, $insurance_ids);
			if(!empty($insurance_ids)) {
				$ids_list = implode(',', $insurance_ids);
				$json_contains_or = implode(' OR ', array_map(function($id) {
					return "JSON_CONTAINS(ppc.package_checklist_json, '{$id}')";
				}, $insurance_ids));
				$row = $this->db->query(
					"SELECT COUNT(DISTINCT booking.BookingID) AS cnt
					 FROM booking
					 WHERE booking.BookingConfirmationTitle='BOOKING CONFIRMATION'
					   AND booking.CancelStatus='N' AND booking.Status!='N'
					   AND booking.BookingID IN (
					     SELECT DISTINCT bp.BookingID
					     FROM booking_product bp
					     JOIN product p ON p.ProductID = bp.ProductID AND p.is_child_or_infant = 0
					     JOIN product_package_checklist ppc ON ppc.product_id = bp.ProductID
					       AND ({$json_contains_or})
					     WHERE bp.Status = 'Y'
					       AND bp.disable_checklist_payment_out = 0
					       AND NOT EXISTS (
					         SELECT 1 FROM booking_checklist_completion bcc
					         WHERE bcc.booking_id = bp.BookingID
					           AND bcc.product_id = bp.ProductID
					           AND bcc.package_checklist_id IN ({$ids_list})
					       )
					   )"
				)->row();
				$insurance_count = (int)$row->cnt;
			} else {
				$insurance_count = 0;
			}
			$cards['insurance_pending'] = array(
				'count' => $insurance_count,
				'link'  => $base . $qs(array(
					'checklist_filter' => implode(',', $insurance_ids),
				)),
			);

			// Supplier Pay-out Due Soon — forward-looking mirror of the
			// Finance Supplier Overdue card, scoped to deadlines 1-3 days
			// ahead so the two cards stay disjoint (today and earlier
			// belong to Supplier Overdue). Headline + per-supplier table
			// reuse the same filters; only the Deadline predicate changes.
			$due_soon_start = date('Y-m-d', strtotime('+1 day'));
			$due_soon_end   = date('Y-m-d', strtotime('+3 days'));
			$row = $this->db->query(
				"SELECT COUNT(*) AS cnt, COALESCE(SUM(payment.Debit), 0) AS total_due
				 FROM payment
				 WHERE payment.Status = 'P'
				   AND payment.Deadline BETWEEN ? AND ?
				   AND payment.Debit > 0
				   AND payment.Type LIKE 'SUPPLIER PAYMENT%'
				   AND payment.SupplierID IS NOT NULL",
				array($due_soon_start, $due_soon_end)
			)->row();
			$cards['supplier_due_soon'] = array(
				'count'     => (int)$row->cnt,
				'total_due' => $money($row->total_due),
			);

			$due_rows = $this->db->query(
				"SELECT supplier.SupplierID AS sid, supplier.Name AS name,
				        COUNT(*) AS cnt,
				        COALESCE(SUM(payment.Debit), 0) AS total_due,
				        MIN(payment.Deadline) AS earliest_deadline
				 FROM payment
				 JOIN supplier ON supplier.SupplierID = payment.SupplierID
				 WHERE payment.Status = 'P'
				   AND payment.Deadline BETWEEN ? AND ?
				   AND payment.Debit > 0
				   AND payment.Type LIKE 'SUPPLIER PAYMENT%'
				 GROUP BY supplier.SupplierID, supplier.Name
				 ORDER BY MIN(payment.Deadline) ASC, total_due DESC
				 LIMIT 5",
				array($due_soon_start, $due_soon_end)
			)->result();
			$due_out = array();
			foreach($due_rows as $r) {
				$due_out[] = array(
					'supplier_id'       => (int)$r->sid,
					'name'              => $r->name,
					'count'             => (int)$r->cnt,
					'total_due'         => $money($r->total_due),
					'earliest_deadline' => $r->earliest_deadline ? $fmt_dmy($r->earliest_deadline) : '-',
				);
			}
			$tables['supplier_due_soon'] = $due_out;

			$dest_rows = $this->db->query(
				"SELECT category.Name AS destination, category.CategoryID AS id,
				        COUNT(BookingID) AS cnt, COALESCE(SUM(NetTotal),0) AS total
				 FROM booking
				 LEFT JOIN category ON category.CategoryID = booking.Destination
				 WHERE booking.BookingConfirmationTitle='BOOKING CONFIRMATION'
				   AND CancelStatus='N' AND booking.Status!='N'
				   AND CAST(booking.InsertDate AS DATE) BETWEEN ? AND ?
				 GROUP BY category.Name, category.CategoryID
				 ORDER BY cnt DESC
				 LIMIT 5",
				array($month_start, $month_end)
			)->result();
			$dest_out = array();
			foreach($dest_rows as $r) {
				$dest_out[] = array(
					'destination' => $r->destination,
					'count'       => (int)$r->cnt,
					'total'       => $money($r->total),
					'link'        => $base . $qs(array(
						'destination'  => $r->id,
						'booking_date' => $fmt_dmy($month_start) . ' - ' . $fmt_dmy($month_end),
					)),
				);
			}
			$tables['destination_sales'] = $dest_out;

			// OP Top Products (Month) — same query Finance already runs; OP
			// historically only saw destinations. Surfacing products here lets
			// OP spot which package codes are driving the workload.
			$prod_rows = $this->db->query(
				"SELECT product.ProductCode AS code, product.Name AS name,
				        COALESCE(SUM(booking_product.Total),0) AS total,
				        COALESCE(SUM(booking_product.Quantity),0) AS qty
				 FROM booking
				 LEFT JOIN booking_product ON booking_product.BookingID = booking.BookingID
				 LEFT JOIN product ON product.ProductID = booking_product.ProductID
				 WHERE booking.BookingConfirmationTitle='BOOKING CONFIRMATION'
				   AND booking.CancelStatus='N' AND booking.Status!='N'
				   AND booking_product.Status='Y'
				   AND CAST(booking.InsertDate AS DATE) BETWEEN ? AND ?
				   AND product.ProductID IS NOT NULL
				 GROUP BY product.ProductID, product.ProductCode, product.Name
				 ORDER BY total DESC
				 LIMIT 5",
				array($month_start, $month_end)
			)->result();
			$prod_out = array();
			foreach($prod_rows as $r) {
				$prod_out[] = array(
					'code'  => $r->code,
					'name'  => $r->name,
					'qty'   => (int)$r->qty,
					'total' => $money($r->total),
				);
			}
			$tables['product_sales'] = $prod_out;
		}

		// ---------- Finance ----------
		// Owner is scoped to lead-conversion cards only, so they no longer
		// trigger this block.
		if($is_finance) {
			$row = $this->db->query(
				"SELECT
				   COALESCE(SUM(CASE WHEN Date BETWEEN ? AND ? THEN Credit ELSE 0 END),0) AS day_total,
				   COALESCE(SUM(CASE WHEN Date BETWEEN ? AND ? THEN Credit ELSE 0 END),0) AS week_total,
				   COALESCE(SUM(CASE WHEN Date BETWEEN ? AND ? THEN Credit ELSE 0 END),0) AS month_total,
				   SUM(CASE WHEN Date BETWEEN ? AND ? THEN 1 ELSE 0 END) AS day_count,
				   SUM(CASE WHEN Date BETWEEN ? AND ? THEN 1 ELSE 0 END) AS week_count,
				   SUM(CASE WHEN Date BETWEEN ? AND ? THEN 1 ELSE 0 END) AS month_count
				 FROM payment
				 WHERE Status='Y' AND Credit > 0
				   AND (Type IS NULL OR Type != 'AGENT COMMISSION FROM SUPPLIER')",
				array(
					$today, $today, $week_start, $week_end, $month_start, $month_end,
					$today, $today, $week_start, $week_end, $month_start, $month_end,
				)
			)->row();
			$cards['payment_in_dwm'] = array(
				'day'         => $money($row->day_total),
				'week'        => $money($row->week_total),
				'month'       => $money($row->month_total),
				'day_count'   => (int)$row->day_count,
				'week_count'  => (int)$row->week_count,
				'month_count' => (int)$row->month_count,
				'day_raw'     => (float)$row->day_total,
				'week_raw'    => (float)$row->week_total,
				'month_raw'   => (float)$row->month_total,
			);

			if(!isset($tables['destination_sales'])) {
				$dest_rows = $this->db->query(
					"SELECT category.Name AS destination, category.CategoryID AS id,
					        COUNT(BookingID) AS cnt, COALESCE(SUM(NetTotal),0) AS total
					 FROM booking
					 LEFT JOIN category ON category.CategoryID = booking.Destination
					 WHERE booking.BookingConfirmationTitle='BOOKING CONFIRMATION'
					   AND CancelStatus='N' AND booking.Status!='N'
					   AND CAST(booking.InsertDate AS DATE) BETWEEN ? AND ?
					 GROUP BY category.Name, category.CategoryID
					 ORDER BY total DESC
					 LIMIT 5",
					array($month_start, $month_end)
				)->result();
				$dest_out = array();
				foreach($dest_rows as $r) {
					$dest_out[] = array(
						'destination' => $r->destination,
						'count'       => (int)$r->cnt,
						'total'       => $money($r->total),
						'link'        => $base . $qs(array(
							'destination'  => $r->id,
							'booking_date' => $fmt_dmy($month_start) . ' - ' . $fmt_dmy($month_end),
						)),
					);
				}
				$tables['destination_sales'] = $dest_out;
			}

			$prod_rows = $this->db->query(
				"SELECT product.ProductCode AS code, product.Name AS name,
				        COALESCE(SUM(booking_product.Total),0) AS total,
				        COALESCE(SUM(booking_product.Quantity),0) AS qty
				 FROM booking
				 LEFT JOIN booking_product ON booking_product.BookingID = booking.BookingID
				 LEFT JOIN product ON product.ProductID = booking_product.ProductID
				 WHERE booking.BookingConfirmationTitle='BOOKING CONFIRMATION'
				   AND booking.CancelStatus='N' AND booking.Status!='N'
				   AND booking_product.Status='Y'
				   AND CAST(booking.InsertDate AS DATE) BETWEEN ? AND ?
				   AND product.ProductID IS NOT NULL
				 GROUP BY product.ProductID, product.ProductCode, product.Name
				 ORDER BY total DESC
				 LIMIT 5",
				array($month_start, $month_end)
			)->result();
			$prod_out = array();
			foreach($prod_rows as $r) {
				$prod_out[] = array(
					'code'  => $r->code,
					'name'  => $r->name,
					'qty'   => (int)$r->qty,
					'total' => $money($r->total),
				);
			}
			$tables['product_sales'] = $prod_out;

			// Sales by Team (Month) — group NetTotal by team-lead admin via
			// SalesAgent -> admin.TeamLeadID -> admin.AdminID. Agents with no
			// team lead collapse into a single "Unassigned" row so the
			// breakdown reconciles to the team-wide total.
			$team_rows = $this->db->query(
				"SELECT COALESCE(tl.AdminID, 0) AS team_lead_id,
				        COALESCE(tl.Name, 'Unassigned') AS team_lead_name,
				        COUNT(*) AS cnt,
				        COALESCE(SUM(booking.NetTotal), 0) AS total
				 FROM booking
				 LEFT JOIN admin agent ON agent.AdminID = booking.SalesAgent
				 LEFT JOIN admin tl    ON tl.AdminID    = agent.TeamLeadID
				 WHERE booking.BookingConfirmationTitle='BOOKING CONFIRMATION'
				   AND booking.CancelStatus='N' AND booking.Status!='N'
				   AND CAST(booking.InsertDate AS DATE) BETWEEN ? AND ?
				 GROUP BY team_lead_id, team_lead_name
				 ORDER BY total DESC",
				array($month_start, $month_end)
			)->result();
			$team_out = array();
			foreach($team_rows as $r) {
				$team_out[] = array(
					'team_lead_id'   => (int)$r->team_lead_id,
					'team_lead_name' => $r->team_lead_name,
					'count'          => (int)$r->cnt,
					'total'          => $money($r->total),
				);
			}
			$tables['sales_by_team'] = $team_out;

			// Supplier Overdue — payment-out rows past their Deadline still
			// pending payment. Card surfaces the grand totals; table breaks
			// down by supplier so Finance can see who's most exposed. SUM of
			// per-supplier totals reconciles with the headline.
			$row = $this->db->query(
				"SELECT COUNT(*) AS cnt, COALESCE(SUM(payment.Debit), 0) AS total_due
				 FROM payment
				 WHERE payment.Status = 'P'
				   AND payment.Deadline < ?
				   AND payment.Debit > 0
				   AND payment.Type LIKE 'SUPPLIER PAYMENT%'
				   AND payment.SupplierID IS NOT NULL",
				array($today)
			)->row();
			$cards['supplier_overdue'] = array(
				'count'     => (int)$row->cnt,
				'total_due' => $money($row->total_due),
			);

			$sup_rows = $this->db->query(
				"SELECT supplier.SupplierID AS sid, supplier.Name AS name,
				        COUNT(*) AS cnt,
				        COALESCE(SUM(payment.Debit), 0) AS total_due,
				        MIN(payment.Deadline) AS earliest_deadline
				 FROM payment
				 JOIN supplier ON supplier.SupplierID = payment.SupplierID
				 WHERE payment.Status = 'P'
				   AND payment.Deadline < ?
				   AND payment.Debit > 0
				   AND payment.Type LIKE 'SUPPLIER PAYMENT%'
				 GROUP BY supplier.SupplierID, supplier.Name
				 ORDER BY total_due DESC
				 LIMIT 5",
				array($today)
			)->result();
			$sup_out = array();
			foreach($sup_rows as $r) {
				$sup_out[] = array(
					'supplier_id'       => (int)$r->sid,
					'name'              => $r->name,
					'count'             => (int)$r->cnt,
					'total_due'         => $money($r->total_due),
					'earliest_deadline' => $r->earliest_deadline ? $fmt_dmy($r->earliest_deadline) : '-',
				);
			}
			$tables['supplier_overdue'] = $sup_out;
		}

		$meta = array();
		$meta['selected_month']       = $period['value'];
		$meta['selected_month_label'] = $period['label'];
		if($is_owner) {
			$row = $this->db
				->select('completed_at')
				->from('ghl_sync_run_log')
				->where('status', 'completed')
				->where('completed_at IS NOT NULL', null, false)
				->order_by('completed_at', 'DESC')
				->limit(1)
				->get()
				->row();
			$last = (!empty($row) && !empty($row->completed_at)) ? $row->completed_at : null;
			$ts = $last ? strtotime($last) : false;
			$meta['last_ghl_sync']         = $last;
			$meta['last_ghl_sync_display'] = $ts ? date('j M Y, H:i', $ts) : 'Never';
		}

		// ---------- Popover content (value-rich, with concrete dates) ----------
		// Each popover follows: Formula → Window → This card (with breakdown
		// and math) → Filters / Excludes. Built server-side so the same date
		// and money formatters that produce the card faces produce the
		// tooltip copy — guarantees the two cannot disagree.
		$popovers = array();
		$fmt_disp = function($d) { return date('j M Y', strtotime($d)); };
		$rng_disp = function($a, $b) use ($fmt_disp) {
			return $fmt_disp($a) . ' &ndash; ' . $fmt_disp($b);
		};
		$plural = function($n, $singular, $plural = null) {
			$word = ($plural === null) ? $singular . 's' : $plural;
			return ((int)$n === 1) ? $singular : $word;
		};
		$tc2_cutoff_disp = '1 Jun 2026';

		$window_month = $rng_disp($month_start, $month_end);
		// Conversion-card window: matches what was queried into $lead_conv /
		// $by_agent above. Owner = current calendar quarter, everyone else =
		// current month. Used only by Card 8 / Card 9 popovers.
		$conv_start_pop = $is_owner ? $quarter_start : $month_start;
		$conv_end_pop   = $is_owner ? $quarter_end   : $month_end;
		$window_conv    = $rng_disp($conv_start_pop, $conv_end_pop);
		$conv_label     = $is_owner ? 'this quarter' : 'this month';
		$window_week  = $rng_disp($week_start, $week_end);
		$window_next7  = $rng_disp($next7_start, $next7_end);
		$window_next14 = $rng_disp($next14_start, $next14_end);

		// TC cards
		if(isset($cards['bc_month'])) {
			$n = (int)$cards['bc_month']['count'];
			$popovers['pop-bc-month'] =
				'<strong>Formula:</strong> Count of confirmations credited to you this month.<br><br>' .
				'<strong>Window:</strong> ' . $window_month . ' (by creation date)<br>' .
				'<strong>This card:</strong><br>' .
				$n . ' ' . $plural($n, 'BC') . ' credited to you &rarr; <strong>' . $n . '</strong><br><br>' .
				'<strong>Credited-slot rule (TC1/TC2):</strong>' .
				'<ul>' .
				'<li>Before ' . $tc2_cutoff_disp . ': you hold TC1 (primary)</li>' .
				'<li>From ' . $tc2_cutoff_disp . ': you hold TC2 (secondary)</li>' .
				'</ul>' .
				'<strong>Excludes:</strong> Quotations, cancelled, drafts.';
		}

		if(isset($cards['sales_month']) && isset($cards['bc_month'])) {
			$n = (int)$cards['bc_month']['count'];
			$popovers['pop-sales-month'] =
				'<strong>Formula:</strong> Sum of NetTotal across BCs credited to you this month.<br><br>' .
				'<strong>Window:</strong> ' . $window_month . ' (by creation date)<br>' .
				'<strong>This card:</strong><br>' .
				$n . ' ' . $plural($n, 'BC') . ' counted (same as BC Created)<br>' .
				'Sum of NetTotal &rarr; <strong>' . $cards['sales_month']['value'] . '</strong><br><br>' .
				'<strong>NetTotal:</strong> BC price after discount, before any later refunds.<br>' .
				'<strong>Excludes:</strong> Cancelled, drafts. Later refunds not subtracted.';
		}

		if(isset($cards['sales_year'])) {
			$popovers['pop-sales-year'] =
				'<strong>Formula:</strong> Sum of NetTotal across your fully-paid BCs for the year.<br><br>' .
				'<strong>Window:</strong> ' . $rng_disp($year_start, $year_end) . ' (by creation date)<br>' .
				'<strong>This card:</strong><br>' .
				'Year sales &rarr; <strong>' . $cards['sales_year']['value'] . '</strong><br>' .
				'Yearly target &rarr; <strong>' . $cards['sales_year']['target'] . '</strong> (' . $cards['sales_year']['percent'] . ')<br><br>' .
				'<strong>Fully paid</strong> = approved customer payments (Status Y, excl. agent commission) &ge; NetTotal.<br>' .
				'<strong>Target:</strong> Set per TC per year under Admin &rarr; Yearly Target. Percent = actual &divide; target &times; 100.<br>' .
				'<strong>Excludes:</strong> Cancelled, drafts, partial / unpaid BCs.';
		}

		if(isset($cards['cancellation_rate']) && $is_tc) {
			$detail   = $cards['cancellation_rate']['detail']; // "5 / 20"
			$rate     = $cards['cancellation_rate']['value'];  // "25%"
			$parts    = explode(' / ', $detail);
			$canc     = isset($parts[0]) ? (int)$parts[0] : 0;
			$total    = isset($parts[1]) ? (int)$parts[1] : 0;
			$math     = ($total > 0)
				? ($canc . ' &divide; ' . $total . ' &times; 100 = <strong>' . $rate . '</strong>')
				: 'No BCs this month &rarr; <strong>0%</strong>';
			$popovers['pop-cancel-rate'] =
				'<strong>Formula:</strong> Cancelled &divide; Total &times; 100<br><br>' .
				'<strong>Window:</strong> ' . $window_month . ' (by creation date)<br>' .
				'<strong>This card (your BCs):</strong><br>' .
				$canc . ' cancelled / ' . $total . ' total BCs<br>' .
				'&rarr; ' . $math . '<br><br>' .
				'<strong>Filters:</strong> Drafts excluded. Your BCs only (credited-slot rule).<br>' .
				'<strong>Note:</strong> Based on creation date, not cancellation date.';
		}

		if(isset($cards['payment_overdue'])) {
			$po_total   = (int)$cards['payment_overdue']['count'];
			$po_full    = (int)$cards['payment_overdue']['full_overdue'];
			$po_deposit = (int)$cards['payment_overdue']['deposit_only_overdue'];
			$popovers['pop-payment-overdue'] =
				'<strong>Counted when EITHER:</strong>' .
				'<ul>' .
				'<li>Full payment deadline passed AND BC still owes balance (Status <code>P</code> or <code>PP</code>)</li>' .
				'<li>Deposit deadline passed AND deposit still unpaid (Status <code>P</code>)</li>' .
				'</ul>' .
				'<strong>As of:</strong> ' . $fmt_disp($today) . ' (no date window)<br>' .
				'<strong>Due today:</strong> counts as overdue from 3:00pm onward.<br>' .
				'<strong>This card (your BCs):</strong><br>' .
				'Full-payment overdue: ' . $po_full . ' ' . $plural($po_full, 'BC') . '<br>' .
				'Deposit overdue (full not yet due): ' . $po_deposit . ' ' . $plural($po_deposit, 'BC') . '<br>' .
				'&rarr; <strong>' . $po_total . ' ' . $plural($po_total, 'BC') . '</strong><br><br>' .
				'<strong>Status codes:</strong> <code>P</code> = waiting for payment · <code>PP</code> = deposit paid, balance pending.<br>' .
				'<strong>Excludes:</strong> Cancelled, fully-paid BCs.';
		}

		if(isset($cards['upcoming_travel_not_ready'])) {
			$u   = $cards['upcoming_travel_not_ready'];
			$tot = (int)$u['count'];
			$popovers['pop-upcoming-not-ready'] =
				'<strong>"Not yet ready" upstream stages:</strong> Payment / Booking Op / Guest List / Travel Voucher.<br><br>' .
				'<strong>Window:</strong> ' . $window_next7 . ' (by travel start date)<br>' .
				'<strong>This card (your BCs):</strong><br>' .
				'<code>P</code> Payment: ' . (int)$u['by_p'] . '<br>' .
				'<code>PBO</code> Booking Op: ' . (int)$u['by_pbo'] . '<br>' .
				'<code>PGL</code> Guest List: ' . (int)$u['by_pgl'] . '<br>' .
				'<code>PTV</code> Travel Voucher: ' . (int)$u['by_ptv'] . '<br>' .
				'&rarr; <strong>' . $tot . ' ' . $plural($tot, 'BC') . '</strong><br><br>' .
				'<strong>Excludes:</strong> Cancelled. "Ready" (Pending Travel and beyond) not counted.<br>' .
				'<strong>Why it matters:</strong> Guests travel within a week.';
		}

		if(isset($cards['upcoming_travel_not_ready_14'])) {
			$u   = $cards['upcoming_travel_not_ready_14'];
			$tot = (int)$u['count'];
			$popovers['pop-upcoming-not-ready-14'] =
				'<strong>"Not yet ready" upstream stages:</strong> Payment / Booking Op / Guest List / Travel Voucher.<br><br>' .
				'<strong>Window:</strong> ' . $window_next14 . ' (by travel start date)<br>' .
				'<strong>This card (your BCs):</strong><br>' .
				'<code>P</code> Payment: ' . (int)$u['by_p'] . '<br>' .
				'<code>PBO</code> Booking Op: ' . (int)$u['by_pbo'] . '<br>' .
				'<code>PGL</code> Guest List: ' . (int)$u['by_pgl'] . '<br>' .
				'<code>PTV</code> Travel Voucher: ' . (int)$u['by_ptv'] . '<br>' .
				'&rarr; <strong>' . $tot . ' ' . $plural($tot, 'BC') . '</strong><br><br>' .
				'<strong>Includes</strong> the "within 7 days" set (cumulative window).<br>' .
				'<strong>Excludes:</strong> Cancelled. "Ready" (Pending Travel and beyond) not counted.<br>' .
				'<strong>Why it matters:</strong> Two-week heads-up to get BCs ready.';
		}

		// TC LEAD / Owner cards
		if(isset($cards['bc_week_month'])) {
			$w = (int)$cards['bc_week_month']['week'];
			$m = (int)$cards['bc_week_month']['month'];
			$bc_wm_html =
				'<strong>Formula:</strong> Count of booking confirmations across all sales agents.<br><br>' .
				'<strong>Windows (by creation date):</strong><br>' .
				'Week: ' . $window_week . ' (Mon&ndash;Sun)<br>' .
				'Month: ' . $window_month . '<br>' .
				'<strong>This card (team-wide):</strong><br>' .
				'Week &rarr; <strong>' . $w . '</strong> ' . $plural($w, 'BC') . '<br>' .
				'Month &rarr; <strong>' . $m . '</strong> ' . $plural($m, 'BC') . '<br><br>' .
				'<strong>Excludes:</strong> Quotations, cancelled, drafts.';
			$popovers['pop-bc-week-month-tl'] = $bc_wm_html;
			$popovers['pop-bc-week-month-op'] = $bc_wm_html;
		}

		if(isset($cards['cancellation_rate']) && $is_tclead) {
			$detail   = $cards['cancellation_rate']['detail'];
			$rate     = $cards['cancellation_rate']['value'];
			$parts    = explode(' / ', $detail);
			$canc     = isset($parts[0]) ? (int)$parts[0] : 0;
			$total    = isset($parts[1]) ? (int)$parts[1] : 0;
			$math     = ($total > 0)
				? ($canc . ' &divide; ' . $total . ' &times; 100 = <strong>' . $rate . '</strong>')
				: 'No BCs this month &rarr; <strong>0%</strong>';
			$popovers['pop-cancel-rate-tl'] =
				'<strong>Formula:</strong> Cancelled &divide; Total &times; 100<br><br>' .
				'<strong>Window:</strong> ' . $window_month . ' (by creation date)<br>' .
				'<strong>This card (team-wide):</strong><br>' .
				$canc . ' cancelled / ' . $total . ' total BCs<br>' .
				'&rarr; ' . $math . '<br><br>' .
				'<strong>Filters:</strong> Drafts excluded. All agents counted.<br>' .
				'<strong>Note:</strong> Based on creation date, not cancellation date.';
		}

		if(isset($cards['leads_dwm'])) {
			$ld = $cards['leads_dwm'];
			$popovers['pop-leads-dwm'] =
				'<strong>Source:</strong> Lead conversations synced from GHL.<br><br>' .
				'<strong>Windows (by lead creation date):</strong><br>' .
				'Today: ' . $fmt_disp($today) . '<br>' .
				'Week: ' . $window_week . ' (Mon&ndash;Sun)<br>' .
				'Month: ' . $window_month . '<br>' .
				'<strong>This card:</strong><br>' .
				'Today &rarr; <strong>' . (int)$ld['day']   . '</strong> ' . $plural($ld['day'], 'lead') . '<br>' .
				'Week &rarr; <strong>' . (int)$ld['week']  . '</strong> ' . $plural($ld['week'], 'lead') . '<br>' .
				'Month &rarr; <strong>' . (int)$ld['month'] . '</strong> ' . $plural($ld['month'], 'lead') . '<br><br>' .
				'Each GHL conversation = 1 lead. Re-entries to the same conversation don\'t double-count.';

			$total_leads = (int)$ld['total_conv'];
			$converted   = (int)$ld['converted_month'];
			$responded   = (int)$ld['responded_month'];
			$conv_math = ($total_leads > 0)
				? ($converted . ' &divide; ' . $total_leads . ' &times; 100 = <strong>' . $ld['conversion_rate'] . '</strong>')
				: 'No leads ' . $conv_label . ' &rarr; <strong>0%</strong>';
			$resp_math = ($total_leads > 0)
				? ($responded . ' &divide; ' . $total_leads . ' &times; 100 = <strong>' . $ld['response_rate'] . '</strong>')
				: 'No leads ' . $conv_label . ' &rarr; <strong>0%</strong>';
			$avg_secs = $ld['avg_response_seconds'];
			$avg_detail = ($avg_secs === null)
				? '<strong>Avg Time:</strong> n/a (no on-duty replies measured)'
				: '<strong>Avg Time:</strong> ' . $ld['avg_response_time'] . ' (mean of each lead\'s first 5 TC reply gaps; fewer than 5 replies counts all available; <em>replies sent outside duty hours are excluded</em>)';
			$popovers['pop-leads-conv'] =
				'<strong>Window:</strong> ' . $window_conv . ' (all leads created ' . $conv_label . ', all agents)<br>' .
				'<strong>This card (' . $total_leads . ' total leads):</strong><br>' .
				'Converted: ' . $converted . ' &rarr; Conversion ' . $conv_math . '<br>' .
				'Responded: ' . $responded . ' &rarr; Response ' . $resp_math . '<br>' .
				$avg_detail . '<br><br>' .
				'<strong>Converted:</strong> Lead linked to a BC AND the TC has sales credit (TC1 before ' . $tc2_cutoff_disp . '; TC2 from ' . $tc2_cutoff_disp . ').<br>' .
				'<strong>Responded:</strong> Lead has at least one TC reply.<br>' .
				'<strong>Duty hours:</strong> Mon&ndash;Sat 09:00&ndash;18:00 MYT &mdash; only on-duty replies feed the Avg Time.';
		}

		if(isset($cards['active_leads_dwm'])) {
			$a = $cards['active_leads_dwm'];
			$popovers['pop-active-leads'] =
				'<strong>Formula:</strong> Count of GHL leads not yet converted to a BC, windowed by lead start date.<br><br>' .
				'<strong>Windows (by lead creation date):</strong><br>' .
				'Today: ' . $fmt_disp($today) . '<br>' .
				'Week: ' . $window_week . ' (Mon&ndash;Sun)<br>' .
				'Month: ' . $window_month . '<br>' .
				'<strong>This card:</strong><br>' .
				'Today &rarr; <strong>' . (int)$a['day']   . '</strong> ' . $plural($a['day'],   'lead') . ' still open<br>' .
				'Week &rarr; <strong>'  . (int)$a['week']  . '</strong> ' . $plural($a['week'],  'lead') . ' still open<br>' .
				'Month &rarr; <strong>' . (int)$a['month'] . '</strong> ' . $plural($a['month'], 'lead') . ' still open<br><br>' .
				'<strong>Open =</strong> <code>is_converted = 0</code> &mdash; no linked BC yet.<br>' .
				'<strong>Pair with:</strong> &quot;Leads&quot; (total) to see open vs converted at a glance.';
		}

		if(isset($tables['agent_conversion'])) {
			$rows = count($tables['agent_conversion']);
			$popovers['pop-agent-conversion'] =
				'<strong>Window:</strong> ' . $window_conv . '<br><br>' .
				'<strong>Per agent:</strong>' .
				'<ul>' .
				'<li>Leads &mdash; total leads assigned ' . $conv_label . '</li>' .
				'<li>Converted &mdash; leads with a linked BC where the agent has sales credit (TC1 before ' . $tc2_cutoff_disp . '; TC2 from ' . $tc2_cutoff_disp . ')</li>' .
				'<li>Rate &mdash; Converted &divide; Leads &times; 100</li>' .
				'</ul>' .
				'<strong>Sort:</strong> By total leads (highest first), then agent name. Top 10.<br>' .
				'<strong>This card:</strong> ' . $rows . ' ' . $plural($rows, 'agent') . ' shown.<br>' .
				'<strong>Excludes:</strong> Unassigned leads.';
		}

		if(isset($tables['leads_by_agent'])) {
			$rows = count($tables['leads_by_agent']);
			$popovers['pop-leads-by-agent'] =
				'<strong>Source:</strong> New lead conversations synced from GHL, broken out per agent.<br><br>' .
				'<strong>Windows (by lead creation date):</strong><br>' .
				'Today: ' . $fmt_disp($today) . '<br>' .
				'Week: ' . $window_week . ' (Mon&ndash;Sun)<br>' .
				'Month: ' . $window_month . '<br><br>' .
				'<strong>Per agent:</strong> count of new leads assigned to that agent in each window. The windows nest &mdash; a lead created today is also counted in this week and this month.<br><br>' .
				'<strong>Sort:</strong> By month leads (highest first), then week, day, agent name.<br>' .
				'<strong>This card:</strong> ' . $rows . ' ' . $plural($rows, 'agent') . ' shown.<br>' .
				'<strong>Excludes:</strong> Unassigned leads.';
		}

		// OP cards
		if(isset($cards['upcoming_travel_not_ready_op'])) {
			$u   = $cards['upcoming_travel_not_ready_op'];
			$tot = (int)$u['count'];
			$popovers['pop-upcoming-not-ready-op'] =
				'<strong>"Not yet ready" upstream stages:</strong> Payment / Booking Op / Guest List / Travel Voucher.<br><br>' .
				'<strong>Window:</strong> ' . $window_next7 . ' (by travel start date)<br>' .
				'<strong>This card (team-wide):</strong><br>' .
				'<code>P</code> Payment: ' . (int)$u['by_p'] . '<br>' .
				'<code>PBO</code> Booking Op: ' . (int)$u['by_pbo'] . '<br>' .
				'<code>PGL</code> Guest List: ' . (int)$u['by_pgl'] . '<br>' .
				'<code>PTV</code> Travel Voucher: ' . (int)$u['by_ptv'] . '<br>' .
				'&rarr; <strong>' . $tot . ' ' . $plural($tot, 'BC') . '</strong><br><br>' .
				'<strong>Excludes:</strong> Cancelled. "Ready" (Pending Travel and beyond) not counted.<br>' .
				'<strong>Why it matters:</strong> Guests travel within a week.';
		}

		if(isset($cards['upcoming_travel_not_ready_op_14'])) {
			$u   = $cards['upcoming_travel_not_ready_op_14'];
			$tot = (int)$u['count'];
			$popovers['pop-upcoming-not-ready-op-14'] =
				'<strong>"Not yet ready" upstream stages:</strong> Payment / Booking Op / Guest List / Travel Voucher.<br><br>' .
				'<strong>Window:</strong> ' . $window_next14 . ' (by travel start date)<br>' .
				'<strong>This card (team-wide):</strong><br>' .
				'<code>P</code> Payment: ' . (int)$u['by_p'] . '<br>' .
				'<code>PBO</code> Booking Op: ' . (int)$u['by_pbo'] . '<br>' .
				'<code>PGL</code> Guest List: ' . (int)$u['by_pgl'] . '<br>' .
				'<code>PTV</code> Travel Voucher: ' . (int)$u['by_ptv'] . '<br>' .
				'&rarr; <strong>' . $tot . ' ' . $plural($tot, 'BC') . '</strong><br><br>' .
				'<strong>Includes</strong> the "within 7 days" set (cumulative window).<br>' .
				'<strong>Excludes:</strong> Cancelled. "Ready" (Pending Travel and beyond) not counted.<br>' .
				'<strong>Why it matters:</strong> Two-week heads-up to get BCs ready.';
		}

		if(isset($cards['insurance_pending'])) {
			$ip = (int)$cards['insurance_pending']['count'];
			$popovers['pop-insurance-pending'] =
				'<strong>Counted when, for an active line item:</strong>' .
				'<ul>' .
				'<li>Product carries an Insurance package checklist</li>' .
				'<li>No completion record yet for that checklist on that line</li>' .
				'<li><code>booking_product.disable_checklist_payment_out = 0</code> (the same rule the modal/filter uses)</li>' .
				'<li>BC, not cancelled, not draft</li>' .
				'</ul>' .
				'<strong>Live queue &middot; as of ' . $fmt_disp($today) . '</strong> &mdash; no date filter.<br>' .
				'<strong>This card:</strong> ' .
				'Insurance pending &rarr; <strong>' . $ip . ' ' . $plural($ip, 'BC') . '</strong><br><br>' .
				'<strong>Action:</strong> Click to filter the list to these BCs and tick off insurance.';
		}

		if(isset($cards['gl_submitted'])) {
			$g = (int)$cards['gl_submitted']['count'];
			$popovers['pop-gl-submitted'] =
				'<strong>Counted when:</strong>' .
				'<ul>' .
				'<li>Customer has submitted (<code>is_submitted=1</code>)</li>' .
				'<li>OP has not yet locked (<code>LockStatus=N</code>)</li>' .
				'<li>Booking confirmation; not cancelled, not draft</li>' .
				'</ul>' .
				'<strong>Live queue &middot; as of ' . $fmt_disp($today) . '</strong> &mdash; no date filter.<br>' .
				'<strong>This card:</strong> ' .
				'Submitted, not yet locked &rarr; <strong>' . $g . ' ' . $plural($g, 'BC') . '</strong><br><br>' .
				'<strong>Action:</strong> Review for completeness, then lock to stop further customer edits.';
		}

		if(isset($tables['destination_sales'])) {
			$rows = count($tables['destination_sales']);
			$is_op_view = $is_op;
			$sort_line = $is_op_view
				? '<strong>Sort:</strong> By BC count (highest first). Top 5.'
				: '<strong>Sort:</strong> By total sales (highest first). Top 5.';
			$dest_html =
				'<strong>Window:</strong> ' . $window_month . ' (by creation date)<br><br>' .
				'<strong>Per destination:</strong>' .
				'<ul>' .
				'<li>BC &mdash; how many bookings</li>' .
				'<li>Sales &mdash; sum of NetTotal</li>' .
				'</ul>' .
				$sort_line . '<br>' .
				'<strong>This card:</strong> ' . $rows . ' ' . $plural($rows, 'destination') . ' shown.<br>' .
				'<strong>Filters:</strong> Booking confirmations only; not cancelled; not draft.<br>' .
				'<strong>Tip:</strong> Click a row to filter the booking list by destination.';
			if($is_op_view) {
				$popovers['pop-destination-sales-op'] = $dest_html;
			} else {
				$popovers['pop-destination-sales-fin'] = $dest_html;
			}
		}

		if(isset($tables['destination_closed_sales'])) {
			$rows = count($tables['destination_closed_sales']);
			$popovers['pop-destination-closed-sales'] =
				'<strong>Window:</strong> ' . $window_month . ' (BCs created this month)<br><br>' .
				'<strong>Per destination:</strong>' .
				'<ul>' .
				'<li>BC &mdash; how many fully-paid bookings</li>' .
				'<li>Sales &mdash; sum of NetTotal across those BCs</li>' .
				'</ul>' .
				'<strong>Sort:</strong> By total sales (highest first). Top 5.<br>' .
				'<strong>This card:</strong> ' . $rows . ' ' . $plural($rows, 'destination') . ' shown.<br>' .
				'<strong>Filters:</strong> Booking confirmations only; not cancelled; not draft; ' .
				'sum of approved customer payments (excluding agent commission) &ge; NetTotal &mdash; ' .
				'i.e. revenue is fully collected. Same definition as the TC Total Sales card.';
		}

		if(isset($tables['active_leads_by_tag'])) {
			$by_tag = $tables['active_leads_by_tag'];
			$dest_n = isset($by_tag['destination']) ? count($by_tag['destination']) : 0;
			$lang_n = isset($by_tag['language'])    ? count($by_tag['language'])    : 0;
			$race_n = isset($by_tag['race'])        ? count($by_tag['race'])        : 0;
			$popovers['pop-active-leads-by-tag'] =
				'<strong>Scope:</strong> All active (unconverted) leads currently ' .
				'residing in agents&rsquo; GHL inboxes &mdash; same lead set as the ' .
				'"Active Leads" card, just sliced by tag instead of by window.<br><br>' .
				'<strong>Per dimension:</strong>' .
				'<ul>' .
				'<li>Destination &mdash; country / island / region tags on the GHL conversation</li>' .
				'<li>Language &mdash; conversation language tags (bm / en / cn)</li>' .
				'<li>Race &mdash; flags for halal / dietary tagging (e.g. muslim)</li>' .
				'</ul>' .
				'<strong>Match:</strong> Case-insensitive exact-string against an allowlist ' .
				'(see <code>ghl_tag_categories_helper.php</code>). Substring matches do not count, ' .
				'so &ldquo;redang052026&rdquo; is not lumped into &ldquo;redang&rdquo;.<br>' .
				'<strong>Dedup:</strong> A lead carrying the same tag twice counts once. A lead ' .
				'carrying tags in multiple dimensions counts in each of them (the three tables ' .
				'are disjoint views over the same lead set).<br>' .
				'<strong>Sort:</strong> Each table sorted by lead count (highest first), then tag name. Top 10 per dimension.<br>' .
				'<strong>This card:</strong> ' .
				$dest_n . ' ' . $plural($dest_n, 'destination') . ', ' .
				$lang_n . ' ' . $plural($lang_n, 'language') . ', ' .
				$race_n . ' race ' . ($race_n === 1 ? 'tag' : 'tags') . ' shown.';
		}

		if(isset($cards['lead_source_split'])) {
			$ls = $cards['lead_source_split'];
			$sg_n = (int)$ls['self_gen_count'];
			$co_n = (int)$ls['company_count'];
			$tot  = $sg_n + $co_n;
			$sg_pct = $tot > 0 ? round(($sg_n / $tot) * 100, 1) : 0;
			$popovers['pop-lead-source-split'] =
				'<strong>Window:</strong> ' . $window_month . ' (by creation date)<br><br>' .
				'<strong>Buckets:</strong>' .
				'<ul>' .
				'<li><strong>Self Gen</strong> &mdash; booking source = &quot;' . SELF_GEN_SOURCE_NAME . '&quot;. The agent brought in the lead themselves.</li>' .
				'<li><strong>Company</strong> &mdash; every other source (WhatsApp, WeChat, Email, Call, Telegram, Facebook, etc.) or no source at all.</li>' .
				'</ul>' .
				'<strong>This card:</strong><br>' .
				'Self Gen &rarr; <strong>' . $sg_n . '</strong> ' . $plural($sg_n, 'BC') . ' &middot; ' . $ls['self_gen_total'] . '<br>' .
				'Company &rarr; <strong>' . $co_n . '</strong> ' . $plural($co_n, 'BC') . ' &middot; ' . $ls['company_total'] . '<br>' .
				($tot > 0 ? 'Self Gen share &rarr; ' . $sg_n . ' &divide; ' . $tot . ' &times; 100 = <strong>' . $sg_pct . '%</strong><br><br>' : '<br>') .
				'<strong>Attribution:</strong> Primary SalesAgent (TC1) regardless of date &mdash; this card does <em>not</em> use the TC1/TC2 credited-slot rule (self-generation is about who hunted the lead, so the primary salesperson is what matters).<br>' .
				'<strong>Filters:</strong> BC only; not cancelled; not draft.';
		}

		if(isset($tables['agent_source_split'])) {
			$rows = count($tables['agent_source_split']);
			$popovers['pop-agent-source-split'] =
				'<strong>Window:</strong> ' . $window_month . ' (by creation date)<br><br>' .
				'<strong>Per agent:</strong>' .
				'<ul>' .
				'<li>Self Gen &mdash; BCs whose source = &quot;' . SELF_GEN_SOURCE_NAME . '&quot;</li>' .
				'<li>Company &mdash; BCs whose source is anything else (or NULL)</li>' .
				'<li>% Self Gen &mdash; Self Gen count &divide; (Self Gen + Company) &times; 100</li>' .
				'</ul>' .
				'<strong>Grouping:</strong> SalesAgent &rarr; <code>admin.TeamLeadID</code> &rarr; team lead. Agents sharing a team lead are consecutive; agents with no team lead appear last.<br>' .
				'<strong>This card:</strong> ' . $rows . ' ' . $plural($rows, 'agent') . ' shown.<br>' .
				'<strong>Attribution:</strong> Primary SalesAgent (TC1) regardless of date &mdash; same as the headline Self Gen vs Company card.<br>' .
				'<strong>Filters:</strong> BC only; not cancelled; not draft; agent must have created at least one BC this month.';
		}

		// Finance cards
		if(isset($cards['payment_in_dwm'])) {
			$p = $cards['payment_in_dwm'];
			$popovers['pop-payin'] =
				'<strong>Formula:</strong> Sum of approved incoming customer payments (Credit > 0).<br><br>' .
				'<strong>Windows (by payment date):</strong><br>' .
				'Today &rarr; ' . $fmt_disp($today) . ' &rarr; <strong>' . $p['day']   . '</strong> across ' . (int)$p['day_count']   . ' ' . $plural($p['day_count'],   'payment') . '<br>' .
				'Week &rarr; ' . $window_week  . ' &rarr; <strong>' . $p['week']  . '</strong> across ' . (int)$p['week_count']  . ' ' . $plural($p['week_count'],  'payment') . '<br>' .
				'Month &rarr; ' . $window_month . ' &rarr; <strong>' . $p['month'] . '</strong> across ' . (int)$p['month_count'] . ' ' . $plural($p['month_count'], 'payment') . '<br><br>' .
				'<strong>Excludes:</strong> Unapproved payments, refunds, outgoing entries, agent commission from suppliers.';
		}

		if(isset($tables['sales_by_team'])) {
			$rows = count($tables['sales_by_team']);
			$grand_total = 0.0;
			$grand_count = 0;
			foreach($tables['sales_by_team'] as $t) {
				// Strip "RM " and thousands separators to reconcile per-team
				// totals against the headline figure in the popover.
				$num = (float)str_replace(array('RM ', ','), '', $t['total']);
				$grand_total += $num;
				$grand_count += (int)$t['count'];
			}
			$popovers['pop-sales-by-team'] =
				'<strong>Window:</strong> ' . $window_month . ' (by creation date)<br><br>' .
				'<strong>Per team:</strong>' .
				'<ul>' .
				'<li>BC &mdash; how many bookings credited to the team</li>' .
				'<li>Sales &mdash; sum of NetTotal</li>' .
				'</ul>' .
				'<strong>Grouping:</strong> SalesAgent &rarr; <code>admin.TeamLeadID</code> &rarr; team lead.<br>' .
				'Agents with no team lead fall into a single &quot;Unassigned&quot; row.<br>' .
				'<strong>This card:</strong> ' . $rows . ' ' . $plural($rows, 'team') . ' shown &rarr; <strong>' . $grand_count . '</strong> ' . $plural($grand_count, 'BC') . ' &middot; <strong>' . $money($grand_total) . '</strong> total.<br>' .
				'<strong>Filters:</strong> BC only; not cancelled; not draft.';
		}

		if(isset($cards['supplier_overdue'])) {
			$so = $cards['supplier_overdue'];
			$rows_n = isset($tables['supplier_overdue']) ? count($tables['supplier_overdue']) : 0;
			$popovers['pop-supplier-overdue'] =
				'<strong>Counted when:</strong>' .
				'<ul>' .
				'<li>Payment-out (<code>Type LIKE \'SUPPLIER PAYMENT%\'</code>)</li>' .
				'<li>Status pending (<code>Status = \'P\'</code>)</li>' .
				'<li>Deadline &lt; today (' . $fmt_disp($today) . ')</li>' .
				'<li>Linked to a supplier</li>' .
				'</ul>' .
				'<strong>This card:</strong><br>' .
				'<strong>' . (int)$so['count'] . '</strong> ' . $plural($so['count'], 'overdue payment') . ' &middot; <strong>' . $so['total_due'] . '</strong> total due<br>' .
				'Top ' . $rows_n . ' ' . $plural($rows_n, 'supplier') . ' shown below; click a row to drill down.<br><br>' .
				'<strong>Excludes:</strong> Already paid (Status=Y), deleted (Status=N), customer payment-ins, agent-commission entries.';
		}

		if(isset($cards['supplier_due_soon'])) {
			$ds = $cards['supplier_due_soon'];
			$rows_n = isset($tables['supplier_due_soon']) ? count($tables['supplier_due_soon']) : 0;
			$popovers['pop-supplier-due-soon'] =
				'<strong>Counted when:</strong>' .
				'<ul>' .
				'<li>Payment-out (<code>Type LIKE \'SUPPLIER PAYMENT%\'</code>)</li>' .
				'<li>Status pending (<code>Status = \'P\'</code>)</li>' .
				'<li>Deadline ' . $rng_disp($due_soon_start, $due_soon_end) . ' (next 3 days)</li>' .
				'<li>Linked to a supplier</li>' .
				'</ul>' .
				'<strong>This card:</strong><br>' .
				'<strong>' . (int)$ds['count'] . '</strong> ' . $plural($ds['count'], 'upcoming payment') . ' &middot; <strong>' . $ds['total_due'] . '</strong> total due<br>' .
				'Top ' . $rows_n . ' ' . $plural($rows_n, 'supplier') . ' shown, earliest deadline first.<br><br>' .
				'<strong>Disjoint from Supplier Overdue:</strong> rows due today or earlier roll into that card.<br>' .
				'<strong>Excludes:</strong> Already paid (Status=Y), deleted (Status=N), customer payment-ins, agent-commission entries.';
		}

		if(isset($tables['product_sales'])) {
			$rows = count($tables['product_sales']);
			$popovers['pop-product-sales'] =
				'<strong>Window:</strong> ' . $window_month . ' (by booking creation date)<br><br>' .
				'<strong>Per product (grouped by item code):</strong>' .
				'<ul>' .
				'<li>Qty &mdash; total quantity sold</li>' .
				'<li>Sales &mdash; sum of line totals</li>' .
				'</ul>' .
				'<strong>Sort:</strong> By total sales (highest first). Top 5.<br>' .
				'<strong>This card:</strong> ' . $rows . ' ' . $plural($rows, 'product') . ' shown.<br>' .
				'<strong>Filters:</strong> Booking confirmations only; not cancelled; not draft; active line items only.';
		}

		header('Content-Type: application/json');
		echo json_encode(array(
			'level'    => $level,
			'cards'    => $cards,
			'tables'   => $tables,
			'meta'     => $meta,
			'popovers' => $popovers,
		));
	}

	function Create()
	{
		if(in_array('GB', $this->session->access_control)) {
			if ($this->input->is_ajax_request()) {

				$booking_id = $this->Booking_Model->Create();

				// Customer-intake draft: created before staff knows pricing/suppliers.
				// Park the booking in SAD ("SAVE AS DRAFT") so the booking list shows
				// it as awaiting customer input, and reflect that in the status log.
				$is_draft_intake = (string) $this->input->post('is_draft_intake') === '1';
				if ($is_draft_intake) {
					$this->load->helper('booking_status_log');
					$this->Booking_Model->update_by_id($booking_id, array('Status' => 'SAD'));
					$created_by = (int) $this->session->userdata('admin_id');
					log_booking_status_change(
						$booking_id,
						'SAD',
						'PBC',
						$created_by,
						'Booking created as draft for customer intake link',
						true
					);
				}

				// A customer-intake draft is created with no products yet, so guard
				// the batch insert (insert_batch errors on an empty set).
				if (!empty($this->input->post('booking_products'))) {
					$this->Booking_Product_Model->Create($this->input->post('booking_products'), $booking_id);

					// Recompute Subtotal from booking_product totals to keep booking.Subtotal authoritative
					$this->Booking_Product_Model->Recompute_Subtotal($booking_id);
				}

				// Create rooms if provided
				$booking_rooms = $this->input->post('booking_rooms');
				if (!empty($booking_rooms)) {
					foreach ($booking_rooms as $room) {
						$this->db->insert('guest_list_room', array(
							'booking_id' => $booking_id,
							'room_name' => strtoupper($room['room_name']),
							'adult_count' => (int)$room['adult_count'],
							'child_count' => (int)$room['child_count'],
							'infant_count' => (int)$room['infant_count'],
							'InsertBy' => $this->session->userdata('admin_id'),
							'InsertDate' => date('Y-m-d H:i:s')
						));
					}
				}

				// Create GL entries based on room data
				$this->Booking_Model->Create_GL_From_Rooms($booking_id);

				// Auto-enable insurance if any product belongs to an insurance category
				$this->db->from('booking_product');
				$this->db->join('product', 'product.ProductID = booking_product.ProductID', 'left');
				$this->db->join('category', 'category.CategoryID = product.CategoryID', 'left');
				$this->db->where('booking_product.BookingID', $booking_id);
				$this->db->where('booking_product.Status', 'Y');
				$this->db->like('category.Name', 'Insurance', 'both');
				if($this->db->count_all_results() > 0) {
					$this->Booking_Model->update_by_id($booking_id, ['TravelInsuranceStatus' => 'Y']);
				}

				$this->Booking_Model->update_by_id($booking_id, [
						'AutocountSyncAction'  => 'C'
				]);
				// $bookingData = $this->Booking_Model->getAllBookingsWithGuests($booking_id);
				// if (!empty($bookingData)) {
				// 	$bookingData = (array) $bookingData[0]; 
				// }

				// $statuses = !empty($this->config->item('booking_sync_status')) ? $this->config->item('booking_sync_status') : ['BOOKING CONFIRMATION'];

				// if (in_array($bookingData['BookingConfirmationTitle'], $statuses)) {
				// 	$bookingProducts = $this->Booking_Model->getAllBookingsWithProducts($booking_id);
				// 	$bookingProducts = array_map('get_object_vars', $bookingProducts); // convert to array

				// 	$quotationData = [
				// 		'BookingNumber'   => $bookingData['BookingNumber'] ?? '',
				// 		'InsertDate'      => $bookingData['InsertDate'] ?? date('Y-m-d'),
				// 		'Customer'        => $bookingData['Customer'] ?? '',
				// 		'guest_email'     => $bookingData['guest_email'] ?? '',
				// 		'guest_address'   => $bookingData['guest_address'] ?? '',
				// 		'guest_phone'     => $bookingData['guest_phone'] ?? '',
				// 		'BokingRemark'    => $bookingData['BokingRemark'] ?? '',
						
				// 		// Fields not in DB → set default or null
				// 		'credit_term'     => null,
				// 		'sales_location'  => '',
				// 		'currency_rate'   => 1,
				// 		'inclusive_tax'   => false,
				// 		'is_round_adj'    => false,
				// 		'tax_code'        => '',

				// 		// Details
				// 		'booking_product' => $bookingProducts
				// 	];
					
				// 	$respond = $this->autocount_create($quotationData);

				// 	$booking = $this->Booking_Model->find($booking_id);
				// 	if ($booking != null && $respond != null) {
				// 		if (!empty($respond['error'])) {
				// 			$this->Booking_Model->update_by_id($booking_id, [
				// 				'AutocountSyncMessage' => json_encode($respond),
				// 			]);
				// 		} elseif ($respond['status'] === 201 || $respond['status'] === 204) {
				// 			$this->Booking_Model->update_by_id($booking_id, [
				// 				'AutocountSyncMessage' => json_encode($respond),
				// 				'AutocountSyncStatus'  => 'C'
				// 			]);
				// 		}
				// 	}
				// }
        } else {
				$titles = array('tab_title' => 'HolidayGoGoGo | Booking', 'breadcrumb_title' => 'Booking >> Create');
					$array = array('BookingID' => 'NA', 'BookingConfirmationFooterID' => 'NA', 'TravelVoucherFooterID' => 'NA', 'BookingNumber' => 'NA', 'Tag' => array(), 'Discount' => 'NA', 'NetTotal' => 'NA', 'ProductSequence' => array(), 'BookingProductID' => ($this->Booking_Product_Model->Read_Last_Booking_Product_ID()) + 1, 'AllowReview' => 1, 'ic_passport_no' => '', 'tin_no' => '', 'customer_types_selected' => array());
					$array['admins'] = $this->Booking_Model->Read_Admins();
					$array['notify_admins'] = $this->Notification_Model->Build_Admin_Handles($this->Booking_Model->Read_Notify_Admins());
					$array['booking_op_admins'] = $this->Booking_Model->Read_Booking_OP_Admins();
					$array['booking_products'][0] = (object) array('BookingProductID' => 'NA');
				$array['categories'] = $this->Booking_Model->Read_Categories();
				$array['products'] = $this->Booking_Model->Read_Products();
				$array['footers'] = $this->Booking_Model->Read_Footers();
				$array['country_codes'] = $this->Booking_Model->Read_Country_Codes();
				$array['tags'] = $this->Booking_Model->Read_Tags();
				$array['sources'] = $this->Booking_Model->Read_Sources();
				$array['customer_types'] = $this->Customer_Type_Model->Read_Customer_Types();
				$array['supplier_invoices'] = [];
				$array['supplier_invoice_suppliers'] = $this->Payment_Model->Read_Suppliers();
				$array['customer_intake'] = null;
				$array['customer_intake_response_seconds'] = null;
				$this->load->view('layout/header', $titles);
				$this->load->view('booking/booking', $array);
				$this->load->view('layout/footer');
			}
		} else {
			redirect('Dashboard');
		}
	}

	function View_Snapshot()
	{
		if (!$this->session->has_userdata('admin_id')) {
			$this->load->view('errors/access_denied');
			return;
		}

		$file = $this->input->get('file');
		if (!preg_match('/^[a-zA-Z0-9_\-\.]+\.pdf$/', $file)) {
			show_404();
			return;
		}

		$path = FCPATH . 'assets/upload/booking_snapshots/' . $file;
		if (!file_exists($path)) {
			show_404();
			return;
		}

		$v = $this->input->get('v');
		$vFresh = !empty($v) && ctype_digit((string)$v) && (time() - intval($v)) <= 5;

		if (!$vFresh) {
			header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private');
			header('Pragma: no-cache');
			header('Expires: 0');
			header('Location: ' . base_url('Booking/View_Snapshot?file=' . urlencode($file) . '&v=' . time()), true, 302);
			exit;
		}

		if (ob_get_length()) { ob_end_clean(); }

		header('Content-Type: application/pdf');
		header('Content-Disposition: inline; filename="' . $file . '"');
		header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private');
		header('Pragma: no-cache');
		header('Expires: 0');
		header('Content-Length: ' . filesize($path));
		readfile($path);
		exit;
	}

	function Update()
	{
		if($this->session->userdata('level') == 20) {
			// check if user is sales agent && booking >= booking.startDate
			$valid_booking_id = $this->Universal_Model->Validate_Id('BookingID', $this->input->get('booking_id'), 'booking');

			if($valid_booking_id) {
				$booking = $this->Booking_Model->Read_Booking();
				$today = strtotime(date('Y-m-d'));
				$startDate = strtotime($booking['StartDate']);

				if ($today >= $startDate) {
					// if travel started, redirect to view booking page
					redirect('Booking/View?booking_id=' . $this->input->get('booking_id'));
				}
			}
		}

		// Block SA and TC from updating completed bookings
		if(in_array($this->session->userdata('level'), [20, 50])) {
			$valid_booking_id = $this->Universal_Model->Validate_Id('BookingID', $this->input->get('booking_id'), 'booking');
			if($valid_booking_id) {
				$booking = $this->Booking_Model->Read_Booking();
				if($booking['Status'] == 'Y' && $booking['AfterSalesService'] == 'COMPLETE') {
					if($this->input->is_ajax_request()) {
						echo json_encode(array('error' => 'Access denied'));
					} else {
						$this->load->view('errors/access_denied');
					}
					return;
				}
			}
		}
		
		if(in_array('AB', $this->session->access_control)) {
			if($this->input->is_ajax_request()) {
				// Capture the booking's pre-save Status so that we can graduate a
				// "Save as Draft" (SAD) booking to PB / PBC once the staff member
				// picks a graduate button — and so the SAD -> PBC transition anchors
				// the customer-intake response-time metric.
				$intake_pre_save_status = null;
				$intake_post_booking_id = $this->input->post('booking_id');
				if (!empty($intake_post_booking_id) && is_numeric($intake_post_booking_id)) {
					$intake_pre = $this->db->select('Status')
						->where('BookingID', (int) $intake_post_booking_id)
						->get('booking')->row();
					$intake_pre_save_status = !empty($intake_pre) ? $intake_pre->Status : null;
				}

				// Always persist IC/Passport No. and TIN No. on the linked customer when posted,
				// independent of whether other booking fields changed.
				$posted_ic = $this->input->post('ic_passport_no');
				$posted_tin = $this->input->post('tin_no');
				$posted_customer_id = $this->input->post('CustomerID');
				if (!empty($posted_customer_id) && is_numeric($posted_customer_id) && (!empty($posted_ic) || $posted_tin !== null)) {
					$update = ['updated_at' => date('Y-m-d H:i:s')];
					if (!empty($posted_ic)) {
						$update['ic_passport_no'] = strtoupper($posted_ic);
					}
					if ($posted_tin !== null) {
						$update['tin_no'] = strtoupper(trim($posted_tin));
					}
					$this->Customer_Model->update_by_id($posted_customer_id, $update);
				}

				// Always sync per-booking customer types when posted, independent of
				// whether any booking field changed (booking[0] may have only the
				// stub BookingID/UpdateBy/UpdateDate fields).
				$posted_booking_id = $this->input->post('booking_id');
				$posted_customer_types = $this->input->post('customer_type');
				if (!empty($posted_booking_id) && is_numeric($posted_booking_id) && is_array($posted_customer_types)) {
					$this->load->model('Booking_Customer_Type_Model');
					$this->Booking_Customer_Type_Model->Sync($posted_booking_id, $posted_customer_types);
				}

				// Draft write guard: while a booking sits in SAD *and the customer
				// has not yet submitted the intake*, the admin form disables every
				// field but a small whitelist. Re-enforce that server-side so a
				// tampered request can't write locked columns — strip booking[0]
				// down to the editable fields plus the structural keys the model
				// needs. Once the intake is submitted the form unlocks and staff
				// complete the whole booking before graduating, so the guard lifts.
				$intake_already_submitted = !empty($intake_post_booking_id)
					&& (int) $this->db->where('booking_id', (int) $intake_post_booking_id)
						->count_all_results('booking_customer_intake') > 0;
				if ($intake_pre_save_status === 'SAD' && !$intake_already_submitted) {
					$this->load->helper('booking_draft');
					$posted_booking = $this->input->post('booking');
					if (!empty($posted_booking) && isset($posted_booking[0]) && is_array($posted_booking[0])) {
						$allowed = array_merge(draft_editable_fields(), array('BookingID', 'UpdateBy', 'UpdateDate'));
						foreach (array_keys($posted_booking[0]) as $col) {
							if (!in_array($col, $allowed, true)) {
								unset($posted_booking[0][$col]);
							}
						}
						$_POST['booking'] = $posted_booking;
					}
				}

				// Booking
				// Action : Update
				if(!empty($this->input->post('booking')) && count($this->input->post('booking')[0]) > 3) {
					$this->Booking_Model->Update();
					$this->Booking_Model->Create_Booking_Log();
				} else {
					// Partial booking updates skip Booking_Model::Update() above, but the
					// linked customer's name/phone_number should still stay in sync with the
					// booking row (needed for the customer portal slug).
					$this->Booking_Model->Sync_Customer_From_Booking($this->input->post('booking_id'));
				}

				// Booking Product
				// Action : Create
				if(!empty($this->input->post('booking_products')[0])) {
					$this->Booking_Product_Model->Create($this->input->post('booking_products')[0], $this->input->post('booking_id'));
					$booking = $this->Booking_Model->Read_Product_Sequence();
					$booking_products = $this->Booking_Product_Model->Read_Booking_Products($this->input->post('booking_id'));
					if(!empty($booking['ProductSequence'])) {
						$booking['ProductSequence'] = explode(',', $booking['ProductSequence']);
						foreach($booking_products as $booking_product) {
							if(!in_array($booking_product->BookingProductID, $booking['ProductSequence'])) {
								array_push($booking['ProductSequence'], $booking_product->BookingProductID);
							}
						}
						$this->Booking_Model->Update_Product_Sequence(implode(',', $booking['ProductSequence']));
					}
					// Check if price changed due to new product (will be checked when booking NetTotal is updated)
				}
				// Action : Update
				if(!empty($this->input->post('booking_products')[1])) {
					$this->Booking_Product_Model->Update($this->input->post('booking_products')[1]);
					// Check if price changed due to product update (will be checked when booking NetTotal is updated)
				}
				// Action : Delete
				if(!empty($this->input->post('booking_products')[2])) {
					$this->Booking_Product_Model->Update($this->input->post('booking_products')[2]);
					$booking = $this->Booking_Model->Read_Product_Sequence();
					$booking_products = $this->Booking_Product_Model->Read_Booking_Products($this->input->post('booking_id'));
					$array = [];
					if(!empty($booking['ProductSequence'])) {
						$booking['ProductSequence'] = explode(',', $booking['ProductSequence']);
						foreach($booking_products as $booking_product) {
							array_push($array, $booking_product->BookingProductID);
						}
						for($i = 0; $i < count($booking['ProductSequence']); $i++) {
							if(!in_array($booking['ProductSequence'][$i], $array)) {
								unset($booking['ProductSequence'][$i]);
							}
						}
						$this->Booking_Model->Update_Product_Sequence(implode(',', $booking['ProductSequence']));
					}
					// Check if price changed due to product deletion (will be checked when booking NetTotal is updated)
				}

				// Supplier Invoices: same three-bucket POST shape as booking_products.
				$invoices_post = $this->input->post('booking_supplier_invoices');
				if (!empty($invoices_post[0])) {
					$this->Booking_Supplier_Invoice_Model->Create($invoices_post[0], $this->input->post('booking_id'));
				}
				if (!empty($invoices_post[1])) {
					$this->Booking_Supplier_Invoice_Model->Update($invoices_post[1]);
				}
				if (!empty($invoices_post[2])) {
					$this->Booking_Supplier_Invoice_Model->Update($invoices_post[2]);
				}

				// Bell notification for booking-update fires only when the travel
				// window (StartDate / EndDate) or booking products changed.
				$this->load->helper('booking_change_summary');
				$log_rows = $this->input->post('booking_log') ?: [];
				$bp_for_notify = $this->input->post('booking_products');
				$products_create = (is_array($bp_for_notify) && !empty($bp_for_notify[0])) ? $bp_for_notify[0] : [];
				$products_update = (is_array($bp_for_notify) && !empty($bp_for_notify[1])) ? $bp_for_notify[1] : [];
				$products_delete = (is_array($bp_for_notify) && !empty($bp_for_notify[2])) ? $bp_for_notify[2] : [];

				$travel_date_changed = false;
				foreach ($log_rows as $row) {
					$arr = is_object($row) ? get_object_vars($row) : (array)$row;
					$col = isset($arr['Column']) ? $arr['Column'] : null;
					if (($col === 'StartDate' || $col === 'EndDate')
						&& (string)(isset($arr['CurrentData']) ? $arr['CurrentData'] : '') !== (string)(isset($arr['NewData']) ? $arr['NewData'] : '')) {
						$travel_date_changed = true;
						break;
					}
				}
				$products_changed = booking_products_have_changes($products_create, $products_update, $products_delete);

				if ($products_changed) {
					$this->Booking_Model->Revert_To_PBC_For_Product_Change(
						$this->input->post('booking_id')
					);
				}

				if ($travel_date_changed || $products_changed) {
					$change_summary = build_booking_update_notification_summary(
						$log_rows, $products_create, $products_update, $products_delete
					);
					$this->load->model('Notification_Model');
					$updater_name = $this->session->userdata('name') ?: 'Someone';
					$this->Notification_Model->Create_Booking_Updated_Notification(
						$this->input->post('booking_id'),
						$this->session->userdata('admin_id'),
						$updater_name,
						$change_summary
					);
				}

				// Cleanup invalid supplier dates on booking products
				$this->Booking_Product_Model->Cleanup_Supplier_Dates($this->input->post('booking_id'));

				// Recompute Subtotal from booking_product totals to keep booking.Subtotal authoritative
				$this->Booking_Product_Model->Recompute_Subtotal($this->input->post('booking_id'));

				// If any booking_product was added, edited, or deleted, auto-unlock the
				// E-Invoice Request session so the customer can review the revised
				// figures and resubmit (pax data is preserved).
				$bp_post = $this->input->post('booking_products');
				if (!empty($bp_post[0]) || !empty($bp_post[1]) || !empty($bp_post[2])) {
					$this->load->model('Invoice_Split_Model');
					$this->Invoice_Split_Model->Unlock_Submitted($this->input->post('booking_id'));
				}

				// Auto-enable insurance if any product belongs to an insurance category
				$booking_id = $this->input->post('booking_id');
				$this->db->from('booking_product');
				$this->db->join('product', 'product.ProductID = booking_product.ProductID', 'left');
				$this->db->join('category', 'category.CategoryID = product.CategoryID', 'left');
				$this->db->where('booking_product.BookingID', $booking_id);
				$this->db->where('booking_product.Status', 'Y');
				$this->db->like('category.Name', 'Insurance', 'both');
				if($this->db->count_all_results() > 0) {
					$this->Booking_Model->update_by_id($booking_id, ['TravelInsuranceStatus' => 'Y']);
				}

				$bookingInfo = get_object_vars($this->Booking_Model->find($this->input->post('booking_id')));

				if (!empty($bookingInfo)) {
					$payments = $this->Payment_Model->get_payments_by_booking_id($this->input->post('booking_id'));

					if ($bookingInfo['AutocountSyncAction'] == 'C' && $bookingInfo['AutocountSyncStatus'] == 'S') {
						$this->Booking_Model->update_by_id($this->input->post('booking_id'), [
							'AutocountSyncAction' => 'U',
							'AutocountSyncStatus' => 'P'
						]);
						if (!empty($payments)) {
							foreach($payments as $payment) {
								if ($payment['AutocountSyncAction'] == 'C' && $payment['AutocountSyncStatus'] == 'S') {
									$this->Payment_Model->update_by_id($payment['PaymentID'], [
										'AutocountSyncAction' => 'U',
										'AutocountSyncStatus' => 'P'
									]);
								} elseif ($payment['AutocountSyncAction'] == 'U' && $payment['AutocountSyncStatus'] == 'S') {
									$this->Payment_Model->update_by_id($payment['PaymentID'], [
										'AutocountSyncStatus' => 'P'
									]);
								} else {
									// Just update status to 'P' in all other cases
									$this->Payment_Model->update_by_id($payment['PaymentID'], [
										'AutocountSyncStatus' => 'P'
									]);
								}
							}
						}
					} else if ($bookingInfo['AutocountSyncAction'] == 'U' && $bookingInfo['AutocountSyncStatus'] == 'S') {
						$this->Booking_Model->update_by_id($this->input->post('booking_id'), [
							'AutocountSyncAction' => 'U',
							'AutocountSyncStatus' => 'P'
						]);
						if (!empty($payments)) {
							foreach($payments as $payment) {
								if ($payment['AutocountSyncAction'] == 'C' && $payment['AutocountSyncStatus'] == 'S') {
									$this->Payment_Model->update_by_id($payment['PaymentID'], [
										'AutocountSyncAction' => 'U',
										'AutocountSyncStatus' => 'P'
									]);
								} elseif ($payment['AutocountSyncAction'] == 'U' && $payment['AutocountSyncStatus'] == 'S') {
									$this->Payment_Model->update_by_id($payment['PaymentID'], [
										'AutocountSyncStatus' => 'P'
									]);
								} else {
									// Just update status to 'P' in all other cases
									$this->Payment_Model->update_by_id($payment['PaymentID'], [
										'AutocountSyncStatus' => 'P'
									]);
								}
							}
						}
					} else {
						$this->Booking_Model->update_by_id($this->input->post('booking_id'), [
							'AutocountSyncStatus'  => 'P',
						]);
						if (!empty($payments)) {
							foreach($payments as $payment) {
								if ($payment['AutocountSyncAction'] == 'C' && $payment['AutocountSyncStatus'] == 'S') {
									$this->Payment_Model->update_by_id($payment['PaymentID'], [
										'AutocountSyncAction' => 'U',
										'AutocountSyncStatus' => 'P'
									]);
								} elseif ($payment['AutocountSyncAction'] == 'U' && $payment['AutocountSyncStatus'] == 'S') {
									$this->Payment_Model->update_by_id($payment['PaymentID'], [
										'AutocountSyncStatus' => 'P'
									]);
								} else {
									// Just update status to 'P' in all other cases
									$this->Payment_Model->update_by_id($payment['PaymentID'], [
										'AutocountSyncStatus' => 'P'
									]);
								}
							}
						}
					}

					$customerInfo = get_object_vars($this->Customer_Model->find($bookingInfo['CustomerID']));
					if (!empty($customerInfo)) {
						if ($customerInfo['AutocountSyncAction'] == 'C' && $customerInfo['AutocountSyncStatus'] == 'S') {
							$this->Customer_Model->update_by_id($customerInfo['CustomerID'], [
								'AutocountSyncAction' => 'U',
								'AutocountSyncStatus' => 'P'
							]);
						} else if ($customerInfo['AutocountSyncAction'] == 'U' && $customerInfo['AutocountSyncStatus'] == 'S') {
							$this->Customer_Model->update_by_id($customerInfo['CustomerID'], [
								'AutocountSyncAction' => 'U',
								'AutocountSyncStatus' => 'P'
							]);
						} else {
							$this->Customer_Model->update_by_id($customerInfo['CustomerID'], [
								'AutocountSyncStatus'  => 'P',
							]);
						}
					}
				}
				
				/*** Fetch Fresh Booking + Products from DB ***/
				// $booking_id = $this->input->post('booking_id');
				// $bookingData = $this->Booking_Model->getAllBookingsWithGuests($booking_id);
				// $bookingProducts = $this->Booking_Product_Model->getAllBookingsWithProducts($booking_id);

				// // Convert objects to arrays
				// $bookingProducts = array_map('get_object_vars', $bookingProducts);

				// $statuses = !empty($this->config->item('booking_sync_status')) ? $this->config->item('booking_sync_status') : ['BOOKING CONFIRMATION'];

				// if (in_array($bookingData['BookingConfirmationTitle'], $statuses)) {
				// 	/*** Call Quotation Update in AutoCount ***/
				// 	$quotationData = [
				// 		'BookingNumber'   => $bookingData['BookingNumber'],
				// 		'DocNo'           => $bookingData['BookingNumber'], // Fallback
				// 		'master'          => [
				// 			'DocDate'        => date('Y-m-d', strtotime($bookingData['InsertDate'])),
				// 			'DebtorName'     => $bookingData['Customer'],
				// 			'Email'          => $bookingData['guest_email'],
				// 			'Address'        => $bookingData['guest_address'],
				// 			'Phone1'         => $bookingData['guest_phone'],
				// 			'DeliverAddress' => $bookingData['guest_address'],
				// 			'DeliverContact' => $bookingData['Customer'],
				// 			'DeliverPhone1'  => $bookingData['guest_phone'],
				// 			'Remark1'        => $bookingData['BokingRemark'],
				// 		],
				// 		'booking_product' => $bookingProducts,
				// 		'tax_code'        => '', // Default tax code if missing
				// 		'saveApprove'     => null
				// 	];

				// 	$respond = $this->autocount_update($quotationData);

				// 	$booking = $this->Booking_Model->find($booking_id);
				// 	if ($booking != null && $respond != null) {
				// 		if ($respond['error']) {
				// 			$this->Booking_Model->update_by_id($booking_id, [
				// 				'AutocountSyncMessage' => json_encode($respond),
				// 			]);
				// 		} elseif ($respond['status'] === 201 || $respond['status'] === 204) {
				// 			$this->Booking_Model->update_by_id($booking_id, [
				// 				'AutocountSyncMessage' => json_encode($respond),
				// 				'AutocountSyncStatus' => 'U'
				// 			]);
				// 		} 
				// 	}
				// }
				
				// Booking Checklist Completion (delegated so the Save_Checklist
				// endpoint can reuse the same write path for users without AB).
				$this->_persist_checklist_completions(
					$this->input->post('booking_id'),
					$this->input->post('checklist_completions'),
					$this->session->userdata('admin_id')
				);

				// Customer-intake draft lifecycle (draft_save_mode):
				//   approve -> set DraftApproved (status stays SAD)
				//   PB      -> park at PENDING BC (stays editable; can graduate later)
				//   PBC     -> PENDING BC CONFIRMATION; enters the normal flow and
				//              writes the log row the intake response-time metric needs
				//   draft / '' -> stays SAD
				// Applies from SAD (approve / graduate) or from PB (re-graduate PB->PBC).
				if (in_array($intake_pre_save_status, array('SAD', 'PB'), true) && !empty($intake_post_booking_id)) {
					$this->load->helper('booking_draft');
					$bid  = (int) $intake_post_booking_id;
					$mode = $this->input->post('draft_save_mode');
					$advancer_id = (int) $this->session->userdata('admin_id');

					if ($mode === 'approve' && $intake_pre_save_status === 'SAD') {
						$this->Booking_Model->update_by_id($bid, array(
							'DraftApproved'     => 1,
							'DraftApprovedDate' => date('Y-m-d H:i:s'),
						));
					} else {
						$target = resolve_graduate_status($mode);
						if ($target !== null) {
							$this->load->helper(array('booking_status_log', 'booking_flow'));
							$status_info  = get_booking_status_info();
							$target_label = isset($status_info['texts'][$target]) ? $status_info['texts'][$target] : $target;
							// Graduating implies approval (the buttons only show once
							// approved), so keep the flag set.
							$this->Booking_Model->update_by_id($bid, array(
								'Status'        => $target,
								'DraftApproved' => 1,
							));
							log_booking_status_change(
								$bid,
								$target,
								$intake_pre_save_status,
								$advancer_id,
								'Customer intake — booking advanced to ' . $target_label,
								true
							);
						}
					}
				}
			} else {
				$valid_booking_id = $this->Universal_Model->Validate_Id('BookingID', $this->input->get('booking_id'), 'booking');

				if($valid_booking_id) {
					if(current_url() == base_url('Booking/Update')) {
						$titles = array('tab_title' => 'HolidayGoGoGo | Booking', 'breadcrumb_title' => 'Booking >> Update');
					} else {
						$titles = array('tab_title' => 'HolidayGoGoGo | Booking', 'breadcrumb_title' => 'Booking >> Create');
					}
					$array = $this->Booking_Model->Read_Booking();

					// Reset lock status for Duplicate - new booking should not inherit GL lock
					if (current_url() == base_url('Booking/Duplicate')) {
						$array['LockStatus'] = 'N';
					}

					// Preserve raw Y-m-d deadlines before display formatting so downstream
					// logic (e.g. display_booking_status) can still parse them reliably.
					$array['DepositDeadlineRaw'] = !empty($array['DepositDeadline']) ? $array['DepositDeadline'] : '';
					$array['FullPaymentDeadlineRaw'] = !empty($array['FullPaymentDeadline']) ? $array['FullPaymentDeadline'] : '';

					if(!empty($array['DepositDeadline'])) {
						$array['DepositDeadline'] = date('d/m/Y', strtotime($array['DepositDeadline']));
					}
					$array['FullPaymentDeadline'] = date('d/m/Y', strtotime($array['FullPaymentDeadline']));
					if(!empty($array['AdditionalPaymentDeadline'])) {
						$array['AdditionalPaymentDeadline'] = date('d/m/Y', strtotime($array['AdditionalPaymentDeadline']));
					}
					if(!empty($array['StartDate']) && !empty($array['EndDate'])) {
						$array['TravelDate'] = date('d/m/Y', strtotime($array['StartDate'])) . ' - ' . date('d/m/Y', strtotime($array['EndDate']));
					} else {
						$array['TravelDate'] = null;
					}
					// Ensure AllowReview is set (default to 1 if not set or null)
					if (!isset($array['AllowReview']) || $array['AllowReview'] === null) {
						$array['AllowReview'] = 1;
					} else {
						// Convert to integer to ensure it's 0 or 1
						$array['AllowReview'] = (int)$array['AllowReview'];
					}
					$array['BookingProductID'] = ($this->Booking_Product_Model->Read_Last_Booking_Product_ID()) + 1;
					$array['Tag'] = explode(',', $array['Tag']);
					$array['Subtotal'] = number_format($array['Subtotal'], 2, '.', ',');
					$array['Discount'] = $array['Discount'] != 0.00 ? number_format($array['Discount'], 2, '.', ',') : '';
					// Store raw NetTotal before formatting for deposit calculation
					$net_total_raw = isset($array['NetTotal']) ? floatval($array['NetTotal']) : 0;
					$array['NetTotal'] = number_format($array['NetTotal'], 2, '.', ',');
					// Handle DepositPercentage - store original DB value for comparison
					$array['DepositPercentageOriginal'] = isset($array['DepositPercentage']) ? $array['DepositPercentage'] : 0;
					// Handle DepositMode and DepositFixedAmount - store originals for comparison
					$array['DepositModeOriginal'] = isset($array['DepositMode']) ? $array['DepositMode'] : 'percentage';
					$array['DepositFixedAmountOriginal'] = isset($array['DepositFixedAmount']) ? $array['DepositFixedAmount'] : 0;
					// For Update page: use actual DB value (even if 0). For Create/Duplicate: default to 50 if 0 or not set
					if (current_url() == base_url('Booking/Update')) {
						// Update page: use actual database value
						if (!isset($array['DepositPercentage'])) {
							$array['DepositPercentage'] = 0;
						}
						if (!isset($array['DepositMode'])) {
							$array['DepositMode'] = 'percentage';
						}
						if (!isset($array['DepositFixedAmount'])) {
							$array['DepositFixedAmount'] = 0;
						}
					} else {
						// Create/Duplicate page: default to 50 if 0 or not set
						if (!isset($array['DepositPercentage']) || $array['DepositPercentage'] == 0) {
							$array['DepositPercentage'] = 50; // Default UI value
						}
						if (!isset($array['DepositMode'])) {
							$array['DepositMode'] = 'percentage';
						}
						if (!isset($array['DepositFixedAmount'])) {
							$array['DepositFixedAmount'] = 0;
						}
					}
					// Calculate deposit information
					$deposit_mode = isset($array['DepositMode']) ? $array['DepositMode'] : 'percentage';
					if ($deposit_mode == 'fixed') {
						$deposit_total = isset($array['DepositFixedAmount']) ? floatval($array['DepositFixedAmount']) : 0;
					} else {
						$deposit_percentage_raw = isset($array['DepositPercentage']) ? $array['DepositPercentage'] : 0;
						$deposit_total = ($net_total_raw * $deposit_percentage_raw) / 100;
					}
					$array['DepositTotal'] = $deposit_total;
					// Calculate deposit paid from payments
					$deposit_paid = 0;
					$total_credit_approved = 0;
					if (isset($array['BookingID'])) {
						$payments = $this->Booking_Model->Read_Payments($array['BookingID']);
						if (!empty($payments)) {
							foreach ($payments as $payment) {
								if (($payment->Status == 'Y' || $payment->Status == 'P') && $payment->Credit > 0) {
									$deposit_paid += $payment->Credit;
								}
								if ($payment->Status == 'Y' && !empty($payment->Credit) && $payment->Credit > 0
									&& (!isset($payment->Type) || $payment->Type != 'AGENT COMMISSION FROM SUPPLIER')) {
									$total_credit_approved += floatval($payment->Credit);
								}
							}
						}
					}
					$array['DepositPaid'] = $deposit_paid;
					$array['balance_due'] = $net_total_raw - $total_credit_approved;
					$this->load->helper('booking_flow');
					$has_deposit_deadline_detail = !empty($array['DepositDeadline']);
					$array['deposit_complete'] = compute_deposit_complete($deposit_total, $total_credit_approved, $has_deposit_deadline_detail);
					$checklist_tl_ids = resolve_booking_checklist_team_leads($array);
					$array['can_modify_checklist'] = can_user_modify_booking_checklist(
						$array,
						$this->session->userdata('admin_id'),
						$this->session->userdata('level'),
						$checklist_tl_ids['tc1_tl'],
						$checklist_tl_ids['op_tl']
					);
					// Calculate deposit status and format Deposit Paid display
					$deposit_difference = $deposit_paid - $deposit_total;
					if ($deposit_paid > 0) {
						$deposit_paid_display = number_format($deposit_paid, 2, '.', ',');

						if ($deposit_difference < -0.01) {
							$deposit_paid_display .= ' (Underpaid: RM ' . number_format(abs($deposit_difference), 2, '.', ',') . ')';
							$array['DepositPaidColor'] = '#FFA500';
						}
					} else {
						$deposit_paid_display = '0.00';
					}
						$array['DepositPaidDisplay'] = $deposit_paid_display;
						$array['admins'] = $this->Booking_Model->Read_Admins();
						$array['notify_admins'] = $this->Notification_Model->Build_Admin_Handles($this->Booking_Model->Read_Notify_Admins());
						$array['booking_op_admins'] = $this->Booking_Model->Read_Booking_OP_Admins();

					if(empty($array['ProductSequence'])) {
						$array['ProductSequence'] = explode(',', $array['ProductSequence']);
						$array['booking_products'] = $this->Booking_Product_Model->Read();
					} else {
						$array['ProductSequence'] = explode(',', $array['ProductSequence']);
						$booking_products = $this->Booking_Product_Model->Read();
						$array['booking_products'] = [];
						for($i = 0; $i < count($array['ProductSequence']); $i++) {
							foreach($booking_products as $booking_product) {
								if($booking_product->BookingProductID == $array['ProductSequence'][$i]) {
									array_push($array['booking_products'], $booking_product);
								}
							}
						}
					}
					$array['categories'] = $this->Booking_Model->Read_Categories();
					$array['products'] = $this->Booking_Model->Read_Products();
					$array['footers'] = $this->Booking_Model->Read_Footers();
					$array['country_codes'] = $this->Booking_Model->Read_Country_Codes();
					$array['tags'] = $this->Booking_Model->Read_Tags();
					$array['sources'] = $this->Booking_Model->Read_Sources_With_Inactive($array['Source']);
					foreach($array['booking_products'] as $booking_product) {
						$booking_product->Price = number_format($booking_product->Price, 2, '.', ',');
						$booking_product->Total = number_format($booking_product->Total, 2, '.', ',');
						$booking_product->PaymentOutSupplierFull = !empty($booking_product->PaymentOutSupplierFull) ? date('d/m/Y', strtotime($booking_product->PaymentOutSupplierFull)) : '';
						$booking_product->PaymentOutSupplierDeposit = !empty($booking_product->PaymentOutSupplierDeposit) ? date('d/m/Y', strtotime($booking_product->PaymentOutSupplierDeposit)) : '';
					}

					// Customer-intake context: surface the customer-submitted intake
					// data so the edit form can render a banner with the raw values
					// and pre-populate empty booking fields. Empty values are only
					// filled — staff edits are never clobbered.
					$this->load->model('Booking_Customer_Intake_Model');
					$this->load->helper('customer_intake');
					$intake_data = $this->Booking_Customer_Intake_Model->get_by_booking_id($array['BookingID']);
					$array['customer_intake'] = $intake_data;
					$array['customer_intake_response_seconds'] = calculate_submitted_to_payment_seconds($array['BookingID']);
					if (!empty($intake_data)) {
						$intake = $intake_data['intake'];
						if (empty($array['Customer']) && !empty($intake->booking_name)) {
							$array['Customer'] = $intake->booking_name;
						}
						if (empty($array['CustomerMobile']) && !empty($intake->contact_number)) {
							$array['CustomerMobile'] = $intake->contact_number;
						}
						if (empty($array['ic_passport_no']) && !empty($intake->ic_passport_no)) {
							$array['ic_passport_no'] = $intake->ic_passport_no;
						}
						if (empty($array['StartDate']) && !empty($intake->travel_start_date)) {
							$array['StartDate'] = $intake->travel_start_date;
						}
						if (empty($array['EndDate']) && !empty($intake->travel_end_date)) {
							$array['EndDate'] = $intake->travel_end_date;
						}
						if (empty($array['SpecialRemarks']) && !empty($intake->special_remarks)) {
							$array['SpecialRemarks'] = $intake->special_remarks;
						}
						if (empty($array['TravelDate'])
							&& !empty($intake->travel_start_date)
							&& !empty($intake->travel_end_date)) {
							$array['TravelDate'] = date('d/m/Y', strtotime($intake->travel_start_date))
								. ' - ' . date('d/m/Y', strtotime($intake->travel_end_date));
						}
					}

					// Get booking checklists
					$array['booking_checklists'] = $this->get_booking_checklists($array['booking_products']);
					$array['completion_map'] = $this->Booking_Checklist_Completion_Model->Read_Completion_Map($array['BookingID']);

					// Get invoice split data
					$this->load->model('Invoice_Split_Model');
					$array['invoice_split'] = $this->Invoice_Split_Model->Get_Pax_By_Booking($array['BookingID']);

					// Surface audit info (status + last admin editor) for the admin
					// E-Invoice card. Reads the first pax row because Save_Split writes
					// these fields uniformly across every pax in the same transaction.
					$array['einvoice_submit_status']    = null;
					$array['einvoice_submitted_date']   = null;
					$array['einvoice_last_edited_by']   = null;
					$array['einvoice_last_edited_date'] = null;
					$array['einvoice_last_editor_name'] = null;
					if (!empty($array['invoice_split'])) {
						$first = $array['invoice_split'][0];
						$array['einvoice_submit_status']    = $first['SubmitStatus'];
						$array['einvoice_submitted_date']   = $first['SubmittedDate'];
						$array['einvoice_last_edited_by']   = $first['LastEditedByAdmin'];
						$array['einvoice_last_edited_date'] = $first['LastEditedDate'];
						if (!empty($first['LastEditedByAdmin'])) {
							$this->load->model('Admin_Model');
							$editor = $this->Admin_Model->find($first['LastEditedByAdmin']);
							if (!empty($editor)) {
								$array['einvoice_last_editor_name'] = $editor->Name;
							}
						}
					}

					// Get custom uploads
					$this->load->model('Custom_Upload_Model');
					$array['custom_uploads'] = $this->Custom_Upload_Model->Read($array['BookingID']);

					// Get booking status log timeline
					$this->load->model('Booking_Status_Log_Model');
					$array['status_logs'] = $this->Booking_Status_Log_Model->get_timeline_data($array['BookingID'], false);

					// Get booking audit logs
					$array['booking_logs'] = $this->Booking_Model->Read_Booking_Logs($array['BookingID']);

					// Get payment change logs
					$array['payment_logs'] = $this->Payment_Model->Read_Payment_Logs($array['BookingID']);

					// Calculate and get display status for the booking
					$this->load->helper('booking_flow');
					$array['display_status'] = display_booking_status($array, true); // true = return all applicable statuses

					// Supplier invoices entered on this booking + dropdown source.
					$array['supplier_invoices']         = $this->Booking_Supplier_Invoice_Model->Read_By_Booking($array['BookingID']);
					$array['supplier_invoice_suppliers'] = $this->Payment_Model->Read_Suppliers();

					if(isset($_GET['nick'])) { echo "<pre>"; print_r($array); exit; }
					$array['customer_types'] = $this->Customer_Type_Model->Read_Customer_Types();
					$this->load->view('layout/header', $titles);
					$this->load->view('booking/booking', $array);
					$this->load->view('layout/footer');
				} else {
					redirect('Booking');
				}
			}
		} else {
			redirect('Dashboard');
		}
	}

	/**
	 * Admin endpoint: edit a submitted e-invoice request from the admin booking
	 * detail page. Reuses the same validator as the customer-portal submit flow
	 * (full allocation required), preserves the original SubmittedDate, and
	 * stamps LastEditedByAdmin / LastEditedDate so the audit strip can render.
	 * Finance is re-notified by email so they always work off the latest copy.
	 *
	 * Any authenticated admin (any admin_id in session) may use this endpoint.
	 */
	public function save_invoice_split_admin($booking_id = null)
	{
		$this->output->set_content_type('application/json');

		$admin_id = $this->session->userdata('admin_id');
		if (empty($admin_id)) {
			$this->output->set_output(json_encode(['success' => false, 'message' => 'Unauthorized']));
			return;
		}

		$booking_id = (int)$booking_id;
		if ($booking_id <= 0) {
			$this->output->set_output(json_encode(['success' => false, 'message' => 'Invalid booking']));
			return;
		}

		$this->db->select('BookingID, Subtotal, Discount, NetTotal');
		$this->db->where('BookingID', $booking_id);
		$this->db->where('Status !=', 'N');
		$booking = $this->db->get('booking')->row_array();
		if (empty($booking)) {
			$this->output->set_output(json_encode(['success' => false, 'message' => 'Booking not found']));
			return;
		}

		$json = $this->input->raw_input_stream;
		$data = json_decode($json, true);

		$this->load->model('Invoice_Split_Model');
		// Admin edits always enforce full allocation, same as a customer Submit.
		$validation = $this->Invoice_Split_Model->Validate_Pax_Input($data, $booking['BookingID'], true);
		if (!$validation['ok']) {
			$this->output->set_output(json_encode(['success' => false, 'message' => $validation['message']]));
			return;
		}

		$result = $this->Invoice_Split_Model->Save_Split(
			$booking['BookingID'],
			$validation['pax_data'],
			floatval($booking['Subtotal']),
			floatval($booking['Discount']),
			'S',
			true,        // preserve_submitted_date
			$admin_id    // admin_editor_id
		);

		if (!$result) {
			$this->output->set_output(json_encode(['success' => false, 'message' => 'Failed to save invoice split']));
			return;
		}

		// Best-effort finance re-notification. Look up the editor's display
		// name once so the email body can name them.
		$this->load->model('Admin_Model');
		$editor = $this->Admin_Model->find($admin_id);
		$editor_name = !empty($editor) ? $editor->Name : '';
		$this->Invoice_Split_Model->Send_Finance_Notification($booking['BookingID'], true, $editor_name);

		$pax = $this->Invoice_Split_Model->Get_Pax_By_Booking($booking['BookingID']);
		$this->output->set_output(json_encode([
			'success' => true,
			'message' => 'E-Invoice request updated successfully',
			'submit_status' => 'S',
			'pax' => $pax,
		]));
	}

	function View()
	{
		// Allow sales agents (level 20) to view their own bookings even without AB access
		$is_sales_agent = $this->session->userdata('level') == 20;
		$has_ab_access = in_array('AB', $this->session->access_control);
		$has_vb_access = in_array('VB', $this->session->access_control);
		
		// Check if user has VB access OR is a sales agent
		if($has_vb_access || $is_sales_agent) {
			$valid_booking_id = $this->Universal_Model->Validate_Id('BookingID', $this->input->get('booking_id'), 'booking');
			
			if($valid_booking_id) {
				$array = $this->Booking_Model->Read_Booking();

				// Block SA and TC from viewing completed bookings
				if(in_array($this->session->userdata('level'), [20, 50]) && $array['Status'] == 'Y' && $array['AfterSalesService'] == 'COMPLETE') {
					$this->load->view('errors/access_denied');
					return;
				}

				// If sales agent without AB access, verify they own the booking
				if($is_sales_agent && !$has_ab_access) {
					if($array['SalesAgent'] != $this->session->userdata('admin_id')) {
						$this->session->set_flashdata('error', 'You can only view bookings assigned to you.');
						redirect('Booking');
						return;
					}
				}
				
				$titles = array('tab_title' => 'HolidayGoGoGo | Booking', 'breadcrumb_title' => 'Booking >> View');
				
				if(!empty($array['DepositDeadline'])) {
					$array['DepositDeadline'] = date('d/m/Y', strtotime($array['DepositDeadline']));
				}
				$array['FullPaymentDeadline'] = date('d/m/Y', strtotime($array['FullPaymentDeadline']));
				if(!empty($array['AdditionalPaymentDeadline'])) {
					$array['AdditionalPaymentDeadline'] = date('d/m/Y', strtotime($array['AdditionalPaymentDeadline']));
				}
				if(!empty($array['PaymentOutSupplierFull'])) {
					$array['PaymentOutSupplierFull'] = date('d/m/Y', strtotime($array['PaymentOutSupplierFull']));
				}
				if(!empty($array['PaymentOutSupplierDeposit'])) {
					$array['PaymentOutSupplierDeposit'] = date('d/m/Y', strtotime($array['PaymentOutSupplierDeposit']));
				}
				if(!empty($array['StartDate']) && !empty($array['EndDate'])) {
					$array['TravelDate'] = date('d/m/Y', strtotime($array['StartDate'])) . ' - ' . date('d/m/Y', strtotime($array['EndDate']));
				} else {
					$array['TravelDate'] = null;
				}
				// Ensure AllowReview is set (default to 1 if not set or null)
				if (!isset($array['AllowReview']) || $array['AllowReview'] === null) {
					$array['AllowReview'] = 1;
				} else {
					// Convert to integer to ensure it's 0 or 1
					$array['AllowReview'] = (int)$array['AllowReview'];
				}
				$array['BookingProductID'] = ($this->Booking_Product_Model->Read_Last_Booking_Product_ID()) + 1;
				$array['Tag'] = explode(',', $array['Tag']);
				$array['Subtotal'] = number_format($array['Subtotal'], 2, '.', ',');
				$array['Discount'] = $array['Discount'] != 0.00 ? number_format($array['Discount'], 2, '.', ',') : '';
				// Store raw NetTotal before formatting for deposit calculation
				$net_total_raw = isset($array['NetTotal']) ? floatval($array['NetTotal']) : 0;
				$array['NetTotal'] = number_format($array['NetTotal'], 2, '.', ',');
				// Handle DepositPercentage - store original DB value for comparison
				$array['DepositPercentageOriginal'] = isset($array['DepositPercentage']) ? $array['DepositPercentage'] : 0;
				$array['DepositModeOriginal'] = isset($array['DepositMode']) ? $array['DepositMode'] : 'percentage';
				$array['DepositFixedAmountOriginal'] = isset($array['DepositFixedAmount']) ? $array['DepositFixedAmount'] : 0;
				// For View page: use actual DB value (even if 0)
				if (!isset($array['DepositPercentage'])) {
					$array['DepositPercentage'] = 0;
				}
				if (!isset($array['DepositMode'])) {
					$array['DepositMode'] = 'percentage';
				}
				if (!isset($array['DepositFixedAmount'])) {
					$array['DepositFixedAmount'] = 0;
				}
				// Calculate deposit information
				$deposit_mode = isset($array['DepositMode']) ? $array['DepositMode'] : 'percentage';
				if ($deposit_mode == 'fixed') {
					$deposit_total = isset($array['DepositFixedAmount']) ? floatval($array['DepositFixedAmount']) : 0;
				} else {
					$deposit_percentage_raw = isset($array['DepositPercentage']) ? $array['DepositPercentage'] : 0;
					$deposit_total = ceil(($net_total_raw * $deposit_percentage_raw) / 100);
				}
				$array['DepositTotal'] = $deposit_total;
				// Calculate deposit paid from payments
				$deposit_paid = 0;
				if (isset($array['BookingID'])) {
					$payments = $this->Booking_Model->Read_Payments($array['BookingID']);
					if (!empty($payments)) {
						foreach ($payments as $payment) {
							if (($payment->Status == 'Y' || $payment->Status == 'P') && $payment->Credit > 0) {
								$deposit_paid += $payment->Credit;
							}
						}
					}
				}
				$array['DepositPaid'] = $deposit_paid;
				// Calculate deposit status and format Deposit Paid display
				$deposit_difference = $deposit_paid - $deposit_total;
				if ($deposit_paid > 0) {
					$deposit_paid_display = number_format($deposit_paid, 2, '.', ',');

					if ($deposit_difference < -0.01) {
						$deposit_paid_display .= ' (Underpaid: RM ' . number_format(abs($deposit_difference), 2, '.', ',') . ')';
						$array['DepositPaidColor'] = '#FFA500';
					}
				} else {
					$deposit_paid_display = '0.00';
				}
					$array['DepositPaidDisplay'] = $deposit_paid_display;
					$array['admins'] = $this->Booking_Model->Read_Admins();
					$array['notify_admins'] = $this->Notification_Model->Build_Admin_Handles($this->Booking_Model->Read_Notify_Admins());
					$array['booking_op_admins'] = $this->Booking_Model->Read_Booking_OP_Admins();

				if(empty($array['ProductSequence'])) {
					$array['ProductSequence'] = explode(',', $array['ProductSequence']);
					$array['booking_products'] = $this->Booking_Product_Model->Read();
				} else {
					$array['ProductSequence'] = explode(',', $array['ProductSequence']);
					$booking_products = $this->Booking_Product_Model->Read();
					$array['booking_products'] = [];
					for($i = 0; $i < count($array['ProductSequence']); $i++) {
						foreach($booking_products as $booking_product) {
							if($booking_product->BookingProductID == $array['ProductSequence'][$i]) {
								array_push($array['booking_products'], $booking_product);
							}
						}
					}
				}
				$array['categories'] = $this->Booking_Model->Read_Categories();
				$array['products'] = $this->Booking_Model->Read_Products();
				$array['footers'] = $this->Booking_Model->Read_Footers();
				$array['country_codes'] = $this->Booking_Model->Read_Country_Codes();
				$array['tags'] = $this->Booking_Model->Read_Tags();
				$array['sources'] = $this->Booking_Model->Read_Sources_With_Inactive($array['Source']);

				// Get Destination Name
				foreach($array['categories'] as $category) {
					if($category->CategoryID == $array['Destination']) {
						$array['DestinationName'] = $category->Name;
						break;
					}
				}
				
				// Get Source Name
				foreach($array['sources'] as $source) {
					if($source->SourceID == $array['Source']) {
						$array['SourceName'] = $source->Name;
						break;
					}
				}
				
				foreach($array['booking_products'] as $booking_product) {
					$booking_product->Price = number_format((float)($booking_product->Price ?? 0), 2, '.', ',');
					$booking_product->Total = number_format((float)($booking_product->Total ?? 0), 2, '.', ',');
				}

				// Get booking checklists
				$array['booking_checklists'] = $this->get_booking_checklists($array['booking_products']);
				$array['completion_map'] = $this->Booking_Checklist_Completion_Model->Read_Completion_Map($array['BookingID']);

				// Get custom uploads
				$this->load->model('Custom_Upload_Model');
				$array['custom_uploads'] = $this->Custom_Upload_Model->Read($array['BookingID']);

				// Get booking status log timeline
				$this->load->model('Booking_Status_Log_Model');
				$array['status_logs'] = $this->Booking_Status_Log_Model->get_timeline_data($array['BookingID'], false);
				
				// Mark as view mode (read-only)
				$array['is_view_mode'] = true;

				if(isset($_GET['nick'])) { echo "<pre>"; print_r($array); exit; }
				$this->load->view('layout/header', $titles);
				$this->load->view('booking/view', $array);
				$this->load->view('layout/footer');
			} else {
				redirect('Booking');
			}
		} else {
			redirect('Dashboard');
		}
	}
	
	function Update_Cancel_Status()
	{
		if(in_array('AB', $this->session->access_control)) {
			if($this->input->is_ajax_request()) {
				$this->Booking_Model->Update_Cancel_Status_With_Reason();
				$this->Booking_Model->Create_Booking_Log_Cancel();
				echo json_encode(true);
			} else {
				$this->Booking_Model->Update_Cancel_Status();
				$this->Booking_Model->Create_Booking_Log();
				if(strpos($this->input->get('param'), '?') == true) {
					redirect('Booking?' . explode('?', $this->input->get('param'))[1]);
				} else {
					redirect('Booking');
				}
			}
		} else {
			redirect('Dashboard');
		}
	}

	function Update_Partial_Refund_Status()
	{
		if(in_array('AB', $this->session->access_control)) {
			if($this->input->is_ajax_request()) {
				$this->Booking_Model->Update_Partial_Refund_Status_With_Reason();
				$this->Booking_Model->Create_Booking_Log_Partial_Refund();
				echo json_encode(true);
			} else {
				$this->Booking_Model->Update_Partial_Refund_Status();
				$this->Booking_Model->Create_Booking_Log();
				if(strpos($this->input->get('param'), '?') == true) {
					redirect('Booking?' . explode('?', $this->input->get('param'))[1]);
				} else {
					redirect('Booking');
				}
			}
		} else {
			redirect('Dashboard');
		}
	}

	function Update_Lock_Status() 
	{
		$booking_id = $this->input->get('booking_id');
		$new_lock_status = $this->input->get('new_lock_status');
		
		// Update lock status
		$this->Booking_Model->Update_Lock_Status();
		$this->Booking_Model->Create_Booking_Log();
		
		// If locking guest list (LockStatus = 'Y'), automatically advance status to PTV if conditions are met
		if ($new_lock_status == 'Y') {
			$this->load->helper('booking_flow');
			
			// Get updated booking
			$booking = $this->Booking_Model->getBookingById($booking_id);
			
			if ($booking) {
				// Determine the correct status based on current state
				$status_info = determine_booking_status_from_state($booking_id, $booking, $this);

				// If determined status is PTV and current status is not PTV, update it
				if ($status_info['status'] == 'PTV' && $booking->Status != 'PTV') {
					$this->Booking_Model->Update_Status('PTV', $booking_id);
					$this->Booking_Model->Create_Booking_Log2($booking->Status, 'PTV', $booking_id);
					
					// Log the status change
					$this->load->helper('booking_status_log');
					$admin_id = $this->session->userdata('admin_id') ?: 0;
					log_booking_status_change(
						$booking_id,
						'PTV',
						$booking->Status,
						$admin_id,
						'Guest list locked - Status advanced to PENDING TRAVEL VOUCHER',
						true
					);
				}
			}
		}
		
		if ($this->input->get('return_to') === 'booking') {
			redirect('Booking/Update?booking_id=' . $booking_id);
		} else {
			redirect('Guest_List?gl=' . $this->input->get('gl'));
		}
	}

	function Update_Travel_Insurance_Status()
	{
		$this->Booking_Model->Update_Travel_Insurance_Status();
		$this->Booking_Model->Create_Booking_Log();
		redirect('Guest_List?gl=' . $this->input->get('gl'));
	}
	
	function Update_After_Sales_Service()
	{
		if(in_array('AB', $this->session->access_control)) {
			$this->Booking_Model->Update_After_Sales_Service();
			$this->Booking_Model->Create_Booking_Log();
			if(strpos($this->input->get('param'), '?') == true) {
				redirect('Booking?' . explode('?', $this->input->get('param'))[1]);
			} else {
				redirect('Booking');
			}
		} else {
			redirect('Dashboard');
		}
	}

	function Update_Status() 
	{
		if(in_array('AB', $this->session->access_control)) {
			// Load booking flow helper
			$this->load->helper('booking_flow');
			
			// Get current booking status
			$booking = $this->Booking_Model->getBookingById($this->input->get('booking_id'));
			if (!$booking) {
				redirect('Dashboard');
				return;
			}
			
			$current_status = $booking->Status;
			$new_status = $this->input->get('new_status');
			
			// Validate status transition
			$validation = validate_booking_status_flow($current_status, $new_status, false);
			
			if (!$validation['valid']) {
				// Set error message and redirect
				$this->session->set_flashdata('error', $validation['message']);
				if(strpos($this->input->get('param'), '?') == true) {
					redirect('Booking?' . explode('?', $this->input->get('param'))[1]);
				} else {
					redirect('Booking');
				}
				return;
			}
			
			$this->Booking_Model->Update_Status($new_status, $this->input->get('booking_id'));
			$this->Booking_Model->Create_Booking_Log();
			
			// Add status history log
			$this->load->helper('booking_status_log');
			$admin_id = $this->session->userdata('admin_id');
			
			// Get status labels for description
			$status_labels = array(
				'PBC' => 'PENDING BC CONFIRMATION',
				'P' => 'PENDING PAYMENT',
				'PBO' => 'PENDING BOOKING OPERATION',
				'PTV' => 'PENDING TRAVEL VOUCHER',
				'PT' => 'PENDING TRAVEL',
				'OG' => 'ON-GOING',
				'Y' => 'COMPLETED',
				'C' => 'CANCELLED'
			);
			
			$from_label = isset($status_labels[$current_status]) ? $status_labels[$current_status] : $current_status;
			$to_label = isset($status_labels[$new_status]) ? $status_labels[$new_status] : $new_status;
			
			// Create description based on status transition
			$description = "Status changed from {$from_label} to {$to_label}";
			if($current_status == 'PTV' && $new_status == 'PT') {
				$description = "Travel Voucher Sent - Status changed to PENDING TRAVEL";
			}
			if($current_status == 'PBO' && $new_status == 'PT') {
				$description = "Approve Travel Voucher - Status changed from PENDING BOOKING OPERATION to PENDING TRAVEL";
			}
			
			log_booking_status_change(
				$this->input->get('booking_id'),
				$new_status,
				$current_status,
				$admin_id,
				$description,
				true
			);
			
			$this->load->config('status_mapping');
			$this->load->helper('autocount');
			$config = get_autocount_config();
			$statusMap = $config['booking_to_autocount_status'];
			$newStatus = $this->input->get('new_status');
			$autoCountStatus = $statusMap[$newStatus] ?? 0; // default Pending

			// $bookingData = $this->Booking_Model->getBookingById(
			// 	$this->input->get('booking_id')
			// );

			// $statuses = !empty($this->config->item('booking_sync_status')) ? $this->config->item('booking_sync_status') : ['BOOKING CONFIRMATION'];

			// if (in_array($bookingData['BookingConfirmationTitle'], $statuses)) {
			// 	if (!empty($bookingData->BookingNumber)) {
			// 		$quotationData = [
			// 			'DocNo'  => $bookingData->BookingNumber,
			// 			'master' => [
			// 				'Status' => $autoCountStatus
			// 			]
			// 		];

			// 		$respond = $this->autocount_update($quotationData);
			// 		$booking = $this->Booking_Model->find($this->input->get('booking_id'));
			// 		if ($booking != null && $respond != null) {
			// 			if ($respond['error']) {
			// 				$this->Booking_Model->update_by_id($this->input->get('booking_id'), [
			// 					'AutocountSyncMessage' => json_encode($respond),
			// 				]);
			// 			} elseif ($respond['status'] === 201 || $respond['status'] === 204) {
			// 				$this->Booking_Model->update_by_id($this->input->get('booking_id'), [
			// 					'AutocountSyncMessage' => json_encode($respond),
			// 					'AutocountSyncStatus' => 'U'
			// 				]);
			// 			} 
			// 		}	
			// 	}
			// }
			// $this->Booking_Model->update_by_id($this->input->get('booking_id'), [
			// 	'AutocountSyncAction'  => 'S',
			// 	'AutocountSyncStatus'  => 'P'
			// ]);

			if(strpos($this->input->get('param'), '?') == true) {
				redirect('Booking?' . explode('?', $this->input->get('param'))[1]);
			} else {
				redirect('Booking');
			}
		} else {
			redirect('Dashboard');
		}
	}

	/**
	 * Approve BC - Change status from PBC (PENDING BC CONFIRMATION) to P (PENDING PAYMENT)
	 * This action should only be available when booking is in PBC status
	 */
	function Approve_BC()
	{
		// Allow AB access control OR Sales Agent (level 20) for their own bookings
		$is_sales_agent = $this->session->userdata('level') == 20;
		$has_ab_access = in_array('AB', $this->session->access_control);
		
		if($has_ab_access || $is_sales_agent) {
			// Load helpers
			$this->load->helper('booking_flow');
			$this->load->helper('booking_status_log');
			$this->load->helper('debug_log');

			// Get and validate booking_id
			$booking_id = $this->input->get('booking_id');
			if (empty($booking_id)) {
				$this->session->set_flashdata('error', 'Booking ID is required.');
				if(strpos($this->input->get('param'), '?') == true) {
					redirect('Booking?' . explode('?', $this->input->get('param'))[1]);
				} else {
					redirect('Booking');
				}
				return;
			}
			
			// Get booking
			$booking = $this->Booking_Model->getBookingById($booking_id);
			if (!$booking) {
				$this->session->set_flashdata('error', 'Booking not found.');
				if(strpos($this->input->get('param'), '?') == true) {
					redirect('Booking?' . explode('?', $this->input->get('param'))[1]);
				} else {
					redirect('Booking');
				}
				return;
			}
			
			// Sales Agents can only approve their own bookings
			if($is_sales_agent && !$has_ab_access) {
				if($booking->SalesAgent != $this->session->userdata('admin_id')) {
					$this->session->set_flashdata('error', 'You can only approve bookings assigned to you.');
					if(strpos($this->input->get('param'), '?') == true) {
						redirect('Booking?' . explode('?', $this->input->get('param'))[1]);
					} else {
						redirect('Booking');
					}
					return;
				}
			}
			
			// Validate that BC is not already approved
			if (!empty($booking->bc_approved) && $booking->bc_approved == 1) {
				$this->session->set_flashdata('error', 'This booking BC has already been approved.');
				if(strpos($this->input->get('param'), '?') == true) {
					redirect('Booking?' . explode('?', $this->input->get('param'))[1]);
				} else {
					redirect('Booking');
				}
				return;
			}
			
			// Determine target status based on current booking state (payment, checklists, travel voucher)
			$status_info = determine_status_after_bc_approval($booking_id, $booking, $this);
			$target_status = $status_info['status'];
			$status_description = $status_info['description'];
			
			// Validate the status transition from current status to target status
			$validation = validate_booking_status_flow($booking->Status, $target_status, false);
			
			if (!$validation['valid']) {
				// If can't progress to determined status, fall back to P
				if ($target_status != 'P') {
					debug_log("Validation failed for {$target_status}, falling back to P", 'Approve_BC');
					$target_status = 'P';
					$status_description = 'BC Approved - Status changed to PENDING PAYMENT';
					$validation = validate_booking_status_flow($booking->Status, $target_status, false);
				}
				
				if (!$validation['valid']) {
					$this->session->set_flashdata('error', $validation['message']);
					if(strpos($this->input->get('param'), '?') == true) {
						redirect('Booking?' . explode('?', $this->input->get('param'))[1]);
					} else {
						redirect('Booking');
					}
					return;
				}
			}
			
			// Update status to target status
			$this->Booking_Model->Update_Status($target_status, $booking_id);
			
			// Update BC approval fields
			$admin_id = $this->session->userdata('admin_id');
			$this->Booking_Model->Update_BC_Approval($booking_id, 1, $admin_id);
			
			// Log the status change with custom description
			log_booking_status_change(
				$booking_id,
				$target_status,  // to_status
				'PBC',  // from_status
				$admin_id,  // created_by
				$status_description,  // description
				true  // show_to_customer
			);
			
			// If status is PBO, check if we can advance further (checklists might be completed)
			if ($target_status == 'PBO') {
				$updated_booking = $this->Booking_Model->getBookingById($booking_id);
				if ($updated_booking) {
					check_and_advance_status_if_no_checklist_or_all_completed($booking_id, $updated_booking, $admin_id, $this);
					// Re-fetch booking in case status was advanced
					$updated_booking = $this->Booking_Model->getBookingById($booking_id);
					$target_status = $updated_booking->Status;
				}
			}
			
			// Set success message
			$success_message = 'Booking BC has been approved.';
			$status_labels = array(
				'P' => 'PENDING PAYMENT',
				'PP' => 'PARTIAL PAYMENT',
				'PBO' => 'PENDING BOOKING OPERATION',
				'PTV' => 'PENDING TRAVEL VOUCHER',
				'PT' => 'PENDING TRAVEL',
				'Y' => 'COMPLETED'
			);
			$target_label = isset($status_labels[$target_status]) ? $status_labels[$target_status] : $target_status;
			$success_message .= " Status advanced to {$target_label}.";
			$this->session->set_flashdata('success', $success_message);
			
			// Redirect back to booking list
			if(strpos($this->input->get('param'), '?') == true) {
				redirect('Booking?' . explode('?', $this->input->get('param'))[1]);
			} else {
				redirect('Booking');
			}
		} else {
			redirect('Dashboard');
		}
	}

	function Delete() 
	{
		if(in_array('RB', $this->session->access_control)) {
			$this->Universal_Model->Delete('BookingID', $this->input->get('booking_id'), 'booking');
			$this->Booking_Model->Create_Booking_Log2($this->input->get('status'), 'N', $this->input->get('booking_id'));
			$this->Universal_Model->Delete('BookingID', $this->input->get('booking_id'), 'booking_product');
			$this->Universal_Model->Delete('BookingID', $this->input->get('booking_id'), 'guest_list');
			$this->Universal_Model->Delete('BookingID', $this->input->get('booking_id'), 'payment');
			//$this->Booking_Model->update_by_id($this->input->get('booking_id'), ['AutocountSyncStatus' => 'D']);
			// Delete from AutoCount (Quotation)

			// $bookingData = $this->Booking_Model->getBookingById($this->input->get('booking_id'));
        	// $bookingNumber = (!empty($bookingData) && !empty($bookingData->BookingNumber)) ? $bookingData->BookingNumber : '';

			// $statuses = !empty($this->config->item('booking_sync_status')) ? $this->config->item('booking_sync_status') : ['BOOKING CONFIRMATION'];

			// if (in_array($bookingData['BookingConfirmationTitle'], $statuses)) {
			// 	if (!empty($bookingNumber)) {
			// 		$respond = $this->autocount_delete([
			// 			'BookingNumber' => $bookingNumber
			// 		]);

			// 		$booking = $this->Booking_Model->find($this->input->get('booking_id'));
			// 		if ($booking != null && $respond != null) {
			// 			if ($respond['error']) {
			// 				$this->Booking_Model->update_by_id($this->input->get('booking_id'), [
			// 					'AutocountSyncMessage' => json_encode($respond),
			// 				]);
			// 			} elseif ($respond['status'] === 201 || $respond['status'] === 204) {
			// 				$this->Booking_Model->update_by_id($this->input->get('booking_id'), [
			// 					'AutocountSyncMessage' => json_encode($respond),
			// 					'AutocountSyncStatus' => 'D'
			// 				]);
			// 			} 
			// 		}						
			// 	}
			// }

			$bookingInfo = get_object_vars($this->Booking_Model->find($this->input->get('booking_id')));
			if (!empty($bookingInfo)) {
				if ($bookingInfo['AutocountSyncAction'] == 'C' && $bookingInfo['AutocountSyncStatus'] == 'S') {
					$this->Booking_Model->update_by_id($this->input->get('booking_id'), [
						'AutocountSyncAction' => 'D',
						'AutocountSyncStatus' => 'P'
					]);				
				} else if ($bookingInfo['AutocountSyncAction'] == 'U') {
					$this->Booking_Model->update_by_id($this->input->get('booking_id'), [
						'AutocountSyncAction' => 'D',
						'AutocountSyncStatus' => 'P'
					]);
				} else {
					$this->Booking_Model->update_by_id($this->input->get('booking_id'), [
						'AutocountSyncStatus' => 'P'
					]);
				}
			}
			// $this->Booking_Model->update_by_id($this->input->get('booking_id'), [
			// 	'AutocountSyncAction'  => 'D',
			// 	'AutocountSyncStatus'  => 'P'
			// ]);
			
		} else {
			redirect('Dashboard');
		}
	}

	function Download() {
		$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
		$spreadsheet->getActiveSheet()->setTitle('Booking Records');
		$spreadsheet->getProperties()->setCreator('HolidayGoGoGo');
		$spreadsheet->getActiveSheet()->setCellValue('A1', 'BOOKING DATE');
		$spreadsheet->getActiveSheet()->setCellValue('B1', 'SALES AGENT');
		$spreadsheet->getActiveSheet()->setCellValue('C1', 'BOOKING NUMBER');
		$spreadsheet->getActiveSheet()->setCellValue('D1', 'RESERVATION NUMBER');
		$spreadsheet->getActiveSheet()->setCellValue('E1', 'CUSTOMER');
		$spreadsheet->getActiveSheet()->setCellValue('F1', 'MOBILE');
		$spreadsheet->getActiveSheet()->setCellValue('G1', 'TRAVEL DATE');
		$spreadsheet->getActiveSheet()->setCellValue('H1', 'PAX NUMBER');
		$spreadsheet->getActiveSheet()->setCellValue('I1', 'DEPOSIT DEADLINE');
		$spreadsheet->getActiveSheet()->setCellValue('J1', 'FULL PAYMENT DEADLINE');
		$spreadsheet->getActiveSheet()->setCellValue('K1', 'DESTINATION');
		$spreadsheet->getActiveSheet()->setCellValue('L1', 'SUBTOTAL');
		$spreadsheet->getActiveSheet()->setCellValue('M1', 'DISCOUNT');
		$spreadsheet->getActiveSheet()->setCellValue('N1', 'NET TOTAL');
		$spreadsheet->getActiveSheet()->setCellValue('O1', 'PROFIT');
		$spreadsheet->getActiveSheet()->setCellValue('P1', 'STATUS');
		$spreadsheet->getActiveSheet()->setCellValue('Q1', 'REMARK');
		$spreadsheet->getActiveSheet()->setCellValue('R1', 'CHAT LANGUAGE');
		$spreadsheet->getActiveSheet()->setCellValue('S1', 'SOURCE');
		$row = 2;
		$bookings = $this->Booking_Model->Read_Bookings_With_Guest_Lists('Y');
		if(isset($_GET['nick'])) {
			print_r($this->db->last_query());exit;
		}
		$spreadsheet->getActiveSheet()->getStyle('A1:S1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_BLACK);
		$spreadsheet->getActiveSheet()->getStyle('A1:S1')->getFont()->getColor()->setARGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE);
		$spreadsheet->getActiveSheet()->getStyle('A1:S1')->getFont()->setBold(true);
		if(!empty($bookings)) {
			$total_subtotal = 0;
			$total_discount = 0;
			$total_net_total = 0;
			$total_profit = 0;
			foreach($bookings as $booking) {
				$total_subtotal += $booking->Subtotal;
				$total_discount += $booking->Discount;
				$total_net_total += $booking->NetTotal;
				if(!empty($booking->AdditionalPaymentDeadline)) {
					$booking->AdditionalPaymentDeadline = strtoupper(date('j M Y', strtotime($booking->AdditionalPaymentDeadline)));
				}
				$booking->CustomerMobile = $booking->CountryCode . str_replace([' ', '-'], '', $booking->CustomerMobile);
				if(!empty($booking->StartDate) && !empty($booking->EndDate)) {
					$booking->TravelDate = strtoupper(date('j M', strtotime($booking->StartDate)) . ' - ' . date('j M Y', strtotime($booking->EndDate)));
				} else {
					$booking->TravelDate = null;
				}
				if(!empty($booking->Adult)) {
					$booking->Adult = $booking->Adult == 1 ? $booking->Adult . ' ADULT ' : $booking->Adult . ' ADULTS ';
				}
				if(!empty($booking->Children)) {
					$booking->Children = $booking->Children == 1 ? $booking->Children . ' CHILD ' : $booking->Children . ' CHILDREN ';
				}
				if(!empty($booking->Infant)) {
					$booking->Infant = $booking->Infant == 1 ? $booking->Infant . ' INFANT ' : $booking->Infant . ' INFANTS ';
				}
				if(!empty($booking->Adult) && !empty($booking->Children) && !empty($booking->Infant)) {
					$booking->PaxNumber = $booking->Adult . '& ' . $booking->Children . '& ' . $booking->Infant;
				} else {
					if(!empty($booking->Adult) && empty($booking->Children) && !empty($booking->Infant)) {
						$booking->PaxNumber = $booking->Adult . '& ' . $booking->Infant;
					} else {
						if(!empty($booking->Adult) && !empty($booking->Children) && empty($booking->Infant)) {
							$booking->PaxNumber = $booking->Adult . '& ' . $booking->Children;
						} else {
							if(!empty($booking->Adult) && empty($booking->Children) && empty($booking->Infant)) {
								$booking->PaxNumber = $booking->Adult;
							} else {
								if(empty($booking->Adult) && !empty($booking->Children) && !empty($booking->Infant)) {
									$booking->PaxNumber = $booking->Children . '& ' . $booking->Infant;
								} else {
									if(empty($booking->Adult) && empty($booking->Children) && !empty($booking->Infant)) {
										$booking->PaxNumber = $booking->Infant;
									} else {
										$booking->PaxNumber = $booking->Children;
									}
								}
							}
						}
					}
				}
				$booking->Discount = $booking->Discount == 0.00 ? '' : $booking->Discount;
				if($booking->LockStatus == 'N' && $booking->Status == 'PTV') {
					$booking->Status = 'PGL';
				}
				if($booking->AfterSalesService == 'PENDING' && $booking->Status == 'Y') {
					$booking->Status = 'PR';
				}
				if(empty($booking->DepositDeadline)) {
					if(date('Y-m-d') > $booking->FullPaymentDeadline && ($booking->Status == 'P' || $booking->Status == 'PP')) {
						$booking->Status = 'PO';
					}
				} else {
					if((date('Y-m-d') > $booking->DepositDeadline && $booking->Status == 'P') || (date('Y-m-d') > $booking->FullPaymentDeadline && ($booking->Status == 'P' || $booking->Status == 'PP'))) {
						$booking->Status = 'PO';
					}
				}
				if(!empty($booking->DepositDeadline)) {
					$booking->DepositDeadline = strtoupper(date('j M Y', strtotime($booking->DepositDeadline)));
				}
				$booking->FullPaymentDeadline = strtoupper(date('j M Y', strtotime($booking->FullPaymentDeadline)));
				if($booking->CancelStatus == 'Y') {
					$booking->Status = 'CANCELLED';
				} else {
					switch($booking->Status) {
						case 'Y':
							$booking->Status = 'COMPLETED';
							break;
						case 'PR':
							$booking->Status = 'PENDING REVIEW';
							break;
						case 'P':
							$booking->Status = 'PENDING PAYMENT';
							break;
						case 'PP':
							$booking->Status = 'PARTIAL PAYMENT';
							break;
						case 'PTV':
							$booking->Status = 'PENDING TRAVEL VOUCHER';
							break;
						case 'PGL':
							$booking->Status = 'PENDING GUEST LIST';
							break;
						case 'PT':
							$booking->Status = 'PENDING TRAVEL';
							break;
						case 'OG':
							$booking->Status = 'ON-GOING';
							break;
						case 'PO':
							$booking->Status = 'PAYMENT OVERDUE';
					}
				}
				$booking->InsertDate = strtoupper(date('j M Y', strtotime($booking->InsertDate)));
				$payments = $this->Booking_Model->Read_Payments($booking->BookingID);
				$total_credit = 0;
				$total_debit = 0;
				$net_profit = 0;
				$profit_margin = 0;
				if(!empty($payments)) {
					foreach($payments as $payment) {
						if($payment->Status == 'Y' || $payment->Status == 'P') {
							if($payment->Credit != 0.00) {
								$total_credit += $payment->Credit;
							} else {
								$total_debit += $payment->Debit;
							}
						}
					}
					$net_profit = $total_credit - $total_debit;
					if($net_profit != 0) {
						$profit_margin = round(($net_profit / $booking->NetTotal) * 100);
					}
				}
				$booking->Profit = $net_profit != 0 ? 'RM ' . number_format($net_profit, 2, '.', ',') . ' (' . $profit_margin . '%)' : 'RM ' . number_format($net_profit, 2, '.', ',') . ' (0%)';
				$total_profit += $net_profit;
				$spreadsheet->getActiveSheet()->setCellValueExplicit('A' . $row, $booking->InsertDate, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('B' . $row, $booking->SalesAgentName, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('C' . $row, $booking->BookingNumber, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('D' . $row, $booking->ReservationNumber, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('E' . $row, $booking->Customer, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('F' . $row, $booking->CustomerMobile, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('G' . $row, $booking->TravelDate, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('H' . $row, $booking->PaxNumber, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('I' . $row, $booking->DepositDeadline, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('J' . $row, $booking->FullPaymentDeadline, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('K' . $row, $booking->DestinationName, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValue('L' . $row, $booking->Subtotal);
				$spreadsheet->getActiveSheet()->setCellValue('M' . $row, $booking->Discount);
				$spreadsheet->getActiveSheet()->setCellValue('N' . $row, $booking->NetTotal);
				$spreadsheet->getActiveSheet()->setCellValue('O' . $row, $booking->Profit);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('P' . $row, $booking->Status, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('Q' . $row, $booking->BookingRemark, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('R' . $row, $booking->ChatLanguage, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('S' . $row, $booking->SourceName, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$row++;
			}
			$total_profit = $total_profit != 0 && $total_net_total != 0 ? number_format($total_profit, 2, '.', ',') . ' (' . round(($total_profit / $total_net_total) * 100) . '%)' : number_format($total_profit, 2, '.', ',') . ' (0%)';
			$spreadsheet->getActiveSheet()->getStyle('L')->getNumberFormat()->setFormatCode('"RM "#,##0.00_-');
			$spreadsheet->getActiveSheet()->getStyle('M')->getNumberFormat()->setFormatCode('"RM "#,##0.00_-');
			$spreadsheet->getActiveSheet()->getStyle('N')->getNumberFormat()->setFormatCode('"RM "#,##0.00_-');
			$spreadsheet->getActiveSheet()->getStyle('O')->getNumberFormat()->setFormatCode('"RM "#,##0.00_-');
			$spreadsheet->getActiveSheet()->getCell('K' . ($row + 2))->setValue('Total');
			$spreadsheet->getActiveSheet()->getStyle('L' . ($row + 2) . ':' . 'O' . ($row + 2))->getBorders()->getTop()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
			$spreadsheet->getActiveSheet()->setCellValue('L' . ($row + 2), $total_subtotal);
			$spreadsheet->getActiveSheet()->setCellValue('M' . ($row + 2), $total_discount);
			$spreadsheet->getActiveSheet()->setCellValue('N' . ($row + 2), $total_net_total);
			$spreadsheet->getActiveSheet()->setCellValue('O' . ($row + 2), $total_profit);
			$spreadsheet->getActiveSheet()->getStyle('L' . ($row + 2) . ':' . 'O' . ($row + 2))->getBorders()->getBottom()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_DOUBLE);
			$spreadsheet->getActiveSheet()->getStyle('A:S')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
		} else {
			$spreadsheet->getActiveSheet()->mergeCells('A2:S2');
			$spreadsheet->getActiveSheet()->getCell('A2')->setValue('Booking Records Not Found');
			$spreadsheet->getActiveSheet()->getStyle('A:S')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
		}
		$spreadsheet->getActiveSheet()->getColumnDimension('A')->setWidth(35);
		$spreadsheet->getActiveSheet()->getColumnDimension('B')->setWidth(35);
		$spreadsheet->getActiveSheet()->getColumnDimension('C')->setWidth(35);
		$spreadsheet->getActiveSheet()->getColumnDimension('D')->setWidth(35);
		$spreadsheet->getActiveSheet()->getColumnDimension('E')->setWidth(35);
		$spreadsheet->getActiveSheet()->getColumnDimension('F')->setWidth(35);
		$spreadsheet->getActiveSheet()->getColumnDimension('G')->setWidth(35);
		$spreadsheet->getActiveSheet()->getColumnDimension('H')->setWidth(35);
		$spreadsheet->getActiveSheet()->getColumnDimension('I')->setWidth(35);
		$spreadsheet->getActiveSheet()->getColumnDimension('J')->setWidth(35);
		$spreadsheet->getActiveSheet()->getColumnDimension('K')->setWidth(35);
		$spreadsheet->getActiveSheet()->getColumnDimension('L')->setWidth(35);
		$spreadsheet->getActiveSheet()->getColumnDimension('M')->setWidth(35);
		$spreadsheet->getActiveSheet()->getColumnDimension('N')->setWidth(35);
		$spreadsheet->getActiveSheet()->getColumnDimension('O')->setWidth(35);
		$spreadsheet->getActiveSheet()->getColumnDimension('P')->setWidth(35);
		$spreadsheet->getActiveSheet()->getColumnDimension('Q')->setWidth(35);
		$spreadsheet->getActiveSheet()->getColumnDimension('R')->setWidth(35);
		$spreadsheet->getActiveSheet()->getColumnDimension('S')->setWidth(35);

		if($this->input->get('checkbox') == 'ON') {
			$spreadsheet->createSheet();
			$spreadsheet->setActiveSheetIndex(1);
			$spreadsheet->getActiveSheet()->setTitle('Guest Lists');
			$spreadsheet->getActiveSheet()->setCellValue('A1', 'BOOKING NUMBER');
			$spreadsheet->getActiveSheet()->setCellValue('B1', 'TRAVEL DATE');
			$spreadsheet->getActiveSheet()->setCellValue('C1', 'DESTINATION');
			$spreadsheet->getActiveSheet()->setCellValue('D1', 'GUEST TYPE');
			$spreadsheet->getActiveSheet()->setCellValue('E1', 'GUEST');
			$spreadsheet->getActiveSheet()->setCellValue('F1', 'GENDER');
			$spreadsheet->getActiveSheet()->setCellValue('G1', 'DATE OF BIRTH');
			$spreadsheet->getActiveSheet()->setCellValue('H1', 'NATIONALITY');
			$spreadsheet->getActiveSheet()->setCellValue('I1', 'GUEST IDENTIFICATION NUMBER');
			$spreadsheet->getActiveSheet()->setCellValue('J1', 'PASSPORT NUMBER');
			$spreadsheet->getActiveSheet()->setCellValue('K1', 'MOBILE');
			$spreadsheet->getActiveSheet()->setCellValue('L1', 'EMAIL');
			$spreadsheet->getActiveSheet()->setCellValue('M1', 'MARITAL STATUS');
			$spreadsheet->getActiveSheet()->setCellValue('N1', 'EMPLOYMENT');
			$spreadsheet->getActiveSheet()->setCellValue('O1', 'ADDRESS');
			$spreadsheet->getActiveSheet()->setCellValue('P1', 'POSTCODE');
			$spreadsheet->getActiveSheet()->setCellValue('Q1', 'CITY');
			$spreadsheet->getActiveSheet()->setCellValue('R1', 'STATE');
			$spreadsheet->getActiveSheet()->setCellValue('S1', 'COUNTRY');
			$spreadsheet->getActiveSheet()->setCellValue('T1', 'NOMINEE');
			$spreadsheet->getActiveSheet()->setCellValue('U1', 'NOMINEE IDENTIFICATION NUMBER');
			$spreadsheet->getActiveSheet()->setCellValue('V1', 'RELATIONSHIP');
			$row = 2;
			$guest_lists = $this->Booking_Model->Read_Bookings_With_Guest_Lists('N');
			$spreadsheet->getActiveSheet()->getStyle('A1:V1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_BLACK);
			$spreadsheet->getActiveSheet()->getStyle('A1:V1')->getFont()->getColor()->setARGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE);
			$spreadsheet->getActiveSheet()->getStyle('A1:V1')->getFont()->setBold(true);
			if(!empty($guest_lists)) {
				foreach($guest_lists as $guest) {
					if(!empty($guest->StartDate) && !empty($guest->EndDate)) {
						$guest->TravelDate = strtoupper(date('j M', strtotime($guest->StartDate)) . ' - ' . date('j M Y', strtotime($guest->EndDate)));
					} else {
						$guest->TravelDate = null;
					}
					if(!empty($guest->DateOfBirth)) {
						$guest->DateOfBirth = strtoupper(date('j M Y', strtotime($guest->DateOfBirth)));
					}
					if(!empty($guest->Nationality)) {
						$guest->Nationality = $this->Universal_Model->Read_Country($guest->Nationality);
					}
					if(!empty($guest->GuestCountryCode) && !empty($guest->GuestMobile)) {
						$country_code = $this->Universal_Model->Read_Country_Code($guest->GuestCountryCode);
						$guest->GuestMobile = $country_code . $guest->GuestMobile;
					} else {
						$guest->GuestMobile = null;
					}
					if(!empty($guest->Country)) {
						$guest->Country = $this->Universal_Model->Read_Country($guest->Country);
					}
					$spreadsheet->getActiveSheet()->setCellValueExplicit('A' . $row, $guest->BookingNumber, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
					$spreadsheet->getActiveSheet()->setCellValueExplicit('B' . $row, $guest->TravelDate, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
					$spreadsheet->getActiveSheet()->setCellValueExplicit('C' . $row, $guest->DestinationName, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
					$spreadsheet->getActiveSheet()->setCellValueExplicit('D' . $row, $guest->Type, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
					$spreadsheet->getActiveSheet()->setCellValueExplicit('E' . $row, $guest->GuestName, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
					$spreadsheet->getActiveSheet()->setCellValueExplicit('F' . $row, $guest->Gender, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
					$spreadsheet->getActiveSheet()->setCellValueExplicit('G' . $row, $guest->DateOfBirth, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
					$spreadsheet->getActiveSheet()->setCellValueExplicit('H' . $row, $guest->Nationality, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
					$spreadsheet->getActiveSheet()->setCellValueExplicit('I' . $row, $guest->IdentificationNumber, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
					$spreadsheet->getActiveSheet()->setCellValueExplicit('J' . $row, $guest->PassportNumber, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
					$spreadsheet->getActiveSheet()->setCellValueExplicit('K' . $row, $guest->GuestMobile, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
					$spreadsheet->getActiveSheet()->setCellValueExplicit('L' . $row, $guest->Email, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
					$spreadsheet->getActiveSheet()->setCellValueExplicit('M' . $row, $guest->MaritalStatus, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
					$spreadsheet->getActiveSheet()->setCellValueExplicit('N' . $row, $guest->Employment, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
					$spreadsheet->getActiveSheet()->setCellValueExplicit('O' . $row, $guest->Address, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
					$spreadsheet->getActiveSheet()->setCellValueExplicit('P' . $row, $guest->Postcode, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
					$spreadsheet->getActiveSheet()->setCellValueExplicit('Q' . $row, $guest->City, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
					$spreadsheet->getActiveSheet()->setCellValueExplicit('R' . $row, $guest->State, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
					$spreadsheet->getActiveSheet()->setCellValueExplicit('S' . $row, $guest->Country, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
					$spreadsheet->getActiveSheet()->setCellValueExplicit('T' . $row, $guest->Nominee, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
					$spreadsheet->getActiveSheet()->setCellValueExplicit('U' . $row, $guest->NomineeIdentificationNumber, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
					$spreadsheet->getActiveSheet()->setCellValueExplicit('V' . $row, $guest->Relationship, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
					$row++;
				}
				$spreadsheet->getActiveSheet()->getStyle('A:V')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
			} else {
				$spreadsheet->getActiveSheet()->mergeCells('A2:V2');
				$spreadsheet->getActiveSheet()->getCell('A2')->setValue('Guest List Records Not Found');
				$spreadsheet->getActiveSheet()->getStyle('A:V')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
			}
			$spreadsheet->getActiveSheet()->getColumnDimension('A')->setWidth(35);
			$spreadsheet->getActiveSheet()->getColumnDimension('B')->setWidth(35);
			$spreadsheet->getActiveSheet()->getColumnDimension('C')->setWidth(35);
			$spreadsheet->getActiveSheet()->getColumnDimension('D')->setWidth(35);
			$spreadsheet->getActiveSheet()->getColumnDimension('E')->setWidth(35);
			$spreadsheet->getActiveSheet()->getColumnDimension('F')->setWidth(35);
			$spreadsheet->getActiveSheet()->getColumnDimension('G')->setWidth(35);
			$spreadsheet->getActiveSheet()->getColumnDimension('H')->setWidth(35);
			$spreadsheet->getActiveSheet()->getColumnDimension('I')->setWidth(35);
			$spreadsheet->getActiveSheet()->getColumnDimension('J')->setWidth(35);
			$spreadsheet->getActiveSheet()->getColumnDimension('K')->setWidth(35);
			$spreadsheet->getActiveSheet()->getColumnDimension('L')->setWidth(35);
			$spreadsheet->getActiveSheet()->getColumnDimension('M')->setWidth(35);
			$spreadsheet->getActiveSheet()->getColumnDimension('N')->setWidth(35);
			$spreadsheet->getActiveSheet()->getColumnDimension('O')->setWidth(35);
			$spreadsheet->getActiveSheet()->getColumnDimension('P')->setWidth(35);
			$spreadsheet->getActiveSheet()->getColumnDimension('Q')->setWidth(35);
			$spreadsheet->getActiveSheet()->getColumnDimension('R')->setWidth(35);
			$spreadsheet->getActiveSheet()->getColumnDimension('S')->setWidth(35);
			$spreadsheet->getActiveSheet()->getColumnDimension('T')->setWidth(35);
			$spreadsheet->getActiveSheet()->getColumnDimension('U')->setWidth(35);
			$spreadsheet->getActiveSheet()->getColumnDimension('V')->setWidth(35);
		}
		
		$spreadsheet->setActiveSheetIndex(0);
		$booking_records = 'BOOKING_RECORDS_' . date('Ymd') . '.xlsx';

		if (ob_get_length()) ob_end_clean();

		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header('Content-Disposition: attachment;filename="' . $booking_records . '"');
		header('Cache-Control: max-age=0');
		header('Cache-Control: max-age=1');
		$writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
		$writer->save('php://output');
		exit;
	}

	function Duplicate()
	{
		if(in_array('GB', $this->session->access_control)) {
			$this->Update();
		} else {
			redirect('Dashboard');
		}
    }
	
	function Detect() {
		$redundant_booking_number = $this->Booking_Model->Detect();
		if($redundant_booking_number) {
			echo json_encode(true);
		} else {
			echo json_encode(false);
		}
	}

	public function bulkSyncToAutocount()
    {
        // Read raw JSON body
		$input = json_decode($this->input->raw_input_stream, true);
		$booking_ids = $input['booking_ids'] ? $input['booking_ids'] : [];

		if (empty($booking_ids)) {
			$this->output
				->set_content_type('application/json')
				->set_output(json_encode([
					'success' => false,
					'message' => 'No bookings selected'
				]));
			return;
		}

        $results = [];

        foreach ($booking_ids as $booking_id) {
            try {
				$booking = $this->Booking_Model->find($booking_id);
                if (!$booking) {
                    $results[$booking_id] = 'Not Found';
                    continue;
                }

				$bookingData = $this->Booking_Model->getAllBookingsWithGuests($booking_id);
				if (!empty($bookingData)) {
					$bookingData = (array) $bookingData[0]; 
				}

				$bookingProducts = $this->Booking_Model->getAllBookingsWithProducts($booking_id);
				$bookingProducts = array_map('get_object_vars', $bookingProducts); // convert to array

				$this->load->helper('autocount');
				$config = get_autocount_config();
				$statuses = !empty($config['booking_sync_status']) ? $config['booking_sync_status'] : ['BOOKING CONFIRMATION'];

				if (in_array($bookingData['BookingConfirmationTitle'], $statuses)) {
					switch ($bookingData['AutocountSyncStatus']) {
						case 'N': // new → create
							$quotationData = [
								'BookingNumber'   => $bookingData['BookingNumber'] ?? '',
								'InsertDate'      => $bookingData['InsertDate'] ?? date('Y-m-d'),
								'Customer'        => $bookingData['Customer'] ?? '',
								'guest_email'     => $bookingData['guest_email'] ?? '',
								'guest_address'   => $bookingData['guest_address'] ?? '',
								'guest_phone'     => $bookingData['guest_phone'] ?? '',
								'BokingRemark'    => $bookingData['BokingRemark'] ?? '',
								
								// Fields not in DB → set default or null
								'credit_term'     => null,
								'sales_location'  => '',
								'currency_rate'   => 1,
								'inclusive_tax'   => false,
								'is_round_adj'    => false,
								'tax_code'        => '',

								// Details
								'booking_product' => $bookingProducts
							];

							$respond = $this->autocount_create($quotationData);
							if ($booking != null && $respond != null) {
								if ($respond['error']) {
									$this->Booking_Model->update_by_id($booking_id, [
										'AutocountSyncMessage' => json_encode($respond),
									]);
									$results[$booking->BookingID] = $respond['error'];
								} elseif ($respond['status'] === 201 || $respond['status'] === 204) {
									$this->Booking_Model->update_by_id($booking_id, [
										'AutocountSyncMessage' => json_encode($respond),
										'AutocountSyncStatus' => 'C'
									]);
								} 
							}	
							$results[$booking->BookingID] = 'Created';
							break;

						case 'U': // update
						case 'C': // created → still allow update
							$quotationData = [
								'BookingNumber'   => $bookingData['BookingNumber'],
								'DocNo'           => $bookingData['BookingNumber'], // Fallback
								'master'          => [
									'DocDate'        => date('Y-m-d', strtotime($bookingData['InsertDate'])),
									'DebtorName'     => $bookingData['Customer'],
									'Email'          => $bookingData['guest_email'],
									'Address'        => $bookingData['guest_address'],
									'Phone1'         => $bookingData['guest_phone'],
									'DeliverAddress' => $bookingData['guest_address'],
									'DeliverContact' => $bookingData['Customer'],
									'DeliverPhone1'  => $bookingData['guest_phone'],
									'Remark1'        => $bookingData['BokingRemark'],
								],
								'booking_product' => $bookingProducts,
								'tax_code'        => '', // Default tax code if missing
								'saveApprove'     => null
							];
							$respond = $this->autocount_update($booking);
							if ($booking != null && $respond != null) {
								if ($respond['error']) {
									$this->Booking_Model->update_by_id($booking_id, [
										'AutocountSyncMessage' => json_encode($respond),
									]);
									$results[$booking->BookingID] = $respond['error'];
								} elseif ($respond['status'] === 201 || $respond['status'] === 204) {
									$this->Booking_Model->update_by_id($booking_id, [
										'AutocountSyncMessage' => json_encode($respond),
										'AutocountSyncStatus' => 'U'
									]);
								} 
							}	
							$results[$booking->BookingID] = 'Updated';
							break;

						case 'D': // delete
							$bookingNumber = (!empty($booking) && !empty($booking->BookingNumber)) ? $booking->BookingNumber : '';

							if (!empty($booking) && $booking->status == 'N') {
								if (!empty($bookingNumber)) {
									$respond = $this->autocount_delete([
										'BookingNumber' => $bookingNumber
									]);
									if ($booking != null && $respond != null) {
										if ($respond['error']) {
											$this->Booking_Model->update_by_id($booking_id, [
												'AutocountSyncMessage' => json_encode($respond),
											]);
											$results[$booking->BookingID] = $respond['error'];
										} elseif ($respond['status'] === 201 || $respond['status'] === 204) {
											$this->Booking_Model->update_by_id($booking_id, [
												'AutocountSyncMessage' => json_encode($respond),
												'AutocountSyncStatus' => 'D'
											]);
										} 
									}	
									$results[$booking->BookingID] = 'Deleted';
									break;
								}
							}
						case 'V': // void
							$bookingNumber = (!empty($booking) && !empty($booking->BookingNumber)) ? $booking->BookingNumber : '';

							if (!empty($bookingNumber)) {
								$respond = $this->autocount_void($booking);
								if ($booking != null && $respond != null) {
									if ($respond['error']) {
										$this->Booking_Model->update_by_id($booking_id, [
											'AutocountSyncMessage' => json_encode($respond),
										]);
										$results[$booking->BookingID] = $respond['error'];
									} elseif ($respond['status'] === 201 || $respond['status'] === 204) {
										$this->Booking_Model->update_by_id($booking_id, [
											'AutocountSyncMessage' => json_encode($respond),
											'AutocountSyncStatus' => 'V'
										]);
									} 
								}
								$results[$booking->BookingID] = 'Voided';
								break;
							}
							

						default:
							$results[$booking->BookingID] = 'Skipped';
							break;
					}
				}

            } catch (\Exception $e) {
                $results[$bookingData['BookingID']] = 'Error: ' . $e->getMessage();
            }
        }

		$this->output
        ->set_content_type('application/json')
        ->set_output(json_encode([
            'success' => true,
            'message' => implode("\n", $results) // return as plain text
        ]));
    }

	/**
	 * Bulk change autocount status to Pending (P) with reset logic
	 * If status is F: update directly to P
	 * If status is P: update to F first, then to P (to trigger re-sync)
	 */
	public function bulkChangeAutocountStatusToPending()
	{
		$json = file_get_contents('php://input');
		$data = json_decode($json, true);
		$booking_ids = isset($data['booking_ids']) ? $data['booking_ids'] : [];

		if (empty($booking_ids)) {
			echo json_encode(['success' => false, 'message' => 'No bookings selected']);
			return;
		}

		$result = $this->Booking_Model->Update_Autocount_Status_To_Pending_With_Reset($booking_ids);

		if ($result) {
			echo json_encode(['success' => true, 'message' => count($booking_ids) . ' booking(s) updated to Pending status']);
		} else {
			echo json_encode(['success' => false, 'message' => 'Failed to update bookings']);
		}
	}

	public function autocount_create($data)
	{
		try {
			$body['master'] = [
				'docNo'           => arr_get($data, 'BookingNumber'),
				'docNoFormatName' => arr_get($data, 'docNoFormatName', null),
				'docDate'         => arr_get($data, 'InsertDate'),
				'debtorCode'      => arr_get($data, 'debtor_code', ''),
				'debtorName'      => arr_get($data, 'Customer'),
				'email'           => arr_get($data, 'guest_email'),
				'emailCC'         => arr_get($data, 'emailCC', null),
				'emailBCC'        => arr_get($data, 'emailBCC', null),
				'address'         => arr_get($data, 'guest_address'),
				'attention'       => arr_get($data, 'attention', ''),
				'phone1'          => arr_get($data, 'guest_phone'),
				'fax1'            => arr_get($data, 'fax1', ''),
				'deliverAddress'  => arr_get($data, 'guest_address'),
				'deliverContact'  => arr_get($data, 'Customer'),
				'deliverPhone1'   => arr_get($data, 'guest_phone'),
				'deliverFax1'     => arr_get($data, 'deliver_fax1', ''),
				'ref'             => arr_get($data, 'ref', null),
				'description'     => arr_get($data, 'description', null),
				'note'            => arr_get($data, 'note', null),
				'salesAgent'      => arr_get($data, 'salesAgent', ''),
				'creditTerm'      => arr_get($data, 'credit_term', 'C.O.D.'),
				'salesLocation'   => arr_get($data, 'sales_location', 'HQ'),
				'remark1'         => arr_get($data, 'BokingRemark'),
				'remark2'         => arr_get($data, 'remark2', null),
				'remark3'         => arr_get($data, 'remark3', null),
				'remark4'         => arr_get($data, 'remark4', null),
				'currencyRate'    => arr_get($data, 'currency_rate', 1),
				'inclusiveTax'    => arr_get($data, 'inclusive_tax', false),
				'isRoundAdj'      => arr_get($data, 'is_round_adj', false),
				'yourRef'         => arr_get($data, 'yourRef', null),
				'validity'        => arr_get($data, 'validity', null),
				'cc'              => arr_get($data, 'cc', null),
				'deliveryTerm'    => arr_get($data, 'deliveryTerm', null),
				'paymentTerm'     => arr_get($data, 'paymentTerm', null),
			];

			$body['details'] = [];
			if (!empty($data['booking_product']) && is_array($data['booking_product'])) {
				foreach ($data['booking_product'] as $product) {
					$body['details'][] = [
						'productCode'        => arr_get($product, 'product_ProductCode'),
						'productVariant'     => arr_get($product, 'productVariant', null),
						'description'        => arr_get($product, 'product_Description'),
						'furtherDescription' => arr_get($product, 'furtherDescription', ''),
						'qty'                => arr_get($product, 'product_Quantity', 1),
						'unit'               => arr_get($product, 'unit', 'unit'),
						'unitPrice'          => arr_get($product, 'product_Price', 0),
						'discount'           => arr_get($product, 'discount', null),
						'taxCode'            => arr_get($product, 'tax_code', 'S-5'),
						'taxAdjustment'      => arr_get($product, 'taxAdjustment', 0),
						'localTaxAdjustment' => arr_get($product, 'localTaxAdjustment', 0),
						'deptNo'             => arr_get($product, 'deptNo', null),
					];
				}
			}

			$body['autoFillOption'] = [
				'taxCode' => arr_get($data, 'tax_code', true),
			];

			$body['saveApprove'] = arr_get($data, 'save_approve', null);

			// dd(json_encode($body));
			// return '1';
			return autocount_request(
				'POST',
				'quotation.create',
				$body,
				['docNo' => $data['BookingNumber']]
			);

		} catch (Exception $e) {
			log_message('error', 'Autocount create error: ' . $e->getMessage());

			return [
				'status' => 500,
				'error'  => $e->getMessage(),
				'data'   => [],
			];
		}
	}

   	public function autocount_update($data)
	{
		try {
			$docNo = arr_get($data, 'BookingNumber');

			$body['master'] = [
				'docNo'           => arr_get($data, 'BookingNumber'),
				'docNoFormatName' => arr_get($data, 'docNoFormatName', null),
				'docDate'         => arr_get($data, 'InsertDate'),
				'debtorCode'      => arr_get($data, 'debtor_code', ''),
				'debtorName'      => arr_get($data, 'Customer'),
				'email'           => arr_get($data, 'guest_email'),
				'emailCC'         => arr_get($data, 'emailCC', null),
				'emailBCC'        => arr_get($data, 'emailBCC', null),
				'address'         => arr_get($data, 'guest_address'),
				'attention'       => arr_get($data, 'attention', ''),
				'phone1'          => arr_get($data, 'guest_phone'),
				'fax1'            => arr_get($data, 'fax1', ''),
				'deliverAddress'  => arr_get($data, 'guest_address'),
				'deliverContact'  => arr_get($data, 'Customer'),
				'deliverPhone1'   => arr_get($data, 'guest_phone'),
				'deliverFax1'     => arr_get($data, 'deliver_fax1', ''),
				'ref'             => arr_get($data, 'ref', null),
				'description'     => arr_get($data, 'description', null),
				'note'            => arr_get($data, 'note', null),
				'salesAgent'      => arr_get($data, 'salesAgent', ''),
				'creditTerm'      => arr_get($data, 'credit_term', 'C.O.D.'),
				'salesLocation'   => arr_get($data, 'sales_location', 'HQ'),
				'remark1'         => arr_get($data, 'BokingRemark'),
				'remark2'         => arr_get($data, 'remark2', null),
				'remark3'         => arr_get($data, 'remark3', null),
				'remark4'         => arr_get($data, 'remark4', null),
				'currencyRate'    => arr_get($data, 'currency_rate', 1),
				'inclusiveTax'    => arr_get($data, 'inclusive_tax', false),
				'isRoundAdj'      => arr_get($data, 'is_round_adj', false),
				'yourRef'         => arr_get($data, 'yourRef', null),
				'validity'        => arr_get($data, 'validity', null),
				'cc'              => arr_get($data, 'cc', null),
				'deliveryTerm'    => arr_get($data, 'deliveryTerm', null),
				'paymentTerm'     => arr_get($data, 'paymentTerm', null),
			];

			$body['details'] = [];
			if (!empty($data['booking_product']) && is_array($data['booking_product'])) {
				foreach ($data['booking_product'] as $product) {
					$body['details'][] = [
						'productCode'        => arr_get($product, 'product_ProductCode'),
						'productVariant'     => arr_get($product, 'productVariant', null),
						'description'        => arr_get($product, 'product_Description'),
						'furtherDescription' => arr_get($product, 'furtherDescription', ''),
						'qty'                => arr_get($product, 'product_Quantity', 1),
						'unit'               => arr_get($product, 'unit', 'unit'),
						'unitPrice'          => arr_get($product, 'product_Price', 0),
						'discount'           => arr_get($product, 'discount', null),
						'taxCode'            => arr_get($product, 'tax_code', 'S-5'),
						'taxAdjustment'      => arr_get($product, 'taxAdjustment', 0),
						'localTaxAdjustment' => arr_get($product, 'localTaxAdjustment', 0),
						'deptNo'             => arr_get($product, 'deptNo', null),
					];
				}
			}

			$body['autoFillOption'] = [
				'taxCode' => arr_get($data, 'tax_code', true),
			];

			$body['saveApprove'] = arr_get($data, 'save_approve', null);

			return autocount_request(
				'PUT',
				'quotation.update',
				$body,
				['docNo' => $docNo]
			);

		} catch (Exception $e) {
			log_message('error', 'Autocount update error: ' . $e->getMessage());

			return [
				'status' => 500,
				'error'  => $e->getMessage(),
				'data'   => [],
			];
		}
	}

	public function autocount_update_status($data)
	{
		try {
			$docNo = arr_get($data, 'BookingNumber');
			
			// Map the status to AutoCount status code
			$status = arr_get($data, 'Status', 'S');
			$autoCountStatus = mapAutoCountStatus($status);  // Use the mapping helper

			// If no valid status is found, log an error and return
			if ($autoCountStatus === null) {
				log_message('error', 'Invalid status: ' . $status);
				return [
					'status' => 400,
					'error'  => 'Invalid status value provided.',
					'data'   => [],
				];
			}

			// Prepare the body for the request
			$body = [
				'documentStatus' => $autoCountStatus,  // Use mapped status
				'lostReason'     => arr_get($data, 'reason'),
			];

			// Make the API request
			return autocount_request(
				'PUT',
				'quotation.update_status',
				$body,
				['docNo' => $docNo]
			);

		} catch (Exception $e) {
			log_message('error', 'Autocount update_status error: ' . $e->getMessage());

			return [
				'status' => 500,
				'error'  => $e->getMessage(),
				'data'   => [],
			];
		}
	}

	public function autocount_delete($data)
	{
		try {
			$docNo = arr_get($data, 'BookingNumber');

			return autocount_request(
				'DELETE',
				'quotation.delete',
				[],
				['docNo' => $docNo]
			);

		} catch (Exception $e) {
			log_message('error', 'Autocount delete error: ' . $e->getMessage());

			return [
				'status' => 500,
				'error'  => $e->getMessage(),
				'data'   => [],
			];
		}
	}

	public function autocount_void($data)
	{
		try {
			$docNo = arr_get($data, 'BookingNumber');

			$body = [
				'voidReason' => arr_get($data, 'reason'),
			];

			return autocount_request(
				'POST',
				'quotation.void',
				$body,
				['docNo' => $docNo]
			);

		} catch (Exception $e) {
			log_message('error', 'Autocount void error: ' . $e->getMessage());

			return [
				'status' => 500,
				'error'  => $e->getMessage(),
				'data'   => [],
			];
		}
	}

	public function UpdateAllowReview()
	{
		// Check access control
		if (!in_array('AB', $this->session->access_control)) {
			$this->output
				->set_content_type('application/json')
				->set_output(json_encode([
					'success' => false,
					'message' => 'Access denied'
				]));
			return;
		}

		// Check if AJAX request
		if (!$this->input->is_ajax_request()) {
			$this->output
				->set_content_type('application/json')
				->set_output(json_encode([
					'success' => false,
					'message' => 'Invalid request'
				]));
			return;
		}

		try {
			$data = $this->input->post();
			$bookingId = arr_get($data, 'booking_id');
			$booking = $this->input->post('booking');
			$bookingLog = $this->input->post('booking_log');

			// Validate booking ID
			if (empty($bookingId)) {
				$this->output
					->set_content_type('application/json')
					->set_output(json_encode([
						'success' => false,
						'message' => 'Booking ID is required'
					]));
				return;
			}

			// Validate booking data
			if (empty($booking) || !is_array($booking) || !isset($booking[0])) {
				$this->output
					->set_content_type('application/json')
					->set_output(json_encode([
						'success' => false,
						'message' => 'Invalid booking data'
					]));
				return;
			}

			// Get AllowReview value and validate
			$allowReview = isset($booking[0]['AllowReview']) ? (int)$booking[0]['AllowReview'] : null;
			if ($allowReview === null || ($allowReview != 0 && $allowReview != 1)) {
				$this->output
					->set_content_type('application/json')
					->set_output(json_encode([
						'success' => false,
						'message' => 'Invalid AllowReview value'
					]));
				return;
			}

			// Verify booking exists
			$existingBooking = $this->Booking_Model->find($bookingId);
			if (empty($existingBooking)) {
				$this->output
					->set_content_type('application/json')
					->set_output(json_encode([
						'success' => false,
						'message' => 'Booking not found'
					]));
				return;
			}

			// Update AllowReview using update_batch
			$bookingData = [
				[
					'BookingID' => $bookingId,
					'AllowReview' => $allowReview,
					'UpdateBy' => $this->session->userdata('admin_id'),
					'UpdateDate' => date('Y-m-d H:i:s')
				]
			];

			// Update the booking
			$this->db->update_batch('booking', $bookingData, 'BookingID');

			// Create booking log if provided
			if (!empty($bookingLog) && is_array($bookingLog) && !empty($bookingLog[0])) {
				$this->db->insert_batch('booking_log', $bookingLog);
			}

			// Return success response
			$this->output
				->set_content_type('application/json')
				->set_output(json_encode([
					'success' => true,
					'message' => 'Allow Review updated successfully'
				]));

		} catch (Exception $e) {
			log_message('error', 'Update allow review error: ' . $e->getMessage());
			$this->output
				->set_content_type('application/json')
				->set_output(json_encode([
					'success' => false,
					'message' => 'An error occurred while updating Allow Review'
				]));
		}
	}

	/**
	 * Upload custom file for booking
	 */
	function Upload_Custom_File()
	{
		if (!in_array('AB', $this->session->access_control)) {
			$this->output
				->set_content_type('application/json')
				->set_output(json_encode([
					'success' => false,
					'message' => 'Access denied'
				]));
			return;
		}

		if (!$this->input->is_ajax_request()) {
			$this->output
				->set_content_type('application/json')
				->set_output(json_encode([
					'success' => false,
					'message' => 'Invalid request'
				]));
			return;
		}

		$booking_id = $this->input->post('booking_id');
		$upload_name = $this->input->post('upload_name');

		if (empty($booking_id) || empty($upload_name)) {
			$this->output
				->set_content_type('application/json')
				->set_output(json_encode([
					'success' => false,
					'message' => 'Booking ID and upload name are required'
				]));
			return;
		}

		// Validate booking exists
		$booking = $this->Booking_Model->find($booking_id);
		if (empty($booking)) {
			$this->output
				->set_content_type('application/json')
				->set_output(json_encode([
					'success' => false,
					'message' => 'Booking not found'
				]));
			return;
		}

		// Configure upload
		$config['upload_path'] = FCPATH . 'assets/upload/custom/';
		$config['allowed_types'] = 'pdf|jpg|jpeg|png|gif|doc|docx|xls|xlsx|txt';
		$config['max_size'] = 10240; // 10MB
		$config['encrypt_name'] = true;

		// Create upload directory if it doesn't exist
		if (!is_dir($config['upload_path'])) {
			mkdir($config['upload_path'], 0755, true);
		}

		$this->load->library('upload', $config);

		if (!$this->upload->do_upload('upload_file')) {
			$error = $this->upload->display_errors('', '');
			$this->output
				->set_content_type('application/json')
				->set_output(json_encode([
					'success' => false,
					'message' => 'Upload failed: ' . $error
				]));
			return;
		}

		$upload_data = $this->upload->data();
		$file_path = 'assets/upload/custom/' . $upload_data['file_name'];

		// Save to database
		$this->load->model('Custom_Upload_Model');
		$upload_id = $this->Custom_Upload_Model->Create(
			$booking_id,
			$upload_name,
			$file_path,
			$this->session->userdata('admin_id')
		);

		// Get admin name
		$this->db->select('Name');
		$this->db->where('AdminID', $this->session->userdata('admin_id'));
		$admin = $this->db->get('admin')->row_array();
		
		// Load utils helper for date formatting
		$this->load->helper('utils');
		$upload_record = $this->Custom_Upload_Model->Get_By_Id($upload_id);
		$created_at_formatted = return_timestamp_output($upload_record['created_at']);

		$this->output
			->set_content_type('application/json')
			->set_output(json_encode([
				'success' => true,
				'message' => 'File uploaded successfully',
				'upload' => [
					'id' => $upload_id,
					'upload_name' => $upload_name,
					'upload_content' => base_url($file_path),
					'created_by' => $admin['Name'],
					'created_at' => $created_at_formatted
				]
			]));
	}

	/**
	 * Upload an inline image from the booking voucher TinyMCE editors.
	 *
	 * TinyMCE 5 posts the file as multipart field `file` to images_upload_url
	 * and expects a JSON body of {"location": "<url>"} on success or
	 * {"error": {"message": "...", "remove": true}} on failure.
	 */
	function Upload_Voucher_Image()
	{
		$this->output->set_content_type('application/json');

		if (!in_array('AB', $this->session->access_control)) {
			$this->output
				->set_status_header(403)
				->set_output(json_encode([
					'error' => ['message' => 'Access denied', 'remove' => true]
				]));
			return;
		}

		if (!$this->input->is_ajax_request()) {
			$this->output
				->set_status_header(400)
				->set_output(json_encode([
					'error' => ['message' => 'Invalid request', 'remove' => true]
				]));
			return;
		}

		$this->load->helper('voucher_image');

		$file_meta = isset($_FILES['file']) ? $_FILES['file'] : [];
		$max_kb = 5120;
		$validation = validate_voucher_image_upload($file_meta, $max_kb);
		if (!$validation['ok']) {
			$this->output
				->set_status_header(400)
				->set_output(json_encode([
					'error' => ['message' => $validation['error'], 'remove' => true]
				]));
			return;
		}

		$config['upload_path']   = FCPATH . 'assets/upload/voucher/';
		$config['allowed_types'] = 'jpg|jpeg|png|gif|webp';
		$config['max_size']      = $max_kb;
		$config['encrypt_name']  = true;

		if (!is_dir($config['upload_path'])) {
			mkdir($config['upload_path'], 0755, true);
		}

		$this->load->library('upload', $config);

		if (!$this->upload->do_upload('file')) {
			$error = $this->upload->display_errors('', '');
			$this->output
				->set_status_header(400)
				->set_output(json_encode([
					'error' => ['message' => 'Upload failed: ' . trim(strip_tags($error)), 'remove' => true]
				]));
			return;
		}

		$upload_data = $this->upload->data();
		$full_path = $upload_data['full_path'];

		// Defence-in-depth: reject anything getimagesize() can't recognise as a raster.
		$image_info = @getimagesize($full_path);
		if ($image_info === false || empty($image_info[0]) || empty($image_info[1])) {
			@unlink($full_path);
			$this->output
				->set_status_header(400)
				->set_output(json_encode([
					'error' => ['message' => 'Uploaded file is not a valid image', 'remove' => true]
				]));
			return;
		}

		$file_path = 'assets/upload/voucher/' . $upload_data['file_name'];
		$this->output->set_output(json_encode([
			'location' => base_url($file_path),
		]));
	}

	/**
	 * Delete custom upload
	 */
	function Delete_Custom_Upload()
	{
		if (!in_array('AB', $this->session->access_control)) {
			$this->output
				->set_content_type('application/json')
				->set_output(json_encode([
					'success' => false,
					'message' => 'Access denied'
				]));
			return;
		}

		if (!$this->input->is_ajax_request()) {
			$this->output
				->set_content_type('application/json')
				->set_output(json_encode([
					'success' => false,
					'message' => 'Invalid request'
				]));
			return;
		}

		$upload_id = $this->input->post('upload_id');
		if (empty($upload_id)) {
			$this->output
				->set_content_type('application/json')
				->set_output(json_encode([
					'success' => false,
					'message' => 'Upload ID is required'
				]));
			return;
		}

		$this->load->model('Custom_Upload_Model');
		$upload = $this->Custom_Upload_Model->Get_By_Id($upload_id);

		if (empty($upload)) {
			$this->output
				->set_content_type('application/json')
				->set_output(json_encode([
					'success' => false,
					'message' => 'Upload not found'
				]));
			return;
		}

		// Delete file
		$file_path = FCPATH . $upload['upload_content'];
		if (file_exists($file_path) && is_file($file_path)) {
			@unlink($file_path);
		}

		// Delete from database
		$this->Custom_Upload_Model->Delete($upload_id);

		$this->output
			->set_content_type('application/json')
			->set_output(json_encode([
				'success' => true,
				'message' => 'Upload deleted successfully'
			]));
	}

    /**
     * AJAX endpoint to get remarks for a booking
     */
    function Get_Remarks()
    {
        if (!in_array('AB', $this->session->access_control)) {
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'success' => false,
                    'message' => 'Access denied'
                ]));
            return;
        }

        $booking_id = $this->input->get('booking_id');
        if (empty($booking_id)) {
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'success' => false,
                    'message' => 'Booking ID is required'
                ]));
            return;
        }

        // Get internal remarks (type 1) only
        $remarks = $this->Remark_Model->Read_Remarks('booking', $booking_id, REMARK_TYPE::INTERNAL);

		// Format remarks for JSON response
		$formatted_remarks = array();
		foreach ($remarks as $remark) {
			// Get initials for avatar
			$initials = '';
			if (!empty($remark->CommenterName)) {
				$name_parts = explode(' ', $remark->CommenterName);
				if (count($name_parts) >= 2) {
					$initials = strtoupper(substr($name_parts[0], 0, 1) . substr($name_parts[count($name_parts) - 1], 0, 1));
				} else {
					$initials = strtoupper(substr($remark->CommenterName, 0, 2));
				}
			}

			$current_user_id = $this->session->userdata('admin_id');

			// Get remark type label from REMARK_TYPE class
			$remark_type = isset($remark->type) ? $remark->type : REMARK_TYPE::INTERNAL;
			$remark_type_label = REMARK_TYPE::getLabel($remark_type) ?: 'INTERNAL';

			$formatted_remarks[] = array(
				'RemarkID' => $remark->RemarkID,
				'content' => $remark->content,
				'commenter_name' => $remark->CommenterName,
				'commenter_id' => $remark->commenter_id,
				'is_owner' => ($remark->commenter_id == $current_user_id),
				'commenter_initials' => $initials,
				'type' => isset($remark->type) ? $remark->type : 1,
				'type_label' => $remark_type_label,
				'created_at' => return_timestamp_output($remark->created_at),
				'created_at_raw' => $remark->created_at,
			);
		}

		$this->output
			->set_content_type('application/json')
			->set_output(json_encode([
				'success' => true,
				'remarks' => $formatted_remarks
			]));
	}

    /**
     * AJAX endpoint to get customer remarks for a booking
     */
    function Get_Customer_Remarks()
    {
        // Allow AB access control OR Sales Agent (level 20) for their own bookings
        $is_sales_agent = $this->session->userdata('level') == 20;
        $has_ab_access = in_array('AB', $this->session->access_control);
        
        if (!$has_ab_access && !$is_sales_agent) {
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'success' => false,
                    'message' => 'Access denied'
                ]));
            return;
        }

        $booking_id = $this->input->get('booking_id');
        if (empty($booking_id)) {
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'success' => false,
                    'message' => 'Booking ID is required'
                ]));
            return;
        }

        // If sales agent without AB access, verify they own the booking
        if($is_sales_agent && !$has_ab_access) {
            $booking = $this->Booking_Model->getBookingById($booking_id);
            if(!$booking || $booking->SalesAgent != $this->session->userdata('admin_id')) {
                $this->output
                    ->set_content_type('application/json')
                    ->set_output(json_encode([
                        'success' => false,
                        'message' => 'You can only view remarks for bookings assigned to you.'
                    ]));
                return;
            }
        }

        // Get customer remarks (type 2) only
        $remarks = $this->Remark_Model->Read_Remarks('booking', $booking_id, REMARK_TYPE::CUSTOMER);
        
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
                $this->db->where('BookingID', $booking_id);
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
                'commenter_id' => $remark->commenter_id,
                'commenter_initials' => $initials,
                'created_at' => return_timestamp_output($remark->created_at),
                'created_at_raw' => $remark->created_at
            );
        }

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'success' => true,
                'remarks' => $formatted_remarks
            ]));
    }

    /**
     * AJAX endpoint to add a new remark
     */
    function Add_Remark()
	{
		// Allow AB access control OR Sales Agent (level 20) for their own bookings
		$is_sales_agent = $this->session->userdata('level') == 20;
		$has_ab_access = in_array('AB', $this->session->access_control);
		
		if (!$has_ab_access && !$is_sales_agent) {
			$this->output
				->set_content_type('application/json')
				->set_output(json_encode([
					'success' => false,
					'message' => 'Access denied'
				]));
			return;
		}

		$booking_id = $this->input->post('booking_id');
		$content = trim($this->input->post('content'));

		if (empty($booking_id)) {
			$this->output
				->set_content_type('application/json')
				->set_output(json_encode([
					'success' => false,
					'message' => 'Booking ID is required'
				]));
			return;
		}

		if (empty($content)) {
			$this->output
				->set_content_type('application/json')
				->set_output(json_encode([
					'success' => false,
					'message' => 'Comment content is required'
				]));
			return;
		}

		// Verify booking exists
		$booking = $this->Booking_Model->find($booking_id);
		if (empty($booking)) {
			$this->output
				->set_content_type('application/json')
				->set_output(json_encode([
					'success' => false,
					'message' => 'Booking not found'
				]));
			return;
		}

		// If sales agent without AB access, verify they own the booking
		if($is_sales_agent && !$has_ab_access) {
			if($booking->SalesAgent != $this->session->userdata('admin_id')) {
				$this->output
					->set_content_type('application/json')
					->set_output(json_encode([
						'success' => false,
						'message' => 'You can only add remarks to bookings assigned to you.'
					]));
				return;
			}
		}

		// Check if this is from Customer Remarks section (type 2) or Internal Comments (type 1)
		$remark_type = $this->input->post('remark_type') == '2' ? REMARK_TYPE::CUSTOMER : REMARK_TYPE::INTERNAL;

		$remark_data = array(
			'owner_type' => 'booking',
			'owner_id' => $booking_id,
			'commenter_id' => $this->session->userdata('admin_id'),
			'content' => $content,
			'type' => $remark_type,
		);

		$remark_id = $this->Remark_Model->Create($remark_data);

		if ($remark_id) {
			// Internal remarks fan out to two notification paths: explicit
			// @mentions parsed from the content, plus auto-notify of the
			// booking's SalesAgent + BookingOP. Both paths are idempotent on
			// (user_id, remark_id), so a user that is both @mentioned and the
			// SA receives exactly one row.
			if ($remark_type == REMARK_TYPE::INTERNAL) {
				$commenter_id = $this->session->userdata('admin_id');

				if (preg_match_all('/@([a-z0-9]+)/', $content, $m) && !empty($m[1])) {
					$tag_user_ids = $this->Notification_Model->Resolve_Handles_To_User_Ids($m[1]);
					if (!empty($tag_user_ids)) {
						$this->Notification_Model->Create_Remark_Notifications_For_Users(
							$booking_id, $remark_id, $commenter_id, $content, $tag_user_ids
						);
					}
				}

				$this->Notification_Model->Create_Remark_Notifications(
					$booking_id, $remark_id, $commenter_id, $content
				);
			}

			// Get the newly created remark with commenter name
			$remark = $this->Remark_Model->Read_Remark($remark_id);
			
			// Get remark type label from REMARK_TYPE class
			$remark_type = isset($remark->type) ? $remark->type : REMARK_TYPE::INTERNAL;
			$remark_type_label = REMARK_TYPE::getLabel($remark_type) ?: 'INTERNAL';
			
			$this->output
				->set_content_type('application/json')
				->set_output(json_encode([
					'success' => true,
					'message' => 'Comment added successfully',
					'remark' => array(
						'RemarkID' => $remark->RemarkID,
						'content' => $remark->content,
						'commenter_name' => $remark->CommenterName,
						'type' => $remark_type,
						'type_label' => $remark_type_label,
						'created_at' => date('d/m/Y H:i:s', strtotime($remark->created_at)),
						'created_at_raw' => $remark->created_at
					)
				]));
		} else {
			$this->output
				->set_content_type('application/json')
				->set_output(json_encode([
					'success' => false,
					'message' => 'Failed to add comment'
				]));
		}
	}

	/**
	 * AJAX endpoint to delete a remark
	 */
	function Delete_Remark()
	{
		if (!in_array('AB', $this->session->access_control)) {
			$this->output
				->set_content_type('application/json')
				->set_output(json_encode([
					'success' => false,
					'message' => 'Access denied'
				]));
			return;
		}

		$remark_id = $this->input->post('remark_id');

		if (empty($remark_id)) {
			$this->output
				->set_content_type('application/json')
				->set_output(json_encode([
					'success' => false,
					'message' => 'Remark ID is required'
				]));
			return;
		}

		// Verify remark exists
		$remark = $this->Remark_Model->Read_Remark($remark_id);
		if (empty($remark)) {
			$this->output
				->set_content_type('application/json')
				->set_output(json_encode([
					'success' => false,
					'message' => 'Remark not found'
				]));
			return;
		}

		// Check if current user is the owner of the remark
		$current_user_id = $this->session->userdata('admin_id');
		if ($remark->commenter_id != $current_user_id) {
			$this->output
				->set_content_type('application/json')
				->set_output(json_encode([
					'success' => false,
					'message' => 'You can only delete your own comments'
				]));
			return;
		}

		// Delete the remark
		$deleted = $this->Remark_Model->Delete($remark_id);

		if ($deleted) {
			$this->output
				->set_content_type('application/json')
				->set_output(json_encode([
					'success' => true,
					'message' => 'Comment deleted Successfully'
				]));
		} else {
			$this->output
				->set_content_type('application/json')
				->set_output(json_encode([
					'success' => false,
					'message' => 'Failed to delete comment'
				]));
		}
	}

	/**
	 * Helper function to get relative time (e.g., "2 hours ago")
	 */
	private function time_ago($datetime)
	{
		$timestamp = strtotime($datetime);
		$diff = time() - $timestamp;
		
		if ($diff < 60) {
			return 'Just now';
		} elseif ($diff < 3600) {
			$mins = floor($diff / 60);
			return $mins . ' min' . ($mins > 1 ? 's' : '') . ' ago';
		} elseif ($diff < 86400) {
			$hours = floor($diff / 3600);
			return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
		} elseif ($diff < 604800) {
			$days = floor($diff / 86400);
			return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
		} else {
			return date('M j, Y', $timestamp);
		}
	}

	/**
	 * AJAX endpoint to get checklist data for a booking (used by listing page modal)
	 */
	public function Get_Checklist($booking_id = null)
	{
		if(empty($booking_id)) {
			echo json_encode(array('success' => false, 'message' => 'Invalid booking ID'));
			return;
		}

		// Check access
		$access_control = $this->session->access_control ?? array();
		$is_sales_agent = $this->session->userdata('level') == 20;
		$is_team_lead = (int)$this->session->userdata('level') === 25;
		$is_op_team_lead = (int)$this->session->userdata('level') === 45;
		if(!in_array('AB', $access_control) && !$is_sales_agent && !$is_team_lead && !$is_op_team_lead) {
			echo json_encode(array('success' => false, 'message' => 'Access denied'));
			return;
		}

		// Get booking info
		$booking = $this->Booking_Model->getBookingById($booking_id);
		if(!$booking) {
			echo json_encode(array('success' => false, 'message' => 'Booking not found'));
			return;
		}

		// Sales agents can only access their own bookings
		if($is_sales_agent && !in_array('AB', $access_control)) {
			if(empty($booking->SalesAgentID) || $booking->SalesAgentID != $this->session->userdata('admin_id')) {
				echo json_encode(array('success' => false, 'message' => 'Access denied'));
				return;
			}
		}

		// Strict whitelist: only TC1 (booking SalesAgent), OP (BookingOP), the
		// SalesAgent's sales Team Lead, and the BookingOP's OP TEAM LEAD may
		// mutate this booking's checklist. The modal can still load read-only
		// for everyone else with AB access.
		$this->load->helper('booking_flow');
		$tl_ids = resolve_booking_checklist_team_leads($booking);
		$can_modify = can_user_modify_booking_checklist(
			$booking,
			$this->session->userdata('admin_id'),
			$this->session->userdata('level'),
			$tl_ids['tc1_tl'],
			$tl_ids['op_tl']
		);

		// Get booking products (need ProductID and Name for checklist grouping)
		$this->db->select('bp.BookingProductID, bp.ProductID, p.Name, bp.PaymentOutSupplierFull, bp.PaymentOutSupplierDeposit, bp.disable_checklist_payment_out');
		$this->db->from('booking_product bp');
		$this->db->join('product p', 'p.ProductID = bp.ProductID', 'left');
		$this->db->where('bp.BookingID', $booking_id);
		$this->db->where('bp.Status', 'Y');
		$booking_products = $this->db->get()->result();

		if(empty($booking_products)) {
			echo json_encode(array('success' => false, 'message' => 'No products found for this booking'));
			return;
		}

		// Get checklists and completion map
		$booking_checklists = $this->get_booking_checklists($booking_products);
		$completion_map = $this->Booking_Checklist_Completion_Model->Read_Completion_Map($booking_id);

		// Format completion map for JSON (convert nested associative array)
		$formatted_completion = array();
		foreach($completion_map as $product_id => $checklists) {
			$formatted_completion[$product_id] = array();
			foreach($checklists as $checklist_id => $info) {
				$formatted_completion[$product_id][$checklist_id] = array(
					'created_by_name' => $info['created_by_name'],
					'created_at' => date('d/m/Y h:i A', strtotime($info['created_at']))
				);
			}
		}

		// Serialize checklist groups for JSON
		$groups = array();
		foreach($booking_checklists['groups'] as $group) {
			$checklists = array();
			foreach($group['checklists'] as $cl) {
				$checklists[] = array('ID' => $cl->ID, 'name' => $cl->name);
			}
			$groups[] = array(
				'product_name' => $group['product_name'],
				'product_id' => $group['product_id'],
				'checklists' => $checklists,
				'PaymentOutSupplierFull' => !empty($group['PaymentOutSupplierFull']) ? date('d/m/Y', strtotime($group['PaymentOutSupplierFull'])) : null,
				'PaymentOutSupplierDeposit' => !empty($group['PaymentOutSupplierDeposit']) ? date('d/m/Y', strtotime($group['PaymentOutSupplierDeposit'])) : null
			);
		}

		echo json_encode(array(
			'success' => true,
			'booking_number' => $booking->BookingNumber,
			'is_multi_product' => $booking_checklists['is_multi_product'],
			'groups' => $groups,
			'total_count' => $booking_checklists['total_count'],
			'completion_map' => $formatted_completion,
			'can_modify' => $can_modify
		));
	}

	/**
	 * Get all checklists for booking products
	 * If product doesn't have checklists, create default (required) ones
	 */
	private function get_booking_checklists($booking_products)
	{
		// Get all package checklists
		$package_checklists = $this->Package_Checklist_Model->Read_Package_Checklists();
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

		// Get deposit checklist ID
		$deposit_checklist_id = null;
		foreach($package_checklists as $pc) {
			if(strpos($pc->name, 'Payment Out To Supplier (deposit)') !== false) {
				$deposit_checklist_id = $pc->ID;
				break;
			}
		}

		// Group booking products by ProductID (collapse duplicates)
		$product_groups = array();
		foreach($booking_products as $booking_product) {
			$product_id = $booking_product->ProductID;
			if(!isset($product_groups[$product_id])) {
				$product_groups[$product_id] = $booking_product;
			}
		}

		// Build groups with checklists
		$groups = array();
		$total_count = 0;

		foreach($product_groups as $product_id => $booking_product) {
			// Skip products with disable_checklist_payment_out enabled
			if(isset($booking_product->disable_checklist_payment_out) && $booking_product->disable_checklist_payment_out == 1) {
				continue;
			}

			// Skip child/infant products — they don't require checklists
			$product_row = $this->db->select('is_child_or_infant, has_supplier_deposit')->where('ProductID', $product_id)->get('product')->row();
			if($product_row && $product_row->is_child_or_infant == 1) {
				continue;
			}

			// Get checklists for this product
			$product_checklist_ids = $this->Product_Package_Checklist_Model->Get_Checklists_For_Product($product_id);

			// If product doesn't have checklists, use required ones and create entry
			if(empty($product_checklist_ids)) {
				$product_checklist_ids = $required_ids;
				$this->Product_Package_Checklist_Model->Bulk_Update_Product_Checklists($product_id, $required_ids);
			}

			// Auto-add deposit checklist if booking product has a PaymentOutSupplierDeposit date
			$has_deposit_date = !empty($booking_product->PaymentOutSupplierDeposit)
				&& $booking_product->PaymentOutSupplierDeposit != '0000-00-00';
			if($deposit_checklist_id && $has_deposit_date) {
				if(!in_array($deposit_checklist_id, $product_checklist_ids)) {
					$product_checklist_ids[] = $deposit_checklist_id;
				}
			}

			// Build checklist list for this group
			$group_checklists = array();
			foreach($product_checklist_ids as $checklist_id) {
				if(isset($checklist_map[$checklist_id])) {
					$group_checklists[] = $checklist_map[$checklist_id];
					$total_count++;
				}
			}

			if(!empty($group_checklists)) {
				$groups[] = array(
					'product_name' => isset($booking_product->Name) ? $booking_product->Name : 'Product #' . $product_id,
					'product_id' => $product_id,
					'checklists' => $group_checklists,
					'PaymentOutSupplierFull' => isset($booking_product->PaymentOutSupplierFull) ? $booking_product->PaymentOutSupplierFull : null,
					'PaymentOutSupplierDeposit' => isset($booking_product->PaymentOutSupplierDeposit) ? $booking_product->PaymentOutSupplierDeposit : null
				);
			}
		}

		return array(
			'is_multi_product' => count($product_groups) > 1,
			'groups' => $groups,
			'total_count' => $total_count
		);
	}

	/**
	 * Log checklist completion changes to booking_log
	 */
	private function log_checklist_changes($booking_id, $previous_completions, $new_completions, $created_by)
	{
		// Both arrays contain "productId_checklistId" strings
		// Extract unique checklist IDs for name lookup
		$all_checklist_ids = array();
		foreach(array_merge($previous_completions, $new_completions) as $key) {
			$parts = explode('_', $key, 2);
			if(count($parts) == 2) {
				$all_checklist_ids[] = intval($parts[1]);
			}
		}
		$all_checklist_ids = array_unique($all_checklist_ids);

		$checklist_name_map = array();
		if(!empty($all_checklist_ids)) {
			$this->db->select('ID, name');
			$this->db->where_in('ID', $all_checklist_ids);
			$checklists = $this->db->get('package_checklist')->result();
			foreach($checklists as $checklist) {
				$checklist_name_map[$checklist->ID] = $checklist->name;
			}
		}

		$booking_logs = array();

		// Find items that were added (ticked)
		$added = array_diff($new_completions, $previous_completions);
		foreach($added as $key) {
			$parts = explode('_', $key, 2);
			$checklist_id = isset($parts[1]) ? intval($parts[1]) : $key;
			$checklist_name = isset($checklist_name_map[$checklist_id]) ? $checklist_name_map[$checklist_id] : 'Checklist ID: ' . $checklist_id;
			$booking_logs[] = array(
				'BookingID' => $booking_id,
				'Column' => 'BookingChecklist',
				'CurrentData' => 'Unchecked',
				'NewData' => 'Checked: ' . $checklist_name,
				'InsertBy' => $created_by,
				'InsertDate' => date('Y-m-d H:i:s')
			);
		}

		// Find items that were removed (unticked)
		$removed = array_diff($previous_completions, $new_completions);
		foreach($removed as $key) {
			$parts = explode('_', $key, 2);
			$checklist_id = isset($parts[1]) ? intval($parts[1]) : $key;
			$checklist_name = isset($checklist_name_map[$checklist_id]) ? $checklist_name_map[$checklist_id] : 'Checklist ID: ' . $checklist_id;
			$booking_logs[] = array(
				'BookingID' => $booking_id,
				'Column' => 'BookingChecklist',
				'CurrentData' => 'Checked: ' . $checklist_name,
				'NewData' => 'Unchecked',
				'InsertBy' => $created_by,
				'InsertDate' => date('Y-m-d H:i:s')
			);
		}

		// Insert logs if there are any changes
		if(!empty($booking_logs)) {
			$this->db->insert_batch('booking_log', $booking_logs);
			log_message('debug', 'Booking Checklist Logs: ' . count($booking_logs) . ' entries inserted for BookingID: ' . $booking_id);
		}
	}

	/**
	 * Shared checklist-write path used by both the full Booking/Update AJAX
	 * handler (AB users editing the form) and the dedicated Save_Checklist
	 * endpoint (Team Leads without AB ticking via the list-page modal).
	 *
	 * Returns ['allowed' => bool, 'success' => bool, 'message' => string].
	 */
	private function _persist_checklist_completions($booking_id, $checklist_completions_raw, $admin_id)
	{
		$result = array('allowed' => false, 'success' => false, 'message' => '');

		if (empty($booking_id) || $checklist_completions_raw === null) {
			$result['message'] = 'Missing booking_id or checklist payload';
			return $result;
		}
		if (empty($admin_id)) {
			$result['message'] = 'No admin session';
			return $result;
		}

		// Normalise to array
		if (!is_array($checklist_completions_raw)) {
			$checklist_completions_raw = !empty($checklist_completions_raw)
				? array($checklist_completions_raw)
				: array();
		}

		// Parse "productId_checklistId" pairs
		$completion_pairs = array();
		foreach ($checklist_completions_raw as $value) {
			$parts = explode('_', $value, 2);
			if (count($parts) == 2) {
				$completion_pairs[] = array(intval($parts[0]), intval($parts[1]));
			}
		}

		if (!$this->db->table_exists('booking_checklist_completion')) {
			log_message('error', 'booking_checklist_completion table does not exist. Please run migration.');
			$result['message'] = 'Checklist storage not provisioned';
			return $result;
		}

		// Snapshot previous completions for diff logging
		$previous_map = $this->Booking_Checklist_Completion_Model->Read_Completion_Map($booking_id);
		$previous_keys = array();
		foreach ($previous_map as $pid => $checklists) {
			foreach ($checklists as $cid => $info) {
				$previous_keys[] = $pid . '_' . $cid;
			}
		}

		$this->load->helper('booking_flow');
		$booking = $this->Booking_Model->getBookingById($booking_id);

		$tl_ids = $booking
			? resolve_booking_checklist_team_leads($booking)
			: array('tc1_tl' => null, 'op_tl' => null);
		$allowed = $booking && can_user_modify_booking_checklist(
			$booking,
			$admin_id,
			$this->session->userdata('level'),
			$tl_ids['tc1_tl'],
			$tl_ids['op_tl']
		);

		if (!$allowed) {
			if ($booking) {
				log_message('info', 'Checklist save blocked: admin_id=' . $admin_id . ' is not TC1/OP/Team Lead for booking ' . $booking_id);
			}
			$result['message'] = 'Access denied';
			return $result;
		}

		$result['allowed'] = true;

		$was_all_completed = are_all_checklists_completed($booking_id, $this);
		$this->Booking_Checklist_Completion_Model->Create($booking_id, $completion_pairs, $admin_id);
		$is_now_all_completed = are_all_checklists_completed($booking_id, $this);

		if ($was_all_completed && !$is_now_all_completed) {
			$this->load->helper('booking_status_log');
			if ($booking->Status != 'PBO' && in_array($booking->Status, ['PTV', 'PT'])) {
				$this->Booking_Model->Update_Status('PBO', $booking_id);
				log_booking_status_change(
					$booking_id,
					'PBO',
					$booking->Status,
					$admin_id,
					'Status reverted to PENDING BOOKING OPERATION - Checklist unchecked',
					true
				);
			}
		} else if ($booking->Status == 'PBO' && $is_now_all_completed) {
			$status_info = determine_booking_status_from_state($booking_id, $booking, $this);
			if ($status_info['status'] != 'PBO') {
				$this->load->helper('booking_status_log');
				$this->Booking_Model->Update_Status($status_info['status'], $booking_id);
				log_booking_status_change(
					$booking_id,
					$status_info['status'],
					'PBO',
					$admin_id,
					'All Booking Checklists Completed - Status changed to ' . $status_info['status'],
					true
				);
			}
		}

		$new_keys = array();
		foreach ($completion_pairs as $pair) {
			$new_keys[] = $pair[0] . '_' . $pair[1];
		}
		$this->log_checklist_changes($booking_id, $previous_keys, $new_keys, $admin_id);

		$result['success'] = true;
		return $result;
	}

	/**
	 * AJAX endpoint to save checklist completions from the list-page modal.
	 * Intentionally decoupled from the AB-gated Booking/Update handler so that
	 * level-25 Team Leads (who typically lack AB) can still tick their team's
	 * bookings. Authorisation is delegated to can_user_modify_booking_checklist()
	 * inside _persist_checklist_completions().
	 */
	public function Save_Checklist()
	{
		header('Content-Type: application/json');

		$access_control = $this->session->access_control ?? array();
		if (!in_array('VB', $access_control)) {
			echo json_encode(array('success' => false, 'message' => 'Access denied'));
			return;
		}

		$booking_id = $this->input->post('booking_id');
		$raw_completions = $this->input->post('checklist_completions');
		$admin_id = $this->session->userdata('admin_id');

		$result = $this->_persist_checklist_completions($booking_id, $raw_completions, $admin_id);

		if (!$result['allowed']) {
			echo json_encode(array('success' => false, 'message' => $result['message'] ?: 'Access denied'));
			return;
		}
		if (!$result['success']) {
			echo json_encode(array('success' => false, 'message' => $result['message'] ?: 'Failed to save checklist'));
			return;
		}
		echo json_encode(array('success' => true));
	}

	// TEMP one-shot sweep: recompute is_submitted for bookings where the flag
	// may have gone stale (room edits used to skip recalc). Remove after running.
	// Pass ?dry_run=1 to preview affected BookingIDs without writing.
	function Recompute_All_Is_Submitted()
	{
		if(!$this->session->userdata('admin_id') || empty($this->session->access_control) || !in_array('VB', $this->session->access_control)) {
			show_error('Unauthorized', 403);
			return;
		}

		$this->load->model('Guest_List_Model');
		$dry_run = !empty($this->input->get('dry_run'));

		$this->db->select('BookingID');
		$this->db->where('is_submitted', 1);
		$this->db->where('LockStatus', 'N');
		$candidates = $this->db->get('booking')->result();

		$affected = array();
		foreach($candidates as $row) {
			if(!$this->Guest_List_Model->Are_All_Guests_Complete($row->BookingID)) {
				if(!$dry_run) {
					$this->db->where('BookingID', $row->BookingID);
					$this->db->update('booking', array('is_submitted' => 0));
				}
				$affected[] = $row->BookingID;
			}
		}

		$verb = $dry_run ? 'would correct' : 'corrected';
		log_message('info', 'Recompute_All_Is_Submitted (' . ($dry_run ? 'dry-run' : 'apply') . '): scanned ' . count($candidates) . ', ' . $verb . ' ' . count($affected) . ' -> ' . implode(',', $affected));
		echo json_encode(array(
			'success' => true,
			'dry_run' => $dry_run,
			'scanned' => count($candidates),
			'affected_count' => count($affected),
			'affected_booking_ids' => $affected
		));
	}

}
