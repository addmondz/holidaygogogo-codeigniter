<?php
/**
 * Run with: php tests/helpers/TotalSalesCardAllBcTest.php
 *
 * Locks the "Total Sales vs Target" card definition on /Booking
 * (Booking::ajax_summary_cards, TC branch -> cards['sales_month']).
 * (The card was formerly "Month Sales vs Target"; element keys stay
 *  sales_month for back-compat with the front end.)
 *
 * Business rule the card's "Actual" (and its "Best" leaderboard) must follow:
 *   - ALL credited Booking Confirmations (exclude QUOTATION / PROFORMA INVOICE)
 *   - regardless of payment status (NO fully-paid gate)
 *   - all-time (NO month / date window)
 *   - exclude cancelled (CancelStatus='N') and deleted (Status!='N')
 *
 * Two halves:
 *   1. Behavioural: run the card's WHERE fragment against in-memory SQLite and
 *      assert the right rows / SUM(NetTotal), including rows in other months and
 *      unpaid rows that the old (fully-paid, this-month) rule would have dropped.
 *   2. Source contract: assert Booking.php / the view actually wire the all-time,
 *      no-payment-gate query and renamed title so the behaviour can't drift.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

$assertions = [];

/* ------------------------------------------------------------------ *
 * 1) BEHAVIOURAL                                                      *
 * ------------------------------------------------------------------ */

// Mirrors $all_bc_sales_sql's predicate set (credited-slot scope omitted here;
// it's attribution, not part of the BC / payment / date rule under test).
$card_where =
    "BookingConfirmationTitle = 'BOOKING CONFIRMATION' "
    . "AND CancelStatus = 'N' "
    . "AND Status != 'N' "
    . "AND NetTotal > 0";

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
    (1, 'BC this month paid',   'P',  'N', 'BOOKING CONFIRMATION', 1000, '2026-06-10', 1), /* COUNT */
    (2, 'BC this month UNPAID', 'PP', 'N', 'BOOKING CONFIRMATION', 2000, '2026-06-11', 0), /* COUNT (regardless of payment) */
    (3, 'BC OLD month unpaid',  'Y',  'N', 'BOOKING CONFIRMATION', 4000, '2025-01-05', 0), /* COUNT (all-time) */
    (4, 'BC cancelled',         'P',  'Y', 'BOOKING CONFIRMATION', 8000, '2026-06-12', 1), /* EXCLUDE cancelled */
    (5, 'Quotation',            'P',  'N', 'QUOTATION',             500, '2026-06-12', 1), /* EXCLUDE QU */
    (6, 'Proforma Invoice',     'P',  'N', 'PROFORMA INVOICE',      600, '2026-06-12', 1), /* EXCLUDE PI */
    (7, 'BC deleted',           'N',  'N', 'BOOKING CONFIRMATION',  700, '2026-06-12', 1)  /* EXCLUDE deleted */
");

$ids = [];
$total = 0.0;
foreach ($pdo->query("SELECT BookingID, NetTotal FROM booking WHERE {$card_where} ORDER BY BookingID") as $r) {
    $ids[] = (int) $r['BookingID'];
    $total += (float) $r['NetTotal'];
}

$assertions['counts paid BC this month']                 = in_array(1, $ids, true);
$assertions['counts UNPAID BC (regardless of payment)']  = in_array(2, $ids, true);
$assertions['counts BC from a previous year (all-time)'] = in_array(3, $ids, true);
$assertions['excludes cancelled BC']                     = !in_array(4, $ids, true);
$assertions['excludes QUOTATION']                        = !in_array(5, $ids, true);
$assertions['excludes PROFORMA INVOICE']                 = !in_array(6, $ids, true);
$assertions['excludes deleted (Status=N)']               = !in_array(7, $ids, true);
$assertions['SUM(NetTotal) = 1000+2000+4000 = 7000']     = (abs($total - 7000.0) < 0.001);

/* ------------------------------------------------------------------ *
 * 2) SOURCE CONTRACT                                                  *
 * ------------------------------------------------------------------ */

$ctrl_path = __DIR__ . '/../../application/controllers/Booking.php';
$src = is_file($ctrl_path) ? file_get_contents($ctrl_path) : '';
$assertions['Booking.php is readable'] = ($src !== '');

$assertions['$all_bc_sales_sql defined'] =
    (bool) strpos($src, '$all_bc_sales_sql =');
$assertions['sales_month_actual uses $all_bc_sales_sql'] =
    (bool) preg_match('/\$row\s*=\s*\$this->db->query\(\s*\$all_bc_sales_sql.*?\$sales_month_actual\s*=/s', $src);

// Isolate the all-time query text: NO payment gate and NO date window.
if (preg_match('/\$all_bc_sales_sql\s*=\s*"(.*?)";/s', $src, $m)) {
    $sql = $m[1];
    $assertions['$all_bc_sales_sql has no fully-paid gate'] =
        (strpos($sql, 'paid_subquery') === false) && (stripos($sql, '>= booking.NetTotal') === false);
    $assertions['$all_bc_sales_sql has no date window'] =
        (stripos($sql, 'InsertDate AS DATE') === false) && (stripos($sql, 'BETWEEN') === false);
    $assertions['$all_bc_sales_sql is BC-only'] =
        (bool) strpos($sql, "BookingConfirmationTitle='BOOKING CONFIRMATION'");
    $assertions['$all_bc_sales_sql excludes cancelled/deleted'] =
        (strpos($sql, "CancelStatus='N'") !== false) && (strpos($sql, "Status!='N'") !== false);
} else {
    $assertions['$all_bc_sales_sql has no fully-paid gate'] = false;
    $assertions['$all_bc_sales_sql has no date window'] = false;
    $assertions['$all_bc_sales_sql is BC-only'] = false;
    $assertions['$all_bc_sales_sql excludes cancelled/deleted'] = false;
}

// The "Best" leaderboard must match: NO payment gate, NO date window.
if (preg_match('/\$best_sales_rows\s*=\s*\$this->db->query\(\s*"(.*?)",/s', $src, $m)) {
    $sql = $m[1];
    $assertions['Best leaderboard has no fully-paid gate'] =
        (strpos($sql, 'paid_subquery') === false) && (stripos($sql, '>= booking.NetTotal') === false);
    $assertions['Best leaderboard has no date window'] =
        (stripos($sql, 'BETWEEN') === false);
} else {
    $assertions['Best leaderboard has no fully-paid gate'] = false;
    $assertions['Best leaderboard has no date window'] = false;
}

// The card was renamed in the view.
$view_path = __DIR__ . '/../../application/views/booking/_summary_cards.php';
$view = is_file($view_path) ? file_get_contents($view_path) : '';
$assertions['card title renamed to "Total Sales vs Target"'] =
    (strpos($view, '<h3>Total Sales vs Target</h3>') !== false)
    && (strpos($view, '<h3>Month Sales vs Target</h3>') === false);

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
