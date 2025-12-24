<?php
class Package_Checklist extends MY_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->load->model('Package_Checklist_Model');
		$this->load->model('Universal_Model');
	}

	function index()
	{
		$titles = array('tab_title' => 'HolidayGoGoGo | Package Checklist', 'breadcrumb_title' => 'Package Checklist');
		$array['package_checklists'] = $this->Package_Checklist_Model->Read_Package_Checklists();
		$this->load->view('layout/header', $titles);
		$this->load->view('package_checklist/index', $array);
		$this->load->view('layout/footer');
	}

	function Create()
	{
		if($this->input->is_ajax_request()) {
			$this->Package_Checklist_Model->Create();
		} else {
			$titles = array('tab_title' => 'HolidayGoGoGo | Package Checklist', 'breadcrumb_title' => 'Package Checklist >> Create');
			$array = array('ID' => 'NA', 'name' => 'NA', 'can_be_disabled' => 1);
			$this->load->view('layout/header', $titles);
			$this->load->view('package_checklist/package_checklist', $array);
			$this->load->view('layout/footer');
		}
	}

	function Update()
	{
		$package_checklist_id = $this->input->get('package_checklist_id') ?: (isset($this->input->post('package_checklist')[0]['ID']) ? $this->input->post('package_checklist')[0]['ID'] : null);
		
		// Prevent editing ID 1
		if($package_checklist_id == 1) {
			if($this->input->is_ajax_request()) {
				$this->output
					->set_content_type('application/json')
					->set_output(json_encode(['success' => false, 'message' => 'This package checklist cannot be edited.']));
				return;
			} else {
				redirect('Package_Checklist');
			}
		}
		
		if($this->input->is_ajax_request()) {
			if(count($this->input->post('package_checklist')[0]) > 3) {
				$this->Package_Checklist_Model->Update();
			}
		} else {
			$valid_package_checklist_id = $this->Package_Checklist_Model->Validate_Id($this->input->get('package_checklist_id'));
			if($valid_package_checklist_id) {
				$titles = array('tab_title' => 'HolidayGoGoGo | Package Checklist', 'breadcrumb_title' => 'Package Checklist >> Update');
				$array = $this->Package_Checklist_Model->Read_Package_Checklist();
				$this->load->view('layout/header', $titles);
				$this->load->view('package_checklist/package_checklist', $array);
				$this->load->view('layout/footer');
			} else {
				redirect('Package_Checklist');
			}
		}
	}
	
	function Delete() 
	{
		$package_checklist_id = $this->input->get('package_checklist_id');
		
		// Prevent deleting ID 1
		if($package_checklist_id == 1) {
			$this->output
				->set_content_type('application/json')
				->set_output(json_encode(['success' => false, 'message' => 'This package checklist cannot be deleted.']));
			return;
		}
		
		$this->Package_Checklist_Model->Delete($package_checklist_id);
	}

	function Detect() {
		$redundant_name = $this->Package_Checklist_Model->Detect();
		if($redundant_name) {
			echo json_encode(true);
		} else {
			echo json_encode(false);
		}
	}
}

