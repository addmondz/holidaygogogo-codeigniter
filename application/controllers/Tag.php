<?php
class Tag extends MY_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->load->model('Tag_Model');
		$this->load->model('Universal_Model');
	}

	function index()
	{
		$titles = array('tab_title' => 'HolidayGoGoGo | Tag', 'breadcrumb_title' => 'Tag');
		$array['tags'] = $this->Tag_Model->Read_Tags();
		$this->load->view('layout/header', $titles);
		$this->load->view('tag/index', $array);
		$this->load->view('layout/footer');
	}

	function Create()
	{
		if($this->input->is_ajax_request()) {
			$this->Tag_Model->Create();
		} else {
			$titles = array('tab_title' => 'HolidayGoGoGo | Tag', 'breadcrumb_title' => 'Tag >> Create');
			$array = array('TagID' => 'NA', 'Name' => 'NA');
			$this->load->view('layout/header', $titles);
			$this->load->view('tag/tag', $array);
			$this->load->view('layout/footer');
		}
	}

	function Update()
	{
		if($this->input->is_ajax_request()) {
			if(count($this->input->post('tag')[0]) > 3) {
				$this->Tag_Model->Update();
			}
		} else {
			$valid_tag_id = $this->Universal_Model->Validate_Id('TagID', $this->input->get('tag_id'), 'tag');
			if($valid_tag_id) {
				$titles = array('tab_title' => 'HolidayGoGoGo | Tag', 'breadcrumb_title' => 'Tag >> Update');
				$array = $this->Tag_Model->Read_Tag();
				$this->load->view('layout/header', $titles);
				$this->load->view('tag/tag', $array);
				$this->load->view('layout/footer');
			} else {
				redirect('Tag');
			}
		}
	}
	
	function Delete() 
	{
		$this->Universal_Model->Delete('TagID', $this->input->get('tag_id'), 'tag');
	}

	function Detect() {
		$redundant_name = $this->Tag_Model->Detect();
		if($redundant_name) {
			echo json_encode(true);
		} else {
			echo json_encode(false);
		}
	}
}