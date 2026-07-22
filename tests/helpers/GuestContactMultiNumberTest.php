<?php
/**
 * Run with: php tests/helpers/GuestContactMultiNumberTest.php
 *
 * Covers guest_contact_parse_multi() — the pure logic that unpacks a merged
 * Guest List row's phone list (records sharing Name + IC but different phones)
 * into display-ready entries, from application/helpers/guest_contact_helper.php.
 *
 * Packing format: each phone is "callingcode<US>mobile" (US = \x1f) and the
 * units are joined by a record separator \x1e, e.g.
 *   "+60\x1f0169546738\x1e+60\x1f0198887777"
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require_once __DIR__ . '/../../application/helpers/guest_contact_helper.php';

$US = "\x1f"; // unit  separator: calling code <-> mobile
$RS = "\x1e"; // record separator: phone <-> phone

$assertions = array();

// empty / junk ------------------------------------------------------------
$assertions['empty string -> []']        = guest_contact_parse_multi('') === array();
$assertions['only separators -> []']      = guest_contact_parse_multi($RS . $RS) === array();
$assertions['blank mobile dropped']       = guest_contact_parse_multi('+60' . $US) === array();

// single phone ------------------------------------------------------------
$single = guest_contact_parse_multi('+60' . $US . '0169546738');
$assertions['single count 1']             = count($single) === 1;
$assertions['single calling code']        = $single[0]['calling_code'] === '+60';
$assertions['single mobile']              = $single[0]['mobile'] === '0169546738';

// GHL-style full number, empty code --------------------------------------
$ghl = guest_contact_parse_multi($US . '+601154282168');
$assertions['ghl empty code kept']        = $ghl[0]['calling_code'] === '';
$assertions['ghl full number kept']       = $ghl[0]['mobile'] === '+601154282168';

// two DISTINCT numbers ----------------------------------------------------
$two = guest_contact_parse_multi('+60' . $US . '0169546738' . $RS . '+60' . $US . '0198887777');
$assertions['two distinct -> 2']          = count($two) === 2;
$assertions['two keeps order 1']          = $two[0]['mobile'] === '0169546738';
$assertions['two keeps order 2']          = $two[1]['mobile'] === '0198887777';

// identical numbers collapse ---------------------------------------------
$dupe = guest_contact_parse_multi('+60' . $US . '0169546738' . $RS . '+60' . $US . '0169546738');
$assertions['identical collapses -> 1']   = count($dupe) === 1;

// same number, different formatting collapses (same wa digits) -----------
$fmt = guest_contact_parse_multi('+60' . $US . '0169546738' . $RS . '+60' . $US . '016-954 6738');
$assertions['formatting variant -> 1']    = count($fmt) === 1;

// distinct code but same local digits are still two entries --------------
$codes = guest_contact_parse_multi('+60' . $US . '169546738' . $RS . '+65' . $US . '169546738');
$assertions['diff code -> 2']             = count($codes) === 2;

// blanks between real numbers are skipped, reals still parse -------------
$mixed = guest_contact_parse_multi('+60' . $US . $RS . '+60' . $US . '0169546738');
$assertions['blank + real -> 1']          = count($mixed) === 1 && $mixed[0]['mobile'] === '0169546738';

// ---- report -------------------------------------------------------------
$failed = 0;
foreach ($assertions as $label => $ok) {
    if (!$ok) {
        $failed++;
        echo "FAIL: {$label}\n";
    }
}
if ($failed === 0) {
    echo 'OK: all ' . count($assertions) . " assertions passed\n";
    exit(0);
}
echo "{$failed} of " . count($assertions) . " assertions FAILED\n";
exit(1);
