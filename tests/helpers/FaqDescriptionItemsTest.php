<?php
/**
 * Run with: php tests/helpers/FaqDescriptionItemsTest.php
 *
 * Locks the contract for the FAQ Description sub-question/sub-answer encoding,
 * implemented by the pure static helpers on Faq_Model:
 *   - Build_Items()  : zip posted sub_questions[]/sub_answers[] -> validated list
 *   - Encode_Items() : list -> stored JSON string
 *   - Decode_Items() : stored string -> list (incl. legacy plain-text fallback)
 *
 * These run without a DB. CI_Model is stubbed so the model file can be required.
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

// ---- Build_Items ----------------------------------------------------------
$r = Faq_Model::Build_Items(array('Q1', 'Q2'), array('A1', 'A2'));
check('build: paired rows',        array(array('q'=>'Q1','a'=>'A1'), array('q'=>'Q2','a'=>'A2')), $r['items']);
check('build: paired rows ok',     null, $r['error']);

$r = Faq_Model::Build_Items(array('  Q1  ', ''), array('  A1  ', ''));
check('build: trims + drops blank', array(array('q'=>'Q1','a'=>'A1')), $r['items']);
check('build: blank row no error',  null, $r['error']);

$r = Faq_Model::Build_Items(array('Q only'), array(''));
check('build: half row -> error items', array(), $r['items']);
check('build: half row -> error msg',   'Each sub-question must have a matching sub-answer.', $r['error']);

$r = Faq_Model::Build_Items(array(''), array('A only'));
check('build: answer-without-question -> error', 'Each sub-question must have a matching sub-answer.', $r['error']);

$r = Faq_Model::Build_Items(null, null);
check('build: non-array -> empty list', array(), $r['items']);
check('build: non-array -> no error',   null, $r['error']);

// mismatched lengths: extra answer with no question is a half row
$r = Faq_Model::Build_Items(array('Q1'), array('A1', 'A2'));
check('build: mismatched lengths -> error', 'Each sub-question must have a matching sub-answer.', $r['error']);

// ---- Encode / Decode round trip ------------------------------------------
$items   = array(array('q'=>'Q1','a'=>'A1'), array('q'=>'Q2','a'=>'A2'));
$encoded = Faq_Model::Encode_Items($items);
check('encode/decode round trip', $items, Faq_Model::Decode_Items($encoded));

check('encode: empty list -> empty string', '', Faq_Model::Encode_Items(array()));

// ---- Decode edge cases ----------------------------------------------------
check('decode: empty string -> []',  array(), Faq_Model::Decode_Items(''));
check('decode: whitespace -> []',     array(), Faq_Model::Decode_Items('   '));
check('decode: legacy plain text',    array(array('q'=>'','a'=>'old answer text')), Faq_Model::Decode_Items('old answer text'));
check('decode: single object wrapped', array(array('q'=>'Q','a'=>'A')), Faq_Model::Decode_Items('{"q":"Q","a":"A"}'));
check('decode: slashes/unicode preserved', array(array('q'=>'Pay 50%','a'=>'http://x/y')), Faq_Model::Decode_Items(Faq_Model::Encode_Items(array(array('q'=>'Pay 50%','a'=>'http://x/y')))));

if ($failures === 0) {
    echo "\nAll FaqDescriptionItems assertions passed.\n";
    exit(0);
}
echo "\n{$failures} assertion(s) failed.\n";
exit(1);
