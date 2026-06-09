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
		$titles = array('tab_title' => 'HolidayGoGoGo | FAQ Tag', 'breadcrumb_title' => 'FAQ Tag');
		$data['tags'] = $this->Faq_Tag_Model->Read_Faq_Tags();
		$this->load->view('layout/header', $titles);
		$this->load->view('faq_tag/index', $data);
		$this->load->view('layout/footer');
	}

	function Create()
	{
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
		$data['tag']  = (object) array('FAQTagID' => 0, 'Name' => '');
		$this->load->view('layout/header', $titles);
		$this->load->view('faq_tag/form', $data);
		$this->load->view('layout/footer');
	}

	function Update()
	{
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

		$data = array('Name' => $name);

		if($id === null) {
			$this->Faq_Tag_Model->Create($data);
		} else {
			$this->Faq_Tag_Model->Update($id, $data);
		}
		return true;
	}
}
