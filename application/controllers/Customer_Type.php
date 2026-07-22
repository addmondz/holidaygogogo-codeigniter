<?php
class Customer_Type extends MY_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->load->model('Customer_Type_Model');
		$this->load->model('Universal_Model');
	}

	function index()
	{
		$titles = array('tab_title' => 'HolidayGoGoGo | Customer Type', 'breadcrumb_title' => 'Customer Type');
		$array['customer_types'] = $this->Customer_Type_Model->Read_Customer_Types();
		$this->load->view('layout/header', $titles);
		$this->load->view('customer_type/index', $array);
		$this->load->view('layout/footer');
	}

	function Create()
	{
		if($this->input->is_ajax_request()) {
			$this->Customer_Type_Model->Create();
		} else {
			$titles = array('tab_title' => 'HolidayGoGoGo | Customer Type', 'breadcrumb_title' => 'Customer Type >> Create');
			$array = array('CustomerTypeID' => 'NA', 'Name' => 'NA');
			$this->load->view('layout/header', $titles);
			$this->load->view('customer_type/customer_type', $array);
			$this->load->view('layout/footer');
		}
	}

	function Update()
	{
		if($this->input->is_ajax_request()) {
			if(count($this->input->post('customer_type')[0]) > 3) {
				$this->Customer_Type_Model->Update();
			}
		} else {
			$valid_customer_type_id = $this->Universal_Model->Validate_Id('CustomerTypeID', $this->input->get('customer_type_id'), 'customer_type');
			if($valid_customer_type_id) {
				$titles = array('tab_title' => 'HolidayGoGoGo | Customer Type', 'breadcrumb_title' => 'Customer Type >> Update');
				$array = $this->Customer_Type_Model->Read_Customer_Type();
				$this->load->view('layout/header', $titles);
				$this->load->view('customer_type/customer_type', $array);
				$this->load->view('layout/footer');
			} else {
				redirect('Customer_Type');
			}
		}
	}

	function Detect() {
		$redundant_name = $this->Customer_Type_Model->Detect();
		if($redundant_name) {
			echo json_encode(true);
		} else {
			echo json_encode(false);
		}
	}
}
