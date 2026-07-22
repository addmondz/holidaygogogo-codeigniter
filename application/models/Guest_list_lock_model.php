<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Guest List Lock Model
 * 
 * Manages heartbeat-based soft locks for guest list editing.
 * 
 * Design Principles:
 * - Timeout ≠ logout
 * - Expiration ≠ ownership loss
 * - Heartbeat controls activity, not identity
 * - Same owner can always re-attach even if expired
 */
class Guest_list_lock_model extends CI_Model
{
	function __construct()
	{
		parent::__construct();
		$this->load->config('guest_list');
	}

	/**
	 * Get lock by guest list hash
	 * 
	 * @param string $hash The gl parameter hash
	 * @return object|null Lock record or null
	 */
	function getByHash($hash)
	{
		$this->db->where('guest_list_hash', $hash);
		$query = $this->db->get('guest_list_locks', 1);
		return $query->num_rows() > 0 ? $query->row() : null;
	}

	/**
	 * Batch version of getByHash for a page of guest list hashes.
	 *
	 * Returns one query's worth of locks keyed by guest_list_hash, so the booking
	 * listing (Booking::ajax_list) can resolve every row's lock without a query
	 * per row (N+1). Hashes with no lock are simply absent from the map.
	 *
	 * @param string[] $hashes
	 * @return array<string, object> map of hash => lock record
	 */
	function getByHashes($hashes)
	{
		$map = array();
		$hashes = array_values(array_unique(array_filter($hashes, 'strlen')));
		if (empty($hashes)) {
			return $map;
		}
		$this->db->where_in('guest_list_hash', $hashes);
		$rows = $this->db->get('guest_list_locks')->result();
		foreach ($rows as $row) {
			$map[$row->guest_list_hash] = $row;
		}
		return $map;
	}

	/**
	 * Check if lock is expired
	 * 
	 * Lock is expired if:
	 * 1. No lock_expires_at set, OR
	 * 2. Current time > lock_expires_at, OR
	 * 3. Last heartbeat is older than timeout (missed 2+ heartbeats)
	 * 
	 * @param object $lock Lock record
	 * @return bool True if expired
	 */
	function isExpired($lock)
	{
		if (empty($lock)) {
			return true;
		}

		// Check if lock_expires_at has passed
		if (!empty($lock->lock_expires_at)) {
			$expires_at = strtotime($lock->lock_expires_at);
			if (time() > $expires_at) {
				return true; // Expiration time has passed
			}
		}

		// Check heartbeat timeout (missed 2+ heartbeats = expired)
		if (!empty($lock->last_heartbeat_at)) {
			$timeout = $this->config->item('guest_list_lock_timeout');
			$last_heartbeat = strtotime($lock->last_heartbeat_at);
			$elapsed = time() - $last_heartbeat;
			
			if ($elapsed > $timeout) {
				return true; // Heartbeat timeout exceeded
			}
		} else {
			// No heartbeat recorded = expired
			return true;
		}

		return false; // Lock is still active
	}

	/**
	 * Check if incoming request is from same owner
	 * 
	 * @param object $lock Lock record
	 * @param string $token Lock token from request
	 * @param int|null $userId User ID if logged in, null otherwise
	 * @return bool True if same owner
	 */
	function isSameOwner($lock, $token, $userId = null)
	{
		if (empty($lock)) {
			return false;
		}

		// For logged-in users: match by user_id
		if (!empty($userId) && $lock->lock_owner_type === 'user') {
			return $lock->lock_owner_id == $userId;
		}

		// For guests: match by token (primary identity)
		if ($lock->lock_owner_type === 'guest') {
			return $lock->lock_token === $token;
		}

		return false;
	}

	/**
	 * Create new lock
	 * 
	 * @param array $data Lock data
	 * @return int Insert ID
	 */
	function create($data)
	{
		$now = date('Y-m-d H:i:s');
		$expires_at = isset($data['lock_expires_at']) ? $data['lock_expires_at'] : date('Y-m-d H:i:s', strtotime('+10 minutes'));
		
		$insert = array(
			'guest_list_hash' => $data['guest_list_hash'],
			'lock_token' => $data['lock_token'],
			'lock_owner_type' => isset($data['lock_owner_type']) ? $data['lock_owner_type'] : 'guest',
			'lock_owner_id' => isset($data['lock_owner_id']) ? $data['lock_owner_id'] : null,
			'ip_hash' => isset($data['ip_hash']) ? $data['ip_hash'] : null,
			'user_agent_hash' => isset($data['user_agent_hash']) ? $data['user_agent_hash'] : null,
			'last_heartbeat_at' => $now,
			'lock_expires_at' => $expires_at,
			'extension_used' => 0,
			'created_at' => $now,
			'updated_at' => $now
		);

		$this->db->insert('guest_list_locks', $insert);
		return $this->db->insert_id();
	}

	/**
	 * Refresh heartbeat timestamp
	 * 
	 * @param int $id Lock ID
	 * @return bool Success
	 */
	function refreshHeartbeat($id)
	{
		$this->db->where('id', $id);
		$this->db->set('last_heartbeat_at', date('Y-m-d H:i:s'));
		$this->db->set('updated_at', date('Y-m-d H:i:s'));
		$this->db->update('guest_list_locks');
		return $this->db->affected_rows() > 0;
	}

	/**
	 * Overwrite existing lock (when expired and different owner takes over)
	 * 
	 * @param int $id Lock ID
	 * @param array $data New lock data
	 * @return bool Success
	 */
	function overwrite($id, $data)
	{
		$now = date('Y-m-d H:i:s');
		$expires_at = isset($data['lock_expires_at']) ? $data['lock_expires_at'] : date('Y-m-d H:i:s', strtotime('+10 minutes'));
		
		$update = array(
			'lock_token' => $data['lock_token'],
			'lock_owner_type' => isset($data['lock_owner_type']) ? $data['lock_owner_type'] : 'guest',
			'lock_owner_id' => isset($data['lock_owner_id']) ? $data['lock_owner_id'] : null,
			'ip_hash' => isset($data['ip_hash']) ? $data['ip_hash'] : null,
			'user_agent_hash' => isset($data['user_agent_hash']) ? $data['user_agent_hash'] : null,
			'last_heartbeat_at' => $now,
			'lock_expires_at' => $expires_at,
			'extension_used' => 0, // Reset extension for new owner
			'updated_at' => $now
		);

		$this->db->where('id', $id);
		$this->db->update('guest_list_locks', $update);
		return $this->db->affected_rows() > 0;
	}

	/**
	 * Extend lock expiration (one-time only)
	 * 
	 * @param int $id Lock ID
	 * @param int $additionalMinutes Minutes to add (default 12)
	 * @return bool Success
	 */
	function extendLock($id, $additionalMinutes = 12)
	{
		$this->db->select('lock_expires_at, extension_used');
		$this->db->where('id', $id);
		$lock = $this->db->get('guest_list_locks')->row();
		
		if (empty($lock)) {
			return false;
		}
		
		// Check if extension already used
		if ($lock->extension_used == 1) {
			return false; // Extension already used
		}
		
		// Extend expiration time
		$new_expires_at = date('Y-m-d H:i:s', strtotime($lock->lock_expires_at . ' +' . $additionalMinutes . ' minutes'));
		
		$this->db->where('id', $id);
		$this->db->set('lock_expires_at', $new_expires_at);
		$this->db->set('extension_used', 1);
		$this->db->set('updated_at', date('Y-m-d H:i:s'));
		$this->db->update('guest_list_locks');
		
		return $this->db->affected_rows() > 0;
	}

	/**
	 * Get lock expiration time
	 * 
	 * @param string $hash Guest list hash
	 * @return string|null Expiration datetime or null
	 */
	function getExpiration($hash)
	{
		$this->db->select('lock_expires_at, extension_used');
		$this->db->where('guest_list_hash', $hash);
		$lock = $this->db->get('guest_list_locks')->row();
		
		return $lock ? $lock->lock_expires_at : null;
	}

	/**
	 * Delete lock only if owned by caller
	 * 
	 * @param string $hash Guest list hash
	 * @param string $token Lock token
	 * @param int|null $userId User ID if logged in
	 * @return bool Success (true if deleted or didn't exist)
	 */
	function deleteIfOwner($hash, $token, $userId = null)
	{
		$lock = $this->getByHash($hash);
		
		if (empty($lock)) {
			return true; // No lock exists, consider it success
		}

		// Only delete if same owner
		if (!$this->isSameOwner($lock, $token, $userId)) {
			return false; // Not owner, cannot delete
		}

		$this->db->where('id', $lock->id);
		$this->db->delete('guest_list_locks');
		return $this->db->affected_rows() > 0;
	}

	/**
	 * Get lock status for display
	 * 
	 * @param string $hash Guest list hash
	 * @return array Status info
	 */
	function getStatus($hash)
	{
		$lock = $this->getByHash($hash);
		
		if (empty($lock)) {
			return array(
				'exists' => false,
				'active' => false,
				'expired' => false
			);
		}

		$expired = $this->isExpired($lock);

		return array(
			'exists' => true,
			'active' => !$expired,
			'expired' => $expired,
			'lock_expires_at' => $lock->lock_expires_at,
			'extension_used' => $lock->extension_used == 1
		);
	}

}
