<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Notification Model
 * 
 * Manages notifications for booking remarks
 */
class Notification_Model extends CI_Model
{
	/**
	 * Create a new notification
	 * 
	 * @param array $data Notification data
	 * @return int Insert ID
	 */
	function Create($data)
	{
		$notification_data = array(
			'user_id' => $data['user_id'],
			'type' => isset($data['type']) ? $data['type'] : 'remark',
			'owner_type' => $data['owner_type'],
			'owner_id' => $data['owner_id'],
			'remark_id' => isset($data['remark_id']) ? $data['remark_id'] : null,
			'message' => $data['message'],
			'is_read' => 0,
			'created_at' => date('Y-m-d H:i:s')
		);
		
		$this->db->insert('notification', $notification_data);
		return $this->db->insert_id();
	}

	/**
	 * Get unread notifications count for a user
	 * 
	 * @param int $user_id Admin ID
	 * @return int Count of unread notifications
	 */
	function Get_Unread_Count($user_id)
	{
		// Get user level to determine filtering
		$this->db->select('level');
		$this->db->where('AdminID', $user_id);
		$user = $this->db->get('admin')->row();
		$user_level = !empty($user) ? $user->level : null;

		$this->db->where('notification.user_id', $user_id);
		$this->db->where('notification.is_read', 0);
		
		// For Sales Agents (level 20) and Travel Consultants, only count notifications for their bookings
		if ($user_level == 20) {
			$this->db->join('booking', 'booking.BookingID = notification.owner_id AND notification.owner_type = "booking"', 'left');
			$this->db->where('booking.SalesAgent', $user_id);
		}
		
		return $this->db->count_all_results('notification');
	}

	/**
	 * Get notifications for a user
	 * 
	 * @param int $user_id Admin ID
	 * @param int $limit Limit number of notifications
	 * @param int $offset Offset for pagination
	 * @return array Array of notification objects
	 */
	function Get_Notifications($user_id, $limit = 20, $offset = 0)
	{
		// Get user level to determine filtering
		$this->db->select('level');
		$this->db->where('AdminID', $user_id);
		$user = $this->db->get('admin')->row();
		$user_level = !empty($user) ? $user->level : null;

		$this->db->select('notification.*, booking.BookingNumber, booking.BookingID, admin.Name AS CommenterName');
		$this->db->join('booking', 'booking.BookingID = notification.owner_id AND notification.owner_type = "booking"', 'left');
		$this->db->join('remark', 'remark.RemarkID = notification.remark_id', 'left');
		$this->db->join('admin', 'admin.AdminID = remark.commenter_id', 'left');
		$this->db->where('notification.user_id', $user_id);
		
		// For Sales Agents (level 20) and Travel Consultants, only show notifications for their bookings
		if ($user_level == 20) {
			$this->db->where('booking.SalesAgent', $user_id);
		}
		
		$this->db->order_by('notification.created_at', 'DESC');
		$this->db->limit($limit, $offset);
		return $this->db->get('notification')->result();
	}

	/**
	 * Mark notification as read
	 * 
	 * @param int $notification_id Notification ID
	 * @param int $user_id User ID (for security check)
	 * @return bool Success status
	 */
	function Mark_As_Read($notification_id, $user_id)
	{
		$this->db->where('NotificationID', $notification_id);
		$this->db->where('user_id', $user_id);
		$this->db->set('is_read', 1);
		$this->db->set('read_at', date('Y-m-d H:i:s'));
		$this->db->update('notification');
		return $this->db->affected_rows() > 0;
	}

	/**
	 * Mark all notifications as read for a user
	 * 
	 * @param int $user_id Admin ID
	 * @return bool Success status
	 */
	function Mark_All_As_Read($user_id)
	{
		$this->db->where('user_id', $user_id);
		$this->db->where('is_read', 0);
		$this->db->set('is_read', 1);
		$this->db->set('read_at', date('Y-m-d H:i:s'));
		$this->db->update('notification');
		return $this->db->affected_rows() >= 0; // Return true even if no rows updated
	}

	/**
	 * Create notifications for all relevant users when a remark is added
	 * 
	 * @param int $booking_id Booking ID
	 * @param int $remark_id Remark ID
	 * @param int $commenter_id Admin ID who created the remark
	 * @param string $remark_content Remark content
	 * @return int Number of notifications created
	 */
	function Create_Remark_Notifications($booking_id, $remark_id, $commenter_id, $remark_content)
	{
		// Get booking details
		$this->db->select('BookingID, BookingNumber, Customer, SalesAgent');
		$this->db->where('BookingID', $booking_id);
		$booking = $this->db->get('booking')->row();
		
		if (empty($booking)) {
			return 0;
		}

		// Get commenter name
		$this->db->select('Name');
		$this->db->where('AdminID', $commenter_id);
		$commenter = $this->db->get('admin')->row();
		$commenter_name = !empty($commenter) ? $commenter->Name : 'Unknown';

		// Prepare notification message - simple format: "XXX added a remark"
		$message = $commenter_name . ' added a remark';

		// Get all admin users (Level 10 only - exclude Sales Agents Level 20 and Finance Level 30)
		// Note: Level is stored as ENUM('10','20','30') so we need to use string '10'
		$this->db->select('AdminID');
		$this->db->where('Status', 'Y');
		$this->db->where('level', '10'); // Only Level 10 (Admin/Owner) - exclude Level 20 (Sales Agent) and Level 30 (Finance)
		$admins = $this->db->get('admin')->result();

		$notifications_created = 0;
		$notified_user_ids = array(); // Track users who already received notification to prevent duplicates

		// Create notifications for all Level 10 admins (except the commenter)
		foreach ($admins as $admin) {
			if ($admin->AdminID != $commenter_id) {
				// Check if notification already exists (prevent duplicates)
				$this->db->where('user_id', $admin->AdminID);
				$this->db->where('remark_id', $remark_id);
				$existing = $this->db->get('notification')->row();
				
				if (empty($existing)) {
					$notification_data = array(
						'user_id' => $admin->AdminID,
						'type' => 'remark',
						'owner_type' => 'booking',
						'owner_id' => $booking_id,
						'remark_id' => $remark_id,
						'message' => $message
					);
					$this->Create($notification_data);
					$notifications_created++;
					$notified_user_ids[] = $admin->AdminID;
				}
			}
		}

		// Create notification for the Sales Agent of this specific booking (if they exist and are not the commenter)
		// The SalesAgent should receive notification regardless of their level, as long as they are the SA for this booking
		if (!empty($booking->SalesAgent) && $booking->SalesAgent != $commenter_id) {
			// Check if Sales Agent exists and is active
			$this->db->select('AdminID');
			$this->db->where('AdminID', $booking->SalesAgent);
			$this->db->where('Status', 'Y');
			$sales_agent = $this->db->get('admin')->row();
			
			// Create notification if Sales Agent exists and is active
			// Also check if they already received notification (to prevent duplicates)
			if (!empty($sales_agent)) {
				// Check if notification already exists (prevent duplicates)
				$this->db->where('user_id', $booking->SalesAgent);
				$this->db->where('remark_id', $remark_id);
				$existing = $this->db->get('notification')->row();
				
				if (empty($existing)) {
					$notification_data = array(
						'user_id' => $booking->SalesAgent,
						'type' => 'remark',
						'owner_type' => 'booking',
						'owner_id' => $booking_id,
						'remark_id' => $remark_id,
						'message' => $message
					);
					$this->Create($notification_data);
					$notifications_created++;
					$notified_user_ids[] = $booking->SalesAgent;
				}
			}
		}

		return $notifications_created;
	}
}

