<?php
/**
 * Run with: php tests/helpers/SettingModuleAccessTest.php
 *
 * Locks admin_can_access_setting_module(): the per-admin AccessControl flag
 * (VPR/VC/VGL) OVERRIDES the role-level block for Product / Customer / Guest
 * List, while historical base roles keep access with no flag. Guest List also
 * stays hard-gated by the global show_guest_list feature flag.
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

// ---- CUSTOMER: base roles 10/30/40 allowed; 20/25/45 need VC ----------------
assert_eq('customer: owner(10) no flag',   true,  admin_can_access_setting_module('customer', '10', $none));
assert_eq('customer: op(40) no flag',      true,  admin_can_access_setting_module('customer', '40', $none));
assert_eq('customer: op-lead(45) no flag', false, admin_can_access_setting_module('customer', '45', $none));
assert_eq('customer: sales(20) no flag',   false, admin_can_access_setting_module('customer', '20', $none));
assert_eq('customer: op-lead(45) with VC', true,  admin_can_access_setting_module('customer', '45', array('VC')));
assert_eq('customer: sales(20) with VC',   true,  admin_can_access_setting_module('customer', '20', array('VC')));

// ---- GUESTS: base roles 10/30/40; 20/25/45 need VGL; global flag hard-gates -
assert_eq('guests: op(40) flag on',        true,  admin_can_access_setting_module('guests', '40', $none, true));
assert_eq('guests: op-lead(45) no flag',   false, admin_can_access_setting_module('guests', '45', $none, true));
assert_eq('guests: op-lead(45) with VGL',  true,  admin_can_access_setting_module('guests', '45', array('VGL'), true));
assert_eq('guests: sales(20) with VGL',    true,  admin_can_access_setting_module('guests', '20', array('VGL'), true));
// Global feature off => nobody, not even owner or a VGL holder.
assert_eq('guests: owner(10) feature off', false, admin_can_access_setting_module('guests', '10', $none, false));
assert_eq('guests: VGL holder feature off',false, admin_can_access_setting_module('guests', '20', array('VGL'), false));

// ---- MARKETING (60): never a base role; Customer / Guest List need the flag --
// Product stays off (Owner never grants VPR to Marketing in practice), Customer
// and Guest List open only once the Owner ticks VC / VGL — matching "subject to
// my approval, and I can stop access".
assert_eq('product: marketing(60) no flag',  false, admin_can_access_setting_module('product', '60', $none));
assert_eq('customer: marketing(60) no flag', false, admin_can_access_setting_module('customer', '60', $none));
assert_eq('customer: marketing(60) with VC', true,  admin_can_access_setting_module('customer', '60', array('VC')));
assert_eq('guests: marketing(60) no flag',   false, admin_can_access_setting_module('guests', '60', $none, true));
assert_eq('guests: marketing(60) with VGL',  true,  admin_can_access_setting_module('guests', '60', array('VGL'), true));
assert_eq('guests: marketing(60) VGL feat off', false, admin_can_access_setting_module('guests', '60', array('VGL'), false));

// ---- Robustness --------------------------------------------------------------
assert_eq('unknown module blocked',        false, admin_can_access_setting_module('supplier', '10', array('VPR')));
assert_eq('int level accepted',            true,  admin_can_access_setting_module('product', 45, $none));
assert_eq('non-array access_control safe',  false, admin_can_access_setting_module('product', '20', null));

echo "\nAll assertions passed.\n";
