<?php

require FCPATH.'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;  
use PhpOffice\PhpSpreadsheet\Writer\Xlxs;

class Cron extends CI_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->load->model('Booking_Model');
		$this->load->model('Booking_Product_Model');
		$this->load->model('Payment_Model');
		$this->load->model('Admin_Model');
		$this->load->model('Category_Model');
		$this->load->model('Supplier_Model');
		$this->load->model('Booking_Model');
		$this->load->model('Customer_Model');
		$this->load->library('AutoCountService'); // <-- where you put Guzzle API
	}

	function index()
	{

		// 1. Get all the bookings
		$bookings = $this->Booking_Model->Read_All_Bookings();

		// 2. Run the logic
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

		echo "DONE!";
	}

	function old_index()
	{
		$startTime = date("Y-m-d H:i:s.u");
		if(in_array('VB', $this->session->access_control)) {
			$titles = array('tab_title' => 'HolidayGoGoGo | Booking', 'breadcrumb_title' => 'Booking');
			$array = array('total_sales' => 0, 'total_net_profit' => 0);
			$bookings = $this->Booking_Model->Read_All_Bookings();
			$array['admins'] = $this->Booking_Model->Read_Admins();
			$array['categories'] = $this->Booking_Model->Read_Categories();
			$array['tags'] = $this->Booking_Model->Read_Tags();
			$array['sources'] = $this->Booking_Model->Read_Sources();
			$total_sales = 0;
			$total_net_profit = 0;

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
			

			$array['bookings'] = $this->Booking_Model->Read_Bookings();

			if(isset($_GET['nick'])) {
				print_r($this->db->last_query());exit;
			}

			foreach($array['bookings'] as $booking1) {
				$booking1->CustomerMobile = $booking1->CountryCode . str_replace([' ', '-'], '', $booking1->CustomerMobile);
				if(!empty($booking1->StartDate)) {
					$booking1->StartDate = strtoupper(date('j M Y', strtotime($booking1->StartDate)));
				} else {
					$booking1->StartDate = null;
				}
				if(!empty($booking1->EndDate)) {
					$booking1->EndDate = strtoupper(date('j M Y', strtotime($booking1->EndDate)));
				} else {
					$booking1->EndDate = null;
				}
				if($booking1->BookingConfirmationTitle == 'BOOKING CONFIRMATION') {
					$booking1->BookingConfirmationTitle = 'BC';
				} else {
					if($booking1->BookingConfirmationTitle == 'QUOTATION') {
						$booking1->BookingConfirmationTitle = 'QU';
					} else {
						$booking1->BookingConfirmationTitle = 'PI';
					}
				}

				$payments = $this->Booking_Model->Read_Payments($booking1->BookingID);
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
						$profit_margin = round(($net_profit / $booking1->NetTotal) * 100);
					}
				}
				$booking1->Profit = number_format($net_profit, 2, '.', ',');
				$booking1->ProfitMargin = $net_profit != 0 ? $profit_margin : 0;
				if($booking1->LockStatus == 'N' && $booking1->Status == 'PTV') {
					$booking1->Status = 'PGL';
				}
				if($booking1->AfterSalesService == 'PENDING' && $booking1->Status == 'Y') {
					$booking1->Status = 'PR';
				}
				if(empty($booking1->DepositDeadline)) {
					if(date('Y-m-d') > $booking1->FullPaymentDeadline && ($booking1->Status == 'P' || $booking1->Status == 'PP')) {
						$booking1->Status = 'PO';
					}
				} else {
					if((date('Y-m-d') > $booking1->DepositDeadline && $booking1->Status == 'P') || (date('Y-m-d') > $booking1->FullPaymentDeadline && ($booking1->Status == 'P' || $booking1->Status == 'PP'))) {
						$booking1->Status = 'PO';
					}
				}
				$total_sales += $booking1->NetTotal;
				$total_net_profit += $net_profit;
				$booking1->NetTotal = number_format($booking1->NetTotal, 2, '.', ',');
				$booking1->InsertDate = strtoupper(date('j M Y', strtotime($booking1->InsertDate)));

			}
			
			$array['total_sales'] = number_format($total_sales, 2, '.', ',');
			$array['total_net_profit'] = $total_net_profit != 0 && $total_sales != 0 ? number_format($total_net_profit, 2, '.', ',') . ' (' . round(($total_net_profit / $total_sales) * 100) . '%)' : number_format($total_net_profit, 2, '.', ',') . ' (0%)';
			$this->load->view('layout/header', $titles);
			$this->load->view('booking/index', $array);
			$this->load->view('layout/footer');
			
		} else {
			redirect('Dashboard');
		}
	}

	/**
     * Master function: run all sync jobs
     */
    public function syncAll()
    {
		// check if request is from CLI (cron job)
		if (is_cli()) {
			$this->runSync();
			return;
		}
		$this->load->helper('autocount');
		$config = get_autocount_config();
		
		$apiKey = $this->input->get('key');
    	$expectedKey = $config['manual_sync_autocount_key']; // store in config or .env

		if ($apiKey !== $expectedKey) {
			show_error('Unauthorized access', 401);
			return;
		}
		$this->runSync();
    }
	
	private function runSync()
	{
		$this->syncCustomer();
		$this->syncSupplier();
		$this->syncBookings();
		$this->syncPayments();
		$this->syncDeletedPayments();
	}

	/**
     * Sync Customer
     */
	private function syncCustomer()
	{
		echo "=== Sync Customer Start ===\n";

		$customers = $this->Customer_Model->get_pending_sycn_customers();

		$this->load->library('CustomerSync');
		$this->load->helper('autocount');
		$config = get_autocount_config();
		
		foreach ($customers as $customer) {
			echo "Customer {$customer['CustomerID']} [{$customer['AutocountSyncAction']}]... ";

			$customer = $this->enrichCustomer($customer);

			try {
				switch ($customer['AutocountSyncAction']) {
					case 'C':
						$result = $this->customersync->autocount_create($customer, $config);
						break;
					case 'U':
						$result = $this->customersync->autocount_update($customer, $config);
						break;
					case 'S':
						$result = $this->customersync->autocount_update_status($customer, $config);
						break;
					case 'D':
						$result = $this->customersync->autocount_delete($customer, $config);
						break;
					default:
						$result = ['error' => 'ERROR Autocount Sync Action'];
				}
				if (isset($result['status']) && ($result['status'] == 201 || $result['status'] == 204) && $result['error'] === null) {
					$this->Customer_Model->update_by_id($customer['CustomerID'], [
						'AutocountSyncStatus'  => 'S',
						'AutocountSyncMessage' => json_encode($result)
					]);

					if (isset($result['docNo']) && !empty($result['docNo'])) {
						$updateData['CustomerCode'] = $result['docNo'];
					}
					if (!empty($updateData)) {
						$this->Customer_Model->update_by_id($customer['CustomerID'], $updateData);
					}
					echo "SUCCESS\n";
				} else {
					$this->Customer_Model->update_by_id($customer['CustomerID'], [
						'AutocountSyncStatus'  => 'F',
						'AutocountSyncMessage' => json_encode($result)
					]);
					echo "FAILED\n";
				}
			} catch (\Exception $e) {
				$this->Customer_Model->update_by_id($customer['CustomerID'], [
					'AutocountSyncStatus'  => 'F',
					'AutocountSyncMessage' => $e->getMessage()
				]);
				echo "ERROR: {$e->getMessage()}\n";
			}
		}
		echo "=== Sync Customer End ===\n\n";
	}

	private function enrichCustomer($customer)
	{
		return $customer;
	}

	/**
     * Sync Supplier
     */
	private function syncSupplier() // creditor
	{
		echo "=== Sync Supplier Start ===\n";

		$suppliers = $this->Supplier_Model->get_pending_sycn_suppliers();

		$this->load->library('SupplierSync');
		$this->load->helper('autocount');
		$config = get_autocount_config();
		
		foreach ($suppliers as $supplier) {
			echo "Supplier ID {$supplier['SupplierID']} [{$supplier['AutocountSyncAction']}]... ";

			$supplier = $this->enrichSupplier($supplier);

			try {
				switch ($supplier['AutocountSyncAction']) {
					case 'C':
						$result = $this->suppliersync->autocount_create($supplier,$config);
						break;
					case 'U':
						$result = $this->suppliersync->autocount_update($supplier, $config);
						break;
					case 'S':
						$result = $this->suppliersync->autocount_update_status($supplier, $config);
						break;
					case 'D':
						$result = $this->suppliersync->autocount_delete($supplier, $config);
						break;
					default:
						$result = ['error' => 'ERROR Autocount Sync Action'];
				}
				if (isset($result['status']) && ($result['status'] == 201 || $result['status'] == 204) && $result['error'] === null) {
					$this->Supplier_Model->update_by_id($supplier['SupplierID'], [
						'AutocountSyncStatus'  => 'S',
						'AutocountSyncMessage' => json_encode($result)
					]);

					if (isset($result['docNo']) && !empty($result['docNo'])) {
						$updateData['SupplierCode'] = $result['docNo'];
					}
					if (!empty($updateData)){
						$this->Supplier_Model->update_by_id($supplier['SupplierID'], $updateData);
					}
					echo "SUCCESS\n";
				} else {
					$this->Supplier_Model->update_by_id($supplier['SupplierID'], [
						'AutocountSyncStatus'  => 'F',
						'AutocountSyncMessage' => json_encode($result)
					]);
					echo "FAILED\n";
				}
			} catch (\Exception $e) {
				$this->Supplier_Model->update_by_id($supplier['SupplierID'], [
					'AutocountSyncStatus'  => 'F',
					'AutocountSyncMessage' => $e->getMessage()
				]);
				echo "ERROR: {$e->getMessage()}\n";
			}
		}
		echo "=== Sync Suppliers End ===\n\n";
	}

	private function enrichSupplier($supplier)
	{
		return $supplier;
	}

    /**
     * Sync Bookings
     */
	private function syncBookings()
	{
		echo "=== Sync Booking Start ===\n";

		$bookings = $this->Booking_Model->getPendingBookingsWithDetails();

		$this->load->library('BookingSync');
		$this->load->helper('autocount');
		$config = get_autocount_config();
		
		foreach ($bookings as $booking) {
			echo "Booking ID {$booking['BookingID']} [{$booking['AutocountSyncAction']}]... ";

			$booking = $this->enrichBooking($booking);

			try {
				switch ($booking['AutocountSyncAction']) {
					case 'C':
						$result = $this->bookingsync->autocount_create($booking);
						break;
					case 'U':
						$result = $this->bookingsync->autocount_update($booking);
						break;
					case 'S':
						$result = $this->bookingsync->autocount_update_status($booking);
						break;
					case 'D':
						$result = $this->bookingsync->autocount_delete($booking);
						break;
					default:
						$result = ['error' => 'ERROR Autocount Sync Action'];
				}
				if (isset($result['status']) && ($result['status'] == 201 || $result['status'] == 204) && $result['error'] === null) {
					$this->Booking_Model->update_by_id($booking['BookingID'], [
						'AutocountSyncStatus'  => 'S',
						'AutocountSyncMessage' => json_encode($result)
					]);
					echo "SUCCESS\n";
				} else {
					$this->Booking_Model->update_by_id($booking['BookingID'], [
						'AutocountSyncStatus'  => 'F',
						'AutocountSyncMessage' => json_encode($result)
					]);
					echo "FAILED\n";
				}
			} catch (\Exception $e) {
				$this->Booking_Model->update_by_id($booking['BookingID'], [
					'AutocountSyncStatus'  => 'F',
					'AutocountSyncMessage' => $e->getMessage()
				]);
				echo "ERROR: {$e->getMessage()}\n";
			}
		}
		echo "=== Sync Bookings End ===\n\n";
	}


	private function enrichBooking($booking)
	{
		// Sales agent
		if (!empty($booking['SalesAgent'])) {
			$sale_agent = $this->Admin_Model->find($booking['SalesAgent']);
			if ($sale_agent) {
				$booking['salesAgent'] = $sale_agent->Name;
			}
		}

		//validity
		if (!empty($booking['StartDate']) && !empty($booking['EndDate'])) {
			$booking['validity'] = $booking['StartDate'] . ' - ' . $booking['EndDate'];
			$booking['BokingRemark'] = $booking['StartDate'] . ' - ' . $booking['EndDate'];
		}

		// yourRef
		if (!empty($booking['ReservationNumber'])) {
			$booking['yourRef'] = $booking['ReservationNumber'];
			$booking['remark2'] = $booking['ReservationNumber'];
		}

		// cc 
		// Check for Adult, Children, and Infant; if empty, set to 0
		$booking['cc'] = '';

		$booking['cc'] .= (!empty($booking['Adult']) ? $booking['Adult'] : 0) . ' A, ';
		$booking['cc'] .= (!empty($booking['Children']) ? $booking['Children'] : 0) . ' C, ';
		$booking['cc'] .= (!empty($booking['Infant']) ? $booking['Infant'] : 0) . ' IN';

		// Remove trailing comma and space
		$booking['cc'] = rtrim($booking['cc'], ', ');

		if (!empty($booking['cc'])) {
			$booking['remark3'] = $booking['cc'];
		}

		// deliveryTerm  
		if (!empty($booking['Destination'])) {
			$Destination = $this->Category_Model->find($booking['Destination']);
			if ($Destination) {
				$booking['Destination'] = $Destination->Name;
				$booking['deliveryTerm'] = $booking['Destination'];
				$booking['remark4'] = $booking['Destination'];
			}
		}

		if (!empty($booking['CustomerID'])) {
			$customer = $this->Customer_Model->find($booking['CustomerID']);
			if (!empty($customer)) {
				if (!empty($customer->CustomerCode) && $customer->CustomerCode != null) {
					$booking['CustomerCode'] = $customer->CustomerCode;
				}
			}
		}

		return $booking;
	}

    /**
     * Sync Payments
     */
  	private function syncPayments()
	{
		echo "=== Sync Payments Start ===\n";
		$payments = $this->Payment_Model->getAllPaymentsWithBookingAndSupplier(null, false);

		$this->load->library('PaymentSync');
		$this->load->helper('autocount');
		$config = get_autocount_config();
		
		foreach ($payments as $payment) {
			echo "Payment ID {$payment['PaymentID']} [{$payment['AutocountSyncAction']}]... ";

			$payment = $this->enrichPayment($payment, $config);

			try {
				switch ($payment['AutocountSyncAction']) {
					case 'C':
						$result = $this->paymentsync->autocount_create($payment, $config);
						break;
					case 'U':
						$result = $this->paymentsync->autocount_update($payment, $config);
						break;
					case 'D':
						$result = $this->paymentsync->autocount_delete($payment, $config);
						break;
					default:
						$result = ['error' => 'ERROR Autocount Sync Action'];
				}

				if (isset($result['status']) && ($result['status'] == 201 || $result['status'] == 204) && $result['error'] === null) {
					$updateData = [
						'AutocountSyncStatus'  => 'S',
						'AutocountSyncMessage' => json_encode($result)
					];

					if (isset($result['docNo']) && !empty($result['docNo'])) {
						$updateData['AutocountReferenceNumber'] = $result['docNo'];
					}
					if (!empty($updateData)){
						$this->Payment_Model->update_by_id($payment['PaymentID'], $updateData);
					}
					echo "SUCCESS\n";
				} else {
					$this->Payment_Model->update_by_id($payment['PaymentID'], [
						'AutocountSyncStatus'  => 'F',
						'AutocountSyncMessage' => json_encode($result)
					]);
					echo "FAILED: " . ($result['error'] ?? 'Unknown error') . "\n";
				}

			} catch (\Exception $e) {
				$this->Payment_Model->update_by_id($payment['PaymentID'], [
					'AutocountSyncStatus'  => 'F',
					'AutocountSyncMessage' => $e->getMessage()
				]);
				echo "ERROR: {$e->getMessage()}\n";
			}
		}

		echo "=== Sync Payments End ===\n\n";
	}

	private function syncDeletedPayments()
	{
		echo "=== Sync Deleted Payments Start ===\n";
		$payments = $this->Payment_Model->getAllPaymentsWithBookingAndSupplier(null, true);

		$this->load->library('PaymentSync');
		$this->load->helper('autocount');
		$config = get_autocount_config();
		
		foreach ($payments as $payment) {
			echo "Payment ID {$payment['PaymentID']} [{$payment['AutocountSyncAction']}]... ";

			$payment = $this->enrichPayment($payment, $config);

			try {
				switch ($payment['AutocountSyncAction']) {
					case 'D':
						$result = $this->paymentsync->autocount_delete($payment, $config);
						break;
					default:
						$result = ['error' => 'ERROR Autocount Sync Action'];
				}

				if (isset($result['status']) && ($result['status'] == 201 || $result['status'] == 204) && $result['error'] === null) {
					$this->Payment_Model->update_by_id($payment['PaymentID'], [
						'AutocountSyncStatus'  => 'S',
						'AutocountSyncMessage' => json_encode($result)
					]);
					echo "SUCCESS\n";
				} else {
					$this->Payment_Model->update_by_id($payment['PaymentID'], [
						'AutocountSyncStatus'  => 'F',
						'AutocountSyncMessage' => json_encode($result)
					]);
					echo "FAILED: " . ($result['error'] ?? 'Unknown error') . "\n";
				}

			} catch (\Exception $e) {
				$this->Payment_Model->update_by_id($payment['PaymentID'], [
					'AutocountSyncStatus'  => 'F',
					'AutocountSyncMessage' => $e->getMessage()
				]);
				echo "ERROR: {$e->getMessage()}\n";
			}
		}

		echo "=== Sync Deleted Payments End ===\n\n";
	}

	private function enrichPayment($payment, $config)
	{
		// Sales agent
		if (!empty($payment['SalesAgent'])) {
			$sale_agent = $this->Admin_Model->find($payment['SalesAgent']);
			if ($sale_agent) {
				$payment['salesAgent'] = $sale_agent->Name;
			}
		}

		// description
		$desc = '';
		if ($payment['Credit'] != 0.00) { // OR
			$desc = !empty($payment['Customer']) ? $payment['Customer'] : '';
		} else { // PV
			$desc = !empty($payment['supplier_name']) ? $payment['supplier_name'] : (!empty($payment['Customer']) ? $payment['Customer'] : '');
		}

		if (!empty($payment['StartDate']) && !empty($payment['EndDate'])) {
			$travelDate = $payment['StartDate'] . ' - ' . $payment['EndDate'];
			$desc = !empty($desc) ? $desc . ' (' . $travelDate . ')' : $travelDate;
			$payment['travelDate'] = $travelDate;
		}

		if (!empty($payment['Type'])) {
			$desc .= ' ' . $payment['Type'];  // Always append Type last
		}

		$payment['description'] = trim($desc);

		// deal with 
		if ($payment['Credit'] != 0.00) { // OR
			$payment['dealWith'] = !empty($payment['Customer']) ? $payment['Customer'] : '';
		} else { // PV
			$payment['dealWith'] = !empty($payment['supplier_name']) ? $payment['supplier_name'] : '';
		}
		if (!empty($payment['Type'])) {
			$type = [
				'AGENT COMMISSION',
				'CUSTOMER REFUND',
				'BANK CHARGES',
				'CREDIT CARD CHARGES',
				'ONE-TIME PAYMENT'
			];		
			if (in_array($payment['Type'], $type)) {
				$payment['dealWith'] = $payment['BankHolder'];
			}
		}

		$payment['CustomerCode'] = $config['payment_acc_no_1'];
		if (!empty($payment['CustomerID'])) {
			$customer = $this->Customer_Model->find($payment['CustomerID']);
			if (!empty($customer)) {
				if (!empty($customer->CustomerCode) && $customer->CustomerCode != null) {
					$payment['CustomerCode'] = $customer->CustomerCode;
				}
			}
		}

		$payment['SupplierCode'] = $config['payment_acc_no_2'];
		if (!empty($payment['SupplierID'])) {
			$supplier = $this->Supplier_Model->find($payment['SupplierID']);
			if (!empty($supplier)) {
				if (!empty($supplier->SupplierCode) && $supplier->SupplierCode != null) {
					$payment['SupplierCode'] = $supplier->SupplierCode;
				}
			}
		}

		return $payment;
	}

}