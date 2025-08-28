<?php
class Payment_Model extends CI_Model
{
	function Read_Payment()
	{
		$this->db->select('PaymentID, payment.BookingID, payment.SupplierID, Date, Type, Currency, ForeignCurrency, Credit, ReferenceNumber, BankSlip, Debit, Deadline, QuotationNumber, Quotation, InvoiceNumber, Invoice, payment.Bank, payment.BankAccount, payment.BankHolder, DebitRemark, PaymentRemark, payment.Status, Remark, BookingNumber, ReservationNumber, Customer');
		$this->db->join('booking', 'booking.BookingID = payment.BookingID', 'left');
		$this->db->where('PaymentID', $this->input->get('payment_id'));
		return $this->db->get('payment')->row_array();
	}
	
	function Read_Payments1()
	{
		$this->db->select('PaymentID, payment.BookingID, payment.SupplierID, Date, Type, Credit, ReferenceNumber, Debit, Deadline, payment.BankHolder, payment.Status, BookingNumber, ReservationNumber, Customer, StartDate, EndDate, NetTotal, Token, admin.Name As SalesAgent, supplier.Name As Supplier');
		$this->db->join('payment', 'payment.BookingID = booking.BookingID', 'left');
		$this->db->join('admin', 'admin.AdminID = booking.SalesAgent', 'left');
		$this->db->join('supplier', 'supplier.SupplierID = payment.SupplierID', 'left');
		if($this->session->userdata('level') == 20) {
			$this->db->where('SalesAgent', $this->session->userdata('admin_id'));
		}
		if(!empty($this->input->get('supplier'))) {
			$this->db->where('payment.SupplierID', $this->input->get('supplier'));
		}
		if(!empty($this->input->get('transaction_date'))) {
			$transaction_date = explode(' - ', $this->input->get('transaction_date'));
			$start_date = date('Y-m-d', strtotime(str_replace('/', '-', $transaction_date[0])));
			$end_date = date('Y-m-d', strtotime(str_replace('/', '-', $transaction_date[1])));
			$this->db->where('Date >=', $start_date);
			$this->db->where('Date <=', $end_date);
		}
		if(!empty($this->input->get('payment_type'))) {
			$this->db->where('Type', $this->input->get('payment_type'));
		}
		if(!empty($this->input->get('transaction_type'))) {
			if($this->input->get('transaction_type') == 'PAYMENT IN') {
				$this->db->where('Credit !=', 0.00);
			} else {
				$this->db->where('Credit', 0.00);
			}
		}
		if(!empty($this->input->get('reference_number'))) {
			$this->db->where('ReferenceNumber', $this->input->get('reference_number'));
		}
		if(!empty($this->input->get('payment_deadline'))) {
			$payment_deadline = explode(' - ', $this->input->get('payment_deadline'));
			$start_date = date('Y-m-d', strtotime(str_replace('/', '-', $payment_deadline[0])));
			$end_date = date('Y-m-d', strtotime(str_replace('/', '-', $payment_deadline[1])));
			$this->db->where('Deadline >=', $start_date);
			$this->db->where('Deadline <=', $end_date);
		}
		if(!empty($this->input->get('quotation_number'))) {
			$this->db->where('QuotationNumber', $this->input->get('quotation_number'));
		}
		if(!empty($this->input->get('invoice_number'))) {
			$this->db->where('InvoiceNumber', $this->input->get('invoice_number'));
		}
		if(!empty($this->input->get('bank'))) {
			$this->db->where('payment.Bank', $this->input->get('bank'));
		}
		if(!empty($this->input->get('bank_account'))) {
			$this->db->where('payment.BankAccount', $this->input->get('bank_account'));
		}
		if(!empty($this->input->get('bank_holder'))) {
			$this->db->where('payment.BankHolder', $this->input->get('bank_holder'));
		}
		if(!empty($this->input->get('status'))) {
			$this->db->where('payment.Status', $this->input->get('status'));
		} else {
			if(strpos($_SERVER['REQUEST_URI'], '?') == false) {
				$this->db->where('payment.Status', 'P');
			}
		}
		if(!empty($this->input->get('booking_number'))) {
			$this->db->where('BookingNumber', $this->input->get('booking_number'));
		}
		if(!empty($this->input->get('customer'))) {
			$this->db->where('Customer', $this->input->get('customer'));
		}
		if(!empty($this->input->get('travel_date'))) {
			$travel_date = explode(' - ', $this->input->get('travel_date'));
			$start_date = date('Y-m-d', strtotime(str_replace('/', '-', $travel_date[0])));
			$end_date = date('Y-m-d', strtotime(str_replace('/', '-', $travel_date[1])));
			$this->db->where("((`StartDate` <= '".$start_date."' AND `EndDate` >= '".$end_date."') OR (`StartDate` >= '".$start_date."' AND `StartDate` <= '".$end_date."') OR (`EndDate` >= '".$start_date."' AND `EndDate` <= '".$end_date."'))");
		}
		if(!empty($this->input->get('sales_agent'))) {
			$this->db->where('SalesAgent', $this->input->get('sales_agent'));
		}
		$this->db->where('payment.Status !=', 'N');
		$this->db->order_by('Date DESC');
		return $this->db->get('booking')->result();
	}

	function Read_Payments2()
	{
		$this->db->select('Date, Type, ForeignCurrency, Credit, ReferenceNumber, Debit, Deadline, QuotationNumber, InvoiceNumber, payment.Bank, payment.BankAccount, payment.BankHolder, PaymentRemark, BookingNumber, ReservationNumber, Customer, StartDate, EndDate, admin.Name As SalesAgent, category.Name As Destination, supplier.Name As Supplier');
		$this->db->join('payment', 'payment.BookingID = booking.BookingID', 'left');
		$this->db->join('admin', 'admin.AdminID = booking.SalesAgent', 'left');
		$this->db->join('category', 'category.CategoryID = booking.Destination', 'left');
		$this->db->join('supplier', 'supplier.SupplierID = payment.SupplierID', 'left');
		if($this->session->userdata('level') == 20) {
			$this->db->where('SalesAgent', $this->session->userdata('admin_id'));
		}
		if(!empty($this->input->get('supplier'))) {
			$this->db->where('payment.SupplierID', $this->input->get('supplier'));
		}
		if(!empty($this->input->get('transaction_date'))) {
			$transaction_date = explode(' - ', $this->input->get('transaction_date'));
			$start_date = date('Y-m-d', strtotime(str_replace('/', '-', $transaction_date[0])));
			$end_date = date('Y-m-d', strtotime(str_replace('/', '-', $transaction_date[1])));
			$this->db->where('Date >=', $start_date);
			$this->db->where('Date <=', $end_date);
		}
		if(!empty($this->input->get('payment_type'))) {
			$this->db->where('Type', $this->input->get('payment_type'));
		}
		if(!empty($this->input->get('transaction_type'))) {
			if($this->input->get('transaction_type') == 'PAYMENT IN') {
				$this->db->where('Credit !=', 0.00);
			} else {
				$this->db->where('Credit', 0.00);
			}
		}
		if(!empty($this->input->get('reference_number'))) {
			$this->db->where('ReferenceNumber', $this->input->get('reference_number'));
		}
		if(!empty($this->input->get('payment_deadline'))) {
			$payment_deadline = explode(' - ', $this->input->get('payment_deadline'));
			$start_date = date('Y-m-d', strtotime(str_replace('/', '-', $payment_deadline[0])));
			$end_date = date('Y-m-d', strtotime(str_replace('/', '-', $payment_deadline[1])));
			$this->db->where('Deadline >=', $start_date);
			$this->db->where('Deadline <=', $end_date);
		}
		if(!empty($this->input->get('quotation_number'))) {
			$this->db->where('QuotationNumber', $this->input->get('quotation_number'));
		}
		if(!empty($this->input->get('invoice_number'))) {
			$this->db->where('InvoiceNumber', $this->input->get('invoice_number'));
		}
		if(!empty($this->input->get('bank'))) {
			$this->db->where('payment.Bank', $this->input->get('bank'));
		}
		if(!empty($this->input->get('bank_account'))) {
			$this->db->where('payment.BankAccount', $this->input->get('bank_account'));
		}
		if(!empty($this->input->get('bank_holder'))) {
			$this->db->where('payment.BankHolder', $this->input->get('bank_holder'));
		}
		if(!empty($this->input->get('status'))) {
			$this->db->where('payment.Status', $this->input->get('status'));
		} else {
			if(strpos($_SERVER['REQUEST_URI'], '?') == false) {
				$this->db->where('payment.Status', 'P');
			}
		}
		if(!empty($this->input->get('booking_number'))) {
			$this->db->where('BookingNumber', $this->input->get('booking_number'));
		}
		if(!empty($this->input->get('customer'))) {
			$this->db->where('Customer', $this->input->get('customer'));
		}
		if(!empty($this->input->get('travel_date'))) {
			$travel_date = explode(' - ', $this->input->get('travel_date'));
			$start_date = date('Y-m-d', strtotime(str_replace('/', '-', $travel_date[0])));
			$end_date = date('Y-m-d', strtotime(str_replace('/', '-', $travel_date[1])));
			$this->db->where("((`StartDate` <= '".$start_date."' AND `EndDate` >= '".$end_date."') OR (`StartDate` >= '".$start_date."' AND `StartDate` <= '".$end_date."') OR (`EndDate` >= '".$start_date."' AND `EndDate` <= '".$end_date."'))");
		}
		if(!empty($this->input->get('sales_agent'))) {
			$this->db->where('SalesAgent', $this->input->get('sales_agent'));
		}
		$this->db->where('payment.Status !=', 'N');
		$this->db->order_by('Date DESC');
		return $this->db->get('booking')->result();
	}

	function Read_BC_Payments()
	{
		$this->db->select('Date, Type, Credit, ReferenceNumber, Debit, Deadline, QuotationNumber, InvoiceNumber, payment.Bank, payment.BankAccount, payment.BankHolder, DebitRemark, PaymentRemark, payment.Status, Remark, supplier.Name As Supplier');
		$this->db->join('supplier', 'supplier.SupplierID = payment.SupplierID', 'left');
		$this->db->where('payment.BookingID', $this->input->get('booking_id'));
		$this->db->where('payment.Status !=', 'N');
		return $this->db->get('payment')->result();
	}

	function Read_Booking()
	{
		$this->db->select('ReservationNumber, DepositDeadline, FullPaymentDeadline, AdditionalPaymentDeadline, StartDate, EndDate, BookingRemark, NetTotal, Token, LockStatus, AfterSalesService, booking.Status, admin.Name As SalesAgentName, category.Name As DestinationName');
		$this->db->join('admin', 'admin.AdminID = booking.SalesAgent', 'left');
		$this->db->join('category', 'category.CategoryID = booking.Destination', 'left');
		$this->db->where('booking.BookingID', $this->input->get('booking_id'));
		return $this->db->get('booking')->row_array();
	}

	function Read_Bookings()
	{
		$this->db->select('booking.BookingID, BookingNumber, Customer');
		if($this->session->userdata('level') == 20) {
			$this->db->where('SalesAgent', $this->session->userdata('admin_id'));
			$this->db->where('AfterSalesService', 'PENDING');
		}
		$this->db->where('CancelStatus', 'N');
		$this->db->where('booking.Status !=', 'N');
		$this->db->order_by('booking.BookingID', 'DESC');
		return $this->db->get('booking')->result();
	}

	function Read_Admins()
	{
		$this->db->select('AdminID, Name');
		$this->db->where('AdminID !=', 8);
		$this->db->where('Level !=', '30');
		$this->db->where('Status', 'Y');
		$this->db->order_by('Name', 'ASC');
		return $this->db->get('admin')->result();
	}

	function Read_Suppliers()
	{
		$this->db->select('SupplierID, Name');
		$this->db->where('Status', 'Y');
		$this->db->order_by('Name', 'ASC');
		return $this->db->get('supplier')->result();
	}

	function Read_Country_Codes()
	{
		$this->db->select('CountryCodeID, Country, CurrencyCode');
		$this->db->where('Status', 'Y');
		$this->db->order_by('Country', 'ASC');
		return $this->db->get('country_code')->result();
	}

	function Read_Received_Payments($booking_id) {
		$this->db->select('Type, Credit, Debit');
		$this->db->where('BookingID', $booking_id);
		$this->db->where_in('Type', array('ADDITIONAL PAYMENT', 'DEPOSIT', 'FULL', 'CUSTOMER REFUND'));
		$this->db->where('Status', 'Y');
		return $this->db->get('payment')->result();
	}
	
	function Read_Date($payment_id) {
		$this->db->select('Date');
		$this->db->where('PaymentID', $payment_id);
		return $this->db->get('payment')->row()->Date;
	}

	function Read_Type($booking_id) {
		$this->db->where('BookingID', $booking_id);
        $this->db->where('Type', 'FULL');
		$this->db->where('Status', 'Y');
		if($this->db->get('payment')->row()) {
			return true;
		} else {
			return false;
		}
	}

	function Read_Reference_Number($payment_id) { 
		$this->db->select('ReferenceNumber');
		$this->db->where('PaymentID', $payment_id);
		return $this->db->get('payment')->row()->ReferenceNumber;
	}

	function Read_Deadline($payment_id) {
		$this->db->select('Deadline');
		$this->db->where('PaymentID', $payment_id);
		return $this->db->get('payment')->row()->Deadline;
	}

	function Read_Status($payment_id) {
		$this->db->select('Status');
		$this->db->where('PaymentID', $payment_id);
		return $this->db->get('payment')->row()->Status;
	}
	
	function Create($count)
	{
		$array = array(
			'BookingID' => $this->input->post('booking'),
			'SupplierID' => $this->input->post('debit_type-' . $count) == 'SUPPLIER PAYMENT' ? $this->input->post('supplier-' . $count) : null,
			'Date' => $this->input->post('credit_type-' . $count) == 'DEPOSIT' || $this->input->post('credit_type-' . $count) == 'FULL' || $this->input->post('credit_type-' . $count) == 'SUPPLIER REFUND' || $this->input->post('credit_type-' . $count) == 'ADDITIONAL PAYMENT' ? date('Y-m-d', strtotime(str_replace('/', '-', $this->input->post('transaction_date-' . $count)))) : null,
			'Type' => $this->input->post('credit_type-' . $count) == 'DEPOSIT' || $this->input->post('credit_type-' . $count) == 'FULL' || $this->input->post('credit_type-' . $count) == 'SUPPLIER REFUND' || $this->input->post('credit_type-' . $count) == 'ADDITIONAL PAYMENT' ? $this->input->post('credit_type-' . $count) : $this->input->post('debit_type-' . $count),
			'Credit' => $this->input->post('credit_type-' . $count) == 'DEPOSIT' || $this->input->post('credit_type-' . $count) == 'FULL' || $this->input->post('credit_type-' . $count) == 'SUPPLIER REFUND' || $this->input->post('credit_type-' . $count) == 'ADDITIONAL PAYMENT' ? str_replace(',', '', $this->input->post('credit-' . $count)) : 0.00,
			'ReferenceNumber' => !empty($this->input->post('reference_number-' . $count)) ? strtoupper($this->input->post('reference_number-' . $count)) : null,
			'Debit' => ($this->input->post('debit_type-' . $count) == 'SUPPLIER PAYMENT' || $this->input->post('debit_type-' . $count) == 'CUSTOMER REFUND' || $this->input->post('debit_type-' . $count) == 'ONE-TIME PAYMENT' || $this->input->post('debit_type-' . $count) == 'AGENT COMMISSION' || $this->input->post('debit_type-' . $count) == 'BANK CHARGES') && !empty($this->input->post('debit-' . $count)) ? str_replace(',', '', $this->input->post('debit-' . $count)) : 0.00,
			'Deadline' => $this->input->post('debit_type-' . $count) == 'SUPPLIER PAYMENT' || $this->input->post('debit_type-' . $count) == 'CUSTOMER REFUND' || $this->input->post('debit_type-' . $count) == 'ONE-TIME PAYMENT' || $this->input->post('debit_type-' . $count) == 'AGENT COMMISSION' || $this->input->post('debit_type-' . $count) == 'BANK CHARGES' ? date('Y-m-d', strtotime(str_replace('/', '-', $this->input->post('payment_deadline-' . $count)))) : null,
			'QuotationNumber' => $this->input->post('debit_type-' . $count) == 'SUPPLIER PAYMENT' && !empty($this->input->post('quotation_number-' . $count)) ? strtoupper($this->input->post('quotation_number-' . $count)) : null,
			'InvoiceNumber' => $this->input->post('debit_type-' . $count) == 'SUPPLIER PAYMENT' && !empty($this->input->post('invoice_number-' . $count)) ? strtoupper($this->input->post('invoice_number-' . $count)) : null,
			'Currency' => ($this->input->post('debit_type-' . $count) == 'SUPPLIER PAYMENT' || $this->input->post('debit_type-' . $count) == 'CUSTOMER REFUND' || $this->input->post('debit_type-' . $count) == 'ONE-TIME PAYMENT' || $this->input->post('debit_type-' . $count) == 'AGENT COMMISSION' || $this->input->post('debit_type-' . $count) == 'BANK CHARGES') && !empty($this->input->post('currency_code-' . $count)) ? $this->input->post('currency_code-' . $count) : null,
			'ForeignCurrency' => ($this->input->post('debit_type-' . $count) == 'SUPPLIER PAYMENT' || $this->input->post('debit_type-' . $count) == 'CUSTOMER REFUND' || $this->input->post('debit_type-' . $count) == 'ONE-TIME PAYMENT' || $this->input->post('debit_type-' . $count) == 'AGENT COMMISSION' || $this->input->post('debit_type-' . $count) == 'BANK CHARGES') && !empty($this->input->post('foreign_currency-' . $count)) ? str_replace(',', '', $this->input->post('foreign_currency-' . $count)) : 0.00,
			'Bank' => $this->input->post('debit_type-' . $count) == 'CUSTOMER REFUND' || $this->input->post('debit_type-' . $count) == 'ONE-TIME PAYMENT' || $this->input->post('debit_type-' . $count) == 'AGENT COMMISSION' || $this->input->post('debit_type-' . $count) == 'BANK CHARGES' ? strtoupper($this->input->post('bank-' . $count)) : null,
			'BankAccount' => $this->input->post('debit_type-' . $count) == 'CUSTOMER REFUND' || $this->input->post('debit_type-' . $count) == 'ONE-TIME PAYMENT' || $this->input->post('debit_type-' . $count) == 'AGENT COMMISSION' || $this->input->post('debit_type-' . $count) == 'BANK CHARGES' ? strtoupper($this->input->post('bank_account-' . $count)) : null,
			'BankHolder' => $this->input->post('debit_type-' . $count) == 'CUSTOMER REFUND' || $this->input->post('debit_type-' . $count) == 'ONE-TIME PAYMENT' || $this->input->post('debit_type-' . $count) == 'AGENT COMMISSION' || $this->input->post('debit_type-' . $count) == 'BANK CHARGES' ? strtoupper($this->input->post('bank_holder-' . $count)) : null,
			'DebitRemark' => ($this->input->post('debit_type-' . $count) == 'CUSTOMER REFUND' || $this->input->post('debit_type-' . $count) == 'ONE-TIME PAYMENT' || $this->input->post('debit_type-' . $count) == 'AGENT COMMISSION' || $this->input->post('debit_type-' . $count) == 'BANK CHARGES') && !empty($this->input->post('remark-' . $count)) ? strtoupper($this->input->post('remark-' . $count)) : null,
			'PaymentRemark' => !empty($this->input->post('payment_remark-' . $count)) ? strtoupper($this->input->post('payment_remark-' . $count)) : null,
			'InsertBy' => $this->session->userdata('admin_id'),
			'InsertDate' => date('Y-m-d H:i:s')
		);
		$this->db->insert('payment', $array);
		$payment_id = $this->db->insert_id();

		$this->db->select('Date, Type, Credit, ReferenceNumber, Debit, Deadline, QuotationNumber, InvoiceNumber, payment.Bank, payment.BankAccount, payment.BankHolder, DebitRemark, BookingNumber, StartDate, EndDate, Subtotal, category.Name As Destination, admin.Name As SalesAgent, supplier.Name As Supplier');
		$this->db->join('booking', 'booking.BookingID = payment.BookingID', 'left');
		$this->db->join('admin', 'admin.AdminID = booking.SalesAgent', 'left');
		$this->db->join('category', 'category.CategoryID = booking.Destination', 'left');
		$this->db->join('supplier', 'supplier.SupplierID = payment.SupplierID', 'left');
		$this->db->where('PaymentID', $payment_id);
		$payment = $this->db->get('payment')->row_array();
		if(!empty($payment['StartDate']) && !empty($payment['EndDate'])) {
			$payment['TravelDate'] = strtoupper(date('j M', strtotime($payment['StartDate'])) . ' - ' . date('j M Y', strtotime($payment['EndDate'])));
		} else {
			$payment['TravelDate'] = '-';
		}
		$payment['Date'] = empty($payment['Date']) ? '-' : strtoupper(date('j M Y', strtotime($payment['Date'])));
		$payment['ReferenceNumber'] = empty($payment['ReferenceNumber']) ? '-' : $payment['ReferenceNumber'];
		if($payment['Credit'] != 0.00) {
			$payment['Credit'] = 'RM ' . number_format($payment['Credit'], 2, '.', ',');
			$text = urlencode('New Payment Record Successfully Created' . "\n\n" . 'Booking Number : ' . "\n" . $payment['BookingNumber'] . "\n\n" . 'Travel Date : ' . "\n" . $payment['TravelDate'] . "\n\n" . 'Destination : ' . "\n" . $payment['Destination'] . "\n\n" . 'Sales Agent : ' . "\n" . $payment['SalesAgent'] . "\n\n" . 'Transaction Date : ' . "\n" . $payment['Date'] . "\n\n" . 'Payment In : ' . "\n" . $payment['Credit'] . "\n\n" . 'Payment Type : ' . "\n" . $payment['Type'] . "\n\n" . 'Reference Number : ' . "\n" . $payment['ReferenceNumber']);
		} else {
			$payment['Debit'] = 'RM ' . number_format($payment['Debit'], 2, '.', ',');
			$payment['Deadline'] = strtoupper(date('j M Y', strtotime($payment['Deadline'])));
			$payment['DebitRemark'] = empty($payment['DebitRemark']) ? '-' : $payment['DebitRemark'];
			if($payment['Type'] == 'SUPPLIER PAYMENT') {
				$payment['QuotationNumber'] = empty($payment['QuotationNumber']) ? '-' : $payment['QuotationNumber'];
				$payment['InvoiceNumber'] = empty($payment['InvoiceNumber']) ? '-' : $payment['InvoiceNumber'];
				$text = urlencode('New Payment Record Successfully Created' . "\n\n" . 'Booking Number : ' . "\n" . $payment['BookingNumber'] . "\n\n" . 'Travel Date : ' . "\n" . $payment['TravelDate'] . "\n\n" . 'Destination : ' . "\n" . $payment['Destination'] . "\n\n" . 'Sales Agent : ' . "\n" . $payment['SalesAgent'] . "\n\n" . 'Transaction Date : ' . "\n" . $payment['Date'] . "\n\n" . 'Payment Out : ' . "\n" . $payment['Debit'] . "\n\n" . 'Payment Type : ' . "\n" . $payment['Type'] . "\n\n" . 'Supplier : ' . "\n" . $payment['Supplier'] . "\n\n" . 'Payment Deadline : ' . "\n" . $payment['Deadline'] . "\n\n" . 'Quotation Number : ' . "\n" . $payment['QuotationNumber'] . "\n\n" . 'Invoice Number : ' . "\n" . $payment['InvoiceNumber']);
			} else {
				$text = urlencode('New Payment Record Successfully Created' . "\n\n" . 'Booking Number : ' . "\n" . $payment['BookingNumber'] . "\n\n" . 'Travel Date : ' . "\n" . $payment['TravelDate'] . "\n\n" . 'Destination : ' . "\n" . $payment['Destination'] . "\n\n" . 'Sales Agent : ' . "\n" . $payment['SalesAgent'] . "\n\n" . 'Transaction Date : ' . "\n" . $payment['Date'] . "\n\n" . 'Payment Out : ' . "\n" . $payment['Debit'] . "\n\n" . 'Payment Type : ' . "\n" . $payment['Type'] . "\n\n" . 'Payment Deadline : ' . "\n" . $payment['Deadline'] . "\n\n" . 'Bank : ' . "\n" . $payment['Bank'] . "\n\n" . 'Bank Account : ' . "\n" . $payment['BankAccount'] . "\n\n" . 'Bank Holder : ' . "\n" . $payment['BankHolder'] . "\n\n" . 'Remark : ' . "\n" . $payment['DebitRemark']);
			}
		}
		file_get_contents('https://api.telegram.org/bot7521016286:AAEMDyjd789UEHBH5LK4xfBzIzY9TZ80tCg/sendMessage?chat_id=-1002546036574&text=' . $text);

		return $payment_id;
	}

	function Create_Payment_Log($column, $current_data, $new_data, $payment_id)
	{
		$array = array(
			'PaymentID' => $payment_id,
			'Column' => $column,
			'CurrentData' => $current_data,
			'NewData' => $new_data,
			'InsertBy' => $this->session->admin_id,
			'InsertDate' => date('Y-m-d H:i:s')
		);
		$this->db->insert('payment_log', $array);
	}

	function Create_Payment_Log2()
	{
		$array = array(
			'PaymentID' => $this->input->get('payment_id'),
			'Column' => 'Status',
			'CurrentData' => $this->input->get('status'),
			'NewData' => 'N',
			'InsertBy' => $this->session->admin_id,
			'InsertDate' => date('Y-m-d H:i:s')
		);
		$this->db->insert('payment_log', $array);
	}
	
	function Update($payment)
	{
		$this->db->update_batch('payment', $payment, 'PaymentID');
	}

	function Update_Date($date, $payment_id)
	{
		$array = array(
			'Date' => $date,
			'UpdateBy' => $this->session->userdata('admin_id'),
			'UpdateDate' => date('Y-m-d H:i:s')
		);
		$this->db->where('PaymentID', $payment_id);
		$this->db->update('payment', $array);
	}

	function Update_Reference_Number($reference_number, $payment_id)
	{
		$array = array(
			'ReferenceNumber' => $reference_number,
			'UpdateBy' => $this->session->userdata('admin_id'),
			'UpdateDate' => date('Y-m-d H:i:s')
		);
		$this->db->where('PaymentID', $payment_id);
		$this->db->update('payment', $array);
	}

	function Update_Deadline($deadline, $payment_id)
	{
		$array = array(
			'Deadline' => $deadline,
			'UpdateBy' => $this->session->userdata('admin_id'),
			'UpdateDate' => date('Y-m-d H:i:s')
		);
		$this->db->where('PaymentID', $payment_id);
		$this->db->update('payment', $array);
	}

	function Update_File($column, $value, $payment_id)
	{
		$array = array(
			$column => $value['file_name'],
			'UpdateBy' => $this->session->userdata('admin_id'),
			'UpdateDate' => date('Y-m-d H:i:s')
		);
		$this->db->where('PaymentID', $payment_id);
		$this->db->update('payment', $array);
	}

	function Update_Status($status, $payment_id)
	{
		$array = array(
			'Status' => $status,
			'UpdateBy' => $this->session->userdata('admin_id'),
			'UpdateDate' => date('Y-m-d H:i:s')
		);
		$this->db->where('PaymentID', $payment_id);
		$this->db->update('payment', $array);
	}
	
	function Detect()
	{
		$this->db->where('PaymentID !=', $this->input->post('payment_id'));
		$this->db->where('BookingID', $this->input->post('booking_id'));
		$this->db->where('Type', 'FULL');
		$this->db->where_in('Status', array('Y', 'P'));
		if($this->db->get('payment')->row()) {
			return true;
		} else {
			return false;
		}
	}
}