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

			$this->db->trans_start();

			$has_error = false;
			foreach ($queries as $query) {
				if (!empty($query)) {
					try {
						$this->db->query($query);
					} catch (Exception $e) {
						echo "❌ Error in {$filename}: " . $e->getMessage() . PHP_EOL;
						$has_error = true;
						break;
					}
				}
			}

			$this->db->trans_complete();

			if ($this->db->trans_status() === FALSE || $has_error) {
				echo "❌ Transaction failed for: {$filename}" . PHP_EOL;
				echo str_repeat('-', 50) . PHP_EOL;
				continue;
			}

			// Record successful migration (skip for the migration table creation file)
			if ($filename !== '20250100_Create_Migration_Table.sql') {
				$this->record_migration($filename);
			}

			echo "✅ Completed: {$filename}" . PHP_EOL;
			$executed_count++;
			echo str_repeat('-', 50) . PHP_EOL;
		}

		echo PHP_EOL;
		echo "📊 Summary:" . PHP_EOL;
		echo "   ✅ Executed: {$executed_count} file(s)" . PHP_EOL;
		echo "   ⏭️  Skipped: {$skipped_count} file(s)" . PHP_EOL;
		echo "🎉 SQL patch execution completed." . PHP_EOL;
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
	 * Record a successful migration in the migrations table
	 * 
	 * @param string $filename The SQL filename that was executed
	 */
	private function record_migration($filename)
	{
		// Check if migrations table exists
		if (!$this->migrations_table_exists()) {
			echo "⚠️ Warning: migrations table does not exist. Cannot record migration." . PHP_EOL;
			return;
		}

		// Check if already recorded (shouldn't happen, but safety check)
		$this->db->where('migration', $filename);
		$exists = $this->db->get('migrations')->num_rows() > 0;

		if (!$exists) {
			$data = [
				'migration' => $filename
			];
			$this->db->insert('migrations', $data);
		}
	}
}
