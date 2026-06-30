<?php
/**
 * Run with: php tests/helpers/FaqPruneBackupsTest.php
 *
 * Locks the contract for Faq_Model::Prune_Backups() - the pure decision of
 * which uploaded import files to delete so only the newest N are kept as
 * backups. Recency is read from the timestamp embedded in the filename
 * (faq_import_<unix>.xlsx), so the helper needs no filesystem and is unit
 * testable. Returns the filenames to DELETE (the ones beyond the newest N),
 * oldest first.
 *
 * Runs without a DB. CI_Model is stubbed so the model file can be required.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}
if (!class_exists('CI_Model')) {
    class CI_Model {}
}
require_once __DIR__ . '/../../application/models/Faq_Model.php';

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

// ---- Fewer than the keep limit -> nothing to delete ------------------------
check('keep 3, have 2 -> delete none', array(), Faq_Model::Prune_Backups(array(
    'faq_import_100.xlsx', 'faq_import_200.xlsx',
), 3));

// ---- Exactly the keep limit -> nothing to delete ---------------------------
check('keep 3, have 3 -> delete none', array(), Faq_Model::Prune_Backups(array(
    'faq_import_100.xlsx', 'faq_import_200.xlsx', 'faq_import_300.xlsx',
), 3));

// ---- More than the limit -> delete the oldest, newest-3 survive ------------
// Pass them out of order to prove it sorts by the embedded timestamp.
$files = array(
    'faq_import_500.xlsx',
    'faq_import_100.xlsx',
    'faq_import_400.xlsx',
    'faq_import_200.xlsx',
    'faq_import_300.xlsx',
);
check('keep 3 of 5 -> delete two oldest (oldest first)', array(
    'faq_import_100.xlsx', 'faq_import_200.xlsx',
), Faq_Model::Prune_Backups($files, 3));

// ---- Default keep is 3 -----------------------------------------------------
check('default keep = 3', array('faq_import_100.xlsx'), Faq_Model::Prune_Backups(array(
    'faq_import_100.xlsx', 'faq_import_200.xlsx', 'faq_import_300.xlsx', 'faq_import_400.xlsx',
)));

// ---- Filenames without a parseable timestamp sort oldest (ts 0) ------------
check('unparseable names treated as oldest', array('stray.xlsx', 'faq_import_100.xlsx'), Faq_Model::Prune_Backups(array(
    'faq_import_300.xlsx', 'stray.xlsx', 'faq_import_100.xlsx', 'faq_import_400.xlsx',
), 2));

// ---- Defensive: non-array -> empty list ------------------------------------
check('null -> []', array(), Faq_Model::Prune_Backups(null, 3));

if ($failures === 0) {
    echo "\nAll FaqPruneBackups assertions passed.\n";
    exit(0);
}
echo "\n{$failures} assertion(s) failed.\n";
exit(1);
