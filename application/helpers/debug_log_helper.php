<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Debug Log Helper
 * 
 * Provides functions to log debug information to files
 * 
 * USAGE EXAMPLES:
 * ===============
 * 
 * 1. Basic logging with a string:
 *    debug_log('User logged in successfully');
 *    // Output: [2024-01-15 10:30:45] User logged in successfully
 
 * NOTE: Log files are stored in: application/logs/
 * Default log files: debug.log and debug.json.log
 */

if (!function_exists('debug_log')) {
    /**
     * Log debug information to a file
     * 
     * @param mixed $data Data to log (string, array, object, etc.)
     * @param string $label Optional label/prefix for the log entry
     * @param string $log_file Optional custom log file name (default: debug.log)
     * @return bool True on success, false on failure
     */
    function debug_log($data, $label = '', $log_file = 'debug.log')
    {
        $CI =& get_instance();
        
        // Get log path from config or use default
        $log_path = $CI->config->item('log_path');
        if (empty($log_path)) {
            $log_path = APPPATH . 'logs/';
        }
        
        // Ensure log directory exists
        if (!is_dir($log_path)) {
            @mkdir($log_path, 0755, true);
        }
        
        // Full file path
        $file_path = $log_path . $log_file;
        
        // Format timestamp
        $timestamp = date('Y-m-d H:i:s');
        
        // Format the data
        $formatted_data = '';
        if (!empty($label)) {
            $formatted_data .= "[{$label}] ";
        }
        
        if (is_array($data) || is_object($data)) {
            $formatted_data .= print_r($data, true);
        } else {
            $formatted_data .= (string)$data;
        }
        
        // Create log entry
        $log_entry = "[{$timestamp}] {$formatted_data}\n";
        
        // Write to file (append mode)
        $result = @file_put_contents($file_path, $log_entry, FILE_APPEND | LOCK_EX);
        
        return $result !== false;
    }
}

if (!function_exists('debug_log_json')) {
    /**
     * Log data as JSON to a file
     * 
     * @param mixed $data Data to log
     * @param string $label Optional label/prefix for the log entry
     * @param string $log_file Optional custom log file name (default: debug.json.log)
     * @return bool True on success, false on failure
     */
    function debug_log_json($data, $label = '', $log_file = 'debug.json.log')
    {
        $CI =& get_instance();
        
        // Get log path from config or use default
        $log_path = $CI->config->item('log_path');
        if (empty($log_path)) {
            $log_path = APPPATH . 'logs/';
        }
        
        // Ensure log directory exists
        if (!is_dir($log_path)) {
            @mkdir($log_path, 0755, true);
        }
        
        // Full file path
        $file_path = $log_path . $log_file;
        
        // Format timestamp
        $timestamp = date('Y-m-d H:i:s');
        
        // Create log entry
        $log_entry = array(
            'timestamp' => $timestamp,
            'label' => $label,
            'data' => $data
        );
        
        // Convert to JSON
        $json_entry = json_encode($log_entry, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
        
        // Write to file (append mode)
        $result = @file_put_contents($file_path, $json_entry, FILE_APPEND | LOCK_EX);
        
        return $result !== false;
    }
}

if (!function_exists('clear_debug_log')) {
    /**
     * Clear a debug log file
     * 
     * @param string $log_file Log file name to clear (default: debug.log)
     * @return bool True on success, false on failure
     */
    function clear_debug_log($log_file = 'debug.log')
    {
        $CI =& get_instance();
        
        // Get log path from config or use default
        $log_path = $CI->config->item('log_path');
        if (empty($log_path)) {
            $log_path = APPPATH . 'logs/';
        }
        
        $file_path = $log_path . $log_file;
        
        if (file_exists($file_path)) {
            return @unlink($file_path);
        }
        
        return true; // File doesn't exist, consider it "cleared"
    }
}
