<?php
defined('BASEPATH') or exit('No direct script access allowed');

class GhlOpportunitiesSyncService
{
    protected $CI;

    public function __construct()
    {
        $this->CI = &get_instance();
        $this->CI->load->model('Ghl_Sync_Model');
        $this->CI->load->model('Ghl_Opportunities_Model');
    }

    public function sync($options = array())
    {
        $config = $this->getConfig();
        $this->validateConfig($config);

        $mode = isset($options['mode']) ? strtolower(trim((string) $options['mode'])) : 'recent';
        if ($mode !== 'full') {
            $mode = 'recent';
        }

        $syncKey = 'ghl_opportunities_' . $config['location_id'];
        $moduleName = 'ghl_opportunities';
        $runId = $this->CI->Ghl_Sync_Model->generate_run_id($moduleName);
        $cutoff = $this->buildCutoffDateTime($config['days_back']);

        $this->logEvent($runId, $moduleName, array(
            'full_sync' => $mode === 'full' ? 1 : 0,
            'status' => 'running',
            'total_page' => 0,
            'total_data' => 0,
            'pulled_count' => 0,
            'updated_count' => 0,
        ));

        $page = 0;
        $pulledTotal = 0;
        $updatedTotal = 0;
        $insertedTotal = 0;
        $apiTotal = null;
        $nextPageUrl = null;

        try {
            do {
                $page++;
                $response = $this->requestOpportunities($config, $nextPageUrl);

                if ($response['status'] >= 400) {
                    $this->logEvent($runId, $moduleName, array(
                        'full_sync' => $mode === 'full' ? 1 : 0,
                        'status' => 'running',
                        'total_page' => (int) $page,
                        'total_data' => $apiTotal !== null ? (int) $apiTotal : 0,
                        'pulled_count' => (int) $pulledTotal,
                        'updated_count' => (int) $updatedTotal,
                    ));
                    throw new Exception('GHL API error: HTTP ' . $response['status'] . ' - ' . $response['error']);
                }

                $opportunities = $this->extractOpportunities($response['body']);
                $pulled = count($opportunities);
                $pulledTotal += $pulled;

                if ($apiTotal === null) {
                    $apiTotal = $this->extractApiTotal($response['body'], $pulled);
                }

                if ($pulled === 0) {
                    break;
                }

                foreach ($opportunities as $opportunity) {
                    $normalized = $this->normalizeOpportunity($opportunity);
                    if (!$normalized) {
                        continue;
                    }

                    $writeResult = $this->CI->Ghl_Opportunities_Model->upsert_opportunity($normalized);
                    if ($writeResult === 'inserted') {
                        $insertedTotal++;
                    } elseif ($writeResult === 'updated') {
                        $updatedTotal++;
                    }
                }

                $lastOpportunity = end($opportunities);
                $nextPageUrl = isset($response['body']['meta']['nextPageUrl']) ? (string) $response['body']['meta']['nextPageUrl'] : null;

                $this->logEvent($runId, $moduleName, array(
                    'full_sync' => $mode === 'full' ? 1 : 0,
                    'status' => 'running',
                    'total_page' => (int) $page,
                    'total_data' => $apiTotal !== null ? (int) $apiTotal : 0,
                    'pulled_count' => (int) $pulledTotal,
                    'updated_count' => (int) $updatedTotal,
                ));

                if ($mode !== 'full' && $this->shouldStopAfterPage($lastOpportunity, $cutoff)) {
                    break;
                }
            } while (!empty($nextPageUrl));

            $this->logEvent($runId, $moduleName, array(
                'full_sync' => $mode === 'full' ? 1 : 0,
                'status' => 'completed',
                'total_page' => (int) $page,
                'total_data' => $apiTotal !== null ? (int) $apiTotal : 0,
                'pulled_count' => (int) $pulledTotal,
                'updated_count' => (int) $updatedTotal,
                'completed_at' => date('Y-m-d H:i:s'),
            ));

            return array(
                'ok' => true,
                'run_id' => $runId,
                'module_name' => $moduleName,
                'mode' => $mode,
                'sync_key' => $syncKey,
                'total_page' => (int) $page,
                'total_data' => $apiTotal !== null ? (int) $apiTotal : 0,
                'pulled_count' => (int) $pulledTotal,
                'inserted_count' => (int) $insertedTotal,
                'updated_count' => (int) $updatedTotal,
                'page_limit' => (int) $config['page_limit'],
                'days_back' => (int) $config['days_back'],
                'cutoff_datetime' => $cutoff->format('Y-m-d H:i:s'),
            );
        } catch (Exception $e) {
            $this->logEvent($runId, $moduleName, array(
                'full_sync' => $mode === 'full' ? 1 : 0,
                'status' => 'failed',
                'total_page' => (int) $page,
                'total_data' => $apiTotal !== null ? (int) $apiTotal : 0,
                'pulled_count' => (int) $pulledTotal,
                'updated_count' => (int) $updatedTotal,
            ));

            return array(
                'ok' => false,
                'run_id' => $runId,
                'module_name' => $moduleName,
                'sync_key' => $syncKey,
                'mode' => $mode,
                'error' => $e->getMessage(),
            );
        }
    }

    protected function getConfig()
    {
        return array(
            'base_url' => 'https://services.leadconnectorhq.com',
            'opportunities_path' => '/opportunities/search',
            'token' => (string) get_env('GHL_API_TOKEN'),
            'api_version' => (string) (get_env('GHL_API_VERSION') ?: '2021-07-28'),
            'location_id' => (string) get_env('GHL_LOCATION_ID'),
            'page_limit' => (int) (get_env('GHL_PAGE_SIZE') ?: 100),
            'days_back' => (int) (get_env('GHL_OPPORTUNITIES_SYNC_DAYS') ?: 3),
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

        if ((int) $config['page_limit'] <= 0) {
            throw new Exception('GHL_PAGE_SIZE must be greater than 0');
        }

        if ((int) $config['days_back'] < 0) {
            throw new Exception('GHL_OPPORTUNITIES_SYNC_DAYS cannot be negative');
        }
    }

    protected function requestOpportunities($config, $nextPageUrl = null)
    {
        if ($nextPageUrl) {
            $url = $nextPageUrl;
        } else {
            $query = array(
                'location_id' => $config['location_id'],
                'limit' => $config['page_limit'],
            );

            $url = rtrim($config['base_url'], '/') . $config['opportunities_path'] . '?' . http_build_query($query);
        }

        $headers = array(
            'Accept: application/json',
            'Authorization: Bearer ' . $config['token'],
            'Version: ' . $config['api_version'],
        );

        return $this->curlRequest('GET', $url, array(), $headers);
    }

    protected function extractOpportunities($body)
    {
        return isset($body['opportunities']) && is_array($body['opportunities']) ? $body['opportunities'] : array();
    }

    protected function extractApiTotal($body, $fallback = null)
    {
        $candidates = array(
            isset($body['total']) ? $body['total'] : null,
            isset($body['count']) ? $body['count'] : null,
            isset($body['meta']['total']) ? $body['meta']['total'] : null,
            isset($body['meta']['count']) ? $body['meta']['count'] : null,
            isset($body['meta']['totalCount']) ? $body['meta']['totalCount'] : null,
            isset($body['meta']['records']) ? $body['meta']['records'] : null,
        );

        foreach ($candidates as $candidate) {
            if ($candidate !== null && $candidate !== '' && is_numeric($candidate)) {
                return (int) $candidate;
            }
        }

        return $fallback !== null ? (int) $fallback : null;
    }

    protected function normalizeOpportunity($opportunity)
    {
        if (!is_array($opportunity)) {
            return null;
        }

        $opportunityId = isset($opportunity['id']) ? trim((string) $opportunity['id']) : '';
        if ($opportunityId === '') {
            return null;
        }

        $relation = $this->extractPrimaryRelation($opportunity);
        $contact = isset($opportunity['contact']) && is_array($opportunity['contact']) ? $opportunity['contact'] : array();

        return array(
            'opportunity_id' => $opportunityId,
            'location_id' => isset($opportunity['locationId']) ? (string) $opportunity['locationId'] : null,
            'contact_id' => isset($opportunity['contactId']) ? (string) $opportunity['contactId'] : null,
            'assigned_to' => isset($opportunity['assignedTo']) && $opportunity['assignedTo'] !== '' ? (string) $opportunity['assignedTo'] : null,
            'name' => isset($opportunity['name']) ? (string) $opportunity['name'] : null,
            'monetary_value' => $this->normalizeDecimal(isset($opportunity['monetaryValue']) ? $opportunity['monetaryValue'] : null),
            'pipeline_id' => isset($opportunity['pipelineId']) ? (string) $opportunity['pipelineId'] : null,
            'pipeline_stage_id' => isset($opportunity['pipelineStageId']) ? (string) $opportunity['pipelineStageId'] : null,
            'pipeline_stage_uid' => isset($opportunity['pipelineStageUId']) ? (string) $opportunity['pipelineStageUId'] : null,
            'status' => isset($opportunity['status']) ? (string) $opportunity['status'] : null,
            'source' => isset($opportunity['source']) ? (string) $opportunity['source'] : null,
            'lost_reason_id' => isset($opportunity['lostReasonId']) && $opportunity['lostReasonId'] !== '' ? (string) $opportunity['lostReasonId'] : null,
            'contact_name' => isset($relation['contactName']) ? (string) $relation['contactName'] : (isset($contact['name']) ? (string) $contact['name'] : null),
            'company_name' => isset($relation['companyName']) ? (string) $relation['companyName'] : (isset($contact['companyName']) ? (string) $contact['companyName'] : null),
            'contact_email' => isset($relation['email']) ? (string) $relation['email'] : null,
            'contact_phone' => isset($relation['phone']) ? (string) $relation['phone'] : (isset($contact['phone']) ? (string) $contact['phone'] : null),
            'last_status_change_at' => $this->normalizeUtcDateTime(isset($opportunity['lastStatusChangeAt']) ? $opportunity['lastStatusChangeAt'] : null),
            'last_stage_change_at' => $this->normalizeUtcDateTime(isset($opportunity['lastStageChangeAt']) ? $opportunity['lastStageChangeAt'] : null),
            'opportunity_created_at' => $this->normalizeUtcDateTime(isset($opportunity['createdAt']) ? $opportunity['createdAt'] : null),
            'opportunity_updated_at' => $this->normalizeUtcDateTime(isset($opportunity['updatedAt']) ? $opportunity['updatedAt'] : null),
            'custom_fields_json' => $this->encodeJson(isset($opportunity['customFields']) ? $opportunity['customFields'] : null),
            'followers_json' => $this->encodeJson(isset($opportunity['followers']) ? $opportunity['followers'] : null),
            'relations_json' => $this->encodeJson(isset($opportunity['relations']) ? $opportunity['relations'] : null),
            'contact_json' => $this->encodeJson($contact),
            'contact_tags_json' => $this->encodeJson(isset($contact['tags']) ? $contact['tags'] : null),
            'contact_score_json' => $this->encodeJson(isset($contact['score']) ? $contact['score'] : null),
            'sort_json' => $this->encodeJson(isset($opportunity['sort']) ? $opportunity['sort'] : null),
            'attributions_json' => $this->encodeJson(isset($opportunity['attributions']) ? $opportunity['attributions'] : null),
            'raw_json' => $this->encodeJson($opportunity),
        );
    }

    protected function extractPrimaryRelation($opportunity)
    {
        $relations = isset($opportunity['relations']) && is_array($opportunity['relations']) ? $opportunity['relations'] : array();

        foreach ($relations as $relation) {
            if (!is_array($relation)) {
                continue;
            }

            if (!empty($relation['primary'])) {
                return $relation;
            }
        }

        return isset($relations[0]) && is_array($relations[0]) ? $relations[0] : array();
    }

    protected function logEvent($runId, $moduleName, $meta = array())
    {
        $record = array(
            'RunID' => (string) $runId,
            'module_name' => (string) $moduleName,
        );

        if (isset($meta['total_page'])) {
            $record['total_page'] = (int) $meta['total_page'];
        }

        if (isset($meta['total_data'])) {
            $record['total_data'] = (int) $meta['total_data'];
        }

        if (isset($meta['full_sync'])) {
            $record['full_sync'] = !empty($meta['full_sync']) ? 1 : 0;
        }

        if (isset($meta['status'])) {
            $record['status'] = (string) $meta['status'];
        }

        if (isset($meta['pulled_count'])) {
            $record['pulled_count'] = (int) $meta['pulled_count'];
        }

        if (isset($meta['updated_count'])) {
            $record['updated_count'] = (int) $meta['updated_count'];
        }

        if (isset($meta['completed_at'])) {
            $record['completed_at'] = (string) $meta['completed_at'];
        }

        $this->CI->Ghl_Sync_Model->create_log($record);
    }

    protected function buildCutoffDateTime($daysBack)
    {
        $daysBack = (int) $daysBack;
        $timezone = new DateTimeZone(date_default_timezone_get());

        return (new DateTimeImmutable('now', $timezone))
            ->setTime(0, 0, 0)
            ->modify('-' . $daysBack . ' days');
    }

    protected function shouldStopAfterPage($lastOpportunity, DateTimeImmutable $cutoff)
    {
        if (!is_array($lastOpportunity)) {
            return false;
        }

        $rawUpdatedAt = isset($lastOpportunity['updatedAt']) ? $lastOpportunity['updatedAt'] : null;
        if (empty($rawUpdatedAt)) {
            $rawUpdatedAt = isset($lastOpportunity['createdAt']) ? $lastOpportunity['createdAt'] : null;
        }

        if (empty($rawUpdatedAt)) {
            return false;
        }

        try {
            $opportunityDate = new DateTimeImmutable((string) $rawUpdatedAt);
            $opportunityDate = $opportunityDate
                ->setTimezone($cutoff->getTimezone())
                ->setTime(0, 0, 0);

            return $opportunityDate <= $cutoff;
        } catch (Exception $e) {
            return false;
        }
    }

    protected function normalizeUtcDateTime($value)
    {
        if (empty($value)) {
            return null;
        }

        try {
            $date = new DateTime((string) $value);
            $date->setTimezone(new DateTimeZone('UTC'));
            return $date->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            return null;
        }
    }

    protected function normalizeDecimal($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }

    protected function encodeJson($value)
    {
        if ($value === null) {
            return null;
        }

        $encoded = json_encode($value);
        return $encoded !== false ? $encoded : null;
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
