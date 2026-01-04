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
		$this->load->model('Booking_Checklist_Completion_Model');
		$this->load->model('Product_Package_Checklist_Model');
		$this->load->model('Package_Checklist_Model');
		$this->load->model('Guest_list_lock_model');
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
			$array['categories'] = $this->Booking_Model->Read_Categories();
			$array['tags'] = $this->Booking_Model->Read_Tags();
			$array['sources'] = $this->Booking_Model->Read_Sources();

			$this->load->helper('autocount');
			$config = get_autocount_config();

			$array['bulkBookingSyncToAutocount'] = !empty($config['bulkBookingSyncToAutocount']) ? $config['bulkBookingSyncToAutocount'] : false;

			// Update status for all bookings (this runs before the AJAX calls)
			$bookings = $this->Booking_Model->Read_All_Bookings();
			foreach($bookings as $booking) {
				if((date('Y-m-d') >= $booking->StartDate && date('Y-m-d') <= $booking->EndDate) && ($booking->Status == 'PT' || $booking->Status == 'Y')) {
					$this->Booking_Model->Update_After_Sales_Service2($booking->BookingID);
					$this->Booking_Model->Update_Status('OG', $booking->BookingID);
					$this->Booking_Model->Create_Booking_Log2($booking->Status, 'OG', $booking->BookingID);
				} else {
					if((date('Y-m-d') < $booking->StartDate && ($booking->Status == 'OG' || $booking->Status == 'Y'))) {
						$this->Booking_Model->Update_After_Sales_Service2($booking->BookingID);
						$this->Booking_Model->Update_Status('PT', $booking->BookingID);
						$this->Booking_Model->Create_Booking_Log2($booking->Status, 'PT', $booking->BookingID);
					} else {
						if((date('Y-m-d') > $booking->EndDate && ($booking->Status == 'PT' || $booking->Status == 'OG'))) {
							$this->Booking_Model->Update_After_Sales_Service2($booking->BookingID);
							$this->Booking_Model->Update_Status('Y', $booking->BookingID);
							$this->Booking_Model->Create_Booking_Log2($booking->Status, 'Y', $booking->BookingID);
						}
					}
				}

				$payments = $this->Booking_Model->Read_Payments($booking->BookingID);
				$total_approved_credit = 0;
				if(!empty($payments)) {
					foreach($payments as $payment) {
						if($payment->Type != 'SUPPLIER REFUND' && $payment->Credit != 0.00 && $payment->Status == 'Y') {
							$total_approved_credit += $payment->Credit;
						}
					}
					if($total_approved_credit != 0) {
						if(strval($total_approved_credit) >= $booking->NetTotal) {
							$full_payment_existed = $this->Payment_Model->Read_Type($booking->BookingID);
							if($full_payment_existed) {
								if($booking->Status == 'P' || $booking->Status == 'PP') {
									$this->Booking_Model->Update_Status('PTV', $booking->BookingID);
									$this->Booking_Model->Create_Booking_Log2($booking->Status, 'PTV', $booking->BookingID);
								}
							} else {
								if($booking->Status == 'P') {
									$this->Booking_Model->Update_Status('PP', $booking->BookingID);
									$this->Booking_Model->Create_Booking_Log2($booking->Status, 'PP', $booking->BookingID);
								}
							}
						} else {
							if($booking->Status != 'PP') {
								$this->Booking_Model->Update_Status('PP', $booking->BookingID);
								$this->Booking_Model->Create_Booking_Log2($booking->Status, 'PP', $booking->BookingID);
							}
						}
					} else {
						if($booking->Status != 'P') {
							$this->Booking_Model->Update_Status('P', $booking->BookingID);
							$this->Booking_Model->Create_Booking_Log2($booking->Status, 'P', $booking->BookingID);
						}
					}
				} else {
					if($booking->Status != 'P') {
						$this->Booking_Model->Update_Status('P', $booking->BookingID);
						$this->Booking_Model->Create_Booking_Log2($booking->Status, 'P', $booking->BookingID);
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
		if(!in_array('VB', $this->session->access_control)) {
			echo json_encode(array('error' => 'Access denied'));
			return;
		}

		$is_sales_agent = $this->session->userdata('level') == 20;

		// DataTables parameters
		$draw = intval($this->input->get('draw'));
		$start = intval($this->input->get('start'));
		$length = intval($this->input->get('length'));

		// Order parameters
		$order_column_index = $this->input->get('order[0][column]');
		$order_dir = $this->input->get('order[0][dir]') == 'asc' ? 'ASC' : 'DESC';

		// Map column index to database column
		$columns = array(
			0 => 'booking.BookingID',      // checkbox
			1 => 'booking.BookingID',      // row number
			2 => 'admin.Name',             // sales agent (or skip for sales agents)
			3 => 'booking.InsertDate',     // creation date
			4 => 'BookingNumber',          // BC number
			5 => 'booking.BookingConfirmationTitle', // BC
			6 => 'Customer',               // customer
			7 => 'booking.ChatLanguage',   // chat
			8 => 'booking.Mobile',         // mobile
			9 => 'StartDate',              // start
			10 => 'EndDate',               // end
			11 => 'category.Name',         // destination
			12 => 'NetTotal',              // net sales
			13 => 'NetTotal',              // profit (calculated, use NetTotal as proxy)
			14 => 'NetTotal',              // profit margin (calculated)
			15 => 'booking.Status',        // BC status
			16 => 'LockStatus',            // GL status
			17 => 'booking.AutocountSyncStatus', // autocount status
			18 => 'booking.BookingID'      // action
		);

		// Adjust column index for sales agents (they don't see SA column)
		if($is_sales_agent && $order_column_index > 1) {
			$order_column_index++;
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
				if($net_profit != 0 && $booking->NetTotal != 0) {
					$profit_margin = round(($net_profit / $booking->NetTotal) * 100);
				}
			}

			// Determine display status
			$display_status = $booking->Status;
			if($booking->LockStatus == 'N' && $booking->Status == 'PTV') {
				$display_status = 'PGL';
			}
			if($booking->AfterSalesService == 'PENDING' && $booking->Status == 'Y') {
				$display_status = 'PR';
			}
			if(empty($booking->DepositDeadline)) {
				if(date('Y-m-d') > $booking->FullPaymentDeadline && ($booking->Status == 'P' || $booking->Status == 'PP')) {
					$display_status = 'PO';
				}
			} else {
				if((date('Y-m-d') > $booking->DepositDeadline && $booking->Status == 'P') || (date('Y-m-d') > $booking->FullPaymentDeadline && ($booking->Status == 'P' || $booking->Status == 'PP'))) {
					$display_status = 'PO';
				}
			}

			// Status color and text
			$status_colors = array(
				'Y' => '#50C878', 'PR' => '#C3B1E1', 'P' => '#FFBF00', 'PP' => '#A7C7E7',
				'PTV' => '#F89880', 'PGL' => '#FAC898', 'PT' => '#F8C8DC', 'OG' => '#CCCCFF', 'PO' => '#DA70D6'
			);
			$status_texts = array(
				'Y' => 'COMPLETED', 'PR' => 'PENDING REVIEW', 'P' => 'PENDING PAYMENT', 'PP' => 'PARTIAL PAYMENT',
				'PTV' => 'PENDING TRAVEL VOUCHER', 'PGL' => 'PENDING GUEST LIST', 'PT' => 'PENDING TRAVEL', 'OG' => 'ON-GOING', 'PO' => 'PAYMENT OVERDUE'
			);
			$status_color = $booking->CancelStatus == 'Y' ? '#FF69B4' : (isset($status_colors[$display_status]) ? $status_colors[$display_status] : '#DA70D6');
			$status_text = $booking->CancelStatus == 'Y' ? 'CANCELLED' : (isset($status_texts[$display_status]) ? $status_texts[$display_status] : 'UNKNOWN');

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

			// Checkbox
			$row['checkbox'] = '<input type="checkbox" class="check_item" value="' . $booking->BookingID . '">';

			// Row number
			$row['row_number'] = $count;

			// Sales agent (only for non-sales agents)
			if(!$is_sales_agent) {
				$row['sales_agent'] = $booking->SalesAgentName;
			}

			// Insert date
			$row['insert_date'] = $insert_date_formatted;

			// BC Number with link
			$row['booking_number'] = '<a href="' . base_url('Payment?booking_number=') . $booking->BookingNumber . '&customer=' . str_replace('&', '%26', $booking->Customer) . '" target="_blank">' . $booking->BookingNumber . '</a>';

			// BC Title
			$row['bc_title'] = $bc_title;

			// Customer
			$row['customer'] = $booking->Customer;

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

			// Profit (only for non-sales agents)
			if(!$is_sales_agent) {
				$row['profit'] = '<span style="color:' . $profit_color . '">' . number_format($net_profit, 2, '.', ',') . '</span>';
				$row['profit_margin'] = '<span style="color:' . $profit_color . '">' . $profit_margin . '</span>';
			}

			// Status with remarks tooltip
			$remarks = $this->Remark_Model->Read_Remarks('booking', $booking->BookingID);
			$remarks_count = count($remarks);
			$remarks_html = '';

			$bc_text = '<strong>' . $booking->BookingNumber . '</strong><br>';
			
			if (!empty($remarks)) {
				$remarks_list = array();
				foreach ($remarks as $remark) {
					$commenter_name = !empty($remark->CommenterName) ? $remark->CommenterName : 'Unknown';
					$commenter_initials = strtoupper(substr($commenter_name, 0, 2));
					$created_at = !empty($remark->created_at) ? date('d/m/Y H:i', strtotime($remark->created_at)) : '';
					
					// Calculate relative time
					$created_timestamp = strtotime($remark->created_at);
					$current_timestamp = time();
					$time_diff = $current_timestamp - $created_timestamp;
					$created_at_relative = '';
					if ($time_diff < 60) {
						$created_at_relative = 'Just now';
					} elseif ($time_diff < 3600) {
						$created_at_relative = floor($time_diff / 60) . 'm ago';
					} elseif ($time_diff < 86400) {
						$created_at_relative = floor($time_diff / 3600) . 'h ago';
					} elseif ($time_diff < 604800) {
						$created_at_relative = floor($time_diff / 86400) . 'd ago';
					} else {
						$created_at_relative = $created_at;
					}
					
					// Avatar color based on first letter
					$avatar_colors = ['primary', 'success', 'info', 'warning', 'danger'];
					$avatar_color = $avatar_colors[ord($commenter_name[0]) % 5];
					
					// Convert newlines to <br> tags and escape HTML
					$content = htmlspecialchars($remark->content, ENT_QUOTES);
					$content = nl2br($content);
					
					$remarks_list[] = '<div class="d-flex mb-2 pb-2" style="border-bottom: 1px solid #e4e6eb;">' .
						// Avatar
						'<div class="flex-shrink-0 mr-2">' .
						'<div class="symbol symbol-30 symbol-circle symbol-light-' . $avatar_color . '">' .
						'<span class="symbol-label font-weight-bold" style="font-size: 0.7rem;">' . $commenter_initials . '</span>' .
						'</div>' .
						'</div>' .
						// Comment content
						'<div class="flex-grow-1" style="min-width: 0; padding-right: 4px;">' .
						'<div class="d-flex align-items-baseline mb-1">' .
						'<strong class="mr-2" style="font-size: 0.8rem; color: #050505;">' . htmlspecialchars($commenter_name, ENT_QUOTES) . '</strong>' .
						'<span class="text-muted" style="font-size: 0.7rem; color: #65676b;">' . $created_at_relative . '</span>' .
						'</div>' .
						'<div style="font-size: 0.8rem; color: #050505; line-height: 1.3; word-wrap: break-word; white-space: pre-line;">' . $content . '</div>' .
						'</div>' .
						'</div>';
				}
				$remarks_html = '<div style="max-width: 450px; text-align: left; padding: 0; background: #fff;">' .
					'<div class="p-2 pb-1" style="border-bottom: 1px solid #e4e6eb; background: #f8f9fa; padding-right: 16px !important;">' . '<span style="font-size: 0.85rem; font-weight: 600; color: #333;">' . $bc_text . '</span></div>' .
					'<div style="padding: 10px 12px 10px 12px; padding-right: 18px !important;">' . implode('', $remarks_list) . '</div>' .
					'</div>';
			} else {
				$remarks_html = '<div class="p-2" style="text-align: center; padding: 15px; color: #65676b; font-size: 0.8rem;">'.$bc_text.' No comments found.</div>';
			}
			
			// Status with tooltip for remarks
			$status_icon = $remarks_count > 0 ? ' <i class="la la-comment" style="font-size: 0.85em; opacity: 0.7;"></i>' : '';
			$row['status'] = '<span class="font-weight-bold remarks-status" style="color:' . $status_color . '; cursor: help;" data-toggle="tooltip" data-html="true" data-placement="left" data-booking-id="' . $booking->BookingID . '" title="' . htmlspecialchars($remarks_html, ENT_QUOTES) . '">' . $status_text . $status_icon . '</span>';

			// GL Status - Basic rule:
			// 1. If old LockStatus = 'Y' -> display locked (red lock)
			// 2. If has active guest_list_lock (someone is filling) -> display loading (spinner)
			// 3. Else -> display green open lock
			$gl_status_icon = '';
			
			// First check: Old LockStatus field
			if ($booking->LockStatus == 'Y') {
				// Locked - display red lock icon
				$gl_status_icon = '<i class="la la-lock text-danger"></i>';
			} else {
				// Second check: Active guest_list_lock (someone is filling)
				if (!empty($booking->Token)) {
					$lock = $this->Guest_list_lock_model->getByHash($booking->Token);
					if (!empty($lock)) {
						// Check if lock is expired (checks both lock_expires_at timestamp and missing heartbeat)
						$is_expired = $this->Guest_list_lock_model->isExpired($lock);
						
						if (!$is_expired) {
							// Active lock exists (someone is filling) - show loading spinner
							$gl_status_icon = '<i class="la la-spinner la-spin text-primary" data-toggle="tooltip" data-placement="top" title="Guest list is being edited"></i>';
						} else {
							// Lock exists but expired - show green open lock
							$gl_status_icon = '<i class="la la-unlock text-success"></i>';
						}
					} else {
						// No lock exists - show green open lock
						$gl_status_icon = '<i class="la la-unlock text-success"></i>';
					}
				} else {
					// No token - show green open lock
					$gl_status_icon = '<i class="la la-unlock text-success"></i>';
				}
			}
			$row['gl_status'] = $gl_status_icon;

			// Autocount Status
			$row['autocount_status'] = '<span class="font-weight-bold" style="color:' . $autocount_info['color'] . '"' . $tooltip_attr . '>' . $autocount_info['text'] . '</span>';

			// Action dropdown - simplified for AJAX response
			$row['action'] = $this->build_action_dropdown($booking, $current_url, $is_sales_agent);

			$data[] = $row;
			$count++;
		}

		$output = array(
			'draw' => $draw,
			'recordsTotal' => $records_total,
			'recordsFiltered' => $records_filtered,
			'data' => $data
		);

		header('Content-Type: application/json');
		echo json_encode($output);
	}

	/**
	 * Build action dropdown HTML for a booking row
	 */
	private function build_action_dropdown($booking, $current_url, $is_sales_agent)
	{
		$html = '<div class="btn-group">';
		$html .= '<button type="button" data-toggle="dropdown" class="btn btn-light-primary btn-sm dropdown-toggle" style="padding-left:3px;"></button>';
		$html .= '<div class="dropdown-menu">';

		if($booking->Status != 'Y' || (!$is_sales_agent && $booking->Status == 'Y')) {
			if(in_array('RB', $this->session->access_control)) {
				$html .= '<button onclick="Delete_Record(\'' . base_url('assets/image/sweetalert.jpg') . '\', \'Booking Record : ' . $booking->BookingNumber . '\', \'' . base_url('Booking/Delete') . '\', \'booking_id\', ' . $booking->BookingID . ', \'' . $booking->Status . '\', \'' . (strpos($current_url, '?') ? base_url('Booking?') . explode('?', $current_url)[1] : base_url('Booking')) . '\')" class="dropdown-item" style="color:#E37383; font-size:11px;">Delete Booking</button>';
			}
			if(in_array('AB', $this->session->access_control)) {
				if($booking->CancelStatus == 'Y') {
					$html .= '<a href="' . base_url('Booking/Update_Cancel_Status?booking_id=') . $booking->BookingID . '&current_cancel_status=' . $booking->CancelStatus . '&new_cancel_status=N&param=' . urlencode($current_url) . '" class="dropdown-item" style="color:#93C572; font-size:11px;">Activate Booking</a>';
				} else {
					$html .= '<a href="' . base_url('Booking/Update_Cancel_Status?booking_id=') . $booking->BookingID . '&current_cancel_status=' . $booking->CancelStatus . '&new_cancel_status=Y&param=' . urlencode($current_url) . '" class="dropdown-item" style="color:#E0115F; font-size:11px;">Cancel Booking</a>';
				}
				if($booking->Status == 'PTV' || $booking->Status == 'PT') {
					if($booking->Status == 'PTV') {
						$html .= '<a href="' . base_url('Booking/Update_Status?booking_id=') . $booking->BookingID . '&current_status=' . $booking->Status . '&new_status=PT&param=' . urlencode($current_url) . '" class="dropdown-item" style="color:#6082B6; font-size:11px;">Sent Travel Voucher ?</a>';
					} else {
						$html .= '<a href="' . base_url('Booking/Update_Status?booking_id=') . $booking->BookingID . '&current_status=' . $booking->Status . '&new_status=PTV&param=' . urlencode($current_url) . '" class="dropdown-item" style="color:#F4BB44; font-size:11px;">Revert Pending Travel Voucher</a>';
					}
				}
				$html .= '<a href="' . (strpos($current_url, '?') ? base_url('Booking/Update?booking_id=') . $booking->BookingID . '&' . explode('?', $current_url)[1] : base_url('Booking/Update?booking_id=') . $booking->BookingID) . '" class="dropdown-item" style="font-size:11px;">Update Booking</a>';
			}
		}
		// Complete Booking / Revert Pending Review - Allow SA users to complete after-sales service
		if(in_array('AB', $this->session->access_control)) {
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
		$html .= '<div class="dropdown-divider"></div>';
		$html .= '<a href="' . base_url('Booking_Confirmation?token=') . $booking->Token . '" target="_blank" class="dropdown-item" style="font-size:11px;">Booking Confirmation</a>';
		$html .= '<button id="bc_url-' . $booking->BookingID . '" value="' . base_url('Booking_Confirmation?token=') . $booking->Token . '" onclick="Copy_URL(\'BC URL\', ' . $booking->BookingID . ')" class="dropdown-item" style="font-size:11px;">Copy BC Link</button>';
		$html .= '<div class="dropdown-divider"></div>';
		if($booking->Status != 'Y' || (!$is_sales_agent && $booking->Status == 'Y')) {
			$html .= '<a href="' . base_url('Guest_List?gl=') . $booking->Token . '" target="_blank" class="dropdown-item" style="font-size:11px;">Guest List</a>';
		}
		$html .= '<a href="' . base_url('Guest_List/Download?booking_id=') . $booking->BookingID . '" class="dropdown-item" style="font-size:11px;">Download Guest List</a>';
		$html .= '<button id="gl_url-' . $booking->BookingID . '" value="' . base_url('Guest_List?gl=') . $booking->Token . '" onclick="Copy_URL(\'GL URL\', ' . $booking->BookingID . ')" class="dropdown-item" style="font-size:11px;">Copy GL Link</button>';
		$html .= '<div class="dropdown-divider"></div>';
		$html .= '<a href="' . base_url('Travel_Voucher?token=') . $booking->Token . '" target="_blank" class="dropdown-item" style="font-size:11px;">Travel Voucher</a>';
		$html .= '<button id="tv_url-' . $booking->BookingID . '" value="' . base_url('Travel_Voucher?token=') . $booking->Token . '" onclick="Copy_URL(\'TV URL\', ' . $booking->BookingID . ')" class="dropdown-item" style="font-size:11px;">Copy TV Link</button>';
		$html .= '<div class="dropdown-divider"></div>';
		$html .= '<button id="customer_name-' . $booking->BookingID . '" value="' . $booking->Customer . '" onclick="Copy_URL(\'CUSTOMER NAME\', ' . $booking->BookingID . ')" class="dropdown-item" style="font-size:11px;">Copy Customer Name</button>';
		$html .= '<button id="customer_mobile-' . $booking->BookingID . '" value="' . $booking->CustomerMobile . '" onclick="Copy_URL(\'CUSTOMER MOBILE\', ' . $booking->BookingID . ')" class="dropdown-item" style="font-size:11px;">Copy Customer Mobile</button>';
		$html .= '<div class="dropdown-divider"></div>';
		if($booking->CustomerID != null) {
			// Load helper for generating portal hash
			$this->load->helper('utils');
			$customer_hash = generate_customer_portal_hash($booking->CustomerID);
			if (!empty($customer_hash)) {
				$portal_url = base_url('customer/' . urlencode($customer_hash));
				$html .= '<a href="' . $portal_url . '" target="_blank" class="dropdown-item" style="font-size:11px;">Go to Customer Portal</a>';
				$html .= '<button id="portal_url-' . $booking->BookingID . '" value="' . $portal_url . '" onclick="Copy_URL(\'CUSTOMER PORTAL LINK\', ' . $booking->BookingID . ')" class="dropdown-item" style="font-size:11px;">Copy Customer Portal Link</button>';
			}
		}
		else {
			$html .= '<a href="#" class="dropdown-item" style="font-size:11px; cursor:not-allowed; color:#6c757d;" disabled>Customer ID not found</a>';
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
		$total_net_profit = $summary['total_net_profit'];

		// Format output
		$total_sales_formatted = number_format($total_sales, 2, '.', ',');

		if($total_net_profit != 0 && $total_sales != 0) {
			$profit_percentage = round(($total_net_profit / $total_sales) * 100);
			$total_net_profit_formatted = number_format($total_net_profit, 2, '.', ',') . ' (' . $profit_percentage . '%)';
		} else {
			$total_net_profit_formatted = number_format($total_net_profit, 2, '.', ',') . ' (0%)';
		}

		$output = array(
			'total_sales' => $total_sales_formatted,
			'total_net_profit' => $total_net_profit_formatted,
			'is_sales_agent' => $is_sales_agent
		);

		header('Content-Type: application/json');
		echo json_encode($output);
	}

	function Create()
	{
		if(in_array('GB', $this->session->access_control)) {
			if ($this->input->is_ajax_request()) {

				$booking_id = $this->Booking_Model->Create();

				$this->Booking_Product_Model->Create($this->input->post('booking_products'), $booking_id);

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
				$array = array('BookingID' => 'NA', 'BookingConfirmationFooterID' => 'NA', 'TravelVoucherFooterID' => 'NA', 'BookingNumber' => 'NA', 'Tag' => array(), 'Discount' => 'NA', 'NetTotal' => 'NA', 'ProductSequence' => array(), 'BookingProductID' => ($this->Booking_Product_Model->Read_Last_Booking_Product_ID()) + 1, 'AllowReview' => 1);
				$array['admins'] = $this->Booking_Model->Read_Admins();
				$array['booking_products'][0] = (object) array('BookingProductID' => 'NA');
				$array['categories'] = $this->Booking_Model->Read_Categories();
				$array['products'] = $this->Booking_Model->Read_Products();
				$array['footers'] = $this->Booking_Model->Read_Footers();
				$array['country_codes'] = $this->Booking_Model->Read_Country_Codes();
				$array['tags'] = $this->Booking_Model->Read_Tags();
				$array['sources'] = $this->Booking_Model->Read_Sources();
				$this->load->view('layout/header', $titles);
				$this->load->view('booking/booking', $array);
				$this->load->view('layout/footer');
			}
		} else {
			redirect('Dashboard');
		}
	}

	function Update()
	{
		if(in_array('AB', $this->session->access_control)) {
			if($this->input->is_ajax_request()) {
				// Booking
				// Action : Update
				if(count($this->input->post('booking')[0]) > 3) {
					$this->Booking_Model->Update();
					$this->Booking_Model->Create_Booking_Log();
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
				}
				// Action : Update
				if(!empty($this->input->post('booking_products')[1])) {
					$this->Booking_Product_Model->Update($this->input->post('booking_products')[1]);
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
						// If it's a single value, convert to array
						if(!empty($checklist_completions)) {
							$checklist_completions = array($checklist_completions);
						} else {
							$checklist_completions = array();
						}
					}
					
					$created_by = $this->session->userdata('admin_id');
					
					if(!empty($created_by)) {
						// Get previous completions for logging (before updating)
						$previous_completions = array();
						if($this->db->table_exists('booking_checklist_completion')) {
							// Get previous completions from map (extract keys)
							$previous_map = $this->Booking_Checklist_Completion_Model->Read_Completion_Map($booking_id);
							$previous_completions = array_keys($previous_map);
							$this->Booking_Checklist_Completion_Model->Create($booking_id, $checklist_completions, $created_by);
						} else {
							// Table doesn't exist - log error
							log_message('error', 'booking_checklist_completion table does not exist. Please run migration.');
						}
						
						// Create activity logs for changes
						$this->log_checklist_changes($booking_id, $previous_completions, $checklist_completions, $created_by);
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
					$array['NetTotal'] = number_format($array['NetTotal'], 2, '.', ',');
					$array['admins'] = $this->Booking_Model->Read_Admins();

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
					$array['sources'] = $this->Booking_Model->Read_Sources();
					foreach($array['booking_products'] as $booking_product) {
						$booking_product->Price = number_format($booking_product->Price, 2, '.', ',');
						$booking_product->Total = number_format($booking_product->Total, 2, '.', ',');
					}

					// Get booking checklists
					$array['booking_checklists'] = $this->get_booking_checklists($array['booking_products']);
					$array['completion_map'] = $this->Booking_Checklist_Completion_Model->Read_Completion_Map($array['BookingID']);

					// Get custom uploads
					$this->load->model('Custom_Upload_Model');
					$array['custom_uploads'] = $this->Custom_Upload_Model->Read($array['BookingID']);

					if(isset($_GET['nick'])) { echo "<pre>"; print_r($array); exit; }
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
	
	function Update_Cancel_Status() 
	{
		if(in_array('AB', $this->session->access_control)) {
			$this->Booking_Model->Update_Cancel_Status();
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

	function Update_Lock_Status() 
	{
		$this->Booking_Model->Update_Lock_Status();
		$this->Booking_Model->Create_Booking_Log();
		redirect('Guest_List?gl=' . $this->input->get('gl'));
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
			$this->Booking_Model->Update_Status($this->input->get('new_status'), $this->input->get('booking_id'));
			$this->Booking_Model->Create_Booking_Log();
			
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
		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header('Content-Disposition: attachment;filename="' . $booking_records . '"');
		header('Cache-Control: max-age=0');
		header('Cache-Control: max-age=1');
		$writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
		$writer->save('php://output');
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
					'created_at' => date('d/m/Y H:i:s')
				]
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

		$remarks = $this->Remark_Model->Read_Remarks('booking', $booking_id);
		
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
			$formatted_remarks[] = array(
				'RemarkID' => $remark->RemarkID,
				'content' => $remark->content,
				'commenter_name' => $remark->CommenterName,
				'commenter_id' => $remark->commenter_id,
				'is_owner' => ($remark->commenter_id == $current_user_id),
				'commenter_initials' => $initials,
				'created_at' => date('d/m/Y H:i:s', strtotime($remark->created_at)),
				'created_at_relative' => $this->time_ago($remark->created_at),
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
		if (!in_array('AB', $this->session->access_control)) {
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

		$remark_data = array(
			'owner_type' => 'booking',
			'owner_id' => $booking_id,
			'commenter_id' => $this->session->userdata('admin_id'),
			'content' => $content
		);

		$remark_id = $this->Remark_Model->Create($remark_data);

		if ($remark_id) {
			// Get the newly created remark with commenter name
			$remark = $this->Remark_Model->Read_Remark($remark_id);
			
			$this->output
				->set_content_type('application/json')
				->set_output(json_encode([
					'success' => true,
					'message' => 'Comment added successfully',
					'remark' => array(
						'RemarkID' => $remark->RemarkID,
						'content' => $remark->content,
						'commenter_name' => $remark->CommenterName,
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
	 * Get all checklists for booking products
	 * If product doesn't have checklists, create default (required) ones
	 */
	private function get_booking_checklists($booking_products)
	{
		$all_checklists = array();
		$all_checklist_ids = array();
		
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
		
		// Process each booking product
		foreach($booking_products as $booking_product) {
			$product_id = $booking_product->ProductID;
			
			// Get checklists for this product
			$product_checklist_ids = $this->Product_Package_Checklist_Model->Get_Checklists_For_Product($product_id);
			
			// If product doesn't have checklists, use required ones and create entry
			if(empty($product_checklist_ids)) {
				$product_checklist_ids = $required_ids;
				// Create entry in product_package_checklist
				$this->Product_Package_Checklist_Model->Bulk_Update_Product_Checklists($product_id, $required_ids);
			}
			
			// Build checklist list in order
			foreach($product_checklist_ids as $checklist_id) {
				if(isset($checklist_map[$checklist_id]) && !in_array($checklist_id, $all_checklist_ids)) {
					$all_checklists[] = $checklist_map[$checklist_id];
					$all_checklist_ids[] = $checklist_id;
				}
			}
		}
		
		return $all_checklists;
	}

	/**
	 * Log checklist completion changes to booking_log
	 */
	private function log_checklist_changes($booking_id, $previous_completions, $new_completions, $created_by)
	{
		// Get checklist names for logging
		$checklist_map = array();
		$all_checklist_ids = array_unique(array_merge($previous_completions, $new_completions));
		if(!empty($all_checklist_ids)) {
			$this->db->select('ID, name');
			$this->db->where_in('ID', $all_checklist_ids);
			$checklists = $this->db->get('package_checklist')->result();
			foreach($checklists as $checklist) {
				$checklist_map[$checklist->ID] = $checklist->name;
			}
		}
		
		$booking_logs = array();
		
		// Find items that were added (ticked)
		$added = array_diff($new_completions, $previous_completions);
		foreach($added as $checklist_id) {
			$checklist_name = isset($checklist_map[$checklist_id]) ? $checklist_map[$checklist_id] : 'Checklist ID: ' . $checklist_id;
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
		foreach($removed as $checklist_id) {
			$checklist_name = isset($checklist_map[$checklist_id]) ? $checklist_map[$checklist_id] : 'Checklist ID: ' . $checklist_id;
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
			$result = $this->db->insert_batch('booking_log', $booking_logs);
			// Log for debugging
			log_message('debug', 'Booking Checklist Logs: ' . count($booking_logs) . ' entries inserted for BookingID: ' . $booking_id);
		}
	}
}