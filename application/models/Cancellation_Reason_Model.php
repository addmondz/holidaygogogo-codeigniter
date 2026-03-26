<?php
class Cancellation_Reason_Model extends CI_Model
{
	function Read_Cancellation_Reason()
	{
		$this->db->select('CancellationReasonID, Name');
		$this->db->where('CancellationReasonID', $this->input->get('cancellation_reason_id'));
		return $this->db->get('cancellation_reason')->row_array();
	}

	function Read_Cancellation_Reasons()
	{
		$this->db->select('CancellationReasonID, Name, Status');
		$this->db->where('Status', 'Y');
		$this->db->order_by('Name', 'ASC');
		return $this->db->get('cancellation_reason')->result();
	}

	function Create()
	{
		$this->db->insert_batch('cancellation_reason', json_decode(json_encode($this->input->post('cancellation_reason'))));
	}

	function Update()
	{
		$this->db->update_batch('cancellation_reason', json_decode(json_encode($this->input->post('cancellation_reason'))), 'CancellationReasonID');
	}

	function Detect()
	{
		$this->db->where('Name', $this->input->post('name'));
		if($this->db->get('cancellation_reason')->row()) {
			return true;
		} else {
			return false;
		}
	}
}
