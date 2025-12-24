<?php
class Package_Checklist_Model extends CI_Model
{
	function Read_Package_Checklist()
	{
		$this->db->select('ID, name, can_be_disabled');
		$this->db->where('ID', $this->input->get('package_checklist_id'));
		return $this->db->get('package_checklist')->row_array();
	}

	function Read_Package_Checklists()
	{
		$this->db->select('ID, name, can_be_disabled, created_at, updated_at');
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
}

