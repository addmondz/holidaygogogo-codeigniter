<?php
require FCPATH.'vendor/autoload.php';

class Faq extends MY_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->load->model('Faq_Model');
		$this->load->model('Faq_Tag_Model');
		$this->load->model('Universal_Model');
	}

	function index()
	{
		if(!$this->Can_View()) {
			redirect(base_url('Dashboard'));
			return;
		}

		// Self-healing backfill: stamp a slug onto any FAQ created before the
		// per-FAQ page existed, so every listed row has a working page link.
		// No-op (one cheap SELECT) once every row has a slug.
		$this->Faq_Model->Backfill_Slugs();

		$titles = array('tab_title' => 'HolidayGoGoGo | FAQ', 'breadcrumb_title' => 'FAQ');
		$data['faqs'] = $this->Faq_Model->Read_Faqs();
		$data['tags'] = $this->Faq_Tag_Model->Read_Active();
		$data['destinations'] = $this->Faq_Model->Read_Destinations();
		// Gates the Create / Edit / Delete controls in the listing view.
		$data['can_edit'] = $this->Can_Edit();
		$this->load->view('layout/header', $titles);
		$this->load->view('faq/index', $data);
		$this->load->view('layout/footer');
	}

	// Access-control gates. OWNER (level 10) always passes (bypass); every other
	// role needs the matching code assigned on their admin record:
	//   'FV' (FAQ VIEW ACCESS) to reach the listing and the per-FAQ pages,
	//   'FE' (FAQ EDIT ACCESS) to create / edit / delete.
	private function Can_View()
	{
		return (int)$this->session->level === 10 || in_array('FV', (array)$this->session->access_control);
	}

	private function Can_Edit()
	{
		return (int)$this->session->level === 10 || in_array('FE', (array)$this->session->access_control);
	}

	// OWNER (level 10) only. Gates the "download every FAQ at once" exports, which
	// pull the whole internal library into one file - kept owner-only regardless
	// of FV/FE so bulk extraction isn't handed to every viewer/editor.
	private function Can_Owner()
	{
		return (int)$this->session->level === 10;
	}

	// Active internal FAQs flattened into export rows (one per sub-Q&A), shared by
	// the Excel and PDF downloads so both formats carry identical content. Mirrors
	// Internal(): Read_Faqs() returns both types, so internal is filtered here.
	private function Export_Faqs()
	{
		$faqs = array_values(array_filter($this->Faq_Model->Read_Faqs(), function($faq) {
			return $faq->Type === 'internal';
		}));
		return Faq_Model::Export_Rows($faqs, $this->Faq_Model->Tag_Name_Map());
	}

	// Excel (.xlsx) export of every internal FAQ - one row per sub-Q&A. OWNER only.
	function Download()
	{
		if(!$this->Can_Owner()) {
			redirect(base_url('Faq'));
			return;
		}
		$rows = $this->Export_Faqs();

		$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
		$sheet = $spreadsheet->getActiveSheet();
		$sheet->setTitle('FAQ Records');
		$spreadsheet->getProperties()->setCreator('HolidayGoGoGo');

		$headers = array('A' => 'FAQ', 'B' => 'DESTINATION', 'C' => 'QUESTION', 'D' => 'ANSWER', 'E' => 'TAGS', 'F' => 'LAST UPDATED');
		foreach($headers as $col => $label) {
			$sheet->setCellValue($col . '1', $label);
		}
		$sheet->getStyle('A1:F1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_BLACK);
		$sheet->getStyle('A1:F1')->getFont()->getColor()->setARGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE);
		$sheet->getStyle('A1:F1')->getFont()->setBold(true);

		if(!empty($rows)) {
			$r = 2;
			foreach($rows as $row) {
				$updated = '';
				if($row['updated'] !== '') {
					$ts = strtotime($row['updated']);
					$updated = $ts ? date('j M Y', $ts) : $row['updated'];
				}
				$sheet->setCellValueExplicit('A' . $r, $row['title'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$sheet->setCellValueExplicit('B' . $r, $row['destinations'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$sheet->setCellValueExplicit('C' . $r, $row['question'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$sheet->setCellValueExplicit('D' . $r, $row['answer'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$sheet->setCellValueExplicit('E' . $r, $row['tags'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$sheet->setCellValueExplicit('F' . $r, $updated, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$r++;
			}
			// Wrap the long free-text columns and top-align every data cell so the
			// multi-line questions/answers stay readable.
			$last = $r - 1;
			$sheet->getStyle('C2:D' . $last)->getAlignment()->setWrapText(true);
			$sheet->getStyle('A2:F' . $last)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP);
		} else {
			$sheet->mergeCells('A2:F2');
			$sheet->getCell('A2')->setValue('FAQ Records Not Found');
			$sheet->getStyle('A2:F2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
		}

		$sheet->getColumnDimension('A')->setWidth(28);
		$sheet->getColumnDimension('B')->setWidth(22);
		$sheet->getColumnDimension('C')->setWidth(45);
		$sheet->getColumnDimension('D')->setWidth(60);
		$sheet->getColumnDimension('E')->setWidth(22);
		$sheet->getColumnDimension('F')->setWidth(16);

		$filename = 'FAQ_RECORDS_' . date('Ymd') . '.xlsx';
		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header('Content-Disposition: attachment;filename="' . $filename . '"');
		header('Cache-Control: max-age=0');
		header('Cache-Control: max-age=1');
		$writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
		$writer->save('php://output');
	}

	// PDF export of every internal FAQ, rendered through a print-friendly view
	// that mirrors the FAQ Library design. OWNER only.
	function Download_Pdf()
	{
		if(!$this->Can_Owner()) {
			redirect(base_url('Faq'));
			return;
		}
		$html = $this->load->view('faq/export_pdf', array('rows' => $this->Export_Faqs()), true);

		$this->load->library('pdf');
		$this->dompdf->loadHtml($html);
		$this->dompdf->setPaper('A4', 'portrait');
		$this->dompdf->render();
		$output = $this->dompdf->output();

		if(ob_get_length()) { ob_end_clean(); }

		$filename = 'FAQ_RECORDS_' . date('Ymd') . '.pdf';
		header('Content-Type: application/pdf');
		header('Content-Disposition: attachment; filename="' . $filename . '"');
		header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private');
		header('Pragma: no-cache');
		header('Expires: 0');
		header('Content-Length: ' . strlen($output));
		echo $output;
	}

	// Round-trip Excel template of every internal FAQ - one row per sub-Q&A, in
	// the exact columns Import() reads back (FAQ | DESTINATION | QUESTION |
	// ANSWER | TAGS). The owner downloads this, edits/adds rows, and re-imports
	// to replace the whole internal library. OWNER only.
	function Export_Template()
	{
		if(!$this->Can_Owner()) {
			redirect(base_url('Faq'));
			return;
		}
		$rows = $this->Export_Faqs();

		$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
		$sheet = $spreadsheet->getActiveSheet();
		$sheet->setTitle('FAQ Template');
		$spreadsheet->getProperties()->setCreator('HolidayGoGoGo');

		$headers = array('A' => 'FAQ', 'B' => 'DESTINATION', 'C' => 'QUESTION', 'D' => 'ANSWER', 'E' => 'TAGS');
		foreach($headers as $col => $label) {
			$sheet->setCellValue($col . '1', $label);
		}
		$sheet->getStyle('A1:E1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_BLACK);
		$sheet->getStyle('A1:E1')->getFont()->getColor()->setARGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE);
		$sheet->getStyle('A1:E1')->getFont()->setBold(true);

		if(!empty($rows)) {
			$r = 2;
			foreach($rows as $row) {
				$sheet->setCellValueExplicit('A' . $r, $row['title'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$sheet->setCellValueExplicit('B' . $r, $row['destinations'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$sheet->setCellValueExplicit('C' . $r, $row['question'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$sheet->setCellValueExplicit('D' . $r, $row['answer'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$sheet->setCellValueExplicit('E' . $r, $row['tags'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$r++;
			}
			$last = $r - 1;
			$sheet->getStyle('C2:D' . $last)->getAlignment()->setWrapText(true);
			$sheet->getStyle('A2:E' . $last)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP);
		}

		$sheet->getColumnDimension('A')->setWidth(28);
		$sheet->getColumnDimension('B')->setWidth(22);
		$sheet->getColumnDimension('C')->setWidth(45);
		$sheet->getColumnDimension('D')->setWidth(60);
		$sheet->getColumnDimension('E')->setWidth(22);

		$filename = 'FAQ_TEMPLATE_' . date('Ymd') . '.xlsx';
		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header('Content-Disposition: attachment;filename="' . $filename . '"');
		header('Cache-Control: max-age=0');
		$writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
		$writer->save('php://output');
	}

	// Wipe-and-rebuild the internal FAQ library from an uploaded template (the
	// file Export_Template() produced, with rows edited/added). OWNER only. The
	// upload is stored under assets/upload/faq_import/ and the newest 3 are kept
	// as backups; older ones are pruned. The whole rebuild runs in a transaction
	// (Replace_Internal), so a failure leaves the old library intact.
	function Import()
	{
		if(!$this->Can_Owner()) {
			redirect(base_url('Faq'));
			return;
		}
		if($this->input->server('REQUEST_METHOD') !== 'POST' || empty($_FILES['import_file']['name'])) {
			redirect(base_url('Faq'));
			return;
		}

		$file = $_FILES['import_file'];
		if($file['error'] !== UPLOAD_ERR_OK) {
			$this->session->set_flashdata('faq_error', 'Upload failed. Please try again.');
			redirect(base_url('Faq'));
			return;
		}
		$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
		if(!in_array($ext, array('xlsx', 'xls'), true)) {
			$this->session->set_flashdata('faq_error', 'Please upload an Excel file (.xlsx or .xls).');
			redirect(base_url('Faq'));
			return;
		}
		if($file['size'] > 10 * 1024 * 1024) {
			$this->session->set_flashdata('faq_error', 'File too large. Maximum size is 10MB.');
			redirect(base_url('Faq'));
			return;
		}

		// Save the upload as a backup (the file itself is the backup), then keep
		// only the newest 3 (Prune_Backups decides which to remove).
		$dir = FCPATH . 'assets/upload/faq_import/';
		if(!is_dir($dir)) {
			mkdir($dir, 0755, true);
		}
		$dest = $dir . 'faq_import_' . time() . '.' . $ext;
		if(!move_uploaded_file($file['tmp_name'], $dest)) {
			$this->session->set_flashdata('faq_error', 'Could not save the uploaded file.');
			redirect(base_url('Faq'));
			return;
		}
		$existing = array();
		foreach(glob($dir . 'faq_import_*') as $path) {
			$existing[] = basename($path);
		}
		foreach(Faq_Model::Prune_Backups($existing, 3) as $old) {
			@unlink($dir . $old);
		}

		// Read every cell as a 0-indexed row array, matching Parse_Import's columns.
		try {
			$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($dest);
			$rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
		} catch(\Exception $e) {
			$this->session->set_flashdata('faq_error', 'Could not read the Excel file. Please use the exported template.');
			redirect(base_url('Faq'));
			return;
		}

		$parsed = Faq_Model::Parse_Import($rows);
		if(empty($parsed)) {
			$this->session->set_flashdata('faq_error', 'No FAQ rows found in the file. Nothing was changed.');
			redirect(base_url('Faq'));
			return;
		}

		$summary = $this->Faq_Model->Replace_Internal($parsed);

		$msg = 'Import complete: ' . (int)$summary['faqs'] . ' FAQ(s) and ' . (int)$summary['items'] . ' question(s) rebuilt.';
		if(!empty($summary['tags_created'])) {
			$msg .= ' New tags created: ' . implode(', ', array_values(array_unique($summary['tags_created']))) . '.';
		}
		if(!empty($summary['destinations_skipped'])) {
			$msg .= ' Skipped unknown destinations: ' . implode(', ', $summary['destinations_skipped']) . '.';
		}
		$this->session->set_flashdata('faq_success', $msg);
		redirect(base_url('Faq'));
	}

	function Create()
	{
		if(!$this->Can_Edit()) {
			redirect(base_url('Faq'));
			return;
		}
		if($this->input->post()) {
			$error = $this->Save_From_Post(null);
			if($error !== true) {
				$this->session->set_flashdata('faq_error', $error);
				redirect(base_url('Faq/Create'));
				return;
			}
			$this->session->set_flashdata('faq_success', 'FAQ created.');
			redirect(base_url('Faq'));
			return;
		}

		$titles = array('tab_title' => 'HolidayGoGoGo | FAQ', 'breadcrumb_title' => 'FAQ >> Create');
		$data['mode'] = 'create';
		$data['faq']  = (object) array('FAQID' => 0, 'Title' => '', 'Description' => '', 'Type' => 'internal');
		$data['items'] = array();
		$data['tags'] = $this->Faq_Tag_Model->Read_Active();
		$data['destinations'] = $this->Faq_Model->Read_Destinations();
		$data['selected_destination_ids'] = array();
		$this->load->view('layout/header', $titles);
		$this->load->view('faq/form', $data);
		$this->load->view('layout/footer');
	}

	function Update()
	{
		if(!$this->Can_Edit()) {
			redirect(base_url('Faq'));
			return;
		}
		$id = (int)$this->input->get('faq_id');
		if($this->input->post()) {
			$post_id = (int)$this->input->post('faq_id');
			if(!$this->Universal_Model->Validate_Id('FAQID', $post_id, 'faq')) {
				redirect(base_url('Faq'));
				return;
			}
			$error = $this->Save_From_Post($post_id);
			if($error !== true) {
				$this->session->set_flashdata('faq_error', $error);
				redirect(base_url('Faq/Update?faq_id=') . $post_id);
				return;
			}
			$this->session->set_flashdata('faq_success', 'FAQ updated.');
			redirect(base_url('Faq'));
			return;
		}

		if(!$this->Universal_Model->Validate_Id('FAQID', $id, 'faq')) {
			redirect(base_url('Faq'));
			return;
		}

		$titles = array('tab_title' => 'HolidayGoGoGo | FAQ', 'breadcrumb_title' => 'FAQ >> Update');
		$data['mode'] = 'update';
		$data['faq']  = $this->Faq_Model->Read_Faq($id);
		$data['items'] = Faq_Model::Decode_Items($data['faq']->Description);
		$data['tags'] = $this->Faq_Tag_Model->Read_Active();
		$data['destinations'] = $this->Faq_Model->Read_Destinations();
		$data['selected_destination_ids'] = $this->Faq_Model->Read_Destination_Ids($id);
		$this->load->view('layout/header', $titles);
		$this->load->view('faq/form', $data);
		$this->load->view('layout/footer');
	}

	function Delete()
	{
		if(!$this->Can_Edit()) {
			return;
		}
		$this->Universal_Model->Delete('FAQID', $this->input->get('faq_id'), 'faq');
	}

	// Per-FAQ page: each FAQ gets its own URL at /faq/<slug> (routed here).
	// Staff-only via MY_Controller, so the internal-only content (sub-Q&As,
	// tags, destinations, audit) is safe to show. Only active internal FAQs are
	// reachable; anything else (including external FAQs) 404s.
	function Page($slug = '')
	{
		if(!$this->Can_View()) {
			redirect(base_url('Dashboard'));
			return;
		}
		$faq = $this->Faq_Model->Read_By_Slug($slug);
		if($faq === null || $faq->Type !== 'internal') {
			show_404();
			return;
		}
		$data['faq'] = $faq;
		$data['tag_names'] = $this->Faq_Model->Tag_Name_Map();
		$this->load->view('faq/page', $data);
	}

	// Grouped page: every active internal FAQ rendered together on one screen
	// (/Faq/Internal), with a single search box and tag filter spanning the whole
	// set. Same FV/owner gate and internal-only scope as Page(), so the staff-only
	// content is safe to show. Honours the same ?tag= / ?destination= filters as
	// the listing (Read_Faqs reads them), so a filtered listing can hand its
	// query string straight to this page to narrow the group.
	function Internal()
	{
		if(!$this->Can_View()) {
			redirect(base_url('Dashboard'));
			return;
		}
		// Read_Faqs() returns both types; the grouped page mirrors Page() and
		// shows internal FAQs only.
		$faqs = $this->Faq_Model->Read_Faqs();
		$data['faqs'] = array_values(array_filter($faqs, function($faq) {
			return $faq->Type === 'internal';
		}));
		$data['tag_names'] = $this->Faq_Model->Tag_Name_Map();
		$this->load->view('faq/all', $data);
	}

	// Returns true on success, or an error message string on failure.
	private function Save_From_Post($id)
	{
		$title = trim((string)$this->input->post('Title'));
		if($title === '') {
			return 'Failed to save FAQ. Title is required.';
		}

		// Per-item audit: the form posts the prior created/updated stamps and the
		// original text back as hidden fields (parallel arrays aligned by row).
		// Build_Items keeps cb/cd, and bumps ub/ud only for rows whose text changed.
		$meta = array(
			'cb' => $this->input->post('sub_cb'),
			'cd' => $this->input->post('sub_cd'),
			'ub' => $this->input->post('sub_ub'),
			'ud' => $this->input->post('sub_ud'),
			'oq' => $this->input->post('sub_oq'),
			'oa' => $this->input->post('sub_oa'),
		);
		$actor = (string)$this->session->userdata('name');
		// Per-item tags: each row's multi-select posts as sub_tags[<row>][].
		// array_values() drops the row keys to a 0..n-1 list aligned with the
		// other parallel sub_* arrays (Build_Items reads them by index).
		$sub_tags = $this->input->post('sub_tags');
		$sub_tags = is_array($sub_tags) ? array_values($sub_tags) : array();
		$built = Faq_Model::Build_Items($this->input->post('sub_questions'), $this->input->post('sub_answers'), $meta, $actor, date('Y-m-d H:i:s'), $sub_tags);
		if($built['error'] !== null) {
			return $built['error'];
		}

		$type = $this->input->post('Type');
		if(!in_array($type, array('internal', 'external'), true)) {
			$type = 'internal';
		}

		$data = array(
			'Title'        => $title,
			// Unique /faq/<slug> for this FAQ, derived from the title and
			// disambiguated against existing slugs (excluding this row on edit).
			'Slug'         => $this->Faq_Model->Generate_Slug($title, $id === null ? 0 : $id),
			'Description'  => Faq_Model::Encode_Items($built['items']),
			'Type'         => $type,
		);

		$destination_ids = $this->input->post('Destinations');

		if($id === null) {
			$new_id = $this->Faq_Model->Create($data);
			$this->Faq_Model->Sync_Destinations($new_id, $destination_ids);
		} else {
			$this->Faq_Model->Update($id, $data);
			$this->Faq_Model->Sync_Destinations($id, $destination_ids);
		}
		return true;
	}
}
