<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Ghl_Contacts_Model extends CI_Model
{
    public function upsert_contact($data)
    {
        $contactId = isset($data['contact_id']) ? trim((string) $data['contact_id']) : '';
        $now = $this->get_code_datetime();
        $customFieldValues = isset($data['custom_field_values']) && is_array($data['custom_field_values'])
            ? $data['custom_field_values']
            : array();

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
            'location_id',
            'contact_name',
            'first_name',
            'last_name',
            'first_name_raw',
            'last_name_raw',
            'company_name',
            'email',
            'phone',
            'assigned_to',
            'business_id',
            'contact_type',
            'source',
            'dnd',
            'dnd_settings_json',
            'city',
            'state',
            'postal_code',
            'address1',
            'country',
            'website',
            'timezone',
            'profile_photo',
            'date_of_birth',
            'date_added',
            'date_updated',
            'tags_json',
            'additional_emails_json',
            'followers_json',
            'attributions_json',
            'custom_fields_json',
        );

        $insert = array();
        foreach ($columns as $column) {
            $insert[$column] = array_key_exists($column, $data) ? $data[$column] : null;
        }

        if (!empty($existing['id'])) {
            $update = $insert;
            unset($update['contact_id']);
            $update['updated_at'] = $now;

            $updated = $this->db
                ->where('id', (int) $existing['id'])
                ->update('ghl_contacts', $update);

            if (!$updated) {
                return false;
            }

            $this->replace_custom_field_values($contactId, $customFieldValues);
            return 'updated';
        }

        $insert['created_at'] = $now;
        $insert['updated_at'] = $now;
        $inserted = $this->db->insert('ghl_contacts', $insert);
        if (!$inserted) {
            return false;
        }

        $this->replace_custom_field_values($contactId, $customFieldValues);
        return 'inserted';
    }

    public function replace_custom_field_values($contactId, $values)
    {
        $contactId = trim((string) $contactId);
        if ($contactId === '') {
            return false;
        }

        $this->db->where('contact_id', $contactId)->delete('ghl_contact_custom_field_values');

        if (empty($values) || !is_array($values)) {
            return true;
        }

        $now = $this->get_code_datetime();
        $rows = array();

        foreach ($values as $value) {
            if (!is_array($value) || empty($value['field_identity'])) {
                continue;
            }

            $rows[] = array(
                'contact_id' => $contactId,
                'field_identity' => (string) $value['field_identity'],
                'field_id' => isset($value['field_id']) ? $value['field_id'] : null,
                'field_key' => isset($value['field_key']) ? $value['field_key'] : null,
                'field_name' => isset($value['field_name']) ? $value['field_name'] : null,
                'value_text' => isset($value['value_text']) ? $value['value_text'] : null,
                'value_json' => isset($value['value_json']) ? $value['value_json'] : null,
                'created_at' => $now,
                'updated_at' => $now,
            );
        }

        if (empty($rows)) {
            return true;
        }

        return $this->db->insert_batch('ghl_contact_custom_field_values', $rows) !== false;
    }

    /**
     * Insert a hand-entered ("Manual") lead. $row is the whitelisted array from
     * ghl_manual_lead_prepare() (synthetic contact_id, lead_source = 'manual',
     * the visible-column fields + tags_json). Never updates: a manual lead's
     * synthetic contact_id is unique, so this is always a fresh insert. Returns
     * the new row id, or false on failure.
     */
    public function create_manual_lead($row)
    {
        if (empty($row['contact_id'])) {
            return false;
        }

        $now = $this->get_code_datetime();

        $allowed = array(
            'contact_id', 'first_name', 'last_name', 'email', 'phone',
            'assigned_to', 'lead_source', 'gender', 'race', 'nationality',
            'chat_language', 'date_of_birth', 'tags_json', 'date_added',
        );

        $insert = array();
        foreach ($allowed as $column) {
            if (array_key_exists($column, $row)) {
                $insert[$column] = $row[$column];
            }
        }

        $insert['created_at'] = $now;
        $insert['updated_at'] = $now;

        if (!$this->db->insert('ghl_contacts', $insert)) {
            return false;
        }

        return (int) $this->db->insert_id();
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
