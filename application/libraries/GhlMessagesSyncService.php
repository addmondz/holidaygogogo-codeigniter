<?php
defined('BASEPATH') or exit('No direct script access allowed');

class GhlMessagesSyncService
{
    protected $CI;

    public function __construct()
    {
        $this->CI = &get_instance();
        $this->CI->load->model('Ghl_Sync_Model');
        $this->CI->load->model('Ghl_Messages_Model');
    }

    public function sync($options = array())
    {
        $config = $this->getConfig();
        $this->validateConfig($config);

        $mode = isset($options['mode']) ? strtolower(trim((string) $options['mode'])) : 'recent';
        if ($mode !== 'full') {
            $mode = 'recent';
        }

        $syncKey = 'ghl_messages_' . $config['location_id'];
        $moduleName = 'ghl_messages';
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
        $cursor = null;

        try {
            do {
                $page++;
                $response = $this->requestMessages($config, $cursor);

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

                $messages = $this->extractMessages($response['body']);
                $pulled = count($messages);
                $pulledTotal += $pulled;

                if ($apiTotal === null) {
                    $apiTotal = $this->extractApiTotal($response['body'], $pulled);
                }

                if ($pulled === 0) {
                    break;
                }

                foreach ($messages as $message) {
                    $normalized = $this->normalizeMessage($message);
                    if (!$normalized) {
                        continue;
                    }

                    $writeResult = $this->CI->Ghl_Messages_Model->upsert_message($normalized);
                    if ($writeResult === 'inserted') {
                        $insertedTotal++;
                    } elseif ($writeResult === 'updated') {
                        $updatedTotal++;
                    }
                }

                $lastMessage = end($messages);
                $cursor = isset($response['body']['nextCursor']) ? (string) $response['body']['nextCursor'] : null;

                $this->logEvent($runId, $moduleName, array(
                    'full_sync' => $mode === 'full' ? 1 : 0,
                    'status' => 'running',
                    'total_page' => (int) $page,
                    'total_data' => $apiTotal !== null ? (int) $apiTotal : 0,
                    'pulled_count' => (int) $pulledTotal,
                    'updated_count' => (int) $updatedTotal,
                ));

                if ($mode !== 'full' && $this->shouldStopAfterPage($lastMessage, $cutoff)) {
                    break;
                }

                if ($cursor === null || $cursor === '') {
                    break;
                }
            } while (true);

            $this->logEvent($runId, $moduleName, array(
                'full_sync' => $mode === 'full' ? 1 : 0,
                'status' => 'completed',
                'total_page' => (int) $page,
                'total_data' => $apiTotal !== null ? (int) $apiTotal : 0,
                'pulled_count' => (int) $pulledTotal,
                'updated_count' => (int) $updatedTotal,
                'completed_at' => $this->getCodeDateTime(),
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
                'api_total' => $apiTotal,
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
            'messages_path' => '/conversations/messages/export',
            'token' => (string) get_env('GHL_API_TOKEN'),
            'api_version' => (string) (get_env('GHL_API_VERSION') ?: '2021-07-28'),
            'location_id' => (string) get_env('GHL_LOCATION_ID'),
            'page_limit' => (int) (get_env('GHL_PAGE_SIZE') ?: 100),
            'days_back' => (int) (get_env('GHL_MESSAGES_SYNC_DAYS') ?: 3),
            'channel' => trim((string) get_env('GHL_MESSAGES_CHANNEL')),
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

        if ((int) $config['page_limit'] < 10) {
            throw new Exception('GHL_PAGE_SIZE must be at least 10 for messages export');
        }

        if ((int) $config['days_back'] < 0) {
            throw new Exception('GHL_MESSAGES_SYNC_DAYS cannot be negative');
        }
    }

    protected function requestMessages($config, $cursor = null)
    {
        $query = array(
            'locationId' => $config['location_id'],
            'limit' => $config['page_limit'],
        );

        if (!empty($config['channel'])) {
            $query['channel'] = $config['channel'];
        }

        if ($cursor !== null && $cursor !== '') {
            $query['cursor'] = $cursor;
        }

        $url = rtrim($config['base_url'], '/') . $config['messages_path'] . '?' . http_build_query($query);

        $headers = array(
            'Accept: application/json',
            'Authorization: Bearer ' . $config['token'],
            'Version: ' . $config['api_version'],
        );

        return $this->curlRequest('GET', $url, array(), $headers);
    }

    protected function extractMessages($body)
    {
        return isset($body['messages']) && is_array($body['messages']) ? $body['messages'] : array();
    }

    protected function extractApiTotal($body, $fallback = null)
    {
        $candidates = array(
            isset($body['total']) ? $body['total'] : null,
            isset($body['count']) ? $body['count'] : null,
            isset($body['meta']['total']) ? $body['meta']['total'] : null,
            isset($body['meta']['count']) ? $body['meta']['count'] : null,
            isset($body['meta']['totalCount']) ? $body['meta']['totalCount'] : null,
        );

        foreach ($candidates as $candidate) {
            if ($candidate !== null && $candidate !== '' && is_numeric($candidate)) {
                return (int) $candidate;
            }
        }

        return $fallback !== null ? (int) $fallback : null;
    }

    protected function normalizeMessage($message)
    {
        if (!is_array($message)) {
            return null;
        }

        $messageId = isset($message['id']) ? trim((string) $message['id']) : '';
        if ($messageId === '') {
            return null;
        }

        $meta = $message;
        unset($meta['attachments']);

        return array(
            'message_id' => $messageId,
            'location_id' => isset($message['locationId']) ? (string) $message['locationId'] : null,
            'conversation_id' => isset($message['conversationId']) ? (string) $message['conversationId'] : null,
            'contact_id' => isset($message['contactId']) ? (string) $message['contactId'] : null,
            'user_id' => isset($message['userId']) ? (string) $message['userId'] : null,
            'alt_id' => isset($message['altId']) ? (string) $message['altId'] : null,
            'direction' => isset($message['direction']) ? (string) $message['direction'] : null,
            'status' => isset($message['status']) ? (string) $message['status'] : null,
            'message_type_code' => isset($message['type']) && $message['type'] !== '' ? (int) $message['type'] : null,
            'message_type' => isset($message['messageType']) ? (string) $message['messageType'] : null,
            'content_type' => isset($message['contentType']) ? (string) $message['contentType'] : null,
            'body' => isset($message['body']) ? (string) $message['body'] : null,
            'from_number' => isset($message['from']) ? (string) $message['from'] : null,
            'to_number' => isset($message['to']) ? (string) $message['to'] : null,
            'date_added' => $this->normalizeUtcDateTime(isset($message['dateAdded']) ? $message['dateAdded'] : null),
            'date_updated' => $this->normalizeUtcDateTime(isset($message['dateUpdated']) ? $message['dateUpdated'] : null),
            'attachments_json' => $this->encodeJson(isset($message['attachments']) ? $message['attachments'] : null),
            // 'meta_json' => $this->encodeJson($meta),
            // 'raw_json' => $this->encodeJson($message),
        );
    }

    protected function shouldStopAfterPage($lastMessage, DateTimeImmutable $cutoff)
    {
        if (!is_array($lastMessage)) {
            return false;
        }

        $date = $this->parseUtcDateTime(isset($lastMessage['dateAdded']) ? $lastMessage['dateAdded'] : null, $cutoff->getTimezone());
        if ($date === null) {
            return false;
        }

        return $date->setTime(0, 0, 0) <= $cutoff;
    }

    protected function buildCutoffDateTime($daysBack)
    {
        $daysBack = (int) $daysBack;
        $timezone = $this->getCodeTimezone();

        return (new DateTimeImmutable('now', $timezone))
            ->setTime(0, 0, 0)
            ->modify('-' . $daysBack . ' days');
    }

    protected function normalizeUtcDateTime($value)
    {
        $date = $this->parseUtcDateTime($value, $this->getCodeTimezone());
        return $date ? $date->format('Y-m-d H:i:s') : null;
    }

    protected function getCodeDateTime($format = 'Y-m-d H:i:s')
    {
        return (new DateTimeImmutable('now', $this->getCodeTimezone()))->format($format);
    }

    protected function getCodeTimezone()
    {
        return new DateTimeZone('Asia/Kuala_Lumpur');
    }

    protected function parseUtcDateTime($value, DateTimeZone $timezone)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        try {
            $date = new DateTimeImmutable($value, new DateTimeZone('UTC'));
            return $date->setTimezone($timezone);
        } catch (Exception $e) {
            return null;
        }
    }

    protected function encodeJson($value)
    {
        if ($value === null) {
            return null;
        }

        $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return $json === false ? null : $json;
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
            if (isset($decoded['message'])) {
                $error = is_array($decoded['message'])
                    ? implode('; ', $decoded['message'])
                    : (string) $decoded['message'];
            } else {
                $error = 'HTTP error';
            }
        }

        return array(
            'status' => $status,
            'body' => $decoded,
            'error' => $error,
        );
    }
}
