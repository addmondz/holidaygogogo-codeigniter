<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Guest List Lock Controller
 * 
 * Handles lock acquisition, heartbeat, and release.
 * All lock logic is in the model - controller only orchestrates.
 */
class GuestListLock extends CI_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->load->model('Guest_list_lock_model');
		$this->load->config('guest_list');
	}

	/**
	 * Acquire lock
	 * 
	 * POST /guestlistlock/acquire
	 * 
	 * Payload: { "guest_list_hash": "HASH", "lock_token": "UUID" }
	 * 
	 * Logic:
	 * - If no lock → create lock → GRANT
	 * - If lock exists:
	 *   - If same owner → refresh heartbeat → GRANT
	 *   - Else if lock expired → overwrite → GRANT
	 *   - Else → DENY
	 */
	function acquire()
	{
		$hash = $this->input->post('guest_list_hash');
		$token = $this->input->post('lock_token');

		if (empty($hash) || empty($token)) {
			$this->output
				->set_status_header(400)
				->set_content_type('application/json')
				->set_output(json_encode(array(
					'status' => 'error',
					'message' => 'Missing required parameters'
				)));
			return;
		}

		// Get current user ID if logged in
		$userId = $this->session->userdata('admin_id') ? $this->session->userdata('admin_id') : null;
		$ownerType = $userId ? 'user' : 'guest';

		// Get existing lock
		$lock = $this->Guest_list_lock_model->getByHash($hash);

		if (empty($lock)) {
			// No lock exists → create new lock with 10 minutes expiration
			$expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));
			$lockData = array(
				'guest_list_hash' => $hash,
				'lock_token' => $token,
				'lock_owner_type' => $ownerType,
				'lock_owner_id' => $userId,
				'ip_hash' => md5($this->input->ip_address()),
				'user_agent_hash' => md5($this->agent->agent_string()),
				'lock_expires_at' => $expires_at
			);

			$this->Guest_list_lock_model->create($lockData);

			$this->output
				->set_content_type('application/json')
				->set_output(json_encode(array(
					'status' => 'granted',
					'expires_at' => $expires_at,
					'extension_used' => false
				)));
			return;
		}

		// Lock exists - check ownership
		$isSameOwner = $this->Guest_list_lock_model->isSameOwner($lock, $token, $userId);
		$isExpired = $this->Guest_list_lock_model->isExpired($lock);

		if ($isSameOwner) {
			// Same owner → refresh heartbeat but keep original expiration time
			$this->Guest_list_lock_model->refreshHeartbeat($lock->id);

			$this->output
				->set_content_type('application/json')
				->set_output(json_encode(array(
					'status' => 'granted',
					'expires_at' => $lock->lock_expires_at,
					'extension_used' => $lock->extension_used == 1
				)));
			return;
		}

		if ($isExpired) {
			// Expired lock → overwrite with new owner (new 10 minutes)
			$expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));
			$lockData = array(
				'lock_token' => $token,
				'lock_owner_type' => $ownerType,
				'lock_owner_id' => $userId,
				'ip_hash' => md5($this->input->ip_address()),
				'user_agent_hash' => md5($this->agent->agent_string()),
				'lock_expires_at' => $expires_at
			);

			$this->Guest_list_lock_model->overwrite($lock->id, $lockData);

			$this->output
				->set_content_type('application/json')
				->set_output(json_encode(array(
					'status' => 'granted',
					'expires_at' => $expires_at,
					'extension_used' => false
				)));
			return;
		}

		// Lock is active and owned by different person → DENY
		$status = $this->Guest_list_lock_model->getStatus($hash);
		
		$this->output
			->set_status_header(423) // 423 Locked
			->set_content_type('application/json')
			->set_output(json_encode(array(
				'status' => 'locked',
				'message' => 'This guest list is currently being edited by another user.',
				'expires_at' => $status['lock_expires_at']
			)));
	}

	/**
	 * Heartbeat - keep lock alive
	 * 
	 * POST /guestlistlock/heartbeat
	 * 
	 * Payload: { "guest_list_hash": "HASH", "lock_token": "UUID" }
	 */
	function heartbeat()
	{
		$hash = $this->input->post('guest_list_hash');
		$token = $this->input->post('lock_token');

		if (empty($hash) || empty($token)) {
			$this->output
				->set_status_header(400)
				->set_content_type('application/json')
				->set_output(json_encode(array(
					'status' => 'error',
					'message' => 'Missing required parameters'
				)));
			return;
		}

		$userId = $this->session->userdata('admin_id') ? $this->session->userdata('admin_id') : null;
		$lock = $this->Guest_list_lock_model->getByHash($hash);

		if (empty($lock)) {
			$this->output
				->set_status_header(404)
				->set_content_type('application/json')
				->set_output(json_encode(array(
					'status' => 'error',
					'message' => 'Lock not found'
				)));
			return;
		}

		// Validate ownership
		if (!$this->Guest_list_lock_model->isSameOwner($lock, $token, $userId)) {
			$this->output
				->set_status_header(403)
				->set_content_type('application/json')
				->set_output(json_encode(array(
					'status' => 'error',
					'message' => 'Not lock owner'
				)));
			return;
		}

		// Refresh heartbeat
		$this->Guest_list_lock_model->refreshHeartbeat($lock->id);

		$this->output
			->set_content_type('application/json')
			->set_output(json_encode(array(
				'status' => 'ok',
				'expires_at' => $lock->lock_expires_at
			)));
	}

	/**
	 * Extend lock (one-time only)
	 * 
	 * POST /guestlistlock/extend
	 * 
	 * Payload: { "guest_list_hash": "HASH", "lock_token": "UUID" }
	 */
	function extend()
	{
		$hash = $this->input->post('guest_list_hash');
		$token = $this->input->post('lock_token');

		if (empty($hash) || empty($token)) {
			$this->output
				->set_status_header(400)
				->set_content_type('application/json')
				->set_output(json_encode(array(
					'status' => 'error',
					'message' => 'Missing required parameters'
				)));
			return;
		}

		$userId = $this->session->userdata('admin_id') ? $this->session->userdata('admin_id') : null;
		$lock = $this->Guest_list_lock_model->getByHash($hash);

		if (empty($lock)) {
			$this->output
				->set_status_header(404)
				->set_content_type('application/json')
				->set_output(json_encode(array(
					'status' => 'error',
					'message' => 'Lock not found'
				)));
			return;
		}

		// Validate ownership
		if (!$this->Guest_list_lock_model->isSameOwner($lock, $token, $userId)) {
			$this->output
				->set_status_header(403)
				->set_content_type('application/json')
				->set_output(json_encode(array(
					'status' => 'error',
					'message' => 'Not lock owner'
				)));
			return;
		}

		// Check if extension already used
		if ($lock->extension_used == 1) {
			$this->output
				->set_status_header(400)
				->set_content_type('application/json')
				->set_output(json_encode(array(
					'status' => 'error',
					'message' => 'Extension already used'
				)));
			return;
		}

		// Extend lock by 12 minutes
		$extended = $this->Guest_list_lock_model->extendLock($lock->id, 12);
		
		if ($extended) {
			// Get updated expiration
			$new_expires_at = $this->Guest_list_lock_model->getExpiration($hash);
			
			$this->output
				->set_content_type('application/json')
				->set_output(json_encode(array(
					'status' => 'extended',
					'expires_at' => $new_expires_at
				)));
		} else {
			$this->output
				->set_status_header(500)
				->set_content_type('application/json')
				->set_output(json_encode(array(
					'status' => 'error',
					'message' => 'Failed to extend lock'
				)));
		}
	}

	/**
	 * Release lock
	 * 
	 * POST /guestlistlock/release
	 * 
	 * Payload: { "guest_list_hash": "HASH", "lock_token": "UUID" }
	 * 
	 * Only deletes if owned by caller (best effort)
	 */
	function release()
	{
		$hash = $this->input->post('guest_list_hash');
		$token = $this->input->post('lock_token');

		if (empty($hash) || empty($token)) {
			$this->output
				->set_status_header(400)
				->set_content_type('application/json')
				->set_output(json_encode(array(
					'status' => 'error',
					'message' => 'Missing required parameters'
				)));
			return;
		}

		$userId = $this->session->userdata('admin_id') ? $this->session->userdata('admin_id') : null;
		$deleted = $this->Guest_list_lock_model->deleteIfOwner($hash, $token, $userId);

		$this->output
			->set_content_type('application/json')
			->set_output(json_encode(array(
				'status' => $deleted ? 'released' : 'not_owner',
				'message' => $deleted ? 'Lock released' : 'Not lock owner, cannot release'
			)));
	}
}

