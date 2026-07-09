<?php

class Dashboard_Model extends CI_Model

{

	//SA

	function Sales_Agent_Daily_Sales()

	{

		$this->db->select('SUM(NetTotal) AS Sales, CAST(InsertDate AS DATE) As Date');

		$this->db->where('SalesAgent', $this->session->admin_id);

		$this->db->where('BookingConfirmationTitle', 'BOOKING CONFIRMATION');

		$this->db->where('CancelStatus', 'N');

		$this->db->where('Status !=', 'N');

		$this->db->where('CAST(InsertDate AS DATE) >=', date('Y-m-d', strtotime('This Week Monday')));

		$this->db->where('CAST(InsertDate AS DATE) <=', date('Y-m-d', strtotime('This Week Sunday')));

		$this->db->group_by('Date');

		$this->db->order_by('Date', 'ASC');

		$daily_sales = $this->db->get('booking');

		return $daily_sales->result();

	}



	function Sales_Agent_Monthly_Sales()

	{

		$this->db->select('SUM(NetTotal) AS Sales, MONTH(InsertDate) AS Month');

		$this->db->where('SalesAgent', $this->session->admin_id);

		$this->db->where('BookingConfirmationTitle', 'BOOKING CONFIRMATION');

		$this->db->where('CancelStatus', 'N');

		$this->db->where('Status !=', 'N');

		$this->db->where('YEAR(InsertDate)', date('Y'));

		$this->db->group_by('Month');

		$this->db->order_by('Month', 'ASC');

		$monthly_sales = $this->db->get('booking');

		return $monthly_sales->result();

	}



	function Sales_Agent_Cancelled_Bookings()

	{

		$this->db->select('COUNT(CancelStatus) AS Total, MONTH(InsertDate) AS Month');

		$this->db->where('SalesAgent', $this->session->admin_id);

		$this->db->where('BookingConfirmationTitle', 'BOOKING CONFIRMATION');

		$this->db->where('CancelStatus', 'Y');

		$this->db->where('Status !=', 'N');

		$this->db->where('YEAR(InsertDate)', date('Y'));

		$this->db->group_by('Month');

		$this->db->order_by('Month', 'ASC');

		$cancelled_bookings = $this->db->get('booking');

		return $cancelled_bookings->result();

	}



	function Sales_Agent_Monthly_Bookings()

	{

		$this->db->select('COUNT(BookingID) AS Total');

		$this->db->where('SalesAgent', $this->session->admin_id);

		$this->db->where('BookingConfirmationTitle', 'BOOKING CONFIRMATION');

		$this->db->where('Status !=', 'N');

		$this->db->where('YEAR(InsertDate)', date('Y'));

		$this->db->group_by('MONTH(InsertDate)');

		$this->db->order_by('MONTH(InsertDate)', 'ASC');

		$monthly_bookings = $this->db->get('booking');

		return $monthly_bookings->result();

	}



	function Sales_Agent_Upcoming_Travels($start_date = null, $end_date = null)

	{

		$this->db->select('BookingNumber, Customer, Name, StartDate');

		$this->db->join('category', 'category.CategoryID = booking.Destination', 'left');

		// Use custom date range if provided, otherwise default to tomorrow
		if ($start_date && $end_date) {
			$this->db->where('StartDate >=', $start_date);
			$this->db->where('StartDate <=', $end_date);
		} elseif ($start_date) {
			$this->db->where('StartDate >=', $start_date);
		} elseif ($end_date) {
			$this->db->where('StartDate <=', $end_date);
		} else {
			$this->db->where('StartDate', date('Y-m-d', strtotime('+ 1 day')));
		}

		$this->db->where('SalesAgent', $this->session->admin_id);

		$this->db->where('BookingConfirmationTitle', 'BOOKING CONFIRMATION');

		$this->db->where('CancelStatus', 'N');

		$this->db->where('booking.Status', 'PT');

		$this->db->order_by('StartDate', 'ASC');

		$this->db->order_by('BookingNumber', 'ASC');

		$upcoming_travels = $this->db->get('booking');

		return $upcoming_travels->result();

	}



	function Sales_Agent_Overdue_Payments()

	{

		$this->db->select('BookingID, BookingNumber, Customer, Name');

		$this->db->join('category', 'category.CategoryID = booking.Destination', 'left');

		$this->db->where("((`FullPaymentDeadline` < '".date('Y-m-d')."' AND `booking`.`Status` IN ('P','PP')) OR ((`DepositDeadline` < '".date('Y-m-d')."' AND `booking`.`Status` = 'P') OR (`FullPaymentDeadline` < '".date('Y-m-d')."' AND `booking`.`Status` IN ('P','PP'))))");

		$this->db->where('SalesAgent', $this->session->admin_id);

		$this->db->where('BookingConfirmationTitle', 'BOOKING CONFIRMATION');

		$this->db->where('CancelStatus', 'N');

		$this->db->order_by('BookingNumber', 'ASC');

		$overdue_payments = $this->db->get('booking');

		return $overdue_payments->result();

	}



	function Sales_Agent_Pending_Travel_Vouchers($start_date = null, $end_date = null)

	{

		$this->db->select('BookingNumber, Customer, StartDate, EndDate, Name, booking.Status');

		$this->db->join('category', 'category.CategoryID = booking.Destination', 'left');

		// Overlap window: include any booking whose travel dates intersect the range.
		// Defaults to today..+7 days so ongoing trips ending in the window are counted.
		$range_start = $start_date ?: date('Y-m-d');
		$range_end   = $end_date   ?: date('Y-m-d', strtotime('+ 7 days'));
		$this->db->where('StartDate <=', $range_end);
		$this->db->where('EndDate >=', $range_start);

		$this->db->where('SalesAgent', $this->session->admin_id);

		$this->db->where('BookingConfirmationTitle', 'BOOKING CONFIRMATION');

		$this->db->where('CancelStatus', 'N');

		$this->db->where_in('booking.Status', ['PTV', 'PBO', 'PT', 'OG']);

		$this->db->order_by('StartDate', 'ASC');

		$this->db->order_by('BookingNumber', 'ASC');

		$pending_travel_vouchers = $this->db->get('booking');

		return $pending_travel_vouchers->result();

	}



	function Sales_Agent_Profit_Margins()

	{

		$this->db->select('BookingNumber, Customer, NetTotal, AfterSalesService, booking.Status, SUM(Credit) - SUM(Debit) As NetProfit, CAST(booking.InsertDate AS DATE) As Date, Name');

		$this->db->join('payment', 'payment.BookingID = booking.BookingID', 'left');

		$this->db->join('category', 'category.CategoryID = booking.Destination', 'left');

		$this->db->where('SalesAgent', $this->session->admin_id);

		$this->db->where('BookingConfirmationTitle', 'BOOKING CONFIRMATION');

		$this->db->where('CancelStatus', 'N');

		$this->db->where('booking.Status !=', 'N');

		$this->db->where('payment.Status', 'Y');

		$this->db->group_by('BookingNumber, Customer, NetTotal, AfterSalesService, booking.Status, CAST(booking.InsertDate AS DATE), Name');

		$this->db->order_by('BookingNumber', 'ASC');

		$profit_margins = $this->db->get('booking');

		return $profit_margins->result();

	}



	function Sales_Agent_Pending_Credit_Payments() 

	{

		$this->db->select('PaymentID, Date, Type, Credit, BookingNumber, Customer');

		$this->db->join('payment', 'payment.BookingID = booking.BookingID', 'left');

		$this->db->where('Credit !=', 0.00);

		$this->db->where('payment.Status', 'P');

		$this->db->where('SalesAgent', $this->session->admin_id);

		$this->db->where('BookingConfirmationTitle', 'BOOKING CONFIRMATION');

		$this->db->where('CancelStatus', 'N');

		$this->db->where('booking.Status !=', 'N');

		$this->db->order_by('Date', 'ASC');

		$pending_credit_payments = $this->db->get('booking');

		return $pending_credit_payments->result();

	}



	function Sales_Agent_Pending_Debit_Payments()

	{

		$this->db->select('PaymentID, Type, Debit, Deadline, payment.BankHolder, BookingNumber, Name');

		$this->db->join('payment', 'payment.BookingID = booking.BookingID', 'left');

		$this->db->join('supplier', 'supplier.SupplierID = payment.SupplierID', 'left');

		$this->db->where('Credit', 0.00);

		$this->db->where('Debit !=', 0.00);

		$this->db->where('payment.Status', 'P');

		$this->db->where('SalesAgent', $this->session->admin_id);

		$this->db->where('BookingConfirmationTitle', 'BOOKING CONFIRMATION');

		$this->db->where('CancelStatus', 'N');

		$this->db->where('booking.Status !=', 'N');

		$this->db->order_by('Deadline', 'ASC');

		$pending_debit_payments = $this->db->get('booking');

		return $pending_debit_payments->result();

	}



	//Owner / Finance

	function Sales_Agents()

	{

		$this->db->select('AdminID, Name');

		$this->db->where('AdminID !=', 8);

		$this->db->where('Level !=', '30');

		$this->db->where('Status', 'Y');

		$this->db->order_by('Name', 'ASC');

		$sales_agents = $this->db->get('admin');

		return $sales_agents->result();

	}



	function Sales_Agents_Daily_Sales($start_date = null, $end_date = null, $selected_agents = null)

	{

		$this->db->select('SalesAgent, SUM(NetTotal) AS Sales, CAST(booking.InsertDate AS DATE) As Date, Name');

		$this->db->join('admin', 'admin.AdminID = booking.SalesAgent', 'left');

		$this->db->where('BookingConfirmationTitle', 'BOOKING CONFIRMATION');

		$this->db->where('CancelStatus', 'N');

		$this->db->where('booking.Status !=', 'N');

		// Use custom date range if provided, otherwise default to current week
		if ($start_date && $end_date) {
			$this->db->where('CAST(booking.InsertDate AS DATE) >=', $start_date);
			$this->db->where('CAST(booking.InsertDate AS DATE) <=', $end_date);
		} else {
			$this->db->where('CAST(booking.InsertDate AS DATE) >=', date('Y-m-d', strtotime('This Week Monday')));
			$this->db->where('CAST(booking.InsertDate AS DATE) <=', date('Y-m-d', strtotime('This Week Sunday')));
		}

		// Filter by selected sales agents if provided
		if (!empty($selected_agents)) {
			$this->db->where_in('SalesAgent', $selected_agents);
		}

		$this->db->where('Level !=', 30);

		$this->db->where('admin.Status', 'Y');

		$this->db->group_by('SalesAgent');

		$this->db->group_by('Date');

		$this->db->order_by('Date', 'ASC');

		$daily_sales = $this->db->get('booking');

		return $daily_sales->result();

	}



	function Sales_Agents_Monthly_Sales()

	{

		$this->db->select('SalesAgent, SUM(NetTotal) AS Sales, MONTH(booking.InsertDate) AS Month, Name');

		$this->db->join('admin', 'admin.AdminID = booking.SalesAgent', 'left');

		$this->db->where('BookingConfirmationTitle', 'BOOKING CONFIRMATION');

		$this->db->where('CancelStatus', 'N');

		$this->db->where('booking.Status !=', 'N');

		$this->db->where('YEAR(booking.InsertDate)', date('Y'));

		$this->db->where('Level !=', 30);

		$this->db->where('admin.Status', 'Y');

		$this->db->group_by('SalesAgent');

		$this->db->group_by('Month');

		$this->db->order_by('Month', 'ASC');

		$monthly_sales = $this->db->get('booking');

		return $monthly_sales->result();

	}



	function Daily_Sales($start_date = null, $end_date = null)

	{

		$this->db->select('SUM(NetTotal) AS Sales, CAST(InsertDate AS DATE) As Date');

		$this->db->where('BookingConfirmationTitle', 'BOOKING CONFIRMATION');

		$this->db->where('CancelStatus', 'N');

		$this->db->where('Status !=', 'N');

		// Use custom date range if provided, otherwise default to current week
		if ($start_date && $end_date) {
			$this->db->where('CAST(InsertDate AS DATE) >=', $start_date);
			$this->db->where('CAST(InsertDate AS DATE) <=', $end_date);
		} else {
			$this->db->where('CAST(InsertDate AS DATE) >=', date('Y-m-d', strtotime('This Week Monday')));
			$this->db->where('CAST(InsertDate AS DATE) <=', date('Y-m-d', strtotime('This Week Sunday')));
		}

		$this->db->group_by('Date');

		$this->db->order_by('Date', 'ASC');

		$daily_sales = $this->db->get('booking');

		return $daily_sales->result();

	}



	function Monthly_Sales()

	{

		$this->db->select('SUM(NetTotal) AS Sales, MONTH(InsertDate) AS Month');

		$this->db->where('BookingConfirmationTitle', 'BOOKING CONFIRMATION');

		$this->db->where('CancelStatus', 'N');

		$this->db->where('Status !=', 'N');

		$this->db->where('YEAR(InsertDate)', date('Y'));

		$this->db->group_by('Month');

		$this->db->order_by('Month', 'ASC');

		$monthly_sales = $this->db->get('booking');

		return $monthly_sales->result();

	}



	function Cancelled_Bookings()

	{

		$this->db->select('COUNT(CancelStatus) AS Total, MONTH(InsertDate) AS Month');

		$this->db->where('BookingConfirmationTitle', 'BOOKING CONFIRMATION');

		$this->db->where('CancelStatus', 'Y');

		$this->db->where('Status !=', 'N');

		$this->db->where('YEAR(InsertDate)', date('Y'));

		$this->db->group_by('Month');

		$this->db->order_by('Month', 'ASC');

		$cancelled_bookings = $this->db->get('booking');

		return $cancelled_bookings->result();

	}



	function Monthly_Bookings()

	{

		$this->db->select('COUNT(BookingID) AS Total');

		$this->db->where('BookingConfirmationTitle', 'BOOKING CONFIRMATION');

		$this->db->where('Status !=', 'N');

		$this->db->where('YEAR(InsertDate)', date('Y'));

		$this->db->group_by('MONTH(InsertDate)');

		$this->db->order_by('MONTH(InsertDate)', 'ASC');

		$monthly_bookings = $this->db->get('booking');

		return $monthly_bookings->result();

	}



	function Pending_Partial_Payments()

	{

		// Grouped by BookingNumber: Credit/Debit are summed across the booking's
		// payment rows, while NetTotal and the deadline columns are constant per
		// booking. Wrap the per-booking columns in MIN() so the SELECT is valid
		// under MySQL's only_full_group_by mode without changing the grouping or
		// the one-row-per-booking output.
		$this->db->select('(MIN(NetTotal) - SUM(Credit) + SUM(DEbit)) As OutstandingBalance,

		MONTH(CASE WHEN MIN(AdditionalPaymentDeadline) IS NULL OR MIN(FullPaymentDeadline) > MIN(AdditionalPaymentDeadline) THEN MIN(FullPaymentDeadline)

		ELSE MIN(AdditionalPaymentDeadline)

		END) As Month', false);

		$this->db->join('payment', 'payment.BookingID = booking.BookingID', 'left');

		$this->db->where_in('Type', array('DEPOSIT', 'FULL', 'ADDITIONAL PAYMENT', 'CUSTOMER REFUND'));

		$this->db->where('payment.Status', 'Y');

		$this->db->where('BookingConfirmationTitle', 'BOOKING CONFIRMATION');

		$this->db->where('CancelStatus', 'N');

		$this->db->where('booking.Status', 'PP');

		$this->db->where('YEAR(booking.FullPaymentDeadline)', date('Y'));

		$this->db->group_by('BookingNumber');

		$pending_partial_payments = $this->db->get('booking');

		return $pending_partial_payments->result();

	}



	function Pending_Debit_Payments()

	{

		$this->db->select('SUM(Debit) AS Debit, MONTH(Deadline) AS Month');

		$this->db->where('Credit', 0.00);

		$this->db->where('Status', 'P');

		$this->db->group_by('Month');

		$this->db->order_by('Month', 'ASC');

		$pending_debit_payments = $this->db->get('payment');

		return $pending_debit_payments->result();

	}



	function Approved_Credit_Payments()

	{

		$this->db->select('SUM(Credit) AS Credit, MONTH(Date) AS Month');

		$this->db->join('payment', 'payment.BookingID = booking.BookingID', 'left');

		$this->db->where('YEAR(Date)', date('Y'));

		$this->db->where('Credit !=', 0.00);

		$this->db->where('payment.Status', 'Y');

		$this->db->where('BookingConfirmationTitle', 'BOOKING CONFIRMATION');

		$this->db->where('CancelStatus', 'N');

		$this->db->where('booking.Status !=', 'N');

		$this->db->group_by('Month');

		$this->db->order_by('Month', 'ASC');

		$approved_credit_payments = $this->db->get('booking');

		return $approved_credit_payments->result();

	}



	function Approved_Debit_Payments()

	{

		$this->db->select('SUM(Debit) AS Debit, MONTH(Date) AS Month');

		$this->db->join('payment', 'payment.BookingID = booking.BookingID', 'left');

		$this->db->where('YEAR(Date)', date('Y'));

		$this->db->where('Credit', 0.00);

		$this->db->where('payment.Status', 'Y');

		$this->db->where('BookingConfirmationTitle', 'BOOKING CONFIRMATION');

		$this->db->where('CancelStatus', 'N');

		$this->db->where('booking.Status !=', 'N');

		$this->db->group_by('Month');

		$this->db->order_by('Month', 'ASC');

		$approved_debit_payments = $this->db->get('booking');

		return $approved_debit_payments->result();

	}



	function Upcoming_Travels($start_date = null, $end_date = null)

	{

		$this->db->select('BookingNumber, Customer, AdminID, admin.Name As SalesAgent, category.Name As Destination, StartDate');

		$this->db->join('admin', 'admin.AdminID = booking.SalesAgent', 'left');

		$this->db->join('category', 'category.CategoryID = booking.Destination', 'left');

		// Use custom date range if provided, otherwise default to tomorrow
		if ($start_date && $end_date) {
			$this->db->where('StartDate >=', $start_date);
			$this->db->where('StartDate <=', $end_date);
		} elseif ($start_date) {
			$this->db->where('StartDate >=', $start_date);
		} elseif ($end_date) {
			$this->db->where('StartDate <=', $end_date);
		} else {
			$this->db->where('StartDate', date('Y-m-d', strtotime('+ 1 day')));
		}

		$this->db->where('BookingConfirmationTitle', 'BOOKING CONFIRMATION');

		$this->db->where('CancelStatus', 'N');

		$this->db->where('booking.Status', 'PT');

		$this->db->order_by('SalesAgent', 'ASC');

		$this->db->order_by('StartDate', 'ASC');

		$this->db->order_by('BookingNumber', 'ASC');

		$upcoming_travels = $this->db->get('booking');

		return $upcoming_travels->result();

	}



	function Overdue_Payments()

	{

		$this->db->select('BookingID, BookingNumber, Customer, AdminID, admin.Name As SalesAgent, category.Name As Destination');

		$this->db->join('admin', 'admin.AdminID = booking.SalesAgent', 'left');

		$this->db->join('category', 'category.CategoryID = booking.Destination', 'left');

		$this->db->where("((`FullPaymentDeadline` < '".date('Y-m-d')."' AND `booking`.`Status` IN ('P','PP')) OR ((`DepositDeadline` < '".date('Y-m-d')."' AND `booking`.`Status` = 'P') OR (`FullPaymentDeadline` < '".date('Y-m-d')."' AND `booking`.`Status` IN ('P','PP'))))");

		$this->db->where('BookingConfirmationTitle', 'BOOKING CONFIRMATION');

		$this->db->where('CancelStatus', 'N');

		$this->db->order_by('SalesAgent', 'ASC');

		$this->db->order_by('BookingNumber', 'ASC');

		$overdue_payments = $this->db->get('booking');



		if(isset($_GET['nick'])) {

			print_r($this->db->last_query());exit;

		}

		

		return $overdue_payments->result();

	}



	function Pending_Travel_Vouchers($start_date = null, $end_date = null)

	{

		$this->db->select('BookingNumber, Customer, StartDate, EndDate, AdminID, admin.Name As SalesAgent, category.Name As Destination, booking.Status');

		$this->db->join('admin', 'admin.AdminID = booking.SalesAgent', 'left');

		$this->db->join('category', 'category.CategoryID = booking.Destination', 'left');

		// Overlap window: include any booking whose travel dates intersect the range.
		// Defaults to today..+7 days so ongoing trips ending in the window are counted.
		$range_start = $start_date ?: date('Y-m-d');
		$range_end   = $end_date   ?: date('Y-m-d', strtotime('+ 7 days'));
		$this->db->where('StartDate <=', $range_end);
		$this->db->where('EndDate >=', $range_start);

		$this->db->where('BookingConfirmationTitle', 'BOOKING CONFIRMATION');

		$this->db->where('CancelStatus', 'N');

		$this->db->where_in('booking.Status', ['PTV', 'PBO', 'PT', 'OG']);

		$this->db->order_by('SalesAgent', 'ASC');

		$this->db->order_by('StartDate', 'ASC');

		$this->db->order_by('BookingNumber', 'ASC');

		$pending_travel_vouchers = $this->db->get('booking');

		return $pending_travel_vouchers->result();

	}



	function Pending_Reviews()

	{

		$this->db->select('BookingNumber, Customer, AdminID, admin.Name As SalesAgent, category.Name As Destination');

		$this->db->join('admin', 'admin.AdminID = booking.SalesAgent', 'left');

		$this->db->join('category', 'category.CategoryID = booking.Destination', 'left');

		$this->db->where('EndDate <', date('Y-m-d'));

		$this->db->where('BookingConfirmationTitle', 'BOOKING CONFIRMATION');

		$this->db->where('CancelStatus', 'N');

		$this->db->where('AfterSalesService', 'PENDING');

		$this->db->where('booking.Status', 'Y');

		$this->db->order_by('SalesAgent', 'ASC');

		$this->db->order_by('BookingNumber', 'ASC');

		$pending_reviews = $this->db->get('booking');

		return $pending_reviews->result();

	}



	function Profit_Margins()

	{

		$this->db->select('BookingNumber, Customer, NetTotal, AfterSalesService, booking.Status, SUM(Credit) - SUM(Debit) As NetProfit, CAST(booking.InsertDate AS DATE) As Date, AdminID, admin.Name As SalesAgent, category.Name As Destination');

		$this->db->join('admin', 'admin.AdminID = booking.SalesAgent', 'left');

		$this->db->join('payment', 'payment.BookingID = booking.BookingID', 'left');

		$this->db->join('category', 'category.CategoryID = booking.Destination', 'left');

		$this->db->where('BookingConfirmationTitle', 'BOOKING CONFIRMATION');

		$this->db->where('CancelStatus', 'N');

		$this->db->where('booking.Status !=', 'N');

		$this->db->where('payment.Status', 'Y');

		$this->db->group_by('BookingNumber, Customer, NetTotal, AfterSalesService, booking.Status, CAST(booking.InsertDate AS DATE), AdminID, admin.Name, category.Name');

		$this->db->order_by('SalesAgent', 'ASC');

		$this->db->order_by('BookingNumber', 'ASC');

		$profit_margins = $this->db->get('booking');

		return $profit_margins->result();

	}



	function Pending_Credit_Payments()

	{

		$this->db->select('PaymentID, Date, Type, Credit, BookingNumber, Customer, AdminID, Name');

		$this->db->join('payment', 'payment.BookingID = booking.BookingID', 'left');

		$this->db->join('admin', 'admin.AdminID = booking.SalesAgent', 'left');

		$this->db->where('Credit !=', 0.00);

		$this->db->where('payment.Status', 'P');

		$this->db->where('BookingConfirmationTitle', 'BOOKING CONFIRMATION');

		$this->db->where('CancelStatus', 'N');

		$this->db->where('booking.Status !=', 'N');

		$this->db->order_by('Name', 'ASC');

		$this->db->order_by('Date', 'ASC');

		$pending_credit_payments = $this->db->get('booking');

		return $pending_credit_payments->result();

	}



	function Pending_Debit_Payments1()

	{

		$this->db->select('PaymentID, Type, Debit, Deadline, payment.BankHolder, BookingNumber, AdminID, admin.Name As SalesAgent, supplier.Name As Supplier');

		$this->db->join('payment', 'payment.BookingID = booking.BookingID', 'left');

		$this->db->join('admin', 'admin.AdminID = booking.SalesAgent', 'left');

		$this->db->join('supplier', 'supplier.SupplierID = payment.SupplierID', 'left');

		$this->db->where('Credit', 0.00);

		$this->db->where('Debit !=', 0.00);

		$this->db->where('payment.Status', 'P');

		$this->db->where('BookingConfirmationTitle', 'BOOKING CONFIRMATION');

		$this->db->where('CancelStatus', 'N');

		$this->db->where('booking.Status !=', 'N');

		$this->db->order_by('SalesAgent', 'ASC');

		$this->db->order_by('Deadline', 'ASC');

		$pending_debit_payments = $this->db->get('booking');

		return $pending_debit_payments->result();

	}



	function Weekly_Top_SA()

	{

		$this->db->select('SUM(NetTotal) AS Sales, Name, Gender');

		$this->db->join('admin', 'admin.AdminID = booking.SalesAgent', 'left');

		$this->db->where('BookingConfirmationTitle', 'BOOKING CONFIRMATION');

		$this->db->where('CancelStatus', 'N');

		$this->db->where('booking.Status !=', 'N');

		$this->db->where('CAST(booking.InsertDate AS DATE) >=', date('Y-m-d', strtotime('This Week Monday')));

		$this->db->where('CAST(booking.InsertDate AS DATE) <=', date('Y-m-d', strtotime('This Week Sunday')));

		$this->db->where('Level !=', 30);

		$this->db->where('admin.Status', 'Y');

		$this->db->group_by('SalesAgent');

		$this->db->order_by('Sales', 'DESC');

		$rating = $this->db->get('booking');

		return $rating->result();

	}



	function Monthly_Top_SA()

	{

		$this->db->select('SUM(NetTotal) AS Sales, Name, Gender');

		$this->db->join('admin', 'admin.AdminID = booking.SalesAgent', 'left');

		$this->db->where('BookingConfirmationTitle', 'BOOKING CONFIRMATION');

		$this->db->where('CancelStatus', 'N');

		$this->db->where('booking.Status !=', 'N');

		$this->db->where('CAST(booking.InsertDate AS DATE) >=', date('Y-m-1'));

		$this->db->where('CAST(booking.InsertDate AS DATE) <=', date('Y-m-t'));

		$this->db->where('Level !=', 30);

		$this->db->where('admin.Status', 'Y');

		$this->db->group_by('SalesAgent');

		$this->db->order_by('Sales', 'DESC');

		$rating = $this->db->get('booking');

		return $rating->result();

	}



	function Annual_Top_SA()

	{

		$this->db->select('SUM(NetTotal) AS Sales, Name, Gender');

		$this->db->join('admin', 'admin.AdminID = booking.SalesAgent', 'left');

		$this->db->where('BookingConfirmationTitle', 'BOOKING CONFIRMATION');

		$this->db->where('CancelStatus', 'N');

		$this->db->where('booking.Status !=', 'N');

		$this->db->where('CAST(booking.InsertDate AS DATE) >=', date('Y-m-d', strtotime('First Day Of January This Year')));

		$this->db->where('CAST(booking.InsertDate AS DATE) <=', date('Y-m-d', strtotime('Last Day Of December This Year')));

		$this->db->where('Level !=', 30);

		$this->db->where('admin.Status', 'Y');

		$this->db->group_by('SalesAgent');

		$this->db->order_by('Sales', 'DESC');

		$rating = $this->db->get('booking');

		return $rating->result();

	}

	// ---------------------------------------------------------------------
	// Owner dashboard KPI cards (Level 10 only). Each method is scoped to an
	// explicit [$start, $end] "Y-m-d" window so the controller can call it once
	// per period (yesterday / today / week / month / year) and the logic stays
	// deterministic under test. Query shapes are locked by
	// tests/helpers/OwnerDashboardKpiTest.php.
	// ---------------------------------------------------------------------

	// Credited booking-confirmation sales grouped by the team the sale was made
	// under, for one date window. Groups on the FROZEN booking.TeamID snapshot
	// (the team the credited agent belonged to at the time of the sale), NOT the
	// agent's live admin.TeamID — so an agent who later moves team or leaves does
	// not retroactively pull an old sale out of the team that earned it. Only
	// active teams; excludes quotation/proforma, cancelled, draft and
	// zero/negative bookings. Amount = SUM(NetTotal), windowed on InsertDate.
	function Team_Sales($start, $end)
	{
		$this->db->select('team.TeamID AS TeamID, team.Name AS TeamName, COALESCE(SUM(booking.NetTotal), 0) AS Sales', false);
		$this->db->join('team', 'team.TeamID = booking.TeamID', 'inner');
		$this->db->where('booking.BookingConfirmationTitle', 'BOOKING CONFIRMATION');
		$this->db->where('booking.CancelStatus', 'N');
		$this->db->where('booking.Status !=', 'N');
		$this->db->where('booking.NetTotal >', 0);
		$this->db->where('team.Status', 'Y');
		$this->db->where('CAST(booking.InsertDate AS DATE) >=', $start);
		$this->db->where('CAST(booking.InsertDate AS DATE) <=', $end);
		$this->db->group_by('team.TeamID, team.Name');
		return $this->db->get('booking')->result();
	}

	// BC sales in the window whose frozen snapshot doesn't land in an active team
	// — the inverse of Team_Sales()'s inner join (booking.TeamID is NULL, or the
	// snapshotted team is inactive). This "Unassigned" bucket lets the team
	// breakdown reconcile to the true company-wide BC total. Same BC filters as
	// Team_Sales(); no payment filter (value is by booking confirmation, not cash).
	function Unassigned_Sales($start, $end)
	{
		$this->db->select('COALESCE(SUM(booking.NetTotal), 0) AS Sales', false);
		$this->db->join('team', 'team.TeamID = booking.TeamID', 'left');
		$this->db->where('booking.BookingConfirmationTitle', 'BOOKING CONFIRMATION');
		$this->db->where('booking.CancelStatus', 'N');
		$this->db->where('booking.Status !=', 'N');
		$this->db->where('booking.NetTotal >', 0);
		$this->db->group_start();
			$this->db->where('booking.TeamID IS NULL', null, false);
			$this->db->or_where('team.Status !=', 'Y');
		$this->db->group_end();
		$this->db->where('CAST(booking.InsertDate AS DATE) >=', $start);
		$this->db->where('CAST(booking.InsertDate AS DATE) <=', $end);
		$row = $this->db->get('booking')->row();
		return $row ? (float) $row->Sales : 0.0;
	}

	// Sum of member agents' sales targets per active team: the monthly target
	// from sales_target (year + month) and the yearly target from
	// sales_target_year (year). Team membership = admin.TeamID on an active team.
	// Returns keyed by TeamID => array('month' => float, 'year' => float). Backs
	// the Owner "Total Sales vs Target by Team" card; teams (or agents) with no
	// target row simply contribute 0 and are absent from the returned array.
	function Team_Targets($year, $month)
	{
		$out = array();

		// Monthly targets (sales_target: one row per agent per year+month).
		$this->db->select('admin.TeamID AS TeamID, COALESCE(SUM(sales_target.target_amount), 0) AS Amount', false);
		$this->db->join('admin', 'admin.AdminID = sales_target.AdminID', 'inner');
		$this->db->join('team', 'team.TeamID = admin.TeamID', 'inner');
		$this->db->where('sales_target.target_year', (int) $year);
		$this->db->where('sales_target.target_month', (int) $month);
		$this->db->where('team.Status', 'Y');
		$this->db->group_by('admin.TeamID');
		foreach($this->db->get('sales_target')->result() as $r) {
			$out[$r->TeamID]['month'] = (float) $r->Amount;
		}

		// Yearly targets (sales_target_year: one row per agent per year).
		$this->db->select('admin.TeamID AS TeamID, COALESCE(SUM(sales_target_year.target_amount), 0) AS Amount', false);
		$this->db->join('admin', 'admin.AdminID = sales_target_year.AdminID', 'inner');
		$this->db->join('team', 'team.TeamID = admin.TeamID', 'inner');
		$this->db->where('sales_target_year.target_year', (int) $year);
		$this->db->where('team.Status', 'Y');
		$this->db->group_by('admin.TeamID');
		foreach($this->db->get('sales_target_year')->result() as $r) {
			$out[$r->TeamID]['year'] = (float) $r->Amount;
		}

		return $out;
	}

	// Company-wide count of new GHL leads whose conversation started in the
	// window (ghl_processed_leads.lead_started_at). One row per processed lead.
	function New_Leads_Count($start, $end)
	{
		$this->db->where('CAST(lead_started_at AS DATE) >=', $start);
		$this->db->where('CAST(lead_started_at AS DATE) <=', $end);
		return (int) $this->db->count_all_results('ghl_processed_leads');
	}

	// Top cancellation reasons by number of cancelled booking confirmations in
	// the window (windowed on InsertDate). Excludes quotation/proforma and drafts.
	function Top_Cancellation_Reasons($start, $end, $limit = 5)
	{
		$this->db->select('cancellation_reason.Name AS Name, COUNT(booking.BookingID) AS Total', false);
		$this->db->join('cancellation_reason', 'cancellation_reason.CancellationReasonID = booking.CancellationReasonID', 'inner');
		$this->db->where('booking.BookingConfirmationTitle', 'BOOKING CONFIRMATION');
		$this->db->where('booking.CancelStatus', 'Y');
		$this->db->where('booking.Status !=', 'N');
		$this->db->where('CAST(booking.InsertDate AS DATE) >=', $start);
		$this->db->where('CAST(booking.InsertDate AS DATE) <=', $end);
		$this->db->group_by('cancellation_reason.CancellationReasonID, cancellation_reason.Name');
		$this->db->order_by('Total', 'DESC');
		$this->db->limit($limit);
		return $this->db->get('booking')->result();
	}

	// Total cancelled booking confirmations in the window (windowed on
	// InsertDate). Same filters as Top_Cancellation_Reasons but without the
	// reason grouping — shown as the cancellation count in the card subtext.
	function Total_Cancellations($start, $end)
	{
		$this->db->select('COUNT(booking.BookingID) AS Total', false);
		$this->db->where('booking.BookingConfirmationTitle', 'BOOKING CONFIRMATION');
		$this->db->where('booking.CancelStatus', 'Y');
		$this->db->where('booking.Status !=', 'N');
		$this->db->where('CAST(booking.InsertDate AS DATE) >=', $start);
		$this->db->where('CAST(booking.InsertDate AS DATE) <=', $end);
		$row = $this->db->get('booking')->row();
		return $row ? (int)$row->Total : 0;
	}

	// Total booking confirmations in the window (windowed on InsertDate),
	// cancelled or not — drafts and quotations excluded. Used as the denominator
	// for each cancellation reason's share: cancelled-for-reason / all bookings.
	function Total_Bookings($start, $end)
	{
		$this->db->select('COUNT(booking.BookingID) AS Total', false);
		$this->db->where('booking.BookingConfirmationTitle', 'BOOKING CONFIRMATION');
		$this->db->where('booking.Status !=', 'N');
		$this->db->where('CAST(booking.InsertDate AS DATE) >=', $start);
		$this->db->where('CAST(booking.InsertDate AS DATE) <=', $end);
		$row = $this->db->get('booking')->row();
		return $row ? (int)$row->Total : 0;
	}

	// Approved payment OUT (to suppliers) in the window: SUM(Debit) of approved
	// debit rows (Credit = 0), windowed on payment.Date. Mirrors
	// Approved_Debit_Payments() but scoped to an explicit range.
	function Approved_Payment_Out($start, $end)
	{
		$this->db->select('COALESCE(SUM(Debit), 0) AS Amount', false);
		$this->db->join('payment', 'payment.BookingID = booking.BookingID', 'left');
		$this->db->where('Credit', 0.00);
		$this->db->where('payment.Status', 'Y');
		$this->db->where('BookingConfirmationTitle', 'BOOKING CONFIRMATION');
		$this->db->where('CancelStatus', 'N');
		$this->db->where('booking.Status !=', 'N');
		$this->db->where('CAST(payment.Date AS DATE) >=', $start);
		$this->db->where('CAST(payment.Date AS DATE) <=', $end);
		$row = $this->db->get('booking')->row();
		return $row ? (float) $row->Amount : 0.0;
	}

	// Unapproved payment OUT (to suppliers): SUM(Debit) of ALL pending debit rows
	// (Credit = 0, Status = 'P') DUE on or before $end — i.e. everything
	// scheduled-but-not-approved-yet, cumulative up to the window's end. Unlike
	// Approved_Payment_Out() there is NO lower bound: a pending pay-out is an
	// outstanding obligation, so overdue ones due before the window must still
	// count (otherwise the card understates the true backlog).
	//
	// NOTE: pending pay-outs have NO transaction Date yet (that is only stamped
	// once the payment is actually made/approved) — they carry a Deadline. So we
	// window on Deadline here, not Date. Using Date would exclude every pending
	// row (all NULL) and the card would always read 0.
	function Unapproved_Payment_Out($end)
	{
		$this->db->select('COALESCE(SUM(Debit), 0) AS Amount', false);
		$this->db->join('payment', 'payment.BookingID = booking.BookingID', 'left');
		$this->db->where('Credit', 0.00);
		$this->db->where('payment.Status', 'P');
		$this->db->where('BookingConfirmationTitle', 'BOOKING CONFIRMATION');
		$this->db->where('CancelStatus', 'N');
		$this->db->where('booking.Status !=', 'N');
		$this->db->where('CAST(payment.Deadline AS DATE) <=', $end);
		$row = $this->db->get('booking')->row();
		return $row ? (float) $row->Amount : 0.0;
	}

	// Approved payment IN (from customers) in the window: SUM(Credit) of approved
	// credit rows (Credit != 0), windowed on payment.Date. Mirrors
	// Approved_Credit_Payments() but scoped to an explicit range.
	function Approved_Payment_In($start, $end)
	{
		$this->db->select('COALESCE(SUM(Credit), 0) AS Amount', false);
		$this->db->join('payment', 'payment.BookingID = booking.BookingID', 'left');
		$this->db->where('Credit !=', 0.00);
		$this->db->where('payment.Status', 'Y');
		$this->db->where('BookingConfirmationTitle', 'BOOKING CONFIRMATION');
		$this->db->where('CancelStatus', 'N');
		$this->db->where('booking.Status !=', 'N');
		$this->db->where('CAST(payment.Date AS DATE) >=', $start);
		$this->db->where('CAST(payment.Date AS DATE) <=', $end);
		$row = $this->db->get('booking')->row();
		return $row ? (float) $row->Amount : 0.0;
	}

}