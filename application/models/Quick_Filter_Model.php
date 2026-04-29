<?php
class Quick_Filter_Model extends CI_Model
{
	function Read_Quick_Filter()
	{
		$this->db->select('QuickFilterID, Name, FilterData');
		$this->db->where('QuickFilterID', $this->input->get('quick_filter_id'));
		return $this->db->get('quick_filter')->row_array();
	}

	function Read_Quick_Filters()
	{
		$this->db->select('QuickFilterID, Name, FilterData, Status');
		if(!empty($this->input->get('name'))) {
			$this->db->like('Name', $this->input->get('name'));
		}
		$this->db->where('Status', 'Y');
		$this->db->order_by('Name', 'ASC');
		return $this->db->get('quick_filter')->result();
	}

	function Read_Quick_Filter_By_Id($id)
	{
		$this->db->select('QuickFilterID, Name, FilterData, Status');
		$this->db->where('QuickFilterID', $id);
		return $this->db->get('quick_filter')->row();
	}

	function Create()
	{
		$this->db->insert_batch('quick_filter', json_decode(json_encode($this->input->post('quick_filter'))));
	}

	function Update()
	{
		$posted = json_decode(json_encode($this->input->post('quick_filter')), true);
		if(!is_array($posted)) {
			return;
		}
		$this->db->update_batch('quick_filter', $posted, 'QuickFilterID');
	}

	function Detect()
	{
		$name = $this->input->post('name');
		$this->db->where('Name', $name);
		if($this->input->post('quick_filter_id')) {
			$this->db->where('QuickFilterID !=', $this->input->post('quick_filter_id'));
		}
		$this->db->where('Status', 'Y');
		if($this->db->get('quick_filter')->row()) {
			return true;
		} else {
			return false;
		}
	}
}
