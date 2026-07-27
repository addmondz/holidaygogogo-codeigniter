<?php
/**
 * Run with: php tests/helpers/NotificationFoldedCountsTest.php
 *
 * Verifies that fold_category_counts() correctly aggregates raw [type=>count]
 * rows into the user-facing category buckets, including pair-folding for
 * supplier_reminder_* and payout_overdue_*, and that the total is the sum.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require_once __DIR__ . '/../../application/helpers/notification_category_helper.php';

$assertions = [];

// --- empty input ---------------------------------------------------------

$empty = fold_category_counts([]);
$assertions['empty -> total 0'] = $empty['total'] === 0;
$assertions['empty -> remark 0'] = $empty['remark'] === 0;
$assertions['empty -> supplier_reminder 0'] = $empty['supplier_reminder'] === 0;
$assertions['empty -> payout_overdue 0'] = $empty['payout_overdue'] === 0;

// --- single-type counts --------------------------------------------------

$single = fold_category_counts(['remark' => 5]);
$assertions['single remark=5 -> remark 5']  = $single['remark'] === 5;
$assertions['single remark=5 -> total 5']   = $single['total'] === 5;
$assertions['single remark=5 -> bu 0']      = $single['booking_updated'] === 0;

// --- supplier_reminder fold ---------------------------------------------

$sr = fold_category_counts([
    'supplier_reminder_full'    => 3,
    'supplier_reminder_deposit' => 2,
]);
$assertions['supplier full+deposit -> supplier_reminder 5'] =
    $sr['supplier_reminder'] === 5;
$assertions['supplier full+deposit -> total 5'] = $sr['total'] === 5;
$assertions['supplier full+deposit -> payout_overdue 0'] =
    $sr['payout_overdue'] === 0;

// --- payout_overdue fold -------------------------------------------------

$po = fold_category_counts([
    'payout_overdue_full'    => 4,
    'payout_overdue_deposit' => 1,
]);
$assertions['payout full+deposit -> payout_overdue 5'] =
    $po['payout_overdue'] === 5;
$assertions['payout full+deposit -> total 5'] = $po['total'] === 5;

// --- mixed payload -------------------------------------------------------

$mixed = fold_category_counts([
    'remark'                    => 3,
    'booking_updated'           => 2,
    'review_submitted'          => 1,
    'supplier_reminder_full'    => 4,
    'supplier_reminder_deposit' => 6,
    'payout_overdue_full'       => 7,
    'payout_overdue_deposit'    => 0,
    'product_no_checklist'      => 9,
]);
$assertions['mixed -> remark 3']               = $mixed['remark'] === 3;
$assertions['mixed -> booking_updated 2']      = $mixed['booking_updated'] === 2;
$assertions['mixed -> review_submitted 1']     = $mixed['review_submitted'] === 1;
$assertions['mixed -> supplier_reminder 10']   = $mixed['supplier_reminder'] === 10;
$assertions['mixed -> payout_overdue 7']       = $mixed['payout_overdue'] === 7;
$assertions['mixed -> product_no_checklist 9'] = $mixed['product_no_checklist'] === 9;
$assertions['mixed -> total = sum (32)']       = $mixed['total'] === 32;

// --- unknown types are dropped (not added to total) ---------------------

$with_junk = fold_category_counts([
    'remark'    => 2,
    'foo_type'  => 99,
]);
$assertions['unknown type ignored from total'] = $with_junk['total'] === 2;
$assertions['unknown type ignored from remark'] = $with_junk['remark'] === 2;

// --- string counts coerce to int ----------------------------------------

$str = fold_category_counts(['remark' => '7']);
$assertions['string count coerces -> remark 7'] = $str['remark'] === 7;
$assertions['string count coerces -> total 7']  = $str['total'] === 7;

// --- output keys exhaustive ---------------------------------------------

$expected_keys = ['remark', 'booking_updated', 'review_submitted',
    'supplier_reminder', 'payout_overdue', 'product_no_checklist', 'total'];
$actual_keys = array_keys(fold_category_counts([]));
sort($expected_keys);
sort($actual_keys);
$assertions['output keys exact match'] = $expected_keys === $actual_keys;

// --- report --------------------------------------------------------------

$failed = 0;
foreach ($assertions as $label => $ok) {
    echo ($ok ? '  PASS  ' : '  FAIL  ') . $label . PHP_EOL;
    if (!$ok) {
        $failed++;
    }
}

echo PHP_EOL . ($failed === 0 ? "All assertions passed.\n" : "$failed assertion(s) failed.\n");
exit($failed === 0 ? 0 : 1);
