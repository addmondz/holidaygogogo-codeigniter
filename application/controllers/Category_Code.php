<?php
class Category_Code extends MY_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->load->model('Category_Code_Model');
		$this->load->model('Universal_Model');
	}

	function index()
	{
		$titles = array('tab_title' => 'HolidayGoGoGo | Category Code', 'breadcrumb_title' => 'Category Code');
		$array['category_codes'] = $this->Category_Code_Model->Read_Category_Codes();
		$this->load->view('layout/header', $titles);
		$this->load->view('category_code/index', $array);
		$this->load->view('layout/footer');
	}

	function Create()
	{
		if($this->input->is_ajax_request()) {
			$this->Category_Code_Model->Create();
		} else {
			$titles = array('tab_title' => 'HolidayGoGoGo | Category Code', 'breadcrumb_title' => 'Category Code >> Create');
			$array = array('CategoryCodeID' => 'NA', 'Name' => 'NA');
			$this->load->view('layout/header', $titles);
			$this->load->view('category_code/category_code', $array);
			$this->load->view('layout/footer');
		}
	}

	function Update()
	{
		if($this->input->is_ajax_request()) {
			if(count($this->input->post('category_code')[0]) > 3) {
				$this->Category_Code_Model->Update();
			}
		} else {
			$valid_category_code_id = $this->Universal_Model->Validate_Id('CategoryCodeID', $this->input->get('category_code_id'), 'category_code');
			if($valid_category_code_id) {
				$titles = array('tab_title' => 'HolidayGoGoGo | Category Code', 'breadcrumb_title' => 'Category Code >> Update');
				$array = $this->Category_Code_Model->Read_Category_Code();
				$this->load->view('layout/header', $titles);
				$this->load->view('category_code/category_code', $array);
				$this->load->view('layout/footer');
			} else {
				redirect('Category_Code');
			}
		}
	}
	
	function Delete() 
	{
		$this->Universal_Model->Delete('CategoryCodeID', $this->input->get('category_code_id'), 'category_code');
	}

	function Detect() {
		$redundant_name = $this->Category_Code_Model->Detect();
		if($redundant_name) {
			echo json_encode(true);
		} else {
			echo json_encode(false);
		}
	}
}