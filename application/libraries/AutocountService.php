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
    public function request($config, $method, $endpoint, $payload = [], $queryParams = []) {
        $apiKey = get_env('AUTOCOUNT_apiKey');
        $keyId = get_env('AUTOCOUNT_keyId');
        $accountBookId = get_env('AUTOCOUNT_accountBookId');
        // Build URL with accountBookId prefix
        $url = rtrim($config['base_url'], '/') . '/' . $accountBookId . '/' . ltrim($endpoint, '/');

        // Append query parameters
        if (!empty($queryParams)) {
            $url .= '?' . http_build_query($queryParams);
        }

        // Headers required by AutoCount Cloud API
        $headers = [
            "API-Key: {$apiKey}",
            "Key-ID: {$keyId}",
            "Content-Type: application/json"
        ];

        $response = $this->curlRequest($method, $url, $payload, $headers);

        $this->CI->load->helper('autocount');
        $status = (is_array($response) && empty($response['error'])) ? 'Y' : 'N';
        autocount_log(
            $url,
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
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            $errorMsg = curl_error($ch);
            curl_close($ch);
            return [
                'status' => $httpCode,
                'body'   => null,
                'error'  => $errorMsg
            ];
        }

        curl_close($ch);

        // Handle empty body (example: 201 Created with no response body)
        if ($result === '' || $result === null) {
            return [
                'status' => $httpCode,
                'body'   => null,
                'error'  => ($httpCode >= 400 ? "HTTP Error $httpCode" : null)
            ];
        }

        // Decode JSON body
        $decoded = json_decode($result, true);

        // If body is JSON and has "statusCode" field → treat as error
        if (is_array($decoded) && isset($decoded['statusCode']) && $decoded['statusCode'] >= 400) {
            return [
                'status' => $httpCode,
                'body'   => $decoded,
                'error'  => $decoded['message'] ?? "HTTP Error {$decoded['statusCode']}"
            ];
        }

        // Normal success response
        return [
            'status' => $httpCode,
            'body'   => $decoded,
            'error'  => null
        ];
    }
}
