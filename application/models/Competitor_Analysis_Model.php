<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Competitor_Analysis_Model — thin persistence for the competitor_analyses
 * table. The scraping + OpenAI work is done in the controller/service; this
 * layer only stores the finished record and reads it back for the listing and
 * the detail view. List fields arrive as PHP arrays and are stored as JSON.
 */
class Competitor_Analysis_Model extends CI_Model
{
	/**
	 * The rich extraction fields that live inside the single details_json column
	 * (everything beyond the headline columns). Kept here so Create() stores and
	 * Read_One() decodes exactly the same set.
	 */
	private $detail_keys = array(
		'price_from', 'price_to', 'departure_city', 'flight_departure', 'flight_return',
		'difficulty', 'target_traveller', 'suitable_age', 'child_friendly', 'senior_friendly',
		'countries', 'cities', 'travel_months', 'themes', 'tour_styles', 'local_transport',
		'exclusions', 'hotels', 'shopping_stops', 'optional_tours', 'special_remarks',
		'scenic_highlights', 'signature_meals', 'usp', 'meals', 'itinerary',
	);

	/**
	 * Insert one analysis. $data is the flat record from
	 * competitor_parse_ai_response() plus url/page_title/model/status. Returns
	 * the new row id.
	 */
	function Create($data)
	{
		$details = array();
		foreach ($this->detail_keys as $k) {
			if (array_key_exists($k, $data)) {
				$details[$k] = $data[$k];
			}
		}

		// A site crawl passes a 'products' array (one combined report row); a
		// single URL/upload passes the flat scalar/list fields (the classic row).
		$is_crawl = isset($data['products']) && is_array($data['products']);

		$row = array(
			'url'           => (string) (isset($data['url']) ? $data['url'] : ''),
			'page_title'    => isset($data['page_title']) ? $data['page_title'] : null,
			'product_name'  => isset($data['product_name']) ? $data['product_name'] : null,
			'tour_code'     => isset($data['tour_code']) ? $data['tour_code'] : null,
			'price'         => isset($data['price']) ? $data['price'] : null,
			'currency'      => isset($data['currency']) ? $data['currency'] : null,
			'destination'   => isset($data['destination']) ? $data['destination'] : null,
			'duration'      => isset($data['duration']) ? $data['duration'] : null,
			'inclusions'    => json_encode(isset($data['inclusions']) ? $data['inclusions'] : array(), JSON_UNESCAPED_UNICODE),
			'pros'          => json_encode(isset($data['pros']) ? $data['pros'] : array(), JSON_UNESCAPED_UNICODE),
			'cons'          => json_encode(isset($data['cons']) ? $data['cons'] : array(), JSON_UNESCAPED_UNICODE),
			'summary'       => isset($data['summary']) ? $data['summary'] : null,
			'comparison'    => isset($data['comparison']) ? $data['comparison'] : null,
			'details_json'  => $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
			'products_json' => $is_crawl ? json_encode($data['products'], JSON_UNESCAPED_UNICODE) : null,
			'product_count' => $is_crawl ? count($data['products']) : (isset($data['product_count']) ? (int) $data['product_count'] : 0),
			'raw_json'      => isset($data['raw_json']) ? $data['raw_json'] : null,
			'model'         => isset($data['model']) ? $data['model'] : null,
			'input_tokens'  => isset($data['input_tokens']) ? (int) $data['input_tokens'] : 0,
			'output_tokens' => isset($data['output_tokens']) ? (int) $data['output_tokens'] : 0,
			'cost_usd'      => isset($data['cost_usd']) ? $data['cost_usd'] : 0,
			'status'        => isset($data['status']) ? $data['status'] : 'done',
			'error_message' => isset($data['error_message']) ? $data['error_message'] : null,
			'created_by'    => isset($data['created_by']) ? $data['created_by'] : null,
		);
		$this->db->insert('competitor_analyses', $row);
		return $this->db->insert_id();
	}

	/** Listing rows, newest first. */
	function Read_All()
	{
		$this->db->select('id, url, page_title, product_name, tour_code, price, currency, destination, duration, cost_usd, product_count, status, created_at');
		$this->db->order_by('id', 'DESC');
		return $this->db->get('competitor_analyses')->result();
	}

	/**
	 * File-upload analyses only (a pasted PDF/image, whose "url" is a filename, not an
	 * http link) — shown as rows in the main results table alongside crawls. Newest
	 * first, capped.
	 */
	function Read_Uploads($limit = 20)
	{
		$this->db->select('id, url, product_name, cost_usd, status, created_at');
		$this->db->where("url NOT LIKE 'http%'", null, false);
		$this->db->order_by('id', 'DESC');
		$this->db->limit((int) $limit);
		return $this->db->get('competitor_analyses')->result();
	}

	/** Cumulative USD OpenAI spend across every stored analysis. */
	function Read_Total_Cost()
	{
		$row = $this->db->select('SUM(cost_usd) AS total', false)->get('competitor_analyses')->row();
		return $row && $row->total !== null ? (float) $row->total : 0.0;
	}

	/**
	 * One full row with list fields decoded back into arrays; null if missing.
	 * The rich details_json is unpacked onto the row so the view can read
	 * $row->countries, $row->itinerary, $row->meals, etc. directly. Every detail
	 * key is defaulted (list -> array, meals -> blank slots, scalar -> '') so
	 * older rows without details_json render cleanly.
	 */
	function Read_One($id)
	{
		$row = $this->db->get_where('competitor_analyses', array('id' => (int) $id))->row();
		if ( ! $row) {
			return null;
		}
		$row->inclusions = $this->decode_list($row->inclusions);
		$row->pros       = $this->decode_list($row->pros);
		$row->cons       = $this->decode_list($row->cons);

		// Combined site-crawl row: an array of full product records. Empty array
		// for classic single/upload rows so the view can branch on it.
		$products = json_decode((string) $row->products_json, true);
		$row->products = is_array($products) ? $products : array();

		$details = json_decode((string) $row->details_json, true);
		if ( ! is_array($details)) {
			$details = array();
		}
		$scalar_keys = array('price_from', 'price_to', 'departure_city', 'flight_departure',
			'flight_return', 'difficulty', 'target_traveller', 'suitable_age',
			'child_friendly', 'senior_friendly');
		foreach ($this->detail_keys as $k) {
			if ($k === 'meals') {
				$m = isset($details['meals']) && is_array($details['meals']) ? $details['meals'] : array();
				$row->meals = array(
					'breakfast' => isset($m['breakfast']) ? $m['breakfast'] : '',
					'lunch'     => isset($m['lunch']) ? $m['lunch'] : '',
					'dinner'    => isset($m['dinner']) ? $m['dinner'] : '',
				);
			} elseif (in_array($k, $scalar_keys, true)) {
				$row->$k = isset($details[$k]) ? (string) $details[$k] : '';
			} else {
				$row->$k = isset($details[$k]) && is_array($details[$k]) ? $details[$k] : array();
			}
		}
		return $row;
	}

	function Delete($id)
	{
		$this->db->delete('competitor_analyses', array('id' => (int) $id));
		return $this->db->affected_rows() > 0;
	}

	private function decode_list($value)
	{
		$arr = json_decode((string) $value, true);
		return is_array($arr) ? $arr : array();
	}
}
