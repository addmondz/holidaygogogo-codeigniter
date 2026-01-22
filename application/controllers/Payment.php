<?php

require FCPATH.'vendor/autoload.php';  
use PhpOffice\PhpSpreadsheet\Spreadsheet;  
use PhpOffice\PhpSpreadsheet\Writer\Xlxs;

class Payment extends MY_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->load->model('Payment_Model');
		$this->load->model('Booking_Model');
		$this->load->model('Universal_Model');
		$this->config->load('autocount'); // load config/autocount.php
	}

	function index()
	{
		if(in_array('VP', $this->session->access_control)) {
			$titles = array('tab_title' => 'HolidayGoGoGo | Payment', 'breadcrumb_title' => 'Payment');

			// Initialize array with default values - totals will be loaded via AJAX
			$array = array(
				'total_supplier_payment' => 'RM 0.00',
				'total_customer_refund' => 'RM 0.00',
				'total_credit' => '0.00',
				'total_debit' => '0.00',
				'total_net_profit' => '0.00 (0%)',
				'booking_subtotal' => 0,
				'outstanding_balance_by_customer' => 0,
				'booking_id' => 'NA',
				'token' => 'NA'
			);

			// Load dropdown data
			$array['admins'] = $this->Payment_Model->Read_Admins();
			$array['suppliers'] = $this->Payment_Model->Read_Suppliers();

			// Get supplier payments breakdown
			$supplier_breakdown = $this->Payment_Model->Read_Supplier_Payments_Breakdown();
			$supplier_payments = [];
			$supplier_ids = [];
			$total_supplier = 0;
			foreach($supplier_breakdown as $item) {
				$supplier_payments[$item->Name] = $item->TotalDebit;
				$supplier_ids[] = $item->SupplierID;
				$total_supplier += $item->TotalDebit;
			}
			$array['supplier_payments'] = $supplier_payments;
			$array['supplier_ids'] = $supplier_ids;
			$array['total_supplier_payment'] = 'RM ' . number_format($total_supplier, 2, '.', ',');

			// Get customer refunds breakdown
			$refunds_breakdown = $this->Payment_Model->Read_Customer_Refunds_Breakdown();
			$customer_refunds = [];
			$total_refunds = 0;
			foreach($refunds_breakdown as $item) {
				$customer_refunds[$item->BankHolder] = $item->TotalDebit;
				$total_refunds += $item->TotalDebit;
			}
			$array['customer_refunds'] = $customer_refunds;
			$array['total_customer_refund'] = 'RM ' . number_format($total_refunds, 2, '.', ',');

			// Load autocount config
			$this->load->helper('autocount');
			$config = get_autocount_config();
			$array['bulkPaymentSyncToAutocount'] = !empty($config['bulkPaymentSyncToAutocount']) ? $config['bulkPaymentSyncToAutocount'] : false;

			// Handle booking_number filter for BC/GL links
			if(!empty($this->input->get('booking_number'))) {
				$array['booking_id'] = !empty($this->Booking_Model->Read_Booking_ID()) ? ($this->Booking_Model->Read_Booking_ID())['BookingID'] : 'NA';
				$array['token'] = !empty($this->Booking_Model->Read_Token()) ? ($this->Booking_Model->Read_Token())['Token'] : 'NA';
				$net_total = !empty($this->Booking_Model->Read_Net_Total()) ? ($this->Booking_Model->Read_Net_Total())['NetTotal'] : 0;
				$array['booking_subtotal'] = number_format($net_total, 2, '.', ',');
				// Calculate outstanding balance: NetTotal - Total Payments Received
				$total_credit = ($array['booking_id'] != 'NA') ? $this->Calculate_Total_Credit($array['booking_id']) : 0;
				$outstanding = $net_total - $total_credit;
				$array['outstanding_balance_by_customer'] = number_format($outstanding, 2, '.', ',');
			}

			if(isset($_GET['nick'])) {
				echo "<pre>";
				print_r($array);exit;
			}

			$this->load->view('layout/header', $titles);
			$this->load->view('payment/index', $array);
			$this->load->view('layout/footer');
		} else {
			redirect('Dashboard');
		}
	}

	function Calculate_Total_Credit($booking_id) {
		$payments = $this->Payment_Model->Read_Received_Payments($booking_id);
		$total_credit = 0;
		foreach($payments as $payment) {
			if($payment->Type != 'CUSTOMER REFUND') {
				$total_credit += $payment->Credit;
			} else {
				$total_credit -= $payment->Debit;
			}
		}
		return $total_credit;
	}

	// ============================================
	// Server-Side DataTables AJAX Methods
	// ============================================

	function ajax_list()
	{
		if(!in_array('VP', $this->session->access_control)) {
			header('Content-Type: application/json');
			echo json_encode(array('error' => 'Access denied'));
			return;
		}

		$is_sales_agent = $this->session->userdata('level') == 20;
		$has_ap_permission = in_array('AP', $this->session->access_control);
		$has_payment_deadline_filter = !empty($this->input->get('payment_deadline'));

		// DataTables parameters
		$draw = intval($this->input->get('draw'));
		$start = intval($this->input->get('start'));
		$length = intval($this->input->get('length'));

		// Order parameters
		$order_column_index = intval($this->input->get('order[0][column]'));
		$order_dir = $this->input->get('order[0][dir]') == 'asc' ? 'ASC' : 'DESC';

		// Build column mapping dynamically based on user role and permissions
		$columns = array(
			0 => 'payment.PaymentID',  // row number
		);

		$col_index = 1;

		// Checkbox column only exists for non-SA with AP permission
		if(!$is_sales_agent && $has_ap_permission) {
			$columns[$col_index] = 'payment.PaymentID';  // checkbox
			$col_index++;
		}

		$columns[$col_index++] = 'payment.Date';  // transaction date

		if(!$is_sales_agent) {
			$columns[$col_index] = 'admin.Name';  // sales agent
			$col_index++;
		}

		$columns[$col_index++] = 'BookingNumber';
		$columns[$col_index++] = 'Customer';
		$columns[$col_index++] = 'ReservationNumber';
		$columns[$col_index++] = 'StartDate';
		$columns[$col_index++] = 'EndDate';
		$columns[$col_index++] = 'payment.PaymentID';  // received (calculated)
		$columns[$col_index++] = 'Type';

		if(!$has_payment_deadline_filter) {
			$columns[$col_index++] = 'Credit';  // in
		}

		$columns[$col_index++] = 'Debit';           // out
		$columns[$col_index++] = 'supplier.Name';   // supplier
		$columns[$col_index++] = 'Deadline';
		$columns[$col_index++] = 'ReferenceNumber';
		$columns[$col_index++] = 'payment.AutocountReferenceNumber';
		$columns[$col_index++] = 'payment.Status';
		$columns[$col_index++] = 'payment.AutocountSyncStatus';
		$columns[$col_index++] = 'payment.PaymentID';  // action

		$order_column = isset($columns[$order_column_index]) ? $columns[$order_column_index] : 'payment.Date';

		// Get counts
		$records_total = $this->Payment_Model->Count_Payments_Total();
		$records_filtered = $this->Payment_Model->Count_Payments_Filtered();

		// Get paginated data
		$payments = $this->Payment_Model->Read_Payments_Paginated($start, $length, $order_column, $order_dir);

		// Build current URL for action links
		$current_url = base_url($_SERVER['REQUEST_URI']);

		// Process payments for display
		$data = array();
		$count = $start + 1;

		foreach($payments as $payment) {
			// Calculate TotalCredit for this booking
			$total_credit = $this->Calculate_Total_Credit($payment->BookingID);

			// Format dates
			$date_formatted = !empty($payment->Date) ? strtoupper(date('j M Y', strtotime($payment->Date))) : '';
			$deadline_formatted = !empty($payment->Deadline) ? strtoupper(date('j M Y', strtotime($payment->Deadline))) : '';
			$start_date_formatted = !empty($payment->StartDate) ? strtoupper(date('j M Y', strtotime($payment->StartDate))) : '';
			$end_date_formatted = !empty($payment->EndDate) ? strtoupper(date('j M Y', strtotime($payment->EndDate))) : '';

			// Format credit/debit for display
			$credit_raw = $payment->Credit;
			$debit_raw = $payment->Debit;
			$credit_display = $credit_raw == 0.00 ? '' : number_format($credit_raw, 2, '.', ',');
			$debit_display = $credit_raw == 0.00 ? number_format($debit_raw, 2, '.', ',') : '';

			// Build row data
			$row = array();
			$row['row_number'] = $count;

			// Checkbox column
			if(!$is_sales_agent && $has_ap_permission) {
				$row['checkbox'] = '<label class="checkbox checkbox-outline checkbox-success"><input type="checkbox" class="check_item" id="' . $payment->PaymentID . '" onclick="Select_Payment(' . $payment->PaymentID . ')"><span></span></label>';
			} else {
				$row['checkbox'] = '';
			}

			$row['transaction_date'] = '<span id="date-' . $payment->PaymentID . '">' . $date_formatted . '</span>';

			if(!$is_sales_agent) {
				$row['sales_agent'] = $payment->SalesAgent;
			}

			// Booking number link
			if(!empty($this->input->get('booking_number'))) {
				$row['booking_number'] = $payment->BookingNumber;
			} else {
				$row['booking_number'] = '<a href="' . base_url('Payment?booking_number=' . $payment->BookingNumber . '&customer=' . str_replace('&', '%26', $payment->Customer)) . '" target="_blank">' . $payment->BookingNumber . '</a>';
			}

			$row['customer'] = $payment->Customer;
			$row['reservation'] = $payment->ReservationNumber;
			$row['start_date'] = $start_date_formatted;
			$row['end_date'] = $end_date_formatted;
			$row['total_credit'] = '<span style="color:#2AAA8A">' . number_format($total_credit, 2, '.', ',') . '</span>';
			$row['type'] = $payment->Type;

			if(!$has_payment_deadline_filter) {
				$row['credit'] = '<span id="credit-' . $payment->PaymentID . '" style="color:#2AAA8A">' . $credit_display . '</span>';
			}

			$row['debit'] = '<span style="color:#F88379">' . $debit_display . '</span>';

			// Supplier link
			$row['supplier'] = '<a href="' . base_url('Supplier/Update?supplier_id=' . $payment->SupplierID) . '" target="_blank">' . $payment->Supplier . '</a>';

			$row['deadline'] = '<span id="deadline-' . $payment->PaymentID . '">' . $deadline_formatted . '</span>';
			$row['reference'] = '<span id="reference_number-' . $payment->PaymentID . '">' . $payment->ReferenceNumber . '</span>';
			$row['autocount_ref'] = '<span id="autocount_reference_number-' . $payment->PaymentID . '">' . $payment->AutocountReferenceNumber . '</span>';

			// Status icon
			if($payment->Status == 'Y') {
				$row['status'] = '<i class="la la-check-circle text-success"></i>';
			} else if($payment->Status == 'P') {
				$row['status'] = '<i class="la la-exclamation-circle text-warning"></i>';
			} else {
				$row['status'] = '<i class="la la-times-circle text-danger"></i>';
			}

			// Autocount sync status
			$row['autocount_status'] = $this->build_autocount_status($payment);

			// Action dropdown
			$row['action'] = $this->build_payment_action_dropdown($payment, $current_url);

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

	function ajax_summary()
	{
		if(!in_array('VP', $this->session->access_control)) {
			header('Content-Type: application/json');
			echo json_encode(array('error' => 'Access denied'));
			return;
		}

		$summary = $this->Payment_Model->Calculate_Payment_Summary();

		$total_credit = $summary['total_credit'];
		$total_debit = $summary['total_debit'];
		$total_net_profit = $summary['total_net_profit'];
		$total_sales = $summary['total_sales'];

		// Format output
		$profit_percentage = ($total_net_profit != 0 && $total_sales != 0)
			? round(($total_net_profit / $total_sales) * 100)
			: 0;

		$output = array(
			'total_credit' => number_format($total_credit, 2, '.', ','),
			'total_debit' => number_format($total_debit, 2, '.', ','),
			'total_net_profit' => number_format($total_net_profit, 2, '.', ',') . ' (' . $profit_percentage . '%)'
		);

		header('Content-Type: application/json');
		echo json_encode($output);
	}

	private function build_autocount_status($payment)
	{
		$statusColor = '#000000';
		$statusText = 'UNKNOWN';

		switch ($payment->AutocountSyncStatus) {
			case 'P': $statusColor = '#808080'; $statusText = 'Pending'; break;
			case 'S': $statusColor = '#50C878'; $statusText = 'Synced'; break;
			case 'F': $statusColor = '#FF4500'; $statusText = 'Failed'; break;
		}

		$tooltipAttr = '';
		if (!empty($payment->AutocountSyncMessage)) {
			$decoded = json_decode($payment->AutocountSyncMessage, true);

			if (json_last_error() === JSON_ERROR_NONE) {
				if (isset($decoded['error']) && $decoded['error'] === null) {
					$tooltipText = "SUCCESS";
				} elseif (isset($decoded['error']) && $decoded['error'] !== null) {
					$tooltipText = "ERROR: " . (is_string($decoded['error']) ? $decoded['error'] : json_encode($decoded['error']));
				} else {
					$tooltipText = $payment->AutocountSyncMessage;
				}
			} else {
				$tooltipText = $payment->AutocountSyncMessage;
			}

			$tooltipAttr = ' data-toggle="tooltip" data-placement="top" title="' . htmlspecialchars($tooltipText) . '"';
		}

		return '<span class="font-weight-bold" style="color:' . $statusColor . ';"' . $tooltipAttr . '>' . $statusText . '</span>';
	}

	private function build_payment_action_dropdown($payment, $current_url)
	{
		$credit_formatted = !empty($payment->Credit) && $payment->Credit != 0.00 ? number_format($payment->Credit, 2, '.', ',') : '';
		$debit_formatted = $payment->Credit == 0.00 ? number_format($payment->Debit, 2, '.', ',') : '';

		$delete_text = !empty($credit_formatted) ? 'Payment Record : Credit ' . $credit_formatted : 'Payment Record : Debit ' . $debit_formatted;

		$html = '<div class="btn-group">';
		$html .= '<button type="button" data-toggle="dropdown" class="btn btn-light-primary btn-sm dropdown-toggle" style="padding-left:3px;"></button>';
		$html .= '<div class="dropdown-menu">';

		// Delete option
		if(in_array('RP', $this->session->access_control)) {
			$redirect_url = strpos($current_url, '?') !== false ? base_url('Payment?') . explode('?', $current_url)[1] : base_url('Payment');
			$html .= '<button onclick="Delete_Record(\'' . base_url('assets/image/sweetalert.jpg') . '\', \'' . $delete_text . '\', \'' . base_url('Payment/Delete') . '\', \'payment_id\', ' . $payment->PaymentID . ', \'' . $payment->Status . '\', \'' . $redirect_url . '\')" class="dropdown-item" style="color:#E37383; font-size:11px;">Delete Payment</button>';
		}

		// Read option
		$html .= '<a href="' . base_url('Payment/View?payment_id=' . $payment->PaymentID) . '" class="dropdown-item" style="font-size:11px;">Read Payment</a>';

		// Generate Receipt option
		if($payment->Status == 'Y' && substr($payment->AutocountReferenceNumber, 0, 2) !== 'PV') {
			$html .= '<a href="' . base_url('Receipt?token=' . $payment->Token . '&payment_id=' . $payment->PaymentID) . '" target="_blank" class="dropdown-item" style="font-size:11px; color:#28a745;">Generate Receipt</a>';
		}

		// Update option
		if(in_array('AP', $this->session->access_control)) {
			$update_url = strpos($current_url, '?') !== false
				? base_url('Payment/Update?payment_id=' . $payment->PaymentID . '&' . explode('?', $current_url)[1])
				: base_url('Payment/Update?payment_id=' . $payment->PaymentID);
			$html .= '<a href="' . $update_url . '" class="dropdown-item" style="font-size:11px;">Update Payment</a>';
		}

		$html .= '</div></div>';

		return $html;
	}

	function Create()
	{
		if(in_array('GP', $this->session->access_control)) {
			if($this->input->post()) {
				$count = $this->input->post('count');
				$this->load->library('upload');
				$config['upload_path'] = 'assets/upload/payment';
				$config['allowed_types'] = 'jpg|jpeg|png|pdf';

				$payment_ids = array();
				for($i = 1; $i <= $count; $i++) {
					if(!empty($this->input->post('transaction_date-' . $i)) || !empty($this->input->post('payment_deadline-' . $i))) {
						$payment_id = $this->Payment_Model->Create($i);
						$milis = substr(round(microtime(true) * 1000), 2, 9);
						$config['file_name'] = 'PAYMENT_' . $payment_id . '_' . $milis;
						$this->upload->initialize($config);
						if($this->upload->do_upload('bank_slip-' . $i)) {
							$bank_slip = $this->upload->data();
							$this->Payment_Model->Update_File('BankSlip', $bank_slip, $payment_id);
						}
						$milis = substr(round(microtime(true) * 1000), 2, 9);
						$config['file_name'] = 'PAYMENT_' . $payment_id . '_' . $milis;
						$this->upload->initialize($config);
						if($this->upload->do_upload('quotation-' . $i)) {
							$quotation = $this->upload->data();
							$this->Payment_Model->Update_File('Quotation', $quotation, $payment_id);
						}
						$milis = substr(round(microtime(true) * 1000), 2, 9);
						$config['file_name'] = 'PAYMENT_' . $payment_id . '_' . $milis;
						$this->upload->initialize($config);
						if($this->upload->do_upload('invoice-' . $i)) {
							$invoice = $this->upload->data();
							$this->Payment_Model->Update_File('Invoice', $invoice, $payment_id);
						}
						$this->Payment_Model->update_by_id($payment_id, [
							'AutocountSyncAction' => 'C'
						]);
					}
				}

				// if (!empty($payment_ids))
				// {
				// 	foreach($payment_ids as $payment_id) {
				// 		$payment = $this->Payment_Model->find($payment_id);
				// 		if ($payment != null) {
				// 			$quotationData = [];
				// 			$respond = $this->autocount_create($quotationData);

				// 			if ($respond['error']) {
				// 				$this->Payment_Model->update_by_id($payment_id, [
				// 					'AutocountSyncMessage' => json_encode($respond),
				// 				]);
				// 			} elseif ($respond['status'] === 201 || $respond['status'] === 204) {
				// 				$this->Payment_Model->update_by_id($payment_id, [
				// 					'AutocountSyncMessage' => json_encode($respond),
				// 					'AutocountSyncStatus' => 'C'
				// 				]);
				// 			} 
				// 		}	
				// 	}
				// }
				$this->session->set_flashdata('message_success', 'New Payment Record Successfully Created');
				redirect($this->input->post('url'));
			} else {
				$titles = array('tab_title' => 'HolidayGoGoGo | Payment', 'breadcrumb_title' => 'Payment >> Create');
				$array['bookings'] = $this->Payment_Model->Read_Bookings();
				$array['suppliers'] = $this->Payment_Model->Read_Suppliers();
				$array['country_codes'] = $this->Payment_Model->Read_Country_Codes();
				$this->load->view('layout/header', $titles);
				$this->load->view('payment/payment', $array);
				$this->load->view('layout/footer');
			}
		} else {
			redirect('Dashboard');
		}
	}

	function Read()
    {
        $array = $this->Payment_Model->Read_Booking();
		$array['AdditionalPaymentDeadline'] = empty($array['AdditionalPaymentDeadline']) ? '-' : strtoupper(date('j M Y', strtotime($array['AdditionalPaymentDeadline'])));
		if(!empty($array['StartDate']) && !empty($array['EndDate'])) {
			$array['TravelDate'] = strtoupper(date('j M', strtotime($array['StartDate'])) . ' - ' . date('j M Y', strtotime($array['EndDate'])));
		} else {
			$array['TravelDate'] = '-';
		}
		$array['BookingRemark'] = empty($array['BookingRemark']) ? '-' : $array['BookingRemark'];
		$array['NetTotal'] = number_format($array['NetTotal'], 2, '.', ',');
		if($array['LockStatus'] == 'N' && $array['Status'] == 'PTV') {
			$array['Status'] = 'PGL';
		}
		if($array['AfterSalesService'] == 'PENDING' && $array['Status'] == 'Y') {
			$array['Status'] = 'PR';
		}
		if(empty($array['DepositDeadline'])) {
			if(date('Y-m-d') > $array['FullPaymentDeadline'] && ($array['Status'] == 'P' || $array['Status'] == 'PP')) {
				$array['Status'] = 'PO';
			}
		} else {
			if((date('Y-m-d') > $array['DepositDeadline'] && $array['Status'] == 'P') || (date('Y-m-d') > $array['FullPaymentDeadline'] && ($array['Status'] == 'P' || $array['Status'] == 'PP'))) {
				$array['Status'] = 'PO';
			}
		}
		$array['DepositDeadline'] = empty($array['DepositDeadline']) ? '-' : strtoupper(date('j M Y', strtotime($array['DepositDeadline'])));
		$array['FullPaymentDeadline'] = strtoupper(date('j M Y', strtotime($array['FullPaymentDeadline'])));
		switch($array['Status']) {
			case 'Y':
				$array['Status'] = 'COMPLETED';
				break;
			case 'PR':
				$array['Status'] = 'PENDING REVIEW';
				break;
			case 'P':
				$array['Status'] = 'PENDING PAYMENT';
				break;
			case 'PP':
				$array['Status'] = 'PARTIAL PAYMENT';
				break;
			case 'PTV':
				$array['Status'] = 'PENDING TRAVEL VOUCHER';
				break;
			case 'PGL':
				$array['Status'] = 'PENDING GUEST LIST';
				break;
			case 'PT':
				$array['Status'] = 'PENDING TRAVEL';
				break;
			case 'OG':
				$array['Status'] = 'ON-GOING';
				break;
			case 'PO':
				$array['Status'] = 'PAYMENT OVERDUE';
		}
		$payments = $this->Payment_Model->Read_BC_Payments();
		$array['credit_payments'] = [];
		$array['debit_payments'] = [];
		foreach($payments as $payment) {
			$payment->Date = empty($payment->Date) ? '-' : strtoupper(date('j M Y', strtotime($payment->Date)));
			$payment->Credit = $payment->Credit != 0.00 ? number_format($payment->Credit, 2, '.', ',') : '';
			$payment->ReferenceNumber = empty($payment->ReferenceNumber) ? '-' : $payment->ReferenceNumber;
			$payment->Debit = $payment->Credit != 0.00 ? '' : number_format($payment->Debit, 2, '.', ',');
			$payment->PaymentRemark = empty($payment->PaymentRemark) ? '-' : $payment->PaymentRemark;
			if($payment->Type == 'DEPOSIT' || $payment->Type == 'FULL' || $payment->Type == 'SUPPLIER REFUND' || $payment->Type == 'ADDITIONAL PAYMENT') {
				array_push($array['credit_payments'], $payment);
			} else {
				$payment->Deadline = strtoupper(date('j M Y', strtotime($payment->Deadline)));
				$payment->QuotationNumber = empty($payment->QuotationNumber) ? '-' : $payment->QuotationNumber;
				$payment->InvoiceNumber = empty($payment->InvoiceNumber) ? '-' : $payment->InvoiceNumber;
				$payment->DebitRemark = empty($payment->DebitRemark) ? '-' : $payment->DebitRemark;
				array_push($array['debit_payments'], $payment);
			}
			switch($payment->Status) {
				case 'Y':
					$payment->Status = '<i class="la la-check-circle text-success"></i>';
					break;
				case 'P':
					$payment->Status = '<i class="la la-exclamation-circle text-warning"></i>';
					break;
				case 'R':
					$payment->Status = empty($payment->Remark) ? '<i class="la la-times-circle text-danger"></i>' : '<i class="la la-times-circle text-danger"></i> ' . $payment->Remark;
			}
		}
        echo json_encode($array);
    }

	function Update()
	{
		if(in_array('AP', $this->session->access_control)) {
			if($this->input->post()) {
				$payment = $this->Payment_Model->Read_Payment();
				$array['payment'][0] = array('PaymentID' => $this->input->get('payment_id'), 'UpdateBy' => $this->session->userdata('admin_id'), 'UpdateDate' => date('Y-m-d H:i:s'));
				$supplier_id = $this->input->post('supplier');
				$date = empty($this->input->post('transaction_date')) ? null : date('Y-m-d', strtotime(str_replace('/', '-', $this->input->post('transaction_date'))));
				$type = $this->input->post('payment_type');
				$currency = empty($this->input->post('currency_code')) ? null : $this->input->post('currency_code');
				$foreign_currency = empty($this->input->post('foreign_currency-' . $this->input->get('payment_id'))) ? 0.00 : str_replace(',', '', $this->input->post('foreign_currency-' . $this->input->get('payment_id')));
				$credit = str_replace(',', '', $this->input->post('credit-' . $this->input->get('payment_id')));
				$reference_number = empty($this->input->post('reference_number')) ? null : strtoupper($this->input->post('reference_number'));
				$debit = empty($this->input->post('debit-' . $this->input->get('payment_id'))) ? 0.00 : str_replace(',', '', $this->input->post('debit-' . $this->input->get('payment_id')));
				$deadline = empty($this->input->post('payment_deadline')) ? null : date('Y-m-d', strtotime(str_replace('/', '-', $this->input->post('payment_deadline'))));
				$quotation_number = empty($this->input->post('quotation_number')) ? null : strtoupper($this->input->post('quotation_number'));
				$invoice_number = empty($this->input->post('invoice_number')) ? null : strtoupper($this->input->post('invoice_number'));
				$bank = empty($this->input->post('bank')) ? null : strtoupper($this->input->post('bank'));
				$bank_account = empty($this->input->post('bank_account')) ? null : strtoupper($this->input->post('bank_account'));
				$bank_holder = empty($this->input->post('bank_holder')) ? null : strtoupper($this->input->post('bank_holder'));
				$debit_remark = empty($this->input->post('remark')) ? null : strtoupper($this->input->post('remark'));
				$payment_remark = empty($this->input->post('payment_remark')) ? null : strtoupper($this->input->post('payment_remark'));
				$status = $this->input->post('status');
				$remark = empty($this->input->post('rejection_reason')) ? null : strtoupper($this->input->post('rejection_reason'));
				$this->load->library('upload');
				$config['upload_path'] = 'assets/upload/payment';
				$config['allowed_types'] = 'jpg|jpeg|png|pdf';
				if($payment['Credit'] == 0.00 && $supplier_id != $payment['SupplierID']) {
					$array['payment'][0]['SupplierID'] = $supplier_id;
					$this->Payment_Model->Create_Payment_Log('SupplierID', $payment['SupplierID'], $supplier_id, $payment['PaymentID']);
				}
				if($date != $payment['Date']) {
					$array['payment'][0]['Date'] = $date;
					$this->Payment_Model->Create_Payment_Log('Date', $payment['Date'], $date, $payment['PaymentID']);
				}
				if($type != $payment['Type']) {
					$array['payment'][0]['Type'] = $type;
					$this->Payment_Model->Create_Payment_Log('Type', $payment['Type'], $type, $payment['PaymentID']);
				}
				if($payment['Credit'] == 0.00) {
					if($currency != $payment['Currency']) {
						$array['payment'][0]['Currency'] = $currency;
						$this->Payment_Model->Create_Payment_Log('Currency', $payment['Currency'], $currency, $payment['PaymentID']);
					}
					if($foreign_currency != $payment['ForeignCurrency']) {
						$array['payment'][0]['ForeignCurrency'] = $foreign_currency;
						$this->Payment_Model->Create_Payment_Log('ForeignCurrency', $payment['ForeignCurrency'], $foreign_currency, $payment['PaymentID']);
					}
				}
				if($payment['Credit'] != 0.00) {
					if($credit != $payment['Credit']) {
						$array['payment'][0]['Credit'] = $credit;
						$this->Payment_Model->Create_Payment_Log('Credit', $payment['Credit'], $credit, $payment['PaymentID']);
					}
				}
				if($reference_number != $payment['ReferenceNumber']) {
					$array['payment'][0]['ReferenceNumber'] = $reference_number;
					$this->Payment_Model->Create_Payment_Log('ReferenceNumber', $payment['ReferenceNumber'], $reference_number, $payment['PaymentID']);
				}
				$milis = substr(round(microtime(true) * 1000), 2, 9);
				$config['file_name'] = 'PAYMENT_' . $this->input->get('payment_id') . '_' . $milis;
				$this->upload->initialize($config);
				if($this->upload->do_upload('bank_slip')) {
					$bank_slip = $this->upload->data();
					$this->Payment_Model->Update_File('BankSlip', $bank_slip, $this->input->get('payment_id'));
					$this->Payment_Model->Create_Payment_Log('BankSlip', null, $bank_slip, $payment['PaymentID']);
				}
				if($payment['Credit'] == 0.00) {
					if($debit != $payment['Debit']) {
						$array['payment'][0]['Debit'] = $debit;
						$this->Payment_Model->Create_Payment_Log('Debit', $payment['Debit'], $debit, $payment['PaymentID']);
					}
					if($deadline != $payment['Deadline']) {
						$array['payment'][0]['Deadline'] = $deadline;
						$this->Payment_Model->Create_Payment_Log('Deadline', $payment['Deadline'], $deadline, $payment['PaymentID']);
					}
					if($quotation_number != $payment['QuotationNumber']) {
						$array['payment'][0]['QuotationNumber'] = $quotation_number;
						$this->Payment_Model->Create_Payment_Log('QuotationNumber', $payment['QuotationNumber'], $quotation_number, $payment['PaymentID']);
					}
					$milis = substr(round(microtime(true) * 1000), 2, 9);
					$config['file_name'] = 'PAYMENT_' . $this->input->get('payment_id') . '_' . $milis;
					$this->upload->initialize($config);
					if($this->upload->do_upload('quotation')) {
						$quotation = $this->upload->data();
						$this->Payment_Model->Update_File('Quotation', $quotation, $this->input->get('payment_id'));
						$this->Payment_Model->Create_Payment_Log('Quotation', null, $quotation, $payment['PaymentID']);
					}
					if($invoice_number != $payment['InvoiceNumber']) {
						$array['payment'][0]['InvoiceNumber'] = $invoice_number;
						$this->Payment_Model->Create_Payment_Log('InvoiceNumber', $payment['InvoiceNumber'], $invoice_number, $payment['PaymentID']);
					}
					$milis = substr(round(microtime(true) * 1000), 2, 9);
					$config['file_name'] = 'PAYMENT_' . $this->input->get('payment_id') . '_' . $milis;
					$this->upload->initialize($config);
					if($this->upload->do_upload('invoice')) {
						$invoice = $this->upload->data();
						$this->Payment_Model->Update_File('Invoice', $invoice, $this->input->get('payment_id'));
						$this->Payment_Model->Create_Payment_Log('Invoice', null, $invoice, $payment['PaymentID']);
					}
					if($bank != $payment['Bank']) {
						$array['payment'][0]['Bank'] = $bank;
						$this->Payment_Model->Create_Payment_Log('Bank', $payment['Bank'], $bank, $payment['PaymentID']);
					}
					if($bank_account != $payment['BankAccount']) {
						$array['payment'][0]['BankAccount'] = $bank_account;
						$this->Payment_Model->Create_Payment_Log('BankAccount', $payment['BankAccount'], $bank_account, $payment['PaymentID']);
					}
					if($bank_holder != $payment['BankHolder']) {
						$array['payment'][0]['BankHolder'] = $bank_holder;
						$this->Payment_Model->Create_Payment_Log('BankHolder', $payment['BankHolder'], $bank_holder, $payment['PaymentID']);
					}
					if($debit_remark != $payment['DebitRemark']) {
						$array['payment'][0]['DebitRemark'] = $debit_remark;
						$this->Payment_Model->Create_Payment_Log('DebitRemark', $payment['DebitRemark'], $debit_remark, $payment['PaymentID']);
					}
				}
				if($payment_remark != $payment['PaymentRemark']) {
					$array['payment'][0]['PaymentRemark'] = $payment_remark;
					$this->Payment_Model->Create_Payment_Log('PaymentRemark', $payment['PaymentRemark'], $payment_remark, $payment['PaymentID']);
				}
				if($status != $payment['Status']) {
					$array['payment'][0]['Status'] = $status;
					$this->Payment_Model->Create_Payment_Log('Status', $payment['Status'], $status, $payment['PaymentID']);
				}
				if($remark != $payment['Remark']) {
					$array['payment'][0]['Remark'] = $remark;
					$this->Payment_Model->Create_Payment_Log('Remark', $payment['Remark'], $remark, $payment['PaymentID']);
				}
				if(count($array['payment'][0]) > 3 || !empty($_FILES['bank_slip']['name']) || !empty($_FILES['quotation']['name']) || !empty($_FILES['invoice']['name'])) {
					if(count($array['payment'][0]) > 3) {
						$this->Payment_Model->Update($array['payment']);

						if ($payment['AutocountSyncAction'] == 'C' && $payment['AutocountSyncStatus'] == 'S') {
							// Update action to 'U' and status to 'P' if action is 'C' and status is 'S'
							$this->Payment_Model->update_by_id($payment['PaymentID'], [
								'AutocountSyncAction' => 'U',
								'AutocountSyncStatus' => 'P'
							]);
						} elseif ($payment['AutocountSyncAction'] == 'U' && $payment['AutocountSyncStatus'] == 'S') {
							// Update status to 'P' if action is 'U' and status is 'S'
							$this->Payment_Model->update_by_id($payment['PaymentID'], [
								'AutocountSyncStatus' => 'P'
							]);
						} else {
							// Just update status to 'P' in all other cases
							$this->Payment_Model->update_by_id($payment['PaymentID'], [
								'AutocountSyncStatus' => 'P'
							]);
						}
						
						// if ($payment != null) {
						// 	$quotationData = [];
						// 	$respond = $this->autocount_create($quotationData);

						// 	if ($respond['error']) {
						// 		$this->Payment_Model->update_by_id($payment->PaymentID, [
						// 			'AutocountSyncMessage' => json_encode($respond),
						// 		]);
						// 	} elseif ($respond['status'] === 201 || $respond['status'] === 204) {
						// 		$this->Payment_Model->update_by_id($payment->PaymentID, [
						// 			'AutocountSyncMessage' => json_encode($respond),
						// 			'AutocountSyncStatus' => 'U'
						// 		]);
						// 	} 
						// }	
					}
					$this->session->set_flashdata('message_success', $payment['Credit'] != 0.00 ? 'Payment Record : Credit RM ' . number_format($payment['Credit'], 2, '.', ',') . ' Successfully Updated' : 'Payment Record : Debit RM ' . number_format($payment['Debit'], 2, '.', ',') . ' Successfully Updated');
				} else {
					$this->session->set_flashdata('message_success', $payment['Credit'] != 0.00 ? 'No Changes Detected In Payment Record : Credit RM ' . number_format($payment['Credit'], 2, '.', ',') : 'No Changes Detected In Payment Record : Debit RM ' . number_format($payment['Debit'], 2, '.', ','));
				}
				redirect($this->input->post('url'));
			} else {
				$valid_payment_id = $this->Universal_Model->Validate_Id('PaymentID', $this->input->get('payment_id'), 'payment');
				if($valid_payment_id) {
					$titles = array('tab_title' => 'HolidayGoGoGo | Payment', 'breadcrumb_title' => 'Payment >> Update');
					$array = $this->Payment_Model->Read_Payment();
					if(!empty($array['Date'])) {
						$array['Date'] = date('d/m/Y', strtotime($array['Date']));
					}
					if($array['ForeignCurrency'] != 0.00) {
						$array['ForeignCurrency'] = number_format($array['ForeignCurrency'], 2, '.', ',');
					} else {
						$array['ForeignCurrency'] = null;
					}
					if($array['Credit'] != 0.00) {
						$array['Credit'] = number_format($array['Credit'], 2, '.', ',');
					}
					if(!empty($array['BankSlip'])) {
						$array['BankSlip'] = base_url('assets/upload/payment/' . $array['BankSlip']);
					}
					if($array['Debit'] != 0.00) {
						$array['Debit'] = number_format($array['Debit'], 2, '.', ',');
					} else {
						$array['Debit'] = null;
					}
					if(!empty($array['Deadline'])) {
						$array['Deadline'] = date('d/m/Y', strtotime($array['Deadline']));
					}
					if(!empty($array['Quotation'])) {
						$array['Quotation'] = base_url('assets/upload/payment/' . $array['Quotation']);
					}
					if(!empty($array['Invoice'])) {
						$array['Invoice'] = base_url('assets/upload/payment/' . $array['Invoice']);
					}
					$array['suppliers'] = $this->Payment_Model->Read_Suppliers();
					$array['country_codes'] = $this->Payment_Model->Read_Country_Codes();
					$this->load->view('layout/header', $titles);
					$this->load->view('payment/payment', $array);
					$this->load->view('layout/footer');
				} else {
					redirect('Payment');
				}
			}
		} else {
			redirect('Dashboard');
		}
	}

	function Bulk_Update()
	{
		if(in_array('AP', $this->session->access_control)) {
			$date = $this->input->post('date');
			$deadline = $this->input->post('deadline');
			$reference_number = $this->input->post('reference');
			$status = $this->input->post('action');
			$payments_exempted_from_updating_transaction_date = explode(',', $this->input->post('payment_ids_exempted_from_updating_transaction_date'));
			$payments_exempted_from_updating_payment_deadline = explode(',', $this->input->post('payment_ids_exempted_from_updating_payment_deadline'));
			$payments_exempted_from_updating_reference_number = explode(',', $this->input->post('payment_ids_exempted_from_updating_reference_number'));
			$payments = explode(',', $this->input->post('payment_ids'));
			for($i = 0; $i < count($payments); $i++) {
				if(!empty($date)) {
					if(!in_array($payments[$i], $payments_exempted_from_updating_transaction_date)) {
						$this->Payment_Model->Update_Date(date('Y-m-d', strtotime(str_replace('/', '-', $date))), $payments[$i]);
						$this->Payment_Model->Create_Payment_Log('Date', $this->Payment_Model->Read_Date($payments[$i]), date('Y-m-d', strtotime(str_replace('/', '-', $date))), $payments[$i]);
					}
				}
				if(!empty($deadline)) {
					if(!in_array($payments[$i], $payments_exempted_from_updating_payment_deadline)) {
						$this->Payment_Model->Update_Deadline(date('Y-m-d', strtotime(str_replace('/', '-', $deadline))), $payments[$i]);
						$this->Payment_Model->Create_Payment_Log('Deadline', $this->Payment_Model->Read_Deadline($payments[$i]), date('Y-m-d', strtotime(str_replace('/', '-', $deadline))), $payments[$i]);
					}
				}
				if(!empty($reference_number)) {
					if(!in_array($payments[$i], $payments_exempted_from_updating_reference_number)) {
						$this->Payment_Model->Update_Reference_Number($reference_number, $payments[$i]);
						$this->Payment_Model->Create_Payment_Log('ReferenceNumber', $this->Payment_Model->Read_Reference_Number($payments[$i]), $reference_number, $payments[$i]);
					}
				}
				if(!empty($status)) {
					$this->Payment_Model->Update_Status($status, $payments[$i]);
					$this->Payment_Model->Create_Payment_Log('Status', $this->Payment_Model->Read_Status($payments[$i]), $status, $payments[$i]);
				}
				
			}
			$this->session->set_flashdata('message_success', 'Payment Records Successfully Updated');
			if(strpos($this->input->get('url'), '?') == true) {
				redirect('Payment?' . explode('?', $this->input->get('url'))[1]);
			} else {
				redirect('Payment');
			}
		} else {
			redirect('Dashboard');
		}
	}
	
	function Delete()
	{
		if(in_array('RP', $this->session->access_control)) {
			$this->Universal_Model->Delete('PaymentID', $this->input->get('payment_id'), 'payment');
			$this->Payment_Model->Create_Payment_Log2();

			
			// $paymentData = $this->Payment_Model->getPaymentById($this->input->get('payment_id'));
        	// $paymentNumber = (!empty($paymentData) && !empty($paymentData->ReferenceNumber)) ? $paymentData->ReferenceNumber : '';

			// if (!empty($paymentNumber)) {
			// 	$payment = $this->Payment_Model->find($this->input->get('payment_id'));

			// 	if ($payment != null) {
			// 		$quotationData = [];
			// 		$respond = $this->autocount_create($quotationData);

			// 		if ($respond['error']) {
			// 			$this->Payment_Model->update_by_id($payment->PaymentID, [
			// 				'AutocountSyncMessage' => json_encode($respond),
			// 			]);
			// 		} elseif ($respond['status'] === 201 || $respond['status'] === 204) {
			// 			$this->Payment_Model->update_by_id($payment->PaymentID, [
			// 				'AutocountSyncMessage' => json_encode($respond),
			// 				'AutocountSyncStatus' => 'D'
			// 			]);
			// 		} 
			// 	}	
				
			// }

			$payment = get_object_vars($this->Payment_Model->find($this->input->get('payment_id')));
			if (!empty($payment)) {
				if ($payment['AutocountSyncAction'] == 'C' && $payment['AutocountSyncStatus'] == 'S') {
					$this->Payment_Model->update_by_id($this->input->get('payment_id'), [
						'AutocountSyncAction' => 'D',
						'AutocountSyncStatus' => 'P'
					]);				
				} else if ($payment['AutocountSyncAction'] == 'U') {
					$this->Payment_Model->update_by_id($this->input->get('payment_id'), [
						'AutocountSyncAction' => 'D',
						'AutocountSyncStatus' => 'P'
					]);	
				} else {
					$this->Payment_Model->update_by_id($this->input->get('payment_id'), [
						'AutocountSyncStatus' => 'P'
					]);
				}
			}
			
		} else {
			redirect('Dashboard');
		}
	}

	function Download() {
		$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
		$spreadsheet->getActiveSheet()->setTitle('Payment Records');
		$spreadsheet->getProperties()->setCreator('HolidayGoGoGo');
		$spreadsheet->getActiveSheet()->setCellValue('A1', 'TRANSACTION DATE');
		$spreadsheet->getActiveSheet()->setCellValue('B1', 'PAYMENT IN');
		$spreadsheet->getActiveSheet()->setCellValue('C1', 'PAYMENT OUT');
		$spreadsheet->getActiveSheet()->setCellValue('D1', 'CUSTOMER');
		$spreadsheet->getActiveSheet()->setCellValue('E1', 'TRAVEL DATE');
		$spreadsheet->getActiveSheet()->setCellValue('F1', 'SUPPLIER');
		$spreadsheet->getActiveSheet()->setCellValue('G1', 'RESERVATION NUMBER');
		$spreadsheet->getActiveSheet()->setCellValue('H1', 'REFERENCE NUMBER');
		$spreadsheet->getActiveSheet()->setCellValue('I1', 'QUOTATION NUMBER');
		$spreadsheet->getActiveSheet()->setCellValue('J1', 'BOOKING NUMBER');
		$spreadsheet->getActiveSheet()->setCellValue('K1', 'DESTINATION');
		$spreadsheet->getActiveSheet()->setCellValue('L1', 'SALES AGENT');
		$spreadsheet->getActiveSheet()->setCellValue('M1', 'PAYMENT TYPE');
		$spreadsheet->getActiveSheet()->setCellValue('N1', 'FOREIGN CURRENCY');
		$spreadsheet->getActiveSheet()->setCellValue('O1', 'PAYMENT DEADLINE');
		$spreadsheet->getActiveSheet()->setCellValue('P1', 'INVOICE NUMBER');
		$spreadsheet->getActiveSheet()->setCellValue('Q1', 'BANK');
		$spreadsheet->getActiveSheet()->setCellValue('R1', 'BANK ACCOUNT');
		$spreadsheet->getActiveSheet()->setCellValue('S1', 'BANK HOLDER');
		$spreadsheet->getActiveSheet()->setCellValue('T1', 'REMARK');
		$credit = 0;
		$debit = 0;
		$row = 2;
		$payments = $this->Payment_Model->Read_Payments2();
		$spreadsheet->getActiveSheet()->getStyle('A1:T1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_BLACK);
		$spreadsheet->getActiveSheet()->getStyle('A1:T1')->getFont()->getColor()->setARGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE);
		$spreadsheet->getActiveSheet()->getStyle('A1:T1')->getFont()->setBold(true);
		if(!empty($payments)) {
			foreach($payments as $payment) {
				$credit = $credit + $payment->Credit;
				$debit = $debit + $payment->Debit;
				if(!empty($payment->StartDate) && !empty($payment->EndDate)) {
					$payment->TravelDate = strtoupper(date('j M', strtotime($payment->StartDate)) . ' - ' . date('j M Y', strtotime($payment->EndDate)));
				} else {
					$payment->TravelDate = null;
				}
				if(!empty($payment->Date)) {
					$payment->Date = strtoupper(date('j M Y', strtotime($payment->Date)));
				}
				$payment->ForeignCurrency = $payment->ForeignCurrency == 0.00 ? '' : $payment->ForeignCurrency;
				$payment->Credit = $payment->Credit == 0.00 ? '' : $payment->Credit;
				$payment->Debit = $payment->Credit == 0.00 ? $payment->Debit : '';
				if(!empty($payment->Deadline)) {
					$payment->Deadline = strtoupper(date('j M Y', strtotime($payment->Deadline)));
				}
				$spreadsheet->getActiveSheet()->setCellValueExplicit('A' . $row, $payment->Date, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValue('B' . $row, $payment->Credit);
				$spreadsheet->getActiveSheet()->setCellValue('C' . $row, $payment->Debit);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('D' . $row, $payment->Customer, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('E' . $row, $payment->TravelDate, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('F' . $row, $payment->Supplier, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('G' . $row, $payment->ReservationNumber, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('H' . $row, $payment->ReferenceNumber, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('I' . $row, $payment->QuotationNumber, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('J' . $row, $payment->BookingNumber, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('K' . $row, $payment->Destination, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('L' . $row, $payment->SalesAgent, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('M' . $row, $payment->Type, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValue('N' . $row, $payment->ForeignCurrency);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('O' . $row, $payment->Deadline, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('P' . $row, $payment->InvoiceNumber, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('Q' . $row, $payment->Bank, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('R' . $row, $payment->BankAccount, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('S' . $row, $payment->BankHolder, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('T' . $row, $payment->PaymentRemark, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$row++;
			}
			$spreadsheet->getActiveSheet()->getStyle('B')->getNumberFormat()->setFormatCode('#,##0.00_-');
			$spreadsheet->getActiveSheet()->getStyle('C')->getNumberFormat()->setFormatCode('#,##0.00_-');
			$spreadsheet->getActiveSheet()->getStyle('N')->getNumberFormat()->setFormatCode('#,##0.00_-');
			$total_credit = $credit;
			$total_debit = $debit;
			$spreadsheet->getActiveSheet()->getCell('A' . ($row + 2))->setValue('Total');
			$spreadsheet->getActiveSheet()->getStyle('B' . ($row + 2) . ':' . 'C' . ($row + 2))->getBorders()->getTop()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
			$spreadsheet->getActiveSheet()->setCellValue('B' . ($row + 2), $total_credit);
			$spreadsheet->getActiveSheet()->setCellValue('C' . ($row + 2), $total_debit);
			$spreadsheet->getActiveSheet()->getStyle('B' . ($row + 2) . ':' . 'C' . ($row + 2))->getBorders()->getBottom()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_DOUBLE);
			$spreadsheet->getActiveSheet()->getCell('A' . ($row + 3))->setValue('Difference');
			$spreadsheet->getActiveSheet()->getCell('B' . ($row + 3))->setValue('');
			$spreadsheet->getActiveSheet()->getCell('A' . ($row + 4))->setValue('%');
			$spreadsheet->getActiveSheet()->getCell('B' . ($row + 4))->setValue('');
			$spreadsheet->getActiveSheet()->getStyle('B' . ($row + 2))->getNumberFormat()->setFormatCode('#,##0.00_-');
			$spreadsheet->getActiveSheet()->getStyle('C' . ($row + 2))->getNumberFormat()->setFormatCode('#,##0.00_-');
			$spreadsheet->getActiveSheet()->getStyle('A:T')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
		} else {
			$spreadsheet->getActiveSheet()->mergeCells('A2:T2');
			$spreadsheet->getActiveSheet()->getCell('A2')->setValue('Payment Records Not Found');
			$spreadsheet->getActiveSheet()->getStyle('A:T')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
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
		$payment_records = 'PAYMENT_RECORDS_' . date('Ymd') . '.xlsx';
		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header('Content-Disposition: attachment;filename="' . $payment_records . '"');
		header('Cache-Control: max-age=0');
		header('Cache-Control: max-age=1');
		$writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
		$writer->save('php://output');
	}

	function View() {
		$valid_payment_id = $this->Universal_Model->Validate_Id('PaymentID', $this->input->get('payment_id'), 'payment');
		if($valid_payment_id) {
			$titles = array('tab_title' => 'HolidayGoGoGo | Payment', 'breadcrumb_title' => 'Payment >> Read');
			$array = $this->Payment_Model->Read_Payment();
			if(!empty($array['Date'])) {
				$array['Date'] = date('d/m/Y', strtotime($array['Date']));
			}
			if($array['ForeignCurrency'] != 0.00) {
				$array['ForeignCurrency'] = number_format($array['ForeignCurrency'], 2, '.', ',');
			} else {
				$array['ForeignCurrency'] = null;
			}
			if($array['Credit'] != 0.00) {
				$array['Credit'] = number_format($array['Credit'], 2, '.', ',');
			}
			if(!empty($array['BankSlip'])) {
				$array['BankSlip'] = base_url('assets/upload/payment/' . $array['BankSlip']);
			}
			if($array['Debit'] != 0.00) {
				$array['Debit'] = number_format($array['Debit'], 2, '.', ',');
			} else {
				$array['Debit'] = null;
			}
			if(!empty($array['Deadline'])) {
				$array['Deadline'] = date('d/m/Y', strtotime($array['Deadline']));
			}
			if(!empty($array['Quotation'])) {
				$array['Quotation'] = base_url('assets/upload/payment/' . $array['Quotation']);
			}
			if(!empty($array['Invoice'])) {
				$array['Invoice'] = base_url('assets/upload/payment/' . $array['Invoice']);
			}
			$array['suppliers'] = $this->Payment_Model->Read_Suppliers();
			$array['country_codes'] = $this->Payment_Model->Read_Country_Codes();
			$this->load->view('layout/header', $titles);
			$this->load->view('payment/payment', $array);
			$this->load->view('layout/footer');
		} else {
			redirect('Payment');
		}
	}
	
	function Detect() {
		$redundant_full_payment = $this->Payment_Model->Detect();
		if($redundant_full_payment) {
			echo json_encode(true);
		} else {
			echo json_encode(false);
		}
	}

	public function bulkSyncToAutocount()
    {
		$this->load->helper('autocount');
		$config = get_autocount_config();

		$input = json_decode($this->input->raw_input_stream, true);
        $payment_ids = $input['payment_ids'] ? $input['payment_ids'] : []; 
		$statuses = !empty($config['payment_sync_status']) ? $config['payment_sync_status'] : ['Y'];

        if (empty($payment_ids)) {
			$this->output
				->set_content_type('application/json')
				->set_output(json_encode([
					'success' => false,
					'message' => 'No payments selected'
				]));
			return;
		}

        $results = [];

        foreach ($payment_ids as $payment_id) {
            try {
				$payment = $this->Payment_Model->find($payment_id);
                if (!$payment) {
                    $results[$payment_id] = 'Not Found';
                    continue;
                }

				$paymentData = $this->Payment_Model->getAllBookingsWithGuests($payment_id);
				if (!empty($paymentData)) {
					$paymentData = (array) $paymentData[0]; 
				}

				if (in_array($payment->status, $statuses)) {
					switch ($paymentData['AutocountSyncStatus']) {
						case 'N': // new → create
							$quotationData = [
								'BookingNumber'   => $paymentData['BookingNumber'] ?? '',
								'InsertDate'      => $paymentData['InsertDate'] ?? date('Y-m-d'),
								'Customer'        => $paymentData['Customer'] ?? '',
								'guest_email'     => $paymentData['guest_email'] ?? '',
								'guest_address'   => $paymentData['guest_address'] ?? '',
								'guest_phone'     => $paymentData['guest_phone'] ?? '',
								'BokingRemark'    => $paymentData['BokingRemark'] ?? '',
								
								// Fields not in DB → set default or null
								'credit_term'     => null,
								'sales_location'  => '',
								'currency_rate'   => 1,
								'inclusive_tax'   => false,
								'is_round_adj'    => false,
								'tax_code'        => '',

								// Details
								'details' => $bookingProducts
							];

							$respond = $this->autocount_create($quotationData);
							$payment->AutocountSyncStatus = 'C';
							$payment->AutocountSyncMessage = $respond;

							$results[$payment->id] = 'Created';
							break;

						case 'U': // update
						case 'C': // created → still allow update
							$quotationData = [
								'BookingNumber'   => $paymentData['BookingNumber'],
								'DocNo'           => $paymentData['BookingNumber'], // Fallback
								'master'          => [
									'DocDate'        => $paymentData['InsertDate'],
									'DebtorName'     => $paymentData['Customer'],
									'Email'          => $paymentData['guest_email'],
									'Address'        => $paymentData['guest_address'],
									'Phone1'         => $paymentData['guest_phone'],
									'DeliverAddress' => $paymentData['guest_address'],
									'DeliverContact' => $paymentData['Customer'],
									'DeliverPhone1'  => $paymentData['guest_phone'],
									'Remark1'        => $paymentData['BokingRemark'],
								],
								'details' => $bookingProducts,
								'tax_code'        => 'S-5', // Default tax code if missing
								'saveApprove'     => null
							];
							$respond = $this->autocount_update($payment);
							$payment->AutocountSyncStatus = 'C'; // keep as created
							$payment->AutocountSyncMessage = $respond;

							$results[$payment->id] = 'Updated';
							break;

						case 'D': // delete
							$paymentNumber = (!empty($payment) && !empty($payment->ReferenceNumber)) ? $payment->ReferenceNumber : '';

							if (!empty($paymentNumber)) {
								$respond = $this->autocount_delete([
									'PaymentNumber' => $paymentNumber
								]);

								$payment->AutocountSyncStatus = 'D';
								$payment->AutocountSyncMessage = $respond;
								$results[$payment->id] = 'Deleted';
								break;
							}
						case 'V': // void
							$paymentNumber = (!empty($payment) && !empty($payment->ReferenceNumber)) ? $payment->ReferenceNumber : '';

							if (!empty($paymentNumber)) {
								$respond = $this->autocount_void([
									'PaymentNumber' => $paymentNumber
								]);

								$payment->AutocountSyncStatus = 'V';
								$payment->AutocountSyncMessage = $respond;
								$results[$payment->id] = 'Void';
								break;
							}

						default:
							$results[$payment->id] = 'Skipped';
							break;
					}
				}
            } catch (\Exception $e) {
                $results[$paymentData['id']] = 'Error: ' . $e->getMessage();
            }
        }

       $this->output
        ->set_content_type('application/json')
        ->set_output(json_encode([
            'success' => true,
            'message' => implode("\n", $results) // return as plain text
        ]));
    }

	public function bulkChangePaymentAutocountStatusToPending()
	{
		$json = file_get_contents('php://input');
		$data = json_decode($json, true);
		$payment_ids = isset($data['payment_ids']) ? $data['payment_ids'] : [];

		if (empty($payment_ids)) {
			echo json_encode(['success' => false, 'message' => 'No payments selected']);
			return;
		}

		$result = $this->Payment_Model->Update_Payment_Autocount_Status_To_Pending_With_Reset($payment_ids);

		if ($result) {
			echo json_encode(['success' => true, 'message' => count($payment_ids) . ' payment(s) updated to Pending status']);
		} else {
			echo json_encode(['success' => false, 'message' => 'Failed to update payments']);
		}
	}

	public function autocount_create($data = [])
	{
		try {
			// Master (single row only)
			$param = [
				'master' => [
					'docNo'           => arr_get($data, 'ReferenceNumber', ''),
					'docNo2'          => arr_get($data, 'docNo2', ''),
					'docNoFormatName' => arr_get($data, 'docNoFormatName', null),
					'docType'         => 'PV', // required
					'docDate'         => arr_get($data, 'InsertDate', date('Y-m-d')),
					'taxDate'         => arr_get($data, 'tax_date', date('Y-m-d')),
					'currencyCode'    => arr_get($data, 'currency_code', 'MYR'),
					'currencyRate'    => arr_get($data, 'currency_rate', 1),
					'journalType'     => 'GENERAL',
					'dealWith'        => arr_get($data, 'supplier_name', ''),
					'description'     => arr_get($data, 'PaymentRemark', ''),
					'note'            => arr_get($data, 'note', ''),
				],
				'details'        => [],
				'paymentDetails' => [],
				'autoFillOption' => [
					'taxCode'    => arr_get($data, 'tax_code', false),
					'tariffCode' => arr_get($data, 'tariff_code', false),
				],
				'saveApprove' => arr_get($data, 'saveApprove', null),
			];

			// Details (loop through $data['details'])
			if (!empty($data['details']) && is_array($data['details'])) {
				foreach ($data['details'] as $detail) {
					$param['details'][] = [
						'accNo'              => arr_get($detail, 'account_no'),
						'toAccountRate'      => arr_get($detail, 'toAccountRate', 1),
						'description'        => arr_get($detail, 'description', ''),
						'furtherDescription' => arr_get($detail, 'furtherDescription', ''),
						'amount'             => (float)arr_get($detail, 'amount', 0),
						'taxCode'            => arr_get($detail, 'taxCode', ''),
						'taxAdjustment'      => arr_get($detail, 'taxAdjustment', 0),
						'localTaxAdjustment' => arr_get($detail, 'localTaxAdjustment', 0),
						'tariffCode'         => arr_get($detail, 'tariffCode', ''),
						'taxExportCountry'   => arr_get($detail, 'taxExportCountry', ''),
						'taxPermitNo'        => arr_get($detail, 'taxPermitNo', ''),
						'taxBRNo'            => arr_get($detail, 'taxBRNo', ''),
						'taxBName'           => arr_get($detail, 'taxBName', ''),
						'taxRefNo'           => arr_get($detail, 'taxRefNo', ''),
						'taxRegisterNo'      => arr_get($detail, 'taxRegisterNo', ''),
						'taxBillDate'        => arr_get($detail, 'taxBillDate', null),
						'salesAgent'         => arr_get($detail, 'salesAgent', ''),
						'inclusiveTax'       => arr_get($detail, 'inclusiveTax', true),
						'deptNo'             => arr_get($detail, 'deptNo', ''),
					];
				}
			}

			// Payment details (loop through $data['paymentDetails'])
			if (!empty($data['paymentDetails']) && is_array($data['paymentDetails'])) {
				foreach ($data['paymentDetails'] as $payment) {
					$param['paymentDetails'][] = [
						'paymentMethod'      => arr_get($payment, 'paymentMethod','CASH'),
						'paymentBy'          => arr_get($payment, 'paymentBy', ''),
						'chequeNo'           => arr_get($payment, 'chequeNo', ''),
						'floatDay'           => arr_get($payment, 'floatDay', 0),
						'bankCharge'         => (float)arr_get($payment, 'bankCharge', 0),
						'toBankRate'         => arr_get($payment, 'toBankRate', 1),
						'paymentAmt'         => (float)arr_get($payment, 'paymentAmt'),
						'bankChargeTaxCode'  => arr_get($payment, 'bankChargeTaxCode', ''),
						'bankChargeTaxRate'  => arr_get($payment, 'bankChargeTaxRate', 0),
						'bankChargeTax'      => arr_get($payment, 'bankChargeTax', 0),
						'bankChargeTaxRefNo' => arr_get($payment, 'bankChargeTaxRefNo', ''),
					];
				}
			}

			// Send request to AutoCount
			return autocount_request('POST', 'payment.create', $param);

		} catch (Exception $e) {
			log_message('error', 'Autocount payment creation error: ' . $e->getMessage());

			return [
				'status' => 500,
				'error'  => $e->getMessage(),
				'data'   => [],
			];
		}
	}

    public function autocount_update($data = [])
	{
		try {
			// Ensure the docNo is passed (either BookingNumber or DocNo)
			$docNo = $data['ReferenceNumber'] ?? $data['ReferenceNumber'] ?? '';
			if ($docNo === '') {
				return ['error' => 'Missing required parameter: ReferenceNumber (or DocNo).'];
			}

			$body = [];

			// Master data (single row only)
			if (!empty($data['master'])) {
				$body['master'] = [
					'docNo'           => $docNo,                                // Reference Number -> docNo
					'docNo2'          => arr_get($data, 'docNo2', ''),
					'docNoFormatName' => arr_get($data, 'docNoFormatName', null),
					'docType'         => arr_get($data, 'docType', 'PV'),        // Document Type (Payment Voucher)
					'docDate'         => arr_get($data, 'Date', date('Y-m-d')),  // Date -> docDate
					'taxDate'         => arr_get($data, 'tax_date', date('Y-m-d')),
					'currencyCode'    => arr_get($data, 'Currency', 'MYR'),      // Currency -> currencyCode
					'currencyRate'    => arr_get($data, 'ForeignCurrency', '1'),   // Foreign Currency -> currencyRate
					'journalType'     => 'GENERAL',                               // Journal Type
					'dealWith'        => arr_get($data, 'SupplierID', ''),       // Supplier -> dealWith
					'description'     => arr_get($data, 'PaymentRemark', ''),    // Payment Remark -> description
					'note'            => arr_get($data, 'Remark', ''),           // Remark -> note
				];
			}

			// Details (loop through $data['details'])
			if (!empty($data['details']) && is_array($data['details'])) {
				foreach ($data['details'] as $detail) {
					$body['details'][] = [
						'accNo'              => arr_get($detail, 'account_no', ''),        // account_no -> accNo
						'toAccountRate'      => arr_get($detail, 'toAccountRate', 1),       // Default to 1
						'description'        => arr_get($detail, 'description', ''),
						'furtherDescription' => arr_get($detail, 'furtherDescription', ''),
						'amount'             => (float)arr_get($detail, 'Debit', 0),        // Debit -> amount
						'taxCode'            => arr_get($detail, 'taxCode', ''),
						'taxAdjustment'      => arr_get($detail, 'taxAdjustment', 0),
						'localTaxAdjustment' => arr_get($detail, 'localTaxAdjustment', 0),
						'tariffCode'         => arr_get($detail, 'tariffCode', ''),
						'taxExportCountry'   => arr_get($detail, 'taxExportCountry', ''),
						'taxPermitNo'        => arr_get($detail, 'taxPermitNo', ''),
						'taxBRNo'            => arr_get($detail, 'taxBRNo', ''),
						'taxBName'           => arr_get($detail, 'taxBName', ''),
						'taxRefNo'           => arr_get($detail, 'taxRefNo', ''),
						'taxRegisterNo'      => arr_get($detail, 'taxRegisterNo', ''),
						'taxBillDate'        => arr_get($detail, 'taxBillDate', null),
						'salesAgent'         => arr_get($detail, 'salesAgent', ''),
						'inclusiveTax'       => arr_get($detail, 'inclusiveTax', true),
						'deptNo'             => arr_get($detail, 'deptNo', ''),
					];
				}
			}

			// Payment details (loop through $data['paymentDetails'])
			if (!empty($data['paymentDetails']) && is_array($data['paymentDetails'])) {
				foreach ($data['paymentDetails'] as $payment) {
					$body['paymentDetails'][] = [
						'paymentMethod'      => arr_get($payment, 'paymentMethod', 'CASH'),
						'paymentBy'          => arr_get($payment, 'paymentBy', ''),
						'chequeNo'           => arr_get($payment, 'chequeNo', ''),
						'floatDay'           => arr_get($payment, 'floatDay', 0),
						'bankCharge'         => (float)arr_get($payment, 'bankCharge', 0),
						'toBankRate'         => arr_get($payment, 'toBankRate', 1),
						'paymentAmt'         => (float)arr_get($payment, 'paymentAmt', 0),  // Debit -> paymentAmt
						'bankChargeTaxCode'  => arr_get($payment, 'bankChargeTaxCode', ''),
						'bankChargeTaxRate'  => arr_get($payment, 'bankChargeTaxRate', 0),
						'bankChargeTax'      => arr_get($payment, 'bankChargeTax', 0),
						'bankChargeTaxRefNo' => arr_get($payment, 'bankChargeTaxRefNo', ''),
					];
				}
			}

			// AutoFill Options (tax code, etc.)
			if (!empty($data['tax_code'])) {
				$body['autoFillOption'] = [
					'TaxCode' => arr_get($payment, 'TaxCode', false),
				];
			}

			// Save Approval (if present)
			if (isset($data['saveApprove'])) {
				$body['saveApprove'] = arr_get($payment, 'saveApprove', false);
			}

			// Send request to AutoCount for updating the payment
			return autocount_request(
				'PUT',
				'payment.update',
				$body,
				['docNo' => $docNo]
			);
		} catch (Exception $e) {
			log_message('error', 'Autocount payment update error: ' . $e->getMessage());

			return [
				'status' => 500,
				'error'  => $e->getMessage(),
				'data'   => [],
			];
		}
	}

    public function autocount_delete($data = [])
    {
        $docNo = isset($data['ReferenceNumber']) ? $data['ReferenceNumber'] : '';

        return autocount_request(
            'DELETE',
            'payment.delete',
            [],
            ['docNo' => $docNo]
        );
    }

    public function autocount_void($data = [])
    {
        $docNo = isset($data['ReferenceNumber']) ? $data['ReferenceNumber'] : '';
        $body = [
            'voidReason' => isset($data['reason']) ? $data['reason'] : ''
        ];

        return autocount_request(
            'POST',
            'payment.void',
            $body,
            ['docNo' => $docNo]
        );
        
    }    
}