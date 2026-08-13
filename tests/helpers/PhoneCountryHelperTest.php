<?php
/**
 * Run with: php tests/helpers/PhoneCountryHelperTest.php
 *
 * Locks the country-code phone helpers shared by the Manual Lead and Customer
 * create/edit forms: combine (picker -> stored "+60 123456789"), split (stored
 * -> picker on edit) and the has-code guard.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}
require __DIR__ . '/../../application/helpers/phone_country_helper.php';

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

// ---- normalize -------------------------------------------------------------
assert_eq('normalize +60', '+60', phone_country_normalize_code('+60'));
assert_eq('normalize 60',  '+60', phone_country_normalize_code('60'));
assert_eq('normalize spaced', '+60', phone_country_normalize_code('  +60 '));
assert_eq('normalize blank', '', phone_country_normalize_code(''));
assert_eq('normalize garbage', '', phone_country_normalize_code('abc'));
assert_eq('normalize too long', '', phone_country_normalize_code('+123456'));

// ---- combine ---------------------------------------------------------------
assert_eq('combine drops trunk 0', '+60 123456789', phone_country_combine('+60', '0123456789'));
assert_eq('combine keeps separators', '+60 12-345 6789', phone_country_combine('+60', '012-345 6789'));
assert_eq('combine code as digits', '+65 91234567', phone_country_combine('65', '91234567'));
assert_eq('combine no code passthru', '0123456789', phone_country_combine('', '0123456789'));
assert_eq('combine no local', '', phone_country_combine('+60', ''));
assert_eq('combine only a zero', '+60', phone_country_combine('+60', '0'));

// ---- split -----------------------------------------------------------------
assert_eq('split canonical', array('code' => '+60', 'local' => '123456789'), phone_country_split('+60 123456789'));
assert_eq('split with separators', array('code' => '+60', 'local' => '12-345 6789'), phone_country_split('+60 12-345 6789'));
assert_eq('split legacy no code', array('code' => '', 'local' => '0123456789'), phone_country_split('0123456789'));
assert_eq('split blank', array('code' => '', 'local' => ''), phone_country_split(''));

// combine -> split round-trips the code (local loses its trunk 0, as intended)
$stored = phone_country_combine('+60', '0169546738');
assert_eq('roundtrip stored', '+60 169546738', $stored);
assert_eq('roundtrip split', array('code' => '+60', 'local' => '169546738'), phone_country_split($stored));

// ---- has_code --------------------------------------------------------------
assert_eq('has_code yes', true,  phone_country_has_code('+60 123456789'));
assert_eq('has_code no', false, phone_country_has_code('0123456789'));
assert_eq('has_code blank', false, phone_country_has_code(''));
assert_eq('has_code bare code', false, phone_country_has_code('+60'));

echo "\nAll PhoneCountryHelper assertions passed.\n";
