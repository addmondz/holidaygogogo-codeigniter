<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Competitor Analysis — pure, DB-less helpers shared by the controller, the
 * OpenAI service and the unit test. Anything that can be reasoned about without
 * a network call or the database lives here so it can be locked by
 * tests/helpers/CompetitorAnalysisHelperTest.php.
 *
 * The AI agent does its own scraping: we only hand OpenAI the URL and our own
 * products, and the built-in web_search tool opens and reads the page. So the
 * helpers here shape the request and normalise the reply — there is no HTML
 * fetching/stripping on our side.
 *
 *   competitor_format_our_products()  dashboard rows -> compact "our products"
 *   competitor_build_agent_input()    url + products -> {instructions, input}
 *   competitor_extract_responses_text() Responses API JSON -> assistant text
 *   competitor_extract_usage()        Responses API JSON -> {input,output} tokens
 *   competitor_estimate_cost()        model + tokens   -> USD cost estimate
 *   competitor_parse_ai_response()    assistant text -> flat, DB-ready record
 *
 * The OpenAI HTTP call itself lives in libraries/CompetitorAnalysisService.php.
 */

if ( ! function_exists('competitor_format_our_products'))
{
	/**
	 * Flatten "our products" into a compact list for the OUR PRODUCTS prompt
	 * block. Handles two inputs:
	 *   - Product_Model::Read_For_Comparison() rows (the current source): our own
	 *     products enriched with tour detail (destination, duration, itinerary,
	 *     inclusions, flights, ...) so the AI can compare like-for-like.
	 *   - Legacy Costing dashboard (['packages'=>[...]] or a bare list) — name,
	 *     tour_code and the latest booking's per-pax selling price.
	 * Tolerates arrays or objects. Empty fields are omitted downstream by
	 * competitor_products_block() to keep prompt tokens (and cost) low.
	 */
	function competitor_format_our_products($source, $limit = 40)
	{
		$rows = array();
		if (is_array($source) && isset($source['packages'])) {
			$rows = $source['packages'];
		} elseif (is_array($source)) {
			$rows = $source;
		}

		// Product columns (from Read_For_Comparison) -> compact prompt keys.
		$single_map = array(
			'Destination' => 'destination', 'Duration' => 'duration',
			'DepartureCity' => 'departure_city', 'Difficulty' => 'difficulty',
			'SuitableAge' => 'suitable_age', 'TargetTraveller' => 'target_traveller',
			'TourStyle' => 'tour_style',
		);
		$list_map = array(
			'Countries' => 'countries', 'Cities' => 'cities', 'Themes' => 'themes',
			'LocalTransport' => 'local_transport', 'Hotels' => 'hotels',
			'ScenicHighlights' => 'scenic_highlights', 'ShoppingStops' => 'shopping_stops',
			'Inclusions' => 'inclusions', 'Exclusions' => 'exclusions',
			'OptionalTours' => 'optional_tours', 'Itinerary' => 'itinerary',
			'SpecialRemarks' => 'special_remarks',
		);

		$out = array();
		foreach ($rows as $p) {
			$p = (array) $p;
			$is_product = array_key_exists('Name', $p) || array_key_exists('ProductCode', $p) || array_key_exists('RetailPrice', $p);

			if ( ! $is_product) {
				// Legacy costing package shape.
				$name = isset($p['name']) ? trim((string) $p['name']) : '';
				if ($name === '') {
					continue;
				}
				$price = null;
				if ( ! empty($p['bookings'])) {
					$first = (array) reset($p['bookings']);
					if (isset($first['selling_price_per_pax']) && $first['selling_price_per_pax'] !== null && $first['selling_price_per_pax'] !== '') {
						$price = (float) $first['selling_price_per_pax'];
					}
				}
				$out[] = array(
					'name'      => $name,
					'tour_code' => isset($p['tour_code']) ? (string) $p['tour_code'] : '',
					'price_myr' => $price,
				);
			} else {
				// Our own product enriched with tour detail.
				$name = isset($p['Name']) ? trim((string) $p['Name']) : '';
				if ($name === '') {
					continue;
				}
				$item = array('name' => $name);
				$code = isset($p['ProductCode']) ? trim((string) $p['ProductCode']) : '';
				if ($code !== '') {
					$item['tour_code'] = $code;
				}
				if (isset($p['RetailPrice']) && $p['RetailPrice'] !== null && $p['RetailPrice'] !== '' && (float) $p['RetailPrice'] > 0) {
					$item['price_myr'] = (float) $p['RetailPrice'];
				}
				foreach ($single_map as $col => $key) {
					if (isset($p[$col]) && trim((string) $p[$col]) !== '') {
						$item[$key] = trim((string) $p[$col]);
					}
				}
				$meals = array();
				foreach (array('MealBreakfast' => 'breakfast', 'MealLunch' => 'lunch', 'MealDinner' => 'dinner') as $col => $key) {
					if (isset($p[$col]) && trim((string) $p[$col]) !== '') {
						$meals[$key] = trim((string) $p[$col]);
					}
				}
				if ( ! empty($meals)) {
					$item['meals'] = $meals;
				}
				foreach ($list_map as $col => $key) {
					if (isset($p[$col]) && trim((string) $p[$col]) !== '') {
						$lines = preg_split('/\r\n|\r|\n/', trim((string) $p[$col]));
						$lines = array_values(array_filter(array_map('trim', $lines), function ($x) { return $x !== ''; }));
						if ( ! empty($lines)) {
							$item[$key] = $lines;
						}
					}
				}
				// Multiple structured flights -> readable one-liners.
				if (isset($p['Flights']) && trim((string) $p['Flights']) !== '' && function_exists('product_flights_decode')) {
					$flight_lines = array();
					foreach (product_flights_decode($p['Flights']) as $fl) {
						$s = product_flight_summary($fl);
						if ($s !== '') {
							$flight_lines[] = $s;
						}
					}
					if ( ! empty($flight_lines)) {
						$item['flights'] = $flight_lines;
					}
				}
				$out[] = $item;
			}

			if ($limit > 0 && count($out) >= $limit) {
				break;
			}
		}
		return $out;
	}
}

if ( ! function_exists('competitor_output_contract'))
{
	/**
	 * The shared JSON-shape contract every mode (URL / PDF / image) must obey,
	 * so competitor_parse_ai_response() reads back the same fields regardless of
	 * how the competitor product was supplied.
	 */
	function competitor_output_contract()
	{
		$schema_hint = '{'
			. '"product_name": string, '
			. '"tour_code": string (competitor product/reference code, "" if none), '
			. '"price": string (headline price with currency as shown, "" if unknown), '
			. '"price_from": string (lowest price e.g. "RM1999", "" if unknown), '
			. '"price_to": string (highest price, "" if unknown), '
			. '"currency": string (ISO code if identifiable, else ""), '
			. '"destination": string (main destination label), '
			. '"countries": string[] (every country visited), '
			. '"cities": string[] (every city/town visited), '
			. '"duration": string (days/nights e.g. "5D4N"), '
			. '"travel_months": string[] (departure months/dates offered), '
			. '"departure_city": string (where the tour departs from), '
			. '"flight_departure": string (FULL outbound flight detail EXACTLY as given: airline + flight no + from/to airports + date + time for every outbound leg, joining connecting legs with " -> "; "" if none), '
			. '"flight_return": string (FULL return flight detail the same way, all legs; "" if none), '
			. '"themes": string[] (INFER trip themes e.g. "Nature","Culture","Shopping"), '
			. '"tour_styles": string[] (INFER e.g. "Group tour","Free & easy","Luxury"), '
			. '"difficulty": string (INFER physical difficulty e.g. "Easy","Moderate","Challenging"), '
			. '"local_transport": string[] (coach, bullet train, cruise, ferry, etc.), '
			. '"inclusions": string[] (what the price includes), '
			. '"exclusions": string[] (what is NOT included), '
			. '"hotels": string[] (hotels/accommodation named), '
			. '"meals": {"breakfast": string, "lunch": string, "dinner": string} (for each meal, work through the day-by-day meal plan and give the COUNT plus the exact days it is provided e.g. "3 (Day 2,3,4)"; add special cuisine in parentheses when named; "" if the plan never provides that meal — do not guess a count), '
			. '"shopping_stops": string[] (shopping/factory stops), '
			. '"optional_tours": string[] (EVERY optional/add-on tour listed, each one verbatim WITH its price and conditions e.g. min pax / what is included — never drop, merge or summarise any), '
			. '"special_remarks": string[] (EVERY important note/term/condition, each listed separately — e.g. guide/commentary language, nationality restriction, room & single-supplement rules, insurance, disclaimers; do not omit any), '
			. '"scenic_highlights": string[] (INFER key scenic/sightseeing highlights), '
			. '"signature_meals": string[] (INFER notable/signature meals featured), '
			. '"target_traveller": string (INFER ideal traveller e.g. "Families","Seniors","Couples"), '
			. '"suitable_age": string (INFER suitable age range), '
			. '"child_friendly": string (INFER "Yes"/"No" + short reason), '
			. '"senior_friendly": string (INFER "Yes"/"No" + short reason), '
			. '"usp": string[] (INFER unique selling points for the target traveller), '
			. '"itinerary": [{"day": string, "title": string, "description": string}] (one entry per day; the description must list ALL places/activities visited that day, not just a few), '
			. '"pros": string[], '
			. '"cons": string[], '
			. '"summary": string (2-4 sentence overview), '
			. '"comparison": string (FIRST find a product in OUR PRODUCTS below that MATCHES or is SIMILAR to this competitor product — same/overlapping destination or tour type. If one matches, compare against THAT product only: pricing, value, gaps, and a recommendation. If NONE of our products match or are similar, do NOT force a comparison — state plainly that we have no comparable product on our side for this destination/type.), '
			. '"matched_product": string (the EXACT "name" from OUR PRODUCTS you compared against in "comparison"; "" if none matched)'
			. '}';
		return "Reply with ONLY a single JSON object, no markdown, no code fences, matching exactly this shape: "
			. $schema_hint . ". "
			. "Fields marked INFER: reason them from the itinerary/content even when not stated outright. "
			. "For every other field use \"\" or [] when the source does not state it — never invent literal facts like prices or hotel names.";
	}
}

if ( ! function_exists('competitor_products_block'))
{
	/**
	 * Compact JSON of our own products for the "OUR PRODUCTS" prompt block.
	 * To minimise input tokens (and cost) on every OpenAI call we strip dead
	 * weight: keep `name` always, include `tour_code` only when non-empty and
	 * `price_myr` only when known. Null prices / empty codes carry no signal for
	 * the comparison, so we never pay to send them.
	 */
	function competitor_products_block($our_products)
	{
		$rows = is_array($our_products) ? $our_products : array();
		$compact = array();
		foreach ($rows as $p) {
			$p = (array) $p;
			$name = isset($p['name']) ? trim((string) $p['name']) : '';
			if ($name === '') {
				continue;
			}
			// Keep `name` always; drop every empty field (null, '', []) — they
			// carry no signal for the comparison, so we never pay to send them.
			$item = array('name' => $name);
			foreach ($p as $k => $v) {
				if ($k === 'name') {
					continue;
				}
				if ($v === null || $v === '' || (is_array($v) && count($v) === 0)) {
					continue;
				}
				$item[$k] = $v;
			}
			$compact[] = $item;
		}
		return json_encode($compact, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	}
}

if ( ! function_exists('competitor_build_agent_input'))
{
	/**
	 * Build the Responses API request pieces for the URL/browsing agent — the
	 * fallback for a JavaScript SPA our own scraper couldn't read. The agent opens
	 * and reads the page itself via the web_search tool. When we DID manage to
	 * scrape partial content (e.g. SEO JSON-LD / metadata on an otherwise-blank SPA
	 * shell), pass it as $partial_text so the agent starts from it and browses only
	 * to FILL THE GAPS (full itinerary, prices, inclusions) — a hybrid that costs
	 * less browsing and grounds the answer in real page data.
	 * Returns ['instructions' => system prompt, 'input' => user text].
	 */
	function competitor_build_agent_input($url, $our_products, $partial_text = '')
	{
		$partial_text = trim((string) $partial_text);
		$instructions = "You are a travel-industry competitor analyst for HolidayGoGoGo, a Malaysian tour operator. "
			. "The competitor page is a JavaScript app our scraper could not fully read. "
			. "Use the web_search tool to OPEN and READ the competitor product page at the URL the user gives — "
			. "scrape its full content yourself; do not rely on prior knowledge. "
			. ($partial_text !== ''
				? "Partial content we already scraped is provided; treat it as a starting point and use web_search to FILL IN the missing full details (day-by-day itinerary, prices, inclusions). "
				: "")
			. "Then extract the competitor product's details and compare it against the user's own products. "
			. competitor_output_contract();

		$input = "COMPETITOR URL (open and scrape this page):\n" . $url . "\n\n";
		if ($partial_text !== '') {
			$input .= "PARTIAL CONTENT WE ALREADY SCRAPED (may be incomplete — verify/complete it by browsing):\n"
				. $partial_text . "\n\n";
		}
		$input .= "OUR PRODUCTS (JSON, prices in MYR):\n" . competitor_products_block($our_products);

		return array('instructions' => $instructions, 'input' => $input);
	}
}

if ( ! function_exists('competitor_html_to_text'))
{
	/**
	 * Reduce raw page HTML to clean, readable text for the AI — our own scraper's
	 * output. Drops noise blocks (script/style/head/svg) and comments, turns block
	 * boundaries into newlines so the itinerary keeps its shape, strips remaining
	 * tags, decodes entities, and collapses whitespace. Capped at $max_chars to
	 * bound token cost (0 = uncapped). Pure — the network fetch lives in the
	 * service. Returns '' for non-strings/empties.
	 */
	function competitor_html_to_text($html, $max_chars = 40000)
	{
		if ( ! is_string($html) || $html === '') {
			return '';
		}
		// Whole blocks whose content is noise (incl. site nav/footer/sidebar chrome,
		// search/booking widgets), plus HTML comments.
		$html = preg_replace('#<(script|style|noscript|template|svg|head|nav|footer|aside|form)\b[^>]*>.*?</\1>#is', ' ', $html);
		$html = preg_replace('#<!--.*?-->#s', ' ', $html);
		// Preserve structure: block/line boundaries become newlines; table cells
		// get a " | " separator so a row's fields don't run together.
		$html = preg_replace('#<br\s*/?>#i', "\n", $html);
		$html = preg_replace('#</(td|th)>#i', ' | ', $html);
		$html = preg_replace('#</(p|div|li|tr|h[1-6]|section|article|ul|ol|table|header|footer)>#i', "\n", $html);
		$text = strip_tags($html);
		$text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		// Collapse spaces/tabs/nbsp, trim each line, tidy cell separators, drop blanks.
		$text = preg_replace('/[ \t\x{00A0}]+/u', ' ', $text);
		$out = array();
		$prev = null;
		foreach (preg_split('/\r\n|\r|\n/', $text) as $ln) {
			$ln = preg_replace('/\s*\|\s*/', ' | ', $ln);
			$ln = trim($ln, " |");
			// Drop blanks and consecutive duplicate lines (repeated menu/CTA chrome).
			if ($ln !== '' && $ln !== $prev) {
				$out[] = $ln;
				$prev  = $ln;
			}
		}
		$text = implode("\n", $out);
		if ($max_chars > 0 && mb_strlen($text, 'UTF-8') > $max_chars) {
			$text = mb_substr($text, 0, $max_chars, 'UTF-8');
		}
		return $text;
	}
}

if ( ! function_exists('competitor_scrape_is_thin'))
{
	/**
	 * True when our scrape returned too little usable text to analyse — the tell
	 * for a JS-rendered page that plain fetching can't read. The service falls
	 * back to the web_search agent in that case. Pure.
	 */
	function competitor_scrape_is_thin($text, $min_chars = 500)
	{
		return mb_strlen(trim((string) $text), 'UTF-8') < (int) $min_chars;
	}
}

if ( ! function_exists('competitor_flatten_json_text'))
{
	/**
	 * Flatten a decoded JSON value (from JSON-LD or a framework state blob) into
	 * readable lines for the AI, keeping only human-meaningful scalars. Skips
	 * structural/noise keys (@context, @type, id, url, image, css class…) and
	 * values that carry no prose signal (pure urls, asset paths, booleans, empty);
	 * bare numbers are kept only under a price-like key. Recurses arrays/objects,
	 * bounded against pathological input by depth + node caps. Pure.
	 */
	function competitor_flatten_json_text($data, $depth = 0)
	{
		static $noise_keys = array('@context', '@type', '@id', 'id', 'url', 'href',
			'src', 'image', 'images', 'icon', 'logo', 'thumbnail', 'class', 'classname',
			'style', 'type', 'key', 'slug', 'width', 'height', 'color', 'background', 'sku');
		if ($depth > 8 || ! is_array($data)) {
			return '';
		}
		$lines = array();
		$count = 0;
		foreach ($data as $k => $v) {
			if (++$count > 5000) {
				break;
			}
			$key = is_string($k) ? strtolower($k) : '';
			if ($key !== '' && in_array($key, $noise_keys, true)) {
				continue;
			}
			if (is_array($v)) {
				$sub = competitor_flatten_json_text($v, $depth + 1);
				if ($sub !== '') {
					$lines[] = $sub;
				}
				continue;
			}
			if (is_bool($v) || $v === null) {
				continue;
			}
			$val = trim((string) $v);
			if ($val === '') {
				continue;
			}
			// Drop pure urls / asset paths — no prose signal, just noise + tokens.
			if (preg_match('#^(https?:)?//#i', $val)
				|| preg_match('#\.(png|jpe?g|gif|webp|svg|css|js|ico)(\?|$)#i', $val)) {
				continue;
			}
			// Bare numbers are noise unless the key names a price/amount.
			$is_price_key = $key !== '' && preg_match('/price|amount|cost|fare|rate/', $key);
			if ( ! preg_match('/\p{L}/u', $val) && ! $is_price_key) {
				continue;
			}
			$line = ($key !== '' ? $k . ': ' : '') . $val;
			$lines[] = trim(preg_replace('/\s+/', ' ', $line));
		}
		return implode("\n", array_filter($lines, 'strlen'));
	}
}

if ( ! function_exists('competitor_extract_meta_text'))
{
	/**
	 * Pull OpenGraph / SEO meta tags into readable lines — the thin-but-free
	 * fallback when a page carries no JSON-LD or state blob. Reads <title>,
	 * og:title/description/site_name, meta description and product/og price meta.
	 * Deduped, order-preserving. Pure.
	 */
	function competitor_extract_meta_text($html)
	{
		if ( ! is_string($html) || $html === '') {
			return '';
		}
		$lines = array();
		if (preg_match('#<title[^>]*>(.*?)</title>#is', $html, $m)) {
			$t = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
			if ($t !== '') {
				$lines[] = 'Title: ' . $t;
			}
		}
		$wanted = array(
			'og:title'              => 'Title',
			'og:description'        => 'Description',
			'og:site_name'          => 'Site',
			'description'           => 'Description',
			'product:price:amount'  => 'Price',
			'product:price:currency'=> 'Currency',
			'og:price:amount'       => 'Price',
			'og:price:currency'     => 'Currency',
		);
		if (preg_match_all('#<meta\b[^>]*>#i', $html, $tags)) {
			foreach ($tags[0] as $tag) {
				$name = '';
				if (preg_match('#(?:property|name)\s*=\s*(["\'])(.*?)\1#i', $tag, $nm)) {
					$name = strtolower(trim($nm[2]));
				}
				if ($name === '' || ! isset($wanted[$name])) {
					continue;
				}
				if (preg_match('#content\s*=\s*(["\'])(.*?)\1#is', $tag, $cm)) {
					$val = trim(html_entity_decode($cm[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
					if ($val !== '') {
						$lines[] = $wanted[$name] . ': ' . $val;
					}
				}
			}
		}
		$seen = array();
		$out  = array();
		foreach ($lines as $l) {
			$key = mb_strtolower($l, 'UTF-8');
			if (isset($seen[$key])) {
				continue;
			}
			$seen[$key] = true;
			$out[] = $l;
		}
		return implode("\n", $out);
	}
}

if ( ! function_exists('competitor_jsonld_product_text'))
{
	/**
	 * Extract ONLY the travel-PRODUCT schema.org JSON-LD from a page
	 * (<script type="application/ld+json">), flattened to readable text — the
	 * @type is Product/Trip/TouristTrip/TouristAttraction/TouristDestination/Offer/
	 * AggregateOffer/Event. Handles a bare node, an array of nodes, or an @graph.
	 * Ignores non-product JSON-LD (WebSite/BreadcrumbList/Organization/…). Deduped.
	 * Used to LEAD the source text with clean structured facts even when the HTML
	 * itself isn't thin (noisy SPA shells otherwise bury the real data). Returns ''
	 * when no product JSON-LD is present. Pure.
	 */
	function competitor_jsonld_product_text($html)
	{
		if ( ! is_string($html) || $html === '') {
			return '';
		}
		if ( ! preg_match_all('#<script[^>]*type\s*=\s*(["\'])application/ld\+json\1[^>]*>(.*?)</script>#is', $html, $m)) {
			return '';
		}
		$wanted = array('product', 'trip', 'touristtrip', 'touristattraction',
			'touristdestination', 'offer', 'aggregateoffer', 'event', 'tourpackage');

		$lines = array();
		$seen  = array();
		foreach ($m[2] as $blob) {
			$data = json_decode(trim($blob), true);
			if ( ! is_array($data)) {
				continue;
			}
			// Candidate nodes: an @graph, a bare list, or the single object.
			$nodes = array();
			if (isset($data['@graph']) && is_array($data['@graph'])) {
				$nodes = $data['@graph'];
			} elseif (array_keys($data) === range(0, count($data) - 1)) {
				$nodes = $data;
			} else {
				$nodes = array($data);
			}
			foreach ($nodes as $node) {
				if ( ! is_array($node) || ! isset($node['@type'])) {
					continue;
				}
				$types = is_array($node['@type']) ? $node['@type'] : array($node['@type']);
				$match = false;
				foreach ($types as $t) {
					if (in_array(strtolower(trim((string) $t)), $wanted, true)) {
						$match = true;
						break;
					}
				}
				if ( ! $match) {
					continue;
				}
				foreach (preg_split('/\r\n|\r|\n/', competitor_flatten_json_text($node)) as $ln) {
					$ln  = trim($ln);
					$key = mb_strtolower($ln, 'UTF-8');
					if ($ln === '' || isset($seen[$key])) {
						continue;
					}
					$seen[$key] = true;
					$lines[] = $ln;
				}
			}
		}
		return implode("\n", $lines);
	}
}

if ( ! function_exists('competitor_jsonld_types'))
{
	/**
	 * All schema.org @type values declared in a page's JSON-LD blocks, lowercased
	 * (nodes inside @graph and bare lists included). Lets us tell a single product
	 * page from a category/listing page. Pure. Returns [] when none.
	 */
	function competitor_jsonld_types($html)
	{
		if ( ! is_string($html) || $html === '') {
			return array();
		}
		if ( ! preg_match_all('#<script[^>]*type\s*=\s*(["\'])application/ld\+json\1[^>]*>(.*?)</script>#is', $html, $m)) {
			return array();
		}
		$types = array();
		foreach ($m[2] as $blob) {
			$data = json_decode(trim($blob), true);
			if ( ! is_array($data)) {
				continue;
			}
			if (isset($data['@graph']) && is_array($data['@graph'])) {
				$nodes = $data['@graph'];
			} elseif (array_keys($data) === range(0, count($data) - 1)) {
				$nodes = $data;
			} else {
				$nodes = array($data);
			}
			foreach ($nodes as $node) {
				if ( ! is_array($node) || ! isset($node['@type'])) {
					continue;
				}
				foreach ((is_array($node['@type']) ? $node['@type'] : array($node['@type'])) as $t) {
					$t = strtolower(trim((string) $t));
					if ($t !== '') {
						$types[$t] = true;
					}
				}
			}
		}
		return array_keys($types);
	}
}

if ( ! function_exists('competitor_looks_like_listing'))
{
	/**
	 * True when a page's JSON-LD marks it as a category/listing/search page
	 * (ItemList, CollectionPage, SearchResultsPage…) and NOT a single product —
	 * so we can drop it instead of analysing a whole catalogue as one "product".
	 * A page that ALSO carries a product type is kept (product wins). Pure.
	 */
	function competitor_looks_like_listing($types)
	{
		if ( ! is_array($types) || empty($types)) {
			return false;   // no evidence → don't drop
		}
		$listing = array('itemlist', 'collectionpage', 'searchresultspage', 'offercatalog');
		$product = array('product', 'trip', 'touristtrip', 'touristattraction',
			'touristdestination', 'offer', 'aggregateoffer', 'event', 'tourpackage');
		$has_listing = (bool) array_intersect($types, $listing);
		$has_product = (bool) array_intersect($types, $product);
		return $has_listing && ! $has_product;
	}
}

if ( ! function_exists('competitor_text_looks_like_listing'))
{
	/**
	 * Content-level catch for a CATALOGUE page that had no listing JSON-LD to flag it
	 * — a page that lays out SEVERAL separate itineraries (each restarting at "Day 1").
	 * A single tour has exactly one "Day 1"; only a multi-product listing/comparison
	 * page repeats it. True = looks like a multi-product listing, so the service drops
	 * it. Pure.
	 *
	 * NOTE: price count is deliberately NOT used — a single tour with a departure
	 * calendar legitimately shows many distinct prices (e.g. Trip Yunnan-9D7N has 14),
	 * so a price threshold wrongly drops real products.
	 */
	function competitor_text_looks_like_listing($text, $min_itineraries = 3)
	{
		if ( ! is_string($text) || $text === '') {
			return false;
		}
		// Several separate itineraries on one page (each product restarts at "Day 1").
		return preg_match_all('/\bday\s*0?1\b/iu', $text) >= $min_itineraries;
	}
}

if ( ! function_exists('competitor_has_product_signal'))
{
	/**
	 * True when the text carries a hallmark of a real TOUR PRODUCT — a price, a
	 * duration (5D4N / "8 days" / "7 nights"), a day-by-day itinerary, inclusions, or
	 * the schema.org product marker. Used to tell a product from a blog article /
	 * info page. Pure.
	 */
	function competitor_has_product_signal($text)
	{
		if ( ! is_string($text) || $text === '') {
			return false;
		}
		return (bool) (
			preg_match('/(?:rm|myr|sgd|usd|eur|php|idr|thb|aud|\$|£|€)\s*[0-9][0-9,]{2,}/iu', $text)   // price
			|| preg_match('/\b\d{1,2}\s*d\s*\d{1,2}\s*n\b/i', $text)                                    // 5D4N
			|| preg_match('/\b\d{1,2}\s*(?:days?|nights?)\b/i', $text)                                  // "8 days"
			|| preg_match('/\bday\s*0?1\b/i', $text)                                                     // itinerary
			|| preg_match('/\b(?:inclusion|exclusion|itinerary|twin\s+share|half\s+board|full\s+board)\b/i', $text)
			|| stripos($text, 'STRUCTURED PRODUCT DATA') !== false
		);
	}
}

if ( ! function_exists('competitor_looks_like_article'))
{
	/**
	 * True when a page is a blog ARTICLE / info page rather than a bookable product —
	 * substantial prose with NO product signal (price / duration / itinerary). Fixes
	 * WordPress content sites (holidaygogogo) where posts like "spice-up-your-holiday…"
	 * carry a product keyword in the URL but are not tours. A THIN page is given the
	 * benefit of the doubt (may be an under-scraped SPA product), so only rich prose
	 * is dropped. Pure.
	 */
	function competitor_looks_like_article($text, $min_len = 1200)
	{
		if ( ! is_string($text) || mb_strlen($text, 'UTF-8') < $min_len) {
			return false;
		}
		return ! competitor_has_product_signal($text);
	}
}

if ( ! function_exists('competitor_json_alternate_url'))
{
	/**
	 * A page can advertise a machine-readable JSON version of itself via
	 * <link rel="alternate" type="application/json" href="…"> (or type ending
	 * +json, e.g. application/ld+json is skipped — that's inline). This returns the
	 * resolved absolute URL of the first such alternate, so a JS-rendered page can
	 * be read from its own JSON feed before falling back to the browsing agent.
	 * Returns '' when none. Pure — the fetch lives in the service.
	 */
	function competitor_json_alternate_url($html, $base_url)
	{
		if ( ! is_string($html) || $html === '') {
			return '';
		}
		if ( ! preg_match_all('#<link\b[^>]*>#i', $html, $m)) {
			return '';
		}
		foreach ($m[0] as $tag) {
			if ( ! preg_match('#\brel\s*=\s*(["\'])\s*alternate\s*\1#i', $tag)) {
				continue;
			}
			if ( ! preg_match('#\btype\s*=\s*(["\'])\s*application/(?:[a-z0-9.+-]*\+)?json\s*\1#i', $tag)) {
				continue;
			}
			if (preg_match('#\bhref\s*=\s*(["\'])(.*?)\1#i', $tag, $h)) {
				$abs = competitor_resolve_url($base_url, $h[2]);
				if ($abs !== '') {
					return $abs;
				}
			}
		}
		return '';
	}
}

if ( ! function_exists('competitor_extract_embedded_json'))
{
	/**
	 * Read the JSON data a JavaScript SPA ships INSIDE its served HTML even when
	 * the visible <body> is an empty shell — the AI-free, browser-free way to read
	 * a JS page. Three sources, richest first:
	 *   1. JSON-LD    <script type="application/ld+json"> (schema.org Product/Trip…)
	 *   2. __NEXT_DATA__ <script id="__NEXT_DATA__" type="application/json"> (Next.js)
	 *   3. OpenGraph / meta tags (og:title, og:description, product:price:amount)
	 * Each JSON blob is flattened via competitor_flatten_json_text(); lines are
	 * deduped (blobs overlap). Returns '' when nothing usable is embedded, so the
	 * caller falls through to the SPA API / web_search. Pure — the fetch lives in
	 * the service. (Extend with __NUXT__/__APOLLO_STATE__ as those platforms appear.)
	 */
	function competitor_extract_embedded_json($html)
	{
		if ( ! is_string($html) || $html === '') {
			return '';
		}
		$parts = array();

		// 1) JSON-LD blocks (may be several per page).
		if (preg_match_all('#<script[^>]*type\s*=\s*(["\'])application/ld\+json\1[^>]*>(.*?)</script>#is', $html, $m)) {
			foreach ($m[2] as $blob) {
				$t = competitor_flatten_json_text(json_decode(trim($blob), true));
				if ($t !== '') {
					$parts[] = $t;
				}
			}
		}

		// 2) Next.js hydration blob.
		if (preg_match('#<script[^>]*id\s*=\s*(["\'])__NEXT_DATA__\1[^>]*>(.*?)</script>#is', $html, $mm)) {
			$t = competitor_flatten_json_text(json_decode(trim($mm[2]), true));
			if ($t !== '') {
				$parts[] = $t;
			}
		}

		// 3) OpenGraph / meta tags.
		$meta = competitor_extract_meta_text($html);
		if ($meta !== '') {
			$parts[] = $meta;
		}

		// Dedupe lines across blobs, drop blanks.
		$seen = array();
		$out  = array();
		foreach ($parts as $chunk) {
			foreach (preg_split('/\r\n|\r|\n/', $chunk) as $ln) {
				$ln  = trim($ln);
				$key = mb_strtolower($ln, 'UTF-8');
				if ($ln === '' || isset($seen[$key])) {
					continue;
				}
				$seen[$key] = true;
				$out[] = $ln;
			}
		}
		return implode("\n", $out);
	}
}

if ( ! function_exists('competitor_resolve_url'))
{
	/**
	 * Resolve an anchor href against the page it was found on into an absolute
	 * URL. Handles absolute, protocol-relative (//host), root-relative (/path)
	 * and directory-relative hrefs; drops the #fragment. Returns '' for empty or
	 * unsupported schemes (mailto:, tel:, javascript:, data:). Pure.
	 */
	function competitor_resolve_url($base, $href)
	{
		$href = trim((string) $href);
		$href = preg_replace('/#.*$/', '', $href);
		if ($href === '') {
			return '';
		}
		if (preg_match('#^(mailto:|tel:|javascript:|data:)#i', $href)) {
			return '';
		}
		if (preg_match('#^https?://#i', $href)) {
			return $href;
		}
		$parts = parse_url((string) $base);
		if (empty($parts['scheme']) || empty($parts['host'])) {
			return '';
		}
		$origin = $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
		if (strpos($href, '//') === 0) {
			return $parts['scheme'] . ':' . $href;
		}
		if (strpos($href, '/') === 0) {
			return $origin . $href;
		}
		$path = isset($parts['path']) ? $parts['path'] : '/';
		$dir  = preg_replace('#/[^/]*$#', '/', $path);
		if ($dir === '') {
			$dir = '/';
		}
		$href = preg_replace('#^\./#', '', $href);
		return $origin . $dir . $href;
	}
}

if ( ! function_exists('competitor_extract_links'))
{
	/**
	 * Pull the distinct, same-host, absolute page links out of a page's HTML —
	 * the crawler's link discovery. Skips off-site links and asset files
	 * (images, css/js, pdf, archives, media, fonts). Order-preserving, deduped.
	 * Pure — the fetch lives in the service.
	 */
	function competitor_extract_links($html, $base_url)
	{
		if ( ! is_string($html) || $html === '') {
			return array();
		}
		$base_host = parse_url((string) $base_url, PHP_URL_HOST);
		if ( ! $base_host) {
			return array();
		}
		$asset = '#\.(jpe?g|png|gif|webp|svg|css|js|ico|pdf|zip|rar|gz|mp4|mp3|avi|mov|webm|woff2?|ttf|eot|xml|json|rss)(\?|$)#i';
		$out = array();
		if (preg_match_all('#<a\b[^>]*\bhref\s*=\s*(?:"([^"]*)"|\'([^\']*)\')#i', $html, $m, PREG_SET_ORDER)) {
			foreach ($m as $mm) {
				$href = ($mm[1] !== '') ? $mm[1] : (isset($mm[2]) ? $mm[2] : '');
				// Decode HTML entities in the href (e.g. &amp; → &) so query-string
				// product URLs (tour.php?a=1&amp;b=2) resolve to a valid, fetchable URL.
				$href = html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8');
				$abs  = competitor_resolve_url($base_url, $href);
				if ($abs === '' || parse_url($abs, PHP_URL_HOST) !== $base_host) {
					continue;
				}
				if (preg_match($asset, $abs)) {
					continue;
				}
				$out[$abs] = true;
			}
		}
		return array_keys($out);
	}
}

if ( ! function_exists('competitor_is_product_url'))
{
	/**
	 * Heuristic: does this URL look like a single tour/product page (not the
	 * homepage, a bare listing, or an about/blog/contact page)? True when the
	 * path carries a product keyword (tour/package/holiday/trip/…), has at least
	 * two path segments (so a bare "/tours" listing is skipped), the last segment
	 * looks like a slug, and no excluded keyword appears. Rules are fixed — the
	 * chosen trade-off is simplicity over per-site accuracy. Pure.
	 */
	function competitor_is_product_url($url)
	{
		$path = parse_url((string) $url, PHP_URL_PATH);
		if ( ! is_string($path) || $path === '') {
			return false;
		}
		$path = strtolower($path);
		$excludes = array('about', 'contact', 'blog', 'news', 'article', 'faq', 'term',
			'privacy', 'policy', 'login', 'signin', 'register', 'signup', 'cart',
			'checkout', 'account', 'career', 'job', 'sitemap', 'wishlist',
			// category / listing hubs (not a single product)
			'travelstyle', 'destination', 'inspiration', 'promotion', 'category', 'catalog');
		foreach ($excludes as $ex) {
			if (strpos($path, $ex) !== false) {
				return false;
			}
		}
		$segments = array_values(array_filter(explode('/', $path), 'strlen'));
		if (empty($segments)) {
			return false;   // homepage
		}
		// WordPress (and common-CMS) taxonomy/archive roots are LISTINGS, not a single
		// product — skip them even when the slug carries a product keyword, e.g.
		// /theme/bagan-datuk-tour/, /tag/china-tour-packages/, /author/x/, /2024/05/….
		// Matched as an exact FIRST segment (substring would nuke 'vintage', 'heritage').
		$taxonomy_roots = array('tag', 'tags', 'category', 'categories', 'author', 'authors',
			'theme', 'themes', 'topic', 'topics', 'type', 'label', 'archive', 'archives');
		if (in_array($segments[0], $taxonomy_roots, true) || preg_match('/^\d{4}$/', $segments[0])) {
			return false;
		}
		// A FLAT permalink (WordPress) puts a product at the root as a specific,
		// multi-word slug (e.g. /3d2n-genting-tour-itinerary/). Require a hyphen for a
		// single-segment path so a bare section hub (/tours, /packages) is NOT taken
		// for a product — a multi-segment path already implies a real sub-page.
		if (count($segments) === 1 && strpos($segments[0], '-') === false) {
			return false;
		}
		$keywords = array('tour', 'package', 'holiday', 'trip', 'itinerary', 'itineraries',
			'vacation', 'getaway', 'cruise', 'product');
		$has_kw = false;
		foreach ($segments as $seg) {
			// A duration code (5d4n / 3d2n) is a strong product signal on its own — so
			// a tour URL that omits the word "tour"/"package" still qualifies.
			if (preg_match('/\d{1,2}d\d{1,2}n/i', $seg)) {
				$has_kw = true;
				break;
			}
			foreach ($keywords as $kw) {
				if (strpos($seg, $kw) !== false) {
					$has_kw = true;
					break 2;
				}
			}
		}
		if ( ! $has_kw) {
			return false;
		}
		$last = end($segments);
		return preg_match('/[a-z]/', $last) === 1;
	}
}

if ( ! function_exists('competitor_is_category_url'))
{
	/**
	 * True when a URL is a CATEGORY / listing page (a page OF products) rather than a
	 * single product — a plural "…-tours/-packages/-holidays" slug, or a known listing
	 * path (listing.php, /category/, /destination/, /travelstyle/, /collections/). The
	 * crawler drills these to their individual products instead of analysing the
	 * catalogue as one item. Pure.
	 */
	function competitor_is_category_url($url)
	{
		$path = strtolower((string) parse_url((string) $url, PHP_URL_PATH));
		if ($path === '') {
			return false;
		}
		if (preg_match('#(?:listing|/category/|/categories/|catalog|/tag/|/destination|/travelstyle|/collections?/)#', $path)) {
			return true;
		}
		$segs = array_values(array_filter(explode('/', $path), 'strlen'));
		if (empty($segs)) {
			return false;
		}
		return (bool) preg_match('/-(?:tours|packages|holidays|cruises|deals|vacations|getaways)$/', end($segs));
	}
}

if ( ! function_exists('competitor_filter_urls_by_keyword'))
{
	/**
	 * Keep only product URLs whose PATH (slug) contains the keyword — so a crawl can
	 * target e.g. "yunnan" or "japan" instead of the whole catalogue. Case-insensitive;
	 * multiple space/comma-separated words match ANY (OR). Matches the path only so the
	 * domain (e.g. "raha" in rahaholidays.com) never matches every URL. Empty keyword =
	 * no filtering (return all). Pure.
	 */
	function competitor_filter_urls_by_keyword($urls, $keyword)
	{
		$keyword = trim((string) $keyword);
		if ($keyword === '' || ! is_array($urls)) {
			return $urls;
		}
		$tokens = array_values(array_filter(
			preg_split('/[\s,]+/', mb_strtolower($keyword, 'UTF-8')),
			'strlen'
		));
		if (empty($tokens)) {
			return $urls;
		}
		$out = array();
		foreach ($urls as $u) {
			$path = mb_strtolower((string) parse_url((string) $u, PHP_URL_PATH), 'UTF-8');
			if ($path === '') {
				continue;
			}
			foreach ($tokens as $t) {
				if (mb_strpos($path, $t) !== false) {
					$out[] = $u;
					break;
				}
			}
		}
		return $out;
	}
}

if ( ! function_exists('competitor_matches_keyword'))
{
	/**
	 * True when a crawled product matches the keyword — in its URL slug OR its
	 * (chrome-stripped) content text. Case-insensitive; multiple space/comma words
	 * match ANY (OR). Empty keyword = always true. Pure.
	 *
	 * IMPORTANT: pass the CLEANED content text (menu/footer already stripped) — raw
	 * HTML would match a site mega-menu that lists every destination on every page.
	 */
	function competitor_matches_keyword($url, $text, $keyword)
	{
		$keyword = trim((string) $keyword);
		if ($keyword === '') {
			return true;
		}
		$tokens = array_values(array_filter(
			preg_split('/[\s,]+/', mb_strtolower($keyword, 'UTF-8')),
			'strlen'
		));
		if (empty($tokens)) {
			return true;
		}
		$path = mb_strtolower((string) parse_url((string) $url, PHP_URL_PATH), 'UTF-8');
		$body = mb_strtolower((string) $text, 'UTF-8');
		foreach ($tokens as $t) {
			if (($path !== '' && mb_strpos($path, $t) !== false)
				|| ($body !== '' && mb_strpos($body, $t) !== false)) {
				return true;
			}
		}
		return false;
	}
}

if ( ! function_exists('competitor_file_binary_kind'))
{
	/**
	 * Classify a fetched file's bytes by its magic number so the service reads it
	 * with the right tool: 'pdf' (%PDF-), 'image' (JPEG/PNG/GIF/WEBP), or '' for
	 * anything else. Content-type headers on these CDN links are unreliable/absent,
	 * so we sniff the bytes. Pure.
	 */
	function competitor_file_binary_kind($bytes)
	{
		if ( ! is_string($bytes) || strlen($bytes) < 4) {
			return '';
		}
		if (substr($bytes, 0, 5) === '%PDF-') {
			return 'pdf';
		}
		if (substr($bytes, 0, 3) === "\xFF\xD8\xFF") {                     // JPEG
			return 'image';
		}
		if (substr($bytes, 0, 8) === "\x89PNG\r\n\x1a\n") {                // PNG
			return 'image';
		}
		if (substr($bytes, 0, 6) === 'GIF87a' || substr($bytes, 0, 6) === 'GIF89a') {
			return 'image';
		}
		if (substr($bytes, 0, 4) === 'RIFF' && substr($bytes, 8, 4) === 'WEBP') {
			return 'image';
		}
		return '';
	}
}

if ( ! function_exists('competitor_is_junk_file_url'))
{
	/**
	 * True when a file URL is boilerplate legal/policy/marketing junk (privacy,
	 * terms, PDPA, code-of-conduct, complaint policy, cookie, disclaimer, brochure
	 * catalogues, EDMs…) rather than a product brochure/itinerary — so the crawler
	 * doesn't fetch + OCR footer PDFs that dilute the product text. Matched on the
	 * URL path/filename. Pure.
	 */
	function competitor_is_junk_file_url($url)
	{
		$path = strtolower((string) parse_url((string) $url, PHP_URL_PATH));
		if ($path === '') {
			return false;
		}
		return (bool) preg_match(
			'#(policy|policies|privacy|pdpa|gdpr|abac|conduct|complaint|cookie|disclaimer'
			. '|terms|tnc|t-and-c|t_c|gst|sst|corporate-governance|whistle|anti-bribery|edm)#',
			$path
		);
	}
}

if ( ! function_exists('competitor_extract_file_links'))
{
	/**
	 * Pull brochure/DOCUMENT links out of raw page HTML — the <a href> targets
	 * whose path ends in a document extension (pdf/doc/xls/ppt). html_to_text strips
	 * hrefs, so on a plain HTML competitor page the PDF brochure links would be
	 * lost; this recovers them so the service can fetch + read them. Images are
	 * deliberately EXCLUDED: a linked .jpg/.png on a generic page is almost always a
	 * photo/gallery/thumbnail, not a brochure (real image brochures arrive via the
	 * ICE/JSON "Files:" path instead). Skips legal/policy junk
	 * (competitor_is_junk_file_url). Absolute, deduped, order-preserving, any host.
	 * Pure.
	 */
	function competitor_extract_file_links($html, $base_url)
	{
		if ( ! is_string($html) || $html === '') {
			return array();
		}
		$exts = 'pdf|docx?|xlsx?|pptx?';
		$out  = array();
		if (preg_match_all('#<a\b[^>]*\bhref\s*=\s*(?:"([^"]*)"|\'([^\']*)\')#i', $html, $m, PREG_SET_ORDER)) {
			foreach ($m as $mm) {
				$href = ($mm[1] !== '') ? $mm[1] : (isset($mm[2]) ? $mm[2] : '');
				// Decode HTML entities in the href (e.g. &amp; → &) so query-string
				// product URLs (tour.php?a=1&amp;b=2) resolve to a valid, fetchable URL.
				$href = html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8');
				$abs  = competitor_resolve_url($base_url, $href);
				if ($abs === '' || isset($out[$abs])) {
					continue;
				}
				$path = parse_url($abs, PHP_URL_PATH);
				if (is_string($path) && preg_match('#\.(' . $exts . ')$#i', $path)
					&& ! competitor_is_junk_file_url($abs)) {
					$out[$abs] = true;
				}
			}
		}
		return array_keys($out);
	}
}

if ( ! function_exists('competitor_extract_urls'))
{
	/**
	 * Pull distinct http(s) URLs out of a block of source text — used to find the
	 * linked brochure/itinerary files (e.g. the "Files: …" line an ICE post/series
	 * carries) so the service can fetch + read them for richer AI input. Strips
	 * trailing punctuation, dedupes, order-preserving, capped at $limit (0 = all).
	 * Pure — the fetch lives in the service.
	 */
	function competitor_extract_urls($text, $limit = 10)
	{
		if ( ! is_string($text) || $text === '') {
			return array();
		}
		$out = array();
		if (preg_match_all('#https?://[^\s"\'<>)\]]+#i', $text, $m)) {
			foreach ($m[0] as $u) {
				$u = rtrim($u, '.,);]');
				if ($u === '' || isset($out[$u])) {
					continue;
				}
				$out[$u] = true;
				if ($limit > 0 && count($out) >= $limit) {
					break;
				}
			}
		}
		return array_keys($out);
	}
}

if ( ! function_exists('competitor_is_site_root'))
{
	/**
	 * True when the URL is a site ROOT/homepage (empty or "/" path) — the case where
	 * we should enumerate the WHOLE site via its sitemap rather than crawl one page.
	 * A deeper path (a listing or product) is scoped by the user and left alone. Pure.
	 */
	function competitor_is_site_root($url)
	{
		$path = parse_url((string) $url, PHP_URL_PATH);
		return (is_string($path) ? trim($path, '/') : '') === '';
	}
}

if ( ! function_exists('competitor_robots_sitemaps'))
{
	/**
	 * Extract the `Sitemap:` URLs advertised in a robots.txt. Order-preserving,
	 * deduped. Returns [] when none. Pure.
	 */
	function competitor_robots_sitemaps($txt)
	{
		if ( ! is_string($txt) || $txt === '') {
			return array();
		}
		$out = array();
		if (preg_match_all('#^\s*sitemap\s*:\s*(\S+)#im', $txt, $m)) {
			foreach ($m[1] as $u) {
				$u = trim($u);
				if ($u !== '' && ! in_array($u, $out, true)) {
					$out[] = $u;
				}
			}
		}
		return $out;
	}
}

if ( ! function_exists('competitor_parse_sitemap'))
{
	/**
	 * Parse a sitemap XML into ['is_index' => bool, 'urls' => [<loc>…]]. A
	 * <sitemapindex> lists child sitemaps (is_index true); a <urlset> lists page
	 * URLs. Locs are decoded + deduped. Returns empty urls for junk. Pure.
	 */
	function competitor_parse_sitemap($xml)
	{
		if ( ! is_string($xml) || $xml === '') {
			return array('is_index' => false, 'urls' => array());
		}
		$is_index = stripos($xml, '<sitemapindex') !== false;
		$urls = array();
		if (preg_match_all('#<loc>\s*(.*?)\s*</loc>#is', $xml, $m)) {
			foreach ($m[1] as $u) {
				$u = trim(html_entity_decode($u, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
				if ($u !== '' && ! in_array($u, $urls, true)) {
					$urls[] = $u;
				}
			}
		}
		return array('is_index' => $is_index, 'urls' => $urls);
	}
}

if ( ! function_exists('competitor_filter_product_urls'))
{
	/**
	 * Keep only the likely product URLs from a discovered link list, deduped and
	 * capped at $limit (the per-crawl cost cap — each product is one paid AI
	 * call). Preserves discovery order. Pure.
	 */
	function competitor_filter_product_urls($urls, $limit = 10)
	{
		$out = array();
		foreach ((array) $urls as $u) {
			$u = trim((string) $u);
			if ($u === '' || isset($out[$u])) {
				continue;
			}
			if (competitor_is_product_url($u)) {
				$out[$u] = true;
				if ($limit > 0 && count($out) >= $limit) {
					break;
				}
			}
		}
		return array_keys($out);
	}
}

if ( ! function_exists('competitor_url_label'))
{
	/**
	 * A readable label for a discovered product URL, shown in the crawl-results
	 * pick list before analysis (we haven't fetched the page's real title). Takes
	 * the last path segment, drops any .html/.php extension, turns -/_ into
	 * spaces and Title-Cases it; falls back to the host for a bare/root URL. Pure.
	 */
	function competitor_url_label($url)
	{
		$path = parse_url((string) $url, PHP_URL_PATH);
		if ( ! is_string($path) || trim($path, '/') === '') {
			$host = parse_url((string) $url, PHP_URL_HOST);
			return $host ? preg_replace('/^www\./i', '', $host) : (string) $url;
		}
		// Take the last path segment, then URL-decode it (many links percent-encode
		// spaces/slashes into a single messy segment). Drop a page/file extension,
		// flatten separators, Title-Case, and cap the length so the pick list stays
		// tidy — some sites bury the whole tour name in one long segment.
		$segments = array_values(array_filter(explode('/', $path), 'strlen'));
		$last = rawurldecode(end($segments));
		$last = preg_replace('/\.(html?|php|aspx?|jsp|pdf)$/i', '', $last);
		$last = trim(preg_replace('/\s+/', ' ', str_replace(array('-', '_', '/'), ' ', $last)));
		if ($last === '') {
			return (string) $url;
		}
		if (mb_strlen($last, 'UTF-8') > 80) {
			$last = rtrim(mb_substr($last, 0, 80, 'UTF-8')) . '…';
		}
		return mb_convert_case($last, MB_CASE_TITLE, 'UTF-8');
	}
}

if ( ! function_exists('competitor_crawl_row_summary'))
{
	/**
	 * Build the listing-row summary for a combined site crawl: a "host — N
	 * products" label, the distinct destinations (first 3 + "+N more"), and the
	 * product count. Pure — feeds Competitor_Analysis_Model::Create().
	 */
	function competitor_crawl_row_summary($base_url, $products)
	{
		$products = is_array($products) ? $products : array();
		$count = count($products);
		$host  = parse_url((string) $base_url, PHP_URL_HOST);
		$host  = $host ? preg_replace('/^www\./i', '', $host) : trim((string) $base_url);

		$dests = array();
		foreach ($products as $p) {
			$p = (array) $p;
			$d = isset($p['destination']) ? trim((string) $p['destination']) : '';
			if ($d !== '' && ! in_array($d, $dests, true)) {
				$dests[] = $d;
			}
		}
		$dest_label = '';
		if ($dests) {
			$dest_label = implode(', ', array_slice($dests, 0, 3));
			if (count($dests) > 3) {
				$dest_label .= ', +' . (count($dests) - 3) . ' more';
			}
		}
		return array(
			'product_name'  => $host . ' — ' . $count . ' product' . ($count === 1 ? '' : 's'),
			'destination'   => $dest_label,
			'product_count' => $count,
		);
	}
}

if ( ! function_exists('competitor_websearch_cap'))
{
	/**
	 * Resolve the per-crawl web_search (browsing) budget from the raw
	 * COMPETITOR_MAX_WEBSEARCH .env value. Each blank-shell SPA product costs one
	 * browsing call (a real per-call fee), so this caps a crawl's surprise bill.
	 * Unset/blank -> $default; an explicit 0 -> 0 = UNLIMITED (opt-in); a negative
	 * -> $default; otherwise the integer. Pure.
	 */
	function competitor_websearch_cap($raw, $default = 15)
	{
		if ($raw === false || $raw === null || trim((string) $raw) === '') {
			return (int) $default;
		}
		$n = (int) $raw;
		return $n < 0 ? (int) $default : $n;
	}
}

if ( ! function_exists('competitor_headless_cap'))
{
	/**
	 * Per-crawl cap on headless-browser renders (each ~2-4s) from the raw
	 * COMPETITOR_MAX_HEADLESS .env value — bounds how long a JS-heavy crawl can run.
	 * Unset/blank -> $default; explicit 0 -> 0 = UNLIMITED; negative -> $default;
	 * otherwise the integer. Pure.
	 */
	function competitor_headless_cap($raw, $default = 30)
	{
		if ($raw === false || $raw === null || trim((string) $raw) === '') {
			return (int) $default;
		}
		$n = (int) $raw;
		return $n < 0 ? (int) $default : $n;
	}
}

if ( ! function_exists('competitor_job_progress_message'))
{
	/**
	 * Turn a background crawl job's status array into a short human line for the
	 * progress popup. States: queued / running (phase discovering|reading with
	 * done/total) / done (with product count) / error (with message). Pure.
	 */
	function competitor_job_progress_message($status)
	{
		$s     = is_array($status) ? $status : array();
		$state = isset($s['state']) ? $s['state'] : '';
		if ($state === 'queued') {
			return 'Queued…';
		}
		if ($state === 'error') {
			return 'Error: ' . (isset($s['message']) && $s['message'] !== '' ? $s['message'] : 'crawl failed');
		}
		if ($state === 'done') {
			$c = isset($s['count']) ? (int) $s['count'] : 0;
			return $c > 0 ? ('Done — ' . $c) : 'Done.';
		}
		if ($state === 'unknown' || $state === '') {
			return 'Job not found.';
		}
		// running — the label reflects the actual phase: discovery has no known total
		// (enumerating the site), reading shows per-product progress N / total.
		$phase = isset($s['phase']) ? $s['phase'] : '';
		$done  = isset($s['done']) ? (int) $s['done'] : 0;
		$total = isset($s['total']) ? (int) $s['total'] : 0;
		if ($phase === 'reading' && $total > 0) {
			return 'Reading products… ' . $done . ' / ' . $total;
		}
		if ($phase === 'analysing') {
			return $total > 0 ? ('Analysing… ' . $done . ' / ' . $total) : 'Analysing…';
		}
		// A pasted-text job has no discovery phase — it goes straight to analysing.
		if (($mode = (isset($s['mode']) ? $s['mode'] : '')) === 'paste') {
			return 'Analysing…';
		}
		return 'Discovering…';
	}
}

if ( ! function_exists('competitor_strip_shared_chrome'))
{
	/**
	 * Remove site chrome (mega-menu / header / footer) that our tag-based scraper
	 * missed because it lives in plain <div>/<ul> — not <nav>/<footer>. Every product
	 * on a site repeats the SAME leading menu + trailing footer verbatim, so we detect
	 * the longest run of leading (and trailing) lines shared by a majority of the
	 * crawled items and strip it from each. Markup-agnostic; fixes titles reading
	 * "MENUMENU" and stops the identical menu bloating every product's AI input.
	 * Pure — takes/returns [['url'=>,'text'=>], …]. No-op with < 3 items.
	 */
	function competitor_strip_shared_chrome($items, $min_share = 0.6, $max_scan = 300)
	{
		$items = array_values(is_array($items) ? $items : array());
		$n = count($items);
		if ($n < 3) {
			return $items;   // too few samples to tell chrome from content
		}
		$lines = array();
		foreach ($items as $it) {
			$t = isset($it['text']) ? (string) $it['text'] : '';
			$lines[] = ($t === '') ? array() : explode("\n", $t);
		}
		$threshold = max(2, (int) ceil($n * $min_share));

		// Longest run of positions whose modal line is shared by >= threshold items.
		$shared_run = function ($rows) use ($threshold, $max_scan) {
			$run = array();
			for ($i = 0; $i < $max_scan; $i++) {
				$counts = array();
				$present = 0;
				foreach ($rows as $r) {
					if ( ! isset($r[$i])) { continue; }
					$present++;
					if ($r[$i] === '') { continue; }
					$counts[$r[$i]] = isset($counts[$r[$i]]) ? $counts[$r[$i]] + 1 : 1;
				}
				if ($present < $threshold || empty($counts)) { break; }
				arsort($counts);
				$top = array_keys($counts)[0];
				if ($counts[$top] >= $threshold) { $run[] = $top; } else { break; }
			}
			return $run;
		};

		$lead = $shared_run($lines);
		$tail = array_reverse($shared_run(array_map('array_reverse', $lines)));
		if (empty($lead) && empty($tail)) {
			return $items;
		}

		$clen = count($lead);
		$tlen = count($tail);
		foreach ($items as $k => $it) {
			$ln = $lines[$k];
			if ($clen) {
				$match = true;
				for ($i = 0; $i < $clen; $i++) {
					if ( ! isset($ln[$i]) || $ln[$i] !== $lead[$i]) { $match = false; break; }
				}
				if ($match) { $ln = array_slice($ln, $clen); }
			}
			if ($tlen && count($ln) >= $tlen) {
				$m = count($ln);
				$match = true;
				for ($i = 0; $i < $tlen; $i++) {
					if ($ln[$m - $tlen + $i] !== $tail[$i]) { $match = false; break; }
				}
				if ($match) { $ln = array_slice($ln, 0, $m - $tlen); }
			}
			$items[$k]['text'] = trim(implode("\n", $ln));
		}
		return $items;
	}
}

if ( ! function_exists('competitor_page_title'))
{
	/**
	 * The real product name from a page's HTML — far more reliable than the first
	 * body line (which is often menu/CTA/inquiry-form chrome). Prefers the first
	 * <h1>, then og:title, then <title> with a trailing " - Site" / " | Site" suffix
	 * trimmed. Decodes entities, collapses whitespace. Pure. '' when none found.
	 */
	function competitor_page_title($html)
	{
		if ( ! is_string($html) || $html === '') {
			return '';
		}
		$clean = function ($s) {
			$s = html_entity_decode((string) $s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
			return trim(preg_replace('/\s+/u', ' ', strip_tags($s)));
		};
		// 1) first <h1>
		if (preg_match('#<h1[^>]*>(.*?)</h1>#is', $html, $m)) {
			$t = $clean($m[1]);
			if (mb_strlen($t, 'UTF-8') >= 3) {
				return $t;
			}
		}
		// 2) og:title (either attribute order)
		if (preg_match('#<meta[^>]+property=["\']og:title["\'][^>]*content=["\']([^"\']+)#i', $html, $m)
			|| preg_match('#<meta[^>]+content=["\']([^"\']+)["\'][^>]*property=["\']og:title["\']#i', $html, $m)) {
			$t = $clean($m[1]);
			if ($t !== '') {
				return $t;
			}
		}
		// 3) <title>, drop the trailing site-name segment ("… - Raha Holidays")
		if (preg_match('#<title[^>]*>(.*?)</title>#is', $html, $m)) {
			$t = $clean($m[1]);
			$t = preg_replace('/\s*[|\x{2013}\x{2014}\-]\s*[^|\x{2013}\x{2014}\-]{2,40}$/u', '', $t);
			if ($t !== '') {
				return $t;
			}
		}
		return '';
	}
}

if ( ! function_exists('competitor_item_meta'))
{
	/**
	 * Extra at-a-glance info for the review list: the trip DURATION (e.g. "9D7N" or
	 * "5 Days") taken from the title first — reliable — then the body, and a short
	 * SNIPPET of the real content (first meaningful line past the title/label).
	 * Deliberately NO price — these pages carry many noisy prices (deposits, related
	 * tours) so a single figure would mislead. Pure. Returns
	 * ['duration' => '', 'snippet' => ''].
	 */
	function competitor_item_meta($text, $title = '')
	{
		$duration = '';
		foreach (array((string) $title, (string) $text) as $src) {
			if ($src === '') { continue; }
			if (preg_match('/\b(\d{1,2})\s*d\s*(\d{1,2})\s*n\b/i', $src, $m)) {
				$duration = strtoupper($m[1] . 'D' . $m[2] . 'N');
				break;
			}
			if (preg_match('/\b(\d{1,2})\s*days?\b(?:\s*\/?\s*(\d{1,2})\s*nights?)?/i', $src, $m)) {
				$duration = $m[1] . ' Days' . (isset($m[2]) && $m[2] !== '' ? ' ' . $m[2] . ' Nights' : '');
				break;
			}
		}

		// Snippet: first content line that isn't the label/title itself.
		$snippet = '';
		$titleLc = mb_strtolower(trim((string) $title), 'UTF-8');
		foreach (preg_split('/\r\n|\r|\n/', (string) $text) as $ln) {
			$ln = trim(preg_replace('/^\s*(product|title|name|caption)\s*:\s*/i', '', trim($ln)));
			if ($ln === '' || ! preg_match('/\p{L}/u', $ln) || mb_strlen($ln, 'UTF-8') < 8) {
				continue;
			}
			if (mb_strtolower($ln, 'UTF-8') === $titleLc) {
				continue;   // skip the title line
			}
			$snippet = mb_strlen($ln, 'UTF-8') > 140 ? rtrim(mb_substr($ln, 0, 140, 'UTF-8')) . '…' : $ln;
			break;
		}
		return array('duration' => $duration, 'snippet' => $snippet);
	}
}

if ( ! function_exists('competitor_item_title'))
{
	/**
	 * A short display title for one crawled product, for the review/select list.
	 * Takes the first meaningful line of the extracted text (stripping a leading
	 * "Product:"/"title:"/"name:" label + skipping bare nav tokens), capped; falls
	 * back to the URL label when the text has none. Pure.
	 */
	function competitor_item_title($text, $url = '')
	{
		// Bare navigation/boilerplate lines that are never a product name.
		$nav = array('menu', 'menumenu', 'home', 'search', 'login', 'log in', 'sign in',
			'register', 'cart', 'wishlist', 'contact', 'contact us', 'book now', 'read more');
		foreach (preg_split('/\r\n|\r|\n/', (string) $text) as $ln) {
			$ln = trim(preg_replace('/^\s*(product|title|name|caption)\s*:\s*/i', '', trim($ln)));
			if ($ln === '' || ! preg_match('/\p{L}/u', $ln) || mb_strlen($ln, 'UTF-8') < 4) {
				continue;
			}
			if (in_array(mb_strtolower($ln, 'UTF-8'), $nav, true)) {
				continue;
			}
			return mb_strlen($ln, 'UTF-8') > 90 ? rtrim(mb_substr($ln, 0, 90, 'UTF-8')) . '…' : $ln;
		}
		return competitor_url_label($url);
	}
}

if ( ! function_exists('competitor_job_public_view'))
{
	/**
	 * Shape a raw job status array into the browser-safe view the jobs rows show:
	 * id, url, state, human message, product count, timestamp; `analysis_id` when an
	 * analyse job saved a report; `reviewable` when a finished CRAWL has products to
	 * pick from. Pure.
	 */
	function competitor_job_public_view($status)
	{
		$s     = is_array($status) ? $status : array();
		$state = isset($s['state']) ? (string) $s['state'] : 'unknown';
		$mode  = isset($s['mode']) ? (string) $s['mode'] : 'crawl';
		return array(
			'job'         => isset($s['job']) ? (string) $s['job'] : '',
			'url'         => isset($s['url']) ? (string) $s['url'] : '',
			'state'       => $state,
			'mode'        => $mode,
			'message'     => competitor_job_progress_message($s),
			'count'       => isset($s['count']) ? (int) $s['count'] : 0,
			'keyword'     => isset($s['keyword']) ? (string) $s['keyword'] : '',
			'force_render' => ! empty($s['force_render']),
			'ai_crawl'    => ! empty($s['ai_crawl']),
			'is_paste'    => ($mode === 'paste'),
			// Fixed submission time (when pasted + Analyse clicked), not last update.
			'ts'          => isset($s['created']) ? (string) $s['created'] : (isset($s['ts']) ? (string) $s['ts'] : ''),
			// Live-ETA inputs (reading phase): progress + when reading began.
			'done'        => isset($s['done']) ? (int) $s['done'] : 0,
			'total'       => isset($s['total']) ? (int) $s['total'] : 0,
			'read_start'  => isset($s['read_start']) ? (string) $s['read_start'] : '',
			'cost_total'  => isset($s['cost_total']) ? (float) $s['cost_total'] : 0.0,
			'analysed'    => isset($s['analysed']) && is_array($s['analysed']) ? count($s['analysed']) : 0,
			'analysis_id' => isset($s['analysis_id']) ? (int) $s['analysis_id'] : 0,
			'reviewable'  => ($state === 'done' && $mode === 'crawl' && (int) (isset($s['count']) ? $s['count'] : 0) > 0),
		);
	}
}

if ( ! function_exists('competitor_spa_api_url'))
{
	/**
	 * Map a JavaScript-app page URL to the JSON API URL that actually holds its
	 * content, for known SPA platforms. Today that's the ICE Holidays platform
	 * (e.g. gd.my): a page like /web/posts/<category>/<id> (or /web/posts/<id>)
	 * is served by /api/v1/posts/<id>. Returns '' when no known mapping applies.
	 * Extend with more patterns as other SPA competitors appear. Pure.
	 */
	function competitor_spa_api_url($url)
	{
		$parts = parse_url((string) $url);
		if (empty($parts['scheme']) || empty($parts['host'])) {
			return '';
		}
		$origin = $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
		$path   = isset($parts['path']) ? $parts['path'] : '';

		// ICE Holidays platform: /web/posts/<category>/<id> or /web/posts/<id>.
		if (preg_match('#/web/posts/(?:[^/]+/)?(\d+)#', $path, $m)) {
			return $origin . '/api/v1/posts/' . $m[1];
		}
		return '';
	}
}

if ( ! function_exists('competitor_ice_listing_api_url'))
{
	/**
	 * Map an ICE Holidays LISTING/search page URL to the JSON API that returns its
	 * FILTERED products, preserving the query verbatim: /web/listing?<query> ->
	 * /api/v1/series?<query> (e.g. keyword=EAST COAST&location_id=85&brands[]=…).
	 * Returns '' for non-listing URLs or a listing with no query (nothing to
	 * filter). Lets a pasted filtered listing analyse only its matches instead of
	 * the whole catalogue. Pure.
	 */
	function competitor_ice_listing_api_url($url)
	{
		$parts = parse_url((string) $url);
		if (empty($parts['scheme']) || empty($parts['host'])) {
			return '';
		}
		$path = isset($parts['path']) ? $parts['path'] : '';
		if ( ! preg_match('#/web/listing/?$#i', $path) || empty($parts['query'])) {
			return '';
		}
		$origin = $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
		return $origin . '/api/v1/series?' . $parts['query'];
	}
}

if ( ! function_exists('competitor_json_api_to_text'))
{
	/**
	 * Turn a JSON:API response body ({data:{attributes:{title, body, …}}} or a
	 * list of them) into readable text for the AI: the title, plus the HTML
	 * `body`/`content`/`description` rendered to text (tables kept), plus any
	 * file/brochure URLs found in it (so PDF links aren't lost). Returns '' when
	 * nothing usable is present. Pure.
	 */
	function competitor_json_api_to_text($json)
	{
		$data = is_string($json) ? json_decode($json, true) : (is_array($json) ? $json : null);
		if ( ! is_array($data)) {
			return '';
		}
		$nodes = array();
		if (isset($data['data'])) {
			$d = $data['data'];
			if (isset($d['attributes']) && is_array($d['attributes'])) {
				$nodes[] = $d['attributes'];
			} elseif (is_array($d)) {
				foreach ($d as $it) {
					if (is_array($it) && isset($it['attributes']) && is_array($it['attributes'])) {
						$nodes[] = $it['attributes'];
					}
				}
			}
		}
		if (empty($nodes) && isset($data['attributes']) && is_array($data['attributes'])) {
			$nodes[] = $data['attributes'];
		}
		if (empty($nodes)) {
			$nodes[] = $data;
		}

		$parts = array();
		foreach ($nodes as $attr) {
			if ( ! is_array($attr)) {
				continue;
			}
			foreach (array('title', 'name', 'heading') as $k) {
				if ( ! empty($attr[$k]) && is_string($attr[$k])) {
					$parts[] = trim(preg_replace('/\s+/', ' ', $attr[$k]));
					break;
				}
			}
			foreach (array('body', 'content', 'description', 'details', 'html', 'itinerary') as $k) {
				if ( ! empty($attr[$k]) && is_string($attr[$k])) {
					$parts[] = competitor_html_to_text($attr[$k]);
					if (preg_match_all('#https?://[^\s"\'<>]+#i', $attr[$k], $mm)) {
						$parts[] = 'Files: ' . implode(' , ', array_slice(array_values(array_unique($mm[0])), 0, 20));
					}
				}
			}
		}
		$text = trim(implode("\n\n", array_filter(array_map('trim', $parts), 'strlen')));
		return preg_replace('/\n{3,}/', "\n\n", $text);
	}
}

if ( ! function_exists('competitor_ice_post_packages'))
{
	/**
	 * Split an ICE "posts" page (e.g. gd.my /api/v1/posts/65 — a promo that LISTS
	 * several packages) into its individual products. The body carries one
	 * `editorjs_table_attach` table per package with 3 cells: name, tour code, and
	 * a "View File" PDF link. Returns [{name, code, file, title}] (title = the post
	 * heading, e.g. "SABAH"), one per package. So a post showing 5 packages becomes
	 * 5 products instead of 1. Empty when the body has no such tables. Pure.
	 */
	function competitor_ice_post_packages($json)
	{
		$data = is_string($json) ? json_decode($json, true) : (is_array($json) ? $json : null);
		if ( ! is_array($data)) {
			return array();
		}
		$attr = isset($data['data']['attributes']) && is_array($data['data']['attributes'])
			? $data['data']['attributes']
			: (isset($data['attributes']) && is_array($data['attributes']) ? $data['attributes'] : $data);
		$body = isset($attr['body']) && is_string($attr['body']) ? $attr['body'] : '';
		if ($body === '') {
			return array();
		}
		$title = '';
		foreach (array('title', 'name', 'heading') as $k) {
			if ( ! empty($attr[$k]) && is_string($attr[$k])) {
				$title = trim(preg_replace('/\s+/', ' ', $attr[$k]));
				break;
			}
		}
		$dec = function ($html) {
			return trim(preg_replace('/\s+/', ' ',
				html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
		};
		$out = array();
		if (preg_match_all('#<table[^>]*editorjs_table_attach[^>]*>(.*?)</table>#is', $body, $tm)) {
			foreach ($tm[1] as $tbl) {
				preg_match_all('#<td[^>]*>(.*?)</td>#is', $tbl, $cm);
				$cells = isset($cm[1]) ? $cm[1] : array();
				$name  = isset($cells[0]) ? $dec($cells[0]) : '';
				$code  = '';
				if (isset($cells[1]) && preg_match('#T\s*/?\s*CODE\s*:?\s*([A-Z0-9\-/]+)#i', $dec($cells[1]), $cc)) {
					$code = strtoupper($cc[1]);
				}
				$file = preg_match('#href\s*=\s*([\'"])(.*?)\1#i', $tbl, $hm) ? trim($hm[2]) : '';
				if ($name !== '' || $file !== '') {
					$out[] = array('name' => $name, 'code' => $code, 'file' => $file, 'title' => $title);
				}
			}
		}
		return $out;
	}
}

if ( ! function_exists('competitor_ice_api_kind'))
{
	/**
	 * Classify an ICE Holidays JSON API URL so the analyser reads it with the
	 * right extractor: 'series' for /api/v1/series/<id> (a tour product),
	 * 'posts' for /api/v1/posts/<id> (a CMS/brochure page), '' otherwise. Pure.
	 */
	function competitor_ice_api_kind($url)
	{
		$path = parse_url((string) $url, PHP_URL_PATH);
		if ( ! is_string($path)) {
			return '';
		}
		if (preg_match('#/api/v1/series/\d+#', $path)) {
			return 'series';
		}
		if (preg_match('#/api/v1/posts/\d+#', $path)) {
			return 'posts';
		}
		return '';
	}
}

if ( ! function_exists('competitor_ice_series_web_url'))
{
	/**
	 * Build the customer-facing web itinerary URL for an ICE series from its detail
	 * JSON — "<origin>/web/itinerary/<code>?type=series[&depart_date=<first tour date>]",
	 * the page a visitor actually browses. The crawler reads the machine /api/v1/series/<id>
	 * JSON, but we store/show THIS friendly URL instead. depart_date is passed through
	 * in the API's own DD/MM/YYYY form. Returns '' when the JSON carries no code. Pure.
	 */
	function competitor_ice_series_web_url($json, $origin)
	{
		$d = is_string($json) ? json_decode($json, true) : $json;
		if ( ! is_array($d)) {
			return '';
		}
		if ( ! isset($d['code']) && ! isset($d['caption']) && isset($d['data']) && is_array($d['data'])) {
			$d = $d['data'];
		}
		$code = isset($d['code']) ? trim((string) $d['code']) : '';
		if ($code === '') {
			return '';
		}
		$url = rtrim((string) $origin, '/') . '/web/itinerary/' . rawurlencode($code) . '?type=series';
		if ( ! empty($d['tours']) && is_array($d['tours'])) {
			foreach ($d['tours'] as $t) {
				if (is_array($t) && ! empty($t['departure_date'])) {
					$url .= '&depart_date=' . rawurlencode(trim((string) $t['departure_date']));
					break;
				}
			}
		}
		return $url;
	}
}

if ( ! function_exists('competitor_ice_series_items'))
{
	/**
	 * Turn an ICE `/api/v1/series?keyword=…` response (its `itineraries[]`) into
	 * pick-list items: [{url, label}] where url is the series DETAIL API
	 * (/api/v1/series/<id>, fetched at analysis time) and label is a human string
	 * "<caption> · <country> · <currency> <price> · <code>". Deduped, capped at
	 * $limit. Pure.
	 */
	function competitor_ice_series_items($json, $origin, $limit = 10)
	{
		$data = is_string($json) ? json_decode($json, true) : $json;
		if ( ! is_array($data) || empty($data['itineraries']) || ! is_array($data['itineraries'])) {
			return array();
		}
		$origin = rtrim((string) $origin, '/');
		$out = array();
		foreach ($data['itineraries'] as $it) {
			if ( ! is_array($it) || ! isset($it['id']) || $it['id'] === '') {
				continue;
			}
			$id  = (string) $it['id'];
			$url = $origin . '/api/v1/series/' . $id;
			if (isset($out[$url])) {
				continue;
			}
			$s = function ($k) use ($it) { return isset($it[$k]) ? trim(preg_replace('/\s+/', ' ', (string) $it[$k])) : ''; };
			$caption = $s('caption');
			$code    = $s('code');
			$country = $s('country');
			$price   = $s('price');
			if (preg_match('/^\d+\.\d+$/', $price)) {
				$price = rtrim(rtrim($price, '0'), '.');
			}
			$name = $caption !== '' ? $caption : ($code !== '' ? $code : ('Series ' . $id));
			$meta = array();
			if ($country !== '') { $meta[] = $country; }
			if ($price !== '')   { $meta[] = trim($s('price_currency') . ' ' . $price); }
			if ($code !== '' && $caption !== '') { $meta[] = $code; }
			$out[$url] = array('url' => $url, 'label' => $name . ($meta ? ' · ' . implode(' · ', $meta) : ''));
			if ($limit > 0 && count($out) >= $limit) {
				break;
			}
		}
		return array_values($out);
	}
}

if ( ! function_exists('competitor_ice_series_to_text'))
{
	/**
	 * Flatten an ICE `/api/v1/series/<id>` detail object into rich text for the
	 * AI: caption/code/country/currency, the HTML description, the `includings`
	 * map, the `tours[]` departures (date/location/price/guide), one highlight
	 * block, and the brochure file URLs. Returns '' when empty. Pure.
	 */
	function competitor_ice_series_to_text($json)
	{
		$d = is_string($json) ? json_decode($json, true) : $json;
		if ( ! is_array($d)) {
			return '';
		}
		if ( ! isset($d['caption']) && ! isset($d['code']) && isset($d['data']) && is_array($d['data'])) {
			$d = $d['data'];
		}
		$g = function ($k) use ($d) {
			if ( ! isset($d[$k])) { return ''; }
			if (is_string($d[$k])) { return trim(preg_replace('/\s+/', ' ', $d[$k])); }
			return is_scalar($d[$k]) ? trim((string) $d[$k]) : '';
		};

		$lines = array();
		if ($g('caption') !== '')       { $lines[] = 'Product: ' . $g('caption'); }
		if ($g('other_caption') !== '') { $lines[] = 'Also known as: ' . $g('other_caption'); }
		$head = array();
		foreach (array('code' => 'Code', 'country' => 'Country', 'price_currency' => 'Currency', 'category' => 'Category') as $k => $lbl) {
			if ($g($k) !== '') { $head[] = $lbl . ': ' . $g($k); }
		}
		if ($head) { $lines[] = implode('  ', $head); }

		if ( ! empty($d['description']) && is_string($d['description'])) {
			$t = competitor_html_to_text($d['description'], 8000);
			if ($t !== '') { $lines[] = "Description:\n" . $t; }
		}

		if ( ! empty($d['includings']) && is_array($d['includings'])) {
			$split = competitor_ice_includings_split($d['includings']);
			if ($split['inclusions']) { $lines[] = "Inclusions:\n- " . implode("\n- ", $split['inclusions']); }
			if ($split['exclusions']) { $lines[] = "Not included:\n- " . implode("\n- ", $split['exclusions']); }
		}

		if ( ! empty($d['tours']) && is_array($d['tours'])) {
			$deps = array();
			foreach (array_slice($d['tours'], 0, 8) as $t) {
				if ( ! is_array($t)) { continue; }
				$dd  = isset($t['departure_date']) ? trim((string) $t['departure_date']) : '';
				$loc = isset($t['departure_location']) ? trim((string) $t['departure_location']) : '';
				$pr  = isset($t['price']) ? trim((string) $t['price']) : '';
				$lang = '';
				if (isset($t['guide_languages'])) {
					$lang = is_array($t['guide_languages']) ? implode('/', array_map('strval', $t['guide_languages'])) : (string) $t['guide_languages'];
				}
				$seg = trim($dd . ($loc !== '' ? ' from ' . $loc : '') . ($pr !== '' ? ': ' . $pr : '') . ($lang !== '' ? ' (guide: ' . $lang . ')' : ''));
				if ($seg !== '') { $deps[] = $seg; }
			}
			if ($deps) { $lines[] = "Departures & prices:\n- " . implode("\n- ", $deps); }
			foreach ($d['tours'] as $t) {
				if (is_array($t) && ! empty($t['highlight']) && is_string($t['highlight'])) {
					$hl = competitor_html_to_text($t['highlight'], 3000);
					if ($hl !== '') { $lines[] = "Highlights:\n" . $hl; }
					break;
				}
			}
		}

		// Flight legs — carried per-departure in tours[].flights[], never in the
		// description text, so surface them explicitly or the AI has no flight data.
		$flt = competitor_ice_flights_text(isset($d['tours']) ? $d['tours'] : array());
		if ($flt !== '') { $lines[] = "Flights:\n- " . $flt; }

		// Day-by-day itinerary — the structured `itinerary_plans` the /web/itinerary
		// page renders (when the product ships one; many gd.my tours carry it only in
		// the brochure PDF instead, which the caller reads separately).
		$itin = competitor_ice_itinerary_text(
			isset($d['itinerary_plans']) ? $d['itinerary_plans'] : array(),
			isset($d['general_content']) ? $d['general_content'] : ''
		);
		if ($itin !== '') { $lines[] = "Itinerary:\n" . $itin; }

		$files = array();
		foreach (array('file_copy_url', 'file_url') as $fk) {
			if ( ! empty($d[$fk]) && is_string($d[$fk])) { $files[] = $d[$fk]; }
		}
		if ($files) { $lines[] = 'Files: ' . implode(' , ', $files); }

		$text = trim(implode("\n\n", array_filter(array_map('trim', $lines), 'strlen')));
		return preg_replace('/\n{3,}/', "\n\n", $text);
	}
}

if ( ! function_exists('competitor_ice_meals_text'))
{
	/**
	 * Render an ICE day-plan's `display_meals` into a short string, tolerating the
	 * shapes it can take: a plain string ("Breakfast / Lunch"), a list of meal names,
	 * or a map meal=>bool / meal=>label. Returns '' when there's nothing to show. Pure.
	 */
	function competitor_ice_meals_text($m)
	{
		if (is_string($m)) { return trim(preg_replace('/\s+/', ' ', $m)); }
		if ( ! is_array($m) || empty($m)) { return ''; }
		$assoc = array_keys($m) !== range(0, count($m) - 1);
		$out = array();
		foreach ($m as $k => $v) {
			if ($assoc) {
				if (is_bool($v)) {
					if ($v) { $out[] = ucwords(str_replace('_', ' ', (string) $k)); }
				} elseif (is_scalar($v) && trim((string) $v) !== '') {
					$out[] = trim((string) $v);
				}
			} elseif (is_scalar($v) && trim((string) $v) !== '') {
				$out[] = trim((string) $v);
			}
		}
		return implode(', ', $out);
	}
}

if ( ! function_exists('competitor_ice_itinerary_text'))
{
	/**
	 * Flatten an ICE series' structured day-by-day itinerary — the `itinerary_plans`
	 * array the /web/itinerary page renders — into readable "Day N: …" text for the
	 * AI. Each plan = {day, title, title_two, display_meals, activities[]}; each
	 * activity = {title, tagline, subtitle (+ *_two second-language variants),
	 * category}. English (the primary field) leads, the second language is appended
	 * in parentheses when it differs. `$general` is the optional general_content HTML
	 * intro. Returns '' when there is no structured itinerary. Pure.
	 */
	function competitor_ice_itinerary_text($plans, $general = '')
	{
		// Prefer the primary (English) string; append the alt-language one when it adds info.
		$bi = function ($a, $b) {
			$a = trim(preg_replace('/\s+/', ' ', (string) $a));
			$b = trim(preg_replace('/\s+/', ' ', (string) $b));
			if ($a === '') { return $b; }
			if ($b === '' || strcasecmp($a, $b) === 0) { return $a; }
			return $a . ' (' . $b . ')';
		};

		$out = array();
		$g = trim((string) $general);
		if ($g !== '') {
			$gt = competitor_html_to_text($g, 4000);
			if ($gt !== '') { $out[] = $gt; }
		}

		if (is_array($plans)) {
			$days = array();
			foreach (array_values($plans) as $i => $plan) {
				if ( ! is_array($plan)) {
					$s = trim((string) $plan);
					if ($s !== '') { $days[] = 'Day ' . ($i + 1) . ': ' . $s; }
					continue;
				}
				$dayno = (isset($plan['day']) && trim((string) $plan['day']) !== '') ? trim((string) $plan['day']) : (string) ($i + 1);
				$title = $bi(isset($plan['title']) ? $plan['title'] : '', isset($plan['title_two']) ? $plan['title_two'] : '');
				$head  = 'Day ' . $dayno . ($title !== '' ? ': ' . $title : '');
				$meals = competitor_ice_meals_text(isset($plan['display_meals']) ? $plan['display_meals'] : null);
				if ($meals !== '') { $head .= '  [Meals: ' . $meals . ']'; }

				$acts = array();
				if ( ! empty($plan['activities']) && is_array($plan['activities'])) {
					foreach ($plan['activities'] as $a) {
						if ( ! is_array($a)) {
							$s = trim(preg_replace('/\s+/', ' ', (string) $a));
							if ($s !== '') { $acts[] = $s; }
							continue;
						}
						$at  = $bi(isset($a['title']) ? $a['title'] : '', isset($a['title_two']) ? $a['title_two'] : '');
						$tag = $bi(isset($a['tagline']) ? $a['tagline'] : '', isset($a['tagline_two']) ? $a['tagline_two'] : '');
						$sub = $bi(isset($a['subtitle']) ? $a['subtitle'] : '', isset($a['subtitle_two']) ? $a['subtitle_two'] : '');
						$seg = $at;
						if ($tag !== '') { $seg = trim($seg . ($seg !== '' ? ' — ' : '') . $tag); }
						if ($sub !== '') { $seg = ($seg !== '' ? $seg . ': ' . $sub : $sub); }
						$seg = trim(preg_replace('/\s+/', ' ', $seg));
						if ($seg !== '') { $acts[] = $seg; }
					}
				}
				$days[] = $head . ($acts ? "\n- " . implode("\n- ", $acts) : '');
			}
			if ($days) { $out[] = implode("\n", $days); }
		}

		return $out ? implode("\n\n", $out) : '';
	}
}

if ( ! function_exists('competitor_ice_flights_text'))
{
	/**
	 * Render an ICE series' flight legs into readable text for the AI. The flight
	 * detail lives in `tours[].flights[]` (airline, flight_no, from/to airports,
	 * departure/arrival date+time) and only the SELECTED/first bookable departure
	 * carries it, so we render the first tour that actually has flights. Each leg =
	 * "AirAsia AK 204: Kuala Lumpur (KUL) -> Nha Trang (CXR), depart 26/09/2026
	 * 10:10, arrive 26/09/2026 11:30". Returns '' when no flights are present. Pure.
	 */
	function competitor_ice_flights_text($tours)
	{
		if ( ! is_array($tours)) {
			return '';
		}
		$flights = array();
		foreach ($tours as $t) {
			if (is_array($t) && ! empty($t['flights']) && is_array($t['flights'])) {
				$flights = $t['flights'];
				break;
			}
		}
		if (empty($flights)) {
			return '';
		}
		$g = function ($a, $k) {
			return isset($a[$k]) && is_scalar($a[$k]) ? trim((string) $a[$k]) : '';
		};
		$legs = array();
		foreach ($flights as $f) {
			if ( ! is_array($f)) { continue; }
			$carrier = trim($g($f, 'airline') . ' ' . $g($f, 'flight_no'));
			$route   = trim($g($f, 'from_airport') . ($g($f, 'to_airport') !== '' ? ' -> ' . $g($f, 'to_airport') : ''));
			$dep     = trim($g($f, 'departure_date') . ' ' . $g($f, 'departure_time'));
			$arr     = trim($g($f, 'arrival_date') . ' ' . $g($f, 'arrival_time'));
			$seg = $carrier !== '' ? $carrier : 'Flight';
			if ($route !== '') { $seg .= ': ' . $route; }
			if ($dep !== '')   { $seg .= ', depart ' . $dep; }
			if ($arr !== '')   { $seg .= ', arrive ' . $arr; }
			$seg = trim(preg_replace('/\s+/', ' ', $seg));
			if ($seg !== '') { $legs[] = $seg; }
		}
		return $legs ? implode("\n- ", $legs) : '';
	}
}

if ( ! function_exists('competitor_ice_includings_split'))
{
	/**
	 * Split an ICE series' `includings` flag-map into what the price DOES and does
	 * NOT include, so the AI reports real exclusions instead of inventing them from
	 * opaque internal flags. Truthy bool / non-empty scalar / non-empty nested array
	 * => included; a bool false => "not included". Internal flags that carry no
	 * customer meaning as a bare yes/no are dropped entirely: `acf`, and a BARE
	 * boolean `accommodation` flag (it contradicts a separately-named hotel — a
	 * nested accommodation object with details is still kept). Returns
	 * ['inclusions' => string[], 'exclusions' => string[]]. Pure.
	 */
	function competitor_ice_includings_split($includings)
	{
		$out = array('inclusions' => array(), 'exclusions' => array());
		if ( ! is_array($includings)) {
			return $out;
		}
		$opaque = array('acf', 'accommodation'); // meaningless/misleading as a bare boolean
		foreach ($includings as $k => $v) {
			$key = str_replace('_', ' ', (string) $k);
			if (is_bool($v)) {
				if (in_array((string) $k, $opaque, true)) { continue; }
				if ($v) { $out['inclusions'][] = $key; }
				else    { $out['exclusions'][] = $key; }
			} elseif (is_scalar($v)) {
				$vv = trim((string) $v);
				if ($vv !== '') { $out['inclusions'][] = $key . ': ' . $vv; }
			} elseif (is_array($v)) {
				$flat = array();
				foreach ($v as $vk => $vv2) {
					if (is_scalar($vv2)) {
						$s = is_bool($vv2) ? ($vv2 ? 'yes' : 'no') : trim((string) $vv2);
						if ($s !== '') { $flat[] = str_replace('_', ' ', (string) $vk) . ' ' . $s; }
					}
				}
				if ($flat) { $out['inclusions'][] = $key . ': ' . implode(', ', array_slice($flat, 0, 10)); }
			}
		}
		return $out;
	}
}

if ( ! function_exists('competitor_build_scraped_agent'))
{
	/**
	 * Build the Responses API pieces when WE scraped the page ourselves: the
	 * already-extracted page text is the source, so there is NO web_search tool
	 * and the model must not browse. Same output contract as the other modes.
	 * Returns ['instructions' => system prompt, 'input' => user text].
	 */
	function competitor_build_scraped_agent($url, $text, $our_products)
	{
		$instructions = "You are a travel-industry competitor analyst for HolidayGoGoGo, a Malaysian tour operator. "
			. "The user gives you the TEXT already scraped from a competitor product page — use ONLY that text; "
			. "do not browse the web and do not rely on prior knowledge. "
			. "Extract the competitor product's details and compare it against the user's own products. "
			. competitor_output_contract();

		$input = "COMPETITOR PAGE URL: " . $url . "\n\n"
			. "SCRAPED PAGE CONTENT:\n" . $text . "\n\n"
			. "OUR PRODUCTS (JSON, prices in MYR):\n" . competitor_products_block($our_products);

		return array('instructions' => $instructions, 'input' => $input);
	}
}

if ( ! function_exists('competitor_extract_text_urls'))
{
	/**
	 * Pull the http(s) URLs out of a block of pasted free text (notes the user
	 * typed, with links dropped in). Strips trailing punctuation/brackets,
	 * dedupes (keeping order), caps at $limit. Pure — no network. Returns [].
	 */
	function competitor_extract_text_urls($text, $limit = 10)
	{
		$text = (string) $text;
		if ($text === '' || ! preg_match_all('#https?://[^\s"\'<>)\]]+#i', $text, $m)) {
			return array();
		}
		$out = array();
		foreach ($m[0] as $u) {
			$u = preg_replace('/[.,);\]]+$/', '', trim($u));
			if ($u === '' || ! preg_match('#^https?://#i', $u) || isset($out[$u])) {
				continue;
			}
			$out[$u] = true;
			if ($limit > 0 && count($out) >= $limit) {
				break;
			}
		}
		return array_keys($out);
	}
}

if ( ! function_exists('competitor_paste_source_label'))
{
	/**
	 * A short "source" label for a pasted-text analysis history row: the first
	 * URL found in the text, else the generic "Pasted text". Pure.
	 */
	function competitor_paste_source_label($text)
	{
		$urls = competitor_extract_text_urls($text, 1);
		return ! empty($urls) ? $urls[0] : 'Pasted text';
	}
}

if ( ! function_exists('competitor_build_paste_agent'))
{
	/**
	 * Build the Responses API pieces for a PASTED-TEXT analysis: the user typed
	 * some notes and (optionally) dropped in one or more links. We fetch each
	 * link's page text OURSELVES and pass it here, so the model sees the pasted
	 * notes plus the scraped content of every link. $links is a list of
	 * ['url' => …, 'text' => …] ('' text = a page we could not read). When
	 * $allow_browse is true (some link came back unreadable and web_search is
	 * available) the instructions let the model open those URLs itself; the
	 * caller adds the web_search tool. Returns ['instructions', 'input'].
	 */
	function competitor_build_paste_agent($notes, $links, $our_products, $allow_browse = false)
	{
		$links = is_array($links) ? $links : array();

		$instructions = "You are a travel-industry competitor analyst for HolidayGoGoGo, a Malaysian tour operator. "
			. "The user pasted some notes and one or more competitor links. Use the pasted notes together with the "
			. "SCRAPED PAGE CONTENT of each link below — do not invent facts that are not shown. ";
		if ($allow_browse) {
			$instructions .= "For any link marked as unreadable, use the web_search tool to OPEN and READ that page "
				. "before analysing it. ";
		} else {
			$instructions .= "Do not browse the web; rely only on what is given. ";
		}
		$instructions .= "Extract the competitor product's details and compare it against the user's own products. "
			. competitor_output_contract();

		$input = "PASTED NOTES:\n" . trim((string) $notes) . "\n\n";
		if ( ! empty($links)) {
			$n = 0;
			foreach ($links as $ln) {
				$n++;
				$url  = isset($ln['url']) ? (string) $ln['url'] : '';
				$body = isset($ln['text']) ? trim((string) $ln['text']) : '';
				$input .= "LINK " . $n . " URL: " . $url . "\n";
				$input .= ($body !== '')
					? ("SCRAPED PAGE CONTENT:\n" . $body . "\n\n")
					: ("(This page's content could not be read automatically" . ($allow_browse ? " — open it with web_search." : ".") . ")\n\n");
			}
		}
		$input .= "OUR PRODUCTS (JSON, prices in MYR):\n" . competitor_products_block($our_products);

		return array('instructions' => $instructions, 'input' => $input);
	}
}

if ( ! function_exists('competitor_build_discovery_agent'))
{
	/**
	 * Build the Responses API pieces for AI-assisted product DISCOVERY — the
	 * fallback when our HTML crawl finds nothing (a JavaScript-rendered site).
	 * The web_search agent browses the site and returns a JSON array of individual
	 * tour/product page URLs (not analysis, just the links). Returns
	 * ['instructions', 'input']; the caller adds the web_search tool.
	 */
	function competitor_build_discovery_agent($base_url, $limit = 10, $keyword = '')
	{
		$limit   = (int) $limit > 0 ? (int) $limit : 10;
		$keyword = trim((string) $keyword);
		$focus   = ($keyword !== '')
			? "Collect ONLY tours whose destination/title matches the keyword \"" . $keyword . "\"; skip unrelated tours. "
			: "";
		$instructions = "You are a web researcher for a Malaysian tour operator. "
			. "Use the web_search tool to OPEN and BROWSE the competitor travel website at the URL given — "
			. "including its tour/package listing pages — and collect the URLs of individual TOUR/PRODUCT DETAIL "
			. "pages (a specific tour, not the homepage and not a category/listing page). "
			. $focus
			. "Only include pages on the same website. "
			. "Reply with ONLY a JSON array of up to " . $limit . " absolute URL strings — no prose, no markdown.";
		$input = "SITE URL (browse this and its tour listings): " . $base_url . "\n"
			. ($keyword !== '' ? "FOCUS keyword (match tours to this): " . $keyword . "\n" : "")
			. "Return up to " . $limit . " individual tour/product page URLs as a JSON array.";
		return array('instructions' => $instructions, 'input' => $input);
	}
}

if ( ! function_exists('competitor_parse_url_list'))
{
	/**
	 * Normalise the discovery agent's reply into a clean product-URL list.
	 * Accepts a JSON array of strings or of {url:…} objects; falls back to
	 * scraping bare http(s) URLs out of prose. Keeps http(s) only, restricts to
	 * $base_host when given (www-insensitive), dedupes, caps at $limit. Pure.
	 */
	function competitor_parse_url_list($text, $base_host = '', $limit = 10)
	{
		$text = trim((string) $text);
		if ($text === '') {
			return array();
		}
		$text = preg_replace('/^```(?:json)?\s*/i', '', $text);
		$text = preg_replace('/\s*```$/', '', $text);

		$raw  = array();
		$data = json_decode($text, true);
		if (is_array($data)) {
			foreach ($data as $item) {
				if (is_string($item)) {
					$raw[] = $item;
				} elseif (is_array($item) && isset($item['url'])) {
					$raw[] = $item['url'];
				}
			}
		}
		if (empty($raw) && preg_match_all('#https?://[^\s"\'<>)\]]+#i', $text, $m)) {
			$raw = $m[0];
		}

		$norm = function ($h) { return preg_replace('/^www\./i', '', strtolower((string) $h)); };
		$want = $norm($base_host);
		$out  = array();
		foreach ($raw as $u) {
			$u = preg_replace('/[.,);\]]+$/', '', trim((string) $u));
			if ($u === '' || ! preg_match('#^https?://#i', $u)) {
				continue;
			}
			if ($base_host !== '' && $norm(parse_url($u, PHP_URL_HOST)) !== $want) {
				continue;
			}
			if (isset($out[$u])) {
				continue;
			}
			$out[$u] = true;
			if ($limit > 0 && count($out) >= $limit) {
				break;
			}
		}
		return array_keys($out);
	}
}

if ( ! function_exists('competitor_build_file_agent'))
{
	/**
	 * Build the Responses API pieces for an uploaded PDF/image competitor
	 * product sheet. Returns ['instructions' => system prompt, 'text' => the
	 * user text prelude]; the caller appends the file content part (see
	 * competitor_file_input_part()) to the same user message. No web_search —
	 * the document IS the source.
	 */
	function competitor_build_file_agent($our_products)
	{
		$instructions = "You are a travel-industry competitor analyst for HolidayGoGoGo, a Malaysian tour operator. "
			. "READ the attached competitor product document/image (a brochure, flyer, itinerary or screenshot) — "
			. "use only what it shows; do not rely on prior knowledge. "
			. "Extract the competitor product's details and compare it against the user's own products. "
			. competitor_output_contract();

		$text = "Analyse the attached competitor product file.\n\n"
			. "OUR PRODUCTS (JSON, prices in MYR):\n" . competitor_products_block($our_products);

		return array('instructions' => $instructions, 'text' => $text);
	}
}

if ( ! function_exists('competitor_file_input_part'))
{
	/**
	 * Turn an uploaded file's extension + base64 data into the Responses API
	 * content part: an `input_image` for pictures, an `input_file` for PDFs.
	 * Returns null for anything unsupported. Pure — the caller does the base64.
	 */
	function competitor_file_input_part($ext, $base64)
	{
		$ext = strtolower(ltrim((string) $ext, '.'));
		$image_mimes = array(
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'png'  => 'image/png',
			'gif'  => 'image/gif',
			'webp' => 'image/webp',
		);
		if (isset($image_mimes[$ext])) {
			return array(
				'type'      => 'input_image',
				'image_url' => 'data:' . $image_mimes[$ext] . ';base64,' . $base64,
			);
		}
		if ($ext === 'pdf') {
			return array(
				'type'      => 'input_file',
				'filename'  => 'competitor.pdf',
				'file_data' => 'data:application/pdf;base64,' . $base64,
			);
		}
		return null;
	}
}

if ( ! function_exists('competitor_extract_responses_text'))
{
	/**
	 * Pull the assistant's final text out of a decoded OpenAI Responses API
	 * body. The raw HTTP response carries an `output` array (web_search_call
	 * items plus a `message` item whose content holds `output_text` parts);
	 * `output_text` at the top level is an SDK convenience we still honour when
	 * present. Returns '' when nothing usable is found.
	 */
	function competitor_extract_responses_text($decoded)
	{
		if ( ! is_array($decoded)) {
			return '';
		}
		// Top-level convenience field (string or array of strings).
		if (isset($decoded['output_text'])) {
			$ot = $decoded['output_text'];
			if (is_string($ot) && trim($ot) !== '') {
				return trim($ot);
			}
			if (is_array($ot)) {
				$joined = trim(implode('', array_map('strval', $ot)));
				if ($joined !== '') {
					return $joined;
				}
			}
		}
		// Walk the output array for message -> output_text parts.
		$text = '';
		if (isset($decoded['output']) && is_array($decoded['output'])) {
			foreach ($decoded['output'] as $item) {
				if ( ! is_array($item) || (isset($item['type']) && $item['type'] !== 'message')) {
					continue;
				}
				if ( ! isset($item['content']) || ! is_array($item['content'])) {
					continue;
				}
				foreach ($item['content'] as $part) {
					if (is_array($part) && isset($part['type']) && $part['type'] === 'output_text' && isset($part['text'])) {
						$text .= (string) $part['text'];
					}
				}
			}
		}
		return trim($text);
	}
}

if ( ! function_exists('competitor_extract_usage'))
{
	/**
	 * Pull token counts out of a decoded OpenAI response body so we can cost the
	 * call. The Responses API reports `usage.input_tokens` / `usage.output_tokens`;
	 * we also accept the Chat Completions names (prompt_/completion_tokens) as a
	 * fallback. Returns ['input_tokens' => int, 'output_tokens' => int], zeros when
	 * usage is absent.
	 */
	function competitor_extract_usage($decoded)
	{
		$in = 0; $out = 0;
		if (is_array($decoded) && isset($decoded['usage']) && is_array($decoded['usage'])) {
			$u = $decoded['usage'];
			if (isset($u['input_tokens'])) {
				$in = (int) $u['input_tokens'];
			} elseif (isset($u['prompt_tokens'])) {
				$in = (int) $u['prompt_tokens'];
			}
			if (isset($u['output_tokens'])) {
				$out = (int) $u['output_tokens'];
			} elseif (isset($u['completion_tokens'])) {
				$out = (int) $u['completion_tokens'];
			}
		}
		return array('input_tokens' => $in, 'output_tokens' => $out);
	}
}

if ( ! function_exists('competitor_model_prices'))
{
	/**
	 * Published OpenAI list prices in USD per 1,000,000 tokens as [input, output].
	 * Used to turn token counts into a cost estimate. Keys are lower-case model
	 * ids; unknown models fall back to gpt-4o-mini in competitor_estimate_cost().
	 * Token-based only — the web_search tool's per-call fee is not included.
	 */
	function competitor_model_prices()
	{
		return array(
			'gpt-4o-mini'  => array(0.15, 0.60),
			'gpt-4o'       => array(2.50, 10.00),
			'gpt-4.1'      => array(2.00, 8.00),
			'gpt-4.1-mini' => array(0.40, 1.60),
			'gpt-4.1-nano' => array(0.10, 0.40),
			'o4-mini'      => array(1.10, 4.40),
			// GPT-5 family — approximate launch-era list prices (USD/1M tokens). Set
			// OPENAI_PRICE_INPUT / OPENAI_PRICE_OUTPUT in .env for exact costing.
			'gpt-5'        => array(1.25, 10.00),
			'gpt-5-mini'   => array(0.25, 2.00),
			'gpt-5-nano'   => array(0.05, 0.40),
			'gpt-5.4-mini' => array(0.25, 2.00),
			'gpt-5.5'      => array(1.25, 10.00),
			'gpt-5.5-pro'  => array(15.00, 120.00),
		);
	}
}

if ( ! function_exists('competitor_model_supports_temperature'))
{
	/**
	 * Whether a model accepts a custom `temperature` on the Responses API.
	 * Reasoning models (o-series, gpt-5+) only allow the default and 400 when a
	 * temperature is sent — EXCEPT their `-chat` variants. Pure.
	 */
	function competitor_model_supports_temperature($model)
	{
		$m = strtolower(trim((string) $model));
		if ($m === '') {
			return true;
		}
		$is_reasoning = (bool) preg_match('/^o\d/', $m) || (bool) preg_match('/^gpt-5/', $m);
		return ! ($is_reasoning && strpos($m, 'chat') === false);
	}
}

if ( ! function_exists('competitor_web_search_tool_for_model'))
{
	/**
	 * The web_search tool `type` a given model expects. GPT-5 / o-series use the
	 * GA `web_search`; older gpt-4o uses `web_search_preview`. A non-empty
	 * OPENAI_WEB_SEARCH_TOOL override always wins. Pure.
	 */
	function competitor_web_search_tool_for_model($model, $override = '')
	{
		$override = trim((string) $override);
		if ($override !== '') {
			return $override;
		}
		$m = strtolower(trim((string) $model));
		if (preg_match('/^o\d/', $m) || preg_match('/^gpt-5/', $m)) {
			return 'web_search';
		}
		return 'web_search_preview';
	}
}

if ( ! function_exists('competitor_estimate_cost'))
{
	/**
	 * Estimate the USD cost of one call from its token counts. Pass explicit
	 * $rates = ['input' => x, 'output' => y] (USD per 1M tokens, e.g. from .env)
	 * to override; otherwise the model is looked up in competitor_model_prices(),
	 * defaulting to gpt-4o-mini. Rounded to 6 dp. Pure.
	 */
	function competitor_estimate_cost($model, $input_tokens, $output_tokens, $rates = null)
	{
		if ( ! is_array($rates) || ! isset($rates['input']) || ! isset($rates['output'])) {
			$table = competitor_model_prices();
			$key   = strtolower(trim((string) $model));
			$rate  = isset($table[$key]) ? $table[$key] : $table['gpt-4o-mini'];
			$rates = array('input' => $rate[0], 'output' => $rate[1]);
		}
		$cost = ((int) $input_tokens  / 1000000) * (float) $rates['input']
		      + ((int) $output_tokens / 1000000) * (float) $rates['output'];
		return round($cost, 6);
	}
}

if ( ! function_exists('competitor_parse_ai_response'))
{
	/**
	 * Normalise the model's JSON reply into a flat record the model layer can
	 * store. Tolerates code-fenced ```json blocks and stray prose around the
	 * object. Returns null when no JSON object can be recovered. List fields
	 * are always arrays of strings; scalar fields always strings.
	 */
	function competitor_parse_ai_response($content)
	{
		$content = trim((string) $content);
		if ($content === '') {
			return null;
		}
		// Strip a leading/trailing ```json ... ``` fence if present.
		$content = preg_replace('/^```(?:json)?\s*/i', '', $content);
		$content = preg_replace('/\s*```$/', '', $content);

		$data = json_decode($content, true);
		if ( ! is_array($data)) {
			// Last resort: grab the outermost {...} span.
			if (preg_match('/\{.*\}/s', $content, $m)) {
				$data = json_decode($m[0], true);
			}
		}
		if ( ! is_array($data)) {
			return null;
		}

		$str = function ($v) {
			if (is_array($v)) {
				$v = implode(', ', array_map('strval', $v));
			}
			return trim((string) $v);
		};
		$list = function ($v) {
			if (is_string($v)) {
				$v = $v === '' ? array() : preg_split('/\s*[\n;•]\s*/u', $v);
			}
			if ( ! is_array($v)) {
				return array();
			}
			$out = array();
			foreach ($v as $item) {
				$item = trim((string) (is_array($item) ? implode(' ', $item) : $item));
				if ($item !== '') {
					$out[] = $item;
				}
			}
			return array_values($out);
		};
		// Meals always returns the three known slots (blank when unknown) so the
		// view can render a stable breakfast/lunch/dinner row.
		$meals = function ($v) use ($str) {
			$v = is_array($v) ? $v : array();
			return array(
				'breakfast' => $str(isset($v['breakfast']) ? $v['breakfast'] : ''),
				'lunch'     => $str(isset($v['lunch']) ? $v['lunch'] : ''),
				'dinner'    => $str(isset($v['dinner']) ? $v['dinner'] : ''),
			);
		};
		// Itinerary: a clean [{day,title,description}] list. A bare-string entry
		// becomes that day's description; a numeric day gets a "Day N" label.
		$itin = function ($v) use ($str) {
			if ( ! is_array($v)) {
				return array();
			}
			$out = array();
			$n = 0;
			foreach ($v as $d) {
				$n++;
				if ( ! is_array($d)) {
					$desc = trim((string) $d);
					if ($desc === '') { continue; }
					$out[] = array('day' => 'Day ' . $n, 'title' => '', 'description' => $desc);
					continue;
				}
				$day = $str(isset($d['day']) ? $d['day'] : '');
				if ($day === '') {
					$day = 'Day ' . $n;
				} elseif (preg_match('/^\d+$/', $day)) {
					$day = 'Day ' . $day;
				}
				$title = $str(isset($d['title']) ? $d['title'] : '');
				$desc  = $str(isset($d['description']) ? $d['description'] : (isset($d['desc']) ? $d['desc'] : ''));
				if ($title === '' && $desc === '') { continue; }
				$out[] = array('day' => $day, 'title' => $title, 'description' => $desc);
			}
			return array_values($out);
		};

		$get = function ($key) use ($data) {
			return isset($data[$key]) ? $data[$key] : null;
		};

		return array(
			// Scalars.
			'product_name'     => $str($get('product_name')),
			'tour_code'        => $str($get('tour_code')),
			'price'            => $str($get('price')),
			'price_from'       => $str($get('price_from')),
			'price_to'         => $str($get('price_to')),
			'currency'         => $str($get('currency')),
			'destination'      => $str($get('destination')),
			'duration'         => $str($get('duration')),
			'departure_city'   => $str($get('departure_city')),
			'flight_departure' => $str($get('flight_departure')),
			'flight_return'    => $str($get('flight_return')),
			'difficulty'       => $str($get('difficulty')),
			'target_traveller' => $str($get('target_traveller')),
			'suitable_age'     => $str($get('suitable_age')),
			'child_friendly'   => $str($get('child_friendly')),
			'senior_friendly'  => $str($get('senior_friendly')),
			'summary'          => $str($get('summary')),
			'comparison'       => $str($get('comparison')),
			'matched_product'  => $str($get('matched_product')),
			// Multi-entry lists.
			'countries'        => $list($get('countries')),
			'cities'           => $list($get('cities')),
			'travel_months'    => $list($get('travel_months')),
			'themes'           => $list($get('themes')),
			'tour_styles'      => $list($get('tour_styles')),
			'local_transport'  => $list($get('local_transport')),
			'inclusions'       => $list($get('inclusions')),
			'exclusions'       => $list($get('exclusions')),
			'hotels'           => $list($get('hotels')),
			'shopping_stops'   => $list($get('shopping_stops')),
			'optional_tours'   => $list($get('optional_tours')),
			'special_remarks'  => $list($get('special_remarks')),
			'scenic_highlights'=> $list($get('scenic_highlights')),
			'signature_meals'  => $list($get('signature_meals')),
			'usp'              => $list($get('usp')),
			'pros'             => $list($get('pros')),
			'cons'             => $list($get('cons')),
			// Structured.
			'meals'            => $meals($get('meals')),
			'itinerary'        => $itin($get('itinerary')),
		);
	}
}
