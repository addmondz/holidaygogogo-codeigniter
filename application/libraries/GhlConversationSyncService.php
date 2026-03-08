<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class GhlConversationSyncService
{
    protected $CI;

    public function __construct()
    {
        $this->CI =& get_instance();
        $this->CI->load->model('Ghl_Sync_Model');
    }

    public function syncDaily($options = array())
    {
        $config = $this->getConfig();
        $this->validateConfig($config);

        $syncKey = 'ghl_conversations_' . $config['location_id'];
        $runId = 'ghl_' . date('YmdHis') . '_' . substr(md5(uniqid('', true)), 0, 8);

        // Resume from last synced position: only fetch conversations after max LastMessageDate
        $maxLastMessageDate = $this->CI->Ghl_Sync_Model->get_max_last_message_date_for_location($config['location_id']);
        $startAfterDate = $maxLastMessageDate !== null ? $this->mysqlDateTimeToStartAfterDate($maxLastMessageDate) : null;

        $batchNumber = 0;
        $pulled = 0;
        $inserted = 0;
        $updated = 0;
        $deduplicated = 0;
        $seenConversationIds = array();
        $maxBatches = (int) $config['max_pages_per_run'];

        $this->logEvent($runId, $syncKey, 'RUN_START', $startAfterDate !== null ? 'GHL conversation sync started (resuming from last run)' : 'GHL conversation sync started (full pull)', array('startAfterDate' => $startAfterDate));

        try {
            do {
                $batchNumber++;
                $response = $this->requestConversations($config, $startAfterDate);
                $this->logInFile(
                    'API_RESPONSE: ' . json_encode($response, JSON_PRETTY_PRINT),
                    ['batch' => $batchNumber, 'startAfterDate' => $startAfterDate]
                );
                if ($response['status'] >= 400) {
                    $this->logEvent($runId, $syncKey, 'API_ERROR', 'GHL API error', array(
                        'http_status' => (int) $response['status'],
                        'error' => (string) $response['error'],
                        'batch' => (int) $batchNumber
                    ));
                    throw new Exception('GHL API error: HTTP ' . $response['status'] . ' - ' . $response['error']);
                }

                $conversations = $this->extractConversations($response['body']);
                if (empty($conversations)) {
                    $this->logEvent($runId, $syncKey, 'BATCH_EMPTY', 'No conversations returned; sync complete', array(
                        'batch' => (int) $batchNumber
                    ));
                    break;
                }

                $pageInserted = 0;
                $pageUpdated = 0;
                $pageDeduplicated = 0;
                foreach ($conversations as $conversation) {
                    $normalized = $this->normalizeConversation($conversation, $config['location_id'], $runId);
                    if (!$normalized) {
                        continue;
                    }

                    $pulled++;
                    $conversationId = isset($normalized['ConversationID']) ? trim((string) $normalized['ConversationID']) : '';

                    if ($conversationId !== '') {
                        if (isset($seenConversationIds[$conversationId])) {
                            $deduplicated++;
                            $pageDeduplicated++;
                            continue;
                        }
                        $seenConversationIds[$conversationId] = true;
                    }

                    $writeResult = $this->CI->Ghl_Sync_Model->upsert_conversation_by_conversation_id($normalized);
                    if ($writeResult === 'inserted') {
                        $inserted++;
                        $pageInserted++;
                    } elseif ($writeResult === 'updated') {
                        $updated++;
                        $pageUpdated++;
                    }
                }

                $startAfterDate = $this->getLastMessageDateFromConversation(end($conversations));

                $this->logEvent($runId, $syncKey, 'BATCH_SYNCED', 'Synced conversation batch', array(
                    'batch' => (int) $batchNumber,
                    'pulled_count' => count($conversations),
                    'inserted_count' => (int) $pageInserted,
                    'updated_count' => (int) $pageUpdated,
                    'deduplicated_count' => (int) $pageDeduplicated,
                    'startAfterDate' => $startAfterDate
                ));

                if ($batchNumber >= $maxBatches) {
                    $this->logEvent($runId, $syncKey, 'MAX_BATCHES', 'Reached max batches per run', array('batch' => (int) $batchNumber));
                    break;
                }
            } while (!empty($conversations));

            $this->logEvent($runId, $syncKey, 'RUN_SUCCESS', 'GHL conversation sync completed', array(
                'pulled_count' => (int) $pulled,
                'inserted_count' => (int) $inserted,
                'updated_count' => (int) $updated,
                'deduplicated_count' => (int) $deduplicated,
                'batches_processed' => (int) $batchNumber
            ));

            return array(
                'ok' => true,
                'run_id' => $runId,
                'sync_key' => $syncKey,
                'pulled' => $pulled,
                'inserted' => $inserted,
                'updated' => $updated,
                'deduplicated' => $deduplicated,
                'batches_processed' => $batchNumber
            );
        } catch (Exception $e) {
            $this->logEvent($runId, $syncKey, 'RUN_FAILED', 'GHL conversation sync failed', array(
                'error' => $e->getMessage(),
                'pulled_count' => (int) $pulled,
                'inserted_count' => (int) $inserted,
                'updated_count' => (int) $updated,
                'deduplicated_count' => (int) $deduplicated,
                'batch' => (int) $batchNumber
            ));

            return array(
                'ok' => false,
                'run_id' => $runId,
                'error' => $e->getMessage()
            );
        }
    }

    protected function getConfig()
    {
        return array(
            'base_url' => 'https://services.leadconnectorhq.com',
            'search_path' => '/conversations/search',
            'token' => (string) get_env('GHL_API_TOKEN'),
            'api_version' => (string) (get_env('GHL_API_VERSION') ?: '2021-07-28'),
            'location_id' => (string) get_env('GHL_LOCATION_ID'),
            'page_size' => (int) (get_env('GHL_PAGE_SIZE') ?: 100),
            'max_pages_per_run' => (int) (get_env('GHL_MAX_PAGES_PER_RUN') ?: 50)
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

    protected function logEvent($runId, $syncKey, $type, $message, $meta = array())
    {
        $record = array(
            'RunID' => (string) $runId,
            'SyncKey' => (string) $syncKey,
            'LogType' => (string) $type,
            'Message' => (string) $message
        );

        if (isset($meta['page'])) {
            $record['Page'] = (int) $meta['page'];
        }
        if (isset($meta['batch'])) {
            $record['Page'] = (int) $meta['batch'];
        }
        if (isset($meta['pulled_count'])) {
            $record['PulledCount'] = (int) $meta['pulled_count'];
        }
        if (isset($meta['inserted_count'])) {
            $record['InsertedCount'] = (int) $meta['inserted_count'];
        }
        if (isset($meta['http_status'])) {
            $record['HttpStatus'] = (int) $meta['http_status'];
        }
        if (!empty($meta)) {
            $record['Meta'] = json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        $this->CI->Ghl_Sync_Model->create_log($record);
    }

    protected function logInFile($message, $meta = array())
    {
        $log_file = 'ghl_conversation_sync.log';
        $log_path = APPPATH . 'logs/';
        $log_file = $log_path . $log_file;
        $log_entry = date('Y-m-d H:i:s') . ' ' . $message . ' ' . json_encode($meta) . "\n";
        file_put_contents($log_file, $log_entry, FILE_APPEND);
    }

    protected function requestConversations($config, $startAfterDate = null)
    {
        $query = array(
            'locationId' => $config['location_id'],
            'limit' => $config['page_size']
        );
        if ($startAfterDate !== null && $startAfterDate !== '') {
            $query['startAfterDate'] = $startAfterDate;
        }

        $url = rtrim($config['base_url'], '/') . $config['search_path'] . '?' . http_build_query($query);

        $headers = array(
            'Authorization: Bearer ' . $config['token'],
            'Version: ' . $config['api_version'],
            'Content-Type: application/json'
        );

        return $this->curlRequest('GET', $url, array(), $headers);
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

    protected function extractConversations($body)
    {
        if (isset($body['conversations']) && is_array($body['conversations'])) {
            return $body['conversations'];
        }

        if (isset($body['data']['conversations']) && is_array($body['data']['conversations'])) {
            return $body['data']['conversations'];
        }

        if (isset($body['data']) && is_array($body['data']) && array_keys($body['data']) === range(0, count($body['data']) - 1)) {
            return $body['data'];
        }

        if (array_keys($body) === range(0, count($body) - 1)) {
            return $body;
        }

        return array();
    }

    protected function normalizeConversation($conversation, $locationId, $runId)
    {
        $conversationId = $this->getValue($conversation, array('id', '_id', 'conversationId', 'conversation_id'));
        $contactId = $this->getValue($conversation, array('contactId', 'contact_id'));
        $apiLocationId = $this->getValue($conversation, array('locationId', 'location_id'));

        if (empty($contactId) && isset($conversation['contact']) && is_array($conversation['contact'])) {
            $contactId = $this->getValue($conversation['contact'], array('id', '_id', 'contactId'));
        }

        $dateAddedRaw = $this->getValue($conversation, array('dateAdded'));
        $dateUpdatedRaw = $this->getValue($conversation, array('dateUpdated'));
        $lastMessageDateRaw = $this->getValue($conversation, array('lastMessageDate'));
        $lastInboundWhatsappDateRaw = $this->getValue($conversation, array('lastInboundWhatsappMessageDate'));
        $lastManualMessageDateRaw = $this->getValue($conversation, array('lastManualMessageDate'));

        return array(
            'RunID' => (string) $runId,
            'ConversationID' => $conversationId ? (string) $conversationId : null,
            'LocationID' => $apiLocationId ? (string) $apiLocationId : (string) $locationId,
            'DateAdded' => $this->toMysqlDateTime($dateAddedRaw),
            'DateUpdated' => $this->toMysqlDateTime($dateUpdatedRaw),
            'LastMessageDate' => $this->toMysqlDateTime($lastMessageDateRaw),
            'LastInboundWhatsappMessageDate' => $this->toMysqlDateTime($lastInboundWhatsappDateRaw),
            'LastMessageType' => $this->getValue($conversation, array('lastMessageType')),
            'LastMessageBody' => $this->extractLastMessageBody($conversation),
            'LastMessageDirection' => $this->getValue($conversation, array('lastMessageDirection')),
            'Inbox' => $this->toTinyInt($this->getValue($conversation, array('inbox'))),
            'UnreadCount' => (int) ($this->getValue($conversation, array('unreadCount')) ?: 0),
            'AssignedTo' => $this->getValue($conversation, array('assignedTo')),
            'LastManualMessageDate' => $this->toMysqlDateTime($lastManualMessageDateRaw),
            'Followers' => $this->toJsonText($this->getValue($conversation, array('followers'))),
            'IsLastMessageInternalComment' => $this->toTinyInt($this->getValue($conversation, array('isLastMessageInternalComment'))),
            'ContactID' => $contactId ? (string) $contactId : null,
            'FullName' => $this->getValue($conversation, array('fullName')),
            'ContactName' => $this->getValue($conversation, array('contactName')),
            'CompanyName' => $this->getValue($conversation, array('companyName')),
            'Phone' => $this->getValue($conversation, array('phone')),
            'Tags' => $this->toJsonText($this->getValue($conversation, array('tags'))),
            'Type' => $this->getValue($conversation, array('type')),
            'Scoring' => $this->toJsonText($this->getValue($conversation, array('scoring'))),
            'Attributed' => $this->toJsonText($this->getValue($conversation, array('attributed'))),
            'Sort' => $this->toJsonText($this->getValue($conversation, array('sort')))
        );
    }

    protected function extractLastMessageBody($conversation)
    {
        $direct = $this->getValue($conversation, array('lastMessageBody', 'last_message_body', 'message', 'preview'));
        if (!empty($direct)) {
            return $direct;
        }

        if (isset($conversation['lastMessage']) && is_array($conversation['lastMessage'])) {
            $nested = $this->getValue($conversation['lastMessage'], array('body', 'text', 'message'));
            if (!empty($nested)) {
                return $nested;
            }
        }

        return null;
    }

    /**
     * Convert MySQL datetime (from our DB) to startAfterDate value for the API.
     * GHL typically accepts Unix milliseconds.
     */
    protected function mysqlDateTimeToStartAfterDate($mysqlDateTime)
    {
        if (empty($mysqlDateTime)) {
            return '';
        }
        $ts = strtotime($mysqlDateTime);
        if ($ts === false) {
            return '';
        }
        return (string) ($ts * 1000);
    }

    /**
     * Get lastMessageDate from a raw conversation for use as startAfterDate on the next request.
     * Returns the value as the API sent it (ISO string or timestamp); empty string if not present.
     */
    protected function getLastMessageDateFromConversation($conversation)
    {
        if (!is_array($conversation)) {
            return '';
        }
        $raw = $this->getValue($conversation, array('lastMessageDate', 'last_message_date', 'dateUpdated', 'date_updated'));
        if ($raw === null || $raw === '') {
            return '';
        }
        return (string) $raw;
    }

    protected function getValue($array, $keys)
    {
        foreach ($keys as $key) {
            if (isset($array[$key]) && $array[$key] !== '') {
                return $array[$key];
            }
        }

        return null;
    }

    protected function toMysqlDateTime($value)
    {
        if (empty($value)) {
            return null;
        }

        if (is_numeric($value)) {
            $value = (int) $value;
            if ($value > 9999999999) {
                $value = (int) floor($value / 1000);
            }
            return date('Y-m-d H:i:s', $value);
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    protected function toTinyInt($value)
    {
        if ($value === null || $value === '') {
            return 0;
        }

        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        return ((int) $value) ? 1 : 0;
    }

    protected function toJsonText($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            return null;
        }

        return $encoded;
    }
}
