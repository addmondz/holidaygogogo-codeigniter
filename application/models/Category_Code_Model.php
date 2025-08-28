<?php
class Category_Code_Model extends CI_Model
{
	function Read_Category_Code()
	{
		$this->db->select('CategoryCodeID, Name');
		$this->db->where('CategoryCodeID', $this->input->get('category_code_id'));
		return $this->db->get('category_code')->row_array();
	}

	function Read_Category_Codes()
	{
		$this->db->select('CategoryCodeID, Name, Status');
		if(!empty($this->input->get('name'))) {
			$this->db->where('Name', $this->input->get('name'));
		}
		$this->db->where('Status', 'Y');
		$this->db->order_by('Name', 'ASC');
		return $this->db->get('category_code')->result();
	}
	
	function Create()
	{
		$this->db->insert_batch('category_code', json_decode(json_encode($this->input->post('category_code'))));
	}
	
	function Update()
	{
		$this->db->update_batch('category_code', json_decode(json_encode($this->input->post('category_code'))), 'CategoryCodeID');
	}

	function Detect()
	{
		$this->db->where('Name', $this->input->post('name'));
		if($this->db->get('category_code')->row()) {
			return true;
		} else {
			return false;
		}
	}
}