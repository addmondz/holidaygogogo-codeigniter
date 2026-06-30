<?php
/**
 * Run with: php tests/helpers/FaqParseImportTest.php
 *
 * Locks the contract for Faq_Model::Parse_Import() - the pure flattening that
 * the wipe-and-rebuild Excel import builds on. It turns the raw sheet rows
 * (one per sub-Q&A, columns FAQ | DESTINATION | QUESTION | ANSWER | TAGS) back
 * into the grouped FAQ structure the importer rebuilds the library from:
 *   array('title' => .., 'destinations' => array(names), 'items' => array(
 *       array('q' => .., 'a' => .., 'tags' => array(names)) ))
 * It is the inverse of Export_Rows(), so an exported template round-trips.
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

// Header row (as PhpSpreadsheet's toArray() hands it) is detected and dropped.
$HEADER = array('FAQ', 'DESTINATION', 'QUESTION', 'ANSWER', 'TAGS');

// ---- Round-trips a typical export: title repeated on every sub-Q&A row -----
$rows = array(
    $HEADER,
    array('Thailand Trip', 'Bangkok, Phuket', 'How to pay?', 'Bank in.', 'Payment'),
    array('Thailand Trip', 'Bangkok, Phuket', 'Need visa?',  'Yes.',     'Visa, Payment'),
    array('Refunds',       'Bali',            'Can I refund?', 'No.',     ''),
);
$out = Faq_Model::Parse_Import($rows);
check('two FAQs grouped by title', 2, count($out));
check('first title', 'Thailand Trip', $out[0]['title']);
check('first destinations deduped + ordered', array('Bangkok', 'Phuket'), $out[0]['destinations']);
check('first FAQ has two items', 2, count($out[0]['items']));
check('item 1', array('q' => 'How to pay?', 'a' => 'Bank in.', 'tags' => array('Payment')), $out[0]['items'][0]);
check('item 2 tags split + trimmed', array('Visa', 'Payment'), $out[0]['items'][1]['tags']);
check('second FAQ title', 'Refunds', $out[1]['title']);
check('second FAQ destinations', array('Bali'), $out[1]['destinations']);
check('item with no tags -> empty tag list', array(), $out[1]['items'][0]['tags']);

// ---- Carry-forward: blank title cell inherits the FAQ above ----------------
$rows = array(
    array('Visas', 'Japan', 'Q1', 'A1', 'Visa'),
    array('',      '',      'Q2', 'A2', ''),
);
$out = Faq_Model::Parse_Import($rows);
check('carry-forward keeps one FAQ', 1, count($out));
check('carry-forward collects both items', 2, count($out[0]['items']));

// ---- Destinations union across the group's rows, deduped -------------------
$rows = array(
    array('Multi', 'Bangkok',          'Q1', 'A1', ''),
    array('Multi', 'Phuket, Bangkok',  'Q2', 'A2', ''),
);
$out = Faq_Model::Parse_Import($rows);
check('destinations union deduped', array('Bangkok', 'Phuket'), $out[0]['destinations']);

// ---- A title-only row creates an FAQ with no items -------------------------
$rows = array(array('Placeholder', '', '', '', ''));
$out = Faq_Model::Parse_Import($rows);
check('title-only FAQ exists', 1, count($out));
check('title-only FAQ has no items', array(), $out[0]['items']);

// ---- Fully blank rows are skipped, whitespace trimmed ----------------------
$rows = array(
    array('  Spaced  ', '  Bali ', '  Q  ', '  A  ', '  Visa , Payment '),
    array('', '', '', '', ''),
    array(null, null, null, null, null),
);
$out = Faq_Model::Parse_Import($rows);
check('blank rows dropped, one FAQ', 1, count($out));
check('title trimmed', 'Spaced', $out[0]['title']);
check('destination trimmed', array('Bali'), $out[0]['destinations']);
check('q/a trimmed', array('q' => 'Q', 'a' => 'A', 'tags' => array('Visa', 'Payment')), $out[0]['items'][0]);

// ---- Orphan row (q/a but no title and no FAQ above) is skipped -------------
$rows = array(array('', '', 'Orphan Q', 'Orphan A', ''));
check('orphan row -> no FAQ', array(), Faq_Model::Parse_Import($rows));

// ---- Defensive: non-array / empty input -> empty list ----------------------
check('null -> []', array(), Faq_Model::Parse_Import(null));
check('empty -> []', array(), Faq_Model::Parse_Import(array()));
check('header-only -> []', array(), Faq_Model::Parse_Import(array($HEADER)));

if ($failures === 0) {
    echo "\nAll FaqParseImport assertions passed.\n";
    exit(0);
}
echo "\n{$failures} assertion(s) failed.\n";
exit(1);
