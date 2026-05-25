<?php
/**
 * Run with: php tests/helpers/AggregateActiveLeadsByTagTest.php
 *
 * Locks the PHP aggregation logic that powers the TC LEAD "Active Leads by
 * Tag" card. The model fetches unconverted leads + tags_json in one query
 * and hands the rows to ghl_aggregate_active_leads_by_tag() — so this test
 * is the source of truth for the bucketing rules. The companion
 * ActiveLeadsByTagTest locks the SQL fetch shape.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require_once __DIR__ . '/../../application/helpers/ghl_tag_categories_helper.php';

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

$category_tags = array(
    'destination' => array('redang', 'tioman', 'phuket', 'bali'),
    'language'    => array('bm', 'en', 'cn'),
    'race'        => array('muslim', 'muslim china'),
);

$rows = array(
    // 1 — redang only
    array('id' => 1, 'tags_json' => '["redang", "active contacts"]'),
    // 2 — REDANG (caps) — same category match as #1
    array('id' => 2, 'tags_json' => '["REDANG"]'),
    // 3 — Redang DUPLICATED within the same lead — counts once
    array('id' => 3, 'tags_json' => '["Redang", "redang"]'),
    // 4 — tioman + muslim — counts in destination AND race
    array('id' => 4, 'tags_json' => '["tioman", "muslim"]'),
    // 5 — phuket
    array('id' => 5, 'tags_json' => '["phuket"]'),
    // 6 — phuket + bali — counts in both destinations
    array('id' => 6, 'tags_json' => '["phuket", "bali"]'),
    // 7 — bm language
    array('id' => 7, 'tags_json' => '["bm"]'),
    // 8 — en + cn (multi-language lead)
    array('id' => 8, 'tags_json' => '["en", "cn"]'),
    // 9 — substring-only "redang052026" must NOT match "redang"
    array('id' => 9, 'tags_json' => '["redang052026"]'),
    // 10 — tag with surrounding whitespace ("  muslim  ") still matches
    array('id' => 10, 'tags_json' => '["  muslim  "]'),
    // 11 — non-array JSON (e.g. corrupt data) — skip cleanly
    array('id' => 11, 'tags_json' => '{"not": "an array"}'),
    // 12 — NULL tags_json — skip cleanly
    array('id' => 12, 'tags_json' => null),
    // 13 — empty string — skip cleanly
    array('id' => 13, 'tags_json' => ''),
    // 14 — empty JSON array — skip cleanly
    array('id' => 14, 'tags_json' => '[]'),
    // 15 — non-string tag entries (defensive) — skip the non-string element
    array('id' => 15, 'tags_json' => '[123, "muslim china"]'),
);

$result = ghl_aggregate_active_leads_by_tag($category_tags, $rows);

// ------------------------- destination -------------------------
// redang counts: rows 1, 2 (case-insens), 3 (dup-in-one-lead -> once each) = 3
// tioman counts: row 4                                                      = 1
// phuket counts: rows 5, 6                                                  = 2
// bali   counts: row 6                                                      = 1
// Sorted by count DESC, then tag ASC: redang(3), phuket(2), bali(1), tioman(1)
assert_eq('destination tag count', 4, count($result['destination']));
assert_eq('destination top tag',       'redang', $result['destination'][0]['tag']);
assert_eq('destination top count',     3,        $result['destination'][0]['count']);
assert_eq('destination 2nd tag',       'phuket', $result['destination'][1]['tag']);
assert_eq('destination 2nd count',     2,        $result['destination'][1]['count']);
// tie-break by tag name ASC: 'bali' < 'tioman'
assert_eq('destination tie-break asc 1', 'bali',   $result['destination'][2]['tag']);
assert_eq('destination tie-break asc 2', 'tioman', $result['destination'][3]['tag']);

// ------------------------- language ----------------------------
// bm: row 7; en: row 8; cn: row 8.
assert_eq('language tag count', 3, count($result['language']));
// All counts = 1; tie-break by tag name ASC: 'bm', 'cn', 'en'.
assert_eq('language[0] tag', 'bm', $result['language'][0]['tag']);
assert_eq('language[1] tag', 'cn', $result['language'][1]['tag']);
assert_eq('language[2] tag', 'en', $result['language'][2]['tag']);

// ------------------------- race --------------------------------
// muslim: row 4 + row 10 (whitespace-trimmed)             = 2
// muslim china: row 15                                    = 1
assert_eq('race tag count', 2, count($result['race']));
assert_eq('race[0] tag',   'muslim',       $result['race'][0]['tag']);
assert_eq('race[0] count', 2,              $result['race'][0]['count']);
assert_eq('race[1] tag',   'muslim china', $result['race'][1]['tag']);
assert_eq('race[1] count', 1,              $result['race'][1]['count']);

// ------------------- empty-category passes through --------------
$empty_input = ghl_aggregate_active_leads_by_tag(
    array('destination' => array('redang'), 'language' => array(), 'race' => array()),
    array()
);
assert_eq('empty inputs -> empty destination',  array(), $empty_input['destination']);
assert_eq('empty inputs -> empty language',     array(), $empty_input['language']);
assert_eq('empty inputs -> empty race',         array(), $empty_input['race']);

// ------------------ unknown tags are silently ignored -----------
$noise = ghl_aggregate_active_leads_by_tag(
    array('destination' => array('redang'), 'language' => array(), 'race' => array()),
    array(
        array('id' => 1, 'tags_json' => '["active contacts", "2025 customers database", "[whatsapp] - lead capture"]'),
    )
);
assert_eq('off-allowlist tags ignored', array(), $noise['destination']);

// --------------------- top-N cap (10 per dimension) -------------
// Build 13 destination tags, each carried by a unique lead with a
// monotonically decreasing count. After the cap, only the top 10
// should appear, and the 11th-13th must be dropped.
$cap_tags = array();
$cap_rows = array();
$lead_id = 100;
for ($i = 1; $i <= 13; $i++) {
    $tag = 'cap-tag-' . sprintf('%02d', $i);
    $cap_tags[] = $tag;
    // Give tag i a count of (14 - i) so cap-tag-01 has 13, cap-tag-13 has 1.
    $count_for_tag = 14 - $i;
    for ($j = 0; $j < $count_for_tag; $j++) {
        $cap_rows[] = array('id' => ++$lead_id, 'tags_json' => json_encode(array($tag)));
    }
}
$capped = ghl_aggregate_active_leads_by_tag(
    array('destination' => $cap_tags, 'language' => array(), 'race' => array()),
    $cap_rows
);
assert_eq('top-10 cap on destination',  10,           count($capped['destination']));
assert_eq('top entry is highest count', 'cap-tag-01', $capped['destination'][0]['tag']);
assert_eq('top entry count',            13,           $capped['destination'][0]['count']);
assert_eq('10th entry is cap-tag-10',   'cap-tag-10', $capped['destination'][9]['tag']);
// cap-tag-11/12/13 must be dropped.
$dropped_tags = array_map(function ($r) { return $r['tag']; }, $capped['destination']);
assert_eq('cap-tag-11 dropped', false, in_array('cap-tag-11', $dropped_tags, true));
assert_eq('cap-tag-13 dropped', false, in_array('cap-tag-13', $dropped_tags, true));

echo "\nAll assertions passed.\n";
