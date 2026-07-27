<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$env = [];
if (file_exists(FCPATH . '.env')) {
    $lines = file(FCPATH . '.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($key, $value) = explode('=', $line, 2);
        $env[trim($key)] = trim($value);
    }
}

$config['tenant_id'] = isset($env['POWERBI_TENANT_ID']) ? $env['POWERBI_TENANT_ID'] : '';
$config['client_id'] = isset($env['POWERBI_CLIENT_ID']) ? $env['POWERBI_CLIENT_ID'] : '';
$config['client_secret'] = isset($env['POWERBI_CLIENT_SECRET']) ? $env['POWERBI_CLIENT_SECRET'] : '';
$config['workspace_id'] = isset($env['POWERBI_WORKSPACE_ID']) ? $env['POWERBI_WORKSPACE_ID'] : '';
$config['dataset_id'] = isset($env['POWERBI_DATASET_ID']) ? $env['POWERBI_DATASET_ID'] : '';
$config['report_id'] = isset($env['POWERBI_REPORT_ID']) ? $env['POWERBI_REPORT_ID'] : '';
$config['embed_url'] = isset($env['POWERBI_EMBED_URL']) ? $env['POWERBI_EMBED_URL'] : '';
