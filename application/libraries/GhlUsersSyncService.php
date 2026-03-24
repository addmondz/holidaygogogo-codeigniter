<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class GhlUsersSyncService
{
    protected $CI;

    public function __construct()
    {
        $this->CI =& get_instance();
        $this->CI->load->model('Ghl_Sync_Model');
        $this->CI->load->model('Ghl_Users_Model');
    }

    /**
     * Sync GHL users for the configured location (single request; API returns all users).
     *
     * @param array $options Reserved for future use (e.g. location_id override)
     * @return array { ok, run_id, sync_key, pulled, inserted, updated, error? }
     */
    public function sync($options = array())
    {
        $config = $this->getConfig();
        $this->validateConfig($config);

        $syncKey = 'ghl_users_' . $config['location_id'];
        $moduleName = 'ghl_users';
        $runId = $this->CI->Ghl_Sync_Model->generate_run_id($moduleName);

        $this->logEvent($runId, $moduleName, array(
            'total_page' => 0,
            'pulled_count' => 0,
            'updated_count' => 0,
        ));

        $pulled = 0;
        $inserted = 0;
        $updated = 0;

        try {
            $response = $this->requestUsers($config);

            if ($response['status'] >= 400) {
                $this->logEvent($runId, $moduleName, array(
                    'total_page' => 0,
                    'pulled_count' => (int) $pulled,
                    'updated_count' => (int) $updated,
                ));
                throw new Exception('GHL API error: HTTP ' . $response['status'] . ' - ' . $response['error']);
            }

            $users = $this->extractUsers($response['body']);
            $pulled = count($users);

            foreach ($users as $user) {
                $normalized = $this->normalizeUser($user, $config['location_id'], $runId);
                if (!$normalized) {
                    continue;
                }

                $writeResult = $this->CI->Ghl_Users_Model->upsert_user($normalized);
                if ($writeResult === 'inserted') {
                    $inserted++;
                } elseif ($writeResult === 'updated') {
                    $updated++;
                }
            }

            $this->logEvent($runId, $moduleName, array(
                'total_page' => 1,
                'pulled_count' => (int) $pulled,
                'updated_count' => (int) $updated
            ));

            return array(
                'ok' => true,
                'run_id' => $runId,
                'module_name' => $moduleName,
                'sync_key' => $syncKey,
                'total_page' => 1,
                'pulled' => $pulled,
                'updated' => $updated,
                'pulled_count' => $pulled,
                'updated_count' => $updated
            );
        } catch (Exception $e) {
            $this->logEvent($runId, $moduleName, array(
                'total_page' => 0,
                'pulled_count' => (int) $pulled,
                'updated_count' => (int) $updated
            ));

            return array(
                'ok' => false,
                'run_id' => $runId,
                'module_name' => $moduleName,
                'sync_key' => $syncKey,
                'error' => $e->getMessage()
            );
        }
    }

    protected function getConfig()
    {
        return array(
            'base_url' => 'https://services.leadconnectorhq.com',
            'users_path' => '/users/',
            'token' => (string) get_env('GHL_API_TOKEN'),
            'api_version' => (string) (get_env('GHL_API_VERSION') ?: '2021-07-28'),
            'location_id' => (string) get_env('GHL_LOCATION_ID')
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

    protected function logEvent($runId, $moduleName, $meta = array())
    {
        $record = array(
            'RunID' => (string) $runId,
            'module_name' => (string) $moduleName
        );
        if (isset($meta['total_page'])) {
            $record['total_page'] = (int) $meta['total_page'];
        }
        if (isset($meta['pulled_count'])) {
            $record['pulled_count'] = (int) $meta['pulled_count'];
        }
        if (isset($meta['updated_count'])) {
            $record['updated_count'] = (int) $meta['updated_count'];
        }
        $this->CI->Ghl_Sync_Model->create_log($record);
    }

    /**
     * GET users list for location.
     */
    protected function requestUsers($config)
    {
        $query = array('locationId' => $config['location_id']);
        $url = rtrim($config['base_url'], '/') . $config['users_path'] . '?' . http_build_query($query);

        $headers = array(
            'Accept: application/json',
            'Authorization: Bearer ' . $config['token'],
            'Version: ' . $config['api_version']
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

    protected function extractUsers($body)
    {
        if (isset($body['users']) && is_array($body['users'])) {
            return $body['users'];
        }
        if (isset($body['data']['users']) && is_array($body['data']['users'])) {
            return $body['data']['users'];
        }
        return array();
    }

    /**
     * Normalize one user from API response to ghl_users row.
     */
    protected function normalizeUser($user, $locationId, $runId)
    {
        if (!is_array($user)) {
            return null;
        }

        $userId = isset($user['id']) ? trim((string) $user['id']) : '';
        if ($userId === '') {
            return null;
        }

        $roles = isset($user['roles']) && is_array($user['roles']) ? $user['roles'] : array();
        $roleType = isset($roles['type']) ? (string) $roles['type'] : null;
        $roleName = isset($roles['role']) ? (string) $roles['role'] : null;
        $locationIds = isset($roles['locationIds']) && is_array($roles['locationIds'])
            ? json_encode($roles['locationIds'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            : null;
        $restrictSubAccount = isset($roles['restrictSubAccount']) ? ($roles['restrictSubAccount'] ? 1 : 0) : 0;

        $lcPhone = isset($user['lcPhone']) && is_array($user['lcPhone'])
            ? json_encode($user['lcPhone'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            : null;

        $deleted = isset($user['deleted']) ? ($user['deleted'] ? 1 : 0) : 0;
        $invitedForMobileApp = isset($user['invitedForMobileApp']) ? ($user['invitedForMobileApp'] ? 1 : 0) : 0;

        return array(
            'RunID' => (string) $runId,
            'UserID' => $userId,
            'LocationID' => (string) $locationId,
            'Name' => isset($user['name']) ? (string) $user['name'] : null,
            'FirstName' => isset($user['firstName']) ? (string) $user['firstName'] : null,
            'LastName' => isset($user['lastName']) ? (string) $user['lastName'] : null,
            'Email' => isset($user['email']) ? (string) $user['email'] : null,
            'Phone' => isset($user['phone']) ? (string) $user['phone'] : null,
            'Extension' => isset($user['extension']) ? (string) $user['extension'] : null,
            'Deleted' => $deleted,
            'RoleType' => $roleType,
            'RoleName' => $roleName,
            'RestrictSubAccount' => $restrictSubAccount,
            'LocationIds' => $locationIds,
            'LcPhone' => $lcPhone,
            'ProfilePhoto' => isset($user['profilePhoto']) ? (string) $user['profilePhoto'] : null,
            'InvitedForMobileApp' => $invitedForMobileApp,
            'FreshdeskContactId' => isset($user['freshdeskContactId']) ? (string) $user['freshdeskContactId'] : null,
            'MembershipContactId' => isset($user['membershipContactId']) ? (string) $user['membershipContactId'] : null,
        );
    }
}
