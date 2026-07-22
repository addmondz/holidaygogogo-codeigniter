<?php
class Source extends MY_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->load->model('Source_Model');
		$this->load->model('Universal_Model');
	}

	function index()
	{
		$titles = array('tab_title' => 'HolidayGoGoGo | Source', 'breadcrumb_title' => 'Source');
		$array['sources'] = $this->Source_Model->Read_Sources();
		$this->load->view('layout/header', $titles);
		$this->load->view('source/index', $array);
		$this->load->view('layout/footer');
	}

	function Create()
	{
		if($this->input->is_ajax_request()) {
			$this->Source_Model->Create();
		} else {
			$titles = array('tab_title' => 'HolidayGoGoGo | Source', 'breadcrumb_title' => 'Source >> Create');
			$array = array('SourceID' => 'NA', 'Name' => 'NA');
			$this->load->view('layout/header', $titles);
			$this->load->view('source/source', $array);
			$this->load->view('layout/footer');
		}
	}

	function Update()
	{
		if($this->input->is_ajax_request()) {
			if(count($this->input->post('source')[0]) > 3) {
				$this->Source_Model->Update();
			}
		} else {
			$valid_source_id = $this->Universal_Model->Validate_Id('SourceID', $this->input->get('source_id'), 'source');
			if($valid_source_id) {
				$titles = array('tab_title' => 'HolidayGoGoGo | Source', 'breadcrumb_title' => 'Source >> Update');
				$array = $this->Source_Model->Read_Source();
				$this->load->view('layout/header', $titles);
				$this->load->view('source/source', $array);
				$this->load->view('layout/footer');
			} else {
				redirect('Source');
			}
		}
	}
	
	function Delete()
	{
		$this->Universal_Model->Delete('SourceID', $this->input->get('source_id'), 'source');
	}

	function Toggle_Status()
	{
		$this->Source_Model->Toggle_Status($this->input->get('source_id'));
	}

	function Detect() {
		$redundant_name = $this->Source_Model->Detect();
		if($redundant_name) {
			echo json_encode(true);
		} else {
			echo json_encode(false);
		}
	}
}