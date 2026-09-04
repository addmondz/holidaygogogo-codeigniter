<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * "Extract from link" for the Product form. The user pastes a tour page URL and
 * clicks Extract; we scrape the page and ask OpenAI to return the tour detail +
 * structured flights in OUR product shape, which the form then auto-fills.
 *
 * This file holds the PURE, DB/network-free halves so they can be unit tested:
 *   - product_extract_contract()      the JSON shape the AI must return
 *   - product_extract_agent_input()   instructions + input for the Responses API
 *   - product_extract_decode()        AI reply text -> associative array (fence-safe)
 *   - product_extract_map()           AI JSON -> {fields: {Col=>value}, flights: []}
 *
 * The OpenAI call + scraping reuse libraries/CompetitorAnalysisService.
 * Depends on product_tour_fields_helper (product_flights_decode).
 */

if ( ! function_exists('product_extract_contract'))
{
	/**
	 * The exact JSON object the model must return — keys map straight onto our
	 * product columns (lists become one-item-per-line text on our side) plus a
	 * structured `flights` array matching the Flights tab.
	 */
	function product_extract_contract()
	{
		$shape = '{'
			. '"destination": string (main destination label), '
			. '"duration": string (e.g. "3D2N"), '
			. '"departure_city": string (where the tour departs from), '
			. '"difficulty": string (INFER e.g. "Easy","Moderate","Challenging"), '
			. '"suitable_age": string (INFER suitable age range), '
			. '"target_traveller": string (INFER e.g. "Families","Couples","Seniors"), '
			. '"tour_style": string (INFER e.g. "Group tour","Free & easy","Luxury"), '
			. '"countries": string[] (every country visited), '
			. '"cities": string[] (every city/town visited), '
			. '"themes": string[] (INFER trip themes e.g. "Nature","Beach","Culture"), '
			. '"local_transport": string[] (coach, ferry, speedboat, train, etc.), '
			. '"meals": {"breakfast": string, "lunch": string, "dinner": string} (for each, the COUNT + days it is provided e.g. "2 (Day 1,2)"; "" if never provided), '
			. '"hotels": string[] (hotels/resorts/accommodation named), '
			. '"scenic_highlights": string[] (INFER key scenic/sightseeing highlights), '
			. '"shopping_stops": string[] (shopping/market/factory stops), '
			. '"inclusions": string[] (what the price includes), '
			. '"exclusions": string[] (what is NOT included), '
			. '"optional_tours": string[] (each optional/add-on tour verbatim WITH price/conditions), '
			. '"itinerary": [{"day": string, "title": string, "description": string}] (one entry per day; description lists ALL places/activities that day), '
			. '"special_remarks": string[] (every important note/term/condition, each separately), '
			. '"flights": [{"direction": string ("Outbound","Return" or "Domestic"), "airline": string, "flight_no": string, "from": string, "to": string, "depart": string (date + time), "arrive": string (date + time)}] (one entry per flight leg; [] if none stated)'
			. '}';
		return "Reply with ONLY a single JSON object, no markdown, no code fences, matching exactly this shape: "
			. $shape . ". "
			. "Fields marked INFER: reason them from the itinerary/content even when not stated. "
			. "For every other field use \"\" or [] when the source does not state it — never invent literal facts like prices, hotel names or flight numbers.";
	}
}

if ( ! function_exists('product_extract_agent_input'))
{
	/**
	 * Build the Responses API {instructions, input} for a product extraction.
	 * When $use_web_search is true the scraped text was thin (a JS page), so the
	 * agent is told to open the URL itself and fill the gaps.
	 */
	function product_extract_agent_input($url, $scraped_text = '', $use_web_search = false)
	{
		$url = trim((string) $url);
		$scraped_text = trim((string) $scraped_text);

		$instructions = "You extract a single travel tour/package from a web page into structured JSON for our product database. "
			. "Read the PRODUCT PAGE CONTENT below and pull out the tour detail and flights. ";
		if ($use_web_search) {
			$instructions .= "The scraped text may be incomplete — use web_search to open the URL and fill in any gaps (itinerary, inclusions, flights). ";
		}
		$instructions .= product_extract_contract();

		$input = "PRODUCT PAGE URL: " . $url . "\n\n";
		if ($scraped_text !== '') {
			$input .= "PRODUCT PAGE CONTENT:\n" . $scraped_text . "\n";
		} else {
			$input .= "(No page text could be scraped — open the URL to read it.)\n";
		}
		return array('instructions' => $instructions, 'input' => $input);
	}
}

if ( ! function_exists('product_extract_decode'))
{
	/**
	 * Turn the model's reply text into an associative array. Tolerates ```json
	 * code fences and leading/trailing prose by grabbing the outermost {...}.
	 * Returns null when nothing parseable is found.
	 *
	 * @param string $raw
	 * @return array|null
	 */
	function product_extract_decode($raw)
	{
		$raw = trim((string) $raw);
		if ($raw === '') {
			return null;
		}
		// Strip a ```json ... ``` (or bare ```) fence if present.
		if (strpos($raw, '```') !== false) {
			$raw = preg_replace('/^```[a-zA-Z]*\s*/', '', $raw);
			$raw = preg_replace('/\s*```$/', '', $raw);
			$raw = trim($raw);
		}
		$data = json_decode($raw, true);
		if (is_array($data)) {
			return $data;
		}
		// Fall back to the outermost {...} span.
		$start = strpos($raw, '{');
		$end   = strrpos($raw, '}');
		if ($start !== false && $end !== false && $end > $start) {
			$data = json_decode(substr($raw, $start, $end - $start + 1), true);
			if (is_array($data)) {
				return $data;
			}
		}
		return null;
	}
}

if ( ! function_exists('product_extract_map'))
{
	/**
	 * Map decoded AI JSON to our product fields. Returns:
	 *   ['fields'  => ['Destination'=>..., 'Itinerary'=>..., ...],   // only non-empty
	 *    'flights' => [ {direction,airline,...}, ... ]]              // normalised
	 * String lists become newline-joined text (one item per line). Empty values
	 * are dropped so the form only overwrites fields we actually extracted.
	 *
	 * @param array $data
	 * @return array
	 */
	function product_extract_map($data)
	{
		$fields = array();
		if ( ! is_array($data)) {
			return array('fields' => $fields, 'flights' => array());
		}

		$flatten = function ($v) {
			// string -> trimmed; array -> non-empty trimmed lines joined by \n.
			if (is_array($v)) {
				$lines = array();
				foreach ($v as $item) {
					if (is_array($item)) {
						$item = trim(implode(' ', array_map('strval', $item)));
					} else {
						$item = trim((string) $item);
					}
					if ($item !== '') {
						$lines[] = $item;
					}
				}
				return implode("\n", $lines);
			}
			return trim((string) $v);
		};

		// AI key => product column. Scalars + string lists share one map.
		$map = array(
			'destination'      => 'Destination',
			'duration'         => 'Duration',
			'departure_city'   => 'DepartureCity',
			'difficulty'       => 'Difficulty',
			'suitable_age'     => 'SuitableAge',
			'target_traveller' => 'TargetTraveller',
			'tour_style'       => 'TourStyle',
			'countries'        => 'Countries',
			'cities'           => 'Cities',
			'themes'           => 'Themes',
			'local_transport'  => 'LocalTransport',
			'hotels'           => 'Hotels',
			'scenic_highlights'=> 'ScenicHighlights',
			'shopping_stops'   => 'ShoppingStops',
			'inclusions'       => 'Inclusions',
			'exclusions'       => 'Exclusions',
			'optional_tours'   => 'OptionalTours',
			'special_remarks'  => 'SpecialRemarks',
		);
		foreach ($map as $key => $col) {
			if (isset($data[$key])) {
				$val = $flatten($data[$key]);
				if ($val !== '') {
					$fields[$col] = $val;
				}
			}
		}

		// Meals object -> three columns.
		if (isset($data['meals']) && is_array($data['meals'])) {
			foreach (array('breakfast' => 'MealBreakfast', 'lunch' => 'MealLunch', 'dinner' => 'MealDinner') as $k => $col) {
				if (isset($data['meals'][$k]) && trim((string) $data['meals'][$k]) !== '') {
					$fields[$col] = trim((string) $data['meals'][$k]);
				}
			}
		}

		// Itinerary array -> "Day X: Title — Description" lines.
		if (isset($data['itinerary']) && is_array($data['itinerary'])) {
			$lines = array();
			$n = 0;
			foreach ($data['itinerary'] as $day) {
				$n++;
				if ( ! is_array($day)) {
					$t = trim((string) $day);
					if ($t !== '') { $lines[] = $t; }
					continue;
				}
				$label = isset($day['day']) && trim((string) $day['day']) !== '' ? trim((string) $day['day']) : (string) $n;
				if (stripos($label, 'day') === false) {
					$label = 'Day ' . $label;
				}
				$title = isset($day['title']) ? trim((string) $day['title']) : '';
				$desc  = isset($day['description']) ? trim((string) $day['description']) : '';
				$line = $label;
				if ($title !== '') { $line .= ': ' . $title; }
				if ($desc !== '')  { $line .= ($title !== '' ? ' — ' : ': ') . $desc; }
				$lines[] = $line;
			}
			$lines = array_values(array_filter($lines, function ($x) { return $x !== ''; }));
			if ( ! empty($lines)) {
				$fields['Itinerary'] = implode("\n", $lines);
			}
		}

		// Flights -> normalised structured rows (reuse the Flights-tab decoder).
		$flights = array();
		if (isset($data['flights']) && function_exists('product_flights_decode')) {
			$flights = product_flights_decode($data['flights']);
		}

		return array('fields' => $fields, 'flights' => $flights);
	}
}
