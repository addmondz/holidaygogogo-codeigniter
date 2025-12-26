<?php
class Remark_Model extends CI_Model
{
	/**
	 * Read all remarks for a specific owner
	 * @param string $owner_type Type of owner (e.g., 'booking')
	 * @param int $owner_id ID of the owner record
	 * @return array Array of remark objects
	 */
	function Read_Remarks($owner_type, $owner_id)
	{
		$this->db->select('remark.RemarkID, remark.owner_type, remark.owner_id, remark.commenter_id, remark.content, remark.created_at, remark.updated_at, admin.Name AS CommenterName');
		$this->db->join('admin', 'admin.AdminID = remark.commenter_id', 'left');
		$this->db->where('remark.owner_type', $owner_type);
		$this->db->where('remark.owner_id', $owner_id);
		$this->db->order_by('remark.created_at', 'DESC');
		return $this->db->get('remark')->result();
	}

	/**
	 * Create a new remark
	 * @param array $data Remark data
	 * @return int Insert ID
	 */
	function Create($data)
	{
		$remark_data = array(
			'owner_type' => $data['owner_type'],
			'owner_id' => $data['owner_id'],
			'commenter_id' => $data['commenter_id'],
			'content' => $data['content']
		);
		$this->db->insert('remark', $remark_data);
		return $this->db->insert_id();
	}

	/**
	 * Update an existing remark
	 * @param int $remark_id Remark ID
	 * @param array $data Updated data
	 * @return bool Success status
	 */
	function Update($remark_id, $data)
	{
		$this->db->where('RemarkID', $remark_id);
		return $this->db->update('remark', $data);
	}

	/**
	 * Delete a remark
	 * @param int $remark_id Remark ID
	 * @return bool Success status
	 */
	function Delete($remark_id)
	{
		$this->db->where('RemarkID', $remark_id);
		return $this->db->delete('remark');
	}

	/**
	 * Get a single remark by ID
	 * @param int $remark_id Remark ID
	 * @return object|null Remark object or null
	 */
	function Read_Remark($remark_id)
	{
		$this->db->select('remark.RemarkID, remark.owner_type, remark.owner_id, remark.commenter_id, remark.content, remark.created_at, remark.updated_at, admin.Name AS CommenterName');
		$this->db->join('admin', 'admin.AdminID = remark.commenter_id', 'left');
		$this->db->where('remark.RemarkID', $remark_id);
		return $this->db->get('remark')->row();
	}
}

