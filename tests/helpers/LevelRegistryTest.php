<?php
/**
 * Run with: php tests/helpers/LevelRegistryTest.php
 *
 * Guards the admin role registry (the LEVEL constant in
 * application/config/constants.php). In particular it pins the OP TEAM LEAD
 * role (level 45) introduced for OP-side checklist team leads, alongside the
 * pre-existing roles, so an accidental edit to the serialized array is caught.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

// constants.php only define()s things; safe to include directly in isolation.
require_once __DIR__ . '/../../application/config/constants.php';

$levels = unserialize(LEVEL);

$expected = array(
    10 => 'OWNER',
    20 => 'SALES AGENT',
    25 => 'TEAM LEAD',
    30 => 'FINANCE',
    40 => 'OP',
    45 => 'OP TEAM LEAD',
    50 => 'TC',
);

$assertions = array();

$assertions['LEVEL unserializes to an array'] = is_array($levels);

foreach ($expected as $key => $label) {
    $assertions["LEVEL[$key] === '$label'"] =
        isset($levels[$key]) && $levels[$key] === $label;
}

// No stray/removed entries
$assertions['LEVEL has exactly the expected keys'] =
    is_array($levels)
    && count(array_diff_key($levels, $expected)) === 0
    && count(array_diff_key($expected, $levels)) === 0;

$failed = 0;
foreach ($assertions as $label => $ok) {
    echo ($ok ? '  PASS  ' : '  FAIL  ') . $label . PHP_EOL;
    if (!$ok) {
        $failed++;
    }
}

echo PHP_EOL . ($failed === 0 ? "All assertions passed.\n" : "$failed assertion(s) failed.\n");
exit($failed === 0 ? 0 : 1);
