<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Ghl_Custom_Fields_Model extends CI_Model
{
    public function upsert_field($data)
    {
        $locationId = isset($data['location_id']) ? trim((string) $data['location_id']) : '';
        $fieldId = isset($data['field_id']) ? trim((string) $data['field_id']) : '';
        $now = $this->get_code_datetime();

        if ($locationId === '' || $fieldId === '') {
            return false;
        }

        $existing = $this->db
            ->select('id')
            ->from('ghl_custom_fields')
            ->where('location_id', $locationId)
            ->where('field_id', $fieldId)
            ->limit(1)
            ->get()
            ->row_array();

        $columns = array(
            'location_id',
            'field_id',
            'field_key',
            'model',
            'name',
            'data_type',
            'placeholder',
            'description',
            'position',
            'show_in_forms',
            'parent_id',
            'object_key',
            'options_json',
            'raw_json',
            'date_added',
            'date_updated',
        );

        $payload = array();
        foreach ($columns as $column) {
            $payload[$column] = array_key_exists($column, $data) ? $data[$column] : null;
        }
        $payload['show_in_forms'] = !empty($payload['show_in_forms']) ? 1 : 0;

        if (!empty($existing['id'])) {
            $payload['updated_at'] = $now;
            $updated = $this->db
                ->where('id', (int) $existing['id'])
                ->update('ghl_custom_fields', $payload);

            return $updated ? 'updated' : false;
        }

        $payload['created_at'] = $now;
        $payload['updated_at'] = $now;
        $inserted = $this->db->insert('ghl_custom_fields', $payload);
        return $inserted ? 'inserted' : false;
    }

    private function get_code_datetime()
    {
        return (new DateTimeImmutable('now', new DateTimeZone('Asia/Kuala_Lumpur')))
            ->format('Y-m-d H:i:s');
    }
}
