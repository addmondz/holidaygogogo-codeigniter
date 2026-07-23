<?php
/**
 * Run with: php tests/helpers/CustomerImportParseTest.php
 *
 * Locks the contract for the pure halves of the bulk-create-via-Excel feature:
 *   - Customer_Model::Parse_Import_Rows() flattens raw sheet rows (columns
 *     CUSTOMER CODE | NAME | PHONE | CHAT LANGUAGE | IC | TIN | EMAIL | ADDRESS)
 *     into normalized customer entries, applying the same casing rules as the
 *     single-create form (name/IC/TIN uppercased; email + address kept as
 *     typed), dropping the header and blank rows, and flagging name-less rows.
 *   - Customer_Model::Prune_Import_Backups() keeps only the newest N uploads.
 *
 * Runs without a DB. CI_Model is stubbed so the model file can be required.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}
if (!class_exists('CI_Model')) {
    class CI_Model {}
}
require_once __DIR__ . '/../../application/models/Customer_Model.php';

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

$HEADER = array('CUSTOMER CODE', 'NAME', 'PHONE NUMBER', 'CHAT LANGUAGE', 'IC / PASSPORT NO', 'TIN', 'EMAIL', 'BILLING ADDRESS');

// ---- A full row: casing rules applied, header dropped ----------------------
$rows = array(
    $HEADER,
    array('', 'ali bin abu', '0123456789', 'en', 'a1234567b', 'tin-99', 'Ali@Mail.com', 'No 1, Jalan Besar'),
);
$parsed = Customer_Model::Parse_Import_Rows($rows);
check('one data row parsed (header dropped)', 1, count($parsed));
$e = $parsed[0];
check('line is the sheet row (2)', 2, $e['line']);
check('no error on a named row', null, $e['error']);
check('name uppercased', 'ALI BIN ABU', $e['name']);
check('phone kept', '0123456789', $e['phone_number']);
check('language uppercased', 'EN', $e['ChatLanguage']);
check('IC uppercased', 'A1234567B', $e['ic_passport_no']);
check('TIN uppercased', 'TIN-99', $e['tin_no']);
check('email kept as typed (case-sensitive)', 'Ali@Mail.com', $e['PrimaryEmail']);
check('address kept as typed', 'No 1, Jalan Besar', $e['Address']);
check('no code supplied -> null', null, $e['CustomerCode']);

// ---- Supplied code is uppercased and carried through -----------------------
$parsed = Customer_Model::Parse_Import_Rows(array(array('303-t126', 'siti', '', '', '', '', '', '')));
check('supplied code uppercased', '303-T126', $parsed[0]['CustomerCode']);

// ---- Unknown language is dropped, not a failure ----------------------------
$parsed = Customer_Model::Parse_Import_Rows(array(array('', 'bob', '', 'FR', '', '', '', '')));
check('unknown language -> null', null, $parsed[0]['ChatLanguage']);
check('unknown language is not an error', null, $parsed[0]['error']);

// ---- Missing name kept with error, at the right line -----------------------
$rows = array(
    $HEADER,
    array('', '', '0111', '', '', '', '', ''), // no name -> error
    array('', ' CHONG ', '', '', '', '', '', ''), // trailing/leading spaces trimmed
);
$parsed = Customer_Model::Parse_Import_Rows($rows);
check('both non-blank rows kept', 2, count($parsed));
check('name-less row flagged', 'Missing name', $parsed[0]['error']);
check('name-less row keeps its line (2)', 2, $parsed[0]['line']);
check('name trimmed then uppercased', 'CHONG', $parsed[1]['name']);

// ---- Fully-blank rows dropped ----------------------------------------------
$rows = array(
    array('', 'AMIR', '', '', '', '', '', ''),
    array('', '', '', '', '', '', '', ''), // blank -> dropped
    array(null, null, null, null, null, null, null, null), // blank -> dropped
);
$parsed = Customer_Model::Parse_Import_Rows($rows);
check('blank rows dropped, one kept', 1, count($parsed));

// ---- Prune keeps the newest 3 by filename timestamp ------------------------
$files = array(
    'customer_import_100.xlsx',
    'customer_import_400.xlsx',
    'customer_import_200.xlsx',
    'customer_import_300.xlsx',
    'customer_import_500.xlsx',
);
$prune = Customer_Model::Prune_Import_Backups($files, 3);
check('prune returns the 2 oldest, oldest first', array('customer_import_100.xlsx', 'customer_import_200.xlsx'), $prune);
check('nothing pruned when under the keep count', array(), Customer_Model::Prune_Import_Backups(array('customer_import_1.xlsx'), 3));

echo $failures === 0 ? "\nALL PASS\n" : "\n{$failures} FAILURE(S)\n";
exit($failures === 0 ? 0 : 1);
