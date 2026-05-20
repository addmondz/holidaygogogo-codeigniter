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
		$this->load->model('Customer_Type_Model');
	}

	function index()
	{
		$page   = max(1, (int) $this->input->get('page'));
		$limit  = 30; // rows per page
		$offset = ($page - 1) * $limit;

		$data['customers'] = $this->Customer_Model->Read_Customers1($limit, $offset);
		$data['total']     = $this->Customer_Model->Count_Customers();
		$data['page']      = $page;
		$data['limit']     = $limit;
		$data['customer_types'] = $this->Customer_Type_Model->Read_Customer_Types();

		$titles = [
			'tab_title' => 'HolidayGoGoGo | Customer',
			'breadcrumb_title' => 'Customer'
		];

		$this->load->view('layout/header', $titles);
		$this->load->view('customer/index', $data);
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
			$array = $valid_customer_id ? $this->Customer_Model->Read_Customer() : null;
			if($valid_customer_id && !empty($array) && (isset($array['Status']) ? $array['Status'] : 'Y') === 'Y') {
				$titles = array('tab_title' => 'HolidayGoGoGo | Customer', 'breadcrumb_title' => 'Customer >> Update');
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
		if ($this->session->userdata('level') != 10) {
			show_error('Only owner level can delete customer.', 403);
			return;
		}
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
		$customers = $this->Customer_Model->Read_Customers_For_Export();
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

	public function search1()
	{
		$q = $this->input->get('q');

		$this->db->group_start(); 
			$this->db->like('name', $q);
			$this->db->or_like('CustomerCode', $q);
			$this->db->or_like('phone_number', $q);
		$this->db->group_end();

		$this->db->where('name IS NOT NULL', null, false);
		$this->db->where('phone_number IS NOT NULL', null, false);

		// 🚫 EXCLUDE heavy batch (8k rows)
		$this->db->where(
			"created_at NOT BETWEEN '2025-12-17 22:58:00' AND '2025-12-17 22:59:59'",
			null,
			false
		);

		if ($this->input->get('limit') != 'INFINITE') {
			$limit = !empty($this->input->get('limit')) ? (int)$this->input->get('limit') : 30;
			$this->db->limit($limit);
		}

		$query = $this->db->get('customer');
		echo json_encode($query->result());
	}

	public function search()
	{
		$q = trim($this->input->get('q'));

		if ($q === '') {
			echo json_encode([]);
			return;
		}

		$this->db->where('Status', 'Y');

		$this->db->group_start();
			$this->db->like('name', $q, 'after');          // q%
			$this->db->or_like('CustomerCode', $q, 'after');
			$this->db->or_like('phone_number', $q, 'after');
		$this->db->group_end();

		$this->db->limit(30);

		echo json_encode($this->db->get('customer')->result());
	}

	public function check_duplicate()
	{
		$name  = trim((string)$this->input->get('name'));
		$phone = trim((string)$this->input->get('phone'));

		if ($name === '' || $phone === '') {
			echo json_encode([]);
			return;
		}

		// LOWER() comparison is explicit so the match does not depend on the
		// column's collation. Both sides are bound parameters via escape().
		$this->db->select('CustomerID, name, phone_number, CustomerCode');
		$this->db->where('Status', 'Y');
		$this->db->where('LOWER(name) = ' . $this->db->escape(strtolower($name)), null, false);
		$this->db->where('phone_number', $phone);
		$this->db->limit(5);

		echo json_encode($this->db->get('customer')->result());
	}

	/**
	 * Generate customer portal URL
	 * 
	 * Usage: /Customer/GeneratePortalUrl?customer_id=123
	 * Returns JSON with portal_url
	 */
	public function GeneratePortalUrl()
	{
		$this->load->helper('utils');
		
		$customer_id = $this->input->get('customer_id');
		
		if (empty($customer_id)) {
			$this->output
				->set_content_type('application/json')
				->set_output(json_encode([
					'success' => false,
					'message' => 'Customer ID is required'
				]));
			return;
		}

		$customer = $this->Customer_Model->find($customer_id);
		
		if (!$customer || empty($customer->CustomerID)) {
			$this->output
				->set_content_type('application/json')
				->set_output(json_encode([
					'success' => false,
					'message' => 'Customer not found or has no CustomerID'
				]));
			return;
		}

		$hash = generate_customer_portal_slug($customer->CustomerID);
		$portal_url = base_url('customer/' . $hash);

		$this->output
			->set_content_type('application/json')
			->set_output(json_encode([
				'success' => true,
				'portal_url' => $portal_url,
				'hash' => $hash,
				'customer_code' => $customer->CustomerCode
			]));
	}


}