<?php
/**
 * Run with: php tests/helpers/PciStatusConstantTest.php
 *
 * Verifies that the new PCI ("PENDING CUSTOMER INFO") status is registered in
 * BOOKING_STATUS and ordered before PBC, so it represents a pre-PBC anchor in
 * the booking lifecycle for response-time measurement.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require __DIR__ . '/../../application/config/constants.php';

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

$statuses = unserialize(BOOKING_STATUS);

assert_eq('BOOKING_STATUS unserialises to array', true, is_array($statuses));
assert_eq("'PCI' key present", true, array_key_exists('PCI', $statuses));
assert_eq("'PCI' label",       'PENDING CUSTOMER INFO', $statuses['PCI']);
assert_eq("'PBC' still present", true, array_key_exists('PBC', $statuses));

$keys = array_keys($statuses);
$pci_index = array_search('PCI', $keys, true);
$pbc_index = array_search('PBC', $keys, true);
assert_eq("PCI is ordered before PBC", true, $pci_index !== false && $pbc_index !== false && $pci_index < $pbc_index);

echo "\nAll assertions passed.\n";
