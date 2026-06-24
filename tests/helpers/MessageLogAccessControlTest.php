<?php
/**
 * Run with: php tests/helpers/MessageLogAccessControlTest.php
 *
 * Locks the access-control contract for the Message Log report. Message Log
 * exposes raw customer conversation logs, so it gets its own 'ML' code instead
 * of riding on the shared 'VR' (VIEW REPORT) grant. Rules:
 *   - 'ML' => 'MESSAGE LOG' exists in ACCESS_CONTROL,
 *   - 'VR' still exists: Message Log is nested under the Report menu, so a user
 *     needs VR (to reach the menu) AND ML (to open the page),
 *   - 'ML' is ordered immediately after 'VR' so admin.php renders it inside the
 *     REPORT MODULE optgroup (that optgroup opens on the VR key and closes on
 *     the FV/FAQ key).
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require __DIR__ . '/../../application/config/constants.php';

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
        return true;
    }
    echo "  FAIL  {$label}: expected " . var_export($expected, true)
       . ", got " . var_export($actual, true) . "\n";
    return false;
}

$ok = true;
$codes = unserialize(ACCESS_CONTROL);

$ok = assert_eq("ML label", 'MESSAGE LOG', isset($codes['ML']) ? $codes['ML'] : null) && $ok;
$ok = assert_eq("VR still present", 'VIEW REPORT', isset($codes['VR']) ? $codes['VR'] : null) && $ok;

$keys = array_keys($codes);
$vr = array_search('VR', $keys, true);
$ml = array_search('ML', $keys, true);
$ok = assert_eq("ML directly after VR (stays in REPORT optgroup)", true, $vr !== false && $ml === $vr + 1) && $ok;
$ok = assert_eq("ML before FV (FAQ optgroup boundary)", true, $ml !== false && $ml < array_search('FV', $keys, true)) && $ok;

echo $ok ? "ALL PASS\n" : "FAILURES\n";
exit($ok ? 0 : 1);
