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

// --- costing_itinerary_html_fields (per-day) -------------------------------
$fields = costing_itinerary_html_fields();
$assertions['one per-day html field']  = $fields === array('description');

// --- costing_itinerary_level_fields (whole itinerary) ----------------------
$level = costing_itinerary_level_fields();
$assertions['one merged level field'] = $level === array('itinerary_notes');

// --- meal plan options + normalise + labels --------------------------------
$opts = costing_meal_plan_options();
$assertions['six meal options']     = count($opts) === 6;
$assertions['meal option slugs']    = array_keys($opts) === array('breakfast', 'lunch', 'dinner', 'tea_break', 'supper', 'special_arrangement');
// Canonical order regardless of posted order; invalids dropped; deduped.
$assertions['meal normalise order'] = costing_meal_plan_normalize(array('dinner', 'breakfast', 'lunch')) === 'breakfast,lunch,dinner';
$assertions['meal normalise drop']  = costing_meal_plan_normalize(array('breakfast', 'bogus', 'breakfast')) === 'breakfast';
$assertions['meal normalise empty'] = costing_meal_plan_normalize(array()) === null;
$assertions['meal normalise str']   = costing_meal_plan_normalize('supper,tea_break') === 'tea_break,supper';
$assertions['meal labels']          = costing_meal_plan_labels('breakfast,dinner') === array('Breakfast', 'Dinner');
$assertions['meal labels legacy']   = costing_meal_plan_labels('<p>B / L / D</p>') === array('B / L / D');
$assertions['meal labels empty']    = costing_meal_plan_labels('') === array();

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
// Only the per-day fields (title, description, meal_plan) decide emptiness now;
// notes/special_remark/terms moved to the itinerary level and are ignored here.
$empty = costing_itinerary_prepare_row(array(
    'day_number'           => 3,
    'title'                => '  ',
    'description'          => '<p></p>',
    'meal_plan'            => array(),
));
$assertions['empty row is_empty']     = $empty['is_empty'] === true;
$assertions['empty row keeps day']    = $empty['day_number'] === 3;
$assertions['empty row null title']   = $empty['title'] === null;
$assertions['empty row null desc']    = $empty['description'] === null;
$assertions['empty row null meal']    = $empty['meal_plan'] === null;
$assertions['empty row no level keys'] = !array_key_exists('notes', $empty) && !array_key_exists('itinerary_notes', $empty);

// --- title-only row is kept -------------------------------------------------
$titleOnly = costing_itinerary_prepare_row(array('title' => 'Arrival Day', 'description' => '<p></p>'));
$assertions['title-only not empty']   = $titleOnly['is_empty'] === false;
$assertions['title-only trims']       = $titleOnly['title'] === 'Arrival Day';
$assertions['title-only day 0']       = $titleOnly['day_number'] === 0;

// --- a row with real content in each per-day field -------------------------
$full = costing_itinerary_prepare_row(array(
    'day_number'           => '2',
    'title'                => '  City Tour  ',
    'description'          => '<p>Visit the <strong>old town</strong>.</p>',
    'meal_plan'            => array('breakfast', 'lunch', 'dinner'),
));
$assertions['full not empty']         = $full['is_empty'] === false;
$assertions['full day cast int']      = $full['day_number'] === 2;
$assertions['full title trimmed']     = $full['title'] === 'City Tour';
$assertions['full desc kept']         = $full['description'] === '<p>Visit the <strong>old town</strong>.</p>';
$assertions['full meal kept']         = $full['meal_plan'] === 'breakfast,lunch,dinner';

// --- a meal-plan-only row is kept even with no title/description -----------
$mealOnly = costing_itinerary_prepare_row(array('meal_plan' => array('breakfast')));
$assertions['meal-only not empty']    = $mealOnly['is_empty'] === false;
$assertions['meal-only null title']   = $mealOnly['title'] === null;
$assertions['meal-only slug kept']    = $mealOnly['meal_plan'] === 'breakfast';

// --- costing_itinerary_prepare_level: merged Notes field -------------------
$lvl = costing_itinerary_prepare_level(array(
    'notes' => '<p><strong>Include</strong></p><ul><li>Bring walking shoes</li></ul>',
));
$assertions['level notes kept']       = strpos($lvl['itinerary_notes'], '<li>Bring walking shoes</li>') !== false;
$assertions['level notes single key']  = (array_keys($lvl) === array('itinerary_notes'));

// Blank TinyMCE markup collapses to null.
$lvlBlank = costing_itinerary_prepare_level(array('notes' => '<p><br></p>'));
$assertions['level all blank null'] = $lvlBlank['itinerary_notes'] === null;

$failed = 0;
foreach ($assertions as $label => $ok) {
    echo ($ok ? '  PASS  ' : '  FAIL  ') . $label . PHP_EOL;
    if (!$ok) {
        $failed++;
    }
}

echo PHP_EOL . ($failed === 0 ? "All assertions passed.\n" : "$failed assertion(s) failed.\n");
exit($failed === 0 ? 0 : 1);
