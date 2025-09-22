<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class AutoCountService {

    protected $CI;
    protected $config;
    protected $token;

    public function __construct() {
        $this->CI =& get_instance();
        $this->CI->config->load('autocount', TRUE);
        $this->config = $this->CI->config->item('autocount');

        //$this->token = $this->getToken();
    }

    /**
     * Get OAuth2 token
     */
    private function getToken() {
        $url = rtrim($this->config['base_url'], '/') . '/auth/token';
        $payload = [
            'client_id'     => $this->config['client_id'],
            'client_secret' => $this->config['client_secret'],
            'username'      => $this->config['username'],
            'password'      => $this->config['password'],
            'grant_type'    => 'password',
        ];

        $response = $this->curlRequest('POST', $url, $payload, false);

        if (isset($response['access_token'])) {
            return $response['access_token'];
        }

        log_message('error', 'AutoCount API Token Failed: ' . json_encode($response));
        return null;
    }

    /**
     * Send request to AutoCount API
     */
    public function request($method, $endpoint, $payload = [], $queryParams = []) {
        $accountBookId = $this->config['accountBookId'];

        // Build URL with accountBookId prefix
        $url = rtrim($this->config['base_url'], '/') . '/' . $accountBookId . '/' . ltrim($endpoint, '/');

        // Append query parameters
        if (!empty($queryParams)) {
            $url .= '?' . http_build_query($queryParams);
        }

        // Headers required by AutoCount Cloud API
        $headers = [
            "API-Key: {$this->config['apiKey']}",
            "Key-ID: {$this->config['keyId']}",
            "Content-Type: application/json"
        ];

        // If your API still needs Bearer token for some endpoints
        if (!empty($this->token)) {
            $headers[] = "Authorization: Bearer {$this->token}";
        }

        $response = $this->curlRequest($method, $url, $payload, $headers);

        $this->CI->load->helper('autocount');
        $status = (is_array($response) && empty($response['error'])) ? 'Y' : 'N';
        autocount_log(
            $endpoint,
            [
                'headers' => $headers,
                'body'    => $payload,
                'query'   => $queryParams
            ],
            $response,
            $status,
            null
        );

        return $response;
    }

    /**
     * Core cURL function
     */
    private function curlRequest($method, $url, $payload = [], $headers = false, $verifySSL = true) {
        $ch = curl_init();

        switch (strtoupper($method)) {
            case 'GET':
                if (!empty($payload)) {
                    $url .= '?' . http_build_query($payload);
                }
                break;
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
                break;
            case 'PUT':
            case 'PATCH':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
                break;
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        if ($headers) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verifySSL);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $verifySSL ? 2 : 0);

        $result = curl_exec($ch);

        if (curl_errno($ch)) {
            return ['error' => curl_error($ch)];
        }

        curl_close($ch);
        return json_decode($result, true);
    }
}
