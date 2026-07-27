<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Run all SQL patch files inside /application/sql/
 * Usage:
 *   - CLI only:  php index.php run_sql_patches
 * 
 * This script:
 * 1. Only runs from CLI (web access disabled)
 * 2. Checks migrations table for already executed files
 * 3. Only runs SQL files that haven't been migrated yet
 * 4. Records successful migrations in the migrations table
 */
class Run_sql_patches extends CI_Controller
{
	public function index()
	{
		// Only allow CLI execution
		if (!$this->input->is_cli_request()) {
			show_error('This script can only be run from the command line.', 403);
			return;
		}

		$sql_dir = APPPATH . 'sql/';
		$files = glob($sql_dir . '*.sql');

		if (empty($files)) {
			echo "❌ No SQL files found in {$sql_dir}" . PHP_EOL;
			return;
		}

		sort($files); // run in order (e.g. 20250101_..., 20250201_...)

		// Get list of already migrated files from migrations table
		$migrated_files = $this->get_migrated_files();

		echo "⚙️ Starting SQL patch execution..." . PHP_EOL;
		echo "📋 Found " . count($files) . " SQL file(s)" . PHP_EOL;
		echo "✅ Already migrated: " . count($migrated_files) . " file(s)" . PHP_EOL;
		echo str_repeat('-', 50) . PHP_EOL;

		$executed_count = 0;
		$skipped_count = 0;
		$failed_count = 0;
		$warning_count = 0;

		foreach ($files as $file) {
			$filename = basename($file);

			// Skip if already migrated
			if (in_array($filename, $migrated_files)) {
				// echo "⏭️  Skipping (already migrated): {$filename}" . PHP_EOL;
				$skipped_count++;
				continue;
			}

			echo "➡️ Running: {$filename}" . PHP_EOL;

			$sql_content = file_get_contents($file);
			if (!$sql_content) {
				echo "⚠️ Skipping (empty file): {$filename}" . PHP_EOL;
				$skipped_count++;
				continue;
			}

			// Split multiple queries by semicolon (basic split)
			$queries = array_filter(array_map('trim', explode(';', $sql_content)));

			$has_error = false;
			$has_warning = false;
			$warning_messages = [];

			foreach ($queries as $query) {
				if (!empty($query)) {
					$result = $this->execute_query_safely($query);

					if (!$result['success']) {
						if ($this->is_non_fatal_schema_warning($result)) {
							$warning_messages[] = $result['message'];
							echo "⚠️ Schema warning handled for {$filename}: " . $result['message'] . PHP_EOL;
							$has_warning = true;
							continue;
						} else {
							echo "❌ Error in {$filename}: " . $result['message'] . PHP_EOL;
							$has_error = true;
						}

						break;
					}
				}
			}

			if ($has_error) {
				echo "❌ Migration failed for: {$filename}" . PHP_EOL;
				$failed_count++;
				echo str_repeat('-', 50) . PHP_EOL;
				continue;
			}

			// Record successful migration (skip for the migration table creation file)
			if ($filename !== '20250100_Create_Migration_Table.sql') {
				$this->record_migration($filename, [
					'has_duplicate_field_error' => $has_warning ? 1 : 0,
					'error_message' => $has_warning ? implode("\n\n", $warning_messages) : null
				]);
			}

			if ($has_warning) {
				echo "⚠️ Recorded schema warning for: {$filename}" . PHP_EOL;
				$warning_count++;
			}

			echo "✅ Completed: {$filename}" . PHP_EOL;
			$executed_count++;
			echo str_repeat('-', 50) . PHP_EOL;
		}

		echo PHP_EOL;
		echo "📊 Summary:" . PHP_EOL;
		echo "   ✅ Executed: {$executed_count} file(s)" . PHP_EOL;
		echo "   ⏭️  Skipped: {$skipped_count} file(s)" . PHP_EOL;
		echo "   ⚠️ Warnings handled: {$warning_count} file(s)" . PHP_EOL;
		echo "   ❌ Failed: {$failed_count} file(s)" . PHP_EOL;
		echo "🎉 SQL patch execution completed." . PHP_EOL;
	}

	/**
	 * Execute a query with db_debug disabled so CLI can inspect database errors.
	 *
	 * @param string $query
	 * @return array
	 */
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
				'code' => 0,
				'message' => $e->getMessage()
			];
		}

		$this->db->db_debug = $original_db_debug;

		if ($result !== false) {
			return [
				'success' => true,
				'code' => 0,
				'message' => null
			];
		}

		$error = $this->db->error();

		return [
			'success' => false,
			'code' => isset($error['code']) ? (int) $error['code'] : 0,
			'message' => $this->format_db_error($error)
		];
	}

	/**
	 * Format a database error into the same shape CodeIgniter normally shows.
	 *
	 * @param array $error
	 * @return string
	 */
	private function format_db_error($error)
	{
		$code = isset($error['code']) ? $error['code'] : 0;
		$message = isset($error['message']) && $error['message'] !== ''
			? $error['message']
			: 'Unknown database error';

		return "Error Number: {$code}\n{$message}";
	}

	/**
	 * Check whether the query failure is a non-fatal schema warning.
	 *
	 * @param array $result
	 * @return bool
	 */
	private function is_non_fatal_schema_warning($result)
	{
		$code = isset($result['code']) ? (int) $result['code'] : 0;
		$message = isset($result['message']) ? $result['message'] : '';

		// These cases mean the schema change is already effectively present or absent,
		// so the migration can continue and be recorded as completed with a warning.
		$non_fatal_codes = [1060, 1061, 1091, 1826];
		if (in_array($code, $non_fatal_codes, true)) {
			return true;
		}

		return stripos($message, 'Duplicate column name') !== false
			|| stripos($message, 'Duplicate key name') !== false
			|| stripos($message, 'Duplicate FOREIGN KEY constraint name') !== false
			|| stripos($message, "Can't DROP INDEX") !== false;
	}

	/**
	 * Get list of already migrated files from migrations table
	 * 
	 * @return array List of migrated filenames
	 */
	private function get_migrated_files()
	{
		// Check if migrations table exists
		if (!$this->migrations_table_exists()) {
			return [];
		}

		$this->db->select('migration');
		$result = $this->db->get('migrations')->result_array();

		return array_column($result, 'migration');
	}

	/**
	 * Check if migrations table exists
	 * 
	 * @return bool
	 */
	private function migrations_table_exists()
	{
		$query = $this->db->query("SHOW TABLES LIKE 'migrations'");
		return $query->num_rows() > 0;
	}

	/**
	 * Record or update a migration row.
	 *
	 * @param string $filename
	 * @param array $extra_data
	 */
	private function record_migration($filename, $extra_data = [])
	{
		// Check if migrations table exists
		if (!$this->migrations_table_exists()) {
			echo "⚠️ Warning: migrations table does not exist. Cannot record migration." . PHP_EOL;
			return;
		}

		$data = array_merge(
			['migration' => $filename],
			$this->filter_supported_migration_fields($extra_data)
		);

		// Check if already recorded
		$this->db->where('migration', $filename);
		$existing = $this->db->get('migrations')->row_array();

		if ($existing) {
			$update_data = $data;
			unset($update_data['migration']);

			if (!empty($update_data)) {
				$this->db->where('id', $existing['id']);
				$this->db->update('migrations', $update_data);
			}

			return;
		}

		$this->db->insert('migrations', $data);
	}

	/**
	 * Only include fields that already exist on the migrations table.
	 *
	 * @param array $data
	 * @return array
	 */
	private function filter_supported_migration_fields($data)
	{
		$filtered = [];

		foreach ($data as $field => $value) {
			if ($this->db->field_exists($field, 'migrations')) {
				$filtered[$field] = $value;
			}
		}

		return $filtered;
	}
}
