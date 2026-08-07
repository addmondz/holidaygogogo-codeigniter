<?php
/**
 * Run with: php tests/helpers/GuestListDateFilterTest.php
 *
 * Locks the Guest List date / booking-only filter behaviour. Bug: filtering by
 * Booking Date left every GHL lead visible because the GHL branch ignored all
 * filters. The fix:
 *   - booking_date filters GHL leads by their lead-captured date
 *     (DATE(COALESCE(date_added, created_at)) — the value shown in the
 *     "Booking Date(s)" column);
 *   - booking/customer-only filters (travel_date, sales_agent, source,
 *     customer_type) drop the GHL branch entirely, since a lead can never
 *     carry those attributes;
 *   - lead-carried attributes (nationality, gender, language, race) instead
 *     FILTER the GHL branch (gcv.* / gc.*), so they keep it — a lead that does
 *     not match the value is dropped by the WHERE, not the whole branch.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}
require __DIR__ . '/../../application/helpers/guest_contact_helper.php';

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

// ---- date range parsing ----------------------------------------------------
assert_eq('empty -> null',        null, guest_list_parse_date_range(''));
assert_eq('whitespace -> null',   null, guest_list_parse_date_range('   '));
assert_eq('single date -> null',  null, guest_list_parse_date_range('14/03/2026'));
assert_eq('two-sided range',
    array('2026-03-14', '2026-03-20'),
    guest_list_parse_date_range('14/03/2026 - 20/03/2026'));
assert_eq('same-day range',
    array('2026-03-14', '2026-03-14'),
    guest_list_parse_date_range('14/03/2026 - 14/03/2026'));

// ---- which filters suppress GHL leads -------------------------------------
assert_eq('no filters -> keep GHL',        false, guest_list_ghl_suppressed_by_filters(array()));
assert_eq('booking_date keeps GHL',        false, guest_list_ghl_suppressed_by_filters(array('booking_date' => '14/03/2026 - 14/03/2026')));
assert_eq('name search keeps GHL',         false, guest_list_ghl_suppressed_by_filters(array('q' => 'ali')));
assert_eq('empty values keep GHL',         false, guest_list_ghl_suppressed_by_filters(array('source' => '', 'gender' => '  ')));
assert_eq('travel_date drops GHL',         true,  guest_list_ghl_suppressed_by_filters(array('travel_date' => '14/03/2026 - 14/03/2026')));
assert_eq('sales_agent drops GHL',         true,  guest_list_ghl_suppressed_by_filters(array('sales_agent' => '7')));
assert_eq('source drops GHL',              true,  guest_list_ghl_suppressed_by_filters(array('source' => '3')));
assert_eq('customer_type drops GHL',       true,  guest_list_ghl_suppressed_by_filters(array('customer_type' => 'VIP')));
// Lead-carried attributes filter the GHL branch instead of suppressing it.
assert_eq('nationality keeps GHL',         false, guest_list_ghl_suppressed_by_filters(array('nationality' => 'Malaysia')));
assert_eq('gender keeps GHL',              false, guest_list_ghl_suppressed_by_filters(array('gender' => 'Male')));
assert_eq('language keeps GHL',            false, guest_list_ghl_suppressed_by_filters(array('language' => 'English')));

// ---- integration: GHL leads filter by their captured date -----------------
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE ghl_contacts (id INTEGER PRIMARY KEY, date_added TEXT, created_at TEXT)");
$pdo->exec("INSERT INTO ghl_contacts VALUES
    (1, '2026-03-14 10:00:00', '2026-03-14 10:00:00'),  -- inside range
    (2, NULL,                  '2025-07-23 09:00:00'),  -- before range (created_at fallback)
    (3, '2025-12-12 08:00:00', '2025-12-12 08:00:00'),  -- before range
    (4, '2026-03-04 12:00:00', '2026-03-04 12:00:00')   -- before range
");

list($start, $end) = guest_list_parse_date_range('14/03/2026 - 14/03/2026');

// Mirror the model's GHL booking_date clause (portable form of the MySQL one).
$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM ghl_contacts gc
     WHERE DATE(COALESCE(gc.date_added, gc.created_at)) >= :s
       AND DATE(COALESCE(gc.date_added, gc.created_at)) <= :e");
$stmt->execute(array(':s' => $start, ':e' => $end));
assert_eq('only the 14 Mar lead survives', 1, (int) $stmt->fetchColumn());

echo "\nAll assertions passed.\n";
