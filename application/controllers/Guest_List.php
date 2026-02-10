<?php

require FCPATH.'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlxs;

class Guest_List extends CI_Controller
{
	function __construct()
	{
		$env = [];
		if (file_exists(FCPATH . '.env')) {
			$lines = file(FCPATH . '.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
			foreach ($lines as $line) {
				if (strpos(trim($line), '#') === 0) continue;
				list($key, $value) = explode('=', $line, 2);
				$env[trim($key)] = trim($value);
			}
		}
		$baseUrl = $env['BASE_URL'];
		$domain = parse_url($baseUrl, PHP_URL_HOST);

		parent::__construct();
		if(empty($this->session->userdata('admin_id')) && $_SERVER['SERVER_NAME'] != $domain) {
			redirect($baseUrl . '?gl=' . $this->input->get('gl'));
		}
		$this->load->model('Guest_List_Model');
		$this->load->model('Booking_Model');
		$this->load->model('Universal_Model');
	}

	function index() 
	{
		if($this->input->post()) {
			$booking_id = $this->Guest_List_Model->Read_Booking_ID();
			if($this->Guest_List_Model->Update() || !empty($this->input->post('new_guests')) || !empty($this->input->post('deleted_guests'))) {
				if(!empty($this->input->post('new_guests'))) {
					$this->Booking_Model->Update_Pax_Number($booking_id);
					$this->Guest_List_Model->Create_Guest($booking_id);
				}
				if(!empty($this->input->post('deleted_guests'))) {
					$this->Booking_Model->Update_Pax_Number($booking_id);
					$deleted_guests = explode(',', $this->input->post('deleted_guests'));
					for($i = 0; $i < count($deleted_guests); $i++) {
						$this->Guest_List_Model->Delete($deleted_guests[$i]);
					}
				}
				$bookingInfo = get_object_vars($this->Booking_Model->find($booking_id));
				if (!empty($bookingInfo)) {
					if (($bookingInfo['AutocountSyncAction'] == 'C' || $bookingInfo['AutocountSyncAction'] == 'U') && $bookingInfo['AutocountSyncStatus'] == 'S') {
							$this->Booking_Model->update_by_id($booking_id, [
							'AutocountSyncAction' => 'U',
							'AutocountSyncStatus' => 'P'
						]);				
					} else {
						$this->Booking_Model->update_by_id($booking_id, [
							'AutocountSyncStatus' => 'P'
						]);
					}
				}
			}
			$this->Guest_List_Model->Update_GL_Session('BookingID', $booking_id, 'N', null);
			redirect('Message?url=' . base_url($_SERVER['REQUEST_URI']));
		} else {
			if(!empty($this->input->get('gl'))) {
	    		$array['guest_lists'] = $this->Guest_List_Model->Read_Guest_Lists1();
	    		if(!empty($array['guest_lists'])) {
					if(($this->session->has_userdata('admin_id') && $this->session->has_userdata('level')) || ($array['guest_lists'][0]->Status != 'Y' && $array['guest_lists'][0]->AfterSalesService != 'COMPLETE' || $array['guest_lists'][0]->Status == 'Y' && $array['guest_lists'][0]->AfterSalesService == 'PENDING')) {
						if(true) {
						//if(($array['guest_lists'][0]->GLSessionLock == 'N' && empty($array['guest_lists'][0]->GLSessionExpiration)) || ($array['guest_lists'][0]->GLSessionLock == 'Y' && date('Y-m-d H:i:s') > $array['guest_lists'][0]->GLSessionExpiration)) {
							if($array['guest_lists'][0]->LockStatus == 'N' && ($this->agent->is_browser() || $this->agent->is_mobile())) {
								$this->Guest_List_Model->Create_Guest_List_Log($array['guest_lists'][0]->BookingID);
								$this->Guest_List_Model->Update_GL_Session('BookingNumber', $array['guest_lists'][0]->BookingNumber, 'Y', date('Y-m-d H:i:s', strtotime('+ 10 minutes')));
							}
							foreach($array['guest_lists'] as $guest) {
								if(!empty($guest->DateOfBirth)) {
									$guest->DateOfBirth = date('d/m/Y', strtotime($guest->DateOfBirth));
								}
								if(!empty($guest->Nationality)) {
									$guest->NationalityName = $this->Universal_Model->Read_Country($guest->Nationality);
								} else {
									$guest->NationalityName = null;
								}
							}
							$array['guest_lists'][0]->CustomerMobile = $array['guest_lists'][0]->CountryCode . $array['guest_lists'][0]->CustomerMobile;
							if(!empty($array['guest_lists'][0]->StartDate) && !empty($array['guest_lists'][0]->EndDate)) {
								$array['guest_lists'][0]->TravelDate = strtoupper(date('j M', strtotime($array['guest_lists'][0]->StartDate)) . ' - ' . date('j M Y', strtotime($array['guest_lists'][0]->EndDate)));
							} else {
								$array['guest_lists'][0]->TravelDate = '-';
							}
							if(!empty($array['guest_lists'][0]->Adult)) {
								$array['guest_lists'][0]->Adult = $array['guest_lists'][0]->Adult == 1 ? $array['guest_lists'][0]->Adult . ' ADULT ' : $array['guest_lists'][0]->Adult . ' ADULTS ';
							}
							if(!empty($array['guest_lists'][0]->Children)) {
								$array['guest_lists'][0]->Children = $array['guest_lists'][0]->Children == 1 ? $array['guest_lists'][0]->Children . ' CHILD ' : $array['guest_lists'][0]->Children . ' CHILDREN ';
							}
							if(!empty($array['guest_lists'][0]->Infant)) {
								$array['guest_lists'][0]->Infant = $array['guest_lists'][0]->Infant == 1 ? $array['guest_lists'][0]->Infant . ' INFANT ' : $array['guest_lists'][0]->Infant . ' INFANTS ';
							}
							if(!empty($array['guest_lists'][0]->Adult) && !empty($array['guest_lists'][0]->Children) && !empty($array['guest_lists'][0]->Infant)) {
								$array['guest_lists'][0]->PaxNumber = $array['guest_lists'][0]->Adult . '& ' . $array['guest_lists'][0]->Children . '& ' . $array['guest_lists'][0]->Infant;
							} else {
								if(!empty($array['guest_lists'][0]->Adult) && empty($array['guest_lists'][0]->Children) && !empty($array['guest_lists'][0]->Infant)) {
									$array['guest_lists'][0]->PaxNumber = $array['guest_lists'][0]->Adult . '& ' . $array['guest_lists'][0]->Infant;
								} else {
									if(!empty($array['guest_lists'][0]->Adult) && !empty($array['guest_lists'][0]->Children) && empty($array['guest_lists'][0]->Infant)) {
										$array['guest_lists'][0]->PaxNumber = $array['guest_lists'][0]->Adult . '& ' . $array['guest_lists'][0]->Children;
									} else {
										if(!empty($array['guest_lists'][0]->Adult) && empty($array['guest_lists'][0]->Children) && empty($array['guest_lists'][0]->Infant)) {
											$array['guest_lists'][0]->PaxNumber = $array['guest_lists'][0]->Adult;
										} else {
											if(empty($array['guest_lists'][0]->Adult) && !empty($array['guest_lists'][0]->Children) && !empty($array['guest_lists'][0]->Infant)) {
												$array['guest_lists'][0]->PaxNumber = $array['guest_lists'][0]->Children . '& ' . $array['guest_lists'][0]->Infant;
											} else {
												if(empty($array['guest_lists'][0]->Adult) && empty($array['guest_lists'][0]->Children) && !empty($array['guest_lists'][0]->Infant)) {
													$array['guest_lists'][0]->PaxNumber = $array['guest_lists'][0]->Infant;
												} else {
													$array['guest_lists'][0]->PaxNumber = $array['guest_lists'][0]->Children;
												}
											}
										}
									}
								}
							}
							$country_code = $this->Universal_Model->Read_Country_Code($array['guest_lists'][0]->SalesAgentCountryCode);
							$array['guest_lists'][0]->SalesAgentMobile = $country_code . $array['guest_lists'][0]->SalesAgentMobile;
							$array['country_codes'] = $this->Guest_List_Model->Read_Country_Codes();
							header('Cache-Control: no-cache, no-store, must-revalidate');
							header('Pragma: no-cache');
							header('Expires: 0');
							$this->load->view('booking/guest_list', $array);
						} else {							
							$array = $this->Guest_List_Model->Read_GL_Session_Expiration();
							$this->load->view('booking/access_denied', $array);
						}
					} else {
						$array = array('type' => 'Guest List');
                		$this->load->view('errors/bc_complete', $array);
					}
	    		} else {
					$this->load->view('errors/access_denied');
				}
	    	} else {
	    		$this->load->view('errors/access_denied');
	    	}
		}
    }

	function Update_GL_Session_Expiration() {
		$this->Guest_List_Model->Update_GL_Session('BookingID', $this->input->post('booking_id'), 'Y', date('Y-m-d H:i:s', strtotime('+ 12 minutes')));
	}
	
	function Populate_Form_Data() {
		$array = $this->Guest_List_Model->Read_Guest_Lists2();
		foreach($array as $guest) {
			if(!empty($guest->DateOfBirth)) {
				$guest->DateOfBirth = date('d/m/Y', strtotime($guest->DateOfBirth));
			}
		}
		if(empty($array[0]->Adult)) {
			$array[0]->Adult = 0;
		}
		if(empty($array[0]->Children)) {
			$array[0]->Children = 0;
		}
		if(empty($array[0]->Infant)) {
			$array[0]->Infant = 0;
		}
		echo json_encode($array);
	}
	
	function Download() {
		$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
		$spreadsheet->getActiveSheet()->setTitle('Guest Lists');
		$spreadsheet->getProperties()->setCreator('HolidayGoGoGo');
		$spreadsheet->getActiveSheet()->setCellValue('A1', 'BOOKING NUMBER');
		$spreadsheet->getActiveSheet()->setCellValue('B1', 'RESERVATION NUMBER');
		$spreadsheet->getActiveSheet()->setCellValue('C1', 'CUSTOMER FIRST NAME');
		$spreadsheet->getActiveSheet()->setCellValue('D1', 'CUSTOMER LAST NAME');
		$spreadsheet->getActiveSheet()->setCellValue('E1', 'CUSTOMER MOBILE');
		$spreadsheet->getActiveSheet()->setCellValue('F1', 'CHAT LANGUAGE');
		$spreadsheet->getActiveSheet()->setCellValue('G1', 'TRAVEL DATE');
		$spreadsheet->getActiveSheet()->setCellValue('H1', 'DESTINATION');
		$spreadsheet->getActiveSheet()->setCellValue('I1', 'SALES AGENT');
		$spreadsheet->getActiveSheet()->setCellValue('J1', 'GUEST TYPE');
		$spreadsheet->getActiveSheet()->setCellValue('K1', 'GUEST');
		$spreadsheet->getActiveSheet()->setCellValue('L1', 'GENDER');
		$spreadsheet->getActiveSheet()->setCellValue('M1', 'DATE OF BIRTH');
		$spreadsheet->getActiveSheet()->setCellValue('N1', 'NATIONALITY');
		$spreadsheet->getActiveSheet()->setCellValue('O1', 'GUEST IDENTIFICATION NUMBER');
		$spreadsheet->getActiveSheet()->setCellValue('P1', 'PASSPORT NUMBER');
		$spreadsheet->getActiveSheet()->setCellValue('Q1', 'GUEST MOBILE');
		$spreadsheet->getActiveSheet()->setCellValue('R1', 'EMAIL');
		$spreadsheet->getActiveSheet()->setCellValue('S1', 'MARITAL STATUS');
		$spreadsheet->getActiveSheet()->setCellValue('T1', 'EMPLOYMENT');
		$spreadsheet->getActiveSheet()->setCellValue('U1', 'ADDRESS');
		$spreadsheet->getActiveSheet()->setCellValue('V1', 'POSTCODE');
		$spreadsheet->getActiveSheet()->setCellValue('W1', 'CITY');
		$spreadsheet->getActiveSheet()->setCellValue('X1', 'STATE');
		$spreadsheet->getActiveSheet()->setCellValue('Y1', 'COUNTRY');
		$spreadsheet->getActiveSheet()->setCellValue('Z1', 'NOMINEE');
		$spreadsheet->getActiveSheet()->setCellValue('AA1', 'NOMINEE IDENTIFICATION NUMBER');
		$spreadsheet->getActiveSheet()->setCellValue('AB1', 'RELATIONSHIP');
		$row = 2;
		$guest_lists = $this->Guest_List_Model->Read_Guest_Lists1();
		$spreadsheet->getActiveSheet()->getStyle('A1:AB1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_BLACK);
		$spreadsheet->getActiveSheet()->getStyle('A1:AB1')->getFont()->getColor()->setARGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE);
		$spreadsheet->getActiveSheet()->getStyle('A1:AB1')->getFont()->setBold(true);
		foreach($guest_lists as $guest) {
			$guest->CustomerMobile = $guest->CountryCode . $guest->CustomerMobile;
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
			$spreadsheet->getActiveSheet()->setCellValueExplicit('B' . $row, $guest->ReservationNumber, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
			$spreadsheet->getActiveSheet()->setCellValueExplicit('C' . $row, $guest->Guest, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
			$spreadsheet->getActiveSheet()->setCellValueExplicit('D' . $row, $guest->GuestLastName, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
			$spreadsheet->getActiveSheet()->setCellValueExplicit('E' . $row, $guest->CustomerMobile, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
			$spreadsheet->getActiveSheet()->setCellValueExplicit('F' . $row, $guest->ChatLanguage, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
			$spreadsheet->getActiveSheet()->setCellValueExplicit('G' . $row, $guest->TravelDate, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
			$spreadsheet->getActiveSheet()->setCellValueExplicit('H' . $row, $guest->Destination, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
			$spreadsheet->getActiveSheet()->setCellValueExplicit('I' . $row, $guest->SalesAgent, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
			$spreadsheet->getActiveSheet()->setCellValueExplicit('J' . $row, $guest->Type, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
			$spreadsheet->getActiveSheet()->setCellValueExplicit('K' . $row, $guest->Guest, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
			$spreadsheet->getActiveSheet()->setCellValueExplicit('L' . $row, $guest->Gender, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
			$spreadsheet->getActiveSheet()->setCellValueExplicit('M' . $row, $guest->DateOfBirth, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
			$spreadsheet->getActiveSheet()->setCellValueExplicit('N' . $row, $guest->Nationality, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
			$spreadsheet->getActiveSheet()->setCellValueExplicit('O' . $row, $guest->IdentificationNumber, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
			$spreadsheet->getActiveSheet()->setCellValueExplicit('P' . $row, $guest->PassportNumber, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
			$spreadsheet->getActiveSheet()->setCellValueExplicit('Q' . $row, $guest->GuestMobile, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
			$spreadsheet->getActiveSheet()->setCellValueExplicit('R' . $row, $guest->Email, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
			$spreadsheet->getActiveSheet()->setCellValueExplicit('S' . $row, $guest->MaritalStatus, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
			$spreadsheet->getActiveSheet()->setCellValueExplicit('T' . $row, $guest->Employment, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
			$spreadsheet->getActiveSheet()->setCellValueExplicit('U' . $row, $guest->Address, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
			$spreadsheet->getActiveSheet()->setCellValueExplicit('V' . $row, $guest->Postcode, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
			$spreadsheet->getActiveSheet()->setCellValueExplicit('W' . $row, $guest->City, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
			$spreadsheet->getActiveSheet()->setCellValueExplicit('X' . $row, $guest->State, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
			$spreadsheet->getActiveSheet()->setCellValueExplicit('Y' . $row, $guest->Country, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
			$spreadsheet->getActiveSheet()->setCellValueExplicit('Z' . $row, $guest->Nominee, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
			$spreadsheet->getActiveSheet()->setCellValueExplicit('AA' . $row, $guest->NomineeIdentificationNumber, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
			$spreadsheet->getActiveSheet()->setCellValueExplicit('AB' . $row, $guest->Relationship, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
			$row++;
		}
		$spreadsheet->getActiveSheet()->getStyle('A:AB')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
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
		$spreadsheet->getActiveSheet()->getColumnDimension('W')->setWidth(35);
		$spreadsheet->getActiveSheet()->getColumnDimension('X')->setWidth(35);
		$spreadsheet->getActiveSheet()->getColumnDimension('Y')->setWidth(35);
		$spreadsheet->getActiveSheet()->getColumnDimension('Z')->setWidth(35);
		$spreadsheet->getActiveSheet()->getColumnDimension('AA')->setWidth(35);
		$spreadsheet->getActiveSheet()->getColumnDimension('AB')->setWidth(35);
		$guest_lists = 'GUEST_LISTS_' . $guest_lists[0]->BookingNumber . '.xlsx';
		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header('Content-Disposition: attachment;filename="' . $guest_lists . '"');
		header('Cache-Control: max-age=0');
		header('Cache-Control: max-age=1');
		$writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
		$writer->save('php://output');
	}
	
	function Unlock() {
		$this->Guest_List_Model->Update_GL_Session('BookingID', $this->input->post('booking_id'), 'N', null);
	}
}