<?php
defined('BASEPATH') OR exit('No direct script access allowed');

// Load REMARK_TYPE class
if (!class_exists('REMARK_TYPE')) {
    require_once(APPPATH . 'libraries/Remark_type.php');
}

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
                    list($env_key, $value) = explode('=', $line, 2);
                    $env[trim($env_key)] = trim($value);
                }
            }
        }

        return isset($env[$key]) ? $env[$key] : null;
    }
}

// Utility function to get APP_ENV from .env file
// Returns 'prod' as default if APP_ENV is not defined
if (!function_exists('get_app_env')) {
    function get_app_env()
    {
        $app_env = get_env('APP_ENV');
        return empty($app_env) ? 'prod' : $app_env;
    }
}

if (!function_exists('arr_get')) {
    function arr_get($array, $key, $default = '')
    {
        return isset($array[$key]) ? $array[$key] : $default;
    }
}

/**
 * Convert a hex string to base36 (0-9, a-z)
 * Uses chunk-based conversion for large numbers
 * 
 * @param string $hex_string Hex string to convert
 * @return string Base36 encoded string (lowercase letters and numbers only)
 */
if (!function_exists('hex_to_base36')) {
    function hex_to_base36($hex_string)
    {
        $base = 36;
        $chars = '0123456789abcdefghijklmnopqrstuvwxyz';
        $base36 = '';
        
        if (function_exists('gmp_init')) {
            // Use GMP for large number handling (preferred method)
            $num = gmp_init($hex_string, 16);
            $zero = gmp_init(0, 10);
            $base_gmp = gmp_init($base, 10);
            
            while (gmp_cmp($num, $zero) > 0) {
                $remainder = gmp_intval(gmp_mod($num, $base_gmp));
                $base36 = $chars[$remainder] . $base36;
                $num = gmp_div($num, $base_gmp);
            }
        } elseif (function_exists('bcmul') && function_exists('bcadd') && function_exists('bcpow') && function_exists('bccomp')) {
            // Fallback: Use BCMath if available
            // Process hex string in chunks
            $chunk_size = 13; // base_convert can handle up to 36^13
            $hex_chunks = str_split($hex_string, $chunk_size * 2);
            $result = '0';
            
            foreach ($hex_chunks as $chunk) {
                $chunk_decimal = base_convert($chunk, 16, 10);
                // Multiply previous result by 16^(chunk_size*2) and add new chunk
                $result = bcmul($result, bcpow('16', strlen($chunk)), 0);
                $result = bcadd($result, $chunk_decimal, 0);
            }
            
            // Convert result to base36
            $num = $result;
            while (bccomp($num, '0') > 0) {
                $remainder = intval(bcmod($num, (string)$base));
                $base36 = $chars[$remainder] . $base36;
                $num = bcdiv($num, (string)$base, 0);
            }
        } else {
            // Final fallback: Use simple method when neither GMP nor BCMath is available
            // Process in smaller chunks that base_convert can safely handle
            $hex_short = substr($hex_string, 0, 16); // Use first 16 hex chars (64 bits, safe for PHP int)
            
            // Convert hex to decimal using base_convert (safe for up to 16 hex chars)
            $decimal = base_convert($hex_short, 16, 10);
            
            // Convert decimal to base36
            $num = intval($decimal);
            if ($num == 0) {
                $base36 = '0';
            } else {
                while ($num > 0) {
                    $remainder = $num % $base;
                    $base36 = $chars[$remainder] . $base36;
                    $num = intval($num / $base);
                }
            }
            
            // If we need more entropy, append hash of remaining hex string
            if (strlen($hex_string) > 16) {
                $remaining = substr($hex_string, 16);
                // Use MD5 to create a consistent hash from remaining part
                $hash_suffix = md5($remaining);
                $suffix_hex = substr($hash_suffix, 0, 8); // Use first 8 chars of MD5
                $suffix_decimal = base_convert($suffix_hex, 16, 10);
                $num_suffix = intval($suffix_decimal);
                $suffix_base36 = '';
                if ($num_suffix == 0) {
                    $suffix_base36 = '0';
                } else {
                    while ($num_suffix > 0) {
                        $remainder = $num_suffix % $base;
                        $suffix_base36 = $chars[$remainder] . $suffix_base36;
                        $num_suffix = intval($num_suffix / $base);
                    }
                }
                $base36 = $base36 . $suffix_base36;
            }
        }
        
        // Ensure we always return a non-empty string
        if (empty($base36)) {
            // Ultimate fallback: use MD5 of the hex string
            $md5_hash = md5($hex_string);
            $base36 = base_convert(substr($md5_hash, 0, 16), 16, 36);
        }
        
        return $base36;
    }
}

/**
 * Generate simple hash for customer portal URL
 * 
 * @param int|string $customer_id The customer ID to sign
 * @param string $secret Secret key (defaults to config value)
 * @param int $length Optional length to truncate (default: 20)
 * @return string Base36 encoded hash (lowercase letters and numbers only)
 */
if (!function_exists('generate_customer_portal_hash')) {
    function generate_customer_portal_hash($customer_id, $secret = null, $length = 20)
    {
        if (empty($customer_id)) {
            return false;
        }
        
        // Convert customer ID to string
        $customer_id_str = (string)$customer_id;
        
        // Get secret from config or .env
        if ($secret === null) {
            $CI =& get_instance();
            $secret = $CI->config->item('customer_portal_hmac_secret');
            if (empty($secret)) {
                if (function_exists('get_env')) {
                    $secret = get_env('CUSTOMER_PORTAL_HMAC_SECRET');
                }
                if (empty($secret)) {
                    show_error('Customer portal HMAC secret not configured');
                }
            }
        }
        
        // Simple method: MD5 hash of customer_id + secret, then convert to base36
        $md5_hash = md5($customer_id_str . $secret);
        
        // Convert first 16 hex characters to base36 (simple, works everywhere)
        $hex_short = substr($md5_hash, 0, 16);
        $base36_hash = base_convert($hex_short, 16, 36);
        
        // Truncate to desired length
        if ($length > 0 && strlen($base36_hash) > $length) {
            $base36_hash = substr($base36_hash, 0, $length);
        }
        
        return $base36_hash;
    }
}

/**
 * Verify HMAC hash for customer portal URL
 * 
 * @param string $hash The hash from the URL
 * @param int|string $customer_id The customer ID to verify against
 * @param string $secret Secret key for HMAC (defaults to config value)
 * @param int $length Expected length of hash (if 0, uses actual hash length)
 * @return bool True if hash is valid, false otherwise
 */
if (!function_exists('verify_customer_portal_hash')) {
    function verify_customer_portal_hash($hash, $customer_id, $secret = null, $length = 0)
    {
        if (empty($hash) || empty($customer_id)) {
            return false;
        }
        
        // Use actual hash length if not specified
        if ($length === 0) {
            $length = strlen($hash);
        }
        
        if ($secret === null) {
            $CI =& get_instance();
            $secret = $CI->config->item('customer_portal_hmac_secret');
            if (empty($secret)) {
                // Fallback to .env if config not set
                if (function_exists('get_env')) {
                    $secret = get_env('CUSTOMER_PORTAL_HMAC_SECRET');
                }
                if (empty($secret)) {
                    return false;
                }
            }
        }
        
        // Generate expected hash with same length as provided hash
        $expected_hash = generate_customer_portal_hash($customer_id, $secret, $length);
        
        // Use timing-safe comparison to prevent timing attacks
        return hash_equals($expected_hash, $hash);
    }
}

function format_mobile_number($mobile_number)
{
    if (empty($mobile_number)) {
        return null;
    }

    // Remove spaces, dashes, brackets, plus sign
    $number = preg_replace('/[^0-9]/', '', $mobile_number);

    // If starts with 60 (already international)
    if (preg_match('/^60\d{8,10}$/', $number)) {
        return $number;
    }

    // If starts with 0 (local format)
    if (preg_match('/^0\d{8,10}$/', $number)) {
        return '6' . $number;
    }

    // If starts with 1 (missing country code & leading zero)
    if (preg_match('/^1\d{8,10}$/', $number)) {
        return '60' . $number;
    }

    // Anything else → invalid or unsupported
    return null;
}

function get_offical_whatsapp_link($text = null){
    if (empty($text)) {
        $text = 'Welcome to Holidaygogogo!';
    }
    return 'https://api.whatsapp.com/send?phone=60102956786&text=' . urlencode($text);
}

function return_timestamp_output($timestamp, $show_time_ago = true, $show_time = true)
{
    $format = 'd M Y' . ($show_time ? ', H:i' : '');
    $formatted = date($format, strtotime($timestamp));

    return $formatted . ($show_time_ago ? '<span style="margin: 0 4px;">•</span> ' . time_ago($timestamp) : '');
}

function time_ago($timestamp)
{
    $diff = time() - strtotime($timestamp);

    if ($diff < 60) {
        return 'just now';
    }

    $units = [
        31536000 => 'year',
        2592000  => 'month',
        86400    => 'day',
        3600     => 'hour',
        60       => 'minute',
    ];

    foreach ($units as $seconds => $label) {
        if ($diff >= $seconds) {
            $value = floor($diff / $seconds);
            return $value . ' ' . $label . ($value > 1 ? 's' : '') . ' ago';
        }
    }
}
