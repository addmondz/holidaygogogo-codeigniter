<?php
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
		// Self-healing backfill: stamp a slug onto any FAQ created before the
		// per-FAQ page existed, so every listed row has a working page link.
		// No-op (one cheap SELECT) once every row has a slug.
		$this->Faq_Model->Backfill_Slugs();

		$titles = array('tab_title' => 'HolidayGoGoGo | FAQ', 'breadcrumb_title' => 'FAQ');
		$data['faqs'] = $this->Faq_Model->Read_Faqs();
		$data['tags'] = $this->Faq_Tag_Model->Read_Active();
		$data['destinations'] = $this->Faq_Model->Read_Destinations();
		$this->load->view('layout/header', $titles);
		$this->load->view('faq/index', $data);
		$this->load->view('layout/footer');
	}

	// OWNER (level 10) is the only role allowed to create/edit/delete FAQs.
	// Everyone else has read-only access to the listing and the FAQ pages.
	private function Is_Owner()
	{
		return (int)$this->session->level === 10;
	}

	function Create()
	{
		if(!$this->Is_Owner()) {
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
		if(!$this->Is_Owner()) {
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
		if(!$this->Is_Owner()) {
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
		$faq = $this->Faq_Model->Read_By_Slug($slug);
		if($faq === null || $faq->Type !== 'internal') {
			show_404();
			return;
		}
		$data['faq'] = $faq;
		$data['tag_names'] = $this->Faq_Model->Tag_Name_Map();
		$this->load->view('faq/page', $data);
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
