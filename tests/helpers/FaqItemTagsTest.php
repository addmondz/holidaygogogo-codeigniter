<?php
/**
 * Run with: php tests/helpers/FaqItemTagsTest.php
 *
 * Locks the per-sub-Q&A tag contract layered on top of the FAQ sub-question /
 * sub-answer encoding. Tags now live on each item (not the whole FAQ):
 *   - Build_Items() takes an optional trailing $tags parallel array (one entry
 *     per posted row, each an array of tag ids). Each kept row gets a normalised
 *     (deduped, positive-int) `tags` key, placed right after `a`; an empty tag
 *     set omits the key entirely (mirrors the conditional audit keys).
 *   - Decode_Items() passes `tags` through, normalised, and omits when empty.
 *   - Item_Tag_Ids() returns the deduped, sorted union of tag ids across items.
 * The legacy / no-$tags call path must stay byte-identical (see the other FAQ
 * tests), so omission-when-empty is part of the contract.
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

$NOW = '2026-06-12 10:00:00';

// ---- Build_Items with per-row tags (no audit) -----------------------------
$r = Faq_Model::Build_Items(array('Q1', 'Q2'), array('A1', 'A2'), null, '', '', array(array(3, 7), array(5)));
check('build: tags attached per row', array(
    array('q' => 'Q1', 'a' => 'A1', 'tags' => array(3, 7)),
    array('q' => 'Q2', 'a' => 'A2', 'tags' => array(5)),
), $r['items']);

// ---- Empty / absent tag set omits the key ---------------------------------
$r = Faq_Model::Build_Items(array('Q1'), array('A1'), null, '', '', array(array()));
check('build: empty tags omit key', array(array('q' => 'Q1', 'a' => 'A1')), $r['items']);

// ---- Tag ids are normalised (dedupe, positive ints only) ------------------
$r = Faq_Model::Build_Items(array('Q1'), array('A1'), null, '', '', array(array(3, 3, '7', 0, -1)));
check('build: tags normalised', array(array('q' => 'Q1', 'a' => 'A1', 'tags' => array(3, 7))), $r['items']);

// ---- Alignment survives a dropped blank row -------------------------------
// Middle row is blank so it (and its tags) drop; surviving rows keep their own.
$r = Faq_Model::Build_Items(
    array('Q1', '', 'Q3'), array('A1', '', 'A3'),
    null, '', '',
    array(array(3), array(9), array(5))
);
check('build: tags align past dropped row', array(
    array('q' => 'Q1', 'a' => 'A1', 'tags' => array(3)),
    array('q' => 'Q3', 'a' => 'A3', 'tags' => array(5)),
), $r['items']);

// ---- Tags + audit together (order: q, a, tags, cb, cd, ub, ud) ------------
$r = Faq_Model::Build_Items(array('Q1'), array('A1'), array(), 'Bob', $NOW, array(array(3)));
check('build: tags + audit new row', array(array(
    'q' => 'Q1', 'a' => 'A1', 'tags' => array(3),
    'cb' => 'Bob', 'cd' => $NOW, 'ub' => 'Bob', 'ud' => $NOW,
)), $r['items']);

// ---- Decode passes tags through, normalised -------------------------------
check('decode: tags passthrough',
    array(array('q' => 'Q', 'a' => 'A', 'tags' => array(3, 7))),
    Faq_Model::Decode_Items('[{"q":"Q","a":"A","tags":[3,7]}]'));

check('decode: tags normalised',
    array(array('q' => 'Q', 'a' => 'A', 'tags' => array(3, 7))),
    Faq_Model::Decode_Items('[{"q":"Q","a":"A","tags":[3,3,"7"]}]'));

check('decode: empty tags omitted',
    array(array('q' => 'Q', 'a' => 'A')),
    Faq_Model::Decode_Items('[{"q":"Q","a":"A","tags":[]}]'));

// ---- Encode / Decode round trip preserves tags (+ audit) ------------------
$built   = Faq_Model::Build_Items(array('Q1'), array('A1'), array(), 'Bob', $NOW, array(array(3, 7)))['items'];
$encoded = Faq_Model::Encode_Items($built);
check('round trip: tags + audit', $built, Faq_Model::Decode_Items($encoded));

// ---- Item_Tag_Ids: deduped, sorted union across items ---------------------
$items = array(
    array('q' => 'Q1', 'a' => 'A1', 'tags' => array(3, 7)),
    array('q' => 'Q2', 'a' => 'A2', 'tags' => array(5, 3)),
    array('q' => 'Q3', 'a' => 'A3'),
);
check('item_tag_ids: union', array(3, 5, 7), Faq_Model::Item_Tag_Ids($items));
check('item_tag_ids: empty', array(), Faq_Model::Item_Tag_Ids(array()));

if ($failures === 0) {
    echo "\nAll FaqItemTags assertions passed.\n";
    exit(0);
}
echo "\n{$failures} assertion(s) failed.\n";
exit(1);
