<?php
require FCPATH.'vendor/autoload.php'; // PhpSpreadsheet for the bulk-upload template/import
/**
 * Manual Leads page — hand-entered ("Manual") leads live here on their own page,
 * separate from GHL Leads for control + privacy. It reuses the Guest List view
 * and model (locked to mode 'manual' via Set_Mode) so it shares the GHL Leads
 * layout, but reads only lead_source='manual' rows and — for everyone except
 * Owner/Team Lead/Marketing — only the leads the viewer created (see
 * Guests_Model::Build_Branches + guest_list_ghl_lead_source_scope).
 */
class Manual_Leads extends MY_Controller
{
	function __construct()
	{
		parent::__construct();
		if (!$this->config->item('show_guest_list')) {
			redirect(base_url('Booking'));
		}
		// Leads/Customer tab access: view gates the whole page (owner always allowed).
		if ( ! lc_can_view('manual_leads')) {
			redirect(base_url('Booking'));
		}
		$this->load->model('Guests_Model');
		$this->load->model('Booking_Model');
		$this->load->model('Customer_Type_Model');
		$this->load->model('Lead_Status_Model');
		$this->load->helper('guest_contact');
		$this->load->helper('ghl_lead_tags');
	}

	function index()
	{
		$titles = array('tab_title' => 'HolidayGoGoGo | Manual Leads', 'breadcrumb_title' => 'Manual Leads');
		$page   = max(1, (int) $this->input->get('page'));
		$limit  = 30;
		$offset = ($page - 1) * $limit;

		$this->Guests_Model->Set_Mode('manual');
		$data['guests']         = $this->Guests_Model->Read_Guests($limit, $offset);
		$this->load->model('Ghl_Messages_Model');
		$data['msg_log_phones'] = $this->Ghl_Messages_Model->Phones_With_Messages_For_Guests($data['guests']);
		$data['chat_counts']    = $this->Chat_Counts_For_Guests($data['guests']);
		$data['status_log_counts'] = $this->Status_Log_Counts_For_Guests($data['guests']);
		$data['total']          = null;
		$data['page']           = $page;
		$data['limit']          = $limit;
		$data['list_base']      = 'Manual_Leads';
		$data['page_title']     = 'Manual Leads Records';
		$data['admins']         = $this->Booking_Model->Read_Admins();
		$data['sources']        = $this->Booking_Model->Read_Sources();
		$data['destinations']   = $this->Booking_Model->Read_Categories();
		$data['customer_types'] = $this->Customer_Type_Model->Read_Customer_Types();
		$data['lead_statuses']  = $this->Lead_Status_Model->Read_Lead_Statuses();
		$data['nationalities']  = $this->Guests_Model->Read_Distinct('Nationality');
		$data['languages']      = $this->Guests_Model->Read_Distinct('ChatLanguage');
		$data['lc_can_edit']    = lc_can_edit('manual_leads');
		$this->load->view('layout/header', $titles);
		$this->load->view('guests/index', $data);
		$this->load->view('layout/footer');
	}

	/**
	 * Export the filtered Manual Leads to Excel (all pages at once). Respects the
	 * same per-creator visibility as the listing (mode 'manual').
	 */
	function Download()
	{
		$this->load->helper('guest_list_export');
		$this->Guests_Model->Set_Mode('manual');
		$rows = $this->Guests_Model->Read_Guests_For_Export();
		guest_list_export_stream($rows, 'manual', 'MANUAL_LEADS_' . date('Ymd') . '.xlsx');
	}

	/**
	 * Create a hand-entered ("Manual") lead from the Create Lead modal. Stored in
	 * ghl_contacts with a synthetic "manual:<uid>" id, lead_source = 'manual' and
	 * created_by = the current admin (drives per-creator visibility). The GHL API
	 * sync never touches it. Redirects back to the listing with a flash message.
	 */
	function Create()
	{
		if(lc_block_edit('manual_leads')) { return; }
		$this->load->helper('ghl_manual_lead');
		$this->load->model('Ghl_Contacts_Model');

		// Unique suffix for the synthetic "manual:<uid>" contact_id (the helper
		// adds the prefix). uniqid(more_entropy) is unique enough for hand entry.
		$uid = uniqid('', true);
		$now = date('Y-m-d H:i:s');

		// Creator is taken from the session, NEVER from the POST body.
		$created_by = (int) $this->session->userdata('admin_id');

		$prepared = ghl_manual_lead_prepare($this->input->post(), $uid, $now, $created_by);

		if (!$prepared['ok']) {
			$this->session->set_flashdata('ghl_lead_error', implode(' ', $prepared['errors']));
			redirect(base_url('Manual_Leads'));
			return;
		}

		$new_id = $this->Ghl_Contacts_Model->create_manual_lead($prepared['row']);
		if ($new_id) {
			// Seed any dated Lead Status log rows entered on the create form, keyed
			// to the new lead's listing dedup_key (author = the creator).
			$this->Seed_Status_Log((int) $new_id, $created_by);
			$this->session->set_flashdata('ghl_lead_success', 'Manual lead created.');
		} else {
			$this->session->set_flashdata('ghl_lead_error', 'Could not save the lead. Please try again.');
		}
		redirect(base_url('Manual_Leads'));
	}

	/**
	 * Insert the dated Lead Status updates entered in the Create Lead form's
	 * repeatable block (parallel arrays log_date[]/log_status[]/log_note[]) for a
	 * just-created lead. A row is stored only when it has a Status; its date
	 * defaults to today when left blank. Invalid rows are skipped silently — the
	 * lead itself is already saved, so a bad log row must not fail the create.
	 */
	private function Seed_Status_Log($new_id, $created_by)
	{
		$dates    = (array) $this->input->post('log_date');
		$statuses = (array) $this->input->post('log_status');
		$notes    = (array) $this->input->post('log_note');
		if (empty($statuses)) {
			return;
		}

		$dedup_key = $this->Ghl_Contacts_Model->effective_dedup_key($new_id);
		if ($dedup_key === null) {
			return;
		}

		$today = date('Y-m-d');
		foreach ($statuses as $i => $raw_status) {
			$st = lead_status_log_validate_status((string) $raw_status);
			if (!$st['ok']) {
				continue; // no status on this row -> not an entry
			}
			$dt = guest_remark_validate_date(isset($dates[$i]) ? (string) $dates[$i] : '', false, 'Date');
			$date = ($dt['ok'] && $dt['value'] !== '') ? $dt['value'] : $today;
			$nt = lead_status_log_validate_note(isset($notes[$i]) ? (string) $notes[$i] : '');
			$note = $nt['ok'] ? $nt['value'] : '';
			$this->Guests_Model->Add_Lead_Status_Log($dedup_key, $date, $st['value'], $note, $created_by);
		}
	}

	/**
	 * Read one manual lead's full detail for the Action ▸ View modal. GET ?dedup_key=…
	 * Returns { ok, lead:{...formatted fields...} }. Respects per-creator visibility
	 * (Read_Manual_Lead_Detail applies the same 'manual' scope as the listing), so a
	 * viewer can only open a lead they are allowed to see.
	 */
	function View_Lead()
	{
		$out = function ($data) {
			$this->output->set_content_type('application/json')->set_output(json_encode($data));
		};
		$dedup_key = (string) $this->input->get('dedup_key');
		if ($dedup_key === '') {
			return $out(array('ok' => false, 'message' => 'Missing lead reference.'));
		}
		$row = $this->Guests_Model->Read_Manual_Lead_Detail($dedup_key);
		if (!$row) {
			return $out(array('ok' => false, 'message' => 'Lead not found.'));
		}

		$tags = ghl_lead_tags_parse($row->tags_json);
		$name = trim((string) ($row->first_name ?? '') . ' ' . (string) ($row->last_name ?? ''));
		$dob  = ($row->date_of_birth && $row->date_of_birth !== '0000-00-00')
			? date('d M Y', strtotime($row->date_of_birth)) : '';
		$created = ($row->created_at && $row->created_at !== '0000-00-00 00:00:00')
			? date('d M Y, g:i A', strtotime($row->created_at)) : '';

		return $out(array('ok' => true, 'lead' => array(
			'name'          => $name,
			'company_name'  => $row->company_name,
			'phone'         => $row->phone,
			'email'         => $row->email,
			'address'       => $row->address,
			'gender'        => $row->gender,
			'chat_language' => $row->chat_language,
			'race'          => $row->race,
			'nationality'   => $row->nationality,
			'country'       => $row->country,
			'source'        => $row->source,
			'customer_type' => $row->customer_type,
			'lead_intro'    => $row->lead_intro,
			'lead_status'   => $row->lead_status,
			'notes'         => $row->notes,
			'date_of_birth' => $dob,
			'tags'          => array_values($tags),
			'created_by'    => $row->CreatedByName,
			'created_at'    => $created,
		)));
	}

	/**
	 * Read the Lead Status log for one lead (Action menu modal). GET ?dedup_key=…
	 * Returns { ok, entries:[{id,status_date,lead_status,note,created_by,can_delete}] }.
	 */
	function Lead_Status_Log()
	{
		$out = function ($data) {
			$this->output->set_content_type('application/json')->set_output(json_encode($data));
		};
		$dedup_key = (string) $this->input->get('dedup_key');
		if ($dedup_key === '') {
			return $out(array('ok' => false, 'message' => 'Missing lead reference.'));
		}
		$admin_id = $this->session->userdata('admin_id');
		$rows = $this->Guests_Model->Read_Lead_Status_Log($dedup_key, $admin_id);
		$entries = array();
		foreach ($rows as $r) {
			$entries[] = array(
				'id'          => (int) $r->LogID,
				'status_date' => $r->StatusDate,
				'lead_status' => $r->LeadStatus,
				'note'        => $r->Note !== null ? $r->Note : '',
				'created_by'  => $r->CreatedByName !== null ? $r->CreatedByName : '',
				'can_delete'  => (bool) $r->CanDelete,
			);
		}
		return $out(array('ok' => true, 'entries' => $entries));
	}

	/**
	 * Add one dated Lead Status log entry (Action menu modal). POST dedup_key,
	 * status_date, lead_status, note. Author is the session admin, never POST.
	 */
	function Add_Lead_Status_Log()
	{
		if(lc_block_edit('manual_leads')) { return; }
		$out = function ($data) {
			$this->output->set_content_type('application/json')->set_output(json_encode($data));
		};
		$dedup_key = (string) $this->input->post('dedup_key');
		if ($dedup_key === '') {
			return $out(array('ok' => false, 'message' => 'Missing lead reference.'));
		}
		$dt = guest_remark_validate_date((string) $this->input->post('status_date'), true, 'Date');
		if (!$dt['ok']) {
			return $out(array('ok' => false, 'message' => $dt['error']));
		}
		$st = lead_status_log_validate_status((string) $this->input->post('lead_status'));
		if (!$st['ok']) {
			return $out(array('ok' => false, 'message' => $st['error']));
		}
		$nt = lead_status_log_validate_note((string) $this->input->post('note'));
		if (!$nt['ok']) {
			return $out(array('ok' => false, 'message' => $nt['error']));
		}
		$admin_id = $this->session->userdata('admin_id');
		$id = $this->Guests_Model->Add_Lead_Status_Log($dedup_key, $dt['value'], $st['value'], $nt['value'], $admin_id);
		return $out(array('ok' => true, 'entry' => array('id' => (int) $id)));
	}

	/**
	 * Soft-delete one Lead Status log entry (author only). POST id.
	 */
	function Delete_Lead_Status_Log()
	{
		if(lc_block_edit('manual_leads')) { return; }
		$out = function ($data) {
			$this->output->set_content_type('application/json')->set_output(json_encode($data));
		};
		$log_id = (int) $this->input->post('id');
		if ($log_id < 1) {
			return $out(array('ok' => false, 'message' => 'Missing entry reference.'));
		}
		$affected = $this->Guests_Model->Delete_Lead_Status_Log($log_id, $this->session->userdata('admin_id'));
		if ($affected < 1) {
			return $out(array('ok' => false, 'message' => 'You can only delete your own entry.'));
		}
		return $out(array('ok' => true));
	}

	/**
	 * Download the Bulk Upload template: an .xlsx whose header row is the manual-
	 * lead field labels (ghl_manual_lead_import_columns), with one greyed sample
	 * row so the format is obvious. Mirrors Customer::Import_Template().
	 */
	function Import_Template()
	{
		$this->load->helper('ghl_manual_lead');

		$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
		$sheet = $spreadsheet->getActiveSheet();
		$sheet->setTitle('Manual Lead Template');
		$spreadsheet->getProperties()->setCreator('HolidayGoGoGo');

		$headers = array_keys(ghl_manual_lead_import_columns());
		$col = 'A';
		foreach ($headers as $label) {
			$sheet->setCellValueExplicit($col . '1', $label, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
			$sheet->getColumnDimension($col)->setWidth(20);
			$col++;
		}
		$last_col = chr(ord('A') + count($headers) - 1); // 17 cols => 'Q'
		$sheet->getStyle('A1:' . $last_col . '1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_BLACK);
		$sheet->getStyle('A1:' . $last_col . '1')->getFont()->getColor()->setARGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE);
		$sheet->getStyle('A1:' . $last_col . '1')->getFont()->setBold(true);

		// One greyed sample row; delete before importing. NAME or CONTACT NUMBER
		// (or EMAIL) must be filled — a fully-blank row is skipped.
		$sample = array('ALI BIN ABU', 'Acme Sdn Bhd', '0123456789', 'ali@example.com',
			'No 1, Jalan Besar, 50000 KL', 'Male', 'Malay', 'Malay', 'Malaysia', 'Malaysia',
			'Facebook', 'Redang, VIP', 'Called twice, keen on Redang', 'Company',
			'Referred by existing client', 'New');
		$col = 'A';
		foreach ($sample as $val) {
			$sheet->setCellValueExplicit($col . '2', $val, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
			$col++;
		}
		$sheet->getStyle('A2:' . $last_col . '2')->getFont()->getColor()->setARGB('FF999999');

		$filename = 'MANUAL_LEAD_IMPORT_TEMPLATE_' . date('Ymd') . '.xlsx';
		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header('Content-Disposition: attachment;filename="' . $filename . '"');
		header('Cache-Control: max-age=0');
		$writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
		$writer->save('php://output');
	}

	/**
	 * Bulk-create manual leads from an uploaded template (Import_Template output,
	 * rows filled). Each row is mapped to the SAME POST shape the Create modal
	 * sends (ghl_manual_lead_row_to_post) and run through the SAME validator
	 * (ghl_manual_lead_prepare), so bulk and single create behave identically.
	 * created_by is the current admin (drives per-creator visibility). The upload
	 * is backed up under assets/upload/manual_lead_import/ (newest 3 kept).
	 */
	function Import()
	{
		if(lc_block_edit('manual_leads')) { return; }
		$this->load->helper('ghl_manual_lead');
		$this->load->model('Ghl_Contacts_Model');

		if ($this->input->server('REQUEST_METHOD') !== 'POST' || empty($_FILES['import_file']['name'])) {
			redirect(base_url('Manual_Leads'));
			return;
		}

		$file = $_FILES['import_file'];
		if ($file['error'] !== UPLOAD_ERR_OK) {
			$this->session->set_flashdata('ghl_lead_error', 'Upload failed. Please try again.');
			redirect(base_url('Manual_Leads'));
			return;
		}
		$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
		if (!in_array($ext, array('xlsx', 'xls'), true)) {
			$this->session->set_flashdata('ghl_lead_error', 'Please upload an Excel file (.xlsx or .xls).');
			redirect(base_url('Manual_Leads'));
			return;
		}
		if ($file['size'] > 10 * 1024 * 1024) {
			$this->session->set_flashdata('ghl_lead_error', 'File too large. Maximum size is 10MB.');
			redirect(base_url('Manual_Leads'));
			return;
		}

		$dir = FCPATH . 'assets/upload/manual_lead_import/';
		if (!is_dir($dir)) {
			mkdir($dir, 0755, true);
		}
		$dest = $dir . 'manual_lead_import_' . time() . '.' . $ext;
		if (!move_uploaded_file($file['tmp_name'], $dest)) {
			$this->session->set_flashdata('ghl_lead_error', 'Could not save the uploaded file.');
			redirect(base_url('Manual_Leads'));
			return;
		}
		// Keep only the newest 3 uploads as backups.
		$stamped = array();
		foreach (glob($dir . 'manual_lead_import_*') as $path) {
			$stamped[$path] = filemtime($path);
		}
		arsort($stamped);
		foreach (array_slice(array_keys($stamped), 3) as $old) {
			@unlink($old);
		}

		try {
			$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($dest);
			$rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
		} catch (\Exception $e) {
			$this->session->set_flashdata('ghl_lead_error', 'Could not read the Excel file. Please use the downloaded template.');
			redirect(base_url('Manual_Leads'));
			return;
		}

		$created_by = (int) $this->session->userdata('admin_id');
		$now = date('Y-m-d H:i:s');
		$created = 0;
		$failed = array();
		$line = 0;
		foreach ((array) $rows as $row) {
			$line++;
			$post = ghl_manual_lead_row_to_post($row);
			if ($post === null) {
				continue; // header / blank row
			}
			$uid = uniqid('', true);
			$prepared = ghl_manual_lead_prepare($post, $uid, $now, $created_by);
			if (!$prepared['ok']) {
				$name = trim($post['first_name'] ?? '');
				$failed[] = 'row ' . $line . ($name !== '' ? ' (' . $name . ')' : '') . ' — ' . implode(' ', $prepared['errors']);
				continue;
			}
			if ($this->Ghl_Contacts_Model->create_manual_lead($prepared['row'])) {
				$created++;
			} else {
				$failed[] = 'row ' . $line . ' — could not save';
			}
		}

		$msg = $created . ' manual lead(s) created.';
		if (!empty($failed)) {
			$msg .= ' Failed ' . count($failed) . ' row(s): ' . implode('; ', $failed) . '.';
		}
		$key = $created > 0 ? 'ghl_lead_success' : 'ghl_lead_error';
		if ($created === 0 && empty($failed)) {
			$msg = 'No lead rows found in the file. Nothing was created.';
		}
		$this->session->set_flashdata($key, $msg);
		redirect(base_url('Manual_Leads'));
	}

	/**
	 * dedup_key => active chat-file count for the leads on this page (badges the
	 * Action menu with "Chat History (n)").
	 */
	private function Chat_Counts_For_Guests($guests)
	{
		$keys = array();
		foreach ((array) $guests as $g) {
			if (!empty($g->dedup_key)) {
				$keys[] = $g->dedup_key;
			}
		}
		return $this->Guests_Model->Read_Chat_History_Counts($keys);
	}

	/**
	 * dedup_key => active Lead Status log count for the leads on this page (badges
	 * the Action menu with "Lead Status (n)").
	 */
	private function Status_Log_Counts_For_Guests($guests)
	{
		$keys = array();
		foreach ((array) $guests as $g) {
			if (!empty($g->dedup_key)) {
				$keys[] = $g->dedup_key;
			}
		}
		return $this->Guests_Model->Read_Lead_Status_Log_Counts($keys);
	}

	function Count()
	{
		$page  = max(1, (int) $this->input->get('page'));
		$limit = 30;
		$this->Guests_Model->Set_Mode('manual');
		$total = (int) $this->Guests_Model->Count_Guests();

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
}
