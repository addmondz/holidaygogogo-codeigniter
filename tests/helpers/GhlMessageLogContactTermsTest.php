<?php
/**
 * Run with: php tests/helpers/GhlMessageLogContactTermsTest.php
 *
 * Pins ghl_message_log_normalize_contact_terms() -- the Message Log "Contact"
 * filter normaliser. GHL stores a contact's DISPLAY NAME in from_number /
 * to_number when it has no phone number for them (e.g. "Siew Chin Yap",
 * "HolidayGoGoGo"), so the filter must accept BOTH phone numbers and names.
 *
 * The old digits-only normaliser silently dropped anything with a letter, so a
 * name search reduced to '' and filtered nothing (the whole log came back).
 * This test locks the typed-term output: phone terms carry digits only, name
 * terms carry the trimmed text, several comma-separated terms compose, and
 * duplicates collapse.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require __DIR__ . '/../../application/helpers/ghl_messages_log_helper.php';

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

// A plain phone number: formatting stripped to digits, tagged 'phone'.
assert_eq('phone number -> digits-only phone term',
    array(array('type' => 'phone', 'value' => '60123456789')),
    ghl_message_log_normalize_contact_terms('+60 12-345 6789'));

// A name (the bug): kept as a trimmed 'name' term instead of vanishing.
assert_eq('name -> name term (not dropped)',
    array(array('type' => 'name', 'value' => 'Siew Chin Yap')),
    ghl_message_log_normalize_contact_terms('Siew Chin Yap'));

// Surrounding whitespace on a name is trimmed.
assert_eq('name is trimmed',
    array(array('type' => 'name', 'value' => 'Jenny Lim')),
    ghl_message_log_normalize_contact_terms('  Jenny Lim  '));

// Mixed comma-separated list: a name and a number compose into two terms.
assert_eq('name + number compose',
    array(
        array('type' => 'name', 'value' => 'Siew Chin Yap'),
        array('type' => 'phone', 'value' => '60198765432'),
    ),
    ghl_message_log_normalize_contact_terms('Siew Chin Yap, +6019-876 5432'));

// Array input (multi-value form field) is accepted the same way.
assert_eq('array input',
    array(
        array('type' => 'phone', 'value' => '60123'),
        array('type' => 'name', 'value' => 'Chat AI'),
    ),
    ghl_message_log_normalize_contact_terms(array('60123', 'Chat AI')));

// Duplicates collapse (same type + value), order preserved.
assert_eq('duplicates collapse',
    array(array('type' => 'phone', 'value' => '60123')),
    ghl_message_log_normalize_contact_terms('60123, +60 123'));

// A phone and a name that reduce to the "same" text stay distinct terms.
assert_eq('phone vs name are distinct',
    array(
        array('type' => 'name', 'value' => 'Ali'),
        array('type' => 'phone', 'value' => '123'),
    ),
    ghl_message_log_normalize_contact_terms('Ali; 123'));

// Blank / punctuation-only entries are dropped.
assert_eq('blank + junk dropped',
    array(array('type' => 'name', 'value' => 'Bob')),
    ghl_message_log_normalize_contact_terms(' , Bob ,   , +++'));

// Nothing usable -> empty list (the WHERE builder then adds no clause).
assert_eq('empty input -> no terms', array(), ghl_message_log_normalize_contact_terms(''));
assert_eq('null input -> no terms', array(), ghl_message_log_normalize_contact_terms(null));

// Idempotent: an already-typed list passes straight through, so the controller
// can normalise once and the model can safely re-normalise what it received.
$typed = array(
    array('type' => 'name', 'value' => 'Siew Chin Yap'),
    array('type' => 'phone', 'value' => '60123'),
);
assert_eq('already-typed list is idempotent', $typed,
    ghl_message_log_normalize_contact_terms($typed));

echo "\nAll assertions passed.\n";
