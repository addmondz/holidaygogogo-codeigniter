<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Ghl_Users_Model extends CI_Model
{
    /**
     * Upsert a GHL user by LocationID + UserID (GHL user id).
     * Returns 'inserted', 'updated', or false.
     */
    public function upsert_user($data)
    {
        $userId = isset($data['UserID']) ? trim((string) $data['UserID']) : '';
        $locationId = isset($data['LocationID']) ? trim((string) $data['LocationID']) : '';

        if ($userId === '' || $locationId === '') {
            return false;
        }

        $existing = $this->db
            ->select('ID')
            ->from('ghl_users')
            ->where('LocationID', $locationId)
            ->where('UserID', $userId)
            ->limit(1)
            ->get()
            ->row_array();

        if (!empty($existing['ID'])) {
            $updated = $this->db
                ->where('ID', (int) $existing['ID'])
                ->update('ghl_users', $data);
            return $updated ? 'updated' : false;
        }

        $inserted = $this->db->insert('ghl_users', $data);
        return $inserted ? 'inserted' : false;
    }

    /**
     * Get all users for a location (optionally exclude deleted).
     *
     * @param string $locationId
     * @param bool   $excludeDeleted
     * @return array
     */
    public function get_users_by_location($locationId, $excludeDeleted = true)
    {
        $this->db->from('ghl_users')->where('LocationID', $locationId);
        if ($excludeDeleted) {
            $this->db->where('Deleted', 0);
        }
        $this->db->order_by('Name', 'ASC');
        return $this->db->get()->result_array();
    }

    /**
     * Get one user by GHL user id and location.
     *
     * @param string $userId     GHL user id
     * @param string $locationId
     * @return array|null
     */
    public function get_user($userId, $locationId)
    {
        $row = $this->db
            ->from('ghl_users')
            ->where('UserID', $userId)
            ->where('LocationID', $locationId)
            ->limit(1)
            ->get()
            ->row_array();
        return !empty($row) ? $row : null;
    }
}
