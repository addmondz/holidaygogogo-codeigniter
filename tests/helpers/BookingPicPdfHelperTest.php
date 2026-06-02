<?php
/**
 * Run with: php tests/helpers/BookingPicPdfHelperTest.php
 *
 * Verifies pdf_show_booking_pic_for_date() switches to the split
 * "Booking PIC" (TC1) + "Sales Agent" (TC2) layout on the 2026-06-01 cutoff
 * and tolerates empty / formatted / malformed dates.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require_once __DIR__ . '/../../application/helpers/booking_pic_pdf_helper.php';

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label}\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

// Boundary: 2026-06-01 is the first day with the split layout (inclusive).
assert_eq('Day before cutoff keeps single layout', false, pdf_show_booking_pic_for_date('2026-05-31'));
assert_eq('Cutoff day uses split (inclusive)',      true,  pdf_show_booking_pic_for_date('2026-06-01'));
assert_eq('Day after cutoff uses split',            true,  pdf_show_booking_pic_for_date('2026-06-02'));

// Datetime strings straddling midnight of the cutoff.
assert_eq('Datetime just before cutoff keeps single', false, pdf_show_booking_pic_for_date('2026-05-31 23:59:59'));
assert_eq('Datetime at cutoff uses split',            true,  pdf_show_booking_pic_for_date('2026-06-01 00:00:01'));

// Long-ago and far-future bookings.
assert_eq('Year 2023 keeps single layout', false, pdf_show_booking_pic_for_date('2023-05-15'));
assert_eq('Year 2030 uses split layout',   true,  pdf_show_booking_pic_for_date('2030-01-01'));

// Display-formatted dates still resolve via strtotime.
assert_eq('Formatted pre-cutoff date keeps single', false, pdf_show_booking_pic_for_date('31 MAY 2026'));
assert_eq('Formatted cutoff date uses split',       true,  pdf_show_booking_pic_for_date('1 JUN 2026'));

// Defensive: empty/malformed dates fall back to the original single layout.
assert_eq('Empty string keeps single',   false, pdf_show_booking_pic_for_date(''));
assert_eq('Null keeps single',           false, pdf_show_booking_pic_for_date(null));
assert_eq('Garbage string keeps single', false, pdf_show_booking_pic_for_date('not-a-date'));

echo "\nAll assertions passed.\n";
