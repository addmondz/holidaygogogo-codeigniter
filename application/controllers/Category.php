<?php
class Category extends MY_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->load->model('Category_Model');
		$this->load->model('Universal_Model');
	}

	function index()
	{
		$titles = array('tab_title' => 'HolidayGoGoGo | Category', 'breadcrumb_title' => 'Category');
		$array['categories'] = $this->Category_Model->Read_Categories();
		$array['category_codes'] = $this->Category_Model->Read_Category_Codes();
		$array['countries'] = $this->Category_Model->Read_Country_Codes();
		$this->load->view('layout/header', $titles);
		$this->load->view('category/index', $array);
		$this->load->view('layout/footer');
	}

	function Create()
	{
		if($this->input->is_ajax_request()) {
			$this->Category_Model->Create();
		} else {
			$titles = array('tab_title' => 'HolidayGoGoGo | Category', 'breadcrumb_title' => 'Category >> Create');
			$array = array('CategoryID' => 'NA', 'Name' => 'NA');
			$array['category_codes'] = $this->Category_Model->Read_Category_Codes();
			$array['countries'] = $this->Category_Model->Read_Country_Codes();
			$this->load->view('layout/header', $titles);
			$this->load->view('category/category', $array);
			$this->load->view('layout/footer');
		}
	}

	function Update()
	{
		if($this->input->is_ajax_request()) {
			if(count($this->input->post('category')[0]) > 3) {
				$this->Category_Model->Update();
			}
		} else {
			$valid_category_id = $this->Universal_Model->Validate_Id('CategoryID', $this->input->get('category_id'), 'category');
			if($valid_category_id) {
				$titles = array('tab_title' => 'HolidayGoGoGo | Category', 'breadcrumb_title' => 'Category >> Update');
				$array = $this->Category_Model->Read_Category();
				$array['category_codes'] = $this->Category_Model->Read_Category_Codes();
				$array['countries'] = $this->Category_Model->Read_Country_Codes();
				$this->load->view('layout/header', $titles);
				$this->load->view('category/category', $array);
				$this->load->view('layout/footer');
			} else {
				redirect('Category');
			}
		}
	}
	
	function Delete() 
	{
		$this->Universal_Model->Delete('CategoryID', $this->input->get('category_id'), 'category');
	}
	
	function Detect() {
		$redundant_name = $this->Category_Model->Detect();
		if($redundant_name) {
			echo json_encode(true);
		} else {
			echo json_encode(false);
		}
	}
}