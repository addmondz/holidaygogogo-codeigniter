<?php

require FCPATH.'vendor/autoload.php';  
use PhpOffice\PhpSpreadsheet\Spreadsheet;  
use PhpOffice\PhpSpreadsheet\Writer\Xlxs;

class Footer extends MY_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->load->model('Footer_Model');
		$this->load->model('Universal_Model');
	}

	function index()
	{
		if($this->session->userdata('level') == 20) {
			redirect(base_url());
		} else {
			$titles = array('tab_title' => 'HolidayGoGoGo | Footer', 'breadcrumb_title' => 'Footer');
			$array['footers'] = $this->Footer_Model->Read_Footers1();
			$this->load->view('layout/header', $titles);
			$this->load->view('footer/index', $array);
			$this->load->view('layout/footer');
		}
	}

	function Create()
	{
		if($this->session->userdata('level') == 20) {
			redirect(base_url());
		} else {
			if($this->input->post()) {
				$this->Footer_Model->Create();
				$this->session->set_flashdata('message_success', 'New Footer Record Successfully Created');
				redirect('Footer');
			} else {
				$titles = array('tab_title' => 'HolidayGoGoGo | Footer', 'breadcrumb_title' => 'Footer >> Create');
				$this->load->view('layout/header', $titles);
				$this->load->view('footer/footer');
				$this->load->view('layout/footer');
			}
		}
	}

	function Read()
    {
        $content = $this->Footer_Model->Read_Footer();
        echo json_encode($content);
    }

	function Update()
	{
		if($this->session->userdata('level') == 20) {
			redirect(base_url());
		} else {
			if($this->input->post()) {
				$footer = $this->Footer_Model->Read_Footer();
				$array['footer'][0] = array('FooterID' => $this->input->get('footer_id'), 'UpdateBy' => $this->session->userdata('admin_id'), 'UpdateDate' => date('Y-m-d H:i:s'));
				$booking_confirmation_title = strtoupper($this->input->post('booking_confirmation_title'));
				$booking_confirmation_content = $this->input->post('booking_confirmation_content');
				$travel_voucher_title = strtoupper($this->input->post('travel_voucher_title'));
				$travel_voucher_content = $this->input->post('travel_voucher_content');
				if($booking_confirmation_title != $footer['BookingConfirmationTitle']) {
					$array['footer'][0]['BookingConfirmationTitle'] = $booking_confirmation_title;
				}
				if($booking_confirmation_content != $footer['BookingConfirmationContent']) {
					$array['footer'][0]['BookingConfirmationContent'] = $booking_confirmation_content;
				}
				if($travel_voucher_title != $footer['TravelVoucherTitle']) {
					$array['footer'][0]['TravelVoucherTitle'] = $travel_voucher_title;
				}
				if($travel_voucher_content != $footer['TravelVoucherContent']) {
					$array['footer'][0]['TravelVoucherContent'] = $travel_voucher_content;
				}
				if(count($array['footer'][0]) > 3) {
					$this->Footer_Model->Update($array['footer']);
					$this->session->set_flashdata('message_success', 'Footer Record : ' . str_replace('\'', '', $footer['BookingConfirmationTitle']) . ' Successfully Updated');
				} else {
					$this->session->set_flashdata('message_success', 'No Changes Detected In Footer Record : ' . str_replace('\'', '', $footer['BookingConfirmationTitle']));
				}
				redirect('Footer');
			} else {
				$valid_footer_id = $this->Universal_Model->Validate_Id('FooterID', $this->input->get('footer_id'), 'footer');
				if($valid_footer_id) {
					$titles = array('tab_title' => 'HolidayGoGoGo | Footer', 'breadcrumb_title' => 'Footer >> Update');
					$array = $this->Footer_Model->Read_Footer();
					$this->load->view('layout/header', $titles);
					$this->load->view('footer/footer', $array);
					$this->load->view('layout/footer');
				} else {
					redirect('Footer');
				}
			}
		}
	}
	
	function Delete() 
	{
		if($this->session->userdata('level') == 20) {
			redirect(base_url());
		} else {
			$this->Universal_Model->Delete('FooterID', $this->input->get('footer_id'), 'footer');
		}
	}

	function Download() {
		$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
		$spreadsheet->getActiveSheet()->setTitle('Footer Records');
		$spreadsheet->getProperties()->setCreator('HolidayGoGoGo');
		$spreadsheet->getActiveSheet()->setCellValue('A1', 'BC TITLE');
		$spreadsheet->getActiveSheet()->setCellValue('B1', 'BC CONTENT');
		$spreadsheet->getActiveSheet()->setCellValue('C1', 'TV TITLE');
		$spreadsheet->getActiveSheet()->setCellValue('D1', 'TV CONTENT');
		$row = 2;
		$footers = $this->Footer_Model->Read_Footers2();
		$spreadsheet->getActiveSheet()->getStyle('A1:D1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_BLACK);
		$spreadsheet->getActiveSheet()->getStyle('A1:D1')->getFont()->getColor()->setARGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE);
		$spreadsheet->getActiveSheet()->getStyle('A1:D1')->getFont()->setBold(true);
		if(!empty($footers)) {
			foreach($footers as $footer) {
				$spreadsheet->getActiveSheet()->setCellValueExplicit('A' . $row, $footer->BookingConfirmationTitle, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('B' . $row, strip_tags(htmlspecialchars_decode(str_replace('&nbsp;', '', $footer->BookingConfirmationContent))), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('C' . $row, $footer->TravelVoucherTitle, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('D' . $row, strip_tags(htmlspecialchars_decode(str_replace('&nbsp;', '', $footer->TravelVoucherContent))), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$row++;
			}
		} else {
			$spreadsheet->getActiveSheet()->mergeCells('A2:D2');
			$spreadsheet->getActiveSheet()->getCell('A2')->setValue('Footer Records Not Found');
			$spreadsheet->getActiveSheet()->getStyle('A:D')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
		}
		$spreadsheet->getActiveSheet()->getColumnDimension('A')->setWidth(35);
		$spreadsheet->getActiveSheet()->getColumnDimension('B')->setWidth(35);
		$spreadsheet->getActiveSheet()->getColumnDimension('C')->setWidth(35);
		$spreadsheet->getActiveSheet()->getColumnDimension('D')->setWidth(35);
		$footer_records = 'FOOTER_RECORDS_' . date('Ymd') . '.xlsx';
		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header('Content-Disposition: attachment;filename="' . $footer_records . '"');
		header('Cache-Control: max-age=0');
		header('Cache-Control: max-age=1');
		$writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
		$writer->save('php://output');
	}
	
	function Detect() {
		$booking_confirmation_title = $this->input->post('booking_confirmation_title');
		$travel_voucher_title = $this->input->post('travel_voucher_title');
		if(!empty($booking_confirmation_title)) {
			$redundant_title = $this->Footer_Model->Detect('BookingConfirmationTitle', $booking_confirmation_title);
		} else {
			$redundant_title = $this->Footer_Model->Detect('TravelVoucherTitle', $travel_voucher_title);
		}
		if($redundant_title) {
			echo json_encode(true);
		} else {
			echo json_encode(false);
		}
	}
}