<?php
class Source_Model extends CI_Model
{
	function Read_Source()
	{
		$this->db->select('SourceID, Name');
		$this->db->where('SourceID', $this->input->get('source_id'));
		return $this->db->get('source')->row_array();
	}

	function Read_Sources()
	{
		$this->db->select('SourceID, Name, Status');
		if(!empty($this->input->get('name'))) {
			$this->db->where('Name', $this->input->get('name'));
		}
		$this->db->order_by('Name', 'ASC');
		return $this->db->get('source')->result();
	}

	function Toggle_Status($source_id)
	{
		$this->db->select('Status');
		$this->db->where('SourceID', $source_id);
		$source = $this->db->get('source')->row();

		$new_status = ($source->Status == 'Y') ? 'N' : 'Y';
		$this->db->where('SourceID', $source_id);
		$this->db->update('source', array(
			'Status' => $new_status,
			'UpdateBy' => $this->session->userdata('admin_id'),
			'UpdateDate' => date('Y-m-d H:i:s')
		));
	}

	function Create()
	{
		$this->db->insert_batch('source', json_decode(json_encode($this->input->post('source'))));
	}
	
	function Update()
	{
		$this->db->update_batch('source', json_decode(json_encode($this->input->post('source'))), 'SourceID');
	}

	function Detect()
	{
		$this->db->where('Name', $this->input->post('name'));
		if($this->db->get('source')->row()) {
			return true;
		} else {
			return false;
		}
	}
}