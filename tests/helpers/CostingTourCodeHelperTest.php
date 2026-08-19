<?php
/**
 * Run with: php tests/helpers/CostingTourCodeHelperTest.php
 *
 * Locks the pure costing tour-code generator (no DB / no HTTP / no date funcs).
 * Format: qu-YYMM-XXXX, sequence restarts each YYMM period.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require_once __DIR__ . '/../../application/helpers/costing_tour_code_helper.php';

$assertions = [];

/* 1) FORMAT ------------------------------------------------------- */
$assertions['format: pads to 4 digits'] = (costing_tour_code_format('2608', 3) === 'qu-2608-0003');
$assertions['format: keeps 4-digit seq'] = (costing_tour_code_format('2608', 1234) === 'qu-2608-1234');
$assertions['format: 5-digit seq overflows pad'] = (costing_tour_code_format('2608', 12345) === 'qu-2608-12345');
$assertions['format: negative -> zero'] = (costing_tour_code_format('2608', -5) === 'qu-2608-0000');
$assertions['prefix: qu'] = (costing_tour_code_prefix() === 'qu');

/* 2) SEQUENCE PARSE ---------------------------------------------- */
$assertions['seq: reads its own format'] = (costing_tour_code_sequence('qu-2608-0007', '2608') === 7);
$assertions['seq: other period -> 0'] = (costing_tour_code_sequence('qu-2607-0007', '2608') === 0);
$assertions['seq: blank -> 0'] = (costing_tour_code_sequence('', '2608') === 0);
$assertions['seq: hand-typed non-numeric tail -> 0'] = (costing_tour_code_sequence('qu-2608-ABC', '2608') === 0);
$assertions['seq: unrelated code -> 0'] = (costing_tour_code_sequence('TOUR-123', '2608') === 0);
$assertions['seq: case-insensitive prefix'] = (costing_tour_code_sequence('QU-2608-0009', '2608') === 9);
$assertions['seq: trims whitespace'] = (costing_tour_code_sequence('  qu-2608-0004  ', '2608') === 4);

/* 3) NEXT --------------------------------------------------------- */
$assertions['next: empty -> 0001'] = (costing_tour_code_next([], '2608') === 'qu-2608-0001');
$assertions['next: max+1'] = (costing_tour_code_next(['qu-2608-0001', 'qu-2608-0005', 'qu-2608-0003'], '2608') === 'qu-2608-0006');
$assertions['next: ignores other periods'] = (costing_tour_code_next(['qu-2607-0009', 'qu-2608-0002'], '2608') === 'qu-2608-0003');
$assertions['next: ignores hand-typed'] = (costing_tour_code_next(['CUSTOM', 'qu-2608-0002'], '2608') === 'qu-2608-0003');
$assertions['next: all foreign -> 0001'] = (costing_tour_code_next(['qu-2607-0009', 'CUSTOM'], '2608') === 'qu-2608-0001');

/* 4) SOURCE CONTRACT --------------------------------------------- */
$helper = @file_get_contents(__DIR__ . '/../../application/helpers/costing_tour_code_helper.php');
foreach (['costing_tour_code_prefix', 'costing_tour_code_format', 'costing_tour_code_sequence', 'costing_tour_code_next'] as $fn) {
    $assertions["helper: defines {$fn}()"] = (bool) preg_match('/function\s+' . preg_quote($fn, '/') . '\s*\(/', (string) $helper);
}

$model = @file_get_contents(__DIR__ . '/../../application/models/Costing_Model.php');
$assertions['model: loads the tour-code helper'] = (strpos((string) $model, 'costing_tour_code') !== false);

/* ----------------------------------------------------------------- */
$fail = 0;
foreach ($assertions as $label => $ok) {
    echo ($ok ? 'PASS  ' : 'FAIL  ') . $label . "\n";
    if (!$ok) {
        $fail++;
    }
}
echo "\n" . (count($assertions) - $fail) . '/' . count($assertions) . " passed\n";
exit($fail === 0 ? 0 : 1);
