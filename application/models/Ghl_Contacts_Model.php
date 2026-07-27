<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Ghl_Contacts_Model extends CI_Model
{
    public function upsert_contact($data)
    {
        $contactId = isset($data['contact_id']) ? trim((string) $data['contact_id']) : '';
        $now = $this->get_code_datetime();

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

        if (!empty($existing['id'])) {
            $updated = $this->db
                ->where('id', (int) $existing['id'])
                ->update('ghl_contacts', array(
                    'first_name' => $insert['first_name'],
                    'last_name' => $insert['last_name'],
                    'email' => $insert['email'],
                    'phone' => $insert['phone'],
                    'assigned_to' => $insert['assigned_to'],
                    'date_added' => $insert['date_added'],
                    'updated_at' => $now,
                ));

            return $updated ? 'updated' : false;
        }

        $insert['created_at'] = $now;
        $insert['updated_at'] = $now;
        $inserted = $this->db->insert('ghl_contacts', $insert);
        return $inserted ? 'inserted' : false;
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

    private function get_code_datetime()
    {
        return (new DateTimeImmutable('now', new DateTimeZone('Asia/Kuala_Lumpur')))
            ->format('Y-m-d H:i:s');
    }
}
