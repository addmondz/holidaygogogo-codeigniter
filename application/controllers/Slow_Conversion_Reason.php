<?php
class Slow_Conversion_Reason extends MY_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->load->model('Slow_Conversion_Reason_Model');
		$this->load->model('Universal_Model');
	}

	function index()
	{
		$titles = array('tab_title' => 'HolidayGoGoGo | Slow Conversion Reason', 'breadcrumb_title' => 'Slow Conversion Reason');
		$array['slow_conversion_reasons'] = $this->Slow_Conversion_Reason_Model->Read_Slow_Conversion_Reasons();
		$this->load->view('layout/header', $titles);
		$this->load->view('slow_conversion_reason/index', $array);
		$this->load->view('layout/footer');
	}

	function Create()
	{
		if($this->input->is_ajax_request()) {
			$this->Slow_Conversion_Reason_Model->Create();
		} else {
			$titles = array('tab_title' => 'HolidayGoGoGo | Slow Conversion Reason', 'breadcrumb_title' => 'Slow Conversion Reason >> Create');
			$array = array('SlowConversionReasonID' => 'NA', 'Name' => 'NA');
			$this->load->view('layout/header', $titles);
			$this->load->view('slow_conversion_reason/slow_conversion_reason', $array);
			$this->load->view('layout/footer');
		}
	}

	function Update()
	{
		if($this->input->is_ajax_request()) {
			if(count($this->input->post('slow_conversion_reason')[0]) > 2) {
				$this->Slow_Conversion_Reason_Model->Update();
			}
		} else {
			$valid_id = $this->Universal_Model->Validate_Id('SlowConversionReasonID', $this->input->get('slow_conversion_reason_id'), 'slow_conversion_reason');
			if($valid_id) {
				$titles = array('tab_title' => 'HolidayGoGoGo | Slow Conversion Reason', 'breadcrumb_title' => 'Slow Conversion Reason >> Update');
				$array = $this->Slow_Conversion_Reason_Model->Read_Slow_Conversion_Reason();
				$this->load->view('layout/header', $titles);
				$this->load->view('slow_conversion_reason/slow_conversion_reason', $array);
				$this->load->view('layout/footer');
			} else {
				redirect('Slow_Conversion_Reason');
			}
		}
	}

	function Delete()
	{
		$this->Universal_Model->Delete('SlowConversionReasonID', $this->input->get('slow_conversion_reason_id'), 'slow_conversion_reason');
	}

	function Detect() {
		$redundant_name = $this->Slow_Conversion_Reason_Model->Detect();
		if($redundant_name) {
			echo json_encode(true);
		} else {
			echo json_encode(false);
		}
	}
}
