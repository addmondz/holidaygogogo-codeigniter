<?php
/**
 * Run with: php tests/helpers/EinvoiceCustomerMatchTest.php
 *
 * Locks the pure e-invoice pax->new-customer decision (no DB / no HTTP).
 * Rule: new customer when name OR phone differs from the booking customer
 * (same customer only when BOTH match); name is case/whitespace-insensitive;
 * phone is last-9-digit keyed.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require_once __DIR__ . '/../../application/helpers/customer_dedup_helper.php';
require_once __DIR__ . '/../../application/helpers/einvoice_customer_helper.php';

$assertions = [];

/* 1) NAME KEY ----------------------------------------------------- */
$assertions['name key: trims + uppers']       = (einvoice_name_key('  ali  ') === 'ALI');
$assertions['name key: collapses whitespace']  = (einvoice_name_key("Ali   bin\tAbu") === 'ALI BIN ABU');
$assertions['name key: blank -> empty']        = (einvoice_name_key('   ') === '');

/* 2) BOTH SAME -> NOT new ---------------------------------------- */
$assertions['same name+phone -> false'] =
    (einvoice_pax_needs_new_customer('Ali', '+60 122983045', 'Ali', '0122983045') === false);
$assertions['same name (case/space) + same phone (format) -> false'] =
    (einvoice_pax_needs_new_customer('  ALI bin  Abu ', '122983045', 'Ali Bin Abu', '+60 12-2983045') === false);

/* 3) ONLY ONE DIFFERS -> new ------------------------------------- */
$assertions['diff name, SAME phone -> true'] =
    (einvoice_pax_needs_new_customer('Siti', '0122983045', 'Ali', '+60 122983045') === true);
$assertions['SAME name, diff phone -> true'] =
    (einvoice_pax_needs_new_customer('Ali', '0139998877', 'Ali', '0122983045') === true);

/* 4) BOTH DIFFER -> new ------------------------------------------ */
$assertions['diff name AND diff phone -> true'] =
    (einvoice_pax_needs_new_customer('Siti Aminah', '0139998877', 'Ali', '0122983045') === true);
$assertions['booking has no phone + diff name -> true'] =
    (einvoice_pax_needs_new_customer('Siti', '0139998877', 'Ali', '') === true);

/* 5) PAX MUST BE USABLE ------------------------------------------ */
$assertions['blank pax name -> false']  =
    (einvoice_pax_needs_new_customer('', '0139998877', 'Ali', '0122983045') === false);
$assertions['blank pax phone -> false'] =
    (einvoice_pax_needs_new_customer('Siti', '', 'Ali', '0122983045') === false);
$assertions['digitless pax phone -> false'] =
    (einvoice_pax_needs_new_customer('Siti', 'N/A', 'Ali', '0122983045') === false);

/* 6) SOURCE CONTRACT --------------------------------------------- */
$model = @file_get_contents(__DIR__ . '/../../application/models/Invoice_Split_Model.php');
$assertions['model: creates customers for new pax'] =
    (strpos((string) $model, 'Create_Customers_For_New_Pax') !== false);
$ctrl = @file_get_contents(__DIR__ . '/../../application/controllers/Customer_Portal.php');
$assertions['controller: invokes it on submit'] =
    (strpos((string) $ctrl, 'Create_Customers_For_New_Pax') !== false);

/* ----------------------------------------------------------------- */
$fail = 0;
foreach ($assertions as $label => $ok) {
    echo ($ok ? 'PASS  ' : 'FAIL  ') . $label . "\n";
    if (!$ok) {
        $fail++;
    }
}
echo "\n" . (count($assertions) - $fail) . '/' . count($assertions) . " passed\n";
exit($fail === 0 ? 0 : 1);
