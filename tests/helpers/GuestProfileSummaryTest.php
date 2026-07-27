<?php
/**
 * Run with: php tests/helpers/GuestProfileSummaryTest.php
 *
 * Covers guest_profile_summary() — the aggregate behind the Customer Profile
 * page's added figures (Total Sales, Num of Pax) and its Source / Customer Type
 * rows. Bookings arrive newest-first; pax and sales sum across every booking,
 * while Source / Customer Type take the most recent booking that carries a value.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require_once __DIR__ . '/../../application/helpers/guest_profile_helper.php';

function gps_booking($fields) { return (object) $fields; }

$assertions = array();

// Empty input -> zeroed summary -------------------------------------------
$assertions['empty -> zeroed'] =
    guest_profile_summary(array())
    === array('total_pax' => 0, 'total_sales' => 0.0, 'source' => '', 'customer_type' => '');

// Single booking sums its own pax and sales -------------------------------
$one = guest_profile_summary(array(
    gps_booking(array('Adult' => 2, 'Children' => 1, 'Infant' => 0, 'NetTotal' => 1500.50,
        'SourceName' => 'Facebook', 'CustomerType' => 'VIP')),
));
$assertions['single total_pax']     = $one['total_pax'] === 3;
$assertions['single total_sales']   = $one['total_sales'] === 1500.50;
$assertions['single source']        = $one['source'] === 'Facebook';
$assertions['single customer_type'] = $one['customer_type'] === 'VIP';

// Several bookings sum pax + sales across all of them ---------------------
$many = guest_profile_summary(array(
    gps_booking(array('Adult' => 2, 'Children' => 0, 'Infant' => 1, 'NetTotal' => 1000,
        'SourceName' => 'Instagram', 'CustomerType' => 'Repeat')),
    gps_booking(array('Adult' => 4, 'Children' => 2, 'Infant' => 0, 'NetTotal' => 2000,
        'SourceName' => 'Walk-in',   'CustomerType' => 'New')),
));
$assertions['many total_pax']   = $many['total_pax'] === 9;      // 3 + 6
$assertions['many total_sales'] = $many['total_sales'] === 3000.0;
// Newest-first: first booking's Source / Customer Type win.
$assertions['many source newest']        = $many['source'] === 'Instagram';
$assertions['many customer_type newest'] = $many['customer_type'] === 'Repeat';

// Most recent booking blank -> fall through to the next non-empty ---------
$fallthrough = guest_profile_summary(array(
    gps_booking(array('Adult' => 1, 'NetTotal' => 500, 'SourceName' => '',   'CustomerType' => null)),
    gps_booking(array('Adult' => 1, 'NetTotal' => 500, 'SourceName' => 'SEO','CustomerType' => 'Agent')),
));
$assertions['fallthrough source']        = $fallthrough['source'] === 'SEO';
$assertions['fallthrough customer_type'] = $fallthrough['customer_type'] === 'Agent';
$assertions['fallthrough pax']           = $fallthrough['total_pax'] === 2;

// Missing pax / sales fields treated as zero ------------------------------
$missing = guest_profile_summary(array(gps_booking(array('SourceName' => 'X'))));
$assertions['missing pax -> 0']   = $missing['total_pax'] === 0;
$assertions['missing sales -> 0'] = $missing['total_sales'] === 0.0;

// --- report ---------------------------------------------------------------
$failed = 0;
foreach ($assertions as $name => $ok) {
    if (!$ok) { $failed++; echo "FAIL: {$name}\n"; }
}
if ($failed === 0) {
    echo "OK: all " . count($assertions) . " assertions passed\n";
    exit(0);
}
echo "\n{$failed} assertion(s) failed\n";
exit(1);
