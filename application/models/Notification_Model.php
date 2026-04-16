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
		// Remarks surface in the Messages dropdown, not the bell
		$this->db->where('notification.type !=', 'remark');

		// For Sales Agents (level 20), only count booking notifications for their bookings
		// (non-booking notifications, e.g. owner_type='product', are always shown)
		if ($user_level == 20) {
			$this->db->join('booking', 'booking.BookingID = notification.owner_id AND notification.owner_type = "booking"', 'left');
			$this->db->where('(notification.owner_type != "booking" OR booking.SalesAgent = ' . (int)$user_id . ')', NULL, FALSE);
		}
		// For BookingOP (level 40), only count booking notifications for their bookings
		elseif ($user_level == 40) {
			$this->db->join('booking', 'booking.BookingID = notification.owner_id AND notification.owner_type = "booking"', 'left');
			$this->db->where('(notification.owner_type != "booking" OR booking.BookingOP = ' . (int)$user_id . ')', NULL, FALSE);
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

		$this->db->select('notification.*, booking.BookingNumber, booking.BookingID, booking.Customer, admin.Name AS CommenterName');
		$this->db->join('booking', 'booking.BookingID = notification.owner_id AND notification.owner_type = "booking"', 'left');
		$this->db->join('remark', 'remark.RemarkID = notification.remark_id', 'left');
		$this->db->join('admin', 'admin.AdminID = remark.commenter_id', 'left');
		$this->db->where('notification.user_id', $user_id);
		// Remarks surface in the Messages dropdown, not the bell
		$this->db->where('notification.type !=', 'remark');

		// For Sales Agents (level 20), only show booking notifications for their bookings
		// (non-booking notifications, e.g. owner_type='product', are always shown)
		if ($user_level == 20) {
			$this->db->where('(notification.owner_type != "booking" OR booking.SalesAgent = ' . (int)$user_id . ')', NULL, FALSE);
		}
		// For BookingOP (level 40), only show booking notifications for their bookings
		elseif ($user_level == 40) {
			$this->db->where('(notification.owner_type != "booking" OR booking.BookingOP = ' . (int)$user_id . ')', NULL, FALSE);
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
	 * Mark notification as unread
	 *
	 * @param int $notification_id Notification ID
	 * @param int $user_id User ID (for security check)
	 * @return bool Success status
	 */
	function Mark_As_Unread($notification_id, $user_id)
	{
		$this->db->where('NotificationID', $notification_id);
		$this->db->where('user_id', $user_id);
		$this->db->set('is_read', 0);
		$this->db->set('read_at', NULL);
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
		$this->db->select('BookingID, BookingNumber, Customer, SalesAgent, BookingOP');
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

		$notifications_created = 0;
		$notified_user_ids = array(); // Track users who already received notification to prevent duplicates

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

		// Create notification for the BookingOP of this specific booking (if they exist and are not the commenter)
		if (!empty($booking->BookingOP) && $booking->BookingOP != $commenter_id && !in_array($booking->BookingOP, $notified_user_ids)) {
			$this->db->select('AdminID');
			$this->db->where('AdminID', $booking->BookingOP);
			$this->db->where('Status', 'Y');
			$booking_op = $this->db->get('admin')->row();

			if (!empty($booking_op)) {
				$this->db->where('user_id', $booking->BookingOP);
				$this->db->where('remark_id', $remark_id);
				$existing = $this->db->get('notification')->row();

				if (empty($existing)) {
					$notification_data = array(
						'user_id' => $booking->BookingOP,
						'type' => 'remark',
						'owner_type' => 'booking',
						'owner_id' => $booking_id,
						'remark_id' => $remark_id,
						'message' => $message
					);
					$this->Create($notification_data);
					$notifications_created++;
					$notified_user_ids[] = $booking->BookingOP;
				}
			}
		}

		return $notifications_created;
	}

	/**
	 * Create notifications for specific users when a remark is added
	 *
	 * @param int $booking_id Booking ID
	 * @param int $remark_id Remark ID
	 * @param int $commenter_id Admin ID who created the remark
	 * @param string $remark_content Remark content
	 * @param array $user_ids Array of Admin IDs to notify
	 * @return int Number of notifications created
	 */
	function Create_Remark_Notifications_For_Users($booking_id, $remark_id, $commenter_id, $remark_content, $user_ids)
	{
		// Get commenter name
		$this->db->select('Name');
		$this->db->where('AdminID', $commenter_id);
		$commenter = $this->db->get('admin')->row();
		$commenter_name = !empty($commenter) ? $commenter->Name : 'Unknown';

		$message = $commenter_name . ' added a remark';

		// Remove commenter from the list (never self-notify)
		$user_ids = array_diff($user_ids, array($commenter_id));

		$notifications_created = 0;

		foreach ($user_ids as $user_id) {
			// Verify admin exists and is active
			$this->db->select('AdminID');
			$this->db->where('AdminID', $user_id);
			$this->db->where('Status', 'Y');
			$admin = $this->db->get('admin')->row();

			if (empty($admin)) {
				continue;
			}

			// Check for duplicate notification
			$this->db->where('user_id', $user_id);
			$this->db->where('remark_id', $remark_id);
			$existing = $this->db->get('notification')->row();

			if (empty($existing)) {
				$notification_data = array(
					'user_id' => $user_id,
					'type' => 'remark',
					'owner_type' => 'booking',
					'owner_id' => $booking_id,
					'remark_id' => $remark_id,
					'message' => $message
				);
				$this->Create($notification_data);
				$notifications_created++;
			}
		}

		return $notifications_created;
	}

	/**
	 * Get list of admins who were explicitly tagged (via "Also Notify") on a remark.
	 * Excludes auto-recipients (e.g. the booking's SalesAgent / BookingOP) so the
	 * returned set represents only the "extra" users selected by the commenter.
	 *
	 * @param int $remark_id
	 * @param array $exclude_user_ids AdminIDs to exclude (typically SalesAgent + BookingOP of the booking)
	 * @return array Rows of {AdminID, Name} ordered by Name
	 */
	function Get_Tagged_Users_For_Remark($remark_id, $exclude_user_ids = array())
	{
		$this->db->select('admin.AdminID, admin.Name');
		$this->db->from('notification');
		$this->db->join('admin', 'admin.AdminID = notification.user_id', 'inner');
		$this->db->where('notification.remark_id', intval($remark_id));
		$this->db->where('notification.type', 'remark');

		$exclude_ids = array();
		foreach ($exclude_user_ids as $uid) {
			if (!empty($uid)) {
				$exclude_ids[] = intval($uid);
			}
		}
		if (!empty($exclude_ids)) {
			$this->db->where_not_in('notification.user_id', $exclude_ids);
		}

		$this->db->order_by('admin.Name', 'ASC');
		return $this->db->get()->result();
	}

	/**
	 * Create notification for Sales Agent and BookingOP when customer adds a remark
	 *
	 * @param int $booking_id Booking ID
	 * @param int $remark_id Remark ID
	 * @param int $sales_agent_id Sales Agent Admin ID
	 * @param string $customer_name Customer name
	 * @param string $remark_content Remark content
	 * @param int|null $booking_op_id BookingOP Admin ID
	 * @return bool Success status
	 */
	function Create_Customer_Remark_Notification($booking_id, $remark_id, $sales_agent_id, $customer_name, $remark_content, $booking_op_id = null)
	{
		$message = 'Customer added a remark';
		$notified = false;
		$notified_user_ids = array();

		// Notify all Level 10 (Owner) admins
		$this->db->select('AdminID');
		$this->db->where('Status', 'Y');
		$this->db->where('level', '10');
		$admins = $this->db->get('admin')->result();

		foreach ($admins as $admin) {
			$this->db->where('user_id', $admin->AdminID);
			$this->db->where('remark_id', $remark_id);
			$existing = $this->db->get('notification')->row();

			if (empty($existing)) {
				$this->Create(array(
					'user_id' => $admin->AdminID,
					'type' => 'remark',
					'owner_type' => 'booking',
					'owner_id' => $booking_id,
					'remark_id' => $remark_id,
					'message' => $message
				));
				$notified = true;
			}
			$notified_user_ids[] = $admin->AdminID;
		}

		// Notify Sales Agent
		if (!empty($sales_agent_id) && !in_array($sales_agent_id, $notified_user_ids)) {
			$this->db->select('AdminID');
			$this->db->where('AdminID', $sales_agent_id);
			$this->db->where('Status', 'Y');
			$sales_agent = $this->db->get('admin')->row();

			if (!empty($sales_agent)) {
				$this->db->where('user_id', $sales_agent_id);
				$this->db->where('remark_id', $remark_id);
				$existing = $this->db->get('notification')->row();

				if (empty($existing)) {
					$this->Create(array(
						'user_id' => $sales_agent_id,
						'type' => 'remark',
						'owner_type' => 'booking',
						'owner_id' => $booking_id,
						'remark_id' => $remark_id,
						'message' => $message
					));
					$notified = true;
				}
			}
			$notified_user_ids[] = $sales_agent_id;
		}

		// Notify BookingOP
		if (!empty($booking_op_id) && !in_array($booking_op_id, $notified_user_ids)) {
			$this->db->select('AdminID');
			$this->db->where('AdminID', $booking_op_id);
			$this->db->where('Status', 'Y');
			$booking_op = $this->db->get('admin')->row();

			if (!empty($booking_op)) {
				$this->db->where('user_id', $booking_op_id);
				$this->db->where('remark_id', $remark_id);
				$existing = $this->db->get('notification')->row();

				if (empty($existing)) {
					$this->Create(array(
						'user_id' => $booking_op_id,
						'type' => 'remark',
						'owner_type' => 'booking',
						'owner_id' => $booking_id,
						'remark_id' => $remark_id,
						'message' => $message
					));
					$notified = true;
				}
			}
		}

		return $notified;
	}

	/**
	 * Create notification for Sales Agent when a booking is created under them by someone else
	 * Message format: "XXX has created an order"
	 *
	 * @param int $booking_id Booking ID
	 * @param int $creator_id Admin ID who created the booking
	 * @param string $creator_name Name of the user who created the booking
	 * @param int|null $sales_agent_id Sales Agent (Admin ID) assigned to the booking
	 * @return int 1 if notification created, 0 otherwise
	 */
	function Create_Booking_Created_Notification($booking_id, $creator_id, $creator_name, $sales_agent_id)
	{
		if (empty($sales_agent_id) || (int) $sales_agent_id === (int) $creator_id) {
			return 0;
		}

		$this->db->select('AdminID');
		$this->db->where('AdminID', $sales_agent_id);
		$this->db->where('Status', 'Y');
		$sa = $this->db->get('admin')->row();
		if (empty($sa)) {
			return 0;
		}

		$message = $creator_name . ' has created an order';
		$data = array(
			'user_id'     => $sales_agent_id,
			'type'        => 'booking_created',
			'owner_type'  => 'booking',
			'owner_id'    => $booking_id,
			'remark_id'   => null,
			'message'     => $message
		);

		return $this->Create($data) ? 1 : 0;
	}

	/**
	 * Create notifications for relevant users when a booking is updated
	 *
	 * @param int $booking_id Booking ID
	 * @param int $updater_id Admin ID who updated the booking
	 * @param string $updater_name Name of the user who updated the booking
	 * @return int Number of notifications created
	 */
	function Create_Booking_Updated_Notification($booking_id, $updater_id, $updater_name)
	{
		// Get booking details
		$this->db->select('BookingID, BookingNumber, SalesAgent, BookingOP');
		$this->db->where('BookingID', $booking_id);
		$booking = $this->db->get('booking')->row();

		if (empty($booking)) {
			return 0;
		}

		$message = $updater_name . ' updated a booking';
		$notifications_created = 0;
		$notified_user_ids = array();

		// Notify Level 10 (Owner) admins
		$this->db->select('AdminID');
		$this->db->where('Status', 'Y');
		$this->db->where('level', '10');
		$admins = $this->db->get('admin')->result();

		foreach ($admins as $admin) {
			if ($admin->AdminID != $updater_id) {
				$notification_data = array(
					'user_id' => $admin->AdminID,
					'type' => 'booking_updated',
					'owner_type' => 'booking',
					'owner_id' => $booking_id,
					'remark_id' => null,
					'message' => $message
				);
				$this->Create($notification_data);
				$notifications_created++;
				$notified_user_ids[] = $admin->AdminID;
			}
		}

		// Notify SalesAgent (TC)
		if (!empty($booking->SalesAgent) && $booking->SalesAgent != $updater_id && !in_array($booking->SalesAgent, $notified_user_ids)) {
			$this->db->select('AdminID');
			$this->db->where('AdminID', $booking->SalesAgent);
			$this->db->where('Status', 'Y');
			$sa = $this->db->get('admin')->row();

			if (!empty($sa)) {
				$notification_data = array(
					'user_id' => $booking->SalesAgent,
					'type' => 'booking_updated',
					'owner_type' => 'booking',
					'owner_id' => $booking_id,
					'remark_id' => null,
					'message' => $message
				);
				$this->Create($notification_data);
				$notifications_created++;
				$notified_user_ids[] = $booking->SalesAgent;
			}
		}

		// Notify BookingOP
		if (!empty($booking->BookingOP) && $booking->BookingOP != $updater_id && !in_array($booking->BookingOP, $notified_user_ids)) {
			$this->db->select('AdminID');
			$this->db->where('AdminID', $booking->BookingOP);
			$this->db->where('Status', 'Y');
			$op = $this->db->get('admin')->row();

			if (!empty($op)) {
				$notification_data = array(
					'user_id' => $booking->BookingOP,
					'type' => 'booking_updated',
					'owner_type' => 'booking',
					'owner_id' => $booking_id,
					'remark_id' => null,
					'message' => $message
				);
				$this->Create($notification_data);
				$notifications_created++;
				$notified_user_ids[] = $booking->BookingOP;
			}
		}

		return $notifications_created;
	}
}

