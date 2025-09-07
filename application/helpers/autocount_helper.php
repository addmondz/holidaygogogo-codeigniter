<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Dynamic function to call any AutoCount API endpoint
 * Usage example:
 *   autocount_request('POST', 'quotation.create', $data);
 */

/**
 * @property CI_Config $config
 * @property AutoCountService $autocountservice
 */
if (!function_exists('dd')) {
    function dd(...$vars) {
        echo '<pre>';
        foreach ($vars as $var) {
            var_dump($var);
        }
        echo '</pre>';
        exit;
    }
}

if (!function_exists('autocount_request')) {
    /**
     * @param string $method       HTTP method (GET, POST, PUT, DELETE)
     * @param string $endpoint_key Endpoint key in config (e.g., quotation.create)
     * @param array  $payload      Body payload
     * @param array  $queryParams  Query string parameters
     * @return array
     */
    function autocount_request($method, $endpoint_key, $payload = [], $queryParams = []) {
        $CI =& get_instance();
        $CI->load->library('AutoCountService');
        $CI->config->load('autocount', TRUE);

        $config = $CI->config->item('autocount', 'autocount');
        $endpoint = null;

        // Dot-notation support (e.g., "quotation.create")
        if (strpos($endpoint_key, '.') !== false) {
            list($group, $action) = explode('.', $endpoint_key, 2);
            $endpoint = $config['endpoints'][$group][$action] ?? null;
        } else {
            $endpoint = $config['endpoints'][$endpoint_key] ?? null;
        }

        if (!$endpoint) {
            return ['error' => "Endpoint '{$endpoint_key}' not found"];
        }

        return $CI->autocountservice->request($method, $endpoint, $payload, $queryParams);
    }
}
if (!function_exists('autocount_log')) {
    /**
     * Log AutoCount API request/response
     *
     * @param string $endpoint API endpoint (e.g., quotation.create)
     * @param array  $request  Request data (headers, body, query)
     * @param mixed  $response API response
     * @param string $status   Y = success, N = fail
     * @param int|null $user_id Optional user ID
     */
    function autocount_log($endpoint, $request, $response, $status, $user_id = null) {
        $CI =& get_instance();

        try {
            $actionData = [
                'endpoint' => $endpoint,
                'request'  => $request,
                'response' => $response
            ];

            $data = [
                'UserID'     => $user_id,
                'Action'     => json_encode($actionData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'IP'         => $_SERVER['REMOTE_ADDR'] ?? null,
                'Url'        => is_string($endpoint) ? $endpoint : '',
                'Status'     => strtoupper($status) === 'Y' ? 'Y' : 'N',
                'InsertBy'   => 'SYSTEM',
                'InsertDate' => date('Y-m-d H:i:s')
            ];

            $CI->db->insert('activity_log', $data);

        } catch (Throwable $e) {
            log_message('error', 'AutoCount log failed: ' . $e->getMessage());
        }
    }
}