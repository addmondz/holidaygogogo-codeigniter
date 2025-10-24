<?php

require FCPATH.'vendor/autoload.php';  
use PhpOffice\PhpSpreadsheet\Spreadsheet;  
use PhpOffice\PhpSpreadsheet\Writer\Xlxs;

class Supplier extends MY_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->load->model('Supplier_Model');
		$this->load->model('Universal_Model');
	}

	function index()
	{
		$titles = array('tab_title' => 'HolidayGoGoGo | Supplier', 'breadcrumb_title' => 'Supplier');
		$array['suppliers'] = $this->Supplier_Model->Read_Suppliers1();
		$this->load->view('layout/header', $titles);
		$this->load->view('supplier/index', $array);
		$this->load->view('layout/footer');
	}

	function Create()
	{
		if($this->input->is_ajax_request()) {
			$this->Supplier_Model->Create();
		} else {
			$titles = array('tab_title' => 'HolidayGoGoGo | Supplier', 'breadcrumb_title' => 'Supplier >> Create');
			$array = array('SupplierID' => 'NA', 'Name' => 'NA');
			$this->load->view('layout/header', $titles);
			$this->load->view('supplier/supplier', $array);
			$this->load->view('layout/footer');
		}
	}

	function Update()
	{
		if($this->input->is_ajax_request()) {
			if(count($this->input->post('supplier')[0]) > 3) {
				$this->Supplier_Model->Update();

				$supplier = get_object_vars($this->Supplier_Model->find($this->input->post('supplier_id')));
				if (!empty($supplier)) {
					if ($supplier['AutocountSyncAction'] == 'C' && $supplier['AutocountSyncStatus'] == 'S') {
						// Update action to 'U' and status to 'P' if action is 'C' and status is 'S'
						$this->Supplier_Model->update_by_id($supplier['SupplierID'], [
							'AutocountSyncAction' => 'U',
							'AutocountSyncStatus' => 'P'
						]);
					} elseif ($supplier['AutocountSyncAction'] == 'U' && $supplier['AutocountSyncStatus'] == 'S') {
						// Update status to 'P' if action is 'U' and status is 'S'
						$this->Supplier_Model->update_by_id($supplier['SupplierID'], [
							'AutocountSyncStatus' => 'P'
						]);
					} else {
						// Just update status to 'P' in all other cases
						$this->Supplier_Model->update_by_id($supplier['SupplierID'], [
							'AutocountSyncStatus' => 'P'
						]);
					}
				}
			}
		} else {
			$valid_supplier_id = $this->Universal_Model->Validate_Id('SupplierID', $this->input->get('supplier_id'), 'supplier');
			if($valid_supplier_id) {
				$titles = array('tab_title' => 'HolidayGoGoGo | Supplier', 'breadcrumb_title' => 'Supplier >> Update');
				$array = $this->Supplier_Model->Read_Supplier();
				$this->load->view('layout/header', $titles);
				$this->load->view('supplier/supplier', $array);
				$this->load->view('layout/footer');
			} else {
				redirect('Supplier');
			}
		}
	}
	
	function Delete() 
	{
		$this->Universal_Model->Delete('SupplierID', $this->input->get('supplier_id'), 'supplier');

		$supplier = get_object_vars($this->Supplier_Model->find($this->input->get('supplier_id')));
		if (!empty($supplier)) {
			if ($supplier['AutocountSyncAction'] == 'C' && $supplier['AutocountSyncStatus'] == 'S') {
				// Update action to 'U' and status to 'P' if action is 'C' and status is 'S'
				$this->Supplier_Model->update_by_id($supplier['SupplierID'], [
					'AutocountSyncAction' => 'D',
					'AutocountSyncStatus' => 'P'
				]);
			} elseif ($supplier['AutocountSyncAction'] == 'U') {
				// Update status to 'P' if action is 'U' and status is 'S'
				$this->Supplier_Model->update_by_id($supplier['SupplierID'], [
					'AutocountSyncAction' => 'D',
					'AutocountSyncStatus' => 'P'
				]);
			} else {
				// Just update status to 'P' in all other cases
				$this->Supplier_Model->update_by_id($supplier['SupplierID'], [
					'AutocountSyncStatus' => 'P'
				]);
			}
		}
	}
	
	function Download() {
		$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
		$spreadsheet->getActiveSheet()->setTitle('Supplier Records');
		$spreadsheet->getProperties()->setCreator('HolidayGoGoGo');
		$spreadsheet->getActiveSheet()->setCellValue('A1', 'NAME');
		$spreadsheet->getActiveSheet()->setCellValue('B1', 'PHONE');
		$spreadsheet->getActiveSheet()->setCellValue('C1', 'PRIMARY EMAIL');
		$spreadsheet->getActiveSheet()->setCellValue('D1', 'SECONDARY EMAIL');
		$spreadsheet->getActiveSheet()->setCellValue('E1', 'ADDRESS');
		$spreadsheet->getActiveSheet()->setCellValue('F1', 'CURRENCY CODE');
		$spreadsheet->getActiveSheet()->setCellValue('G1', 'BANK');
		$spreadsheet->getActiveSheet()->setCellValue('H1', 'BANK ACCOUNT');
		$spreadsheet->getActiveSheet()->setCellValue('I1', 'BANK HOLDER');
		$spreadsheet->getActiveSheet()->setCellValue('J1', 'SWIFT CODE');
		$row = 2;
		$suppliers = $this->Supplier_Model->Read_Suppliers2();
		$spreadsheet->getActiveSheet()->getStyle('A1:J1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_BLACK);
		$spreadsheet->getActiveSheet()->getStyle('A1:J1')->getFont()->getColor()->setARGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE);
		$spreadsheet->getActiveSheet()->getStyle('A1:J1')->getFont()->setBold(true);
		if(!empty($suppliers)) {
			foreach($suppliers as $supplier) {
				$spreadsheet->getActiveSheet()->setCellValueExplicit('A' . $row, $supplier->Name, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('B' . $row, $supplier->Phone, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('C' . $row, $supplier->PrimaryEmail, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('D' . $row, $supplier->SecondaryEmail, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('E' . $row, $supplier->Address, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('F' . $row, $supplier->CurrencyCode, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('G' . $row, $supplier->Bank, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('H' . $row, $supplier->BankAccount, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('I' . $row, $supplier->BankHolder, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('J' . $row, $supplier->SwiftCode, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$row++;
			}
			$spreadsheet->getActiveSheet()->getStyle('A:J')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
		} else {
			$spreadsheet->getActiveSheet()->mergeCells('A2:J2');
			$spreadsheet->getActiveSheet()->getCell('A2')->setValue('Supplier Records Not Found');
			$spreadsheet->getActiveSheet()->getStyle('A:J')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
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
		$supplier_records = 'SUPPLIER_RECORDS_' . date('Ymd') . '.xlsx';
		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header('Content-Disposition: attachment;filename="' . $supplier_records . '"');
		header('Cache-Control: max-age=0');
		header('Cache-Control: max-age=1');
		$writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
		$writer->save('php://output');
	}

	function Detect() {
		$redundant_name = $this->Supplier_Model->Detect();
		if($redundant_name) {
			echo json_encode(true);
		} else {
			echo json_encode(false);
		}
	}
}