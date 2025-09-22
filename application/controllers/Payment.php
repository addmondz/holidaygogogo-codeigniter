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
	}

	function index()
	{
		if(in_array('VP', $this->session->access_control)) {
			$titles = array('tab_title' => 'HolidayGoGoGo | Payment', 'breadcrumb_title' => 'Payment');
			$array = array('total_supplier_payment' => 0, 'total_customer_refund' => 0, 'total_credit' => 0, 'total_debit' => 0, 'total_net_profit' => 0, 'booking_subtotal' => 0, 'outstanding_balance_by_customer' => 0, 'booking_id' => 'NA', 'token' => 'NA');
			$array['payments'] = $this->Payment_Model->Read_Payments1();
			$array['admins'] = $this->Payment_Model->Read_Admins();
			$array['suppliers'] = $this->Payment_Model->Read_Suppliers();
			$array['supplier_payments'] = [];
			$array['customer_refunds'] = [];
			$array['supplier_ids'] = [];
			$customer_refund_ids = [];
			$total_supplier_payment = 0;
			$total_customer_refund = 0;
			$total_credit = 0;
			$total_debit = 0;
			$total_net_profit = 0;
			$booking_ids = [];
			$total_sales = 0;
			if(!empty($array['payments'])) {
				foreach($array['payments'] as $payment) {
					if(!empty($payment->Date)) {
						$payment->Date = strtoupper(date('j M Y', strtotime($payment->Date)));
					}
					if(!empty($payment->Deadline)) {
						$payment->Deadline = strtoupper(date('j M Y', strtotime($payment->Deadline)));
					}
					if(!empty($payment->StartDate)) {
						$payment->StartDate = strtoupper(date('j M Y', strtotime($payment->StartDate)));
					} else {
						$payment->StartDate = null;
					}
					if(!empty($payment->EndDate)) {
						$payment->EndDate = strtoupper(date('j M Y', strtotime($payment->EndDate)));
					} else {
						$payment->EndDate = null;
					}
					$payment->TotalCredit = number_format($this->Calculate_Total_Credit($payment->BookingID), 2, '.', ',');
					if($payment->Type == 'SUPPLIER PAYMENT') {
						$total_supplier_payment += $payment->Debit;
						if(!in_array($payment->SupplierID, $array['supplier_ids'])) {
							array_push($array['supplier_ids'], $payment->SupplierID);
							$array['supplier_payments'][$payment->Supplier] = $payment->Debit;
						} else {
							$array['supplier_payments'][$payment->Supplier] += $payment->Debit;
						}
					}
					if($payment->Type == 'CUSTOMER REFUND') {
						$total_customer_refund += $payment->Debit;
						if(!in_array($payment->PaymentID, $customer_refund_ids)) {
							array_push($customer_refund_ids, $payment->PaymentID);
							$array['customer_refunds'][$payment->BankHolder] = $payment->Debit;
						} else {
							$array['customer_refunds'][$payment->BankHolder] += $payment->Debit;
						}
					}
					if($array['booking_subtotal'] == 0 && $array['outstanding_balance_by_customer'] == 0) {
						$array['booking_subtotal'] = number_format($payment->NetTotal, 2, '.', ',');
						$array['outstanding_balance_by_customer'] = number_format(($payment->NetTotal - str_replace(',', '', $payment->TotalCredit)), 2, '.', ',');
					}
					$total_credit += $payment->Credit;
					$total_debit += $payment->Debit;
					$total_net_profit += $payment->Credit - $payment->Debit;
					if(!in_array($payment->BookingID, $booking_ids)) {
						array_push($booking_ids, $payment->BookingID);
						$total_sales += $payment->NetTotal;
					}
					$payment->Credit = $payment->Credit == 0.00 ? '' : number_format($payment->Credit, 2, '.', ',');
					$payment->Debit = $payment->Credit == 0.00 ? number_format($payment->Debit, 2, '.', ',') : '';
				}
			} else {
				if(!empty($this->input->get('booking_number'))) {
					$array['booking_id'] = !empty($this->Booking_Model->Read_Booking_ID()) ? ($this->Booking_Model->Read_Booking_ID())['BookingID'] : 'NA';
					$array['token'] = !empty($this->Booking_Model->Read_Token()) ? ($this->Booking_Model->Read_Token())['Token'] : 'NA';
					$array['booking_subtotal'] = !empty($this->Booking_Model->Read_Net_Total()) ? number_format(($this->Booking_Model->Read_Net_Total())['NetTotal'], 2, '.', ',') : number_format(0, 2, '.', ',');
					$array['outstanding_balance_by_customer'] = !empty($this->Booking_Model->Read_Net_Total()) ? number_format(($this->Booking_Model->Read_Net_Total())['NetTotal'], 2, '.', ',') : number_format(0, 2, '.', ',');
				}
			}
			$array['total_supplier_payment'] = 'RM ' . number_format($total_supplier_payment, 2, '.', ',');
			$array['total_customer_refund'] = 'RM ' . number_format($total_customer_refund, 2, '.', ',');
			$array['total_credit'] = number_format($total_credit, 2, '.', ',');
			$array['total_debit'] = number_format($total_debit, 2, '.', ',');
			$array['total_net_profit'] = $total_net_profit != 0 && $total_sales != 0 ? number_format($total_net_profit, 2, '.', ',') . ' (' . round(($total_net_profit / $total_sales) * 100) . '%)' : number_format($total_net_profit, 2, '.', ',') . ' (0%)';
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
						$payment_ids[] = $payment_id;
					}
				}

				if (!empty($payment_ids))
				{
					foreach($payment_ids as $payment_id) {
						$payment = Payment::find($payment_id);
						if ($payment != null) {
							$quotationData = [];
							$respond = $this->autocount_create($quotationData);

							$payment->AutocountSyncStatus = 'C';
							$payment->AutocountSyncMessage = $respond;
							$payment->update();
						}	
					}
				}
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

						if ($payment != null) {	
							$payment2 = Payment::find($payment->PaymentID);
							if ($payment2 != null) {
								$quotationData = [];
								$respond = $this->autocount_update($quotationData);

								$payment2->AutocountSyncStatus = 'U';
								$payment2->AutocountSyncMessage = $respond;
								$payment2->update();
							}	
						}
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

			
			$paymentData = $this->Payment_Model->getPaymentById($this->input->get('payment_id'));
        	$paymentNumber = (!empty($paymentData) && !empty($paymentData->ReferenceNumber)) ? $paymentData->ReferenceNumber : '';

			if (!empty($paymentNumber)) {
				$payment = Payment::find($this->input->get('payment_id'));
				if ($payment != null) {
					$quotationData = [];
					$respond = $this->autocount_delete($quotationData);

					$payment->AutocountSyncStatus = 'D';
					$payment->AutocountSyncMessage = $respond;
					$payment->update();
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
        $payment_ids = $this->input->post('payment_id'); 

        if (empty($payment_ids)) {
            return response()->json(['message' => 'No payment selected'], 400);
        }

        $results = [];

        foreach ($payment_ids as $payment_id) {
            try {
                $payment = Payment::find($payment_id);
                if (!$payment) {
                    $results[$payment_id] = 'Not Found';
                    continue;
                }

				$paymentData = $this->Payment_Model->getAllBookingsWithGuests($payment_id);
				if (!empty($paymentData)) {
					$paymentData = (array) $paymentData[0]; 
				}

			
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
							'booking_product' => $bookingProducts
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
							'booking_product' => $bookingProducts,
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

                $payment->save();
            } catch (\Exception $e) {
                $results[$paymentData['id']] = 'Error: ' . $e->getMessage();
            }
        }

        return response()->json([
            'message' => 'Sync process completed',
            'results' => $results,
        ]);
    }

	public function autocount_create($data = [])
	{
		// Master (single row only)
		$param = [
			'master' => [
				'docNo'           => $data['ReferenceNumber'],
				'docNo2'          => '',
				'docNoFormatName' => null,
				'docType'         => 'PV', // required
				'docDate'         => date('Y-m-d', strtotime($data['InsertDate'])) ?? date('Y-m-d'), // required
				'taxDate'         => $data['tax_date'] ?? '',
				'currencyCode'    => $data['currency_code'] ?? 'MYR', // required
				'currencyRate'    => $data['currency_rate'] ?? '1', // required
				'journalType'     => 'GENERAL', // required
				'dealWith'        => $data['supplier_name'] ?? '', // required
				'description'     => $data['PaymentRemark'] ?? '',
				'note'            => ''
			],
			'details'        => [],
			'paymentDetails' => [],
			'autoFillOption' => [
				'taxCode'    => $data['tax_code'] ?? '',
				'tariffCode' => $data['tariff_code'] ?? ''
			],
			'saveApprove' => null
		];

		// Details (loop through $data['details'])
		if (!empty($data['details']) && is_array($data['details'])) {
			foreach ($data['details'] as $detail) {
				$param['details'][] = [
					'accNo'              => $detail['account_no'], // required
					'toAccountRate'      => $detail['toAccountRate'] ?? 1,
					'description'        => $detail['description'] ?? '',
					'furtherDescription' => $detail['furtherDescription'] ?? '',
					'amount'             => (float)$detail['amount'], // required
					'taxCode'            => $detail['taxCode'] ?? '',
					'taxAdjustment'      => $detail['taxAdjustment'] ?? 0,
					'localTaxAdjustment' => $detail['localTaxAdjustment'] ?? 0,
					'tariffCode'         => $detail['tariffCode'] ?? '',
					'taxExportCountry'   => $detail['taxExportCountry'] ?? '',
					'taxPermitNo'        => $detail['taxPermitNo'] ?? '',
					'taxBRNo'            => $detail['taxBRNo'] ?? '',
					'taxBName'           => $detail['taxBName'] ?? '',
					'taxRefNo'           => $detail['taxRefNo'] ?? '',
					'taxRegisterNo'      => $detail['taxRegisterNo'] ?? '',
					'taxBillDate'        => $detail['taxBillDate'] ?? null,
					'salesAgent'         => $detail['salesAgent'] ?? '',
					'inclusiveTax'       => $detail['inclusiveTax'] ?? true,
					'deptNo'             => $detail['deptNo'] ?? ''
				];
			}
		}

		// Payment details (loop through $data['paymentDetails'])
		if (!empty($data['paymentDetails']) && is_array($data['paymentDetails'])) {
			foreach ($data['paymentDetails'] as $payment) {
				$param['paymentDetails'][] = [
					'paymentMethod'      => $payment['paymentMethod'],
					'paymentBy'          => $payment['paymentBy'] ?? '',
					'chequeNo'           => $payment['chequeNo'] ?? '',
					'floatDay'           => $payment['floatDay'] ?? 0,
					'bankCharge'         => (float)$payment['bankCharge'] ?? 0,
					'toBankRate'         => $payment['toBankRate'] ?? 1,
					'paymentAmt'         => (float)$payment['paymentAmt'],
					'bankChargeTaxCode'  => $payment['bankChargeTaxCode'] ?? '',
					'bankChargeTaxRate'  => $payment['bankChargeTaxRate'] ?? 0,
					'bankChargeTax'      => $payment['bankChargeTax'] ?? 0,
					'bankChargeTaxRefNo' => $payment['bankChargeTaxRefNo'] ?? ''
				];
			}
		}

		return autocount_request('POST', 'payment.create', $param);
	}


    public function autocount_update($data = [])
    {
        $docNo = $data['BookingNumber'] ?? $data['DocNo'] ?? '';
        if ($docNo === '') {
            return ['error' => 'Missing required parameter: BookingNumber (or DocNo).'];
        }

        $body = [];

        if (!empty($data['master'])) {
            $body['master'] = $data['master'];
        }

       // Details (loop through $data['details'])
		if (!empty($data['details']) && is_array($data['details'])) {
			foreach ($data['details'] as $detail) {
				$param['details'][] = [
					'accNo'              => $detail['account_no'], // required
					'toAccountRate'      => $detail['toAccountRate'] ?? 1,
					'description'        => $detail['description'] ?? '',
					'furtherDescription' => $detail['furtherDescription'] ?? '',
					'amount'             => (float)$detail['amount'], // required
					'taxCode'            => $detail['taxCode'] ?? '',
					'taxAdjustment'      => $detail['taxAdjustment'] ?? 0,
					'localTaxAdjustment' => $detail['localTaxAdjustment'] ?? 0,
					'tariffCode'         => $detail['tariffCode'] ?? '',
					'taxExportCountry'   => $detail['taxExportCountry'] ?? '',
					'taxPermitNo'        => $detail['taxPermitNo'] ?? '',
					'taxBRNo'            => $detail['taxBRNo'] ?? '',
					'taxBName'           => $detail['taxBName'] ?? '',
					'taxRefNo'           => $detail['taxRefNo'] ?? '',
					'taxRegisterNo'      => $detail['taxRegisterNo'] ?? '',
					'taxBillDate'        => $detail['taxBillDate'] ?? null,
					'salesAgent'         => $detail['salesAgent'] ?? '',
					'inclusiveTax'       => $detail['inclusiveTax'] ?? true,
					'deptNo'             => $detail['deptNo'] ?? ''
				];
			}
		}

		// Payment details (loop through $data['paymentDetails'])
		if (!empty($data['paymentDetails']) && is_array($data['paymentDetails'])) {
			foreach ($data['paymentDetails'] as $payment) {
				$param['paymentDetails'][] = [
					'paymentMethod'      => $payment['paymentMethod'],
					'paymentBy'          => $payment['paymentBy'] ?? '',
					'chequeNo'           => $payment['chequeNo'] ?? '',
					'floatDay'           => $payment['floatDay'] ?? 0,
					'bankCharge'         => (float)$payment['bankCharge'] ?? 0,
					'toBankRate'         => $payment['toBankRate'] ?? 1,
					'paymentAmt'         => (float)$payment['paymentAmt'],
					'bankChargeTaxCode'  => $payment['bankChargeTaxCode'] ?? '',
					'bankChargeTaxRate'  => $payment['bankChargeTaxRate'] ?? 0,
					'bankChargeTax'      => $payment['bankChargeTax'] ?? 0,
					'bankChargeTaxRefNo' => $payment['bankChargeTaxRefNo'] ?? ''
				];
			}
		}

        if (!empty($data['tax_code'])) {
            $body['autoFillOption'] = [
                'TaxCode' => $data['tax_code']
            ];
        }

        if (isset($data['saveApprove'])) {
            $body['saveApprove'] = $data['saveApprove'];
        }

        return autocount_request(
            'PUT',
            'payment.update',
            $body,
            ['docNo' => $docNo]
        );
    }

    // public function autocount_update_status($data = [])
    // {
    //     $docNo = $data['BookingNumber'] ?? '';
    //     $body = [
    //         'documentStatus' => $data['Status'] ?? '',
    //         'lostReason'     => $data['reason'] ?? ''
    //     ];

    //     return autocount_request(
    //         'PUT',
    //         'payment.update_status',
    //         $body,
    //         ['docNo' => $docNo]
    //     );
    // }

    public function autocount_delete($data = [])
    {
        $docNo = $data['BookingNumber'] ?? '';

        return autocount_request(
            'DELETE',
            'payment.delete',
            [],
            ['docNo' => $docNo]
        );
    }

    public function autocount_void($data = [])
    {
        $docNo = $data['DocNo'] ?? '';
        $body = [
            'voidReason' => $data['reason'] ?? ''
        ];

        return autocount_request(
            'POST',
            'payment.void',
            $body,
            ['docNo' => $docNo]
        );
        
    }    
}