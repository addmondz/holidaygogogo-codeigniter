<?php
/**
 * Run with: php tests/helpers/BookingSummaryCardCreditedSlotTest.php
 *
 * Locks the WHERE-fragment used by the TC view of the booking summary cards
 * (Booking::ajax_summary_cards levels 20/50). A booking counts toward the
 * logged-in TC only when they hold the CREDITED slot for that booking's
 * InsertDate window:
 *   - InsertDate <  2026-06-01  => credited slot is SalesAgent  (TC1)
 *   - InsertDate >= 2026-06-01  => credited slot is SalesAgent2 (TC2)
 *
 * Mirrors the rule already enforced by lead_conversion_credit_sql_fragment()
 * for the Lead Dashboard's converted_leads attribution, so the summary cards
 * agree with the lead conversion credit model.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require_once __DIR__ . '/../../application/helpers/lead_conversion_credit_helper.php';

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE booking (
    BookingID INTEGER PRIMARY KEY,
    SalesAgent INTEGER,
    SalesAgent2 INTEGER,
    InsertDate TEXT,
    NetTotal REAL
)");

// Seed bookings on both sides of 2026-06-01. Convention: admin 99 = hccs,
// admin 10 = alice. Comments label whether the row should be CREDITED to 99.
$pdo->exec("INSERT INTO booking VALUES
    (1,  99,   10,   '2026-05-30 10:00:00', 100), /* pre,  hccs=TC1            -> COUNT */
    (2,  10,   99,   '2026-05-30 10:00:00', 200), /* pre,  hccs=TC2            -> NO   */
    (3,  99,   10,   '2026-06-01 00:00:00', 300), /* cutoff, hccs=TC1          -> NO   */
    (4,  10,   99,   '2026-06-01 00:00:00', 400), /* cutoff, hccs=TC2          -> COUNT */
    (5,  99,   NULL, '2026-05-31 23:59:59', 500), /* pre boundary, hccs=TC1    -> COUNT */
    (6,  NULL, 99,   '2026-06-15 12:30:00', 600), /* post, hccs=TC2 only       -> COUNT */
    (7,  99,   10,   '2026-06-15 12:30:00', 700), /* post, hccs=TC1            -> NO   */
    (8,  99,   99,   '2026-05-30 10:00:00', 800), /* pre, hccs on both         -> COUNT (TC1 credit) */
    (9,  99,   99,   '2026-06-15 12:30:00', 900), /* post, hccs on both        -> COUNT (TC2 credit) */
    (10, NULL, 99,   '2026-05-30 10:00:00',1000), /* pre, hccs=TC2 only        -> NO   */
    (11, 10,   20,   '2026-05-30 10:00:00',1100), /* pre, hccs absent          -> NO   */
    (12, 99,   0,    '2026-06-15 12:30:00',1200)  /* post, hccs=TC1 + TC2=0    -> NO   */
");

if (!function_exists('lead_conversion_credit_booking_clause')) {
    fwrite(STDERR, "FAIL: helper lead_conversion_credit_booking_clause() is not defined.\n");
    exit(1);
}

$clause = lead_conversion_credit_booking_clause();

$assertions = [];

// 1) Count + sum for hccs (admin 99): expected rows 1, 4, 5, 6, 8, 9 -> count=6, sum=3300
$sql = "SELECT COUNT(*) AS cnt, COALESCE(SUM(NetTotal),0) AS total
        FROM booking
        WHERE {$clause}";
$stmt = $pdo->prepare($sql);
$stmt->execute([99, 99]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$assertions['hccs count = 6']  = ((int) $row['cnt']) === 6;
$assertions['hccs sum  = 3300'] = ((int) $row['total']) === 3300;

// 2) Count for alice (admin 10): expected rows 2, 4, 7, 11 -> 4
$stmt = $pdo->prepare($sql);
$stmt->execute([10, 10]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$assertions['alice count = 4'] = ((int) $row['cnt']) === 4;

// 3) Count for admin 20: only row 11 (pre, SA2=20 -> NO; pre-cutoff TC2-only) -> 0
$stmt = $pdo->prepare($sql);
$stmt->execute([20, 20]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$assertions['admin 20 count = 0'] = ((int) $row['cnt']) === 0;

// 4) Per-row credit breakdown for hccs — debug aid if aggregate is off.
$detail_sql = "SELECT BookingID,
    CASE WHEN {$clause} THEN 1 ELSE 0 END AS credited
FROM booking ORDER BY BookingID";
$expected_per_row = [
    1  => 1, 2  => 0, 3  => 0, 4  => 1, 5  => 1, 6  => 1,
    7  => 0, 8  => 1, 9  => 1, 10 => 0, 11 => 0, 12 => 0,
];
$stmt = $pdo->prepare($detail_sql);
$stmt->execute([99, 99]);
foreach ($stmt as $r) {
    $id = (int) $r['BookingID'];
    $assertions["Booking #{$id} credit for hccs = {$expected_per_row[$id]}"] =
        ((int) $r['credited']) === $expected_per_row[$id];
}

// 5) Cutoff boundary is inclusive on the post side. Row 4 is on cutoff -> TC2 credit.
$stmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM booking WHERE BookingID = 4 AND {$clause}");
$stmt->execute([99, 99]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$assertions['Cutoff 2026-06-01 belongs to post-window (TC2)'] = ((int) $row['cnt']) === 1;

$failed = 0;
foreach ($assertions as $label => $ok) {
    echo ($ok ? '  PASS  ' : '  FAIL  ') . $label . PHP_EOL;
    if (!$ok) {
        $failed++;
    }
}

echo PHP_EOL . ($failed === 0 ? "All assertions passed.\n" : "$failed assertion(s) failed.\n");
exit($failed === 0 ? 0 : 1);
