<?php
/**
 * Run with: php tests/helpers/CampaignNormalizeFiltersTest.php
 *
 * Pins Campaign_Model::Normalize_Filters — the pure sanitiser that turns the
 * posted guest-picker filter JSON into a clean array (whitelisted keys, scalar
 * strings, array-of-strings for multi-selects) before it is stored, and returns
 * null when nothing meaningful was applied.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

if (!class_exists('CI_Model')) {
    class CI_Model {}
}

require_once __DIR__ . '/../../application/models/Campaign_Model.php';

$assertions = [];

// -- whitelisted scalar + multi keys kept, unknown keys dropped -------------
$out = Campaign_Model::Normalize_Filters(json_encode([
    'q'           => 'ali',
    'type'        => 'guest',
    'bc_type'     => 'BOOKING CONFIRMATION',
    'destination' => ['3', '7'],
    'gender'      => ['Male'],
    'family_kids' => '1',
    'has_email'   => '1',
    'campaign_mode' => 'exclude',
    'hacker'      => 'DROP TABLE',
]));
$assertions['scalar kept']            = $out['q'] === 'ali' && $out['type'] === 'guest';
$assertions['bc_type kept']           = $out['bc_type'] === 'BOOKING CONFIRMATION';
$assertions['multi kept as array']    = $out['destination'] === ['3', '7'];
$assertions['single multi kept']      = $out['gender'] === ['Male'];
$assertions['checkbox kept']          = $out['family_kids'] === '1';
$assertions['has_email kept']         = $out['has_email'] === '1';
$assertions['mode kept']              = $out['campaign_mode'] === 'exclude';
$assertions['unknown key dropped']    = !array_key_exists('hacker', $out);

// -- scalar passed as array is coerced to first string; array coerced to strings
$coerce = Campaign_Model::Normalize_Filters(json_encode([
    'q'      => ['nested', 'junk'],
    'source' => [1, 2, 3],
]));
$assertions['scalar from array coerced'] = $coerce['q'] === 'nested';
$assertions['multi ints stringified']    = $coerce['source'] === ['1', '2', '3'];

// -- blank/empty values are stripped so they don't count as "applied" -------
$blankish = Campaign_Model::Normalize_Filters(json_encode([
    'q'           => '',
    'type'        => '',
    'destination' => [],
    'gender'      => ['', '  '],
    'source'      => ['5'],
]));
$assertions['empty scalar stripped']   = !array_key_exists('q', $blankish);
$assertions['empty array stripped']    = !array_key_exists('destination', $blankish);
$assertions['blank array items stripped'] = !array_key_exists('gender', $blankish);
$assertions['real value survives']     = $blankish['source'] === ['5'];

// -- nothing meaningful => null ---------------------------------------------
$assertions['all-empty returns null'] = Campaign_Model::Normalize_Filters(json_encode([
    'q' => '', 'destination' => [], 'campaign_mode' => 'include',
])) === null;
$assertions['blank string returns null']   = Campaign_Model::Normalize_Filters('') === null;
$assertions['invalid json returns null']   = Campaign_Model::Normalize_Filters('{not json') === null;
$assertions['null returns null']           = Campaign_Model::Normalize_Filters(null) === null;
$assertions['non-object json returns null'] = Campaign_Model::Normalize_Filters('[1,2,3]') === null;

// -- campaign_mode alone is not "meaningful" but rides along when real filters exist
$withMode = Campaign_Model::Normalize_Filters(json_encode([
    'source'        => ['5'],
    'campaign_mode' => 'exclude',
]));
$assertions['mode included alongside real filter'] = $withMode['campaign_mode'] === 'exclude';

$failed = 0;
foreach ($assertions as $label => $ok) {
    echo ($ok ? '  PASS  ' : '  FAIL  ') . $label . PHP_EOL;
    if (!$ok) { $failed++; }
}

echo PHP_EOL . ($failed === 0 ? "All assertions passed.\n" : "$failed assertion(s) failed.\n");
exit($failed === 0 ? 0 : 1);
