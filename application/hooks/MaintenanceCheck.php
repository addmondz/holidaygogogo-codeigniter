<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class MaintenanceCheck
{
	public function check()
	{
		if (is_cli()) return;

		$env = array();
		$env_file = FCPATH . '.env';

		if (file_exists($env_file)) {
			$lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
			foreach ($lines as $line) {
				if (strpos(trim($line), '#') === 0) continue;
				list($key, $value) = explode('=', $line, 2);
				$env[trim($key)] = trim($value);
			}
		}

		if (!empty($env['MAINTENANCE_MODE']) && strtolower($env['MAINTENANCE_MODE']) === 'true') {
			http_response_code(503);
			include APPPATH . 'views/errors/maintenance.php';
			exit;
		}
	}
}
