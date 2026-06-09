<?php
class Faq extends MY_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->load->model('Faq_Model');
		$this->load->model('Universal_Model');
	}

	function index()
	{
		$titles = array('tab_title' => 'HolidayGoGoGo | FAQ', 'breadcrumb_title' => 'FAQ');
		$data['faqs'] = $this->Faq_Model->Read_Faqs();
		$this->load->view('layout/header', $titles);
		$this->load->view('faq/index', $data);
		$this->load->view('layout/footer');
	}

	function Create()
	{
		if($this->input->post()) {
			if($this->Save_From_Post(null) === false) {
				$this->session->set_flashdata('faq_error', 'Failed to create FAQ. Title is required.');
				redirect(base_url('Faq/Create'));
				return;
			}
			$this->session->set_flashdata('faq_success', 'FAQ created.');
			redirect(base_url('Faq'));
			return;
		}

		$titles = array('tab_title' => 'HolidayGoGoGo | FAQ', 'breadcrumb_title' => 'FAQ >> Create');
		$data['mode'] = 'create';
		$data['faq']  = (object) array('FAQID' => 0, 'Title' => '', 'Description' => '', 'Type' => 'internal', 'DisplayOrder' => 0);
		$this->load->view('layout/header', $titles);
		$this->load->view('faq/form', $data);
		$this->load->view('layout/footer');
	}

	function Update()
	{
		$id = (int)$this->input->get('faq_id');
		if($this->input->post()) {
			$post_id = (int)$this->input->post('faq_id');
			if(!$this->Universal_Model->Validate_Id('FAQID', $post_id, 'faq')) {
				redirect(base_url('Faq'));
				return;
			}
			if($this->Save_From_Post($post_id) === false) {
				$this->session->set_flashdata('faq_error', 'Failed to update FAQ. Title is required.');
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
		$this->load->view('layout/header', $titles);
		$this->load->view('faq/form', $data);
		$this->load->view('layout/footer');
	}

	function Delete()
	{
		$this->Universal_Model->Delete('FAQID', $this->input->get('faq_id'), 'faq');
	}

	// Internal FAQ page (blue theme). Login-protected via MY_Controller so it
	// cannot be reached by guessing a URL the way the public /faq/external page
	// can. Renders the same accordion view as the external page.
	function Internal()
	{
		$data['type'] = 'internal';
		$data['faqs'] = $this->Faq_Model->Read_Public('internal');
		$this->load->view('faq/display', $data);
	}

	private function Save_From_Post($id)
	{
		$title = trim((string)$this->input->post('Title'));
		if($title === '') {
			return false;
		}

		$type = $this->input->post('Type');
		if(!in_array($type, array('internal', 'external'), true)) {
			$type = 'internal';
		}

		$data = array(
			'Title'        => $title,
			'Description'  => (string)$this->input->post('Description'),
			'Type'         => $type,
			'DisplayOrder' => (int)$this->input->post('DisplayOrder'),
		);

		if($id === null) {
			return $this->Faq_Model->Create($data);
		}
		return $this->Faq_Model->Update($id, $data);
	}
}
