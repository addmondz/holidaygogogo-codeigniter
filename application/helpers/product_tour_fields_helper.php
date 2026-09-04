<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Single source of truth for the "tour attribute" fields added to the Product
 * form (Product/Create + Product/Update). These mirror the data points shown on
 * the Competitor Analysis >> View page so our own products carry the same rich
 * detail (destination, itinerary, inclusions, ...).
 *
 * Every entry maps 1:1 to a real column on the `product` table (same name), so
 * the view can render <input id="{col}"> / <textarea id="{col}"> and the form's
 * dirty-field tracking writes each back to its column automatically.
 *
 * Pure + DB-free so it can be unit tested and reused by the view and JS.
 *
 * Field shape: array('col','label','type') where type is 'text' or 'textarea'.
 * 'textarea' fields hold free-form lists (one item per line).
 *
 * @return array section title => list of field definitions
 */
if ( ! function_exists('product_tour_fields'))
{
	function product_tour_fields()
	{
		return array(
			'Facts' => array(
				array('col' => 'Destination',     'label' => 'Destination',      'type' => 'text'),
				array('col' => 'Duration',        'label' => 'Duration',         'type' => 'text'),
				array('col' => 'DepartureCity',   'label' => 'Departure City',   'type' => 'text'),
				array('col' => 'Difficulty',      'label' => 'Difficulty',       'type' => 'text'),
				array('col' => 'SuitableAge',     'label' => 'Suitable Age',     'type' => 'text'),
				array('col' => 'TargetTraveller', 'label' => 'Target Traveller', 'type' => 'text'),
			),
			'Coverage' => array(
				array('col' => 'Countries',      'label' => 'Countries',       'type' => 'textarea'),
				array('col' => 'Cities',         'label' => 'Cities',          'type' => 'textarea'),
				array('col' => 'Themes',         'label' => 'Themes',          'type' => 'textarea'),
				array('col' => 'TourStyle',      'label' => 'Tour Style',      'type' => 'text'),
				array('col' => 'LocalTransport', 'label' => 'Local Transport', 'type' => 'textarea'),
			),
			'Meals' => array(
				array('col' => 'MealBreakfast', 'label' => 'Breakfast', 'type' => 'text'),
				array('col' => 'MealLunch',     'label' => 'Lunch',     'type' => 'text'),
				array('col' => 'MealDinner',    'label' => 'Dinner',    'type' => 'text'),
			),
			'Stay & Highlights' => array(
				array('col' => 'Hotels',           'label' => 'Hotels',            'type' => 'textarea'),
				array('col' => 'ScenicHighlights', 'label' => 'Scenic Highlights', 'type' => 'textarea'),
				array('col' => 'ShoppingStops',    'label' => 'Shopping Stops',    'type' => 'textarea'),
			),
			'Inclusions' => array(
				array('col' => 'Inclusions',    'label' => 'Inclusions',     'type' => 'textarea'),
				array('col' => 'Exclusions',    'label' => 'Exclusions',     'type' => 'textarea'),
				array('col' => 'OptionalTours', 'label' => 'Optional Tours', 'type' => 'textarea'),
			),
			'Detail' => array(
				array('col' => 'Itinerary',      'label' => 'Itinerary (one day per line)', 'type' => 'textarea'),
				array('col' => 'SpecialRemarks', 'label' => 'Special Remarks',              'type' => 'textarea'),
			),
		);
	}
}

/**
 * Flat list of every tour-attribute field definition (across all sections).
 *
 * @return array list of array('col','label','type')
 */
if ( ! function_exists('product_tour_fields_flat'))
{
	function product_tour_fields_flat()
	{
		$out = array();
		foreach (product_tour_fields() as $fields) {
			foreach ($fields as $f) {
				$out[] = $f;
			}
		}
		return $out;
	}
}

/**
 * Just the column names, e.g. array('Destination','Duration',...). Used by the
 * form JS to know which posted fields are free-form text and must NOT be
 * force-uppercased (unlike Name / product code).
 *
 * @return array
 */
if ( ! function_exists('product_tour_field_columns'))
{
	function product_tour_field_columns()
	{
		$out = array();
		foreach (product_tour_fields_flat() as $f) {
			$out[] = $f['col'];
		}
		return $out;
	}
}

/**
 * The fields of ONE flight section. A product can hold MANY flights (stored as a
 * JSON array in the single `product`.`Flights` column), each rendered as a
 * repeatable card in the "Flights" tab. Shape: array('key','label','type'[,'options']).
 *
 * @return array
 */
if ( ! function_exists('product_flight_fields'))
{
	function product_flight_fields()
	{
		return array(
			array('key' => 'direction', 'label' => 'Direction',           'type' => 'select', 'options' => array('Outbound', 'Return', 'Domestic')),
			array('key' => 'airline',   'label' => 'Airline',             'type' => 'text'),
			array('key' => 'flight_no', 'label' => 'Flight No',           'type' => 'text'),
			array('key' => 'from',      'label' => 'From',                'type' => 'text'),
			array('key' => 'to',        'label' => 'To',                  'type' => 'text'),
			array('key' => 'depart',    'label' => 'Depart (date & time)', 'type' => 'text'),
			array('key' => 'arrive',    'label' => 'Arrive (date & time)', 'type' => 'text'),
		);
	}
}

/**
 * Normalise a stored Flights value (JSON string or already-decoded array) into a
 * clean list of flight rows. Every row carries all flight keys (blank when
 * missing), keys in the canonical product_flight_fields() order so the form's
 * "changed?" check can string-compare reliably. Rows with no real content beyond
 * the direction are dropped. Pure + safe on junk.
 *
 * @param string|array $json
 * @return array
 */
if ( ! function_exists('product_flights_decode'))
{
	function product_flights_decode($json)
	{
		if (is_array($json)) {
			$arr = $json;
		} else {
			$json = trim((string) $json);
			if ($json === '') {
				return array();
			}
			$arr = json_decode($json, true);
			if ( ! is_array($arr)) {
				return array();
			}
		}
		$keys = array();
		foreach (product_flight_fields() as $f) {
			$keys[] = $f['key'];
		}
		$out = array();
		foreach ($arr as $row) {
			if ( ! is_array($row)) {
				continue;
			}
			$flight = array();
			$has = false;
			foreach ($keys as $k) {
				$v = isset($row[$k]) ? trim((string) $row[$k]) : '';
				$flight[$k] = $v;
				if ($v !== '' && $k !== 'direction') {
					$has = true;
				}
			}
			if ($has) {
				$out[] = $flight;
			}
		}
		return $out;
	}
}

/**
 * One readable line for a flight row, e.g.
 *   "Outbound AirAsia AK204: KUL -> CXR, depart 26 Sep 10:10, arrive 26 Sep 11:30"
 * Empty parts are skipped. Used for the Competitor Analysis "our products" block.
 *
 * @param array $f
 * @return string
 */
if ( ! function_exists('product_flight_summary'))
{
	function product_flight_summary($f)
	{
		if ( ! is_array($f)) {
			return '';
		}
		$g = function ($k) use ($f) {
			return isset($f[$k]) ? trim((string) $f[$k]) : '';
		};
		$head = array();
		if ($g('direction') !== '') { $head[] = $g('direction'); }
		$flight = trim($g('airline') . ' ' . $g('flight_no'));
		if ($flight !== '') { $head[] = $flight; }
		$line = implode(' ', $head);

		$route = '';
		if ($g('from') !== '' || $g('to') !== '') {
			$route = trim($g('from') . ' -> ' . $g('to'));
			$route = trim($route, '-> ');
		}
		if ($route !== '') {
			$line = ($line !== '' ? $line . ': ' : '') . $route;
		}

		$tail = array();
		if ($g('depart') !== '') { $tail[] = 'depart ' . $g('depart'); }
		if ($g('arrive') !== '') { $tail[] = 'arrive ' . $g('arrive'); }
		if ( ! empty($tail)) {
			$line = trim($line . ($line !== '' ? ', ' : '') . implode(', ', $tail));
		}
		return trim($line);
	}
}
