<?php
/**
 * Run with: php tests/helpers/FaqAuditItemsTest.php
 *
 * Locks the per-item audit contract layered on top of the FAQ sub-question /
 * sub-answer encoding. Build_Items() now optionally accepts the previously
 * stored audit metadata plus the acting admin and a timestamp, and stamps each
 * row with created-by/date (cb/cd) and updated-by/date (ub/ud):
 *   - a brand new row        -> cb/cd/ub/ud all = actor/now
 *   - an existing row, edited -> cb/cd kept, ub/ud = actor/now
 *   - an existing row, same   -> cb/cd/ub/ud all preserved
 * The legacy 2-argument call must keep returning bare {q,a} rows so the older
 * contract (FaqDescriptionItemsTest) is untouched. Decode_Items() passes the
 * audit keys through when present.
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

// ---- New rows: everything stamped with actor/now -------------------------
$r = Faq_Model::Build_Items(
    array('Q1'), array('A1'),
    array(),               // empty meta -> audited mode, but no prior data
    'Bob', $NOW
);
check('audit: new row stamped', array(array(
    'q' => 'Q1', 'a' => 'A1',
    'cb' => 'Bob', 'cd' => $NOW, 'ub' => 'Bob', 'ud' => $NOW,
)), $r['items']);
check('audit: new row no error', null, $r['error']);

// ---- Existing row, unchanged: all audit fields preserved -----------------
$meta = array(
    'cb' => array('Alice'), 'cd' => array('2026-06-09 09:00:00'),
    'ub' => array('Alice'), 'ud' => array('2026-06-09 09:00:00'),
    'oq' => array('Q1'),    'oa' => array('A1'),
);
$r = Faq_Model::Build_Items(array('Q1'), array('A1'), $meta, 'Bob', $NOW);
check('audit: unchanged row preserved', array(array(
    'q' => 'Q1', 'a' => 'A1',
    'cb' => 'Alice', 'cd' => '2026-06-09 09:00:00',
    'ub' => 'Alice', 'ud' => '2026-06-09 09:00:00',
)), $r['items']);

// ---- Existing row, edited: created kept, updated bumped -------------------
$r = Faq_Model::Build_Items(array('Q1'), array('A1 changed'), $meta, 'Bob', $NOW);
check('audit: edited row bumps updated only', array(array(
    'q' => 'Q1', 'a' => 'A1 changed',
    'cb' => 'Alice', 'cd' => '2026-06-09 09:00:00',
    'ub' => 'Bob', 'ud' => $NOW,
)), $r['items']);

// ---- Mixed: row 1 untouched, row 2 new -----------------------------------
$meta2 = array(
    'cb' => array('Alice', ''), 'cd' => array('2026-06-09 09:00:00', ''),
    'ub' => array('Alice', ''), 'ud' => array('2026-06-09 09:00:00', ''),
    'oq' => array('Q1', ''),    'oa' => array('A1', ''),
);
$r = Faq_Model::Build_Items(array('Q1', 'Q2'), array('A1', 'A2'), $meta2, 'Bob', $NOW);
check('audit: mixed kept+new', array(
    array('q'=>'Q1','a'=>'A1','cb'=>'Alice','cd'=>'2026-06-09 09:00:00','ub'=>'Alice','ud'=>'2026-06-09 09:00:00'),
    array('q'=>'Q2','a'=>'A2','cb'=>'Bob','cd'=>$NOW,'ub'=>'Bob','ud'=>$NOW),
), $r['items']);

// ---- Validation still applies in audited mode ----------------------------
$r = Faq_Model::Build_Items(array('Q only'), array(''), array(), 'Bob', $NOW);
check('audit: half row still errors', 'Each sub-question must have a matching sub-answer.', $r['error']);

// ---- Legacy 2-arg call unchanged (no audit keys) -------------------------
$r = Faq_Model::Build_Items(array('Q1'), array('A1'));
check('audit: legacy 2-arg stays bare', array(array('q'=>'Q1','a'=>'A1')), $r['items']);

// ---- Round trip preserves audit keys -------------------------------------
$audited = Faq_Model::Build_Items(array('Q1'), array('A1'), array(), 'Bob', $NOW)['items'];
$encoded = Faq_Model::Encode_Items($audited);
check('audit: encode/decode round trip', $audited, Faq_Model::Decode_Items($encoded));

// ---- Decode omits audit keys for legacy rows -----------------------------
check('audit: legacy decode has no audit keys',
    array(array('q'=>'Q','a'=>'A')),
    Faq_Model::Decode_Items('{"q":"Q","a":"A"}'));

if ($failures === 0) {
    echo "\nAll FaqAuditItems assertions passed.\n";
    exit(0);
}
echo "\n{$failures} assertion(s) failed.\n";
exit(1);
