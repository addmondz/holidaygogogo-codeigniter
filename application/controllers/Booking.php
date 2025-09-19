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
	}

	function index()
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
	
	function Create()
	{
		if(in_array('GB', $this->session->access_control)) {
			 if ($this->input->is_ajax_request()) {

            $booking_id = $this->Booking_Model->Create();

            $this->Booking_Product_Model->Create($this->input->post('booking_products'), $booking_id);

            $bookingData = $this->Booking_Model->getAllBookingsWithGuests($booking_id);
            if (!empty($bookingData)) {
                $bookingData = (array) $bookingData[0]; 
            }

            $bookingProducts = $this->Booking_Model->getAllBookingsWithProducts($booking_id);
            $bookingProducts = array_map('get_object_vars', $bookingProducts); // convert to array

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

			$booking = Booking::find($booking_id);
			if ($booking != null) {
				$booking->AutocountSyncStatus = 'C';
				$booking->AutocountSyncMessage = $respond;
				$booking->update();
			}	
        } else {
				$titles = array('tab_title' => 'HolidayGoGoGo | Booking', 'breadcrumb_title' => 'Booking >> Create');
				$array = array('BookingID' => 'NA', 'BookingConfirmationFooterID' => 'NA', 'TravelVoucherFooterID' => 'NA', 'BookingNumber' => 'NA', 'Tag' => array(), 'Discount' => 'NA', 'NetTotal' => 'NA', 'ProductSequence' => array(), 'BookingProductID' => ($this->Booking_Product_Model->Read_Last_Booking_Product_ID()) + 1);
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

				/*** Fetch Fresh Booking + Products from DB ***/
				$booking_id = $this->input->post('booking_id');
				$bookingData = $this->Booking_Model->getAllBookingsWithGuests($booking_id);
				$bookingProducts = $this->Booking_Product_Model->getAllBookingsWithProducts($booking_id);

				// Convert objects to arrays
				$bookingProducts = array_map('get_object_vars', $bookingProducts);

				/*** Call Quotation Update in AutoCount ***/
				$quotationData = [
					'BookingNumber'   => $bookingData['BookingNumber'],
					'DocNo'           => $bookingData['BookingNumber'], // Fallback
					'master'          => [
						'DocDate'        => $bookingData['InsertDate'],
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
					'tax_code'        => 'S-5', // Default tax code if missing
					'saveApprove'     => null
				];

				$respond = $this->autocount_update($quotationData);
				$booking = Booking::find($booking_id);
				if ($booking != null) {
					$booking->AutocountSyncStatus = 'U';
					$booking->AutocountSyncMessage = $respond;
					$booking->update();
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
			$statusMap = $this->config->item('booking_to_autocount_status');
			$newStatus = $this->input->get('new_status');
			$autoCountStatus = $statusMap[$newStatus] ?? 0; // default Pending

			$bookingData = $this->Booking_Model->getBookingById(
				$this->input->get('booking_id')
			);

			if (!empty($bookingData->BookingNumber)) {
				$quotationData = [
					'DocNo'  => $bookingData->BookingNumber,
					'master' => [
						'Status' => $autoCountStatus
					]
				];

				$respond = $this->autocount_update($quotationData);
				$booking = Booking::find($this->input->get('booking_id'));
				if ($booking != null) {
					$booking->AutocountSyncStatus = 'U';
					$booking->AutocountSyncMessage = $respond;
					$booking->update();
				}	
			}

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
			// Delete from AutoCount (Quotation)

			$bookingData = $this->Booking_Model->getBookingById($this->input->get('booking_id'));
        	$bookingNumber = (!empty($bookingData) && !empty($bookingData->BookingNumber)) ? $bookingData->BookingNumber : '';

			if (!empty($bookingNumber)) {
				$respond = $this->autocount_delete([
					'BookingNumber' => $bookingNumber
				]);

				$booking = Booking::find($this->input->get('booking_id'));
				if ($booking != null) {
					$booking->AutocountSyncStatus = 'D';
					$booking->AutocountSyncMessage = $respond;
					$booking->update();
				}	
			}
			
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
        $booking_ids = $this->input->post('booking_id'); 

        if (empty($booking_ids)) {
            return response()->json(['message' => 'No bookings selected'], 400);
        }

        $results = [];

        foreach ($booking_ids as $booking_id) {
            try {
                $booking = Booking::find($booking_id);
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
                        $booking->AutocountSyncStatus = 'C_S';
						$booking->AutocountSyncMessage = $respond;

                        $results[$booking->id] = 'Created';
                        break;

                    case 'U': // update
                    case 'C': // created → still allow update
						$quotationData = [
							'BookingNumber'   => $bookingData['BookingNumber'],
							'DocNo'           => $bookingData['BookingNumber'], // Fallback
							'master'          => [
								'DocDate'        => $bookingData['InsertDate'],
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
							'tax_code'        => 'S-5', // Default tax code if missing
							'saveApprove'     => null
						];
                        $respond = $this->autocount_update($booking);
                        $booking->AutocountSyncStatus = 'U_S'; // keep as created
						$booking->AutocountSyncMessage = $respond;

                        $results[$booking->id] = 'Updated';
                        break;

                    case 'D': // delete
						$bookingNumber = (!empty($booking) && !empty($booking->BookingNumber)) ? $booking->BookingNumber : '';

						if (!empty($bookingNumber)) {
							$respond = $this->autocount_delete([
								'BookingNumber' => $bookingNumber
							]);

							$booking->AutocountSyncStatus = 'D_S';
							$booking->AutocountSyncMessage = $respond;
							$results[$booking->id] = 'Deleted';
							break;
						}
                    case 'V': // void
						$bookingNumber = (!empty($booking) && !empty($booking->BookingNumber)) ? $booking->BookingNumber : '';

						if (!empty($bookingNumber)) {
							$this->autocount_void([
								'BookingNumber' => $bookingNumber
							]);
						}
                        $respond = $this->autocount_void($booking);
                        $booking->AutocountSyncStatus = 'V_S';
						$booking->AutocountSyncMessage = $respond;
                        $results[$booking->id] = 'Voided';
                        break;

                    default:
                        $results[$booking->id] = 'Skipped';
                        break;
                }

                $booking->save();
            } catch (\Exception $e) {
                $results[$bookingData['id']] = 'Error: ' . $e->getMessage();
            }
        }

        return response()->json([
            'message' => 'Sync process completed',
            'results' => $results,
        ]);
    }

	public function autocount_create($data = [])
    {
        $param = [
            'master' => [
                'DocNo'           => $data['BookingNumber'],
                'DocNoFormatName' => null,
                'DocDate'         => $data['InsertDate'],
                'DebtorCode'      => '',
                'DebtorName'      => $data['Customer'],
                'Email'           => $data['guest_email'],
                'EmailCC'         => null,
                'EmailBCC'        => null,
                'Address'         => $data['guest_address'],
                'Attention'       => '',
                'Phone1'          => $data['guest_phone'],
                'Fax1'            => '',
                'DeliverAddress'  => $data['guest_address'],
                'DeliverContact'  => $data['Customer'],
                'DeliverPhone1'   => $data['guest_phone'],
                'DeliverFax1'     => '',
                'Ref'             => null,
                'Description'     => null,
                'Note'            => null,
                'SalesAgent'      => '',
                'CreditTerm'      => $data['credit_term'] ?? 'C.O.D.',
                'SalesLocation'   => $data['sales_location'] ?? 'HQ',
                'Remark1'         => $data['BokingRemark'],
                'Remark2'         => null,
                'Remark3'         => null,
                'Remark4'         => null,
                'CurrencyRate'    => $data['currency_rate'],
                'InclusiveTax'    => $data['inclusive_tax'] ?? false,
                'IsRoundAdj'      => $data['is_round_adj'] ?? false,
                'YourRef'         => null,
                'Validity'        => null,
                'CC'              => null,
                'DeliveryTerm'    => null,
                'PaymentTerm'     => null
            ],
            'details' => [],
            'autoFillOption' => [
                'TaxCode' => $data['tax_code'] ?? true
            ],
            'saveApprove' => null
        ];

        if (!empty($data['booking_product'])) {
            foreach ($data['booking_product'] as $product) {
                $param['details'][] = [
                    'ProductCode'        => $product['product_ProductCode'],
                    'ProductVariant'     => null,
                    'Description'        => $product['product_Description'],
                    'FurtherDescription' => '',
                    'Qty'                => $product['product_Quantity'],
                    'Unit'               => isset($product['unit']) ? $product['unit'] : 'unit',
                    'UnitPrice'          => $product['product_Price'],
                    'Discount'           => null,
                    'TaxCode'            => isset($product['tax_code']) ? $product['tax_code'] : 'S-5',
                    'TaxAdjustment'      => 0,
                    'LocalTaxAdjustment' => 0,
                    'DeptNo'             => null
                ];
            }
        }

        return autocount_request('POST', 'quotation.create', $param);
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

        if (!empty($data['booking_product']) && is_array($data['booking_product'])) {
            $body['details'] = [];
            foreach ($data['booking_product'] as $product) {
                $body['details'][] = [
                    'ProductCode'        => $product['product_ProductCode'],
                    'ProductVariant'     => null,
                    'Description'        => $product['product_Description'],
                    'FurtherDescription' => '',
                    'Qty'                => $product['product_Quantity'],
                    'Unit'               => isset($product['unit']) ? $product['unit'] : 'unit',
                    'UnitPrice'          => $product['product_Price'],
                    'Discount'           => null,
                    'TaxCode'            => isset($product['tax_code']) ? $product['tax_code'] : 'S-5',
                    'TaxAdjustment'      => 0,
                    'LocalTaxAdjustment' => 0,
                    'DeptNo'             => null
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
            'quotation.update',
            $body,
            ['docNo' => $docNo]
        );
    }

    public function autocount_update_status($data = [])
    {
        $docNo = $data['BookingNumber'] ?? '';
        $body = [
            'documentStatus' => $data['Status'] ?? '',
            'lostReason'     => $data['reason'] ?? ''
        ];

        return autocount_request(
            'PUT',
            'quotation.update_status',
            $body,
            ['docNo' => $docNo]
        );
    }

    public function autocount_delete($data = [])
    {
        $docNo = $data['BookingNumber'] ?? '';

        return autocount_request(
            'DELETE',
            'quotation.delete',
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
            'quotation.void',
            $body,
            ['docNo' => $docNo]
        );
        
    }    
}