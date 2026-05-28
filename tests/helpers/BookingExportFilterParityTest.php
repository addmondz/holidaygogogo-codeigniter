<?php
/**
 * Run with: php tests/helpers/BookingExportFilterParityTest.php
 *
 * Locks the contract that the booking spreadsheet exports
 *   - Booking::Download                  -> "Booking Records" sheet
 *   - Booking::Mass_Generate_Guest_Lists -> "Guest List" sheet
 * apply EXACTLY the same filter set as the on-screen list at /Booking.
 *
 * Bug shape this guards against:
 *   Read_Bookings_With_Guest_Lists used to carry its own hand-rolled copy of
 *   the filter logic. Whenever a new filter was added to the listing (e.g.
 *   sales_agent_2, booking_op, customer_type, autocount_status,
 *   einvoice_status, the multi-value variants of source / chat_language /
 *   destination / sales_agent / cancellation_reason, the trim/guest-list-aware
 *   customer search, or the SalesAgent2 OR-clause for level 20/50), the
 *   spreadsheet drifted out of sync and Download leaked unrelated rows.
 *
 * The contract: Read_Bookings_With_Guest_Lists routes its filter set through
 * the same private apply_booking_filters() the listing uses, and does NOT
 * keep its own duplicate copies of those filter call sites.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

$model_path = __DIR__ . '/../../application/models/Booking_Model.php';
if (!is_file($model_path)) {
    echo "FAIL  cannot locate Booking_Model.php at {$model_path}\n";
    exit(1);
}
$source = file_get_contents($model_path);

// Locate the body of Read_Bookings_With_Guest_Lists.
if (!preg_match(
    '/function\s+Read_Bookings_With_Guest_Lists\s*\([^)]*\)\s*\{/',
    $source,
    $m,
    PREG_OFFSET_CAPTURE
)) {
    echo "FAIL  Read_Bookings_With_Guest_Lists() not found in Booking_Model.php\n";
    exit(1);
}
$brace_start = $m[0][1] + strlen($m[0][0]) - 1;
$depth = 0;
$body_end = null;
for ($i = $brace_start, $n = strlen($source); $i < $n; $i++) {
    $c = $source[$i];
    if ($c === '{') {
        $depth++;
    } elseif ($c === '}') {
        $depth--;
        if ($depth === 0) {
            $body_end = $i;
            break;
        }
    }
}
if ($body_end === null) {
    echo "FAIL  could not delimit Read_Bookings_With_Guest_Lists() body\n";
    exit(1);
}
$body = substr($source, $brace_start, $body_end - $brace_start + 1);

$assertions = [];

// 1) Must call the shared filter helper.
$assertions['Read_Bookings_With_Guest_Lists calls $this->apply_booking_filters()'] =
    strpos($body, '$this->apply_booking_filters()') !== false;

// 2) Must not duplicate filter call sites that belong to apply_booking_filters.
//    Each fragment below is a filter the listing supports; if any of them
//    appears inside Read_Bookings_With_Guest_Lists, the export has drifted
//    back to a local copy.
$drift_fragments = [
    "input->get('booking_op')",
    "input->get('sales_agent_2')",
    "input->get('customer_type')",
    "input->get('autocount_status')",
    "input->get('einvoice_status')",
    "input->get('upcoming_not_ready')",
    "input->get('search[value]')",
    // Single-value variants that have been replaced by where_in() in the
    // shared helper. Their presence here would re-introduce the multi-select
    // mismatch.
    "where('SalesAgent',",
    "where('Destination',",
    "where('Source',",
    "where('ChatLanguage',",
];
foreach ($drift_fragments as $f) {
    $assertions["Read_Bookings_With_Guest_Lists no longer inlines: {$f}"] =
        strpos($body, $f) === false;
}

// 3) Sanity: apply_booking_filters itself still exists and is private.
$assertions['apply_booking_filters() is still defined in Booking_Model'] =
    (bool) preg_match('/private\s+function\s+apply_booking_filters\s*\(/', $source);

// 4) The customer JOIN must be present in Read_Bookings_With_Guest_Lists so
//    that apply_booking_filters' customer.name / customer.CustomerCode
//    search clauses resolve.
$assertions['Read_Bookings_With_Guest_Lists joins the customer table'] =
    (bool) preg_match("/join\(\s*'customer'/i", $body);

$failed = 0;
foreach ($assertions as $label => $ok) {
    echo ($ok ? '  PASS  ' : '  FAIL  ') . $label . PHP_EOL;
    if (!$ok) {
        $failed++;
    }
}

echo PHP_EOL . ($failed === 0 ? "All assertions passed.\n" : "{$failed} assertion(s) failed.\n");
exit($failed === 0 ? 0 : 1);
