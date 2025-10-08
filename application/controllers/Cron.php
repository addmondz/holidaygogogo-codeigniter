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
		$this->load->model('Universal_Model');
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
		$this->syncBookings();
		$this->syncPayments();
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
		if (!empty($booking['salesAgent'])) {
			$sale_agent = $this->Admin_Model->find($booking['salesAgent']);
			if ($sale_agent) {
				$booking['salesAgent'] = $sale_agent['Name'];
			}
		}

		//validity
		if (!empty($booking['StartDate']) && !empty($booking['EndDate'])) {
			$booking['validity'] = $booking['StartDate'] . '-' . $booking['EndDate'];
			$booking['BookingRemark'] = $booking['StartDate'] . '-' . $booking['EndDate'];
		}

		// yourRef
		if (!empty($booking['ReservationNumber'])) {
			$booking['yourRef'] = $booking['ReservationNumber'];
			$booking['remark2'] = $booking['ReservationNumber'];
		}

		// cc 
		if (!empty($booking['Adult'])) {
			$booking['cc'] = $booking['Adult'] . ',';
		}
		if (!empty($booking['Children'])) {
			$booking['cc'] .= $booking['Children'] . ',';
		}
		if (!empty($booking['InFant'])) {
			$booking['cc'] .= $booking['InFant'];
		}
		if (!empty($booking['cc'])) {
			$booking['cc'] = rtrim($booking['cc'],',');
			$booking['remark3'] = $booking['cc'];
		}

		// deliveryTerm  
		if (!empty($booking['deliveryTerm'])) {
			$booking['deliveryTerm'] = $booking['Destination'];
			$booking['remark4'] = $booking['Destination'];
		}

		return $booking;
	}

    /**
     * Sync Payments
     */
  	private function syncPayments()
	{
		echo "=== Sync Payments Start ===\n";
		$payments = $this->Payment_Model->getAllPaymentsWithBookingAndSupplier();

		$this->load->library('PaymentSync');
		$this->load->helper('autocount');
		$config = get_autocount_config();
		
		foreach ($payments as $payment) {
			echo "Payment ID {$payment['PaymentID']} [{$payment['AutocountSyncAction']}]... ";

			$payment = $this->enrichPayment($payment);

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

		echo "=== Sync Payments End ===\n\n";
	}

	private function enrichPayment($payment)
	{
		// Sales agent
		if (!empty($payment['salesAgent'])) {
			$sale_agent = $this->Admin_Model->find($payment['salesAgent']);
			if ($sale_agent) {
				$payment['salesAgent'] = $sale_agent['Name'];
			}
		}

		// Payment details description
		if (!empty($payment['Customer'])) {
			$payment['detail_description'] = $payment['Customer'];
		}

		if (!empty($payment['StartDate']) && !empty($payment['EndDate'])) {
			$payment['travelDate'] = $payment['StartDate'] . '-' . $payment['EndDate'];

			if (!empty($payment['detail_description'])) {
				$payment['detail_description'] .= ' (' . $payment['travelDate'] . ')';
			} else {
				$payment['detail_description'] = $payment['travelDate'];
			}
		}

				

		return $payment;
	}

}