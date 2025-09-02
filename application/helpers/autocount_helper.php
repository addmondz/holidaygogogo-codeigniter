<?php
defined('BASEPATH') OR exit('No direct script access allowed');

if (!function_exists('autocount_call')) {
    function autocount_call($module, $action, $params = [], $body = [], $headers = [])
    {
        $CI =& get_instance();
        $CI->config->load('autocount');

        $config = $CI->config->item('autocount'); // Now will return array from config file
        $endpointConfig = $config['endpoints'][$module][$action] ?? null;

        if (!$endpointConfig) {
            throw new InvalidArgumentException("Endpoint not found for {$module}.{$action}");
        }

        $method   = strtoupper($endpointConfig['method']);
        $endpoint = autocount_build_endpoint($endpointConfig['url'], $params);

        $baseUrl = rtrim($config['api_base_url'], '/');
        $apiKey  = $config['api_key'];

        if (empty($apiKey)) {
            throw new InvalidArgumentException("AutoCount API key is missing");
        }

        $url = "{$baseUrl}/" . ltrim($endpoint, '/');

        $headers = array_merge([
            'Accept: application/json',
            'Content-Type: application/json',
            "Authorization: Bearer {$apiKey}"
        ], $headers);

        $ch = curl_init();

        if ($method === 'GET' || $method === 'DELETE') {
            if (!empty($params)) {
                $url .= '?' . http_build_query($params);
            }
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => $method,
        ]);

        if ($method === 'POST' || $method === 'PUT') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $response = curl_exec($ch);
        $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            log_message('error', 'AutoCount API cURL Error: ' . curl_error($ch));
        }
        curl_close($ch);

        $decoded = json_decode($response, true);

        if (in_array($status, [200, 201])) {
            return ['success' => true, 'status' => $status, 'data' => $decoded];
        }

        log_message('error', "AutoCount API Error [{$status}]: " . $response);
        return ['success' => false, 'status' => $status, 'error' => $decoded];
    }
}

if (!function_exists('autocount_build_endpoint')) {
    function autocount_build_endpoint($template, $params)
    {
        foreach ($params as $key => $value) {
            $template = str_replace("{" . $key . "}", $value, $template);
        }
        return $template;
    }
}
