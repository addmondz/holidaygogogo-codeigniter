<?php
/**
 * Run with: php tests/helpers/GuestContactPhonelessGhlKeyTest.php
 *
 * A GHL lead can have NO stored phone (ghl_contacts.phone empty), so the listing
 * shows "—" and the phone-based "View message log" icon can never appear — even
 * though the contact still owns a WhatsApp history by its GHL contact id.
 *
 * guest_contact_phoneless_ghl_key() picks exactly those rows (GHL type, blank
 * phone) and returns their dedup_key so the icon can fall back to a contact-id
 * lookup. This pins which rows qualify and which are left alone.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require_once __DIR__ . '/../../application/helpers/guest_contact_helper.php';

$assertions = array();

$row = function ($type, $phone, $dedup) {
    return (object) array('Type' => $type, 'ContactNum' => $phone, 'dedup_key' => $dedup);
};

// Phone-less GHL row -> qualifies, returns its dedup_key.
$assertions['phone-less GHL row returns dedup_key'] =
    guest_contact_phoneless_ghl_key($row('GHL', '', 'ghl:21')) === 'ghl:21';

// Whitespace-only phone still counts as phone-less.
$assertions['whitespace phone counts as blank'] =
    guest_contact_phoneless_ghl_key($row('GHL', '   ', 'ghl:66')) === 'ghl:66';

// GHL row WITH a phone -> skipped (already gets the phone icon).
$assertions['GHL row with phone is skipped'] =
    guest_contact_phoneless_ghl_key($row('GHL', '+60 126120032', 'ghl:2')) === '';

// Non-GHL rows never carry a by-contact-id history.
$assertions['booking guest skipped'] =
    guest_contact_phoneless_ghl_key($row('Booking Guest', '', 'p:123456789')) === '';
$assertions['manual lead skipped'] =
    guest_contact_phoneless_ghl_key($row('Manual', '', 'manual:abc')) === '';

// Missing dedup_key -> nothing to key on.
$assertions['GHL row without dedup_key returns empty'] =
    guest_contact_phoneless_ghl_key($row('GHL', '', '')) === '';

// Works on array rows too (defensive).
$assertions['array row supported'] =
    guest_contact_phoneless_ghl_key(array('Type' => 'GHL', 'ContactNum' => '', 'dedup_key' => 'ghl:9')) === 'ghl:9';

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
