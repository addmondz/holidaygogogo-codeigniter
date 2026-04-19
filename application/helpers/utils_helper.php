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

if (!function_exists('logInFile')) {
    /**
     * Append a structured log entry to application/logs/{channel}.log
     *
     * @param string $channel Log channel / file prefix
     * @param string $message Message to write
     * @param array $meta Additional structured context
     * @return bool
     */
    function logInFile($channel, $message, $meta = array())
    {
        $channel = trim((string) $channel);
        if ($channel === '') {
            $channel = 'application';
        }

        $safeChannel = preg_replace('/[^A-Za-z0-9_\-]/', '_', $channel);
        $logPath = APPPATH . 'logs/';

        if (!is_dir($logPath) && !@mkdir($logPath, 0755, true) && !is_dir($logPath)) {
            return false;
        }

        $entry = '[' . date('Y-m-d H:i:s') . '] ' . (string) $message;

        if (!empty($meta)) {
            $json = json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $entry .= ' | meta=' . ($json !== false ? $json : print_r($meta, true));
        }

        $entry .= PHP_EOL;

        return file_put_contents($logPath . $safeChannel . '.log', $entry, FILE_APPEND | LOCK_EX) !== false;
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
 * Build a URL slug from a customer name and phone number.
 *
 * @param string $name    Customer name
 * @param string $phone   Phone number (may include country code, spaces, dashes)
 * @param int    $digits  Number of trailing phone digits to append (default 2)
 * @return string Slug like "john-doe-89"
 */
if (!function_exists('build_customer_slug')) {
    function build_customer_slug($name, $phone, $digits = 2)
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');

        $phone_clean = preg_replace('/[^0-9]/', '', $phone);
        $suffix = substr($phone_clean, -$digits);

        return $slug . '-' . $suffix;
    }
}

/**
 * Generate a customer portal slug for a given customer ID.
 *
 * Queries the database for the customer's name and phone number, builds a
 * slug, and checks for duplicates among active customers with a lower ID.
 * If a collision is found the slug uses 3 phone digits instead of 2.
 *
 * @param int|string $customer_id
 * @return string|false  Slug string, or false on failure
 */
if (!function_exists('generate_customer_portal_slug')) {
    function generate_customer_portal_slug($customer_id)
    {
        if (empty($customer_id)) {
            return false;
        }

        $CI =& get_instance();

        $customer = $CI->db->select('CustomerID, name, phone_number')
            ->where('CustomerID', $customer_id)
            ->get('customer')
            ->row_array();

        if (!$customer || empty($customer['name']) || empty($customer['phone_number'])) {
            return false;
        }

        $base_slug = build_customer_slug($customer['name'], $customer['phone_number'], 2);

        // Check for duplicate slugs among customers with a lower ID
        $others = $CI->db->select('name, phone_number')
            ->where('Status', 'Y')
            ->where('CustomerID <', $customer_id)
            ->get('customer')
            ->result_array();

        foreach ($others as $other) {
            if (!empty($other['name']) && !empty($other['phone_number'])) {
                if (build_customer_slug($other['name'], $other['phone_number'], 2) === $base_slug) {
                    return build_customer_slug($customer['name'], $customer['phone_number'], 3);
                }
            }
        }

        return $base_slug;
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

function get_offical_whatsapp_link($text = null, $phone_number = null){
    if (empty($text)) {
        $text = 'Hi, can i get more information about your travel package?';
    }
    if (empty($phone_number)) {
        $phone_number = get_offical_phone_number();
    }
    return 'https://api.whatsapp.com/send?phone=' . $phone_number . '&text=' . urlencode($text);
}

function get_offical_phone_number() {
    return '60102956786';
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
