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
	 * Mark notification as unread (AJAX)
	 */
	function Mark_As_Unread()
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

		$success = $this->Notification_Model->Mark_As_Unread($notification_id, $user_id);

		$this->output
			->set_content_type('application/json')
			->set_output(json_encode([
				'success' => $success,
				'message' => $success ? 'Notification marked as unread' : 'Failed to mark notification as unread'
			]));
	}

	/**
	 * Get remarks list for global remarks panel (AJAX)
	 */
	function Get_Remarks()
	{
		if (empty($this->session->userdata('admin_id'))) {
			$this->output
				->set_content_type('application/json')
				->set_output(json_encode([
					'success' => false,
					'message' => 'Not authenticated',
					'remarks' => [],
					'total' => 0
				]));
			return;
		}

		$user_id = $this->session->userdata('admin_id');
		$user_level = $this->session->userdata('level');
		$type = intval($this->input->get('type')) ?: 1; // 1=INTERNAL, 2=CUSTOMER
		$limit = intval($this->input->get('limit')) ?: 10;
		$offset = intval($this->input->get('offset')) ?: 0;

		$this->load->model('Remark_Model');
		$remarks = $this->Remark_Model->Get_All_Remarks($type, $limit, $offset, $user_id, $user_level);
		$total = $this->Remark_Model->Get_All_Remarks_Count($type, $user_id, $user_level);

		$formatted_remarks = array();
		foreach ($remarks as $remark) {
			$created_timestamp = strtotime($remark->created_at);
			$time_diff = time() - $created_timestamp;

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

			$formatted_remarks[] = array(
				'RemarkID' => $remark->RemarkID,
				'content' => $remark->content,
				'CommenterName' => $remark->CommenterName,
				'BookingNumber' => $remark->BookingNumber,
				'BookingID' => $remark->BookingID,
				'created_at' => date('d/m/Y H:i:s', $created_timestamp),
				'time_ago' => $time_ago
			);
		}

		$this->output
			->set_content_type('application/json')
			->set_output(json_encode([
				'success' => true,
				'remarks' => $formatted_remarks,
				'total' => $total
			]));
	}

	/**
	 * Get unread remarks count (AJAX)
	 */
	function Get_Remarks_Unread_Count()
	{
		if (empty($this->session->userdata('admin_id'))) {
			$this->output
				->set_content_type('application/json')
				->set_output(json_encode([
					'success' => false,
					'count' => 0
				]));
			return;
		}

		$user_id = $this->session->userdata('admin_id');
		$user_level = $this->session->userdata('level');

		$this->load->model('Remark_Model');
		$internal_count = $this->Remark_Model->Get_Unread_Count(1, $user_id, $user_level);
		$customer_count = $this->Remark_Model->Get_Unread_Count(2, $user_id, $user_level);

		$this->output
			->set_content_type('application/json')
			->set_output(json_encode([
				'success' => true,
				'count' => $internal_count + $customer_count,
				'internal_count' => $internal_count,
				'customer_count' => $customer_count
			]));
	}

	/**
	 * Mark remarks as read (AJAX)
	 */
	function Mark_Remarks_As_Read()
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
		$type = intval($this->input->post('type'));

		if (empty($type) || !in_array($type, [1, 2])) {
			$this->output
				->set_content_type('application/json')
				->set_output(json_encode([
					'success' => false,
					'message' => 'Invalid remark type'
				]));
			return;
		}

		$this->load->model('Remark_Model');
		$success = $this->Remark_Model->Mark_Remarks_As_Read($user_id, $type);

		$this->output
			->set_content_type('application/json')
			->set_output(json_encode([
				'success' => $success
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

