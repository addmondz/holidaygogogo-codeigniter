<?php
/**
 * Run with: php tests/helpers/BookingSyncAutocountUnitTest.php
 *
 * Pins the UOM ("unit") that BookingSync sends to AutoCount for each booking
 * product line.
 *
 * Bug: booking_product / product carry no unit-of-measure column, yet the
 * detail builder did `arr_get($product, 'unit', 'unit')`, so every line shipped
 * the literal string "unit" as the UOM. AutoCount validates the UOM against the
 * stock item's registered units + multi-packs, finds nothing called "unit", and
 * rejects the whole document with:
 *     "unit or multi pack 'unit' not exists"
 *
 * Contract locked here:
 *   - No usable unit on the product  -> send null, so AutoCount falls back to
 *     the stock item's own base UOM (works regardless of each item's UOM code).
 *   - A real unit IS present          -> send it through verbatim.
 *
 * Covers both autocount_create() and autocount_update(); they build details
 * identically. The detail builder calls the global autocount_request() at the
 * end, which we stub to capture the body instead of hitting the live API.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

// --- minimal CI shims the library leans on --------------------------------
if (!function_exists('arr_get')) {
    function arr_get($array, $key, $default = '')
    {
        return isset($array[$key]) ? $array[$key] : $default;
    }
}
if (!function_exists('log_message')) {
    function log_message($level, $message) { /* no-op for tests */ }
}

// Capture whatever the detail builder hands to AutoCount.
$GLOBALS['__ac_last_body'] = null;
if (!function_exists('autocount_request')) {
    function autocount_request($method, $endpoint, $body = array(), $query = array())
    {
        $GLOBALS['__ac_last_body'] = $body;
        return array('status' => 200, 'error' => null, 'body' => null);
    }
}

require_once __DIR__ . '/../../application/libraries/BookingSync.php';

// Subclass so we can instantiate without a real CI superobject.
class StubBookingSync extends BookingSync
{
    public function __construct() { /* skip get_instance() */ }
}

function build_booking($products)
{
    return array(
        'BookingNumber' => 'BK-1001',
        'InsertDate'    => '2026-06-15 10:00:00',
        'Customer'      => 'Acme Sdn Bhd',
        'booking_product' => $products,
    );
}

$sync = new StubBookingSync();

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got "      . var_export($actual, true) . "\n";
        exit(1);
    }
}

// 1. No unit key -> null (AutoCount uses the item's base UOM). This is the
//    regression: the old code emitted the literal string 'unit' here.
$sync->autocount_create(build_booking(array(
    array('product_ProductCode' => 'P001', 'product_Name' => 'Tour A', 'product_Quantity' => 2),
)));
$details = $GLOBALS['__ac_last_body']['details'];
assert_eq('create: missing unit -> null', null, $details[0]['unit']);

// 2. A real unit present -> passed through untouched.
$sync->autocount_create(build_booking(array(
    array('product_ProductCode' => 'P002', 'product_Name' => 'Tour B', 'product_Quantity' => 1, 'unit' => 'SET'),
)));
$details = $GLOBALS['__ac_last_body']['details'];
assert_eq('create: explicit unit kept', 'SET', $details[0]['unit']);

// 3. update() builds details the same way.
$sync->autocount_update(build_booking(array(
    array('product_ProductCode' => 'P003', 'product_Name' => 'Tour C', 'product_Quantity' => 3),
)));
$details = $GLOBALS['__ac_last_body']['details'];
assert_eq('update: missing unit -> null', null, $details[0]['unit']);

$sync->autocount_update(build_booking(array(
    array('product_ProductCode' => 'P004', 'product_Name' => 'Tour D', 'product_Quantity' => 1, 'unit' => 'PCS'),
)));
$details = $GLOBALS['__ac_last_body']['details'];
assert_eq('update: explicit unit kept', 'PCS', $details[0]['unit']);

echo "\nAll assertions passed.\n";
