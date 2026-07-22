<?php
/**
 * Run with: php tests/helpers/PaymentSyncZeroAmountTest.php
 *
 * Bug (BC-2602-0100 / PV-2602-0139): a "SUPPLIER PAYMENT (FULL)" row with
 * Credit == 0 AND Debit == 0 was still pushed to AutoCount. The detail line
 * carried amount 0, and AutoCount rejected the whole document with:
 *     "Detail line [1] missing value in Amount field."
 * so the row stayed red ("Failed") on every sync run.
 *
 * Contract locked here:
 *   - Zero-amount payment (no foreign_amount, Credit == Debit == 0)
 *       -> autocount_create()/autocount_update() SKIP the API entirely and
 *          return ['skipped' => true], so the caller can mark it resolved.
 *   - Any real amount (Credit, Debit, or foreign_amount)
 *       -> the API IS called as before.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

if (!function_exists('arr_get')) {
    function arr_get($array, $key, $default = '')
    {
        return isset($array[$key]) ? $array[$key] : $default;
    }
}
if (!function_exists('log_message')) {
    function log_message($level, $message) { /* no-op for tests */ }
}

// Track whether the live API was hit, and hand back a normal success shape.
$GLOBALS['__ac_called'] = false;
if (!function_exists('autocount_request')) {
    function autocount_request($method, $endpoint, $body = array(), $query = array())
    {
        $GLOBALS['__ac_called'] = true;
        return array('status' => 201, 'error' => null, 'body' => null, 'docNo' => 'PV-TEST-0001');
    }
}

require_once __DIR__ . '/../../application/libraries/PaymentSync.php';

// Subclass so we can instantiate without a real CI superobject.
class StubPaymentSync extends PaymentSync
{
    public function __construct() { /* skip get_instance() */ }
}

$sync = new StubPaymentSync();

$failures = 0;
function check($label, $cond) {
    global $failures;
    if ($cond) {
        echo "  PASS: {$label}\n";
    } else {
        echo "  FAIL: {$label}\n";
        $failures++;
    }
}

// --- 1. Zero amount (Credit == Debit == 0) is skipped, no API call ---------
foreach (['autocount_create', 'autocount_update'] as $method) {
    $GLOBALS['__ac_called'] = false;
    $res = $sync->$method([
        'Credit' => 0.00,
        'Debit'  => 0.00,
        'BookingNumber' => 'BC-2602-0100',
    ]);
    check("{$method}: zero amount returns skipped=true", !empty($res['skipped']));
    check("{$method}: zero amount does NOT hit AutoCount", $GLOBALS['__ac_called'] === false);
    check("{$method}: skipped result carries no error", $res['error'] === null);
}

// --- 2. Real Debit (PV) still syncs ---------------------------------------
$GLOBALS['__ac_called'] = false;
$res = $sync->autocount_create([
    'Credit'       => 0.00,
    'Debit'        => 3038.39,
    'SupplierCode' => '300-S001',
]);
check('real Debit is NOT skipped', empty($res['skipped']));
check('real Debit hits AutoCount', $GLOBALS['__ac_called'] === true);

// --- 3. Real Credit (OR) still syncs --------------------------------------
$GLOBALS['__ac_called'] = false;
$res = $sync->autocount_update([
    'Credit'       => 2224.00,
    'Debit'        => 0.00,
    'CustomerCode' => '300-C001',
    'AutocountReferenceNumber' => 'OR-2603-0253',
]);
check('real Credit is NOT skipped', empty($res['skipped']));
check('real Credit hits AutoCount', $GLOBALS['__ac_called'] === true);

// --- 4. Zero Credit/Debit but foreign amount present still syncs ----------
$GLOBALS['__ac_called'] = false;
$res = $sync->autocount_create([
    'Credit'         => 0.00,
    'Debit'          => 0.00,
    'foreign_amount' => 1848.00,
    'currency_code'  => 'USD',
    'SupplierCode'   => '300-S001',
]);
check('foreign amount is NOT skipped', empty($res['skipped']));
check('foreign amount hits AutoCount', $GLOBALS['__ac_called'] === true);

echo "\n" . ($failures === 0 ? "ALL TESTS PASSED\n" : "{$failures} TEST(S) FAILED\n");
exit($failures === 0 ? 0 : 1);
