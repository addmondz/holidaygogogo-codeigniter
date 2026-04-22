<?php
defined('BASEPATH') or exit('No direct script access allowed');

class GhlContactsSyncService
{
    protected $CI;
    protected $lastSyncMeta = array();

    public function __construct()
    {
        $this->CI = &get_instance();
        $this->CI->load->model('Ghl_Sync_Model');
        $this->CI->load->model('Ghl_Contacts_Model');
    }

    public function logInfoInFile($message, $meta = array())
    {
        logInFile('GHL_CONTACTS_SYNC', $message, $meta);
    }

    public function sync($options = array())
    {
        $config = $this->getConfig();
        $this->validateConfig($config);
        $mode = isset($options['mode']) ? strtolower(trim((string) $options['mode'])) : 'recent';
        if ($mode !== 'full') {
            $mode = 'recent';
        }

        $syncKey = 'ghl_contacts_' . $config['location_id'];
        $moduleName = 'ghl_contacts';
        $runId = $this->CI->Ghl_Sync_Model->generate_run_id($moduleName);

        $cutoff = $this->buildCutoffDateTime($config['days_back']);

        $this->lastSyncMeta = array(
            'cutoff_datetime' => $cutoff->format('Y-m-d H:i:s'),
            'cutoff_date' => $cutoff->format('Y-m-d'),
        );

        $this->logEvent($runId, $moduleName, array(
            'full_sync' => $mode === 'full' ? 1 : 0,
            'status' => 'running',
            'mode' => $mode,
            'days_back' => (int) $config['days_back'],
            'page_limit' => (int) $config['page_limit'],
            'cutoff_datetime' => $this->lastSyncMeta['cutoff_datetime'],
            'cutoff_date' => $this->lastSyncMeta['cutoff_date'],
        ));

        return $this->fetchContacts($config, $runId, $moduleName, $syncKey, $cutoff, $mode);
    }

    protected function fetchContacts($config, $runId, $moduleName, $syncKey, DateTimeImmutable $cutoff, $mode)
    {
        $page = 0;
        $pulledTotal = 0;
        $insertedTotal = 0;
        $updatedTotal = 0;
        $apiTotal = null;
        $nextPageUrl = null;

        try {
            do {
                $page++;
                $response = $this->requestContacts($config, $nextPageUrl);

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

                $contacts = $this->extractContacts($response['body']);
                $pulled = count($contacts);
                $pulledTotal += $pulled;
                if ($apiTotal === null) {
                    $apiTotal = $this->extractApiTotal($response['body'], $pulled);
                }

                if ($pulled === 0) {
                    break; // no more data
                }

                $inserted = 0;
                $updated = 0;

                foreach ($contacts as $contact) {
                    $normalized = $this->normalizeContact($contact);
                    if (!$normalized) {
                        continue;
                    }

                    $writeResult = $this->CI->Ghl_Contacts_Model->upsert_contact($normalized);
                    if ($writeResult === 'inserted') {
                        $inserted++;
                        $insertedTotal++;
                    } elseif ($writeResult === 'updated') {
                        $updated++;
                        $updatedTotal++;
                    }
                }

                $lastContact = end($contacts);
                $lastContactId = isset($lastContact['id']) ? $lastContact['id'] : (isset($lastContact['_id']) ? $lastContact['_id'] : null);
                $lastContactDateAdded = $this->normalizeUtcDateTime(
                    isset($lastContact['dateAdded']) ? $lastContact['dateAdded'] : null
                );

                $this->logEvent($runId, $moduleName, array(
                    'full_sync' => $mode === 'full' ? 1 : 0,
                    'status' => 'running',
                    'total_page' => (int) $page,
                    'total_data' => $apiTotal !== null ? (int) $apiTotal : 0,
                    'pulled_count' => (int) $pulledTotal,
                    'updated_count' => (int) $updatedTotal,
                ));

                if ($mode !== 'full' && $this->shouldStopAfterPage($lastContact, $cutoff)) {
                    $this->logEvent($runId, $moduleName, array(
                        'full_sync' => $mode === 'full' ? 1 : 0,
                        'status' => 'running',
                        'total_page' => (int) $page,
                        'total_data' => $apiTotal !== null ? (int) $apiTotal : 0,
                        'pulled_count' => (int) $pulledTotal,
                        'updated_count' => (int) $updatedTotal,
                    ));

                    break;
                }

                $nextPageUrl = $response['body']['meta']['nextPageUrl'] ?? null;
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
                'updated_count' => (int) $updatedTotal,
                'page_limit' => (int) $config['page_limit'],
                'days_back' => (int) $config['days_back'],
                'cutoff_datetime' => $cutoff->format('Y-m-d H:i:s'),
                'cutoff_date' => $cutoff->format('Y-m-d'),
            );
        } catch (Exception $e) {
            $this->logEvent($runId, $moduleName, array(
                'full_sync' => $mode === 'full' ? 1 : 0,
                'status' => 'failed',
                'total_page' => (int) $page,
                'total_data' => $apiTotal !== null ? (int) $apiTotal : 0,
                'pulled_count' => (int) $pulledTotal,
                'updated_count' => (int) $updatedTotal
            ));

            return array(
                'ok' => false,
                'run_id' => $runId,
                'module_name' => $moduleName,
                'sync_key' => $syncKey,
                'mode' => $mode,
                'error' => $e->getMessage()
            );
        }
    }

    protected function getConfig()
    {
        return array(
            'base_url' => 'https://services.leadconnectorhq.com',
            'contacts_path' => '/contacts/',
            'token' => (string) get_env('GHL_API_TOKEN'),
            'api_version' => (string) (get_env('GHL_API_VERSION') ?: '2021-07-28'),
            'location_id' => (string) get_env('GHL_LOCATION_ID'),
            'page_limit' => (int) (get_env('GHL_PAGE_SIZE') ?: 100),
            'days_back' => (int) (get_env('GHL_CONTACTS_SYNC_DAYS') ?: 3),
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
            throw new Exception('GHL_CONTACTS_SYNC_DAYS cannot be negative');
        }
    }

    protected function requestContacts($config, $nextPageUrl = null)
    {
        $query = array(
            'locationId' => $config['location_id'],
            'limit' => $config['page_limit'],
        );

        if ($nextPageUrl) {
            $url = $nextPageUrl;
        } else {
            $url = rtrim($config['base_url'], '/') . $config['contacts_path'] . '?' . http_build_query($query);
        }

        $headers = array(
            'Accept: application/json',
            'Authorization: Bearer ' . $config['token'],
            'Version: ' . $config['api_version']
        );

        return $this->curlRequest('GET', $url, array(), $headers);
    }

    protected function extractContacts($body)
    {
        return isset($body['contacts']) && is_array($body['contacts']) ? $body['contacts'] : array();
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

    protected function normalizeContact($contact)
    {
        if (!is_array($contact)) {
            return null;
        }

        $contactId = isset($contact['id']) ? trim((string) $contact['id']) : '';
        if ($contactId === '' && !empty($contact['_id'])) {
            $contactId = trim((string) $contact['_id']);
        }

        if ($contactId === '') {
            return null;
        }

        $assignedTo = null;
        if (!empty($contact['assignedTo'])) {
            $assignedTo = (string) $contact['assignedTo'];
        } elseif (!empty($contact['assignedToUserId'])) {
            $assignedTo = (string) $contact['assignedToUserId'];
        }

        return array(
            'contact_id' => $contactId,
            'first_name' => isset($contact['firstName']) ? (string) $contact['firstName'] : null,
            'last_name' => isset($contact['lastName']) ? (string) $contact['lastName'] : null,
            'email' => isset($contact['email']) ? (string) $contact['email'] : null,
            'phone' => isset($contact['phone']) ? (string) $contact['phone'] : null,
            'assigned_to' => $assignedTo,
            'date_added' => $this->normalizeUtcDateTime(
                isset($contact['dateAdded']) ? $contact['dateAdded'] : null
            ),
        );
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

    protected function shouldStopAfterPage($lastContact, DateTimeImmutable $cutoff)
    {
        if (!is_array($lastContact)) {
            return false;
        }

        $rawDateAdded = isset($lastContact['dateAdded']) ? $lastContact['dateAdded'] : null;
        if (empty($rawDateAdded)) {
            return false;
        }

        try {
            $contactDate = new DateTimeImmutable((string) $rawDateAdded);
            $contactDate = $contactDate
                ->setTimezone($cutoff->getTimezone())
                ->setTime(0, 0, 0);

            return $contactDate <= $cutoff;
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
            $date = new DateTimeImmutable((string) $value, new DateTimeZone('UTC'));
            $date = $date->setTimezone(new DateTimeZone(date_default_timezone_get()));
            return $date->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            return null;
        }
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
            'error' => $error
        );
    }
}
