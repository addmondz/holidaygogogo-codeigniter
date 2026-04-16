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
			'type' => $remark_type,
			'created_at' => date('Y-m-d H:i:s')
		);
		$this->db->insert('remark', $remark_data);
		$remark_id = $this->db->insert_id();

		// Create notifications for relevant users (only for internal remarks, not customer remarks)
		// Customer remarks have their own notification logic handled separately
		// Skip notifications if flag is set
		if ($remark_id && $data['owner_type'] == 'booking' && $remark_type == REMARK_TYPE::INTERNAL && !$skip_notifications) {
			$this->load->model('Notification_Model');

			// Always send automatic notifications to Owners, SalesAgent, BookingOP
			$this->Notification_Model->Create_Remark_Notifications(
				$data['owner_id'],
				$remark_id,
				$data['commenter_id'],
				$data['content']
			);

			// Also notify any additional users selected via checkbox
			$notify_user_ids = isset($data['notify_user_ids']) ? $data['notify_user_ids'] : null;
			if (!empty($notify_user_ids)) {
				$this->Notification_Model->Create_Remark_Notifications_For_Users(
					$data['owner_id'],
					$remark_id,
					$data['commenter_id'],
					$data['content'],
					$notify_user_ids
				);
			}
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
		$this->db->select('remark.RemarkID, remark.content, remark.created_at, admin.Name AS CommenterName, booking.BookingNumber, booking.BookingID, booking.Customer, IF(rur.id IS NOT NULL, 1, 0) AS is_read');
		$this->db->join('admin', 'admin.AdminID = remark.commenter_id', 'left');
		$this->db->join('booking', 'booking.BookingID = remark.owner_id AND remark.owner_type = "booking"', 'inner');
		if ($user_id) {
			$this->db->join('remark_user_read rur', 'rur.remark_id = remark.RemarkID AND rur.user_id = ' . intval($user_id), 'left');
		} else {
			$this->db->join('remark_user_read rur', 'rur.remark_id = remark.RemarkID AND 1=0', 'left');
		}
		$this->db->where('remark.type', $type);
		if ($user_id) {
			$this->db->where('remark.commenter_id !=', intval($user_id));
		}

		// Sales agents (20) / BookingOP (40): their own bookings OR remarks they were tagged on
		if ($user_level == 20 && $user_id) {
			$uid = intval($user_id);
			$this->db->where("(booking.SalesAgent = $uid OR EXISTS (SELECT 1 FROM notification n WHERE n.remark_id = remark.RemarkID AND n.user_id = $uid))", null, false);
		}
		elseif ($user_level == 40 && $user_id) {
			$uid = intval($user_id);
			$this->db->where("(booking.BookingOP = $uid OR EXISTS (SELECT 1 FROM notification n WHERE n.remark_id = remark.RemarkID AND n.user_id = $uid))", null, false);
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
		if ($user_id) {
			$this->db->where('remark.commenter_id !=', intval($user_id));
		}

		if ($user_level == 20 && $user_id) {
			$uid = intval($user_id);
			$this->db->where("(booking.SalesAgent = $uid OR EXISTS (SELECT 1 FROM notification n WHERE n.remark_id = remark.RemarkID AND n.user_id = $uid))", null, false);
		}
		elseif ($user_level == 40 && $user_id) {
			$uid = intval($user_id);
			$this->db->where("(booking.BookingOP = $uid OR EXISTS (SELECT 1 FROM notification n WHERE n.remark_id = remark.RemarkID AND n.user_id = $uid))", null, false);
		}

		return $this->db->count_all_results('remark');
	}

	/**
	 * Get unread count for a specific remark type
	 * @param int $type Remark type (1=INTERNAL, 2=CUSTOMER)
	 * @param int $user_id Current admin user ID
	 * @param int $user_level Current admin level
	 * @return int Unread count
	 */
	function Get_Unread_Count($type, $user_id, $user_level = null)
	{
		$this->db->join('booking', 'booking.BookingID = remark.owner_id AND remark.owner_type = "booking"', 'inner');
		$this->db->join('remark_user_read rur', 'rur.remark_id = remark.RemarkID AND rur.user_id = ' . intval($user_id), 'left');
		$this->db->where('remark.type', $type);
		$this->db->where('rur.id IS NULL', null, false);
		$this->db->where('remark.commenter_id !=', intval($user_id));

		if ($user_level == 20 && $user_id) {
			$uid = intval($user_id);
			$this->db->where("(booking.SalesAgent = $uid OR EXISTS (SELECT 1 FROM notification n WHERE n.remark_id = remark.RemarkID AND n.user_id = $uid))", null, false);
		}
		elseif ($user_level == 40 && $user_id) {
			$uid = intval($user_id);
			$this->db->where("(booking.BookingOP = $uid OR EXISTS (SELECT 1 FROM notification n WHERE n.remark_id = remark.RemarkID AND n.user_id = $uid))", null, false);
		}

		return $this->db->count_all_results('remark');
	}

	/**
	 * Get total unread count across all remark types (for badge)
	 * @param int $user_id Current admin user ID
	 * @param int $user_level Current admin level
	 * @return int Total unread count
	 */
	function Get_Total_Unread_Count($user_id, $user_level = null)
	{
		return $this->Get_Unread_Count(1, $user_id, $user_level)
			 + $this->Get_Unread_Count(2, $user_id, $user_level);
	}

	/**
	 * Mark remarks of a specific type as read for a user (upsert)
	 * @param int $user_id Current admin user ID
	 * @param int $remark_type Remark type (1=INTERNAL, 2=CUSTOMER)
	 * @return bool Success status
	 */
	function Mark_Remarks_As_Read($user_id, $remark_type, $user_level = null)
	{
		// Get all unread remark IDs of this type for this user
		$this->db->select('remark.RemarkID');
		$this->db->join('booking', 'booking.BookingID = remark.owner_id AND remark.owner_type = "booking"', 'inner');
		$this->db->join('remark_user_read rur', 'rur.remark_id = remark.RemarkID AND rur.user_id = ' . intval($user_id), 'left');
		$this->db->where('remark.type', $remark_type);
		$this->db->where('rur.id IS NULL', null, false);

		if ($user_level == 20 && $user_id) {
			$uid = intval($user_id);
			$this->db->where("(booking.SalesAgent = $uid OR EXISTS (SELECT 1 FROM notification n WHERE n.remark_id = remark.RemarkID AND n.user_id = $uid))", null, false);
		}
		elseif ($user_level == 40 && $user_id) {
			$uid = intval($user_id);
			$this->db->where("(booking.BookingOP = $uid OR EXISTS (SELECT 1 FROM notification n WHERE n.remark_id = remark.RemarkID AND n.user_id = $uid))", null, false);
		}

		$unread_remarks = $this->db->get('remark')->result();

		if (empty($unread_remarks)) {
			return true;
		}

		$batch = array();
		foreach ($unread_remarks as $remark) {
			$batch[] = array(
				'remark_id' => $remark->RemarkID,
				'user_id' => $user_id
			);
		}

		return $this->db->insert_batch('remark_user_read', $batch);
	}

	/**
	 * Mark a single remark as read for a user
	 * @param int $remark_id Remark ID
	 * @param int $user_id User ID
	 * @return bool Success status
	 */
	function Mark_Remark_As_Read($remark_id, $user_id)
	{
		// Use INSERT IGNORE to avoid duplicate key errors
		$sql = 'INSERT IGNORE INTO remark_user_read (remark_id, user_id) VALUES (?, ?)';
		$this->db->query($sql, array(intval($remark_id), intval($user_id)));
		return true;
	}

	/**
	 * Mark a single remark as unread for a user
	 * @param int $remark_id Remark ID
	 * @param int $user_id User ID
	 * @return bool Success status
	 */
	function Mark_Remark_As_Unread($remark_id, $user_id)
	{
		$this->db->where('remark_id', intval($remark_id));
		$this->db->where('user_id', intval($user_id));
		return $this->db->delete('remark_user_read');
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

