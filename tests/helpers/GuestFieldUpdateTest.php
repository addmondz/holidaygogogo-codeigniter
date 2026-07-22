<?php
/**
 * Run with: php tests/helpers/GuestFieldUpdateTest.php
 *
 * Covers the pure validators behind the Guest List dashboard inline field edits
 * (Guests::Update_Field) from application/helpers/guest_contact_helper.php:
 *
 *   1. guest_field_validate_name()     — First Name (required, length-capped)
 *   2. guest_field_validate_email()    — Email (empty clears; else valid form)
 *   3. guest_field_validate_language() — Language (must be an allowed code)
 *
 * The DB-side updates (guest_list / booking / customer) use MySQL-only
 * REGEXP_REPLACE for leader detection and are covered by manual verification,
 * mirroring GuestContactUpdateTest (which unit-tests only the portable pieces).
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require_once __DIR__ . '/../../application/helpers/guest_contact_helper.php';

$assertions = array();

// 1) validate_name ---------------------------------------------------------
$assertions['name: empty rejected']            = guest_field_validate_name('')['ok'] === false;
$assertions['name: whitespace-only rejected']  = guest_field_validate_name("   \t")['ok'] === false;
$assertions['name: trims surrounding spaces']  = guest_field_validate_name('  John  ')['value'] === 'John';
$assertions['name: unicode allowed']           = guest_field_validate_name("陈大文")['ok'] === true;
$assertions['name: apostrophe/dot allowed']    = guest_field_validate_name("O'Brien Jr.")['ok'] === true;
$assertions['name: 100 chars ok']              = guest_field_validate_name(str_repeat('a', 100))['ok'] === true;
$assertions['name: 101 chars rejected']        = guest_field_validate_name(str_repeat('a', 101))['ok'] === false;

// 2) validate_email --------------------------------------------------------
$assertions['email: empty allowed (clears)']   = guest_field_validate_email('')['ok'] === true;
$assertions['email: empty value is ""']        = guest_field_validate_email('')['value'] === '';
$assertions['email: valid ok']                 = guest_field_validate_email('a@b.com')['ok'] === true;
$assertions['email: trims spaces']             = guest_field_validate_email('  a@b.com ')['value'] === 'a@b.com';
$assertions['email: no @ rejected']            = guest_field_validate_email('not-an-email')['ok'] === false;
$assertions['email: missing domain rejected']  = guest_field_validate_email('a@')['ok'] === false;
$assertions['email: over 255 rejected']        = guest_field_validate_email(str_repeat('a', 250) . '@b.com')['ok'] === false;

// 3) validate_language -----------------------------------------------------
$allowed = array('CN', 'EN', 'ML');
$assertions['lang: allowed code ok']           = guest_field_validate_language('EN', $allowed)['ok'] === true;
$assertions['lang: trims then matches']        = guest_field_validate_language('  CN ', $allowed)['value'] === 'CN';
$assertions['lang: empty rejected']            = guest_field_validate_language('', $allowed)['ok'] === false;
$assertions['lang: unknown code rejected']     = guest_field_validate_language('FR', $allowed)['ok'] === false;
$assertions['lang: case-sensitive (en) reject']= guest_field_validate_language('en', $allowed)['ok'] === false;
$assertions['lang: non-array allowed reject']  = guest_field_validate_language('EN', null)['ok'] === false;

// Report -------------------------------------------------------------------
$failed = 0;
foreach ($assertions as $label => $ok) {
    echo ($ok ? '  PASS  ' : '  FAIL  ') . $label . PHP_EOL;
    if (!$ok) {
        $failed++;
    }
}

echo PHP_EOL . ($failed === 0 ? "All assertions passed.\n" : "$failed assertion(s) failed.\n");
exit($failed === 0 ? 0 : 1);
