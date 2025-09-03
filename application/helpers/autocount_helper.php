<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Dynamic function to call any AutoCount API endpoint
 * Usage example:
 *   autocount_request('POST', 'quotation_create', $data);
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
    function autocount_request($method, $endpoint_key, $payload = []) {
		
		$CI =& get_instance();
		$CI->load->library('AutoCountService');
		$CI->config->load('autocount', TRUE);

		// ✅ Correct way when using load(..., TRUE)
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

		return $CI->autocountservice->request($method, $endpoint, $payload);
	}
}
