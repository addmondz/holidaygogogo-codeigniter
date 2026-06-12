<?php
/**
 * Run with: php tests/helpers/FaqExportRowsTest.php
 *
 * Locks the contract for Faq_Model::Export_Rows() - the pure flattening that
 * both the Excel and PDF "download all FAQs" exports build on, so the two
 * formats can never drift. It turns a list of FAQ rows (each carrying Title,
 * the Description JSON of sub-Q&As, and a "||"-joined Destinations string) into
 * a flat list of export rows: one row per sub-Q&A, plus a single title-only row
 * for a FAQ that has no sub-Q&As yet. Per-item tag ids resolve to names through
 * the passed FAQTagID => Name map.
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

// Build a FAQ row the way Read_Faqs() hands it to the export.
function faq($title, $description, $destinations = '') {
    return (object) array('Title' => $title, 'Description' => $description, 'Destinations' => $destinations);
}

$tag_names = array(3 => 'Payment', 7 => 'Visa');

// ---- One row per sub-Q&A, tags + destinations resolved --------------------
$desc = Faq_Model::Encode_Items(array(
    array('q' => 'How to pay?', 'a' => 'Bank in.', 'tags' => array(3), 'cd' => '2026-01-02 09:00:00', 'ud' => '2026-03-04 10:00:00'),
    array('q' => 'Need visa?',  'a' => 'Yes.',     'tags' => array(7, 3)),
));
$rows = Faq_Model::Export_Rows(array(faq('Thailand Trip', $desc, 'Bangkok||Phuket')), $tag_names);
check('flatten: row count', 2, count($rows));
check('flatten: first row', array(
    'title' => 'Thailand Trip', 'destinations' => 'Bangkok, Phuket',
    'question' => 'How to pay?', 'answer' => 'Bank in.', 'tags' => 'Payment',
    'created' => '2026-01-02 09:00:00', 'updated' => '2026-03-04 10:00:00',
), $rows[0]);
check('flatten: second row tags joined', 'Visa, Payment', $rows[1]['tags']);
check('flatten: second row blank dates', '', $rows[1]['created']);

// ---- A FAQ with no sub-Q&As still emits one title-only row ----------------
$rows = Faq_Model::Export_Rows(array(faq('Empty FAQ', '', 'Bali')), $tag_names);
check('empty faq: one row', 1, count($rows));
check('empty faq: title-only row', array(
    'title' => 'Empty FAQ', 'destinations' => 'Bali',
    'question' => '', 'answer' => '', 'tags' => '', 'created' => '', 'updated' => '',
), $rows[0]);

// ---- Unknown tag ids are dropped, not rendered as blanks ------------------
$desc = Faq_Model::Encode_Items(array(array('q' => 'Q', 'a' => 'A', 'tags' => array(3, 999))));
$rows = Faq_Model::Export_Rows(array(faq('T', $desc)), $tag_names);
check('unknown tag dropped', 'Payment', $rows[0]['tags']);
check('no destinations -> empty', '', $rows[0]['destinations']);

// ---- Multiple FAQs flatten in order ---------------------------------------
$rows = Faq_Model::Export_Rows(array(
    faq('A', Faq_Model::Encode_Items(array(array('q' => 'a1', 'a' => 'x')))),
    faq('B', Faq_Model::Encode_Items(array(array('q' => 'b1', 'a' => 'y'), array('q' => 'b2', 'a' => 'z')))),
), $tag_names);
check('multi: total rows', 3, count($rows));
check('multi: titles in order', array('A', 'B', 'B'), array_map(function ($r) { return $r['title']; }, $rows));

// ---- Defensive: non-array input -> empty list -----------------------------
check('non-array faqs -> []', array(), Faq_Model::Export_Rows(null, $tag_names));
check('non-array tag map ok', 'Q', Faq_Model::Export_Rows(array(faq('T', Faq_Model::Encode_Items(array(array('q' => 'Q', 'a' => 'A'))))), null)[0]['question']);

if ($failures === 0) {
    echo "\nAll FaqExportRows assertions passed.\n";
    exit(0);
}
echo "\n{$failures} assertion(s) failed.\n";
exit(1);
