<?php
/**
 * Run with: php tests/helpers/SettingModuleAccessTest.php
 *
 * Locks admin_can_access_setting_module(): the per-admin 'VPR' AccessControl flag
 * OVERRIDES the role-level block for Product, while historical base roles keep
 * access with no flag. Customer and Guest List have MOVED to the "Leads/Customer"
 * tab (leads_customer_access_helper), so they are no longer setting modules here —
 * asking for them now returns FALSE like any unknown module.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require __DIR__ . '/../../application/helpers/setting_module_access_helper.php';

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

$none = array();

// ---- PRODUCT: base roles 10/30/40/45 allowed; 20/25 need VPR ----------------
assert_eq('product: owner(10) no flag',    true,  admin_can_access_setting_module('product', '10', $none));
assert_eq('product: finance(30) no flag',  true,  admin_can_access_setting_module('product', '30', $none));
assert_eq('product: op(40) no flag',       true,  admin_can_access_setting_module('product', '40', $none));
assert_eq('product: op-lead(45) no flag',  true,  admin_can_access_setting_module('product', '45', $none));
assert_eq('product: sales(20) no flag',    false, admin_can_access_setting_module('product', '20', $none));
assert_eq('product: lead(25) no flag',     false, admin_can_access_setting_module('product', '25', $none));
assert_eq('product: sales(20) with VPR',   true,  admin_can_access_setting_module('product', '20', array('VPR')));
assert_eq('product: lead(25) with VPR',    true,  admin_can_access_setting_module('product', '25', array('VPR')));
assert_eq('product: wrong flag no help',   false, admin_can_access_setting_module('product', '20', array('VC')));
assert_eq('product: marketing(60) no flag',false, admin_can_access_setting_module('product', '60', $none));

// ---- CUSTOMER / GUESTS moved to the Leads/Customer tab -----------------------
// No longer setting modules — they resolve to FALSE regardless of role or flag.
assert_eq('customer: owner(10) now off',   false, admin_can_access_setting_module('customer', '10', $none));
assert_eq('customer: VC flag no help',      false, admin_can_access_setting_module('customer', '20', array('VC')));
assert_eq('guests: owner(10) now off',     false, admin_can_access_setting_module('guests', '10', $none, true));
assert_eq('guests: VGL flag no help',       false, admin_can_access_setting_module('guests', '20', array('VGL'), true));

// ---- Robustness --------------------------------------------------------------
assert_eq('unknown module blocked',        false, admin_can_access_setting_module('supplier', '10', array('VPR')));
assert_eq('int level accepted',            true,  admin_can_access_setting_module('product', 45, $none));
assert_eq('non-array access_control safe',  false, admin_can_access_setting_module('product', '20', null));

echo "\nAll assertions passed.\n";
