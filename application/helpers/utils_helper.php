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
if (!function_exists('generate_secure_hash')) {
    /**
     * Generate a secure hash based on array of arguments
     *
     * @param array  $args   Key/value pairs to include in hash
     * @param string $secret Secret key from config/env
     * @param string $algo   Hash algorithm (default sha256)
     * @return string
     */
    function generate_secure_hash(array $args, string $secret, string $algo = 'sha256')
    {
        // Sort args to keep consistent order
        ksort($args);

        // Build query-like string: key1=value1&key2=value2
        $data = http_build_query($args);

        // Append secret to prevent replay
        $data .= '|' . $secret;

        // Generate hash
        return hash($algo, $data);
    }
}

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

if (!function_exists('ddSql')) {
    function ddSql($query)
    {  
        $sql = $query->toSql();
        $bindings = $query->getBindings();

        dd(preg_replace_callback('/\?/', function ($match) use ($sql, &$bindings) {
            return json_encode(array_shift($bindings));
        }, $sql));
    }
}

// Utility function to get environment variables from .env file
if (!function_exists('get_env')) {
    function get_env($key)
    {
        static $env = [];

        // Load .env file only once
        if (empty($env)) {
            if (file_exists(FCPATH . '.env')) {
                $lines = file(FCPATH . '.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    // Skip comments
                    if (strpos(trim($line), '#') === 0) continue;
                    // Split key-value pairs
                    list($key, $value) = explode('=', $line, 2);
                    $env[trim($key)] = trim($value);
                }
            }
        }

        return isset($env[$key]) ? $env[$key] : null;
    }
}
if (!function_exists('arr_get')) {
    function arr_get($array, $key, $default = '')
    {
        return isset($array[$key]) ? $array[$key] : $default;
    }
}