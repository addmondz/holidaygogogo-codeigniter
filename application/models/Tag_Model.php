<?php
class Tag_Model extends CI_Model
{
	function Read_Tag()
	{
		$this->db->select('TagID, Name');
		$this->db->where('TagID', $this->input->get('tag_id'));
		return $this->db->get('tag')->row_array();
	}

	function Read_Tags()
	{
		$this->db->select('TagID, Name, Status');
		if(!empty($this->input->get('name'))) {
			$this->db->where('Name', $this->input->get('name'));
		}
		$this->db->where('Status', 'Y');
		$this->db->order_by('Name', 'ASC');
		return $this->db->get('tag')->result();
	}

	function Create()
	{
		$this->db->insert_batch('tag', json_decode(json_encode($this->input->post('tag'))));
	}
	
	function Update()
	{
		$this->db->update_batch('tag', json_decode(json_encode($this->input->post('tag'))), 'TagID');
	}

	function Detect()
	{
		$this->db->where('Name', $this->input->post('name'));
		if($this->db->get('tag')->row()) {
			return true;
		} else {
			return false;
		}
	}
}