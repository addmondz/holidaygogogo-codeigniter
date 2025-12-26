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
			$package_checklist_data = $this->input->post('package_checklist');
			$is_required = isset($package_checklist_data[0]['is_required']) ? (int)$package_checklist_data[0]['is_required'] : 0;
			$name = isset($package_checklist_data[0]['name']) ? $package_checklist_data[0]['name'] : '';
			
			// Create the checklist
			$this->Package_Checklist_Model->Create();
			
			// If is_required is 1, get the newly created ID and add to all products
			if($is_required == 1 && !empty($name)) {
				// Get the ID by name (since insert_batch doesn't return insert_id easily)
				$this->db->select('ID');
				$this->db->where('name', $name);
				$this->db->order_by('ID', 'DESC');
				$this->db->limit(1);
				$result = $this->db->get('package_checklist')->row();
				if($result && isset($result->ID)) {
					$this->Package_Checklist_Model->Add_Required_Checklist_To_All_Products($result->ID);
				}
			}
		} else {
			$titles = array('tab_title' => 'HolidayGoGoGo | Package Checklist', 'breadcrumb_title' => 'Package Checklist >> Create');
			$array = array('ID' => 'NA', 'name' => 'NA', 'is_required' => 0);
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
				// Get old value before update
				$old_data = $this->Package_Checklist_Model->Read_Package_Checklist_By_Id($package_checklist_id);
				$old_is_required = isset($old_data['is_required']) ? (int)$old_data['is_required'] : 0;
				
				// Get new value
				$package_checklist_data = $this->input->post('package_checklist');
				$new_is_required = isset($package_checklist_data[0]['is_required']) ? (int)$package_checklist_data[0]['is_required'] : 0;
				
				// Update the checklist
				$this->Package_Checklist_Model->Update();
				
				// If changed from 0 (No) to 1 (Yes), add to all products
				if($old_is_required == 0 && $new_is_required == 1) {
					$this->Package_Checklist_Model->Add_Required_Checklist_To_All_Products($package_checklist_id);
				}
				// If changed from 1 (Yes) to 0 (No), do nothing (as per requirement)
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

