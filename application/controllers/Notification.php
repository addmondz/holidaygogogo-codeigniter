<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Notification extends MY_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->load->model('Notification_Model');
	}

	/**
	 * Get unread notifications count (AJAX)
	 */
	function Get_Unread_Count()
	{
		if (empty($this->session->userdata('admin_id'))) {
			$this->output
				->set_content_type('application/json')
				->set_output(json_encode([
					'success' => false,
					'message' => 'Not authenticated',
					'count' => 0
				]));
			return;
		}

		$user_id = $this->session->userdata('admin_id');
		$count = $this->Notification_Model->Get_Unread_Count($user_id);

		$this->output
			->set_content_type('application/json')
			->set_output(json_encode([
				'success' => true,
				'count' => $count
			]));
	}

	/**
	 * Get notifications list (AJAX)
	 */
	function Get_Notifications()
	{
		if (empty($this->session->userdata('admin_id'))) {
			$this->output
				->set_content_type('application/json')
				->set_output(json_encode([
					'success' => false,
					'message' => 'Not authenticated',
					'notifications' => []
				]));
			return;
		}

		$user_id = $this->session->userdata('admin_id');
		$limit = intval($this->input->get('limit')) ?: 20;
		$offset = intval($this->input->get('offset')) ?: 0;

		// Get notifications first (with original is_read status)
		$notifications = $this->Notification_Model->Get_Notifications($user_id, $limit, $offset);

		// Format notifications for JSON response (using original is_read status)
		$formatted_notifications = array();
		foreach ($notifications as $notification) {
			// Calculate relative time
			$created_timestamp = strtotime($notification->created_at);
			$current_timestamp = time();
			$time_diff = $current_timestamp - $created_timestamp;
			$time_ago = '';
			
			if ($time_diff < 60) {
				$time_ago = 'Just now';
			} elseif ($time_diff < 3600) {
				$time_ago = floor($time_diff / 60) . 'm ago';
			} elseif ($time_diff < 86400) {
				$time_ago = floor($time_diff / 3600) . 'h ago';
			} elseif ($time_diff < 604800) {
				$time_ago = floor($time_diff / 86400) . 'd ago';
			} else {
				$time_ago = date('d/m/Y H:i', $created_timestamp);
			}

			$formatted_notifications[] = array(
				'NotificationID' => $notification->NotificationID,
				'message' => $notification->message,
				'is_read' => $notification->is_read == 1,
				'created_at' => date('d/m/Y H:i:s', $created_timestamp),
				'time_ago' => $time_ago,
				'BookingNumber' => $notification->BookingNumber,
				'BookingID' => $notification->BookingID,
				'CommenterName' => $notification->CommenterName
			);
		}

		// Mark all notifications as read AFTER formatting (so response has original is_read status)
		// This way frontend can show unread styling initially, but on next fetch they're already read
		$this->Notification_Model->Mark_All_As_Read($user_id);

		$this->output
			->set_content_type('application/json')
			->set_output(json_encode([
				'success' => true,
				'notifications' => $formatted_notifications
			]));
	}

	/**
	 * Mark notification as read (AJAX)
	 */
	function Mark_As_Read()
	{
		if (empty($this->session->userdata('admin_id'))) {
			$this->output
				->set_content_type('application/json')
				->set_output(json_encode([
					'success' => false,
					'message' => 'Not authenticated'
				]));
			return;
		}

		$notification_id = intval($this->input->post('notification_id'));
		$user_id = $this->session->userdata('admin_id');

		if (empty($notification_id)) {
			$this->output
				->set_content_type('application/json')
				->set_output(json_encode([
					'success' => false,
					'message' => 'Notification ID is required'
				]));
			return;
		}

		$success = $this->Notification_Model->Mark_As_Read($notification_id, $user_id);

		$this->output
			->set_content_type('application/json')
			->set_output(json_encode([
				'success' => $success,
				'message' => $success ? 'Notification marked as read' : 'Failed to mark notification as read'
			]));
	}

	/**
	 * Mark all notifications as read (AJAX)
	 */
	function Mark_All_As_Read()
	{
		if (empty($this->session->userdata('admin_id'))) {
			$this->output
				->set_content_type('application/json')
				->set_output(json_encode([
					'success' => false,
					'message' => 'Not authenticated'
				]));
			return;
		}

		$user_id = $this->session->userdata('admin_id');
		$success = $this->Notification_Model->Mark_All_As_Read($user_id);

		$this->output
			->set_content_type('application/json')
			->set_output(json_encode([
				'success' => $success,
				'message' => $success ? 'All notifications marked as read' : 'Failed to mark notifications as read'
			]));
	}
}

