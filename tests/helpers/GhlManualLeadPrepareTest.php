<?php
/**
 * Run with: php tests/helpers/GhlManualLeadPrepareTest.php
 *
 * Locks the mapper that turns the "Create Lead" form into a ghl_contacts insert
 * row for a hand-entered ("Manual") GHL lead:
 *   - synthetic contact_id "manual:<uid>" so the GHL API sync never clobbers it,
 *   - lead_source = 'manual' (drives the Type column: Manual vs GHL),
 *   - tags stored as a JSON array (same shape ghl_lead_tags_parse renders),
 *   - a lead must carry at least one identifying value (name / phone / email),
 *   - DOB / email / gender are validated & normalized.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}
require __DIR__ . '/../../application/helpers/ghl_manual_lead_helper.php';

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

$now = '2026-07-24 10:00:00';

// ---- happy path: every visible column filled -------------------------------
$res = ghl_manual_lead_prepare(array(
    'first_name'    => '  Ali  ',
    'last_name'     => 'Bakar',
    'phone'         => '+60 12-345 6789',
    'email'         => 'ali@example.com',
    'gender'        => 'male',
    'chat_language' => 'Malay',
    'race'          => 'Malay',
    'nationality'   => 'Malaysia',
    'date_of_birth' => '1990-05-20',
    'tags'          => 'Redang, VIP, redang',
    // created_by is server-supplied; a POST value must NOT leak into the row.
    'created_by'    => '999',
), 'abc123', $now, 42);

assert_eq('happy ok', true, $res['ok']);
// creator comes from the $created_by arg, never from POST
assert_eq('created_by from arg', 42, $res['row']['created_by']);
assert_eq('happy no errors', array(), $res['errors']);
assert_eq('synthetic contact_id', 'manual:abc123', $res['row']['contact_id']);
assert_eq('lead_source', 'manual', $res['row']['lead_source']);
assert_eq('first name trimmed', 'Ali', $res['row']['first_name']);
assert_eq('phone kept raw', '+60 12-345 6789', $res['row']['phone']);
assert_eq('gender normalized', 'Male', $res['row']['gender']);
assert_eq('language', 'Malay', $res['row']['chat_language']);
assert_eq('race', 'Malay', $res['row']['race']);
assert_eq('nationality', 'Malaysia', $res['row']['nationality']);
assert_eq('dob', '1990-05-20', $res['row']['date_of_birth']);
assert_eq('date_added passthrough', $now, $res['row']['date_added']);
// tags: de-duped case-insensitively, first-seen order, JSON array
assert_eq('tags json', '["Redang","VIP"]', $res['row']['tags_json']);

// ---- minimal: name only, everything else null ------------------------------
$min = ghl_manual_lead_prepare(array('first_name' => 'Solo'), 'u2', $now);
assert_eq('minimal ok', true, $min['ok']);
assert_eq('minimal phone null', null, $min['row']['phone']);
assert_eq('minimal email null', null, $min['row']['email']);
assert_eq('minimal tags null', null, $min['row']['tags_json']);
assert_eq('minimal dob null', null, $min['row']['date_of_birth']);
assert_eq('minimal gender null', null, $min['row']['gender']);
// created_by defaults to null when the caller omits it
assert_eq('minimal created_by null', null, $min['row']['created_by']);

// ---- blank lead rejected ---------------------------------------------------
$blank = ghl_manual_lead_prepare(array('tags' => 'x'), 'u3', $now);
assert_eq('blank rejected', false, $blank['ok']);
assert_eq('blank has error', true, count($blank['errors']) === 1);

// ---- bad email rejected ----------------------------------------------------
$bad = ghl_manual_lead_prepare(array('first_name' => 'A', 'email' => 'nope'), 'u4', $now);
assert_eq('bad email rejected', false, $bad['ok']);

// ---- bad dob rejected ------------------------------------------------------
$baddob = ghl_manual_lead_prepare(array('first_name' => 'A', 'date_of_birth' => '20/05/1990'), 'u5', $now);
assert_eq('bad dob rejected', false, $baddob['ok']);
$rolldob = ghl_manual_lead_prepare(array('first_name' => 'A', 'date_of_birth' => '2026-13-40'), 'u6', $now);
assert_eq('rollover dob rejected', false, $rolldob['ok']);

// ---- unknown gender dropped (not an error) ---------------------------------
$g = ghl_manual_lead_prepare(array('first_name' => 'A', 'gender' => 'wizard'), 'u7', $now);
assert_eq('unknown gender ok', true, $g['ok']);
assert_eq('unknown gender null', null, $g['row']['gender']);

// ---- tags-only encode helper ------------------------------------------------
assert_eq('encode empty', null, ghl_manual_lead_encode_tags('   '));
assert_eq('encode split newlines', '["a","b"]', ghl_manual_lead_encode_tags("a\nb\n"));

echo "\nAll GhlManualLeadPrepare assertions passed.\n";
