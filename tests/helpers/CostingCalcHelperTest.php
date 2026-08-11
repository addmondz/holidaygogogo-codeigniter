<?php
/**
 * Run with: php tests/helpers/CostingCalcHelperTest.php
 *
 * Locks the pure costing/quotation math (no DB / no HTTP / no date funcs, so it
 * runs on plain PHP + SQLite). These functions turn cost rows priced in any
 * currency + a frozen per-costing rate snapshot into MYR cost, then apply a
 * markup-on-cost margin to show selling + profit. No manual selling price.
 *
 *   selling = cost x (1 + margin%/100)
 *   profit  = cost x (margin%/100)
 *
 * Three parts:
 *   1. Pure math    : convert, sum-by-category, markup, snapshot build, normalize.
 *   2. End-to-end   : rows + rate_map + margin -> selling/profit hand-checked.
 *   3. Source contract: helper defines the functions; Costing wires it in.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require_once __DIR__ . '/../../application/helpers/costing_calc_helper.php';

$assertions = [];
$approx = function ($a, $b) { return abs((float) $a - (float) $b) < 1e-6; };

/* ------------------------------------------------------------------ *
 * 0) CATEGORIES                                                       *
 * ------------------------------------------------------------------ */

$cats = costing_categories();
$assertions['cats: 5 fixed categories'] = (count($cats) === 5);
$assertions['cats: keys are the enum values'] = (
    isset($cats['flight'], $cats['accommodation'], $cats['other'], $cats['tour_leader'], $cats['miscellaneous'])
);
$assertions['cats: label is human readable'] = ($cats['tour_leader'] === 'Tour Leader');

/* ------------------------------------------------------------------ *
 * 1) LINE -> MYR                                                      *
 * ------------------------------------------------------------------ */

$rate_map = ['MYR' => 1.0, 'USD' => 4.5, 'SGD' => 3.5];

$assertions['line: USD 100 @4.5 -> 450'] =
    $approx(costing_line_to_myr(['currency_code' => 'USD', 'unit_cost' => 100, 'quantity' => 1], $rate_map), 450.00);
$assertions['line: MYR uses rate 1'] =
    $approx(costing_line_to_myr(['currency_code' => 'MYR', 'unit_cost' => 250, 'quantity' => 1], $rate_map), 250.00);
$assertions['line: quantity multiplies'] =
    $approx(costing_line_to_myr(['currency_code' => 'USD', 'unit_cost' => 100, 'quantity' => 2], $rate_map), 900.00);
$assertions['line: missing rate -> 0 (no crash)'] =
    $approx(costing_line_to_myr(['currency_code' => 'THB', 'unit_cost' => 100, 'quantity' => 1], $rate_map), 0.00);
$assertions['line: rounds to 2dp'] =
    $approx(costing_line_to_myr(['currency_code' => 'USD', 'unit_cost' => 10.005, 'quantity' => 1], ['USD' => 1.0]), 10.01);

/* ------------------------------------------------------------------ *
 * 2) SUM BY CATEGORY                                                  *
 * ------------------------------------------------------------------ */

$lines = [
    ['category' => 'flight',        'currency_code' => 'USD', 'unit_cost' => 100, 'quantity' => 1], // 450
    ['category' => 'flight',        'currency_code' => 'MYR', 'unit_cost' => 50,  'quantity' => 1], // 50
    ['category' => 'accommodation', 'currency_code' => 'SGD', 'unit_cost' => 100, 'quantity' => 1], // 350
    ['category' => 'tour_leader',   'currency_code' => 'MYR', 'unit_cost' => 200, 'quantity' => 1], // 200
];
$sum = costing_sum_by_category($lines, $rate_map);
$assertions['sum: flight = 500']          = $approx($sum['flight'], 500.00);
$assertions['sum: accommodation = 350']   = $approx($sum['accommodation'], 350.00);
$assertions['sum: tour_leader = 200']     = $approx($sum['tour_leader'], 200.00);
$assertions['sum: other = 0']             = $approx($sum['other'], 0.00);
$assertions['sum: miscellaneous = 0']     = $approx($sum['miscellaneous'], 0.00);
$assertions['sum: total = 1050']          = $approx($sum['total'], 1050.00);
$assertions['sum: empty -> total 0']      = $approx(costing_sum_by_category([], $rate_map)['total'], 0.00);
$assertions['sum: always has 5 cats + total'] = (count(costing_sum_by_category([], $rate_map)) === 6);

/* ------------------------------------------------------------------ *
 * 3) MARKUP ON COST                                                   *
 * ------------------------------------------------------------------ */

$m = costing_apply_markup(1000, 20);
$assertions['markup: 20% on 1000 -> selling 1200'] = $approx($m['selling'], 1200.00);
$assertions['markup: 20% on 1000 -> profit 200']   = $approx($m['profit'], 200.00);
$assertions['markup: keeps cost']                  = $approx($m['cost'], 1000.00);

$m0 = costing_apply_markup(1000, 0);
$assertions['markup: 0% -> selling == cost'] = $approx($m0['selling'], 1000.00);
$assertions['markup: 0% -> profit 0']        = $approx($m0['profit'], 0.00);

$m100 = costing_apply_markup(1000, 100);
$assertions['markup: 100% -> selling 2x'] = $approx($m100['selling'], 2000.00);
$assertions['markup: 100% -> profit == cost'] = $approx($m100['profit'], 1000.00);

$mf = costing_apply_markup(1000, 12.5);
$assertions['markup: 12.5% -> selling 1125'] = $approx($mf['selling'], 1125.00);
$assertions['markup: 12.5% -> profit 125']   = $approx($mf['profit'], 125.00);

$mr = costing_apply_markup(333.33, 15);
$assertions['markup: rounds selling to 2dp'] = $approx($mr['selling'], 383.33);
$assertions['markup: negative margin clamped to 0'] = $approx(costing_apply_markup(1000, -5)['selling'], 1000.00);

/* ------------------------------------------------------------------ *
 * 4) SNAPSHOT ROWS                                                    *
 * ------------------------------------------------------------------ */

// currency_lookup maps code -> currency_id (from the master); prefill maps code -> rate.
$currency_lookup = ['MYR' => 1, 'USD' => 2, 'SGD' => 3, 'THB' => 4];
$prefill = ['USD' => 4.7, 'MYR' => 999]; // MYR must be forced to 1 regardless
$snap_lines = [
    ['currency_code' => 'USD'],
    ['currency_code' => 'USD'],
    ['currency_code' => 'THB'],
    ['currency_code' => 'MYR'],
];
$rows = costing_build_snapshot_rows($snap_lines, $currency_lookup, $prefill);
$by_code = [];
foreach ($rows as $r) { $by_code[$r['currency_code']] = $r; }

$assertions['snapshot: one row per distinct currency'] = (count($rows) === 3);
$assertions['snapshot: USD prefilled from feed']       = $approx($by_code['USD']['rate_to_myr'], 4.7);
$assertions['snapshot: MYR forced to 1.0']             = $approx($by_code['MYR']['rate_to_myr'], 1.0);
$assertions['snapshot: unknown -> 0 (manual entry)']   = $approx($by_code['THB']['rate_to_myr'], 0.0);
$assertions['snapshot: carries currency_id']           = ((int) $by_code['USD']['currency_id'] === 2);
$assertions['snapshot: carries empty remark']          = (array_key_exists('remark', $by_code['USD']) && $by_code['USD']['remark'] === '');

/* ------------------------------------------------------------------ *
 * 5) NORMALIZE RATE                                                   *
 * ------------------------------------------------------------------ */

$assertions['normalize: MYR always 1.0']       = $approx(costing_normalize_rate('MYR', 999), 1.0);
$assertions['normalize: negative -> 0']        = $approx(costing_normalize_rate('USD', -3), 0.0);
$assertions['normalize: passes positive rate'] = $approx(costing_normalize_rate('usd', 4.5), 4.5);
$assertions['normalize: clamps to 8dp']        = $approx(costing_normalize_rate('USD', 4.123456789), 4.12345679);

/* ------------------------------------------------------------------ *
 * 6) END-TO-END                                                      *
 * ------------------------------------------------------------------ */

// Rows: USD 200 (flight) + SGD 100 (accommodation) + MYR 50 (misc), 10% markup.
// Cost = 200*4.5 + 100*3.5 + 50 = 900 + 350 + 50 = 1300; selling = 1430; profit = 130.
$e_lines = [
    ['category' => 'flight',        'currency_code' => 'USD', 'unit_cost' => 200, 'quantity' => 1],
    ['category' => 'accommodation', 'currency_code' => 'SGD', 'unit_cost' => 100, 'quantity' => 1],
    ['category' => 'miscellaneous', 'currency_code' => 'MYR', 'unit_cost' => 50,  'quantity' => 1],
];
$e_sum = costing_sum_by_category($e_lines, $rate_map);
$e_fin = costing_apply_markup($e_sum['total'], 10);
$assertions['e2e: cost 1300']    = $approx($e_fin['cost'], 1300.00);
$assertions['e2e: selling 1430'] = $approx($e_fin['selling'], 1430.00);
$assertions['e2e: profit 130']   = $approx($e_fin['profit'], 130.00);

/* ------------------------------------------------------------------ *
 * 7) SOURCE CONTRACT                                                  *
 * ------------------------------------------------------------------ */

$helper = @file_get_contents(__DIR__ . '/../../application/helpers/costing_calc_helper.php');
foreach (['costing_categories', 'costing_line_to_myr', 'costing_sum_by_category', 'costing_apply_markup', 'costing_build_snapshot_rows', 'costing_normalize_rate'] as $fn) {
    $assertions["helper: defines {$fn}()"] = (bool) preg_match('/function\s+' . preg_quote($fn, '/') . '\s*\(/', (string) $helper);
}

$model = @file_get_contents(__DIR__ . '/../../application/models/Costing_Model.php');
$assertions['model: loads the calc helper'] = (strpos((string) $model, 'costing_calc') !== false);

/* ------------------------------------------------------------------ */

$fail = 0;
foreach ($assertions as $label => $ok) {
    echo ($ok ? 'PASS  ' : 'FAIL  ') . $label . "\n";
    if (!$ok) {
        $fail++;
    }
}
echo "\n" . (count($assertions) - $fail) . '/' . count($assertions) . " passed\n";
exit($fail === 0 ? 0 : 1);
