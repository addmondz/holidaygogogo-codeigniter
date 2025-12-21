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
            // Final fallback: Use simple method with MD5 hash for consistency
            // This creates a shorter, consistent hash when neither GMP nor BCMath is available
            // We'll use the first 32 chars of hex, convert to decimal, then to base36
            $hex_short = substr($hex_string, 0, 32); // Use first 32 hex chars (128 bits)
            
            // Convert in smaller chunks that base_convert can handle
            $chunks = str_split($hex_short, 8); // 8 hex chars = 32 bits, safe for base_convert
            $decimal_parts = [];
            
            foreach ($chunks as $chunk) {
                $decimal_parts[] = base_convert($chunk, 16, 10);
            }
            
            // Combine chunks manually (simple addition for small numbers)
            $total = 0;
            $multiplier = 1;
            for ($i = count($decimal_parts) - 1; $i >= 0; $i--) {
                $total += $decimal_parts[$i] * $multiplier;
                $multiplier *= pow(16, 8); // 16^8 for each chunk
            }
            
            // Convert to base36
            $num = $total;
            while ($num > 0) {
                $remainder = $num % $base;
                $base36 = $chars[$remainder] . $base36;
                $num = intval($num / $base);
            }
        }
        
        return $base36;
    }
}

/**
 * Generate HMAC hash for customer portal URL
 * 
 * @param string $customer_code The customer code to sign
 * @param string $secret Secret key for HMAC (defaults to config value)
 * @param int $length Optional length to truncate (default: full length, recommended: 16-24)
 * @return string Base36 encoded HMAC hash (lowercase letters and numbers only)
 */
if (!function_exists('generate_customer_portal_hash')) {
    function generate_customer_portal_hash($customer_code, $secret = null, $length = 20)
    {
        if (empty($customer_code)) {
            return false;
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
                    show_error('Customer portal HMAC secret not configured');
                }
            }
        }
        
        // Generate HMAC using SHA-256
        $hash = hash_hmac('sha256', $customer_code, $secret);
        
        // Convert hex to base36 (only lowercase letters and numbers)
        $base36_hash = hex_to_base36($hash);
        
        // Truncate to desired length (default 20 characters for shorter URLs)
        // Still secure as long as length >= 16
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
 * @param string $customer_code The customer code to verify against
 * @param string $secret Secret key for HMAC (defaults to config value)
 * @param int $length Expected length of hash (if 0, uses actual hash length)
 * @return bool True if hash is valid, false otherwise
 */
if (!function_exists('verify_customer_portal_hash')) {
    function verify_customer_portal_hash($hash, $customer_code, $secret = null, $length = 0)
    {
        if (empty($hash) || empty($customer_code)) {
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
        $expected_hash = generate_customer_portal_hash($customer_code, $secret, $length);
        
        // Use timing-safe comparison to prevent timing attacks
        return hash_equals($expected_hash, $hash);
    }
}