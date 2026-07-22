<?php
class Product_Package_Checklist extends MY_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->load->model('Product_Package_Checklist_Model');
	}

	function index()
	{
		$titles = array('tab_title' => 'HolidayGoGoGo | Product Package Checklist', 'breadcrumb_title' => 'Product Package Checklist');
		$array['relationships'] = $this->Product_Package_Checklist_Model->Read_Product_Package_Checklists();
		$array['products'] = $this->Product_Package_Checklist_Model->Read_Products();
		$array['package_checklists'] = $this->Product_Package_Checklist_Model->Read_Package_Checklists();
		$this->load->view('layout/header', $titles);
		$this->load->view('product_package_checklist/index', $array);
		$this->load->view('layout/footer');
	}

	function Create()
	{
		if($this->input->is_ajax_request()) {
			$this->Product_Package_Checklist_Model->Create();
		} else {
			$titles = array('tab_title' => 'HolidayGoGoGo | Product Package Checklist', 'breadcrumb_title' => 'Product Package Checklist >> Create');
			$array['products'] = $this->Product_Package_Checklist_Model->Read_Products();
			$array['package_checklists'] = $this->Product_Package_Checklist_Model->Read_Package_Checklists();
			$this->load->view('layout/header', $titles);
			$this->load->view('product_package_checklist/product_package_checklist', $array);
			$this->load->view('layout/footer');
		}
	}

	function Update()
	{
		if($this->input->is_ajax_request()) {
			if(count($this->input->post('product_package_checklist')[0]) > 2) {
				$this->Product_Package_Checklist_Model->Update();
			}
		} else {
			$valid_id = $this->Product_Package_Checklist_Model->Validate_Id($this->input->get('id'));
			if($valid_id) {
				$titles = array('tab_title' => 'HolidayGoGoGo | Product Package Checklist', 'breadcrumb_title' => 'Product Package Checklist >> Update');
				$array = $this->Product_Package_Checklist_Model->Read_Product_Package_Checklist();
				$array['products'] = $this->Product_Package_Checklist_Model->Read_Products();
				$array['package_checklists'] = $this->Product_Package_Checklist_Model->Read_Package_Checklists();
				$this->load->view('layout/header', $titles);
				$this->load->view('product_package_checklist/product_package_checklist', $array);
				$this->load->view('layout/footer');
			} else {
				redirect('Product_Package_Checklist');
			}
		}
	}
	
	function Delete() 
	{
		$this->Product_Package_Checklist_Model->Delete($this->input->get('id'));
	}

	function Detect() {
		$duplicate = $this->Product_Package_Checklist_Model->Detect_Duplicate();
		if($duplicate) {
			echo json_encode(true);
		} else {
			echo json_encode(false);
		}
	}
}

