<?php
/**
 * Run with: php tests/helpers/ProductTourFieldsTest.php
 *
 * Locks product_tour_fields_helper — the single source of truth for the tour-
 * attribute fields (destination, itinerary, inclusions, ...) added to the
 * Product form. The migration, the form view and the form JS all key off this
 * list, so it must stay well-formed:
 *   - every field has col + label + a valid type (text|textarea),
 *   - column names are unique and safe (used verbatim as DB columns + input ids),
 *   - the flat + columns helpers agree with the sectioned definition.
 *
 * Runs without a DB. BASEPATH is stubbed so the guarded helper file loads.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}
require_once __DIR__ . '/../../application/helpers/product_tour_fields_helper.php';

$failures = 0;
function check($label, $expected, $actual) {
    global $failures;
    if ($expected === $actual) {
        echo "PASS  {$label}\n";
    } else {
        $failures++;
        echo "FAIL  {$label}\n";
        echo "      expected: " . json_encode($expected) . "\n";
        echo "      actual:   " . json_encode($actual) . "\n";
    }
}

$sections = product_tour_fields();
$flat     = product_tour_fields_flat();
$cols     = product_tour_field_columns();

// ---- Sections are non-empty and flat == sum of sections --------------------
check('has sections', true, is_array($sections) && count($sections) > 0);
$sum = 0;
foreach ($sections as $s) { $sum += count($s); }
check('flat count equals sum of section fields', $sum, count($flat));
check('columns count equals flat count', count($flat), count($cols));

// ---- Every field is well-formed -------------------------------------------
$all_valid = true;
$valid_types = array('text', 'textarea');
foreach ($flat as $f) {
    if (!isset($f['col'], $f['label'], $f['type'])) { $all_valid = false; break; }
    if ($f['col'] === '' || $f['label'] === '') { $all_valid = false; break; }
    if (!in_array($f['type'], $valid_types, true)) { $all_valid = false; break; }
    // Column names are used verbatim as SQL columns + DOM ids: letters only.
    if (!preg_match('/^[A-Za-z]+$/', $f['col'])) { $all_valid = false; break; }
}
check('every field has col+label+valid type+safe column name', true, $all_valid);

// ---- Column names are unique ----------------------------------------------
check('column names are unique', count($cols), count(array_unique($cols)));

// ---- Sanity: a few expected columns are present ----------------------------
foreach (array('Destination', 'Itinerary', 'Inclusions', 'Hotels') as $expected_col) {
    check("has column {$expected_col}", true, in_array($expected_col, $cols, true));
}

// ---- Flights are NOT flat tour columns (they live in the JSON Flights col) --
foreach (array('FlightDeparture', 'FlightReturn', 'Flights') as $not_col) {
    check("'{$not_col}' is not a flat tour column", false, in_array($not_col, $cols, true));
}

// ---- None collide with existing product columns ----------------------------
$existing = array('ProductID','CategoryID','SupplierID','ProductCode','Name',
    'RetailPrice','SupplierPrice','is_child_or_infant','has_supplier_deposit',
    'Status','InsertBy','InsertDate','UpdateBy','UpdateDate');
$collide = array_intersect($cols, $existing);
check('no collision with existing product columns', array(), array_values($collide));

// ---- Flight section helpers -------------------------------------------------
$fflds = product_flight_fields();
check('flight fields defined', true, is_array($fflds) && count($fflds) > 0);
$fkeys = array();
foreach ($fflds as $f) { $fkeys[] = $f['key']; }
check('flight fields include direction+route+times', true,
    in_array('direction', $fkeys, true) && in_array('flight_no', $fkeys, true)
    && in_array('depart', $fkeys, true) && in_array('arrive', $fkeys, true));

// decode: JSON string -> normalised rows, junk-safe, drops empty rows
$decoded = product_flights_decode('[{"direction":"Outbound","airline":"AirAsia","flight_no":"AK204","from":"KUL","to":"CXR","depart":"26 Sep 10:10","arrive":"26 Sep 11:30"},{"direction":"Return"}]');
check('decode keeps the real flight, drops the direction-only row', 1, count($decoded));
check('decode normalises all keys in order', $fkeys, array_keys($decoded[0]));
check('decode empty on blank', array(), product_flights_decode(''));
check('decode empty on junk', array(), product_flights_decode('not json'));
check('decode accepts an array too', 1,
    count(product_flights_decode(array(array('airline' => 'MAS', 'flight_no' => 'MH1')))));

// decode output round-trips to the same JSON (form "changed?" check relies on this)
$rt = product_flights_decode(json_encode($decoded));
check('decode is idempotent (stable JSON)', json_encode($decoded), json_encode($rt));

// summary: readable one-liner, empties skipped
check('flight summary full line',
    'Outbound AirAsia AK204: KUL -> CXR, depart 26 Sep 10:10, arrive 26 Sep 11:30',
    product_flight_summary($decoded[0]));
check('flight summary skips empty parts', 'Return MH1',
    product_flight_summary(array('direction' => 'Return', 'airline' => '', 'flight_no' => 'MH1')));
check('flight summary empty on junk', '', product_flight_summary('nope'));

echo $failures === 0 ? "\nALL PASS\n" : "\n{$failures} FAILURE(S)\n";
exit($failures === 0 ? 0 : 1);
