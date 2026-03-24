<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Ghl_Contacts_Model extends CI_Model
{
    public function upsert_contact($data)
    {
        $contactId = isset($data['contact_id']) ? trim((string) $data['contact_id']) : '';

        if ($contactId === '') {
            return false;
        }

        $existing = $this->db
            ->select('id')
            ->from('ghl_contacts')
            ->where('contact_id', $contactId)
            ->limit(1)
            ->get()
            ->row_array();

        $columns = array(
            'contact_id',
            'first_name',
            'last_name',
            'email',
            'phone',
            'assigned_to',
            'date_added',
        );

        $insert = array();
        foreach ($columns as $column) {
            $insert[$column] = array_key_exists($column, $data) ? $data[$column] : null;
        }

        $sql = "INSERT INTO `ghl_contacts` (`contact_id`, `first_name`, `last_name`, `email`, `phone`, `assigned_to`, `date_added`) VALUES (?, ?, ?, ?, ?, ?, ?) "
            . "ON DUPLICATE KEY UPDATE "
            . "`first_name` = VALUES(`first_name`), "
            . "`last_name` = VALUES(`last_name`), "
            . "`email` = VALUES(`email`), "
            . "`phone` = VALUES(`phone`), "
            . "`assigned_to` = VALUES(`assigned_to`), "
            . "`date_added` = VALUES(`date_added`), "
            . "`updated_at` = CURRENT_TIMESTAMP";

        $success = $this->db->query($sql, array(
            $insert['contact_id'],
            $insert['first_name'],
            $insert['last_name'],
            $insert['email'],
            $insert['phone'],
            $insert['assigned_to'],
            $insert['date_added'],
        ));

        if (!$success) {
            return false;
        }

        return empty($existing['id']) ? 'inserted' : 'updated';
    }

    public function get_last_contact_cursor()
    {
        $row = $this->db
            ->select('contact_id')
            ->from('ghl_contacts')
            ->order_by('id', 'DESC')
            ->limit(1)
            ->get()
            ->row_array();

       return $row['contact_id'] ?? null;
    }
}
