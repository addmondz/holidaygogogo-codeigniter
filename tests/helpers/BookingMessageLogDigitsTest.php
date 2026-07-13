<?php
/**
 * Run with: php tests/helpers/BookingMessageLogDigitsTest.php
 *
 * Regression for the missing "View message log" icon on the Booking listing.
 *
 * GHL stores WhatsApp numbers in E.164 with the national trunk "0" dropped
 * (e.g. "+601111200223"). The Booking listing used to build its match key by
 * simply concatenating CountryCode . Mobile WITHOUT dropping that trunk "0",
 * producing "6001111200223" — which never equals the stored "601111200223",
 * so the chat-history icon stayed hidden even when a conversation existed
 * (real case: booking BC-2607-0111, customer LING YEE SIEW).
 *
 * The fix is to build the booking's wa-digits with guest_contact_wa_digits()
 * (same as the Guest List), which strips the trunk "0". This test pins that
 * the corrected digits match a stored E.164 conversation and the old digits
 * do not.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require_once __DIR__ . '/../../application/helpers/guest_contact_helper.php';
require_once __DIR__ . '/../../application/helpers/ghl_message_log_helper.php';

$assertions = array();

// The stored GHL conversation for this contact (E.164, trunk 0 dropped).
$stored = array(
    array('from_number' => '+601111200223', 'to_number' => '+60 10-295 6786'),
    array('from_number' => '+60 10-295 6786', 'to_number' => '+601111200223'),
);

// Booking row as stored: MY calling code + a mobile WITH the national trunk 0.
$countryCode = '+60';
$mobile      = '01111200223';

// Old, buggy digit key: concat + strip non-digits, KEEPS the trunk 0.
$oldDigits = preg_replace('/\D+/', '', $countryCode . $mobile);
$assertions['old digits keep the trunk 0']       = $oldDigits === '6001111200223';
$assertions['old digits do NOT match stored log'] =
    ghl_message_log_match_phones($stored, array($oldDigits)) === array();

// New digit key via the shared helper: drops the trunk 0 -> true E.164.
$newDigits = guest_contact_wa_digits($countryCode, $mobile);
$assertions['new digits drop the trunk 0']        = $newDigits === '601111200223';
$assertions['new digits match the stored log']    =
    ghl_message_log_match_phones($stored, array($newDigits)) === array('601111200223');

// A number already free of a trunk 0 (e.g. SG) still matches both ways.
$sgStored = array(array('from_number' => '+6591178102', 'to_number' => '+601000000'));
$sgDigits = guest_contact_wa_digits('+65', '91178102');
$assertions['SG number unaffected, still matches'] =
    ghl_message_log_match_phones($sgStored, array($sgDigits)) === array('6591178102');

// A contact with no stored conversation still shows nothing.
$assertions['no conversation => no match'] =
    ghl_message_log_match_phones($stored, array(guest_contact_wa_digits('+60', '0169999999'))) === array();

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
