<?php
/**
 * Run with: php tests/helpers/CostingCategorySlugTest.php
 *
 * Locks costing_category_slug() — the pure name->code slug used when adding a
 * dynamic costing category. No DB, so it runs on plain PHP.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require_once __DIR__ . '/../../application/helpers/costing_calc_helper.php';

$assertions = [];

$assertions['basic: lowercases + underscores'] = (costing_category_slug('Land Transport') === 'land_transport');
$assertions['basic: trims punctuation runs']   = (costing_category_slug('  Visa / Permit!!  ') === 'visa_permit');
$assertions['basic: keeps digits']             = (costing_category_slug('4 Star Hotel') === '4_star_hotel');
$assertions['basic: matches legacy codes']     = (costing_category_slug('Tour Leader') === 'tour_leader');
$assertions['empty: falls back to category']   = (costing_category_slug('') === 'category');
$assertions['empty: symbols only fallback']    = (costing_category_slug('!!!') === 'category');

$assertions['unique: appends _2 on clash'] = (
    costing_category_slug('Flight', ['flight']) === 'flight_2'
);
$assertions['unique: appends _3 when _2 taken'] = (
    costing_category_slug('Flight', ['flight', 'flight_2']) === 'flight_3'
);
$assertions['unique: clash check is case-insensitive'] = (
    costing_category_slug('Other', ['OTHER']) === 'other_2'
);
$assertions['unique: no clash returns plain slug'] = (
    costing_category_slug('Cruise', ['flight', 'other']) === 'cruise'
);

$failed = 0;
foreach ($assertions as $label => $ok) {
    echo ($ok ? '  ok  ' : 'FAIL  ') . $label . PHP_EOL;
    if (!$ok) { $failed++; }
}

echo PHP_EOL . ($failed === 0
    ? 'PASS: all ' . count($assertions) . ' assertions passed.' . PHP_EOL
    : 'FAILURES: ' . $failed . ' of ' . count($assertions) . ' assertions failed.' . PHP_EOL);

exit($failed === 0 ? 0 : 1);
