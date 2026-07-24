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
		$this->load->model('Guests_Model');       // customer-anchored Guest List parity list
		$this->load->model('Booking_Model');       // filter dropdowns (admins/sources/destinations)
		$this->load->model('Ghl_Messages_Model');  // WhatsApp message-log badges
		$this->load->helper('customer_code');      // can_edit_customer_code() for the form + guard
		$this->load->helper('guest_contact');      // filter/format helpers used by the shared view
	}

	/**
	 * Customer List — reuses the shared Guest List view (views/guests/index.php)
	 * with the same layout, 21 filters, and columns, but anchored on the customer
	 * master table (one row per customer). Mirrors Ghl_Leads::index()'s wiring.
	 */
	function index()
	{
		$page   = max(1, (int) $this->input->get('page'));
		$limit  = 30; // rows per page
		$offset = ($page - 1) * $limit;

		// Date Creation sort toggle: DESC (newest first) by default, ASC oldest first.
		$sort_dir               = (strtoupper((string) $this->input->get('dir')) === 'ASC') ? 'ASC' : 'DESC';
		$data['sort_dir']       = $sort_dir;
		$data['guests']         = $this->Guests_Model->Read_Customers_Rich($limit, $offset, $sort_dir);
		$data['msg_log_phones'] = $this->Ghl_Messages_Model->Phones_With_Messages_For_Guests($data['guests']);
		$data['remark_counts']  = $this->Remark_Counts_For_Guests($data['guests']);
		$data['total']          = null; // AJAX-loaded via Count(), like Guests/Ghl_Leads
		$data['page']           = $page;
		$data['limit']          = $limit;
		$data['list_base']      = 'Customer';
		$data['page_title']     = 'Customer List Records';
		$data['admins']         = $this->Booking_Model->Read_Admins();
		$data['sources']        = $this->Booking_Model->Read_Sources();
		$data['destinations']   = $this->Booking_Model->Read_Categories();
		$data['customer_types']  = $this->Customer_Type_Model->Read_Customer_Types();
		$data['nationalities']  = $this->Guests_Model->Read_Distinct('Nationality');
		$data['languages']      = $this->Guests_Model->Read_Distinct('ChatLanguage');
		$data['edit_languages'] = array('CN', 'EN', 'ML');

		$titles = [
			'tab_title' => 'HolidayGoGoGo | Customer',
			'breadcrumb_title' => 'Customer'
		];

		$this->load->view('layout/header', $titles);
		$this->load->view('guests/index', $data);
		$this->load->view('layout/footer');
	}

	/**
	 * AJAX total-count + pagination for the Customer List (mirrors
	 * Ghl_Leads::Count()). The shared view calls base_url('Customer/Count').
	 */
	function Count()
	{
		$page  = max(1, (int) $this->input->get('page'));
		$limit = 30;
		$total = (int) $this->Guests_Model->Count_Customers_Rich();

		$pagination_html = $this->load->view('guests/_pagination', array(
			'total' => $total,
			'page'  => $page,
			'limit' => $limit,
			'query' => $this->input->get(),
		), true);

		$this->output
			->set_content_type('application/json')
			->set_output(json_encode(array(
				'total'           => $total,
				'pagination_html' => $pagination_html,
			)));
	}

	/**
	 * dedup_key => active-remark count for the rows on this page, so the shared
	 * view can badge each Action menu with "Remarks (n)". Mirrors the private
	 * helper of the same name on the Guests controller.
	 */
	private function Remark_Counts_For_Guests($guests)
	{
		$keys = array();
		foreach ((array) $guests as $g) {
			if (!empty($g->dedup_key)) {
				$keys[] = $g->dedup_key;
			}
		}
		return $this->Guests_Model->Read_Remark_Counts($keys);
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
		// Columns A–F mirror the Customer dashboard order (Alt Name, Name, Contact,
		// Email, Language, Customer Code); G is Date Creation (created_at). The
		// AutoCount sync columns + Updated At are kept after for operational use.
		// Dashboard's Guest Type / Destination are booking-derived, not on the
		// customer master, so they aren't part of this record export.
		$spreadsheet->getActiveSheet()->setCellValue('A1', 'ALT NAME');
		$spreadsheet->getActiveSheet()->setCellValue('B1', 'NAME');
		$spreadsheet->getActiveSheet()->setCellValue('C1', 'PHONE NUMBER');
		$spreadsheet->getActiveSheet()->setCellValue('D1', 'EMAIL');
		$spreadsheet->getActiveSheet()->setCellValue('E1', 'CHAT LANGUAGE');
		$spreadsheet->getActiveSheet()->setCellValue('F1', 'CUSTOMER CODE');
		$spreadsheet->getActiveSheet()->setCellValue('G1', 'DATE CREATION');
		$spreadsheet->getActiveSheet()->setCellValue('H1', 'AUTOCOUNT SYNC ACTION');
		$spreadsheet->getActiveSheet()->setCellValue('I1', 'AUTOCOUNT SYNC STATUS');
		$spreadsheet->getActiveSheet()->setCellValue('J1', 'AUTOCOUNT SYNC MESSAGE');
		$spreadsheet->getActiveSheet()->setCellValue('K1', 'UPDATED AT');
		$row = 2;
		$customers = $this->Customer_Model->Read_Customers_For_Export();
		$spreadsheet->getActiveSheet()->getStyle('A1:K1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_BLACK);
		$spreadsheet->getActiveSheet()->getStyle('A1:K1')->getFont()->getColor()->setARGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE);
		$spreadsheet->getActiveSheet()->getStyle('A1:K1')->getFont()->setBold(true);
		if(!empty($customers)) {
			foreach($customers as $customer) {
				$spreadsheet->getActiveSheet()->setCellValueExplicit('A' . $row, isset($customer->AltName) ? $customer->AltName : '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('B' . $row, $customer->name, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('C' . $row, $customer->phone_number, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('D' . $row, isset($customer->PrimaryEmail) ? $customer->PrimaryEmail : '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('E' . $row, $customer->ChatLanguage, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('F' . $row, $customer->CustomerCode, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('G' . $row, $customer->created_at, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('H' . $row, $customer->AutocountSyncAction, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('I' . $row, $customer->AutocountSyncStatus, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('J' . $row, $customer->AutocountSyncMessage, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$spreadsheet->getActiveSheet()->setCellValueExplicit('K' . $row, $customer->updated_at, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$row++;
			}
			$spreadsheet->getActiveSheet()->getStyle('A:K')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
		} else {
			$spreadsheet->getActiveSheet()->mergeCells('A2:K2');
			$spreadsheet->getActiveSheet()->getCell('A2')->setValue('Customer Records Not Found');
			$spreadsheet->getActiveSheet()->getStyle('A:K')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
		}
		foreach (array('A','B','C','D','E','F','G','H','I','J','K') as $c) {
			$spreadsheet->getActiveSheet()->getColumnDimension($c)->setWidth(35);
		}
		$customer_records = 'CUSTOMER_RECORDS_' . date('Ymd') . '.xlsx';
		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header('Content-Disposition: attachment;filename="' . $customer_records . '"');
		header('Cache-Control: max-age=0');
		header('Cache-Control: max-age=1');
		$writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
		$writer->save('php://output');
	}

	/**
	 * Download the blank bulk-create template: one header row in the exact
	 * columns Import() reads back (same order as the Customer dashboard), plus a
	 * sample row to show the expected format. The user fills rows and re-uploads
	 * via Import(). ALT NAME, NAME, PHONE NUMBER and CHAT LANGUAGE are mandatory;
	 * CUSTOMER CODE is optional — leave it blank to let the app generate a
	 * collision-free code per customer.
	 */
	function Import_Template()
	{
		$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
		$sheet = $spreadsheet->getActiveSheet();
		$sheet->setTitle('Customer Template');
		$spreadsheet->getProperties()->setCreator('HolidayGoGoGo');

		$headers = Customer_Model::IMPORT_COLUMNS; // 0-based index => label
		$col = 'A';
		foreach ($headers as $label) {
			$sheet->setCellValueExplicit($col . '1', $label, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
			$sheet->getColumnDimension($col)->setWidth(24);
			$col++;
		}
		$last_col = chr(ord('A') + count($headers) - 1); // e.g. 'H'
		$sheet->getStyle('A1:' . $last_col . '1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_BLACK);
		$sheet->getStyle('A1:' . $last_col . '1')->getFont()->getColor()->setARGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE);
		$sheet->getStyle('A1:' . $last_col . '1')->getFont()->setBold(true);

		// One greyed sample row so the format is obvious; delete before importing.
		// Columns: ALT NAME | NAME | PHONE | EMAIL | CHAT LANGUAGE | CUSTOMER CODE
		//          | IC / PASSPORT | TIN | BILLING ADDRESS. First four are mandatory.
		$sample = array('ALI', 'ALI BIN ABU', '0123456789', 'ali@example.com', 'EN', '', 'A12345678', '', 'No 1, Jalan Besar, 50000 KL');
		$col = 'A';
		foreach ($sample as $val) {
			$sheet->setCellValueExplicit($col . '2', $val, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
			$col++;
		}
		$sheet->getStyle('A2:' . $last_col . '2')->getFont()->getColor()->setARGB('FF999999');

		$filename = 'CUSTOMER_IMPORT_TEMPLATE_' . date('Ymd') . '.xlsx';
		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header('Content-Disposition: attachment;filename="' . $filename . '"');
		header('Cache-Control: max-age=0');
		$writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
		$writer->save('php://output');
	}

	/**
	 * Bulk-create customers from an uploaded template (the file Import_Template()
	 * produced, with data rows filled in). Each row becomes one customer; a blank
	 * CUSTOMER CODE is auto-generated, a supplied one is used as-is (skipped if
	 * taken). Rows matching an existing name+phone are skipped and reported. The
	 * upload is stored under assets/upload/customer_import/ and the newest 3 are
	 * kept as backups. Mirrors Faq::Import()'s upload/backup handling.
	 */
	function Import()
	{
		if ($this->input->server('REQUEST_METHOD') !== 'POST' || empty($_FILES['import_file']['name'])) {
			redirect(base_url('Customer'));
			return;
		}

		$file = $_FILES['import_file'];
		if ($file['error'] !== UPLOAD_ERR_OK) {
			$this->session->set_flashdata('customer_import_error', 'Upload failed. Please try again.');
			redirect(base_url('Customer'));
			return;
		}
		$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
		if (!in_array($ext, array('xlsx', 'xls'), true)) {
			$this->session->set_flashdata('customer_import_error', 'Please upload an Excel file (.xlsx or .xls).');
			redirect(base_url('Customer'));
			return;
		}
		if ($file['size'] > 10 * 1024 * 1024) {
			$this->session->set_flashdata('customer_import_error', 'File too large. Maximum size is 10MB.');
			redirect(base_url('Customer'));
			return;
		}

		// Save the upload as a backup, then keep only the newest 3.
		$dir = FCPATH . 'assets/upload/customer_import/';
		if (!is_dir($dir)) {
			mkdir($dir, 0755, true);
		}
		$dest = $dir . 'customer_import_' . time() . '.' . $ext;
		if (!move_uploaded_file($file['tmp_name'], $dest)) {
			$this->session->set_flashdata('customer_import_error', 'Could not save the uploaded file.');
			redirect(base_url('Customer'));
			return;
		}
		$existing = array();
		foreach (glob($dir . 'customer_import_*') as $path) {
			$existing[] = basename($path);
		}
		foreach (Customer_Model::Prune_Import_Backups($existing, 3) as $old) {
			@unlink($dir . $old);
		}

		try {
			$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($dest);
			$rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
		} catch (\Exception $e) {
			$this->session->set_flashdata('customer_import_error', 'Could not read the Excel file. Please use the downloaded template.');
			redirect(base_url('Customer'));
			return;
		}

		$parsed = Customer_Model::Parse_Import_Rows($rows, array('CN', 'EN', 'ML'));
		if (empty($parsed)) {
			$this->session->set_flashdata('customer_import_error', 'No customer rows found in the file. Nothing was created.');
			redirect(base_url('Customer'));
			return;
		}

		$summary = $this->Customer_Model->Bulk_Import($parsed);

		$msg = count($summary['created']) . ' customer(s) created.';
		if (!empty($summary['skipped_duplicate'])) {
			$lines = array();
			foreach ($summary['skipped_duplicate'] as $s) {
				$lines[] = 'row ' . $s['line'] . ' (' . $s['name'] . ')';
			}
			$msg .= ' Skipped ' . count($lines) . ' existing customer(s): ' . implode(', ', $lines) . '.';
		}
		if (!empty($summary['failed'])) {
			$lines = array();
			foreach ($summary['failed'] as $f) {
				$lines[] = 'row ' . $f['line'] . ' — ' . $f['reason'];
			}
			$msg .= ' Failed ' . count($lines) . ' row(s): ' . implode('; ', $lines) . '.';
		}

		// Any successful create -> success banner (with any skip/fail notes
		// appended); nothing created -> error banner.
		$key = !empty($summary['created']) ? 'customer_import_success' : 'customer_import_error';
		$this->session->set_flashdata($key, $msg);
		redirect(base_url('Customer'));
	}

	public function search1()
	{
		$q = $this->input->get('q');

		$this->db->group_start();
			$this->db->like('name', $q);
			$this->db->or_like('AltName', $q);
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
			$this->db->or_like('AltName', $q, 'after');    // q%
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