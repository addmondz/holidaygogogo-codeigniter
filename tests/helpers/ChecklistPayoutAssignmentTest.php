<?php
/**
 * Run with: php tests/helpers/ChecklistPayoutAssignmentTest.php
 *
 * Locks checklist_payout_assignment_join_sql() — the shared rule behind the
 * "Supplier Pay-out Checklist Due Soon" card (Booking::summary_cards) and its
 * drill-down list (Booking_Model::apply_checklist_payout_filter).
 *
 * Bug it guards against: a booking whose product carries the FULL payout
 * checklist but NOT the DEPOSIT one, yet has a deposit pay-out date, was
 * invisible on the card even though the checklist modal auto-adds and tracks
 * the deposit checklist. The card/list required JSON_CONTAINS(product
 * assignment, deposit_id), which the modal never requires. The fix: a deposit
 * branch qualifies by its date alone (no assignment join); full still requires
 * assignment.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}
require_once __DIR__ . '/../../application/helpers/checklist_payout_helper.php';

$assertions = [];

// --- Rule: deposit qualifies by date; full requires assignment -------------
$assertions['full requires assignment'] =
    checklist_payout_requires_assignment('PaymentOutSupplierFull') === true;
$assertions['deposit does NOT require assignment'] =
    checklist_payout_requires_assignment('PaymentOutSupplierDeposit') === false;

$dep_join  = checklist_payout_assignment_join_sql(2, 'PaymentOutSupplierDeposit');
$full_join = checklist_payout_assignment_join_sql(1, 'PaymentOutSupplierFull');

$assertions['deposit join is empty'] = $dep_join === '';
$assertions['full join adds product_package_checklist'] =
    strpos($full_join, 'product_package_checklist') !== false;
$assertions['full join uses JSON_CONTAINS'] =
    strpos($full_join, 'JSON_CONTAINS') !== false;
$assertions['full join references the checklist id'] =
    strpos($full_join, "'1'") !== false;

// checklist id is int-cast -> no injection through the fragment
$evil = checklist_payout_assignment_join_sql("1'); DROP TABLE booking;--", 'PaymentOutSupplierFull');
$assertions['checklist id is int-cast'] =
    strpos($evil, 'DROP TABLE') === false && strpos($evil, "'1'") !== false;

// --- Integration: reproduce BC-2606-0288 against SQLite --------------------
// Product carries the FULL checklist (id 1) but NOT deposit (id 2); the line
// has a deposit date due today and no completion row. With the fix the deposit
// branch (no assignment join) surfaces the booking; the old assignment-gated
// branch hid it.
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE product (ProductID INTEGER PRIMARY KEY, is_child_or_infant INTEGER)");
$pdo->exec("CREATE TABLE booking_product (BookingProductID INTEGER PRIMARY KEY, BookingID INTEGER, ProductID INTEGER, Status TEXT, disable_checklist_payment_out INTEGER, PaymentOutSupplierDeposit TEXT)");
// product 271 assigned only [1] (full) — no deposit id 2
$pdo->exec("CREATE TABLE product_package_checklist (product_id INTEGER, assigned TEXT)");
$pdo->exec("CREATE TABLE booking_checklist_completion (booking_id INTEGER, product_id INTEGER, package_checklist_id INTEGER)");

$pdo->exec("INSERT INTO product VALUES (271,0)");
$pdo->exec("INSERT INTO booking_product VALUES (24052, 9771, 271, 'Y', 0, '2026-07-02')");
$pdo->exec("INSERT INTO product_package_checklist VALUES (271, '1')"); // full only

// Deposit branch built exactly like the card, using the helper's join fragment
// (empty for deposit). JSON_CONTAINS never appears, so this runs under SQLite.
$dep_branch = "SELECT bp.BookingID AS bid FROM booking_product bp"
    . " JOIN product p ON p.ProductID = bp.ProductID AND p.is_child_or_infant = 0"
    . checklist_payout_assignment_join_sql(2, 'PaymentOutSupplierDeposit')
    . " WHERE bp.Status = 'Y' AND bp.disable_checklist_payment_out = 0"
    . " AND bp.PaymentOutSupplierDeposit IS NOT NULL"
    . " AND bp.PaymentOutSupplierDeposit = '2026-07-02'"
    . " AND NOT EXISTS (SELECT 1 FROM booking_checklist_completion bcc"
    . " WHERE bcc.booking_id = bp.BookingID AND bcc.product_id = bp.ProductID"
    . " AND bcc.package_checklist_id = 2)";
$found = $pdo->query("SELECT COUNT(*) c FROM ({$dep_branch}) d")->fetch(PDO::FETCH_ASSOC);
$assertions['fixed deposit branch surfaces BC-2606-0288'] = ((int) $found['c']) === 1;

// Old behaviour: require deposit assignment -> booking hidden (the bug).
$old_branch = "SELECT bp.BookingID AS bid FROM booking_product bp"
    . " JOIN product p ON p.ProductID = bp.ProductID AND p.is_child_or_infant = 0"
    . " JOIN product_package_checklist ppc ON ppc.product_id = bp.ProductID AND ppc.assigned = '2'"
    . " WHERE bp.Status = 'Y' AND bp.PaymentOutSupplierDeposit = '2026-07-02'";
$hidden = $pdo->query("SELECT COUNT(*) c FROM ({$old_branch}) d")->fetch(PDO::FETCH_ASSOC);
$assertions['old assignment-gated branch hid it (0)'] = ((int) $hidden['c']) === 0;

// Ticking the deposit checklist removes it again (completion gate still works).
$pdo->exec("INSERT INTO booking_checklist_completion VALUES (9771, 271, 2)");
$ticked = $pdo->query("SELECT COUNT(*) c FROM ({$dep_branch}) d")->fetch(PDO::FETCH_ASSOC);
$assertions['ticked deposit drops off the card'] = ((int) $ticked['c']) === 0;

$failed = 0;
foreach ($assertions as $label => $ok) {
    echo ($ok ? '  PASS  ' : '  FAIL  ') . $label . PHP_EOL;
    if (!$ok) { $failed++; }
}
echo PHP_EOL . ($failed === 0 ? "All assertions passed.\n" : "$failed assertion(s) failed.\n");
exit($failed === 0 ? 0 : 1);
