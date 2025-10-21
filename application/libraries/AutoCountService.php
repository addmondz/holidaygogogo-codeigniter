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
        $apiKey = $config['AUTOCOUNT_apiKey'];
        $keyId = $config['AUTOCOUNT_keyId'];
        $accountBookId = $config['AUTOCOUNT_accountBookId'];
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
                'method'  => (isset($method)) ? $method : '',
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
        curl_setopt($ch, CURLOPT_HEADER, true);

        if ($headers) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verifySSL);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $verifySSL ? 2 : 0);

        $result = curl_exec($ch);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            $errorMsg = curl_error($ch);
            curl_close($ch);
            return [
                'status' => $httpCode,
                'headers' => [], 
                'body'   => null,
                'error'  => $errorMsg
            ];
        }

        curl_close($ch);

        $headerText = substr($result, 0, $headerSize);
        $bodyText = substr($result, $headerSize);

        $headerLines = explode("\r\n", trim($headerText));
        $headersAssoc = []; // whole header 
        $docNo = null; // ✅ added

        foreach ($headerLines as $line) {
            if (strpos($line, ':') !== false) {
                [$key, $value] = explode(':', $line, 2);
                $key = strtolower(trim($key));
                $value = trim($value);

                $headersAssoc[$key] = $value;

                // ✅ auto-detect and extract docNo if "location" header exists
                if ($key === 'location' && stripos($value, 'docNo=') !== false) {
                    $parts = parse_url($value);
                    parse_str($parts['query'] ?? '', $query);
                    $docNo = $query['docNo'] ?? null;
                }
            }
        }


        // Handle empty body (example: 201 Created with no response body)
        if ($bodyText === '' || $bodyText === null) {
            $response = [
                'status' => $httpCode,
                //'headers' => $headersAssoc,
                'body'   => (!empty($result)) ? $result : null,
                'error'  => ($httpCode >= 400 ? "HTTP Error $httpCode" : null)
            ];

            // ✅ only add docNo if it exists
            if (!empty($docNo)) {
                $response['docNo'] = $docNo;
            }

            return $response;
        }

        // Decode JSON body
        $decoded = json_decode($result, true);

        // If body is JSON and has "statusCode" field → treat as error
        if (is_array($decoded) && isset($decoded['statusCode']) && $decoded['statusCode'] >= 400) {
            $response = [
                'status' => $httpCode,
                //'headers' => $headersAssoc,
                'body'   => $decoded,
                'error'  => $decoded['message'] ?? "HTTP Error {$decoded['statusCode']}"
            ];

            if (!empty($docNo)) {
                $response['docNo'] = $docNo;
            }

            return $response;
        }

        // Normal success response
        $response = [
            'status' => $httpCode,
            //'headers' => $headersAssoc,
            'body'   => $decoded,
            'error'  => null
        ];
        if (!empty($docNo)) {
            $response['docNo'] = $docNo;
        }

        return $response;
    }
}
