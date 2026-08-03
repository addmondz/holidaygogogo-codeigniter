<?php
/**
 * Lead Status presets — a Settings CRUD (Owner L10 + Team Lead L25). Manual leads
 * pick a status by NAME from this list. Mirrors the Customer_Type controller: GET
 * renders the list/form views, AJAX POST performs the write; feedback is the
 * client-side Display_Message SweetAlert (no flashdata). Access is enforced
 * server-side in MY_Controller (levels 20/45/60 redirected to Dashboard).
 */
class Lead_Status extends MY_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->load->model('Lead_Status_Model');
		$this->load->model('Universal_Model');
	}

	function index()
	{
		$titles = array('tab_title' => 'HolidayGoGoGo | Lead Status', 'breadcrumb_title' => 'Lead Status');
		$array['lead_statuses'] = $this->Lead_Status_Model->Read_Lead_Statuses();
		$this->load->view('layout/header', $titles);
		$this->load->view('lead_status/index', $array);
		$this->load->view('layout/footer');
	}

	function Create()
	{
		if($this->input->is_ajax_request()) {
			$this->Lead_Status_Model->Create();
		} else {
			$titles = array('tab_title' => 'HolidayGoGoGo | Lead Status', 'breadcrumb_title' => 'Lead Status >> Create');
			$array = array('LeadStatusID' => 'NA', 'Name' => 'NA');
			$this->load->view('layout/header', $titles);
			$this->load->view('lead_status/lead_status', $array);
			$this->load->view('layout/footer');
		}
	}

	function Update()
	{
		if($this->input->is_ajax_request()) {
			if(count($this->input->post('lead_status')[0]) > 3) {
				$this->Lead_Status_Model->Update();
			}
		} else {
			$valid_lead_status_id = $this->Universal_Model->Validate_Id('LeadStatusID', $this->input->get('lead_status_id'), 'lead_status');
			if($valid_lead_status_id) {
				$titles = array('tab_title' => 'HolidayGoGoGo | Lead Status', 'breadcrumb_title' => 'Lead Status >> Update');
				$array = $this->Lead_Status_Model->Read_Lead_Status();
				$this->load->view('layout/header', $titles);
				$this->load->view('lead_status/lead_status', $array);
				$this->load->view('layout/footer');
			} else {
				redirect('Lead_Status');
			}
		}
	}

	function Detect() {
		$redundant_name = $this->Lead_Status_Model->Detect();
		if($redundant_name) {
			echo json_encode(true);
		} else {
			echo json_encode(false);
		}
	}
}
