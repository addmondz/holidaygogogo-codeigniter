<?php
class Country_Code extends MY_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->load->model('Country_Code_Model');
		$this->load->model('Universal_Model');
	}

	function index()
	{
		$titles = array('tab_title' => 'HolidayGoGoGo | Country Code', 'breadcrumb_title' => 'Country Code');
		$array['country_codes'] = $this->Country_Code_Model->Read_Country_Codes();
		$this->load->view('layout/header', $titles);
		$this->load->view('country_code/index', $array);
		$this->load->view('layout/footer');
	}

	function Create()
	{
		if($this->input->is_ajax_request()) {
			$this->Country_Code_Model->Create();
		} else {
			$titles = array('tab_title' => 'HolidayGoGoGo | Country Code', 'breadcrumb_title' => 'Country Code >> Create');
			$array = array('CountryCodeID' => 'NA', 'Country' => 'NA');
			$this->load->view('layout/header', $titles);
			$this->load->view('country_code/country_code', $array);
			$this->load->view('layout/footer');
		}
	}

	function Update()
	{
		if($this->input->is_ajax_request()) {
			if(count($this->input->post('country_code')[0]) > 3) {
				$this->Country_Code_Model->Update();
			}
		} else {
			$valid_country_code_id = $this->Universal_Model->Validate_Id('CountryCodeID', $this->input->get('country_code_id'), 'country_code');
			if($valid_country_code_id) {
				$titles = array('tab_title' => 'HolidayGoGoGo | Country Code', 'breadcrumb_title' => 'Country Code >> Update');
				$array = $this->Country_Code_Model->Read_Country_Code();
				$this->load->view('layout/header', $titles);
				$this->load->view('country_code/country_code', $array);
				$this->load->view('layout/footer');
			} else {
				redirect('Country_Code');
			}
		}
	}
	
	function Delete() 
	{
		$this->Universal_Model->Delete('CountryCodeID', $this->input->get('country_code_id'), 'country_code');
	}

	function Detect() {
		$redundant_country = $this->Country_Code_Model->Detect();
		if($redundant_country) {
			echo json_encode(true);
		} else {
			echo json_encode(false);
		}
	}
}