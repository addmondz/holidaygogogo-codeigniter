<?php
/**
 * Run with: php tests/helpers/GhlLeadTagsParseTest.php
 *
 * Pins ghl_lead_tags_parse() — the GHL Leads listing's tag flattener. The model
 * hands over one contact's tags as the newline-joined JSON arrays of every one
 * of that contact's conversations (GROUP_CONCAT of ghl_conversations.tags_json).
 * This must flatten them into a clean, de-duplicated, first-seen-ordered list.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require __DIR__ . '/../../application/helpers/ghl_lead_tags_helper.php';

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

// Empty / null inputs → no tags.
assert_eq('null',        array(), ghl_lead_tags_parse(null));
assert_eq('empty',       array(), ghl_lead_tags_parse(''));
assert_eq('whitespace',  array(), ghl_lead_tags_parse("   \n  "));

// Single conversation, single tag.
assert_eq('one tag', array('redang'), ghl_lead_tags_parse('["redang"]'));

// Single conversation, several tags — order preserved.
assert_eq('many tags in one array',
    array('2025 customers database', 'temporary crm package sharing oversea', 'active contacts'),
    ghl_lead_tags_parse('["2025 customers database","temporary crm package sharing oversea","active contacts"]'));

// Two conversations for one contact — merged, "redang" de-duplicated, order kept.
assert_eq('merge + dedupe across conversations',
    array('redang', '2025 customers database', 'active contacts'),
    ghl_lead_tags_parse("[\"redang\",\"2025 customers database\"]\n[\"redang\",\"active contacts\"]"));

// Case-insensitive dedupe keeps the first casing seen.
assert_eq('case-insensitive dedupe',
    array('Redang'),
    ghl_lead_tags_parse("[\"Redang\"]\n[\"redang\"]"));

// Blank / non-string members are dropped.
assert_eq('drop blanks and non-strings',
    array('bali'),
    ghl_lead_tags_parse('["bali","","   ",5,null]'));

// A truncated trailing line (GROUP_CONCAT hit its length cap) is skipped, not fatal.
assert_eq('skip malformed line',
    array('phuket'),
    ghl_lead_tags_parse("[\"phuket\"]\n[\"krabi\",\"chiang"));

// Bracketed system tags (device / channel auto-stamps) are dropped; the real
// destination / segment tags survive.
assert_eq('drop bracketed system tags',
    array('redang', '2025 customers database', 'active contacts'),
    ghl_lead_tags_parse('["[whatsapp] - lead capture","redang","[device] - sales - 0102396385","2025 customers database","active contacts"]'));

echo "\nAll assertions passed.\n";
