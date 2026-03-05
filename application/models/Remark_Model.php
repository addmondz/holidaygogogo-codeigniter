<?php
class Remark_Model extends CI_Model
{
	/**
	 * Read all remarks for a specific owner
	 * @param string $owner_type Type of owner (e.g., 'booking')
	 * @param int $owner_id ID of the owner record
	 * @return array Array of remark objects
	 */
	function Read_Remarks($owner_type, $owner_id, $type = null)
	{
		$this->db->select('remark.RemarkID, remark.owner_type, remark.owner_id, remark.commenter_id, remark.content, remark.type, remark.created_at, remark.updated_at, admin.Name AS CommenterName');
		$this->db->join('admin', 'admin.AdminID = remark.commenter_id', 'left');
		$this->db->where('remark.owner_type', $owner_type);
		$this->db->where('remark.owner_id', $owner_id);
		if ($type !== null) {
			$this->db->where('remark.type', $type);
		}
		$this->db->order_by('remark.created_at', 'ASC'); // Order by oldest first, latest at bottom
		return $this->db->get('remark')->result();
	}

	/**
	 * Create a new remark
	 * @param array $data Remark data
	 * @return int Insert ID
	 */
	function Create($data)
	{
		// Default to INTERNAL type if not specified
		$remark_type = isset($data['type']) ? $data['type'] : REMARK_TYPE::INTERNAL;
		
		// Check if notifications should be skipped
		$skip_notifications = isset($data['skip_notifications']) ? $data['skip_notifications'] : false;
		
		$remark_data = array(
			'owner_type' => $data['owner_type'],
			'owner_id' => $data['owner_id'],
			'commenter_id' => $data['commenter_id'],
			'content' => $data['content'],
			'type' => $remark_type
		);
		$this->db->insert('remark', $remark_data);
		$remark_id = $this->db->insert_id();
		
		// Create notifications for relevant users (only for internal remarks, not customer remarks)
		// Customer remarks have their own notification logic handled separately
		// Skip notifications if flag is set
		if ($remark_id && $data['owner_type'] == 'booking' && $remark_type == REMARK_TYPE::INTERNAL && !$skip_notifications) {
			$this->load->model('Notification_Model');
			$this->Notification_Model->Create_Remark_Notifications(
				$data['owner_id'],
				$remark_id,
				$data['commenter_id'],
				$data['content']
			);
		}
		
		return $remark_id;
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
	 * Get all remarks across all bookings (for global remarks panel)
	 * @param int $type Remark type (1=INTERNAL, 2=CUSTOMER)
	 * @param int $limit Number of records to return
	 * @param int $offset Offset for pagination
	 * @param int $user_id Current admin user ID
	 * @param int $user_level Current admin level (20=Sales Agent)
	 * @return array Array of remark objects
	 */
	function Get_All_Remarks($type, $limit = 10, $offset = 0, $user_id = null, $user_level = null)
	{
		$this->db->select('remark.RemarkID, remark.content, remark.created_at, admin.Name AS CommenterName, booking.BookingNumber, booking.BookingID');
		$this->db->join('admin', 'admin.AdminID = remark.commenter_id', 'left');
		$this->db->join('booking', 'booking.BookingID = remark.owner_id AND remark.owner_type = "booking"', 'inner');
		$this->db->where('remark.type', $type);

		// Sales agents (level 20) only see remarks from their bookings
		if ($user_level == 20 && $user_id) {
			$this->db->where('booking.SalesAgentID', $user_id);
		}

		$this->db->order_by('remark.created_at', 'DESC');
		$this->db->limit($limit, $offset);
		return $this->db->get('remark')->result();
	}

	/**
	 * Get total count of remarks for pagination
	 * @param int $type Remark type (1=INTERNAL, 2=CUSTOMER)
	 * @param int $user_id Current admin user ID
	 * @param int $user_level Current admin level
	 * @return int Total count
	 */
	function Get_All_Remarks_Count($type, $user_id = null, $user_level = null)
	{
		$this->db->join('booking', 'booking.BookingID = remark.owner_id AND remark.owner_type = "booking"', 'inner');
		$this->db->where('remark.type', $type);

		if ($user_level == 20 && $user_id) {
			$this->db->where('booking.SalesAgentID', $user_id);
		}

		return $this->db->count_all_results('remark');
	}

	/**
	 * Get a single remark by ID
	 * @param int $remark_id Remark ID
	 * @return object|null Remark object or null
	 */
	function Read_Remark($remark_id)
	{
		$this->db->select('remark.RemarkID, remark.owner_type, remark.owner_id, remark.commenter_id, remark.content, remark.type, remark.created_at, remark.updated_at, admin.Name AS CommenterName');
		$this->db->join('admin', 'admin.AdminID = remark.commenter_id', 'left');
		$this->db->where('remark.RemarkID', $remark_id);
		return $this->db->get('remark')->row();
	}
}

