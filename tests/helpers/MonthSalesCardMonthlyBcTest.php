<?php
/**
 * Run with: php tests/helpers/MonthSalesCardMonthlyBcTest.php
 *
 * Locks the "Month Sales vs Target" card definition on /Booking
 * (Booking::ajax_summary_cards, TC branch -> cards['sales_month']).
 *
 * Business rule the card's "Actual" (and its "Best" leaderboard) must follow:
 *   - ALL credited Booking Confirmations (exclude QUOTATION / PROFORMA INVOICE)
 *   - regardless of payment status (NO fully-paid gate)
 *   - within the SELECTED MONTH (InsertDate BETWEEN month_start AND month_end)
 *   - exclude cancelled (CancelStatus='N') and deleted (Status!='N')
 *
 * Two halves:
 *   1. Behavioural: run the card's WHERE fragment against in-memory SQLite and
 *      assert the right rows / SUM(NetTotal): unpaid in-month BCs are counted
 *      (no payment gate) while out-of-month BCs are excluded (month window).
 *   2. Source contract: assert Booking.php wires the month-windowed, no-payment-
 *      gate query and the view keeps the "Month Sales vs Target" title.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

$assertions = [];

$month_start = '2026-06-01';
$month_end   = '2026-06-30';

/* ------------------------------------------------------------------ *
 * 1) BEHAVIOURAL                                                      *
 * ------------------------------------------------------------------ */

// Mirrors $month_bc_sales_sql's predicate set (credited-slot scope omitted
// here; it's attribution, not part of the BC / payment / date rule under test).
$card_where =
    "BookingConfirmationTitle = 'BOOKING CONFIRMATION' "
    . "AND CancelStatus = 'N' "
    . "AND Status != 'N' "
    . "AND NetTotal > 0 "
    . "AND (InsertDate >= '{$month_start}' AND InsertDate <= '{$month_end}')";

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE booking (
    BookingID INTEGER PRIMARY KEY,
    Customer TEXT,
    Status TEXT,
    CancelStatus TEXT,
    BookingConfirmationTitle TEXT,
    NetTotal REAL,
    InsertDate TEXT,
    PaidInFull INTEGER
)");

// PaidInFull is illustrative only — the card must NOT consult payment at all.
// Convention: comment marks whether the row SHOULD be counted.
$pdo->exec("INSERT INTO booking VALUES
    (1, 'BC in-month paid',   'P',  'N', 'BOOKING CONFIRMATION', 1000, '2026-06-10', 1), /* COUNT */
    (2, 'BC in-month UNPAID', 'PP', 'N', 'BOOKING CONFIRMATION', 2000, '2026-06-11', 0), /* COUNT (regardless of payment) */
    (3, 'BC LAST month',      'Y',  'N', 'BOOKING CONFIRMATION', 4000, '2026-05-31', 0), /* EXCLUDE (out of month) */
    (4, 'BC cancelled',       'P',  'Y', 'BOOKING CONFIRMATION', 8000, '2026-06-12', 1), /* EXCLUDE cancelled */
    (5, 'Quotation',          'P',  'N', 'QUOTATION',             500, '2026-06-12', 1), /* EXCLUDE QU */
    (6, 'Proforma Invoice',   'P',  'N', 'PROFORMA INVOICE',      600, '2026-06-12', 1), /* EXCLUDE PI */
    (7, 'BC deleted',         'N',  'N', 'BOOKING CONFIRMATION',  700, '2026-06-12', 1)  /* EXCLUDE deleted */
");

$ids = [];
$total = 0.0;
foreach ($pdo->query("SELECT BookingID, NetTotal FROM booking WHERE {$card_where} ORDER BY BookingID") as $r) {
    $ids[] = (int) $r['BookingID'];
    $total += (float) $r['NetTotal'];
}

$assertions['counts paid in-month BC']                  = in_array(1, $ids, true);
$assertions['counts UNPAID in-month BC (no pay gate)']  = in_array(2, $ids, true);
$assertions['excludes BC from last month (month window)'] = !in_array(3, $ids, true);
$assertions['excludes cancelled BC']                    = !in_array(4, $ids, true);
$assertions['excludes QUOTATION']                       = !in_array(5, $ids, true);
$assertions['excludes PROFORMA INVOICE']                = !in_array(6, $ids, true);
$assertions['excludes deleted (Status=N)']              = !in_array(7, $ids, true);
$assertions['SUM(NetTotal) = 1000+2000 = 3000']         = (abs($total - 3000.0) < 0.001);

/* ------------------------------------------------------------------ *
 * 2) SOURCE CONTRACT                                                  *
 * ------------------------------------------------------------------ */

$ctrl_path = __DIR__ . '/../../application/controllers/Booking.php';
$src = is_file($ctrl_path) ? file_get_contents($ctrl_path) : '';
$assertions['Booking.php is readable'] = ($src !== '');

$assertions['$month_bc_sales_sql defined'] =
    (bool) strpos($src, '$month_bc_sales_sql =');
$assertions['sales_month_actual uses $month_bc_sales_sql'] =
    (bool) preg_match('/\$row\s*=\s*\$this->db->query\(\s*\$month_bc_sales_sql.*?\$sales_month_actual\s*=/s', $src);

// Isolate the query text: must drop the fully-paid gate but KEEP the month window.
if (preg_match('/\$month_bc_sales_sql\s*=\s*"(.*?)";/s', $src, $m)) {
    $sql = $m[1];
    $assertions['$month_bc_sales_sql has no fully-paid gate'] =
        (strpos($sql, 'paid_subquery') === false) && (stripos($sql, '>= booking.NetTotal') === false);
    $assertions['$month_bc_sales_sql keeps the month window'] =
        (stripos($sql, 'InsertDate AS DATE') !== false) && (stripos($sql, 'BETWEEN') !== false);
    $assertions['$month_bc_sales_sql is BC-only'] =
        (bool) strpos($sql, "BookingConfirmationTitle='BOOKING CONFIRMATION'");
    $assertions['$month_bc_sales_sql excludes cancelled/deleted'] =
        (strpos($sql, "CancelStatus='N'") !== false) && (strpos($sql, "Status!='N'") !== false);
} else {
    $assertions['$month_bc_sales_sql has no fully-paid gate'] = false;
    $assertions['$month_bc_sales_sql keeps the month window'] = false;
    $assertions['$month_bc_sales_sql is BC-only'] = false;
    $assertions['$month_bc_sales_sql excludes cancelled/deleted'] = false;
}

// The Month "Best" leaderboard must match: dropped the payment gate, kept window.
if (preg_match('/\$best_sales_rows\s*=\s*\$this->db->query\(\s*"(.*?)",/s', $src, $m)) {
    $sql = $m[1];
    $assertions['Best (month) leaderboard has no fully-paid gate'] =
        (strpos($sql, 'paid_subquery') === false) && (stripos($sql, '>= booking.NetTotal') === false);
    $assertions['Best (month) leaderboard keeps the month window'] =
        (stripos($sql, 'BETWEEN') !== false);
} else {
    $assertions['Best (month) leaderboard has no fully-paid gate'] = false;
    $assertions['Best (month) leaderboard keeps the month window'] = false;
}

// The view keeps the monthly title.
$view_path = __DIR__ . '/../../application/views/booking/_summary_cards.php';
$view = is_file($view_path) ? file_get_contents($view_path) : '';
$assertions['card title is "Month Sales vs Target"'] =
    (strpos($view, '<h3>Month Sales vs Target</h3>') !== false);

/* ------------------------------------------------------------------ */

$fail = 0;
foreach ($assertions as $label => $ok) {
    echo ($ok ? 'PASS  ' : 'FAIL  ') . $label . "\n";
    if (!$ok) {
        $fail++;
    }
}
echo "\n" . (count($assertions) - $fail) . '/' . count($assertions) . " passed\n";
exit($fail === 0 ? 0 : 1);
