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
