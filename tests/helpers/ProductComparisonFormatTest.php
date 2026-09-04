<?php
/**
 * Run with: php tests/helpers/ProductComparisonFormatTest.php
 *
 * Locks the product-table path of competitor_format_our_products() — the new
 * source for the Competitor Analysis "Comparison vs Our Products" block. Our own
 * products (from Product_Model::Read_For_Comparison()) are flattened into the
 * compact OUR PRODUCTS list, enriched with tour detail + multiple structured
 * flights, and competitor_products_block() drops empty fields to save tokens.
 *
 * Runs without a DB/network. Loads both helpers (flights decode lives in the
 * product helper).
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}
require_once __DIR__ . '/../../application/helpers/product_tour_fields_helper.php';
require_once __DIR__ . '/../../application/helpers/competitor_analysis_helper.php';

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
function check_true($label, $cond) { check($label, true, (bool) $cond); }

// A product row shaped like Read_For_Comparison() returns (object w/ PascalCase cols).
$flights_json = json_encode(array(
    array('direction' => 'Outbound', 'airline' => 'AirAsia', 'flight_no' => 'AK204', 'from' => 'KUL', 'to' => 'CXR', 'depart' => '26 Sep 10:10', 'arrive' => '26 Sep 11:30'),
    array('direction' => 'Return', 'airline' => 'AirAsia', 'flight_no' => 'AK205', 'from' => 'CXR', 'to' => 'KUL'),
));
$rows = array(
    (object) array(
        'Name' => 'Bali 5D4N', 'ProductCode' => 'TOUR-9', 'RetailPrice' => '1899.00',
        'Destination' => 'Bali', 'Duration' => '5D4N', 'TourStyle' => 'Group tour',
        'Inclusions' => "4-star hotel\nDaily breakfast\nEnglish guide",
        'MealBreakfast' => '4 (Day 1-4)',
        'Flights' => $flights_json,
    ),
    (object) array(   // sparse product: only name + destination
        'Name' => 'Langkawi Free & Easy', 'ProductCode' => '', 'RetailPrice' => '0.00',
        'Destination' => 'Langkawi',
    ),
    (object) array('Name' => '', 'Destination' => 'Nowhere'),   // no name -> skipped
);

$out = competitor_format_our_products($rows);
check('formats products, drops nameless', 2, count($out));

$p0 = $out[0];
check('name kept', 'Bali 5D4N', $p0['name']);
check('ProductCode -> tour_code', 'TOUR-9', $p0['tour_code']);
check('RetailPrice -> price_myr (float)', 1899.0, $p0['price_myr']);
check('Destination -> destination', 'Bali', $p0['destination']);
check('Duration -> duration', '5D4N', $p0['duration']);
check('TourStyle -> tour_style', 'Group tour', $p0['tour_style']);
check('Inclusions multiline -> array', array('4-star hotel', 'Daily breakfast', 'English guide'), $p0['inclusions']);
check('meals nested', array('breakfast' => '4 (Day 1-4)'), $p0['meals']);
check_true('flights present as readable lines', isset($p0['flights']) && count($p0['flights']) === 2);
check('first flight summary', 'Outbound AirAsia AK204: KUL -> CXR, depart 26 Sep 10:10, arrive 26 Sep 11:30', $p0['flights'][0]);

// Sparse product: no code, zero price -> those keys omitted (no token waste).
$p1 = $out[1];
check('sparse: no tour_code key', false, array_key_exists('tour_code', $p1));
check('sparse: zero price omitted', false, array_key_exists('price_myr', $p1));
check('sparse: destination kept', 'Langkawi', $p1['destination']);

// Block JSON: keeps rich signal, still valid JSON.
$block = competitor_products_block($out);
$decoded = json_decode($block, true);
check('block decodes to 2 items', 2, count($decoded));
check_true('block carries destination', strpos($block, 'Bali') !== false);
check_true('block carries flight summary', strpos($block, 'AK204') !== false);
check_true('block carries inclusions list', strpos($block, 'English guide') !== false);
check('block drops empty tour_code on sparse row', false, array_key_exists('tour_code', $decoded[1]));

echo $failures === 0 ? "\nALL PASS\n" : "\n{$failures} FAILURE(S)\n";
exit($failures === 0 ? 0 : 1);
