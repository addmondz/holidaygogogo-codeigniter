<?php
/**
 * Run with: php tests/helpers/LeadOwnershipDailyConversionTest.php
 *
 * Locks the per-agent "Converted" column contract on the Lead Ownership
 * dashboard (Report_Model::Lead_Ownership_By_Agent).
 *
 * The conversion % is no longer a single aggregate ratio over the whole range.
 * Instead each day's conversion percentage is "locked" on its own, and the
 * range value is the MEAN of those daily percentages spread over EVERY calendar
 * day in the selected range:
 *
 *     daily_rate(day)  = 100 * converted(day) / owned(day)      (owned>0)
 *     conversion_rate  = SUM(daily_rate over days with data) / calendar_days_in_range
 *
 * Days with zero owned leads contribute 0% (they are simply absent from the sum
 * but still counted in the denominator). The day a lead belongs to is
 * DATE(lead_started_at). This test pins that math in portable SQLite.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE ghl_lead_ownership (
    owner_user_id TEXT,
    processed_lead_id INTEGER,
    is_converted INTEGER,
    booking_id INTEGER,
    lead_started_at TEXT
)");

// Range under test: 2026-06-01 .. 2026-06-04  => 4 calendar days.
//
// Agent A:
//   06-01: 2 owned, 1 converted        => 50%
//   06-02: 4 owned, 1 converted        => 25%
//   06-03: (no leads)                  =>  0% (absent, but day still counted)
//   06-04: 1 owned, 1 converted        => 100%
//   sum daily% = 175 ; / 4 days = 43.75 -> 43.8
//   (old aggregate ratio would be 3/7 = 42.9, so the methods must differ)
//
// Agent B:
//   06-02: 2 owned, 0 converted        => 0%
//   06-04: 2 owned, 2 converted        => 100%
//   sum daily% = 100 ; / 4 days = 25.0
$pdo->exec("INSERT INTO ghl_lead_ownership (owner_user_id, processed_lead_id, is_converted, booking_id, lead_started_at) VALUES
    ('A', 1, 1, 500, '2026-06-01 08:00:00'),
    ('A', 2, 0, NULL,'2026-06-01 09:00:00'),
    ('A', 3, 1, 501, '2026-06-02 08:00:00'),
    ('A', 4, 0, NULL,'2026-06-02 09:00:00'),
    ('A', 5, 0, NULL,'2026-06-02 10:00:00'),
    ('A', 6, 1, NULL,'2026-06-02 11:00:00'),
    ('A', 7, 1, 502, '2026-06-04 12:00:00'),
    ('B', 8, 0, NULL,'2026-06-02 08:00:00'),
    ('B', 9, 0, NULL,'2026-06-02 09:00:00'),
    ('B', 10,1, 503, '2026-06-04 08:00:00'),
    ('B', 11,1, 504, '2026-06-04 09:00:00')
");

// is_converted=1 but booking_id IS NULL must NOT count as converted (row 6).

$start = '2026-06-01';
$end   = '2026-06-04';

// --- calendar days in range (PHP side) ---
$rangeDays = (int) floor((strtotime($end . ' 00:00:00') - strtotime($start . ' 00:00:00')) / 86400) + 1;
assert_eq('calendar days in range', 4, $rangeDays);

// --- daily-rate aggregation (mirrors the model SQL) ---
$sql = "
    SELECT owner_user_id,
           SUM(daily_rate)  AS sum_daily_rate,
           COUNT(*)         AS active_days
    FROM (
        SELECT owner_user_id,
               (100.0 * SUM(CASE WHEN is_converted = 1 AND booking_id IS NOT NULL THEN 1 ELSE 0 END) / COUNT(*)) AS daily_rate
        FROM ghl_lead_ownership
        WHERE lead_started_at >= :s AND lead_started_at <= :e
        GROUP BY owner_user_id, DATE(lead_started_at)
    ) daily
    GROUP BY owner_user_id
";
$stmt = $pdo->prepare($sql);
$stmt->execute(array(':s' => $start . ' 00:00:00', ':e' => $end . ' 23:59:59'));

$rates = array();
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $denom = $rangeDays > 0 ? $rangeDays : (int) $row['active_days'];
    $rates[$row['owner_user_id']] = $denom > 0
        ? round(((float) $row['sum_daily_rate']) / $denom, 1)
        : 0.0;
}

assert_eq('Agent A daily-avg conversion', 43.8, $rates['A']);
assert_eq('Agent B daily-avg conversion', 25.0, $rates['B']);

// Lock that the new method genuinely differs from the old aggregate ratio.
$oldAgg = round((3 / 7) * 100, 1); // A: 3 converted / 7 owned
assert_eq('old aggregate ratio for A (for contrast)', 42.9, $oldAgg);
if ($rates['A'] === $oldAgg) {
    echo "  FAIL  daily-avg must differ from aggregate ratio\n";
    exit(1);
}
echo "  PASS  daily-avg (43.8) differs from aggregate ratio (42.9)\n";

echo "\nAll LeadOwnershipDailyConversion tests passed.\n";
