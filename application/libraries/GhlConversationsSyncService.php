<?php
defined('BASEPATH') or exit('No direct script access allowed');

class GhlConversationsSyncService
{
    protected $CI;

    public function __construct()
    {
        $this->CI = &get_instance();
        $this->CI->load->model('Ghl_Sync_Model');
        $this->CI->load->model('Ghl_Conversations_Model');
    }

    public function sync($options = array())
    {
        $config = $this->getConfig();
        $this->validateConfig($config);

        $mode = isset($options['mode']) ? strtolower(trim((string) $options['mode'])) : 'recent';
        if ($mode !== 'full') {
            $mode = 'recent';
        }

        $syncKey = 'ghl_conversations_' . $config['location_id'];
        $moduleName = 'ghl_conversations';
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
        $startAfterDate = null;

        try {
            do {
                $page++;
                $response = $this->requestConversations($config, $startAfterDate);

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

                $conversations = $this->extractConversations($response['body']);
                $pulled = count($conversations);
                $pulledTotal += $pulled;

                if ($apiTotal === null && isset($response['body']['total'])) {
                    $apiTotal = (int) $response['body']['total'];
                }
                if ($apiTotal === null) {
                    $apiTotal = $this->extractApiTotal($response['body'], $pulled);
                }

                if ($pulled === 0) {
                    break;
                }

                foreach ($conversations as $conversation) {
                    $normalized = $this->normalizeConversation($conversation);
                    if (!$normalized) {
                        continue;
                    }

                    $writeResult = $this->CI->Ghl_Conversations_Model->upsert_conversation($normalized);
                    if ($writeResult === 'inserted') {
                        $insertedTotal++;
                    } elseif ($writeResult === 'updated') {
                        $updatedTotal++;
                    }
                }

                $lastConversation = end($conversations);
                $startAfterDate = $this->extractCursor($lastConversation);

                $this->logEvent($runId, $moduleName, array(
                    'full_sync' => $mode === 'full' ? 1 : 0,
                    'status' => 'running',
                    'total_page' => (int) $page,
                    'total_data' => $apiTotal !== null ? (int) $apiTotal : 0,
                    'pulled_count' => (int) $pulledTotal,
                    'updated_count' => (int) $updatedTotal,
                ));

                if ($mode !== 'full' && $this->shouldStopAfterPage($lastConversation, $cutoff)) {
                    break;
                }

                if ($startAfterDate === null) {
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
            'conversations_path' => '/conversations/search',
            'token' => (string) get_env('GHL_API_TOKEN'),
            'api_version' => (string) (get_env('GHL_API_VERSION') ?: '2021-07-28'),
            'location_id' => (string) get_env('GHL_LOCATION_ID'),
            'page_limit' => (int) (get_env('GHL_PAGE_SIZE') ?: 100),
            'days_back' => (int) (get_env('GHL_CONVERSATIONS_SYNC_DAYS') ?: 3),
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
            throw new Exception('GHL_CONVERSATIONS_SYNC_DAYS cannot be negative');
        }
    }

    protected function requestConversations($config, $startAfterDate = null)
    {
        $query = array(
            'locationId' => $config['location_id'],
            'limit' => $config['page_limit'],
            'sortBy' => 'last_message_date',
            'sort' => 'desc',
        );

        if ($startAfterDate !== null && $startAfterDate !== '') {
            $query['startAfterDate'] = $startAfterDate;
        }

        $url = rtrim($config['base_url'], '/') . $config['conversations_path'] . '?' . http_build_query($query);

        $headers = array(
            'Accept: application/json',
            'Authorization: Bearer ' . $config['token'],
            'Version: ' . $config['api_version'],
        );

        return $this->curlRequest('GET', $url, array(), $headers);
    }

    protected function extractConversations($body)
    {
        return isset($body['conversations']) && is_array($body['conversations']) ? $body['conversations'] : array();
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

    protected function normalizeConversation($conversation)
    {
        if (!is_array($conversation)) {
            return null;
        }

        $conversationId = isset($conversation['id']) ? trim((string) $conversation['id']) : '';
        if ($conversationId === '') {
            return null;
        }

        return array(
            'conversation_id' => $conversationId,
            'location_id' => isset($conversation['locationId']) ? (string) $conversation['locationId'] : null,
            'contact_id' => isset($conversation['contactId']) ? (string) $conversation['contactId'] : null,
            'assigned_to' => isset($conversation['assignedTo']) ? (string) $conversation['assignedTo'] : null,
            'full_name' => isset($conversation['fullName']) ? (string) $conversation['fullName'] : null,
            'contact_name' => isset($conversation['contactName']) ? (string) $conversation['contactName'] : null,
            'company_name' => isset($conversation['companyName']) ? (string) $conversation['companyName'] : null,
            'phone' => isset($conversation['phone']) ? (string) $conversation['phone'] : null,
            'conversation_type' => isset($conversation['type']) ? (string) $conversation['type'] : null,
            'inbox' => !empty($conversation['inbox']) ? 1 : 0,
            'unread_count' => isset($conversation['unreadCount']) ? (int) $conversation['unreadCount'] : 0,
            'last_message_type' => isset($conversation['lastMessageType']) ? (string) $conversation['lastMessageType'] : null,
            'last_message_body' => isset($conversation['lastMessageBody']) ? (string) $conversation['lastMessageBody'] : null,
            'last_message_direction' => isset($conversation['lastMessageDirection']) ? (string) $conversation['lastMessageDirection'] : null,
            'last_outbound_message_action' => isset($conversation['lastOutboundMessageAction']) ? (string) $conversation['lastOutboundMessageAction'] : null,
            'last_internal_comment' => isset($conversation['lastInternalComment']) ? (string) $conversation['lastInternalComment'] : null,
            'is_last_message_internal_comment' => !empty($conversation['isLastMessageInternalComment']) ? 1 : 0,
            'date_added' => $this->normalizeTimestampMs(isset($conversation['dateAdded']) ? $conversation['dateAdded'] : null),
            'date_updated' => $this->normalizeTimestampMs(isset($conversation['dateUpdated']) ? $conversation['dateUpdated'] : null),
            'last_message_date' => $this->normalizeTimestampMs(isset($conversation['lastMessageDate']) ? $conversation['lastMessageDate'] : null),
            'last_inbound_whatsapp_message_date' => $this->normalizeTimestampMs(isset($conversation['lastInboundWhatsappMessageDate']) ? $conversation['lastInboundWhatsappMessageDate'] : null),
            'last_manual_message_date' => $this->normalizeTimestampMs(isset($conversation['lastManualMessageDate']) ? $conversation['lastManualMessageDate'] : null),
            'followers_json' => $this->encodeJson(isset($conversation['followers']) ? $conversation['followers'] : null),
            'mentions_json' => $this->encodeJson(isset($conversation['mentions']) ? $conversation['mentions'] : null),
            'tags_json' => $this->encodeJson(isset($conversation['tags']) ? $conversation['tags'] : null),
            'scoring_json' => $this->encodeJson(isset($conversation['scoring']) ? $conversation['scoring'] : null),
            'sort_json' => $this->encodeJson(isset($conversation['sort']) ? $conversation['sort'] : null),
            'attributed_json' => $this->encodeJson(isset($conversation['attributed']) ? $conversation['attributed'] : null),
            // 'raw_json' => $this->encodeJson($conversation),
        );
    }

    protected function extractCursor($conversation)
    {
        if (!is_array($conversation) || !isset($conversation['lastMessageDate'])) {
            return null;
        }

        $cursor = (string) $conversation['lastMessageDate'];
        return $cursor === '' ? null : $cursor;
    }

    protected function shouldStopAfterPage($lastConversation, DateTimeImmutable $cutoff)
    {
        if (!is_array($lastConversation) || !isset($lastConversation['lastMessageDate'])) {
            return false;
        }

        $date = $this->timestampMsToDateTimeImmutable($lastConversation['lastMessageDate'], $cutoff->getTimezone());
        if ($date === null) {
            return false;
        }

        return $date->setTime(0, 0, 0) <= $cutoff;
    }

    protected function buildCutoffDateTime($daysBack)
    {
        $daysBack = (int) $daysBack;
        $timezone = new DateTimeZone(date_default_timezone_get());

        return (new DateTimeImmutable('now', $timezone))
            ->setTime(0, 0, 0)
            ->modify('-' . $daysBack . ' days');
    }

    protected function normalizeTimestampMs($value)
    {
        $date = $this->timestampMsToDateTimeImmutable($value, new DateTimeZone('UTC'));
        return $date ? $date->format('Y-m-d H:i:s') : null;
    }

    protected function timestampMsToDateTimeImmutable($value, DateTimeZone $timezone)
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            return null;
        }

        try {
            $seconds = ((float) $value) / 1000;
            $date = DateTimeImmutable::createFromFormat('U.u', number_format($seconds, 3, '.', ''), new DateTimeZone('UTC'));
            if (!$date) {
                $date = new DateTimeImmutable('@' . (int) floor($seconds));
            }

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
            $error = isset($decoded['message']) ? $decoded['message'] : 'HTTP error';
        }

        return array(
            'status' => $status,
            'body' => $decoded,
            'error' => $error,
        );
    }
}
