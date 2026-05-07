<?php
/**
 * Run with: php tests/helpers/NotificationCategoryMappingTest.php
 *
 * Verifies that user-facing notification category keys map to the correct
 * notification.type values, and that round-tripping a type back to a category
 * is consistent.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require_once __DIR__ . '/../../application/helpers/notification_category_helper.php';

$assertions = [];

// --- notification_category_to_types() -------------------------------------

$assertions['remark -> [remark]'] =
    notification_category_to_types('remark') === ['remark'];

$assertions['booking_updated -> [booking_updated]'] =
    notification_category_to_types('booking_updated') === ['booking_updated'];

$assertions['review_submitted -> [review_submitted]'] =
    notification_category_to_types('review_submitted') === ['review_submitted'];

$assertions['supplier_reminder folds full + deposit'] =
    notification_category_to_types('supplier_reminder')
        === ['supplier_reminder_full', 'supplier_reminder_deposit'];

$assertions['payout_overdue folds full + deposit'] =
    notification_category_to_types('payout_overdue')
        === ['payout_overdue_full', 'payout_overdue_deposit'];

$assertions['product_no_checklist -> [product_no_checklist]'] =
    notification_category_to_types('product_no_checklist') === ['product_no_checklist'];

$assertions['unknown category -> []'] =
    notification_category_to_types('not_a_category') === [];

$assertions['empty category -> []'] =
    notification_category_to_types('') === [];

// --- notification_type_to_category() (round-trip) -------------------------

$assertions['type remark -> category remark'] =
    notification_type_to_category('remark') === 'remark';

$assertions['type supplier_reminder_full -> supplier_reminder'] =
    notification_type_to_category('supplier_reminder_full') === 'supplier_reminder';

$assertions['type supplier_reminder_deposit -> supplier_reminder'] =
    notification_type_to_category('supplier_reminder_deposit') === 'supplier_reminder';

$assertions['type payout_overdue_full -> payout_overdue'] =
    notification_type_to_category('payout_overdue_full') === 'payout_overdue';

$assertions['type payout_overdue_deposit -> payout_overdue'] =
    notification_type_to_category('payout_overdue_deposit') === 'payout_overdue';

$assertions['type product_no_checklist -> product_no_checklist'] =
    notification_type_to_category('product_no_checklist') === 'product_no_checklist';

$assertions['unknown type -> null'] =
    notification_type_to_category('something_else') === null;

// --- notification_category_keys() -----------------------------------------

$keys = notification_category_keys();
$assertions['category keys count == 6'] = count($keys) === 6;
$assertions['category keys include remark']               = in_array('remark', $keys, true);
$assertions['category keys include booking_updated']      = in_array('booking_updated', $keys, true);
$assertions['category keys include review_submitted']     = in_array('review_submitted', $keys, true);
$assertions['category keys include supplier_reminder']    = in_array('supplier_reminder', $keys, true);
$assertions['category keys include payout_overdue']       = in_array('payout_overdue', $keys, true);
$assertions['category keys include product_no_checklist'] = in_array('product_no_checklist', $keys, true);

// --- notification_category_label() ----------------------------------------

$assertions['label all -> All'] = notification_category_label('all') === 'All';
$assertions['label remark -> Remarks'] = notification_category_label('remark') === 'Remarks';
$assertions['label supplier_reminder -> Supplier Reminders'] =
    notification_category_label('supplier_reminder') === 'Supplier Reminders';

// --- report ---------------------------------------------------------------

$failed = 0;
foreach ($assertions as $label => $ok) {
    echo ($ok ? '  PASS  ' : '  FAIL  ') . $label . PHP_EOL;
    if (!$ok) {
        $failed++;
    }
}

echo PHP_EOL . ($failed === 0 ? "All assertions passed.\n" : "$failed assertion(s) failed.\n");
exit($failed === 0 ? 0 : 1);
