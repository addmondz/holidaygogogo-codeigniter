<?php
/**
 * Run with: php tests/helpers/FaqParseIdCsvTest.php
 *
 * Locks the contract for Faq_Model::Parse_Id_Csv(), the pure helper that turns
 * the comma-separated tag/destination filter values posted from the FAQ listing
 * (e.g. "3,7,7,abc") into a clean list of positive ints for an IN(...) clause.
 *
 * Contract:
 *   - non-string / empty / whitespace-only input -> empty array
 *   - splits on comma, casts to int, keeps only positive ints
 *   - duplicates collapsed, first-occurrence order preserved, keys reindexed
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

check('null -> []',             array(),        Faq_Model::Parse_Id_Csv(null));
check('empty string -> []',     array(),        Faq_Model::Parse_Id_Csv(''));
check('whitespace -> []',       array(),        Faq_Model::Parse_Id_Csv('   '));
check('array -> []',            array(),        Faq_Model::Parse_Id_Csv(array(1, 2)));

check('single id',              array(5),       Faq_Model::Parse_Id_Csv('5'));
check('clean csv',              array(3, 7, 9), Faq_Model::Parse_Id_Csv('3,7,9'));
check('dedupe keeps order',     array(2, 7),    Faq_Model::Parse_Id_Csv('2,7,7,2'));
check('drops 0 / neg / junk',   array(8),       Faq_Model::Parse_Id_Csv('0,-3,abc,8'));
check('trims surrounding ws',   array(4, 6),    Faq_Model::Parse_Id_Csv(' 4 , 6 '));

if ($failures === 0) {
    echo "\nAll FaqParseIdCsv assertions passed.\n";
    exit(0);
}
echo "\n{$failures} assertion(s) failed.\n";
exit(1);
