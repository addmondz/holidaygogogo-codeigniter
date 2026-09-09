<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Competitor_Product_Job — the background worker for Competitor Product. Spawned as a
 * detached CLI process:  php index.php Competitor_Product_Job run <job_id>
 * (exact case — Linux filesystems are case-sensitive; lowercase 404s there.)
 *
 * Two modes (from the queued status file's `mode`):
 *   - 'crawl' (default): discover + read every product into per-product {url,text}
 *     (jobs/<id>.items.json) and stop. The user then reviews the crawled products
 *     and picks which to analyse.
 *   - 'analyse': analyse a SELECTED subset (indices) of a source crawl's items with
 *     OpenAI (reusing the crawled text) and save one history row.
 * The status file is updated through the phases (discovering→reading, or
 * analysing→done); the web side polls it. CLI-only.
 */
class Competitor_Product_Job extends CI_Controller
{
	public function run($job_id = '')
	{
		if ( ! is_cli()) {
			show_404();
			return;
		}
		$this->load->helper('competitor_analysis');

		$job_id = preg_replace('/[^A-Za-z0-9_]/', '', (string) $job_id);
		if ($job_id === '') {
			return;
		}
		$status_file = APPPATH . 'logs/competitor_crawl/jobs/' . $job_id . '.json';
		if ( ! is_file($status_file)) {
			return;
		}
		$job = json_decode((string) file_get_contents($status_file), true);
		if ( ! is_array($job)) {
			return;
		}

		$write = function ($data) use ($status_file, $job) {
			$base = array(
				'job'          => isset($job['job']) ? $job['job'] : '',
				'url'          => isset($job['url']) ? $job['url'] : '',
				'mode'         => isset($job['mode']) ? $job['mode'] : 'crawl',
				'keyword'      => isset($job['keyword']) ? $job['keyword'] : '',          // persist chips
				'ai_crawl'     => ! empty($job['ai_crawl']) ? 1 : 0,
				'created'      => isset($job['created']) ? $job['created'] : date('Y-m-d H:i:s'),   // fixed submit time
				'ts'           => date('Y-m-d H:i:s'),
			);
			@file_put_contents($status_file, json_encode(array_merge($base, $data), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
		};

		$this->load->library('CompetitorAnalysisService');
		$mode = isset($job['mode']) ? $job['mode'] : 'crawl';
		if ($mode === 'analyse') {
			$this->run_analyse($job, $write);
		} elseif ($mode === 'paste') {
			$this->run_paste($job, $write);
		} else {
			$this->run_crawl($job, $write);
		}
	}

	/**
	 * Analyse PASTED TEXT (notes + links) in the background — the same work the old
	 * synchronous path did, moved here so a slow paste (link fetches + an OpenAI
	 * web_search browse) can't hit the web server's read timeout and show a false
	 * "Analysis Failed" while the analysis actually completes. Saves one history row
	 * and records its id on the job so the results table can link to it.
	 */
	private function run_paste($job, $write)
	{
		@set_time_limit(0);
		$paste = isset($job['paste_text']) ? (string) $job['paste_text'] : '';
		$write(array('state' => 'running', 'phase' => 'analysing', 'done' => 0, 'total' => 1));
		$this->load->model('Product_Model');
		$this->load->helper('product_tour_fields');
		$this->load->model('Competitor_Analysis_Model');
		try {
			if (trim($paste) === '') {
				throw new Exception('No text to analyse.');
			}
			$our    = competitor_format_our_products($this->Product_Model->Read_For_Comparison());
			$record = $this->competitoranalysisservice->analyze_paste($paste, $our);
			$record['url']        = competitor_paste_source_label($paste);
			$record['source']     = 'paste';
			$record['status']     = 'done';
			$record['created_by'] = isset($job['created_by']) ? $job['created_by'] : null;
			$id = (int) $this->Competitor_Analysis_Model->Create($record);
			$write(array('state' => 'done', 'phase' => 'analysing', 'done' => 1, 'total' => 1,
				'count' => 1, 'analysis_id' => $id, 'cost_total' => (float) $record['cost_usd']));
		} catch (Exception $e) {
			log_message('error', 'CompetitorJob paste failed: ' . $e->getMessage());
			$write(array('state' => 'error', 'message' => $e->getMessage()));
		}
	}

	/** Crawl the products to per-product {url,text} and stop (user reviews next). */
	private function run_crawl($job, $write)
	{
		$url = isset($job['url']) ? (string) $job['url'] : '';
		if ($url === '') {
			return;
		}
		$write(array('state' => 'running', 'phase' => 'discovering', 'done' => 0, 'total' => 0));
		try {
			// Stamp when the READING phase begins so the UI can show a live ETA
			// (discovery has no known total; reading does).
			$read_start = 0;
			$progress = function ($phase, $done, $total, $label) use ($write, &$read_start) {
				if ($phase === 'reading' && $read_start === 0) {
					$read_start = time();
				}
				$data = array('state' => 'running', 'phase' => $phase, 'done' => (int) $done, 'total' => (int) $total, 'label' => (string) $label);
				if ($read_start > 0) {
					$data['read_start'] = date('Y-m-d H:i:s', $read_start);
				}
				$write($data);
			};
			$keyword  = isset($job['keyword']) ? (string) $job['keyword'] : '';
			$ai_crawl = ! empty($job['ai_crawl']);
			$items = $this->competitoranalysisservice->crawl_to_text($url, 0, $progress, $keyword, $ai_crawl);
			$items_file = APPPATH . 'logs/competitor_crawl/jobs/' . $job['job'] . '.items.json';
			@file_put_contents($items_file, json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

			// AI-crawl discovery (web_search) spends tokens that aren't tied to any saved
			// product — seed the crawl's running cost with it so the AI Cost column reflects
			// it (later analyses add on top via merge_crawl_analysed).
			$crawl_cost = (float) $this->competitoranalysisservice->last_run_cost();
			$n = count($items);
			$write(array('state' => 'done', 'phase' => 'reading', 'done' => $n, 'total' => $n,
				'count' => $n, 'items_file' => $items_file, 'cost_total' => round($crawl_cost, 6)));
		} catch (Exception $e) {
			$write(array('state' => 'error', 'message' => $e->getMessage()));
		}
	}

	/** Analyse the SELECTED items of a source crawl with OpenAI; save one history row. */
	private function run_analyse($job, $write)
	{
		$url        = isset($job['url']) ? (string) $job['url'] : '';
		$items_file = isset($job['items_file']) ? (string) $job['items_file'] : '';
		$all = ($items_file !== '' && is_file($items_file))
			? json_decode((string) file_get_contents($items_file), true) : null;
		if ( ! is_array($all) || empty($all)) {
			$write(array('state' => 'error', 'message' => 'Crawled products are no longer available.'));
			return;
		}
		// Pick the selected indices (default: all), keyed by original index.
		$indices = isset($job['indices']) && is_array($job['indices']) ? $job['indices'] : array_keys($all);
		$sel = array();
		foreach ($indices as $i) {
			if (isset($all[(int) $i])) { $sel[(int) $i] = $all[(int) $i]; }
		}
		if (empty($sel)) {
			$write(array('state' => 'error', 'message' => 'No products selected.'));
			return;
		}

		$n = count($sel);
		$write(array('state' => 'running', 'phase' => 'analysing', 'done' => 0, 'total' => $n));
		$this->load->model('Product_Model');
		$this->load->helper('product_tour_fields');
		$this->load->model('Competitor_Analysis_Model');
		try {
			$our = competitor_format_our_products($this->Product_Model->Read_For_Comparison());
			$results = array();   // index => {id, cost}
			$total_cost = 0.0;
			$done = 0;
			foreach ($sel as $i => $item) {
				$write(array('state' => 'running', 'phase' => 'analysing', 'done' => $done, 'total' => $n, 'label' => isset($item['url']) ? $item['url'] : ''));
				try {
					$site = $this->competitoranalysisservice->analyze_texts(array($item), $our);
					if ( ! empty($site['products'])) {
						$rec = $site['products'][0];
						$rec['status']     = 'done';
						$rec['created_by'] = isset($job['created_by']) ? $job['created_by'] : null;
						$id = (int) $this->Competitor_Analysis_Model->Create($rec);
						$results[(string) $i] = array('id' => $id, 'cost' => (float) $site['cost_usd'], 'at' => date('Y-m-d H:i:s'));
						$total_cost += (float) $site['cost_usd'];
					}
				} catch (Exception $e) {
					log_message('error', 'CompetitorJob analyse item failed: ' . $e->getMessage());
				}
				$write(array('state' => 'running', 'phase' => 'analysing', 'done' => ++$done, 'total' => $n));
			}
			$this->merge_crawl_analysed($job, $results, $total_cost);
			$write(array('state' => 'done', 'phase' => 'analysing', 'done' => $n, 'total' => $n, 'count' => count($results)));
		} catch (Exception $e) {
			$write(array('state' => 'error', 'message' => $e->getMessage()));
		}
	}

	/** Merge newly-analysed items (index → {id,cost}) and accumulated cost onto the source crawl. */
	private function merge_crawl_analysed($job, $results, $add_cost)
	{
		$src = isset($job['src_job']) ? preg_replace('/[^A-Za-z0-9_]/', '', (string) $job['src_job']) : '';
		if ($src === '' || empty($results)) {
			return;
		}
		$file = APPPATH . 'logs/competitor_crawl/jobs/' . $src . '.json';
		if ( ! is_file($file)) {
			return;
		}
		$s = json_decode((string) file_get_contents($file), true);
		if ( ! is_array($s)) {
			return;
		}
		$analysed = isset($s['analysed']) && is_array($s['analysed']) ? $s['analysed'] : array();
		foreach ($results as $idx => $r) {
			$analysed[(string) $idx] = $r;
		}
		$s['analysed']   = $analysed;
		$s['cost_total'] = round((isset($s['cost_total']) ? (float) $s['cost_total'] : 0.0) + (float) $add_cost, 6);
		@file_put_contents($file, json_encode($s, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
	}
}
