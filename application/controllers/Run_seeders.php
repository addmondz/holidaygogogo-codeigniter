<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Run all SQL seeder files inside /application/seeder/
 * Usage:
 *   - CLI only: php index.php run_seeders
 *
 * This script:
 * 1. Only runs from CLI (web access disabled)
 * 2. Checks migrations table for already executed seeders
 * 3. Only runs SQL seeder files that have not been recorded yet
 * 4. Records successful seeders in the migrations table
 */
class Run_seeders extends CI_Controller
{
	public function index()
	{
		if (!$this->input->is_cli_request()) {
			show_error('This script can only be run from the command line.', 403);
			return;
		}

		$seeder_dir = APPPATH . 'seeder/';
		$files = glob($seeder_dir . '*.sql');

		if (empty($files)) {
			echo "No SQL seeder files found in {$seeder_dir}" . PHP_EOL;
			return;
		}

		sort($files);

		$migrated_files = $this->get_migrated_files();

		echo "Starting SQL seeder execution..." . PHP_EOL;
		echo "Found " . count($files) . " SQL seeder file(s)" . PHP_EOL;
		echo "Already recorded: " . count($migrated_files) . " file(s)" . PHP_EOL;
		echo str_repeat('-', 50) . PHP_EOL;

		$executed_count = 0;
		$skipped_count = 0;
		$failed_count = 0;

		foreach ($files as $file) {
			$filename = basename($file);

			if (in_array($filename, $migrated_files, true)) {
				$skipped_count++;
				continue;
			}

			echo "Running: {$filename}" . PHP_EOL;

			$sql_content = file_get_contents($file);
			if (!$sql_content) {
				echo "Skipping empty file: {$filename}" . PHP_EOL;
				$skipped_count++;
				echo str_repeat('-', 50) . PHP_EOL;
				continue;
			}

			$queries = array_filter(array_map('trim', explode(';', $sql_content)));
			$has_error = false;

			foreach ($queries as $query) {
				if ($query === '') {
					continue;
				}

				$result = $this->execute_query_safely($query);

				if (!$result['success']) {
					echo "Error in {$filename}: " . $result['message'] . PHP_EOL;
					$has_error = true;
					break;
				}
			}

			if ($has_error) {
				echo "Seeder failed for: {$filename}" . PHP_EOL;
				$failed_count++;
				echo str_repeat('-', 50) . PHP_EOL;
				continue;
			}

			$this->record_migration($filename);

			echo "Completed: {$filename}" . PHP_EOL;
			$executed_count++;
			echo str_repeat('-', 50) . PHP_EOL;
		}

		echo PHP_EOL;
		echo "Summary:" . PHP_EOL;
		echo "   Executed: {$executed_count} file(s)" . PHP_EOL;
		echo "   Skipped: {$skipped_count} file(s)" . PHP_EOL;
		echo "   Failed: {$failed_count} file(s)" . PHP_EOL;
		echo "SQL seeder execution completed." . PHP_EOL;
	}

	private function execute_query_safely($query)
	{
		$original_db_debug = $this->db->db_debug;
		$this->db->db_debug = false;

		try {
			$result = $this->db->query($query);
		} catch (Exception $e) {
			$this->db->db_debug = $original_db_debug;

			return [
				'success' => false,
				'message' => $e->getMessage()
			];
		}

		$this->db->db_debug = $original_db_debug;

		if ($result !== false) {
			return [
				'success' => true,
				'message' => null
			];
		}

		$error = $this->db->error();

		return [
			'success' => false,
			'message' => $this->format_db_error($error)
		];
	}

	private function format_db_error($error)
	{
		$code = isset($error['code']) ? $error['code'] : 0;
		$message = isset($error['message']) && $error['message'] !== ''
			? $error['message']
			: 'Unknown database error';

		return "Error Number: {$code}\n{$message}";
	}

	private function get_migrated_files()
	{
		if (!$this->migrations_table_exists()) {
			return [];
		}

		$this->db->select('migration');
		$result = $this->db->get('migrations')->result_array();

		return array_column($result, 'migration');
	}

	private function migrations_table_exists()
	{
		$query = $this->db->query("SHOW TABLES LIKE 'migrations'");
		return $query->num_rows() > 0;
	}

	private function record_migration($filename)
	{
		if (!$this->migrations_table_exists()) {
			echo "Warning: migrations table does not exist. Cannot record seeder." . PHP_EOL;
			return;
		}

		$this->db->where('migration', $filename);
		$existing = $this->db->get('migrations')->row_array();

		if ($existing) {
			return;
		}

		$this->db->insert('migrations', [
			'migration' => $filename
		]);
	}
}
