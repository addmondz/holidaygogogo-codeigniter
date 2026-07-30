<?php
/**
 * Run with: php tests/helpers/AgentCommissionFromSupplierTest.php
 *
 * Feature: "AGENT COMMISSION FROM SUPPLIER" is a Payment Out (PV) whose payee is
 * a chosen supplier. Contract locked here:
 *   1. Classification: payment_type_uses_supplier() returns true for it (so the
 *      form shows a Supplier selector and the model stores payment.SupplierID),
 *      and false for the free-text bank types (AGENT COMMISSION, ONE-TIME, ...).
 *   2. Sync: once enriched (dealWith = supplier name, SupplierCode = supplier's
 *      creditor code), PaymentSync builds a PV addressed to that supplier — the
 *      AutoCount document carries the supplier name and the supplier's account.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}
if (!function_exists('arr_get')) {
    function arr_get($array, $key, $default = '') {
        return isset($array[$key]) ? $array[$key] : $default;
    }
}
if (!function_exists('log_message')) {
    function log_message($level, $message) { /* no-op */ }
}

// Capture the payload PaymentSync sends to AutoCount instead of hitting the API.
$GLOBALS['__ac_param'] = null;
if (!function_exists('autocount_request')) {
    function autocount_request($method, $endpoint, $body = array(), $query = array()) {
        $GLOBALS['__ac_param'] = $body;
        return array('status' => 201, 'error' => null, 'body' => null, 'docNo' => 'PV-TEST-0001');
    }
}

require_once __DIR__ . '/../../application/helpers/payment_type_helper.php';
require_once __DIR__ . '/../../application/libraries/PaymentSync.php';

class StubPaymentSync extends PaymentSync {
    public function __construct() { /* skip get_instance() */ }
}

$failures = 0;
function check($label, $cond) {
    global $failures;
    if ($cond) { echo "  PASS: {$label}\n"; }
    else { echo "  FAIL: {$label}\n"; $failures++; }
}

// --- 1. Classification -----------------------------------------------------
check('AGENT COMMISSION FROM SUPPLIER uses a supplier',
    payment_type_uses_supplier('AGENT COMMISSION FROM SUPPLIER') === true);
check('SUPPLIER PAYMENT (FULL) uses a supplier',
    payment_type_uses_supplier('SUPPLIER PAYMENT (FULL)') === true);
check('AGENT COMMISSION (bank type) does NOT use a supplier',
    payment_type_uses_supplier('AGENT COMMISSION') === false);
check('CUSTOMER REFUND does NOT use a supplier',
    payment_type_uses_supplier('CUSTOMER REFUND') === false);
check('ONE-TIME PAYMENT does NOT use a supplier',
    payment_type_uses_supplier('ONE-TIME PAYMENT') === false);

// --- 2. Sync builds a PV addressed to the supplier -------------------------
$sync = new StubPaymentSync();
$GLOBALS['__ac_param'] = null;
$sync->autocount_create(array(
    'Type'         => 'AGENT COMMISSION FROM SUPPLIER',
    'Credit'       => 0.00,
    'Debit'        => 500.00,
    'dealWith'     => 'ABC Tours Sdn Bhd',   // supplier name (set in enrichPayment)
    'SupplierCode' => '400-A123',            // supplier's creditor code
    'Date'         => '2026-07-23',
));
$param = $GLOBALS['__ac_param'];
check('sync was called', is_array($param));
check('document is a PV (payment out)', $param['master']['docType'] === 'PV');
check('deal-with is the supplier name', $param['master']['dealWith'] === 'ABC Tours Sdn Bhd');
check('detail account is the supplier creditor code', $param['details'][0]['accNo'] === '400-A123');
check('detail amount is the debit', (float)$param['details'][0]['amount'] === 500.00);

// --- 3. Same type as Payment IN posts a receipt (OR) to the supplier -------
// When it arrives as money IN (Credit > 0) the commission is received FROM the
// supplier, so it must still book against the supplier's creditor account — an
// OR whose deal-with and detail account are the supplier's, NOT the customer's.
$GLOBALS['__ac_param'] = null;
$sync->autocount_create(array(
    'Type'         => 'AGENT COMMISSION FROM SUPPLIER',
    'Credit'       => 500.00,
    'Debit'        => 0.00,
    'dealWith'     => 'ABC Tours Sdn Bhd',   // supplier name (set in enrichPayment)
    'CustomerCode' => '300-C999',            // must NOT be used for this type
    'SupplierCode' => '400-A123',            // supplier's creditor code
    'Date'         => '2026-07-30',
));
$param = $GLOBALS['__ac_param'];
check('IN: document is an OR (payment in)', $param['master']['docType'] === 'OR');
check('IN: deal-with is the supplier name', $param['master']['dealWith'] === 'ABC Tours Sdn Bhd');
check('IN: detail account is the supplier creditor code (not the customer)', $param['details'][0]['accNo'] === '400-A123');
check('IN: detail amount is the credit', (float)$param['details'][0]['amount'] === 500.00);

echo "\n" . ($failures === 0 ? "ALL TESTS PASSED\n" : "{$failures} TEST(S) FAILED\n");
exit($failures === 0 ? 0 : 1);
