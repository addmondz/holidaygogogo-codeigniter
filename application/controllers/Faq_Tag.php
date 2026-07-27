<?php
class Faq_Tag extends MY_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->load->model('Faq_Tag_Model');
		$this->load->model('Universal_Model');
	}

	function index()
	{
		if(!$this->Can_View()) {
			redirect(base_url('Dashboard'));
			return;
		}

		$titles = array('tab_title' => 'HolidayGoGoGo | FAQ Tag', 'breadcrumb_title' => 'FAQ Tag');
		$data['tags'] = $this->Faq_Tag_Model->Read_Faq_Tags();
		// Gates the Create / Edit / Delete controls in the listing view.
		$data['can_edit'] = $this->Can_Edit();
		$this->load->view('layout/header', $titles);
		$this->load->view('faq_tag/index', $data);
		$this->load->view('layout/footer');
	}

	// Access-control gates. OWNER (level 10) always passes (bypass); every other
	// role needs the matching code assigned on their admin record. FAQ tags share
	// the FAQ permission codes:
	//   'FV' (FAQ VIEW ACCESS) to reach the listing,
	//   'FE' (FAQ EDIT ACCESS) to create / edit / delete.
	private function Can_View()
	{
		return (int)$this->session->level === 10 || in_array('FV', (array)$this->session->access_control);
	}

	private function Can_Edit()
	{
		return (int)$this->session->level === 10 || in_array('FE', (array)$this->session->access_control);
	}

	function Create()
	{
		if(!$this->Can_Edit()) {
			redirect(base_url('Faq_Tag'));
			return;
		}
		if($this->input->post()) {
			$error = $this->Save_From_Post(null);
			if($error !== true) {
				$this->session->set_flashdata('faq_tag_error', $error);
				redirect(base_url('Faq_Tag/Create'));
				return;
			}
			$this->session->set_flashdata('faq_tag_success', 'FAQ tag created.');
			redirect(base_url('Faq_Tag'));
			return;
		}

		$titles = array('tab_title' => 'HolidayGoGoGo | FAQ Tag', 'breadcrumb_title' => 'FAQ Tag >> Create');
		$data['mode'] = 'create';
		$data['tag']  = (object) array('FAQTagID' => 0, 'Name' => '', 'IsDefault' => 'N');
		$this->load->view('layout/header', $titles);
		$this->load->view('faq_tag/form', $data);
		$this->load->view('layout/footer');
	}

	function Update()
	{
		if(!$this->Can_Edit()) {
			redirect(base_url('Faq_Tag'));
			return;
		}
		$id = (int)$this->input->get('faq_tag_id');
		if($this->input->post()) {
			$post_id = (int)$this->input->post('faq_tag_id');
			if(!$this->Universal_Model->Validate_Id('FAQTagID', $post_id, 'faq_tag')) {
				redirect(base_url('Faq_Tag'));
				return;
			}
			$error = $this->Save_From_Post($post_id);
			if($error !== true) {
				$this->session->set_flashdata('faq_tag_error', $error);
				redirect(base_url('Faq_Tag/Update?faq_tag_id=') . $post_id);
				return;
			}
			$this->session->set_flashdata('faq_tag_success', 'FAQ tag updated.');
			redirect(base_url('Faq_Tag'));
			return;
		}

		if(!$this->Universal_Model->Validate_Id('FAQTagID', $id, 'faq_tag')) {
			redirect(base_url('Faq_Tag'));
			return;
		}

		$titles = array('tab_title' => 'HolidayGoGoGo | FAQ Tag', 'breadcrumb_title' => 'FAQ Tag >> Update');
		$data['mode'] = 'update';
		$data['tag']  = $this->Faq_Tag_Model->Read_Faq_Tag($id);
		$this->load->view('layout/header', $titles);
		$this->load->view('faq_tag/form', $data);
		$this->load->view('layout/footer');
	}

	function Delete()
	{
		if(!$this->Can_Edit()) {
			redirect(base_url('Faq_Tag'));
			return;
		}
		$this->Universal_Model->Delete('FAQTagID', $this->input->get('faq_tag_id'), 'faq_tag');
	}

	// Returns true on success, or an error message string on failure.
	private function Save_From_Post($id)
	{
		$name = trim((string)$this->input->post('Name'));
		if($name === '') {
			return 'Failed to save FAQ tag. Name is required.';
		}
		if($this->Faq_Tag_Model->Name_Exists($name, (int)$id)) {
			return 'A FAQ tag with that name already exists.';
		}

		$data = array(
			'Name'      => $name,
			'IsDefault' => $this->input->post('IsDefault') ? 'Y' : 'N',
		);

		if($id === null) {
			$this->Faq_Tag_Model->Create($data);
		} else {
			$this->Faq_Tag_Model->Update($id, $data);
		}
		return true;
	}
}
