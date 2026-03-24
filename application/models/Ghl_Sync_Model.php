<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Ghl_Sync_Model extends CI_Model
{
    protected $runLogColumns = null;

    public function generate_run_id($moduleName)
    {
        $moduleName = trim((string) $moduleName);
        if ($moduleName === '') {
            return '';
        }

        $timestamp = date('Ymd-His');
        $baseRunId = $moduleName . '_' . $timestamp;
        $runId = $baseRunId;
        $suffix = 1;

        while ($this->run_id_exists($runId)) {
            $suffix++;
            $runId = $baseRunId . '_' . $suffix;
        }

        return $runId;
    }

    public function create_log($data)
    {
        $runId = isset($data['RunID']) ? trim((string) $data['RunID']) : '';
        $moduleName = isset($data['module_name']) ? trim((string) $data['module_name']) : '';

        if ($runId === '' || $moduleName === '') {
            return 0;
        }

        $payload = array(
            'RunID' => $runId,
            'module_name' => $moduleName,
            'total_page' => isset($data['total_page']) ? (int) $data['total_page'] : 0,
            'total_data' => isset($data['total_data']) ? (int) $data['total_data'] : 0,
            'full_sync' => isset($data['full_sync']) ? ((int) !empty($data['full_sync'])) : 0,
            'status' => $this->normalize_run_log_status(isset($data['status']) ? $data['status'] : null),
            'pulled_count' => isset($data['pulled_count']) ? (int) $data['pulled_count'] : 0,
            'updated_count' => isset($data['updated_count']) ? (int) $data['updated_count'] : 0,
        );
        $payload = $this->filter_run_log_payload($payload);

        $existing = $this->db
            ->select('id')
            ->from('ghl_sync_run_log')
            ->where('RunID', $runId)
            ->limit(1)
            ->get()
            ->row_array();

        if (!empty($existing['id'])) {
            $this->db
                ->where('id', (int) $existing['id'])
                ->update('ghl_sync_run_log', $payload);

            return (int) $existing['id'];
        }

        $this->db->insert('ghl_sync_run_log', $payload);
        return (int) $this->db->insert_id();
    }

    protected function run_id_exists($runId)
    {
        return $this->db
            ->select('id')
            ->from('ghl_sync_run_log')
            ->where('RunID', (string) $runId)
            ->limit(1)
            ->get()
            ->num_rows() > 0;
    }

    protected function filter_run_log_payload($payload)
    {
        $columns = $this->get_run_log_columns();
        if (empty($columns)) {
            return $payload;
        }

        $filtered = array();
        foreach ($payload as $field => $value) {
            if (isset($columns[$field])) {
                $filtered[$field] = $value;
            }
        }

        return $filtered;
    }

    protected function normalize_run_log_status($status)
    {
        $status = strtolower(trim((string) $status));
        $allowed = array(
            'pending' => true,
            'running' => true,
            'completed' => true,
            'failed' => true,
        );

        return isset($allowed[$status]) ? $status : 'pending';
    }

    protected function get_run_log_columns()
    {
        if (is_array($this->runLogColumns)) {
            return $this->runLogColumns;
        }

        $this->runLogColumns = array();

        $fields = $this->db->list_fields('ghl_sync_run_log');
        if (!is_array($fields)) {
            return $this->runLogColumns;
        }

        foreach ($fields as $field) {
            $this->runLogColumns[$field] = true;
        }

        return $this->runLogColumns;
    }
}
