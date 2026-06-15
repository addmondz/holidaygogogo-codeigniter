<?php
/**
 * Run with: php tests/helpers/CustomerSyncTinIcMappingTest.php
 *
 * Locks the AutoCount debtor payload mapping for the two identity fields that
 * are now captured in customer MASTER DATA and must flow to AutoCount:
 *
 *   customer.tin_no         -> debtor "taxRegisterNo"  (Tax Identification No)
 *   customer.ic_passport_no -> debtor "registerNo"     (Registration / IC No)
 *
 * Both the create and update payloads are asserted. The test exercises the
 * REAL CustomerSync library; autocount_request() is stubbed to capture the
 * outgoing payload instead of hitting AutoCount.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

// --- Stubs for CustomerSync dependencies -------------------------------------

// arr_get mirrors application/helpers/utils_helper.php
if (!function_exists('arr_get')) {
    function arr_get($array, $key, $default = '')
    {
        return isset($array[$key]) ? $array[$key] : $default;
    }
}

// CodeIgniter super object (constructor only stores it; unused by these methods)
if (!function_exists('get_instance')) {
    function &get_instance()
    {
        static $ci;
        if ($ci === null) {
            $ci = new stdClass();
        }
        return $ci;
    }
}

// Capture the payload that would be sent to AutoCount.
$GLOBALS['__captured'] = null;
if (!function_exists('autocount_request')) {
    function autocount_request($method, $endpoint_key, $payload = [], $queryParams = [])
    {
        $GLOBALS['__captured'] = [
            'method'   => $method,
            'endpoint' => $endpoint_key,
            'payload'  => $payload,
        ];
        return ['status' => 200, 'data' => []];
    }
}
if (!function_exists('log_message')) {
    function log_message($level, $msg) {}
}

require __DIR__ . '/../../application/libraries/CustomerSync.php';

// --- Assertions --------------------------------------------------------------

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

$sync   = new CustomerSync();
$config = ['customer_creditTerm' => 'C.O.D'];

$data = [
    'name'            => 'Alice Tan',
    'phone_number'    => '0123456789',
    'CustomerCode'    => '300-A0001',
    'tin_no'          => 'IG12345678901',
    'ic_passport_no'  => '900101-14-5678',
];

// --- create ---
$sync->autocount_create($data, $config);
$create = $GLOBALS['__captured']['payload'];
assert_eq('create: TIN -> taxRegisterNo', 'IG12345678901', $create['taxRegisterNo']);
assert_eq('create: IC  -> registerNo',    '900101-14-5678', $create['registerNo']);

// --- update ---
$GLOBALS['__captured'] = null;
$sync->autocount_update($data, $config);
$update = $GLOBALS['__captured']['payload'];
assert_eq('update: TIN -> taxRegisterNo', 'IG12345678901', $update['taxRegisterNo']);
assert_eq('update: IC  -> registerNo',    '900101-14-5678', $update['registerNo']);

echo "\nAll assertions passed.\n";
