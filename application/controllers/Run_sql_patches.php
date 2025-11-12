<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Run all SQL patch files inside /application/sql/
 * Usage:
 *   - Web:  https://yourdomain.com/index.php/run_sql_patches
 *   - CLI:  php index.php run_sql_patches
 */
class Run_sql_patches extends CI_Controller
{
	public function index()
	{
		$sql_dir = APPPATH . 'sql/';
		$files = glob($sql_dir . '*.sql');

		if (empty($files)) {
			echo "❌ No SQL files found in {$sql_dir}" . PHP_EOL;
			return;
		}

		sort($files); // run in order (e.g. 20250101_..., 20250201_...)

		echo "⚙️ Starting SQL patch execution..." . PHP_EOL;

		foreach ($files as $file) {
			$filename = basename($file);
			echo "➡️ Running: {$filename}" . PHP_EOL;

			$sql_content = file_get_contents($file);
			if (!$sql_content) {
				echo "⚠️ Skipping (empty file): {$filename}" . PHP_EOL;
				continue;
			}

			// Split multiple queries by semicolon (basic split)
			$queries = array_filter(array_map('trim', explode(';', $sql_content)));

			$this->db->trans_start();

			foreach ($queries as $query) {
				if (!empty($query)) {
					try {
						$this->db->query($query);
					} catch (Exception $e) {
						echo "❌ Error in {$filename}: " . $e->getMessage() . PHP_EOL;
					}
				}
			}

			$this->db->trans_complete();

			if ($this->db->trans_status() === FALSE) {
				echo "❌ Transaction failed for: {$filename}" . PHP_EOL;
			} else {
				echo "✅ Completed: {$filename}" . PHP_EOL;
			}

			echo str_repeat('-', 50) . PHP_EOL;
		}

		echo "🎉 All SQL patches executed." . PHP_EOL;
	}
}
