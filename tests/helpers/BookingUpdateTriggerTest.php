<?php
/**
 * Run with: php tests/helpers/BookingUpdateTriggerTest.php
 *
 * Verifies the booking-updated notification only fires for travel-date
 * (StartDate / EndDate) or booking-product changes, and that the scoped
 * summary excludes other booking-log diffs and customer-type changes.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require_once __DIR__ . '/../../application/helpers/booking_change_summary_helper.php';

$assertions = [];

// --- booking_products_have_changes() --------------------------------------

$assertions['create non-empty -> true'] =
    booking_products_have_changes([['Name' => 'Hotel A']], [], []) === true;

$assertions['delete non-empty -> true'] =
    booking_products_have_changes([], [], [['BookingProductID' => 7]]) === true;

$assertions['update with meaningful key -> true'] =
    booking_products_have_changes([], [
        ['BookingProductID' => 7, 'Quantity' => 3],
    ], []) === true;

$assertions['all empty -> false'] =
    booking_products_have_changes([], [], []) === false;

$assertions['update sweep-only -> false'] =
    booking_products_have_changes([], [
        ['BookingProductID' => 7, 'disable_checklist_payment_out' => 'Y'],
    ], []) === false;

$assertions['update meta-only -> false'] =
    booking_products_have_changes([], [
        ['BookingProductID' => 7, 'UpdateBy' => 1, 'UpdateDate' => '2026-05-07 10:00:00'],
    ], []) === false;

$assertions['update mixed sweep + real -> true'] =
    booking_products_have_changes([], [
        ['BookingProductID' => 7, 'disable_checklist_payment_out' => 'Y'],
        ['BookingProductID' => 8, 'Price' => 100],
    ], []) === true;

$assertions['update PaymentOutSupplierFull-only -> false'] =
    booking_products_have_changes([], [
        ['BookingProductID' => 7, 'PaymentOutSupplierFull' => '2026-06-01'],
    ], []) === false;

$assertions['update PaymentOutSupplierDeposit-only -> false'] =
    booking_products_have_changes([], [
        ['BookingProductID' => 7, 'PaymentOutSupplierDeposit' => '2026-05-15'],
    ], []) === false;

$assertions['update PaymentOutSupplierFull + Quantity same row -> true'] =
    booking_products_have_changes([], [
        ['BookingProductID' => 7, 'PaymentOutSupplierFull' => '2026-06-01', 'Quantity' => 3],
    ], []) === true;

$assertions['update PaymentOutSupplierFull row + separate Price row -> true'] =
    booking_products_have_changes([], [
        ['BookingProductID' => 7, 'PaymentOutSupplierFull' => '2026-06-01'],
        ['BookingProductID' => 8, 'Price' => 250],
    ], []) === true;

// --- build_booking_update_notification_summary() --------------------------

$start_only = build_booking_update_notification_summary(
    [['Column' => 'StartDate', 'CurrentData' => '2025-12-01', 'NewData' => '2025-12-15']],
    [], [], []
);
$assertions['StartDate change -> contains Start Date'] =
    strpos($start_only, 'Start Date') !== false;
$assertions['StartDate change -> contains date diff'] =
    strpos($start_only, '01/12/2025 → 15/12/2025') !== false;

$end_only = build_booking_update_notification_summary(
    [['Column' => 'EndDate', 'CurrentData' => '2025-12-10', 'NewData' => '2025-12-20']],
    [], [], []
);
$assertions['EndDate change -> contains End Date'] =
    strpos($end_only, 'End Date') !== false;

$customer_only = build_booking_update_notification_summary(
    [['Column' => 'Customer', 'CurrentData' => 'Alice', 'NewData' => 'Bob']],
    [], [], []
);
$assertions['Customer change excluded -> empty summary'] = $customer_only === '';
$assertions['Customer change excluded -> no Customer label'] =
    strpos($customer_only, 'Customer') === false;

$nettotal_only = build_booking_update_notification_summary(
    [['Column' => 'NetTotal', 'CurrentData' => '1000.00', 'NewData' => '1500.00']],
    [], [], []
);
$assertions['NetTotal change excluded -> empty summary'] = $nettotal_only === '';
$assertions['NetTotal change excluded -> no Net Total label'] =
    strpos($nettotal_only, 'Net Total') === false;

$deposit_deadline = build_booking_update_notification_summary(
    [['Column' => 'DepositDeadline', 'CurrentData' => '2025-11-01', 'NewData' => '2025-11-15']],
    [], [], []
);
$assertions['DepositDeadline excluded -> empty summary'] = $deposit_deadline === '';

$create_only = build_booking_update_notification_summary(
    [], [['Name' => 'Hotel A']], [], []
);
$assertions['Product create -> contains Added Hotel A'] =
    strpos($create_only, 'Added Hotel A') !== false;

$mixed = build_booking_update_notification_summary(
    [
        ['Column' => 'StartDate', 'CurrentData' => '2025-12-01', 'NewData' => '2025-12-15'],
        ['Column' => 'Customer',  'CurrentData' => 'Alice',      'NewData' => 'Bob'],
    ],
    [], [], []
);
$assertions['Mixed -> contains StartDate diff'] =
    strpos($mixed, '01/12/2025 → 15/12/2025') !== false;
$assertions['Mixed -> excludes Customer'] =
    strpos($mixed, 'Customer') === false;

$assertions['All empty -> empty summary'] =
    build_booking_update_notification_summary([], [], [], []) === '';

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
