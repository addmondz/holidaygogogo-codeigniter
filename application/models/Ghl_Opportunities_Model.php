<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Ghl_Opportunities_Model extends CI_Model
{
    public function upsert_opportunity($data)
    {
        $opportunityId = isset($data['opportunity_id']) ? trim((string) $data['opportunity_id']) : '';

        if ($opportunityId === '') {
            return false;
        }

        $existing = $this->db
            ->select('id')
            ->from('ghl_opportunities')
            ->where('opportunity_id', $opportunityId)
            ->limit(1)
            ->get()
            ->row_array();

        $columns = array(
            'opportunity_id',
            'location_id',
            'contact_id',
            'assigned_to',
            'name',
            'monetary_value',
            'pipeline_id',
            'pipeline_stage_id',
            'pipeline_stage_uid',
            'status',
            'source',
            'lost_reason_id',
            'contact_name',
            'company_name',
            'contact_email',
            'contact_phone',
            'last_status_change_at',
            'last_stage_change_at',
            'opportunity_created_at',
            'opportunity_updated_at',
            'custom_fields_json',
            'followers_json',
            'relations_json',
            'contact_json',
            'contact_tags_json',
            'contact_score_json',
            'sort_json',
            'attributions_json',
            'raw_json',
        );

        $insert = array();
        foreach ($columns as $column) {
            $insert[$column] = array_key_exists($column, $data) ? $data[$column] : null;
        }

        if (!empty($existing['id'])) {
            $updated = $this->db
                ->where('id', (int) $existing['id'])
                ->update('ghl_opportunities', array(
                    'location_id' => $insert['location_id'],
                    'contact_id' => $insert['contact_id'],
                    'assigned_to' => $insert['assigned_to'],
                    'name' => $insert['name'],
                    'monetary_value' => $insert['monetary_value'],
                    'pipeline_id' => $insert['pipeline_id'],
                    'pipeline_stage_id' => $insert['pipeline_stage_id'],
                    'pipeline_stage_uid' => $insert['pipeline_stage_uid'],
                    'status' => $insert['status'],
                    'source' => $insert['source'],
                    'lost_reason_id' => $insert['lost_reason_id'],
                    'contact_name' => $insert['contact_name'],
                    'company_name' => $insert['company_name'],
                    'contact_email' => $insert['contact_email'],
                    'contact_phone' => $insert['contact_phone'],
                    'last_status_change_at' => $insert['last_status_change_at'],
                    'last_stage_change_at' => $insert['last_stage_change_at'],
                    'opportunity_created_at' => $insert['opportunity_created_at'],
                    'opportunity_updated_at' => $insert['opportunity_updated_at'],
                    'custom_fields_json' => $insert['custom_fields_json'],
                    'followers_json' => $insert['followers_json'],
                    'relations_json' => $insert['relations_json'],
                    'contact_json' => $insert['contact_json'],
                    'contact_tags_json' => $insert['contact_tags_json'],
                    'contact_score_json' => $insert['contact_score_json'],
                    'sort_json' => $insert['sort_json'],
                    'attributions_json' => $insert['attributions_json'],
                    'raw_json' => $insert['raw_json'],
                ));

            return $updated ? 'updated' : false;
        }

        $inserted = $this->db->insert('ghl_opportunities', $insert);
        return $inserted ? 'inserted' : false;
    }
}
