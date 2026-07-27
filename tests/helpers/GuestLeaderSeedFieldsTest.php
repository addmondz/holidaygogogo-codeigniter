<?php
/**
 * Run with: php tests/helpers/GuestLeaderSeedFieldsTest.php
 *
 * Locks the "auto-propagate customer info into the Guest List" mapping:
 * when a booking is created, the group leader (first ADULT guest row) is
 * seeded from the booking customer so the Guest List form opens pre-filled.
 *
 *  - Name and Email are stored UPPERCASE (matches Guest_List_Model casing).
 *  - Mobile is kept verbatim (may carry country-code prefix / formatting).
 *  - Empty booking fields are dropped, never seeded as blank strings, so a
 *    blank booking value can't overwrite a guest_list column.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}
require __DIR__ . '/../../application/helpers/guest_leader_helper.php';

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

// ---- full customer -> all four columns seeded, names/email uppercased -------
$full = guest_leader_seed_fields('John Tan', '+60123456789', 7, 'john@example.com');
assert_eq('name uppercased',   'JOHN TAN',              $full['Name']);
assert_eq('mobile verbatim',   '+60123456789',          $full['Mobile']);
assert_eq('country code kept', 7,                       $full['CountryCodeID']);
assert_eq('email uppercased',  'JOHN@EXAMPLE.COM',      $full['Email']);

// ---- email optional: no email -> no Email key -------------------------------
$noEmail = guest_leader_seed_fields('Mary Lim', '0139998888', 7, '');
assert_eq('no email key when blank', false, array_key_exists('Email', $noEmail));
assert_eq('name still seeded',       'MARY LIM', $noEmail['Name']);

// ---- blank fields are dropped, not seeded as empty strings ------------------
$blank = guest_leader_seed_fields('   ', '   ', 0, '   ');
assert_eq('blank name dropped',    false, array_key_exists('Name', $blank));
assert_eq('blank mobile dropped',  false, array_key_exists('Mobile', $blank));
assert_eq('zero country dropped',  false, array_key_exists('CountryCodeID', $blank));
assert_eq('blank email dropped',   false, array_key_exists('Email', $blank));
assert_eq('all-blank -> empty',    array(), $blank);

// ---- whitespace is trimmed before uppercasing -------------------------------
$trim = guest_leader_seed_fields('  Ali  ', '  012 345  ', '9', '  a@b.co  ');
assert_eq('name trimmed+upper',  'ALI',      $trim['Name']);
assert_eq('mobile trimmed',      '012 345',  $trim['Mobile']);
assert_eq('email trimmed+upper', 'A@B.CO',   $trim['Email']);
assert_eq('string country kept', '9',        $trim['CountryCodeID']);

echo "\nAll GuestLeaderSeedFields tests passed.\n";
