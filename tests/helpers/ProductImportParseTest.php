<?php
/**
 * Run with: php tests/helpers/ProductImportParseTest.php
 *
 * Locks the pure halves of the product bulk-import (upsert-by-code) feature:
 *   - Product_Model::Parse_Import_Rows() flattens raw sheet rows (columns
 *     CATEGORY | SUPPLIER | PRODUCT CODE | NAME | RETAIL PRICE | SUPPLIER PRICE,
 *     matching Product::Download()) into normalized product entries. It resolves
 *     CATEGORY/SUPPLIER names to IDs via the passed-in maps, parses RM-formatted
 *     prices, drops the header + blank rows, and flags rows missing/invalid
 *     Category, Supplier or Name. Product Code is carried through so the caller
 *     can decide update-if-exists vs create.
 *   - Product_Model::Prune_Import_Backups() keeps only the newest N uploads.
 *
 * Runs without a DB. CI_Model is stubbed so the model file can be required.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}
if (!class_exists('CI_Model')) {
    class CI_Model {}
}
require_once __DIR__ . '/../../application/models/Product_Model.php';

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

// Name -> id maps the controller builds from the category/supplier tables.
$CATS = array('HOTEL' => 7, 'FERRY' => 3);
$SUPS = array('SUNRISE TRAVEL' => 11, 'ABC SDN BHD' => 4);

// Column order matches Product_Model::IMPORT_COLUMNS / Product::Download():
// CATEGORY | SUPPLIER | PRODUCT CODE | NAME | RETAIL PRICE | SUPPLIER PRICE
$HEADER = array('CATEGORY', 'SUPPLIER', 'PRODUCT CODE', 'NAME', 'RETAIL PRICE', 'SUPPLIER PRICE');

// ---- A full row: names resolved, prices parsed, header dropped -------------
$rows = array(
    $HEADER,
    array('Hotel', 'Sunrise Travel', 'HOTEL-12', 'Deluxe Sea View', 'RM1,200.50', '900'),
);
$parsed = Product_Model::Parse_Import_Rows($rows, $CATS, $SUPS);
check('one data row parsed (header dropped)', 1, count($parsed));
$e = $parsed[0];
check('line is the sheet row (2)', 2, $e['line']);
check('no error when all mandatory valid', null, $e['error']);
check('category name resolved to id', 7, $e['CategoryID']);
check('supplier name resolved to id', 11, $e['SupplierID']);
check('product code uppercased + kept', 'HOTEL-12', $e['ProductCode']);
check('name kept as typed', 'Deluxe Sea View', $e['Name']);
check('retail price RM+comma stripped', 1200.50, $e['RetailPrice']);
check('supplier price parsed', 900.0, $e['SupplierPrice']);

// ---- Blank product code -> null (caller creates a new product) -------------
$parsed = Product_Model::Parse_Import_Rows(array(array('Ferry', 'ABC Sdn Bhd', '', 'Round Trip', '', '')), $CATS, $SUPS);
check('blank code -> null', null, $parsed[0]['ProductCode']);
check('blank retail price -> 0.0', 0.0, $parsed[0]['RetailPrice']);
check('blank supplier price -> 0.0', 0.0, $parsed[0]['SupplierPrice']);
check('ferry resolved', 3, $parsed[0]['CategoryID']);

// ---- Case-insensitive name matching ----------------------------------------
$parsed = Product_Model::Parse_Import_Rows(array(array('  hOtEl ', ' sunrise travel ', 'HOTEL-9', 'X', '10', '5')), $CATS, $SUPS);
check('category matched case-insensitively + trimmed', 7, $parsed[0]['CategoryID']);
check('supplier matched case-insensitively + trimmed', 11, $parsed[0]['SupplierID']);

// ---- Unknown category / supplier flagged -----------------------------------
$parsed = Product_Model::Parse_Import_Rows(array(array('Villa', 'Nobody Ltd', '', 'Y', '', '')), $CATS, $SUPS);
check('unknown category -> null id', null, $parsed[0]['CategoryID']);
check('unknown supplier -> null id', null, $parsed[0]['SupplierID']);
check('unknown category + supplier flagged',
    'Missing/invalid: Category (Villa), Supplier (Nobody Ltd)', $parsed[0]['error']);

// ---- Missing mandatory fields listed ---------------------------------------
$parsed = Product_Model::Parse_Import_Rows(array(array('', 'Sunrise Travel', '', '', '10', '5')), $CATS, $SUPS);
check('missing category + name flagged',
    'Missing/invalid: Category, Name', $parsed[0]['error']);

// ---- Header + fully-blank rows dropped --------------------------------------
$rows = array(
    $HEADER,
    array('', '', '', '', '', ''),
    array('Hotel', 'ABC Sdn Bhd', '', 'Good', '1', '1'),
    array(null, null, null, null, null, null),
);
$parsed = Product_Model::Parse_Import_Rows($rows, $CATS, $SUPS);
check('only the one real data row kept', 1, count($parsed));
check('kept row is at sheet line 3', 3, $parsed[0]['line']);

// ---- Prune_Import_Backups keeps newest N -----------------------------------
$files = array(
    'product_import_100.xlsx',
    'product_import_300.xlsx',
    'product_import_200.xlsx',
    'product_import_400.xlsx',
);
$prune = Product_Model::Prune_Import_Backups($files, 2);
sort($prune);
check('prunes the two oldest, keeps newest two',
    array('product_import_100.xlsx', 'product_import_200.xlsx'), $prune);

echo "\n" . ($failures === 0 ? "ALL PASSED\n" : "{$failures} FAILURE(S)\n");
exit($failures === 0 ? 0 : 1);
