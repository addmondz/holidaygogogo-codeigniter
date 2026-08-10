<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Powerbi
{
    protected $CI;
    protected $tenant_id;
    protected $client_id;
    protected $client_secret;
    protected $workspace_id;
    protected $dataset_id;
    protected $report_id;
    protected $embed_url;
    protected $access_token;
    protected $token_expiry;

    public function __construct()
    {
        $this->CI =& get_instance();
        $this->CI->config->load('powerbi', TRUE);

        $this->tenant_id = $this->CI->config->item('tenant_id', 'powerbi');
        $this->client_id = $this->CI->config->item('client_id', 'powerbi');
        $this->client_secret = $this->CI->config->item('client_secret', 'powerbi');
        $this->workspace_id = $this->CI->config->item('workspace_id', 'powerbi');
        $this->dataset_id = $this->CI->config->item('dataset_id', 'powerbi');
        $this->report_id = $this->CI->config->item('report_id', 'powerbi');
        $this->embed_url = $this->CI->config->item('embed_url', 'powerbi');
    }

    public function isConfigured()
    {
        return $this->hasCredentials()
            && !empty($this->workspace_id)
            && !empty($this->dataset_id);
    }

    public function isWorkspaceConfigured()
    {
        return $this->hasCredentials() && !empty($this->workspace_id);
    }

    public function getDatasetId()
    {
        return $this->dataset_id;
    }

    public function getWorkspaceId()
    {
        return $this->workspace_id;
    }

    public function getReportId()
    {
        return $this->report_id;
    }

    public function getTokenExpiry()
    {
        return $this->token_expiry;
    }

    public function getCreateEmbedUrl()
    {
        return 'https://app.powerbi.com/reportEmbed?groupId=' . $this->workspace_id;
    }

    public function getCreateEmbedToken()
    {
        $url = 'https://api.powerbi.com/v1.0/myorg/GenerateToken';
        $response = $this->request('POST', $url, array(
            'datasets' => array(
                array('id' => $this->dataset_id),
            ),
            'targetWorkspaces' => array(
                array('id' => $this->workspace_id),
            ),
        ));

        if (empty($response['token'])) {
            throw new Exception('Unable to generate Power BI create-report embed token.');
        }

        if (!empty($response['expiration'])) {
            $this->token_expiry = $response['expiration'];
        }

        return $response['token'];
    }

    public function listReports()
    {
        $url = 'https://api.powerbi.com/v1.0/myorg/groups/' . $this->workspace_id . '/reports';
        $response = $this->request('GET', $url);

        return !empty($response['value']) && is_array($response['value'])
            ? $response['value']
            : array();
    }

    public function getReport($report_id = null)
    {
        $report_id = !empty($report_id) ? $report_id : $this->report_id;

        if (empty($report_id)) {
            throw new Exception('Power BI report ID is required.');
        }

        if (!empty($this->embed_url) && $report_id === $this->report_id) {
            return array(
                'id' => $this->report_id,
                'embedUrl' => $this->embed_url,
                'datasetId' => $this->dataset_id,
                'name' => '',
            );
        }

        $url = 'https://api.powerbi.com/v1.0/myorg/groups/' . $this->workspace_id . '/reports/' . $report_id;
        $response = $this->request('GET', $url);

        if (empty($response['embedUrl'])) {
            throw new Exception('Unable to retrieve Power BI report embed URL.');
        }

        return $response;
    }

    public function getReportEmbedToken($report_id, $dataset_id = null, $access_level = 'View')
    {
        $report_id = !empty($report_id) ? $report_id : $this->report_id;
        if (empty($report_id)) {
            throw new Exception('Power BI report ID is required.');
        }

        $access_level = strcasecmp($access_level, 'Edit') === 0 ? 'Edit' : 'View';
        $dataset_id = !empty($dataset_id) ? $dataset_id : $this->dataset_id;

        if ($access_level === 'Edit' && !empty($dataset_id)) {
            $url = 'https://api.powerbi.com/v1.0/myorg/GenerateToken';
            $body = array(
                'reports' => array(
                    array(
                        'id' => $report_id,
                        'allowEdit' => true,
                    ),
                ),
                'datasets' => array(
                    array('id' => $dataset_id),
                ),
                'targetWorkspaces' => array(
                    array('id' => $this->workspace_id),
                ),
            );
        } else {
            $url = 'https://api.powerbi.com/v1.0/myorg/groups/' . $this->workspace_id . '/reports/' . $report_id . '/GenerateToken';
            $body = array(
                'accessLevel' => $access_level,
            );
        }

        $response = $this->request('POST', $url, $body);

        if (empty($response['token'])) {
            throw new Exception('Unable to generate Power BI report embed token.');
        }

        if (!empty($response['expiration'])) {
            $this->token_expiry = $response['expiration'];
        }

        return $response['token'];
    }

    public function getEmbedToken()
    {
        return $this->getCreateEmbedToken();
    }

    protected function hasCredentials()
    {
        return !empty($this->tenant_id)
            && !empty($this->client_id)
            && !empty($this->client_secret);
    }

    protected function getAccessToken()
    {
        if (!empty($this->access_token) && !empty($this->token_expiry) && time() < ($this->token_expiry - 60)) {
            return $this->access_token;
        }

        $url = 'https://login.microsoftonline.com/' . $this->tenant_id . '/oauth2/v2.0/token';
        $payload = http_build_query(array(
            'grant_type' => 'client_credentials',
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'scope' => 'https://analysis.windows.net/powerbi/api/.default',
        ));

        $response = $this->curlRequest('POST', $url, $payload, array(
            'Content-Type: application/x-www-form-urlencoded',
        ));

        if (empty($response['access_token'])) {
            $message = !empty($response['error_description']) ? $response['error_description'] : 'Unable to authenticate with Power BI.';
            throw new Exception($message);
        }

        $this->access_token = $response['access_token'];
        $this->token_expiry = time() + (isset($response['expires_in']) ? (int) $response['expires_in'] : 3600);

        return $this->access_token;
    }

    protected function request($method, $url, $body = null)
    {
        $headers = array(
            'Authorization: Bearer ' . $this->getAccessToken(),
        );

        $payload = null;
        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
            $payload = json_encode($body);
        }

        return $this->curlRequest($method, $url, $payload, $headers);
    }

    protected function curlRequest($method, $url, $payload = null, $headers = array())
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }

        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($result === false) {
            throw new Exception('Power BI request failed: ' . $curl_error);
        }

        $decoded = json_decode($result, true);
        if ($http_code >= 400) {
            $message = $this->formatApiError($decoded, $http_code);
            throw new Exception($message);
        }

        return is_array($decoded) ? $decoded : array();
    }

    protected function formatApiError($decoded, $http_code)
    {
        $code = !empty($decoded['error']['code']) ? $decoded['error']['code'] : '';
        $message = !empty($decoded['error']['message']) ? $decoded['error']['message'] : '';

        if ($code === 'PowerBINotAuthorizedException' || $http_code === 401) {
            return 'Power BI app is not authorized for this workspace. Add your Azure app to the workspace in Power BI (Manage access) and grant admin consent for Dataset.Read.All / Report.ReadWrite.All.';
        }

        if ($code === 'PowerBIEntityNotFound' || $http_code === 404) {
            return 'Power BI workspace, report, or dataset was not found. Check POWERBI_WORKSPACE_ID, POWERBI_REPORT_ID, and POWERBI_DATASET_ID, and make sure the content is published to that shared workspace.';
        }

        if (!empty($message)) {
            return $message;
        }

        if (!empty($code)) {
            return 'Power BI API error: ' . $code;
        }

        return 'Power BI API request failed.';
    }
}
