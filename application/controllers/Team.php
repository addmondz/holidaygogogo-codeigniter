<?php
class Team extends MY_Controller
{
	function __construct()
	{
		parent::__construct();
		// Team function DISABLED — the whole feature (management page + listing
		// scope) has been turned off. Block all access to the controller.
		redirect('Dashboard');
		$this->load->model('Team_Model');
		$this->load->model('Universal_Model');
	}

	function index()
	{
		$titles = array('tab_title' => 'HolidayGoGoGo | Team', 'breadcrumb_title' => 'Team');
		$array['teams'] = $this->Team_Model->Read_Teams();
		$this->load->view('layout/header', $titles);
		$this->load->view('team/index', $array);
		$this->load->view('layout/footer');
	}

	function Create()
	{
		if($this->input->is_ajax_request()) {
			$this->Team_Model->Create();
		} else {
			$titles = array('tab_title' => 'HolidayGoGoGo | Team', 'breadcrumb_title' => 'Team >> Create');
			$array = array('TeamID' => 'NA', 'Name' => 'NA');
			$this->load->view('layout/header', $titles);
			$this->load->view('team/team', $array);
			$this->load->view('layout/footer');
		}
	}

	function Update()
	{
		if($this->input->is_ajax_request()) {
			if(count($this->input->post('team')[0]) > 2) {
				$this->Team_Model->Update();
			}
		} else {
			$valid_id = $this->Universal_Model->Validate_Id('TeamID', $this->input->get('team_id'), 'team');
			if($valid_id) {
				$titles = array('tab_title' => 'HolidayGoGoGo | Team', 'breadcrumb_title' => 'Team >> Update');
				$array = $this->Team_Model->Read_Team();
				$this->load->view('layout/header', $titles);
				$this->load->view('team/team', $array);
				$this->load->view('layout/footer');
			} else {
				redirect('Team');
			}
		}
	}

	function Delete()
	{
		$this->Universal_Model->Delete('TeamID', $this->input->get('team_id'), 'team');
	}

	function Detect() {
		$redundant_name = $this->Team_Model->Detect();
		if($redundant_name) {
			echo json_encode(true);
		} else {
			echo json_encode(false);
		}
	}
}
