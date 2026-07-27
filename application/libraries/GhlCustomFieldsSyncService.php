<?php
defined('BASEPATH') or exit('No direct script access allowed');

class GhlCustomFieldsSyncService
{
    protected $CI;

    public function __construct()
    {
        $this->CI = &get_instance();
        $this->CI->load->model('Ghl_Sync_Model');
        $this->CI->load->model('Ghl_Custom_Fields_Model');
    }

    public function sync($options = array())
    {
        $config = $this->getConfig();
        $this->validateConfig($config);

        $model = isset($options['model']) ? strtolower(trim((string) $options['model'])) : 'contact';
        if (!in_array($model, array('contact', 'opportunity', 'all'), true)) {
            $model = 'contact';
        }

        $moduleName = 'ghl_custom_fields';
        $syncKey = $moduleName . '_' . $config['location_id'] . '_' . $model;
        $runId = $this->CI->Ghl_Sync_Model->generate_run_id($moduleName);

        $this->logEvent($runId, $moduleName, array(
            'full_sync' => 1,
            'status' => 'running',
            'total_page' => 0,
            'total_data' => 0,
            'pulled_count' => 0,
            'updated_count' => 0,
        ));

        $pulled = 0;
        $inserted = 0;
        $updated = 0;
        $apiTotal = null;

        try {
            $response = $this->requestCustomFields($config, $model);
            if ($response['status'] >= 400) {
                throw new Exception('GHL API error: HTTP ' . $response['status'] . ' - ' . $response['error']);
            }

            $fields = $this->extractFields($response['body']);
            $pulled = count($fields);
            $apiTotal = $this->extractApiTotal($response['body'], $pulled);

            foreach ($fields as $field) {
                $normalized = $this->normalizeField($field, $config['location_id'], $model);
                if (!$normalized) {
                    continue;
                }

                $writeResult = $this->CI->Ghl_Custom_Fields_Model->upsert_field($normalized);
                if ($writeResult === 'inserted') {
                    $inserted++;
                } elseif ($writeResult === 'updated') {
                    $updated++;
                }
            }

            $this->logEvent($runId, $moduleName, array(
                'full_sync' => 1,
                'status' => 'completed',
                'total_page' => 1,
                'total_data' => $apiTotal !== null ? (int) $apiTotal : 0,
                'pulled_count' => (int) $pulled,
                'updated_count' => (int) $updated,
                'completed_at' => $this->getCodeDateTime(),
            ));

            return array(
                'ok' => true,
                'run_id' => $runId,
                'module_name' => $moduleName,
                'sync_key' => $syncKey,
                'model' => $model,
                'total_page' => 1,
                'total_data' => $apiTotal !== null ? (int) $apiTotal : 0,
                'pulled' => $pulled,
                'inserted' => $inserted,
                'updated' => $updated,
                'pulled_count' => $pulled,
                'updated_count' => $updated,
            );
        } catch (Exception $e) {
            $this->logEvent($runId, $moduleName, array(
                'full_sync' => 1,
                'status' => 'failed',
                'total_page' => 0,
                'total_data' => $apiTotal !== null ? (int) $apiTotal : 0,
                'pulled_count' => (int) $pulled,
                'updated_count' => (int) $updated,
            ));

            return array(
                'ok' => false,
                'run_id' => $runId,
                'module_name' => $moduleName,
                'sync_key' => $syncKey,
                'model' => $model,
                'error' => $e->getMessage(),
            );
        }
    }

    protected function getConfig()
    {
        return array(
            'base_url' => 'https://services.leadconnectorhq.com',
            'custom_fields_path' => '/locations/%s/customFields',
            'token' => (string) get_env('GHL_API_TOKEN'),
            'api_version' => (string) (get_env('GHL_CUSTOM_FIELDS_API_VERSION') ?: 'v3'),
            'location_id' => (string) get_env('GHL_LOCATION_ID'),
        );
    }

    protected function validateConfig($config)
    {
        if (empty($config['token'])) {
            throw new Exception('Missing GHL_API_TOKEN in .env');
        }
        if (empty($config['location_id'])) {
            throw new Exception('Missing GHL_LOCATION_ID in .env');
        }
    }

    protected function requestCustomFields($config, $model)
    {
        $path = sprintf($config['custom_fields_path'], rawurlencode($config['location_id']));
        $url = rtrim($config['base_url'], '/') . $path . '?' . http_build_query(array('model' => $model));

        $headers = array(
            'Accept: application/json',
            'Authorization: Bearer ' . $config['token'],
            'Version: ' . $config['api_version'],
        );

        return $this->curlRequest('GET', $url, array(), $headers);
    }

    protected function extractFields($body)
    {
        if (isset($body['customFields']) && is_array($body['customFields'])) {
            return $body['customFields'];
        }
        if (isset($body['fields']) && is_array($body['fields'])) {
            return $body['fields'];
        }
        return array();
    }

    protected function extractApiTotal($body, $fallback)
    {
        foreach (array('total', 'count', 'totalCount') as $key) {
            if (isset($body[$key]) && is_numeric($body[$key])) {
                return (int) $body[$key];
            }
        }
        return (int) $fallback;
    }

    protected function normalizeField($field, $locationId, $fallbackModel)
    {
        if (!is_array($field)) {
            return null;
        }

        $fieldId = $this->nullableString(isset($field['id']) ? $field['id'] : (isset($field['_id']) ? $field['_id'] : null));
        if ($fieldId === null) {
            return null;
        }

        return array(
            'location_id' => $this->nullableString(isset($field['locationId']) ? $field['locationId'] : $locationId),
            'field_id' => $fieldId,
            'field_key' => $this->nullableString(isset($field['fieldKey']) ? $field['fieldKey'] : null),
            'model' => $this->nullableString(isset($field['model']) ? $field['model'] : $fallbackModel),
            'name' => $this->nullableString(isset($field['name']) ? $field['name'] : null),
            'data_type' => $this->nullableString(isset($field['dataType']) ? $field['dataType'] : null),
            'placeholder' => $this->nullableString(isset($field['placeholder']) ? $field['placeholder'] : null),
            'description' => $this->nullableString(isset($field['description']) ? $field['description'] : null),
            'position' => isset($field['position']) && is_numeric($field['position']) ? (int) $field['position'] : null,
            'show_in_forms' => !empty($field['showInForms']) ? 1 : 0,
            'parent_id' => $this->nullableString(isset($field['parentId']) ? $field['parentId'] : null),
            'object_key' => $this->nullableString(isset($field['objectKey']) ? $field['objectKey'] : null),
            'options_json' => $this->jsonOrNull(isset($field['options']) ? $field['options'] : null),
            'raw_json' => $this->jsonOrNull($field),
            'date_added' => $this->normalizeUtcDateTime(isset($field['dateAdded']) ? $field['dateAdded'] : null),
            'date_updated' => $this->normalizeUtcDateTime(isset($field['dateUpdated']) ? $field['dateUpdated'] : null),
        );
    }

    protected function logEvent($runId, $moduleName, $meta = array())
    {
        $record = array(
            'RunID' => (string) $runId,
            'module_name' => (string) $moduleName,
        );

        foreach (array('total_page', 'total_data', 'pulled_count', 'updated_count') as $key) {
            if (isset($meta[$key])) {
                $record[$key] = (int) $meta[$key];
            }
        }
        if (isset($meta['full_sync'])) {
            $record['full_sync'] = !empty($meta['full_sync']) ? 1 : 0;
        }
        if (isset($meta['status'])) {
            $record['status'] = (string) $meta['status'];
        }
        if (isset($meta['completed_at'])) {
            $record['completed_at'] = (string) $meta['completed_at'];
        }

        $this->CI->Ghl_Sync_Model->create_log($record);
    }

    protected function nullableString($value)
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    protected function jsonOrNull($value)
    {
        if ($value === null) {
            return null;
        }

        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $json === false ? null : $json;
    }

    protected function normalizeUtcDateTime($value)
    {
        if (empty($value)) {
            return null;
        }

        try {
            $date = new DateTimeImmutable((string) $value, new DateTimeZone('UTC'));
            return $date->setTimezone($this->getCodeTimezone())->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            return null;
        }
    }

    protected function getCodeDateTime($format = 'Y-m-d H:i:s')
    {
        return (new DateTimeImmutable('now', $this->getCodeTimezone()))->format($format);
    }

    protected function getCodeTimezone()
    {
        return new DateTimeZone('Asia/Kuala_Lumpur');
    }

    protected function curlRequest($method, $url, $payload = array(), $headers = array())
    {
        $ch = curl_init();

        if (strtoupper($method) === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        } elseif (strtoupper($method) !== 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
            if (!empty($payload)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            }
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = null;

        if (curl_errno($ch)) {
            $error = curl_error($ch);
        }

        curl_close($ch);

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            $decoded = array();
        }

        if ($error === null && $status >= 400) {
            $error = isset($decoded['message']) ? $decoded['message'] : 'HTTP error';
        }

        return array(
            'status' => $status,
            'body' => $decoded,
            'error' => $error,
        );
    }
}
