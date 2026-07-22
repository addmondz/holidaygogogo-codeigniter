<?php
/**
 * Run with: php tests/helpers/SupplierInvoiceRowHasDataTest.php
 *
 * Guards the rule that a supplier-invoice row is persistable when it carries ANY
 * content — a supplier, an invoice number, OR an attached file. Supplier +
 * invoice number are now nullable, so a FILE-ONLY row (no supplier / no number)
 * is valid and must be kept. Only a completely blank row (a stray "Add Invoice"
 * click) is dropped by the model.
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

// --- rows with data (array + object shapes) ---
check('array: supplier + number has data',
    supplier_invoice_row_has_data(['SupplierID' => 7, 'InvoiceNumber' => 'INV-001']) === true);
check('object: supplier + number has data',
    supplier_invoice_row_has_data((object) ['SupplierID' => 7, 'InvoiceNumber' => 'INV-001']) === true);
check('numeric-string supplier id counts',
    supplier_invoice_row_has_data(['SupplierID' => '12', 'InvoiceNumber' => 'A']) === true);

// --- the new rule: a FILE-ONLY row has data and must be kept ---
check('file attached, no supplier or number -> has data',
    supplier_invoice_row_has_data([
        'SupplierID' => null, 'InvoiceNumber' => '', 'InvoiceFilePath' => 'assets/upload/supplier_invoice/x.pdf'
    ]) === true);
check('object file-only row -> has data',
    supplier_invoice_row_has_data((object) [
        'SupplierID' => 0, 'InvoiceNumber' => '', 'InvoiceFilePath' => 'a/b.pdf'
    ]) === true);

// --- supplier only / number only still count ---
check('supplier only -> has data',
    supplier_invoice_row_has_data(['SupplierID' => 5, 'InvoiceNumber' => '']) === true);
check('number only -> has data',
    supplier_invoice_row_has_data(['SupplierID' => null, 'InvoiceNumber' => 'INV-1']) === true);

// --- genuinely blank rows are dropped ---
check('empty supplier + empty number + no file -> no data',
    supplier_invoice_row_has_data(['SupplierID' => 0, 'InvoiceNumber' => '', 'InvoiceFilePath' => '']) === false);
check('whitespace-only number + no file -> no data',
    supplier_invoice_row_has_data(['SupplierID' => null, 'InvoiceNumber' => '   ']) === false);
check('empty array (no keys) -> no data',
    supplier_invoice_row_has_data([]) === false);
check('amount/remark only (no id fields, no file) -> no data',
    supplier_invoice_row_has_data(['InvoiceAmount' => 100, 'Remark' => 'x']) === false);

// --- non-row inputs ---
check('null input -> no data', supplier_invoice_row_has_data(null) === false);
check('string input -> no data', supplier_invoice_row_has_data('nope') === false);

echo "\n" . ($failures === 0 ? "OK" : "{$failures} FAILURE(S)") . "\n";
exit($failures === 0 ? 0 : 1);
