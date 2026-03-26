<?php
class Cancellation_Reason extends MY_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->load->model('Cancellation_Reason_Model');
		$this->load->model('Universal_Model');
	}

	function index()
	{
		$titles = array('tab_title' => 'HolidayGoGoGo | Cancellation Reason', 'breadcrumb_title' => 'Cancellation Reason');
		$array['cancellation_reasons'] = $this->Cancellation_Reason_Model->Read_Cancellation_Reasons();
		$this->load->view('layout/header', $titles);
		$this->load->view('cancellation_reason/index', $array);
		$this->load->view('layout/footer');
	}

	function Create()
	{
		if($this->input->is_ajax_request()) {
			$this->Cancellation_Reason_Model->Create();
		} else {
			$titles = array('tab_title' => 'HolidayGoGoGo | Cancellation Reason', 'breadcrumb_title' => 'Cancellation Reason >> Create');
			$array = array('CancellationReasonID' => 'NA', 'Name' => 'NA');
			$this->load->view('layout/header', $titles);
			$this->load->view('cancellation_reason/cancellation_reason', $array);
			$this->load->view('layout/footer');
		}
	}

	function Update()
	{
		if($this->input->is_ajax_request()) {
			if(count($this->input->post('cancellation_reason')[0]) > 2) {
				$this->Cancellation_Reason_Model->Update();
			}
		} else {
			$valid_id = $this->Universal_Model->Validate_Id('CancellationReasonID', $this->input->get('cancellation_reason_id'), 'cancellation_reason');
			if($valid_id) {
				$titles = array('tab_title' => 'HolidayGoGoGo | Cancellation Reason', 'breadcrumb_title' => 'Cancellation Reason >> Update');
				$array = $this->Cancellation_Reason_Model->Read_Cancellation_Reason();
				$this->load->view('layout/header', $titles);
				$this->load->view('cancellation_reason/cancellation_reason', $array);
				$this->load->view('layout/footer');
			} else {
				redirect('Cancellation_Reason');
			}
		}
	}

	function Delete()
	{
		$this->Universal_Model->Delete('CancellationReasonID', $this->input->get('cancellation_reason_id'), 'cancellation_reason');
	}

	function Detect() {
		$redundant_name = $this->Cancellation_Reason_Model->Detect();
		if($redundant_name) {
			echo json_encode(true);
		} else {
			echo json_encode(false);
		}
	}
}
