<?php
/**
 * Run with: php tests/helpers/CostingQuoteHelperTest.php
 *
 * Drives the pure quote helpers that back Costing_Model::Save_Quote_Details:
 * price normalisation, per-row hotel/flight normalisation (blank rows skipped),
 * quote-level field prep (only known columns, price coerced to float), and the
 * footer-notes splitter / default boilerplate.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require_once __DIR__ . '/../../application/helpers/costing_quote_helper.php';

$assertions = array();

// --- costing_quote_price_normalize -----------------------------------------
$assertions['price plain']       = costing_quote_price_normalize('1999') === 1999.0;
$assertions['price commas']      = costing_quote_price_normalize('1,999.00') === 1999.0;
$assertions['price rm prefix']   = costing_quote_price_normalize('RM 1,708') === 1708.0;
$assertions['price decimals']    = costing_quote_price_normalize('99.5') === 99.5;
$assertions['price blank null']  = costing_quote_price_normalize('') === null;
$assertions['price dash null']   = costing_quote_price_normalize('-') === null;
$assertions['price junk null']   = costing_quote_price_normalize('abc') === null;
$assertions['price array null']  = costing_quote_price_normalize(array(1)) === null;

// --- costing_quote_hotel_prepare_row ---------------------------------------
$hotel = costing_quote_hotel_prepare_row(array(
    'hotel_name'        => '  Tahiti Central Phu Quoc or similar ',
    'twin_triple_price' => '1,999',
    'single_supp_price' => 'RM 699',
));
$assertions['hotel name trimmed']  = $hotel['hotel_name'] === 'Tahiti Central Phu Quoc or similar';
$assertions['hotel twin float']    = $hotel['twin_triple_price'] === 1999.0;
$assertions['hotel single float']  = $hotel['single_supp_price'] === 699.0;
$assertions['hotel not empty']     = $hotel['is_empty'] === false;

$hotelPriceOnly = costing_quote_hotel_prepare_row(array('single_supp_price' => '500'));
$assertions['hotel price-only kept']  = $hotelPriceOnly['is_empty'] === false;
$assertions['hotel price-only null name'] = $hotelPriceOnly['hotel_name'] === null;

$hotelBlank = costing_quote_hotel_prepare_row(array('hotel_name' => '   ', 'twin_triple_price' => '', 'single_supp_price' => ''));
$assertions['hotel all blank empty'] = $hotelBlank['is_empty'] === true;
$assertions['hotel blank null name'] = $hotelBlank['hotel_name'] === null;
$assertions['hotel blank null twin'] = $hotelBlank['twin_triple_price'] === null;

// --- costing_quote_flight_prepare_row --------------------------------------
$flight = costing_quote_flight_prepare_row(array(
    'travel_date' => ' 15 Jan 2027 ',
    'sector'      => 'KUL - PQC',
    'flight_no'   => 'AK 545',
    'timing'      => '1250 - 1355',
    'duration'    => '1hr 45mins',
));
$assertions['flight date trimmed'] = $flight['travel_date'] === '15 Jan 2027';
$assertions['flight sector kept']  = $flight['sector'] === 'KUL - PQC';
$assertions['flight no kept']      = $flight['flight_no'] === 'AK 545';
$assertions['flight timing kept']  = $flight['timing'] === '1250 - 1355';
$assertions['flight duration kept'] = $flight['duration'] === '1hr 45mins';
$assertions['flight not empty']    = $flight['is_empty'] === false;

$flightPartial = costing_quote_flight_prepare_row(array('sector' => 'PQC - KUL'));
$assertions['flight partial kept']    = $flightPartial['is_empty'] === false;
$assertions['flight partial null date'] = $flightPartial['travel_date'] === null;

$flightBlank = costing_quote_flight_prepare_row(array('sector' => '  ', 'flight_no' => ''));
$assertions['flight all blank empty'] = $flightBlank['is_empty'] === true;

// --- costing_quote_level_fields / prepare_level ----------------------------
$fields = costing_quote_level_fields();
$assertions['level has price field'] = in_array('quote_flight_price', $fields, true);
$assertions['level eight fields']    = count($fields) === 8;

$level = costing_quote_prepare_level(array(
    'quote_pricing_basis'    => ' 25paxs + 1FOC ',
    'quote_flight_price'     => 'RM 1,708',
    'quote_flight_expiry'    => 'Expired valid until 10 September 2026',
    'quote_hotel_note'       => '',
    'bogus_field'            => 'should be dropped',
));
$assertions['level basis trimmed']   = $level['quote_pricing_basis'] === '25paxs + 1FOC';
$assertions['level price float']     = $level['quote_flight_price'] === 1708.0;
$assertions['level expiry kept']     = $level['quote_flight_expiry'] === 'Expired valid until 10 September 2026';
$assertions['level blank null']      = $level['quote_hotel_note'] === null;
$assertions['level no bogus key']    = !array_key_exists('bogus_field', $level);
$assertions['level only known keys'] = (array_keys($level) === $fields);

// --- costing_quote_default_footer_notes / footer_note_lines ----------------
$default = costing_quote_default_footer_notes();
$assertions['default footer non-empty'] = trim($default) !== '';
$defaultLines = costing_quote_footer_note_lines('');
$assertions['blank footer -> default lines'] = count($defaultLines) === 5;

$customLines = costing_quote_footer_note_lines("Line one\n\n  Line two  \nLine three");
$assertions['custom footer trims blanks'] = $customLines === array('Line one', 'Line two', 'Line three');

$failed = 0;
foreach ($assertions as $label => $ok) {
    echo ($ok ? '  PASS  ' : '  FAIL  ') . $label . PHP_EOL;
    if (!$ok) {
        $failed++;
    }
}

echo PHP_EOL . ($failed === 0 ? "All assertions passed.\n" : "$failed assertion(s) failed.\n");
exit($failed === 0 ? 0 : 1);
