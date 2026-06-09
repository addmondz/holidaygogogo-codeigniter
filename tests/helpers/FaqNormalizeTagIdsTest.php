<?php
/**
 * Run with: php tests/helpers/FaqNormalizeTagIdsTest.php
 *
 * Locks the contract for Faq_Model::Normalize_Ids(), the pure helper that
 * sanitises the posted Tags[] payload before it is written to faq_tag_map.
 *
 * Contract:
 *   - non-array input (null, '', scalar) -> empty array
 *   - values are cast to int and only positive ints are kept
 *   - duplicates are collapsed, original order of first occurrence preserved
 *   - keys are reindexed 0..n (so insert_batch gets a clean list)
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

// Minimal stand-in so the model file can be included without CodeIgniter.
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

check('null -> []',            array(), Faq_Model::Normalize_Ids(null));
check('empty string -> []',    array(), Faq_Model::Normalize_Ids(''));
check('scalar -> []',          array(), Faq_Model::Normalize_Ids(5));
check('empty array -> []',     array(), Faq_Model::Normalize_Ids(array()));

check('clean ints',            array(1, 2, 3),  Faq_Model::Normalize_Ids(array(1, 2, 3)));
check('numeric strings cast',  array(4, 5),     Faq_Model::Normalize_Ids(array('4', '5')));
check('dedupe keeps order',    array(2, 7, 9),  Faq_Model::Normalize_Ids(array('2', 2, '7', 9, 7)));
check('drops 0 / negative',    array(8),        Faq_Model::Normalize_Ids(array(0, '-3', 8, 'abc')));
check('reindexed list',        array(10, 20),   Faq_Model::Normalize_Ids(array(3 => 10, 9 => 20)));

if ($failures === 0) {
    echo "\nAll FaqNormalizeTagIds assertions passed.\n";
    exit(0);
}
echo "\n{$failures} assertion(s) failed.\n";
exit(1);
