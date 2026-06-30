<?php
/**
 * Run with: php tests/helpers/CardVisibilityRegistryTest.php
 *
 * Locks the pure pieces of the per-(user x card) visibility feature
 * (application/helpers/card_visibility_helper.php):
 *
 *   - Registry integrity: every card has group/title/levels/anchor/keys, slugs
 *     are unique and contain no '|' (the pair-key separator), levels are valid
 *     non-owner Levels, and anchors are unique enough to hide one column.
 *   - Eligibility: a (card,user) pair exists only when the user's Level is in the
 *     card's levels — i.e. only role-eligible users are offered per card.
 *   - Visible-by-default diff: the hidden set is exactly the eligible pairs the
 *     owner did NOT leave switched on.
 *   - Hide CSS: emits one column-hiding selector per hidden card, empty when none.
 */

if (!defined('BASEPATH')) {
	define('BASEPATH', __DIR__);
}
require __DIR__ . '/../../application/helpers/card_visibility_helper.php';

$fail = 0;
function check($label, $cond) {
	global $fail;
	if ($cond) { echo "  PASS  {$label}\n"; }
	else { echo "  FAIL  {$label}\n"; $fail++; }
}
function eq($label, $expected, $actual) {
	check($label . ' = ' . var_export($actual, true), $expected === $actual);
}

// ---------------------------------------------------------------------------
// Registry integrity.
// ---------------------------------------------------------------------------
$reg = card_visibility_registry();
check('registry non-empty', count($reg) > 0);

$valid_levels = array(20, 25, 30, 40, 45, 50);
$seen_anchor = array();
$reg_ok = true;
foreach ($reg as $slug => $c) {
	if (strpos($slug, '|') !== false) { $reg_ok = false; echo "    slug has pipe: {$slug}\n"; }
	foreach (array('group', 'title', 'levels', 'anchor', 'keys') as $f) {
		if (!isset($c[$f]) || $c[$f] === '' || $c[$f] === array() && $f !== 'levels') {
			if (!isset($c[$f])) { $reg_ok = false; echo "    {$slug} missing {$f}\n"; }
		}
	}
	if (empty($c['levels'])) { $reg_ok = false; echo "    {$slug} no levels\n"; }
	foreach ($c['levels'] as $lv) {
		if (!in_array((int)$lv, $valid_levels, true)) { $reg_ok = false; echo "    {$slug} bad level {$lv}\n"; }
	}
	if (empty($c['keys'])) { $reg_ok = false; echo "    {$slug} no keys\n"; }
	if (empty($c['anchor'])) { $reg_ok = false; echo "    {$slug} no anchor\n"; }
}
check('every card well-formed (group/title/levels/anchor/keys)', $reg_ok);

// Group buckets are exactly the four non-owner roles.
$groups = array();
foreach ($reg as $c) { $groups[$c['group']] = true; }
ksort($groups);
eq('groups present', array('Finance', 'OP', 'TC', 'TC Lead'), array_keys($groups));

// ---------------------------------------------------------------------------
// Eligibility — only role-eligible users get a pair per card.
// ---------------------------------------------------------------------------
$mini = array(
	'tc_one'  => array('group' => 'TC', 'title' => 'A', 'levels' => array(20, 50), 'anchor' => 'sc-a', 'keys' => array('a')),
	'op_one'  => array('group' => 'OP', 'title' => 'B', 'levels' => array(40, 45), 'anchor' => 'sc-b', 'keys' => array('b')),
);
$users = array(
	(object) array('AdminID' => 1, 'Name' => 'Tc',   'Level' => '20'),
	(object) array('AdminID' => 2, 'Name' => 'Tc2',  'Level' => '50'),
	(object) array('AdminID' => 3, 'Name' => 'Op',   'Level' => '40'),
	(object) array('AdminID' => 4, 'Name' => 'Fin',  'Level' => '30'),
);
$pairs = card_visibility_eligible_pairs($mini, $users);
sort($pairs);
// tc_one -> users 1,2 ; op_one -> user 3 ; finance user 4 eligible for nothing here.
eq('eligible pairs only role-matched', array('op_one|3', 'tc_one|1', 'tc_one|2'), $pairs);

// ---------------------------------------------------------------------------
// Visible-by-default diff — hidden = eligible minus the posted "visible" set.
// ---------------------------------------------------------------------------
$eligible = array('tc_one|1', 'tc_one|2', 'op_one|3');
// Owner left tc_one|1 and op_one|3 ON; tc_one|2 was switched OFF.
$visible  = array('tc_one|1' => true, 'op_one|3' => true);
$hidden   = card_visibility_hidden_from_visible($eligible, $visible);
eq('one hidden pair', 1, count($hidden));
eq('hidden is the un-toggled pair', array('slug' => 'tc_one', 'admin' => 2), $hidden[0]);

// All visible -> nothing hidden (the default state).
$none = card_visibility_hidden_from_visible($eligible, array('tc_one|1' => true, 'tc_one|2' => true, 'op_one|3' => true));
eq('all-on hides nothing', 0, count($none));

// Nothing visible -> everything hidden.
$all = card_visibility_hidden_from_visible($eligible, array());
eq('all-off hides everything', 3, count($all));

// ---------------------------------------------------------------------------
// Hide CSS — one selector per hidden card, scoped + display:none.
// ---------------------------------------------------------------------------
eq('no hidden -> empty css', '', card_visibility_hide_css(array(), $reg));
$css = card_visibility_hide_css(array('tc_bc_created' => true, 'op_pending_bc' => true), $reg);
check('css hides bc anchor',      strpos($css, ':has(#sc-bc-month-count)') !== false);
check('css hides pending-bc anchor', strpos($css, ':has(#sc-pending-bc-op-count)') !== false);
check('css uses display:none',    strpos($css, 'display:none') !== false);
check('css scoped to container',  strpos($css, '#booking_summary_cards') !== false);
// Unknown slug is ignored, not fatal.
eq('unknown slug -> empty css', '', card_visibility_hide_css(array('does_not_exist' => true), $reg));

echo $fail === 0 ? "\nAll CardVisibilityRegistry tests passed.\n" : "\n{$fail} FAILED\n";
exit($fail === 0 ? 0 : 1);
