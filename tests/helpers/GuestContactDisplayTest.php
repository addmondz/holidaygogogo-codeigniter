<?php
/**
 * Run with: php tests/helpers/GuestContactDisplayTest.php
 *
 * Covers the pure logic that renders a Guest List contact number WITH its
 * international calling code (Guests dashboard), from
 * application/helpers/guest_contact_helper.php:
 *
 *   1. guest_contact_format_display() — human-readable "+60 169546738"
 *   2. guest_contact_wa_digits()      — digits-only "60169546738" for wa.me
 *
 * Booking guests store a local Mobile (e.g. "0169546738") plus a separate
 * CountryCodeID -> country_code.CountryCode ("+60"). GHL leads already store a
 * full E.164 phone ("+601154282168") and so carry an empty calling code, which
 * both helpers must pass through untouched.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require_once __DIR__ . '/../../application/helpers/guest_contact_helper.php';

$assertions = array();

// 1) format_display --------------------------------------------------------
$assertions['display MY drops trunk 0']            = guest_contact_format_display('+60', '0169546738') === '+60 169546738';
$assertions['display trims surrounding space']     = guest_contact_format_display('+60', ' 179510706') === '+60 179510706';
$assertions['display keeps formatting chars']      = guest_contact_format_display('+1', '(514) 969-4918') === '+1 (514) 969-4918';
$assertions['display SG number (no trunk 0)']      = guest_contact_format_display('+65', '91178102') === '+65 91178102';
$assertions['display empty code -> passthrough']   = guest_contact_format_display('', '+601154282168') === '+601154282168';
$assertions['display empty local -> empty']        = guest_contact_format_display('+60', '') === '';
$assertions['display lone 0 local -> code only']   = guest_contact_format_display('+60', '0') === '+60';

// 2) wa_digits -------------------------------------------------------------
$assertions['wa MY drops trunk 0, adds code']      = guest_contact_wa_digits('+60', '0169546738') === '60169546738';
$assertions['wa strips formatting + adds code']    = guest_contact_wa_digits('+1', '(514) 969-4918') === '15149694918';
$assertions['wa SG number']                        = guest_contact_wa_digits('+65', '91178102') === '6591178102';
$assertions['wa empty code -> local digits only']  = guest_contact_wa_digits('', '+601154282168') === '601154282168';
$assertions['wa empty local -> empty']             = guest_contact_wa_digits('+60', '') === '';

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
