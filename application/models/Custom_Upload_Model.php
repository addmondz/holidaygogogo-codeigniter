<?php
class Custom_Upload_Model extends CI_Model
{
	function Create($booking_id, $upload_name, $upload_content, $created_by)
	{
		$array = array(
			'booking_id' => $booking_id,
			'upload_name' => $upload_name,
			'upload_content' => $upload_content,
			'created_by' => $created_by,
			'created_at' => date('Y-m-d H:i:s')
		);
		$this->db->insert('custom_upload', $array);
		return $this->db->insert_id();
	}

	function Read($booking_id)
	{
		$this->db->select('custom_upload.*, admin.Name AS CreatedByName');
		$this->db->from('custom_upload');
		$this->db->join('admin', 'admin.AdminID = custom_upload.created_by', 'left');
		$this->db->where('custom_upload.booking_id', $booking_id);
		$this->db->order_by('custom_upload.created_at', 'DESC');
		return $this->db->get()->result_array();
	}

	function Delete($id)
	{
		$this->db->where('id', $id);
		$this->db->delete('custom_upload');
		return $this->db->affected_rows() > 0;
	}

	function Get_By_Id($id)
	{
		$this->db->select('*');
		$this->db->where('id', $id);
		return $this->db->get('custom_upload')->row_array();
	}
}

