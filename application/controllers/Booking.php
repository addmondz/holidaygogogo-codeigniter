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
						// Only reset if not already in payment-related status
						if(!in_array($booking->Status, ['PBC', 'P', 'PBO', 'PTV', 'PT', 'Y', 'OG'])) {
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
				$booking->SalesAgent2
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
			// Edit Checklist - AB users or SA viewing own booking
			if(in_array('AB', $access_control) || ($is_sales_agent && !empty($booking->SalesAgentID) && $booking->SalesAgentID == $this->session->userdata('admin_id'))) {
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

	function Create()
	{
		if(in_array('GB', $this->session->access_control)) {
			if ($this->input->is_ajax_request()) {

				$booking_id = $this->Booking_Model->Create();

				$this->Booking_Product_Model->Create($this->input->post('booking_products'), $booking_id);

				// Recompute Subtotal from booking_product totals to keep booking.Subtotal authoritative
				$this->Booking_Product_Model->Recompute_Subtotal($booking_id);

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
				
				// Booking Checklist Completion
				$booking_id = $this->input->post('booking_id');
				$checklist_completions = $this->input->post('checklist_completions');

				// Always process checklist completions if booking_id and checklist_completions are provided
				if(!empty($booking_id) && $checklist_completions !== null) {
					// Ensure it's an array
					if(!is_array($checklist_completions)) {
						if(!empty($checklist_completions)) {
							$checklist_completions = array($checklist_completions);
						} else {
							$checklist_completions = array();
						}
					}

					// Parse "productId_checklistId" pairs
					$completion_pairs = array(); // [[product_id, checklist_id], ...]
					foreach($checklist_completions as $value) {
						$parts = explode('_', $value, 2);
						if(count($parts) == 2) {
							$completion_pairs[] = array(intval($parts[0]), intval($parts[1]));
						}
					}

					$created_by = $this->session->userdata('admin_id');

					if(!empty($created_by)) {
						// Get previous completions for logging (before updating)
						$previous_keys = array();
						$checklist_allowed = false;
						if($this->db->table_exists('booking_checklist_completion')) {
							// Get previous completions (nested map: product_id => checklist_id => info)
							$previous_map = $this->Booking_Checklist_Completion_Model->Read_Completion_Map($booking_id);
							// Flatten to "productId_checklistId" strings for comparison
							foreach($previous_map as $pid => $checklists) {
								foreach($checklists as $cid => $info) {
									$previous_keys[] = $pid . '_' . $cid;
								}
							}

							// Get booking to check current status
							$this->load->helper('booking_flow');
							$booking = $this->Booking_Model->getBookingById($booking_id);

							// Same scoping as the booking-update notification bell
							// (Notification_Model::_apply_visibility_filter): only TC1
							// (level 20 = SalesAgent) and OP (level 40 = BookingOP) for
							// THIS booking may tick its checklist. Other levels bypass.
							$checklist_allowed = $booking && can_user_modify_booking_checklist(
								$booking,
								$created_by,
								$this->session->userdata('level')
							);
							if($booking && !$checklist_allowed) {
								log_message('info', 'Checklist save blocked: admin_id=' . $created_by . ' is not TC1/OP for booking ' . $booking_id);
							}

							if($checklist_allowed) {
								// Check if checklist was unchecked (going from all completed to not all completed)
								$was_all_completed = are_all_checklists_completed($booking_id, $this);

								// Update checklist completions with product-aware pairs
								$this->Booking_Checklist_Completion_Model->Create($booking_id, $completion_pairs, $created_by);

								// Check if now all completed
								$is_now_all_completed = are_all_checklists_completed($booking_id, $this);

								// If was all completed but now not all completed, revert to PBO
								if ($was_all_completed && !$is_now_all_completed) {
									$this->load->helper('booking_status_log');

									// Only revert if status is beyond PBO
									if ($booking->Status != 'PBO' && in_array($booking->Status, ['PTV', 'PT'])) {
										$this->Booking_Model->Update_Status('PBO', $booking_id);
										log_booking_status_change(
											$booking_id,
											'PBO',
											$booking->Status,
											$created_by,
											'Status reverted to PENDING BOOKING OPERATION - Checklist unchecked',
											true
										);
									}
								} else if ($booking->Status == 'PBO' && $is_now_all_completed) {
									// All checklists are now completed, advance status
									$status_info = determine_booking_status_from_state($booking_id, $booking, $this);
									if ($status_info['status'] != 'PBO') {
										$this->load->helper('booking_status_log');
										$this->Booking_Model->Update_Status($status_info['status'], $booking_id);
										log_booking_status_change(
											$booking_id,
											$status_info['status'],
											'PBO',
											$created_by,
											'All Booking Checklists Completed - Status changed to ' . $status_info['status'],
											true
										);
									}
								}
							}
						} else {
							log_message('error', 'booking_checklist_completion table does not exist. Please run migration.');
						}

						// Create activity logs for changes — only when the user was
						// actually permitted to mutate the checklist.
						if (!empty($checklist_allowed)) {
							$new_keys = array();
							foreach($completion_pairs as $pair) {
								$new_keys[] = $pair[0] . '_' . $pair[1];
							}
							$this->log_checklist_changes($booking_id, $previous_keys, $new_keys, $created_by);
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
					$array['can_modify_checklist'] = can_user_modify_booking_checklist(
						$array,
						$this->session->userdata('admin_id'),
						$this->session->userdata('level')
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

					// Get booking checklists
					$array['booking_checklists'] = $this->get_booking_checklists($array['booking_products']);
					$array['completion_map'] = $this->Booking_Checklist_Completion_Model->Read_Completion_Map($array['BookingID']);

					// Get invoice split data
					$this->load->model('Invoice_Split_Model');
					$array['invoice_split'] = $this->Invoice_Split_Model->Get_Pax_By_Booking($array['BookingID']);

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

		// Auto-recipients (SalesAgent / BookingOP) aren't "extra tagged" users — exclude them
		// from the notified-users display so only explicit "Also Notify" picks show up.
		$booking_row = $this->Booking_Model->getBookingById($booking_id);
		$exclude_user_ids = array();
		if (!empty($booking_row)) {
			if (!empty($booking_row->SalesAgent)) $exclude_user_ids[] = $booking_row->SalesAgent;
			if (!empty($booking_row->BookingOP))  $exclude_user_ids[] = $booking_row->BookingOP;
		}
		$this->load->model('Notification_Model');

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

			$tagged = $this->Notification_Model->Get_Tagged_Users_For_Remark($remark->RemarkID, $exclude_user_ids);
			$notified_users = array();
			foreach ($tagged as $t) {
				$notified_users[] = array('AdminID' => $t->AdminID, 'Name' => $t->Name);
			}

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
				'notified_users' => $notified_users
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
		
		// Check if notifications should be skipped (when adding from Customer Remarks section)
		$skip_notifications = $this->input->post('skip_notifications') == '1' ? true : false;

		// Parse @mention handles from the comment content (internal remarks only)
		$notify_user_ids = null;
		if ($remark_type == REMARK_TYPE::INTERNAL && !$skip_notifications) {
			if (preg_match_all('/@([a-z0-9]+)/', $content, $matches) && !empty($matches[1])) {
				$ids = $this->Notification_Model->Resolve_Handles_To_User_Ids($matches[1]);
				if (!empty($ids)) {
					$notify_user_ids = $ids;
				}
			}
		}

		$remark_data = array(
			'owner_type' => 'booking',
			'owner_id' => $booking_id,
			'commenter_id' => $this->session->userdata('admin_id'),
			'content' => $content,
			'type' => $remark_type,
			'skip_notifications' => $skip_notifications,
			'notify_user_ids' => $notify_user_ids
		);

		$remark_id = $this->Remark_Model->Create($remark_data);

		if ($remark_id) {
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
		if(!in_array('AB', $access_control) && !$is_sales_agent) {
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

		// Mirror notification scoping: only TC1 (booking SalesAgent) and OP
		// (BookingOP) may mutate the checklist for this specific booking. The
		// modal can still load read-only for everyone else with AB access.
		$this->load->helper('booking_flow');
		$can_modify = can_user_modify_booking_checklist(
			$booking,
			$this->session->userdata('admin_id'),
			$this->session->userdata('level')
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
