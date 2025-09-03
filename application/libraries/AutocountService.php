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

        // Optionally fetch token immediately
        $this->token = $this->getToken();
    }

    /**
     * Get OAuth2 token
     */
    private function getToken() {
        $url = $this->config['base_url'] . '/auth/token';
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
    public function request($method, $endpoint, $payload = []) {
        $url = $this->config['base_url'] . $endpoint;
        $headers = [
            "Authorization: Bearer {$this->token}",
            "Content-Type: application/json"
        ];

        return $this->curlRequest($method, $url, $payload, $headers);
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

        // 🔹 Control HTTPS SSL verification with flag
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
