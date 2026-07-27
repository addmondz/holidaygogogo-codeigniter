<?php
/**
 * Run with: php tests/helpers/BookingDraftHelperTest.php
 *
 * Locks the booking_draft_helper contract used by both the admin booking form
 * (field locking) and the controller (graduate transition + server-side guard):
 *
 *   - is_draft_status()      : only 'SAD' is the parked-draft status.
 *   - draft_editable_fields(): the canonical booking-column whitelist a draft
 *                              save is allowed to write.
 *   - resolve_graduate_status(): maps the posted button value to a valid target
 *                              status ('PB' or 'PBC'), rejecting everything else.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require __DIR__ . '/../../application/helpers/booking_draft_helper.php';

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

// ---- is_draft_status --------------------------------------------------------
assert_eq("'SAD' is draft",  true,  is_draft_status('SAD'));
assert_eq("'PBC' not draft", false, is_draft_status('PBC'));
assert_eq("'PB' not draft",  false, is_draft_status('PB'));
assert_eq("'PCI' not draft (removed)", false, is_draft_status('PCI'));
assert_eq("null not draft",  false, is_draft_status(null));
assert_eq("'' not draft",    false, is_draft_status(''));

// ---- draft_editable_fields --------------------------------------------------
$fields = draft_editable_fields();
assert_eq('returns array', true, is_array($fields));
foreach (array('Customer', 'Mobile', 'CountryCodeID', 'SalesAgent2', 'Source', 'ChatLanguage', 'Destination', 'StartDate', 'EndDate', 'DepositDeadline', 'FullPaymentDeadline', 'BookingFormText', 'ChatSummary') as $expected_key) {
    assert_eq("whitelist contains {$expected_key}", true, in_array($expected_key, $fields, true));
}
// Locked-down fields must NOT be writable in draft mode.
foreach (array('NetTotal', 'Subtotal', 'Status', 'bc_approved', 'Adult', 'Children', 'Infant') as $locked) {
    assert_eq("whitelist excludes {$locked}", false, in_array($locked, $fields, true));
}

// ---- resolve_graduate_status ------------------------------------------------
assert_eq("'PB'  -> PB",  'PB',  resolve_graduate_status('PB'));
assert_eq("'PBC' -> PBC", 'PBC', resolve_graduate_status('PBC'));
assert_eq("'' -> null",       null, resolve_graduate_status(''));
assert_eq("null -> null",     null, resolve_graduate_status(null));
assert_eq("'P' -> null",      null, resolve_graduate_status('P'));
assert_eq("'SAD' -> null",    null, resolve_graduate_status('SAD'));
assert_eq("lowercase 'pbc' -> null", null, resolve_graduate_status('pbc'));
assert_eq("'Y' -> null",      null, resolve_graduate_status('Y'));
// Non-graduate draft save modes never resolve to a status advance.
assert_eq("'approve' -> null", null, resolve_graduate_status('approve'));
assert_eq("'draft' -> null",   null, resolve_graduate_status('draft'));

echo "\nAll assertions passed.\n";
