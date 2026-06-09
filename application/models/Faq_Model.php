<?php
class Faq_Model extends CI_Model
{
	// Admin list. Honours the Title (like) and Type (exact) filters posted
	// from the index page filter accordion.
	function Read_Faqs()
	{
		$this->db->select('f.FAQID, f.Title, f.Description, f.Type, f.DisplayOrder, f.Status, f.InsertDate, a.Name AS InsertByName', false);
		$this->db->from('faq f');
		$this->db->join('admin a', 'a.AdminID = f.InsertBy', 'left');
		$this->db->where('f.Status', 'Y');

		if(!empty($this->input->get('title'))) {
			$this->db->like('f.Title', $this->input->get('title'));
		}
		if(!empty($this->input->get('type')) && in_array($this->input->get('type'), array('internal', 'external'), true)) {
			$this->db->where('f.Type', $this->input->get('type'));
		}

		$this->db->order_by('f.DisplayOrder', 'ASC');
		$this->db->order_by('f.FAQID', 'ASC');
		return $this->db->get()->result();
	}

	function Read_Faq($id)
	{
		$this->db->select('FAQID, Title, Description, Type, DisplayOrder, Status');
		$this->db->where('FAQID', (int)$id);
		return $this->db->get('faq')->row();
	}

	// Public read used by both /faq/internal and /faq/external. Only active
	// FAQs of the requested type, in display order.
	function Read_Public($type)
	{
		$type = ($type === 'external') ? 'external' : 'internal';
		$this->db->select('FAQID, Title, Description');
		$this->db->where('Status', 'Y');
		$this->db->where('Type', $type);
		$this->db->order_by('DisplayOrder', 'ASC');
		$this->db->order_by('FAQID', 'ASC');
		return $this->db->get('faq')->result();
	}

	function Create($data)
	{
		$admin_id = $this->session->userdata('admin_id');
		$now      = date('Y-m-d H:i:s');

		$row = array(
			'Title'        => $data['Title'],
			'Description'  => $data['Description'],
			'Type'         => $data['Type'],
			'DisplayOrder' => (int)$data['DisplayOrder'],
			'Status'       => 'Y',
			'InsertBy'     => $admin_id,
			'InsertDate'   => $now,
			'UpdateBy'     => $admin_id,
			'UpdateDate'   => $now,
		);
		$this->db->insert('faq', $row);
		return (int)$this->db->insert_id();
	}

	function Update($id, $data)
	{
		$row = array(
			'Title'        => $data['Title'],
			'Description'  => $data['Description'],
			'Type'         => $data['Type'],
			'DisplayOrder' => (int)$data['DisplayOrder'],
			'UpdateBy'     => $this->session->userdata('admin_id'),
			'UpdateDate'   => date('Y-m-d H:i:s'),
		);
		$this->db->where('FAQID', (int)$id);
		return $this->db->update('faq', $row);
	}
}
