<?php

require FCPATH.'vendor/autoload.php';  
use PhpOffice\PhpSpreadsheet\Spreadsheet;  
use PhpOffice\PhpSpreadsheet\Writer\Xlxs;

class Product extends MY_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->load->model('Product_Model');
		$this->load->model('Universal_Model');
	}

	function index()
	{
		$titles = array('tab_title' => 'HolidayGoGoGo | Product', 'breadcrumb_title' => 'Product');
		$array['products'] = $this->Product_Model->Read_Products1();
		$array['categories'] = $this->Product_Model->Read_Categories();
		$array['suppliers'] = $this->Product_Model->Read_Suppliers();
		foreach($array['products'] as $product) {
            $product->RetailPrice = $product->RetailPrice == 0.00 ? '' : number_format($product->RetailPrice, 2, '.', ',');
            $product->SupplierPrice = $product->SupplierPrice == 0.00 ? '' : number_format($product->SupplierPrice, 2, '.', ',');
        }
		$this->load->view('layout/header', $titles);
		$this->load->view('product/index', $array);
		$this->load->view('layout/footer');
	}

	function Create()
	{
		if($this->input->is_ajax_request()) {
			$this->Product_Model->Create();
		} else {
			$titles = array('tab_title' => 'HolidayGoGoGo | Product', 'breadcrumb_title' => 'Product >> Create');
			$array = array('ProductID' => 'NA', 'ProductCode' => 'NA');
			$array['categories'] = $this->Product_Model->Read_Categories();
			$array['suppliers'] = $this->Product_Model->Read_Suppliers();
			$this->load->view('layout/header', $titles);
			$this->load->view('product/product', $array);
			$this->load->view('layout/footer');
		}
	}

	function Update()
	{
		if($this->input->is_ajax_request()) {
			if(count($this->input->post('product')[0]) > 3) {
				$this->Product_Model->Update();
			}
		} else {
			$valid_product_id = $this->Universal_Model->Validate_Id('ProductID', $this->input->get('product_id'), 'product');
			if($valid_product_id) {
				$titles = array('tab_title' => 'HolidayGoGoGo | Product', 'breadcrumb_title' => 'Product >> Update');
				$array = $this->Product_Model->Read_Product();
				$array['RetailPrice'] = $array['RetailPrice'] == 0.00 ? null : number_format($array['RetailPrice'], 2, '.', ',');
            	$array['SupplierPrice'] = $array['SupplierPrice'] == 0.00 ? null : number_format($array['SupplierPrice'], 2, '.', ',');
				$array['categories'] = $this->Product_Model->Read_Categories();
				$array['suppliers'] = $this->Product_Model->Read_Suppliers();
				$this->load->view('layout/header', $titles);
				$this->load->view('product/product', $array);
				$this->load->view('layout/footer');
			} else {
				redirect('Product');
			}
		}
	}
	
	function Delete() 
	{
		$this->Universal_Model->Delete('ProductID', $this->input->get('product_id'), 'product');
	}
	
	function Download() {
		$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
		$spreadsheet->getActiveSheet()->setTitle('Product Records');
		$spreadsheet->getProperties()->setCreator('HolidayGoGoGo');
		$spreadsheet->getActiveSheet()->setCellValue('A1', 'CATEGORY');
		$spreadsheet->getActiveSheet()->setCellValue('B1', 'SUPPLIER');
		$spreadsheet->getActiveSheet()->setCellValue('C1', 'PRODUCT CODE');
		$spreadsheet->getActiveSheet()->setCellValue('D1', 'NAME');
		$spreadsheet->getActiveSheet()->setCellValue('E1', 'RETAIL PRICE');
		$spreadsheet->getActiveSheet()->setCellValue('F1', 'SUPPLIER PRICE');
		$row = 2;
		$products = $this->Product_Model->Read_Products2();
		$spreadsheet->getActiveSheet()->getStyle('A1:F1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_BLACK);
		$spreadsheet->getActiveSheet()->getStyle('A1:F1')->getFont()->getColor()->setARGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE);
		$spreadsheet->getActiveSheet()->getStyle('A1:F1')->getFont()->setBold(true);
		if(!empty($products)) {
			foreach($products as $product) {
				$product->RetailPrice = $product->RetailPrice == 0.00 ? '' : $product->RetailPrice;
				$product->SupplierPrice = $product->SupplierPrice == 0.00 ? '' : $product->SupplierPrice;
				$spreadsheet->getActiveSheet()->setCellValueExplicit('A' . $row, $product->Category, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('B' . $row, $product->Supplier, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('C' . $row, $product->ProductCode, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('D' . $row, $product->Product, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValue('E' . $row, $product->RetailPrice);
				$spreadsheet->getActiveSheet()->setCellValue('F' . $row, $product->SupplierPrice);

				$spreadsheet->getActiveSheet()->getStyle('E' . $row)->getNumberFormat()->setFormatCode('"RM"#,##0.00_-');
				$spreadsheet->getActiveSheet()->getStyle('F' . $row)->getNumberFormat()->setFormatCode('"RM"#,##0.00_-');
				
				$row++;
			}
			$spreadsheet->getActiveSheet()->getStyle('A:F')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
		} else {
			$spreadsheet->getActiveSheet()->mergeCells('A2:F2');
			$spreadsheet->getActiveSheet()->getCell('A2')->setValue('Product Records Not Found');
			$spreadsheet->getActiveSheet()->getStyle('A:F')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
		}
		$spreadsheet->getActiveSheet()->getColumnDimension('A')->setWidth(35);
		$spreadsheet->getActiveSheet()->getColumnDimension('B')->setWidth(35);
		$spreadsheet->getActiveSheet()->getColumnDimension('C')->setWidth(35);
		$spreadsheet->getActiveSheet()->getColumnDimension('D')->setWidth(35);
		$spreadsheet->getActiveSheet()->getColumnDimension('E')->setWidth(35);
		$spreadsheet->getActiveSheet()->getColumnDimension('F')->setWidth(35);
		$product_records = 'PRODUCT_RECORDS_' . date('Ymd') . '.xlsx';
		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header('Content-Disposition: attachment;filename="' . $product_records . '"');
		header('Cache-Control: max-age=0');
		header('Cache-Control: max-age=1');
		$writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
		$writer->save('php://output');
	}

	function Detect() {
		$redundant_name = $this->Product_Model->Detect();
		if($redundant_name) {
			echo json_encode(true);
		} else {
			echo json_encode(false);
		}
	}
}