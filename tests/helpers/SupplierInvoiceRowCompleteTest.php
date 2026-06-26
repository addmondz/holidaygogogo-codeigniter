<?php
/**
 * Run with: php tests/helpers/SupplierInvoiceRowCompleteTest.php
 *
 * Guards the rule that a supplier-invoice row is only persistable when it has
 * BOTH a real SupplierID (> 0) and a non-empty InvoiceNumber — the two NOT NULL
 * columns on booking_supplier_invoice.
 *
 * Bug shape it guards against: a brand-new invoice row that only had a file
 * attached (no supplier picked / no invoice number — e.g. on a parked draft
 * where those inputs are locked) reaching Create() and aborting the entire
 * booking save with a NOT NULL violation ("Booking Record … Could Not Be
 * Updated"). Such rows must be reported incomplete so the model can skip them.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}
if (!defined('FCPATH')) {
    define('FCPATH', __DIR__ . '/');
}
require_once __DIR__ . '/../../application/helpers/supplier_invoice_helper.php';

$failures = 0;
function check($label, $cond) {
    global $failures;
    if ($cond) {
        echo "PASS  {$label}\n";
    } else {
        $failures++;
        echo "FAIL  {$label}\n";
    }
}

// --- complete rows (array + object shapes) ---
check('array: supplier + number is complete',
    supplier_invoice_row_is_complete(['SupplierID' => 7, 'InvoiceNumber' => 'INV-001']) === true);
check('object: supplier + number is complete',
    supplier_invoice_row_is_complete((object) ['SupplierID' => 7, 'InvoiceNumber' => 'INV-001']) === true);
check('numeric-string supplier id counts',
    supplier_invoice_row_is_complete(['SupplierID' => '12', 'InvoiceNumber' => 'A']) === true);
check('extra fields are ignored',
    supplier_invoice_row_is_complete([
        'SupplierID' => 3, 'InvoiceNumber' => 'X', 'InvoiceAmount' => 0, 'InvoiceFilePath' => 'a/b.pdf'
    ]) === true);

// --- the exact bug: a file-only row with no supplier / no number ---
check('file attached but no supplier or number -> incomplete',
    supplier_invoice_row_is_complete([
        'SupplierID' => null, 'InvoiceNumber' => '', 'InvoiceFilePath' => 'assets/upload/supplier_invoice/x.pdf'
    ]) === false);

// --- missing / empty SupplierID ---
check('null supplier -> incomplete',
    supplier_invoice_row_is_complete(['SupplierID' => null, 'InvoiceNumber' => 'INV-1']) === false);
check('zero supplier -> incomplete',
    supplier_invoice_row_is_complete(['SupplierID' => 0, 'InvoiceNumber' => 'INV-1']) === false);
check('empty-string supplier -> incomplete',
    supplier_invoice_row_is_complete(['SupplierID' => '', 'InvoiceNumber' => 'INV-1']) === false);
check('missing supplier key -> incomplete',
    supplier_invoice_row_is_complete(['InvoiceNumber' => 'INV-1']) === false);

// --- missing / blank InvoiceNumber ---
check('empty number -> incomplete',
    supplier_invoice_row_is_complete(['SupplierID' => 5, 'InvoiceNumber' => '']) === false);
check('whitespace-only number -> incomplete',
    supplier_invoice_row_is_complete(['SupplierID' => 5, 'InvoiceNumber' => '   ']) === false);
check('missing number key -> incomplete',
    supplier_invoice_row_is_complete(['SupplierID' => 5]) === false);

// --- non-row inputs ---
check('null input -> incomplete', supplier_invoice_row_is_complete(null) === false);
check('string input -> incomplete', supplier_invoice_row_is_complete('nope') === false);

echo "\n" . ($failures === 0 ? "OK" : "{$failures} FAILURE(S)") . "\n";
exit($failures === 0 ? 0 : 1);
