<?php
/**
 * Run with: php tests/helpers/AltNameFieldTest.php
 *
 * Covers the pure validator behind the "Alt Name" inline field edit on the
 * Guest/Customer dashboard (Guests::Update_Field, field 'altname') from
 * application/helpers/guest_contact_helper.php:
 *
 *   guest_field_validate_altname() — Alt Name (optional, length-capped to 255)
 *
 * Alt Name is a customer-level attribute (customer.AltName). The DB write is
 * a plain UPDATE customer SET AltName=? WHERE CustomerID=? and is covered by
 * manual verification, mirroring GuestFieldUpdateTest.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require_once __DIR__ . '/../../application/helpers/guest_contact_helper.php';

$assertions = array();

$assertions['altname: empty allowed (clears)']  = guest_field_validate_altname('')['ok'] === true;
$assertions['altname: empty value is ""']       = guest_field_validate_altname('')['value'] === '';
$assertions['altname: whitespace-only clears']  = guest_field_validate_altname("   \t")['value'] === '';
$assertions['altname: trims surrounding space'] = guest_field_validate_altname('  Johnny  ')['value'] === 'Johnny';
$assertions['altname: unicode allowed']         = guest_field_validate_altname("陈小明")['ok'] === true;
$assertions['altname: apostrophe/dot allowed']  = guest_field_validate_altname("O'Brien Jr.")['ok'] === true;
$assertions['altname: 255 chars ok']            = guest_field_validate_altname(str_repeat('a', 255))['ok'] === true;
$assertions['altname: 256 chars rejected']      = guest_field_validate_altname(str_repeat('a', 256))['ok'] === false;

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
