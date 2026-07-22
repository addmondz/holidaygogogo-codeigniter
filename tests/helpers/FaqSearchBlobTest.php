<?php
/**
 * Run with: php tests/helpers/FaqSearchBlobTest.php
 *
 * The admin FAQ listing renders as a client-side DataTable (column-rendering.js
 * inits #kt_datatable globally), so its built-in search only sees rendered cell
 * text - it can't reach the sub-Q&A stored as JSON in Description. Search_Blob()
 * flattens a decoded FAQ's sub-questions + sub-answers into one space-joined
 * string the view emits as a hidden cell, so the table's search box matches on
 * sub-Q&A text too. Pure + static so the flattening is unit-testable without a
 * DB; mirrors the q + a blob the public faq/page.php already builds.
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

// ---- q + a flattened in item order, q before a ----------------------------
$items = array(
    array('q' => 'How do I cancel?', 'a' => 'Email support.'),
    array('q' => 'Refund window?',   'a' => 'Within 14 days.'),
);
check('blob: q+a joined',
    'How do I cancel? Email support. Refund window? Within 14 days.',
    Faq_Model::Search_Blob($items));

// ---- Tag ids / audit metadata are NOT included ----------------------------
$items = array(array('q' => 'Q', 'a' => 'A', 'tags' => array(3, 7), 'cb' => 'SIMON', 'cd' => '2026-06-09 15:40:41'));
check('blob: only q/a text', 'Q A', Faq_Model::Search_Blob($items));

// ---- Blank pieces skipped, no double spaces -------------------------------
$items = array(
    array('q' => '',            'a' => 'Answer only'),  // legacy answer-only row
    array('q' => 'Question two', 'a' => ''),
);
check('blob: blanks skipped', 'Answer only Question two', Faq_Model::Search_Blob($items));

// ---- Inner whitespace collapsed, ends trimmed -----------------------------
$items = array(array('q' => "  spaced   out  ", 'a' => "line\nbreak"));
check('blob: whitespace normalised', 'spaced out line break', Faq_Model::Search_Blob($items));

// ---- Empty / non-array input ----------------------------------------------
check('blob: empty items', '', Faq_Model::Search_Blob(array()));
check('blob: non-array',   '', Faq_Model::Search_Blob('nope'));

// ---- Real Description round-trips through Decode_Items ---------------------
$desc = '[{"q":"Is there a passport rule?","a":"Valid 6 months.","tags":[8]}]';
check('blob: from decoded description',
    'Is there a passport rule? Valid 6 months.',
    Faq_Model::Search_Blob(Faq_Model::Decode_Items($desc)));

if ($failures === 0) {
    echo "\nAll FaqSearchBlob assertions passed.\n";
    exit(0);
}
echo "\n{$failures} assertion(s) failed.\n";
exit(1);
