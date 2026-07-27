<?php
/**
 * Run with: php tests/helpers/FaqTagChipsTest.php
 *
 * Locks Faq_Model::Tag_Chips(), the pure helper that decides how the tag
 * filter chips are presented on the internal FAQ Library (/Faq/Internal).
 *
 * Contract:
 *   - Input is the id => name map of tags actually present on the page and the
 *     set of "default" tag ids (from faq_tag.IsDefault = 'Y').
 *   - Output is an ordered, reindexed list of chip descriptors:
 *       array('id' => int, 'name' => string, 'is_default' => bool)
 *   - Every present tag is returned (the tag search bar reveals non-default
 *     ones); is_default marks which show up-front as curated chips.
 *   - Sorted by name, natural + case-insensitive, so the bar reads stably.
 *   - Default ids that are NOT present on the page are ignored (a default tag
 *     nobody used never appears as a chip).
 *   - Bad input is tolerated: non-array default set -> nothing is default;
 *     ids are compared as ints ('3' matches 3).
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

// ---- default flag assigned, sorted by name, all tags kept ------------------
$present = array(3 => 'Visa', 1 => 'Flights', 2 => 'Refunds');
$chips   = Faq_Model::Tag_Chips($present, array(1, 2));
check('sorted + flagged', array(
    array('id' => 1, 'name' => 'Flights', 'is_default' => true),
    array('id' => 2, 'name' => 'Refunds', 'is_default' => true),
    array('id' => 3, 'name' => 'Visa',    'is_default' => false),
), $chips);

// ---- default id not present on the page is ignored -------------------------
$chips = Faq_Model::Tag_Chips(array(5 => 'Hotels'), array(99));
check('absent default ignored', array(
    array('id' => 5, 'name' => 'Hotels', 'is_default' => false),
), $chips);

// ---- string ids in the default set still match int keys --------------------
$chips = Faq_Model::Tag_Chips(array(7 => 'Baggage'), array('7'));
check('string default id matches', array(
    array('id' => 7, 'name' => 'Baggage', 'is_default' => true),
), $chips);

// ---- case-insensitive natural sort -----------------------------------------
$chips = Faq_Model::Tag_Chips(array(1 => 'banana', 2 => 'Apple', 3 => 'cherry'), array());
check('case-insensitive order', array('Apple', 'banana', 'cherry'),
    array_map(function ($c) { return $c['name']; }, $chips));

// ---- non-array default set -> nothing flagged; empty present -> empty -------
$chips = Faq_Model::Tag_Chips(array(1 => 'Solo'), null);
check('non-array default set', array(
    array('id' => 1, 'name' => 'Solo', 'is_default' => false),
), $chips);
check('empty present -> []', array(), Faq_Model::Tag_Chips(array(), array(1, 2)));

echo $failures === 0 ? "\nOK\n" : "\n{$failures} FAILURE(S)\n";
exit($failures === 0 ? 0 : 1);
