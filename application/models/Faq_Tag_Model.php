<?php
class Faq_Tag_Model extends CI_Model
{
	// Admin list. Honours the Name (like) filter posted from the index page.
	function Read_Faq_Tags()
	{
		$this->db->select('t.FAQTagID, t.Name, t.IsDefault, t.Status, t.InsertDate, a.Name AS InsertByName', false);
		$this->db->from('faq_tag t');
		$this->db->join('admin a', 'a.AdminID = t.InsertBy', 'left');
		$this->db->where('t.Status', 'Y');

		if(!empty($this->input->get('name'))) {
			$this->db->like('t.Name', $this->input->get('name'));
		}

		$this->db->order_by('t.Name', 'ASC');
		return $this->db->get()->result();
	}

	function Read_Faq_Tag($id)
	{
		$this->db->select('FAQTagID, Name, IsDefault, Status');
		$this->db->where('FAQTagID', (int)$id);
		return $this->db->get('faq_tag')->row();
	}

	// All active tags, used to populate the multi-select on the FAQ form.
	function Read_Active()
	{
		$this->db->select('FAQTagID, Name');
		$this->db->where('Status', 'Y');
		$this->db->order_by('Name', 'ASC');
		return $this->db->get('faq_tag')->result();
	}

	// Duplicate-name guard (case-insensitive) among active tags. $ignore_id lets
	// the Update screen keep its own name without tripping the check.
	function Name_Exists($name, $ignore_id = 0)
	{
		$this->db->where('Status', 'Y');
		$this->db->where('LOWER(Name)', strtolower(trim((string)$name)));
		if((int)$ignore_id > 0) {
			$this->db->where('FAQTagID !=', (int)$ignore_id);
		}
		return (bool)$this->db->get('faq_tag')->row();
	}

	function Create($data)
	{
		$admin_id = $this->session->userdata('admin_id');
		$now      = date('Y-m-d H:i:s');

		$row = array(
			'Name'       => $data['Name'],
			'IsDefault'  => (isset($data['IsDefault']) && $data['IsDefault'] === 'Y') ? 'Y' : 'N',
			'Status'     => 'Y',
			'InsertBy'   => $admin_id,
			'InsertDate' => $now,
			'UpdateBy'   => $admin_id,
			'UpdateDate' => $now,
		);
		$this->db->insert('faq_tag', $row);
		return (int)$this->db->insert_id();
	}

	function Update($id, $data)
	{
		$row = array(
			'Name'       => $data['Name'],
			'IsDefault'  => (isset($data['IsDefault']) && $data['IsDefault'] === 'Y') ? 'Y' : 'N',
			'UpdateBy'   => $this->session->userdata('admin_id'),
			'UpdateDate' => date('Y-m-d H:i:s'),
		);
		$this->db->where('FAQTagID', (int)$id);
		return $this->db->update('faq_tag', $row);
	}
}
