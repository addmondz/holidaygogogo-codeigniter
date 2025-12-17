<?php

require FCPATH.'vendor/autoload.php';  
use PhpOffice\PhpSpreadsheet\Spreadsheet;  
use PhpOffice\PhpSpreadsheet\Writer\Xlxs;

class Customer extends MY_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->load->model('Customer_Model');
		$this->load->model('Universal_Model');
	}

	function index()
	{
		$titles = array('tab_title' => 'HolidayGoGoGo | Customer', 'breadcrumb_title' => 'Customer');
		$array['customers'] = $this->Customer_Model->Read_Customers1();
		$this->load->view('layout/header', $titles);
		$this->load->view('customer/index', $array);
		$this->load->view('layout/footer');
	}

	function Create()
	{							
		if($this->input->is_ajax_request()) {
			$this->Customer_Model->Create();
		} else {
			$titles = array('tab_title' => 'HolidayGoGoGo | Customer', 'breadcrumb_title' => 'Customer >> Create');
			$array = array('CustomerID' => 'NA', 'name' => 'NA');
			$this->load->view('layout/header', $titles);
			$this->load->view('customer/customer', $array);
			$this->load->view('layout/footer');
		}
	}

	function Update()
	{
		if($this->input->is_ajax_request()) {
			if(count($this->input->post('customer')[0]) > 1) {
				$this->Customer_Model->Update();

				$customer = get_object_vars($this->Customer_Model->find($this->input->post('customer_id')));
				if (!empty($customer)) {
					if ($customer['AutocountSyncAction'] == 'C' && $customer['AutocountSyncStatus'] == 'S') {
						// Update action to 'U' and status to 'P' if action is 'C' and status is 'S'
						$this->Customer_Model->update_by_id($customer['CustomerID'], [
							'AutocountSyncAction' => 'U',
							'AutocountSyncStatus' => 'P'
						]);
					} elseif ($customer['AutocountSyncAction'] == 'U' && $customer['AutocountSyncStatus'] == 'S') {
						// Update status to 'P' if action is 'U' and status is 'S'
						$this->Customer_Model->update_by_id($customer['CustomerID'], [
							'AutocountSyncStatus' => 'P'
						]);
					} else {
						// Just update status to 'P' in all other cases
						$this->Customer_Model->update_by_id($customer['CustomerID'], [
							'AutocountSyncStatus' => 'P'
						]);
					}
				}
			}
		} else {
			$valid_customer_id = $this->Universal_Model->Validate_Id('CustomerID', $this->input->get('customer_id'), 'customer');
			if($valid_customer_id) {
				$titles = array('tab_title' => 'HolidayGoGoGo | Customer', 'breadcrumb_title' => 'Customer >> Update');
				$array = $this->Customer_Model->Read_Customer();
				$this->load->view('layout/header', $titles);
				$this->load->view('customer/customer', $array);
				$this->load->view('layout/footer');
			} else {
				redirect('Customer');
			}
		}
	}
	
	function Delete() 
	{
		//$this->Universal_Model->Delete('CustomerID', $this->input->get('customer_id'), 'customer');
		$this->Customer_Model->update_by_id($this->input->get('customer_id'), [
			'Status'  => 'N',
			'updated_at' => date('Y-m-d H:i:s')
		]);

		$customer = get_object_vars($this->Customer_Model->find($this->input->get('customer_id')));
		if (!empty($customer)) {
			if ($customer['AutocountSyncAction'] == 'C' && $customer['AutocountSyncStatus'] == 'S') {
				// Update action to 'U' and status to 'P' if action is 'C' and status is 'S'
				$this->Customer_Model->update_by_id($customer['CustomerID'], [
					'AutocountSyncAction' => 'D',
					'AutocountSyncStatus' => 'P'
				]);
			} elseif ($customer['AutocountSyncAction'] == 'U') {
				// Update status to 'P' if action is 'U' and status is 'S'
				$this->Customer_Model->update_by_id($customer['CustomerID'], [
					'AutocountSyncAction' => 'D',
					'AutocountSyncStatus' => 'P'
				]);
			} else {
				// Just update status to 'P' in all other cases
				$this->Customer_Model->update_by_id($customer['CustomerID'], [
					'AutocountSyncStatus' => 'P'
				]);
			}
		}
	}
	
	function Download() {
		$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
		$spreadsheet->getActiveSheet()->setTitle('Customer Records');
		$spreadsheet->getProperties()->setCreator('HolidayGoGoGo');
		$spreadsheet->getActiveSheet()->setCellValue('A1', 'CUSTOMER CODE');
		$spreadsheet->getActiveSheet()->setCellValue('B1', 'NAME');
		$spreadsheet->getActiveSheet()->setCellValue('C1', 'PHONE NUMBER');
		$spreadsheet->getActiveSheet()->setCellValue('D1', 'CHAT LANGUAGE');
		$spreadsheet->getActiveSheet()->setCellValue('E1', 'AUTOCOUNT SYNC ACTION');
		$spreadsheet->getActiveSheet()->setCellValue('F1', 'AUTOCOUNT SYNC STATUS');
		$spreadsheet->getActiveSheet()->setCellValue('G1', 'AUTOCOUNT SYNC MESSAGE');
		$spreadsheet->getActiveSheet()->setCellValue('H1', 'CREATED AT');
		$spreadsheet->getActiveSheet()->setCellValue('I1', 'UPDATED AT');
		$row = 2;
		$customers = $this->Customer_Model->Read_Customers2();
		$spreadsheet->getActiveSheet()->getStyle('A1:I1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_BLACK);
		$spreadsheet->getActiveSheet()->getStyle('A1:I1')->getFont()->getColor()->setARGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE);
		$spreadsheet->getActiveSheet()->getStyle('A1:I1')->getFont()->setBold(true);
		if(!empty($customers)) {
			foreach($customers as $customer) {
				$spreadsheet->getActiveSheet()->setCellValueExplicit('A' . $row, $customer->CustomerCode, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('B' . $row, $customer->name, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('C' . $row, $customer->phone_number, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('D' . $row, $customer->ChatLanguage, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('E' . $row, $customer->AutocountSyncAction, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('F' . $row, $customer->AutocountSyncStatus, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('G' . $row, $customer->AutocountSyncMessage, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('H' . $row, $customer->created_at, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('I' . $row, $customer->updated_at, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$row++;
			}
			$spreadsheet->getActiveSheet()->getStyle('A:I')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
		} else {
			$spreadsheet->getActiveSheet()->mergeCells('A2:I2');
			$spreadsheet->getActiveSheet()->getCell('A2')->setValue('Customer Records Not Found');
			$spreadsheet->getActiveSheet()->getStyle('A:I')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
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
		$customer_records = 'CUSTOMER_RECORDS_' . date('Ymd') . '.xlsx';
		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header('Content-Disposition: attachment;filename="' . $customer_records . '"');
		header('Cache-Control: max-age=0');
		header('Cache-Control: max-age=1');
		$writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
		$writer->save('php://output');
	}

	function Detect() {
		$redundant_name = $this->Customer_Model->Detect();
		if($redundant_name) {
			echo json_encode(true);
		} else {
			echo json_encode(false);
		}
	}

	public function search()
	{
		$q = $this->input->get('q');

		$this->db->group_start(); // Open bracket (
		$this->db->like('name', $q);
		$this->db->or_like('CustomerCode', $q);
		$this->db->or_like('phone_number', $q);
		$this->db->group_end();   // Close bracket )

		// Check not null and not empty
		$this->db->where('name IS NOT NULL');
		$this->db->where('phone_number IS NOT NULL');

		if ($this->input->get('limit') != 'INFINITE') {
			$limit = !empty($this->input->get('limit')) ? (int)$this->input->get('limit') : 30;
			$this->db->limit($limit);
		}
		
		$query = $this->db->get('customer');
		$results = $query->result();

		// Return JSON
		echo json_encode($results);
	}

}