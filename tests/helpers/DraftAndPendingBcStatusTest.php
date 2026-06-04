<?php
/**
 * Run with: php tests/helpers/DraftAndPendingBcStatusTest.php
 *
 * Locks the two status changes behind the customer-intake draft refactor:
 *
 *   1. The old PCI ("PENDING CUSTOMER INFO") code is gone and replaced by
 *      SAD ("SAVE AS DRAFT") — the parked-draft anchor — ordered before PBC.
 *   2. A brand-new PB ("PENDING BC") status exists, ordered before PBC, so the
 *      "Save as Pending BC" graduate button has a distinct target.
 *
 * Both BOOKING_STATUS (constants) and get_booking_status_info()
 * (booking_flow_helper) must agree.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require __DIR__ . '/../../application/config/constants.php';
require __DIR__ . '/../../application/helpers/booking_flow_helper.php';

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

// ---- BOOKING_STATUS constant -------------------------------------------------
$statuses = unserialize(BOOKING_STATUS);
assert_eq('BOOKING_STATUS unserialises to array', true, is_array($statuses));

assert_eq("'PCI' key removed",          false, array_key_exists('PCI', $statuses));
assert_eq("'SAD' key present",          true,  array_key_exists('SAD', $statuses));
assert_eq("'SAD' label",                'SAVE AS DRAFT', $statuses['SAD']);
assert_eq("'PB' key present",           true,  array_key_exists('PB', $statuses));
assert_eq("'PB' label",                 'PENDING BC', $statuses['PB']);
assert_eq("'PBC' still present",        true,  array_key_exists('PBC', $statuses));

$keys = array_keys($statuses);
$sad_index = array_search('SAD', $keys, true);
$pb_index  = array_search('PB',  $keys, true);
$pbc_index = array_search('PBC', $keys, true);
assert_eq('SAD ordered before PBC', true, $sad_index !== false && $pbc_index !== false && $sad_index < $pbc_index);
assert_eq('PB ordered before PBC',  true, $pb_index  !== false && $pbc_index !== false && $pb_index  < $pbc_index);

// ---- get_booking_status_info() colours + texts ------------------------------
$info = get_booking_status_info();
assert_eq("info has 'colors'", true, isset($info['colors']) && is_array($info['colors']));
assert_eq("info has 'texts'",  true, isset($info['texts'])  && is_array($info['texts']));

assert_eq("SAD text = SAVE AS DRAFT", 'SAVE AS DRAFT', isset($info['texts']['SAD']) ? $info['texts']['SAD'] : null);
assert_eq("PB text = PENDING BC",     'PENDING BC',    isset($info['texts']['PB'])  ? $info['texts']['PB']  : null);
assert_eq("PCI text removed",         false, array_key_exists('PCI', $info['texts']));

assert_eq("SAD colour present + non-empty", true, !empty($info['colors']['SAD']));
assert_eq("PB colour present + non-empty",  true, !empty($info['colors']['PB']));
// PB must be visually distinct from PBC's gold so the two buttons read apart.
assert_eq("PB colour differs from PBC", true,
    isset($info['colors']['PB'], $info['colors']['PBC']) && $info['colors']['PB'] !== $info['colors']['PBC']);

echo "\nAll assertions passed.\n";
