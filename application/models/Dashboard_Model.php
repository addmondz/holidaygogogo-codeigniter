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

		$this->db->select('BookingNumber, Customer, StartDate, EndDate, Name');

		$this->db->join('category', 'category.CategoryID = booking.Destination', 'left');

		// Use custom date range if provided, otherwise default to next 7 days
		if ($start_date && $end_date) {
			$this->db->where('StartDate >=', $start_date);
			$this->db->where('StartDate <=', $end_date);
		} elseif ($start_date) {
			$this->db->where('StartDate >=', $start_date);
		} elseif ($end_date) {
			$this->db->where('StartDate <=', $end_date);
		} else {
			$this->db->where('StartDate >=', date('Y-m-d', strtotime('+ 1 day')));
			$this->db->where('StartDate <=', date('Y-m-d', strtotime('+ 7 days')));
		}

		$this->db->where('SalesAgent', $this->session->admin_id);

		$this->db->where('BookingConfirmationTitle', 'BOOKING CONFIRMATION');

		$this->db->where('CancelStatus', 'N');

		$this->db->where('booking.Status', 'PTV');

		$this->db->order_by('StartDate', 'ASC');

		$this->db->order_by('BookingNumber', 'ASC');

		$pending_travel_vouchers = $this->db->get('booking');

		return $pending_travel_vouchers->result();

	}



	function Sales_Agent_Pending_Reviews()

	{

		$this->db->select('BookingNumber, Customer, Name');

		$this->db->join('category', 'category.CategoryID = booking.Destination', 'left');

		$this->db->where('EndDate <', date('Y-m-d'));

		$this->db->where('SalesAgent', $this->session->admin_id);

		$this->db->where('BookingConfirmationTitle', 'BOOKING CONFIRMATION');

		$this->db->where('CancelStatus', 'N');

		$this->db->where('AfterSalesService', 'PENDING');

		$this->db->where('booking.Status', 'Y');

		$this->db->order_by('BookingNumber', 'ASC');

		$pending_reviews = $this->db->get('booking');

		return $pending_reviews->result();

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

		$this->db->group_by('BookingNumber');

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



	function Sales_Agents_Daily_Sales()

	{

		$this->db->select('SalesAgent, SUM(NetTotal) AS Sales, CAST(booking.InsertDate AS DATE) As Date, Name');

		$this->db->join('admin', 'admin.AdminID = booking.SalesAgent', 'left');

		$this->db->where('BookingConfirmationTitle', 'BOOKING CONFIRMATION');

		$this->db->where('CancelStatus', 'N');

		$this->db->where('booking.Status !=', 'N');

		$this->db->where('CAST(booking.InsertDate AS DATE) >=', date('Y-m-d', strtotime('This Week Monday')));

		$this->db->where('CAST(booking.InsertDate AS DATE) <=', date('Y-m-d', strtotime('This Week Sunday')));

		$this->db->where('Level !=', 30);

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

		$this->db->group_by('SalesAgent');

		$this->db->group_by('Month');

		$this->db->order_by('Month', 'ASC');

		$monthly_sales = $this->db->get('booking');

		return $monthly_sales->result();

	}



	function Daily_Sales()

	{

		$this->db->select('SUM(NetTotal) AS Sales, CAST(InsertDate AS DATE) As Date');

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

		$this->db->select('(NetTotal - SUM(Credit) + SUM(DEbit)) As OutstandingBalance,

		MONTH(CASE WHEN AdditionalPaymentDeadline IS NULL OR FullPaymentDeadline > AdditionalPaymentDeadline THEN FullPaymentDeadline

		ELSE AdditionalPaymentDeadline

		END) As Month');

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

		$this->db->select('BookingNumber, Customer, StartDate, EndDate, AdminID, admin.Name As SalesAgent, category.Name As Destination');

		$this->db->join('admin', 'admin.AdminID = booking.SalesAgent', 'left');

		$this->db->join('category', 'category.CategoryID = booking.Destination', 'left');

		// Use custom date range if provided, otherwise default to next 7 days
		if ($start_date && $end_date) {
			$this->db->where('StartDate >=', $start_date);
			$this->db->where('StartDate <=', $end_date);
		} elseif ($start_date) {
			$this->db->where('StartDate >=', $start_date);
		} elseif ($end_date) {
			$this->db->where('StartDate <=', $end_date);
		} else {
			$this->db->where('StartDate >=', date('Y-m-d', strtotime('+ 1 day')));
			$this->db->where('StartDate <=', date('Y-m-d', strtotime('+ 7 days')));
		}

		$this->db->where('BookingConfirmationTitle', 'BOOKING CONFIRMATION');

		$this->db->where('CancelStatus', 'N');

		$this->db->where('booking.Status', 'PTV');

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

		$this->db->group_by('BookingNumber');

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

		$this->db->group_by('SalesAgent');

		$this->db->order_by('Sales', 'DESC');

		$rating = $this->db->get('booking');

		return $rating->result();

	}

}