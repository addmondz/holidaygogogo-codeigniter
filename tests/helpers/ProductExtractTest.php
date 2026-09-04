<?php
/**
 * Run with: php tests/helpers/ProductExtractTest.php
 *
 * Locks the pure halves of the Product form's "Extract from link" feature:
 *   - product_extract_decode()  fence/prose-tolerant JSON parse of the AI reply
 *   - product_extract_map()     AI JSON -> {fields:{Col=>value}, flights:[...]}
 *
 * No DB/network. Loads product_tour_fields_helper (flights decode) first.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}
require_once __DIR__ . '/../../application/helpers/product_tour_fields_helper.php';
require_once __DIR__ . '/../../application/helpers/product_extract_helper.php';

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

// ---- product_extract_decode -------------------------------------------------
check('decode plain JSON', array('a' => 1), product_extract_decode('{"a":1}'));
check('decode strips ```json fence', array('a' => 1), product_extract_decode("```json\n{\"a\":1}\n```"));
check('decode strips bare ``` fence', array('a' => 1), product_extract_decode("```\n{\"a\":1}\n```"));
check('decode grabs outermost braces from prose', array('a' => 1),
    product_extract_decode('Here you go: {"a":1} thanks'));
check('decode null on junk', null, product_extract_decode('not json at all'));
check('decode null on empty', null, product_extract_decode(''));

// ---- product_extract_map ----------------------------------------------------
$ai = array(
    'destination'   => 'Redang',
    'duration'      => '3D2N',
    'departure_city'=> '',
    'tour_style'    => 'Free & easy',
    'countries'     => array('Malaysia'),
    'cities'        => array('Redang Island', 'Kuala Terengganu'),
    'themes'        => array('Beach', 'Snorkeling'),
    'meals'         => array('breakfast' => '2 (Day 1,2)', 'lunch' => '', 'dinner' => '2'),
    'inclusions'    => array('Redang Beach Resort', 'Boat transfer', 'Snorkeling trips'),
    'itinerary'     => array(
        array('day' => '1', 'title' => 'Arrival', 'description' => 'Jetty transfer, check-in, free time'),
        array('day' => 'Day 2', 'title' => 'Snorkeling', 'description' => 'Marine Park'),
        array('day' => '', 'title' => '', 'description' => 'Departure'),
    ),
    'flights'       => array(
        array('direction' => 'Outbound', 'airline' => 'Firefly', 'flight_no' => 'FY123', 'from' => 'SZB', 'to' => 'TGG', 'depart' => '', 'arrive' => ''),
        array('direction' => 'Return'),  // empty -> dropped by flights decode
    ),
    'exclusions'    => array(),          // empty -> omitted
);
$m = product_extract_map($ai);
$f = $m['fields'];

check('maps destination', 'Redang', $f['Destination']);
check('maps duration', '3D2N', $f['Duration']);
check('omits empty departure_city', false, array_key_exists('DepartureCity', $f));
check('maps tour_style single', 'Free & easy', $f['TourStyle']);
check('countries list -> newline text', 'Malaysia', $f['Countries']);
check('cities list -> newline text', "Redang Island\nKuala Terengganu", $f['Cities']);
check('inclusions list -> newline text', "Redang Beach Resort\nBoat transfer\nSnorkeling trips", $f['Inclusions']);
check('meals breakfast', '2 (Day 1,2)', $f['MealBreakfast']);
check('meals empty lunch omitted', false, array_key_exists('MealLunch', $f));
check('meals dinner', '2', $f['MealDinner']);
check('itinerary -> Day lines',
    "Day 1: Arrival — Jetty transfer, check-in, free time\nDay 2: Snorkeling — Marine Park\nDay 3: Departure",
    $f['Itinerary']);
check('omits empty exclusions', false, array_key_exists('Exclusions', $f));

check('flights: real leg kept, empty dropped', 1, count($m['flights']));
check('flights normalised keys', array('direction','airline','flight_no','from','to','depart','arrive'),
    array_keys($m['flights'][0]));
check('flight airline', 'Firefly', $m['flights'][0]['airline']);

// ---- robustness -------------------------------------------------------------
$empty = product_extract_map(array());
check('empty AI -> no fields', array(), $empty['fields']);
check('empty AI -> no flights', array(), $empty['flights']);
check('non-array input safe', array('fields' => array(), 'flights' => array()), product_extract_map('nope'));

echo $failures === 0 ? "\nALL PASS\n" : "\n{$failures} FAILURE(S)\n";
exit($failures === 0 ? 0 : 1);
