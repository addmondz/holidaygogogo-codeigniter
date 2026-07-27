<?php
class Team_Model extends CI_Model
{
	function Read_Team()
	{
		$this->db->select('TeamID, Name');
		$this->db->where('TeamID', $this->input->get('team_id'));
		return $this->db->get('team')->row_array();
	}

	function Read_Teams()
	{
		$this->db->select('TeamID, Name, Status');
		$this->db->where('Status', 'Y');
		$this->db->order_by('Name', 'ASC');
		return $this->db->get('team')->result();
	}

	function Create()
	{
		$this->db->insert_batch('team', json_decode(json_encode($this->input->post('team'))));
	}

	function Update()
	{
		$this->db->update_batch('team', json_decode(json_encode($this->input->post('team'))), 'TeamID');
	}

	function Detect()
	{
		$this->db->where('Name', $this->input->post('name'));
		if($this->db->get('team')->row()) {
			return true;
		} else {
			return false;
		}
	}
}
