<?php
class Package_Checklist_Model extends CI_Model
{
	function Read_Package_Checklist()
	{
		$this->db->select('ID, name, is_required, include_booking_filter');
		$this->db->where('ID', $this->input->get('package_checklist_id'));
		return $this->db->get('package_checklist')->row_array();
	}

	function Read_Package_Checklists()
	{
		$this->db->select('ID, name, is_required, include_booking_filter, created_at, updated_at');
		if(!empty($this->input->get('name'))) {
			$this->db->like('name', $this->input->get('name'));
		}
		$this->db->order_by('name', 'ASC');
		return $this->db->get('package_checklist')->result();
	}

	function Create()
	{
		$this->db->insert_batch('package_checklist', json_decode(json_encode($this->input->post('package_checklist'))));
	}
	
	function Update()
	{
		$this->db->update_batch('package_checklist', json_decode(json_encode($this->input->post('package_checklist'))), 'ID');
	}

	function Detect()
	{
		$this->db->where('name', $this->input->post('name'));
		if($this->input->get('package_checklist_id')) {
			$this->db->where('ID !=', $this->input->get('package_checklist_id'));
		}
		if($this->db->get('package_checklist')->row()) {
			return true;
		} else {
			return false;
		}
	}

	function Validate_Id($id)
	{
		$this->db->where('ID', $id);
		if($this->db->get('package_checklist')->row()) {
			return true;
		} else {
			return false;
		}
	}

	function Delete($id)
	{
		$this->db->where('ID', $id);
		$this->db->delete('package_checklist');
	}

	function Read_Booking_Filter_Checklists()
	{
		$this->db->select('ID, name');
		$this->db->where('include_booking_filter', 1);
		$this->db->order_by('name', 'ASC');
		return $this->db->get('package_checklist')->result();
	}

	function Read_Package_Checklist_By_Id($id)
	{
		$this->db->select('ID, name, is_required, include_booking_filter');
		$this->db->where('ID', $id);
		return $this->db->get('package_checklist')->row_array();
	}

	function Add_Required_Checklist_To_All_Products($checklist_id)
	{
		// Ensure checklist_id is an integer
		$checklist_id = (int)$checklist_id;
		
		// Get all products from product_package_checklist table
		$this->db->select('id, product_id, package_checklist_json');
		$products = $this->db->get('product_package_checklist')->result();
		
		foreach($products as $product) {
			// Decode JSON
			$checklist_ids = array();
			if(!empty($product->package_checklist_json)) {
				$json = $product->package_checklist_json;
				if(is_string($json)) {
					$checklist_ids = json_decode($json, true);
				} else {
					$checklist_ids = $json;
				}
				if(!is_array($checklist_ids)) {
					$checklist_ids = array();
				}
			}
			
			// Normalize all IDs to integers
			$checklist_ids = array_map('intval', $checklist_ids);
			$checklist_ids = array_values(array_filter($checklist_ids, function($id) { return $id > 0; }));
			
			// Check if checklist_id is already in the array
			if(!in_array($checklist_id, $checklist_ids)) {
				// Append to the array (as integer)
				$checklist_ids[] = $checklist_id;
				
				// Update the JSON
				$json_data = json_encode($checklist_ids);
				$this->db->where('id', $product->id);
				$this->db->update('product_package_checklist', array(
					'package_checklist_json' => $json_data
				));
			}
		}
	}
}

