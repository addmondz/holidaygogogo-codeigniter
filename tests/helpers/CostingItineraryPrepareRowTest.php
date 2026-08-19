<?php
/**
 * Run with: php tests/helpers/CostingItineraryPrepareRowTest.php
 *
 * Drives the pure itinerary helpers that back Costing_Model::Save_Itinerary_Days:
 * blank-HTML detection (so empty TinyMCE editors don't create phantom content)
 * and per-row normalisation of the title + five rich-text fields.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require_once __DIR__ . '/../../application/helpers/costing_itinerary_helper.php';

$assertions = array();

// --- costing_itinerary_html_fields -----------------------------------------
$fields = costing_itinerary_html_fields();
$assertions['five html fields'] = count($fields) === 5;
$assertions['fields in order']  = $fields === array('description', 'meal_plan', 'notes', 'special_remark', 'terms_and_conditions');

// --- costing_itinerary_html_is_blank ---------------------------------------
$assertions['empty string blank']    = costing_itinerary_html_is_blank('') === true;
$assertions['null blank']            = costing_itinerary_html_is_blank(null) === true;
$assertions['empty <p> blank']       = costing_itinerary_html_is_blank('<p></p>') === true;
$assertions['<p><br></p> blank']     = costing_itinerary_html_is_blank('<p><br></p>') === true;
$assertions['nbsp entity blank']     = costing_itinerary_html_is_blank('<p>&nbsp;</p>') === true;
$assertions['raw nbsp blank']        = costing_itinerary_html_is_blank("<p>\xc2\xa0</p>") === true;
$assertions['whitespace blank']      = costing_itinerary_html_is_blank("   \n  ") === true;
$assertions['real text not blank']   = costing_itinerary_html_is_blank('<p>Breakfast at hotel</p>') === false;
$assertions['bold text not blank']   = costing_itinerary_html_is_blank('<strong>B/L/D</strong>') === false;
$assertions['image only not blank']  = costing_itinerary_html_is_blank('<p><img src="/x.jpg"></p>') === false;

// --- costing_itinerary_prepare_row: a fully empty row is skippable ----------
$empty = costing_itinerary_prepare_row(array(
    'day_number'           => 3,
    'title'                => '  ',
    'description'          => '<p></p>',
    'meal_plan'            => '<p>&nbsp;</p>',
    'notes'                => '',
    'special_remark'       => '<p><br></p>',
    'terms_and_conditions' => '   ',
));
$assertions['empty row is_empty']     = $empty['is_empty'] === true;
$assertions['empty row keeps day']    = $empty['day_number'] === 3;
$assertions['empty row null title']   = $empty['title'] === null;
$assertions['empty row null desc']    = $empty['description'] === null;
$assertions['empty row null meal']    = $empty['meal_plan'] === null;
$assertions['empty row null terms']   = $empty['terms_and_conditions'] === null;

// --- title-only row is kept -------------------------------------------------
$titleOnly = costing_itinerary_prepare_row(array('title' => 'Arrival Day', 'description' => '<p></p>'));
$assertions['title-only not empty']   = $titleOnly['is_empty'] === false;
$assertions['title-only trims']       = $titleOnly['title'] === 'Arrival Day';
$assertions['title-only day 0']       = $titleOnly['day_number'] === 0;

// --- a row with real rich content in each field ----------------------------
$full = costing_itinerary_prepare_row(array(
    'day_number'           => '2',
    'title'                => '  City Tour  ',
    'description'          => '<p>Visit the <strong>old town</strong>.</p>',
    'meal_plan'            => '<p>B / L / D</p>',
    'notes'                => '<ul><li>Bring walking shoes</li></ul>',
    'special_remark'       => '<p>Vegetarian available</p>',
    'terms_and_conditions' => '<p>Non-refundable after departure.</p>',
));
$assertions['full not empty']         = $full['is_empty'] === false;
$assertions['full day cast int']      = $full['day_number'] === 2;
$assertions['full title trimmed']     = $full['title'] === 'City Tour';
$assertions['full desc kept']         = $full['description'] === '<p>Visit the <strong>old town</strong>.</p>';
$assertions['full meal kept']         = $full['meal_plan'] === '<p>B / L / D</p>';
$assertions['full notes kept']        = strpos($full['notes'], '<li>Bring walking shoes</li>') !== false;
$assertions['full remark kept']       = $full['special_remark'] === '<p>Vegetarian available</p>';
$assertions['full terms kept']        = $full['terms_and_conditions'] === '<p>Non-refundable after departure.</p>';

// --- a meal-plan-only row is kept even with no title/description -----------
$mealOnly = costing_itinerary_prepare_row(array('meal_plan' => '<p>Breakfast only</p>'));
$assertions['meal-only not empty']    = $mealOnly['is_empty'] === false;
$assertions['meal-only null title']   = $mealOnly['title'] === null;

$failed = 0;
foreach ($assertions as $label => $ok) {
    echo ($ok ? '  PASS  ' : '  FAIL  ') . $label . PHP_EOL;
    if (!$ok) {
        $failed++;
    }
}

echo PHP_EOL . ($failed === 0 ? "All assertions passed.\n" : "$failed assertion(s) failed.\n");
exit($failed === 0 ? 0 : 1);
