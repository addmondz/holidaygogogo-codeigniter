<?php
/**
 * Run with: php tests/helpers/TcCommissionCardTest.php
 *
 * Locks the "Sales Commission (Month)" card on /Booking
 * (Booking::ajax_summary_cards, TC branch -> cards['commission_month']).
 *
 * Business rule:
 *   - Commission = admin.CommissionPercent (per-TC rate) applied to the
 *     NetTotal of eligible bookings.
 *   - Eligible booking = COMPLETED (Status='Y'), NOT cancelled (CancelStatus='N'),
 *     a real Booking Confirmation, NetTotal > 0, created in the selected month
 *     (InsertDate BETWEEN month_start AND month_end).
 *   - Credited to the SECOND sales agent only (booking.SalesAgent2 = viewer) —
 *     unlike the other TC cards this ignores the TC1/TC2 date cutoff.
 *   - TC role (level 50) only.
 *
 * Three halves:
 *   1. Pure maths (tc_commission_helper): percent arithmetic + edge cases.
 *   2. Behavioural: run the card's WHERE fragment against in-memory SQLite and
 *      assert the right rows / SUM(NetTotal) feed the commission.
 *   3. Source contract: Booking.php wires the SalesAgent2 + completed query and
 *      gates it to level 50; the view keeps the card + is level-50 gated.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require_once __DIR__ . '/../../application/helpers/tc_commission_helper.php';

$assertions = [];

/* ------------------------------------------------------------------ *
 * 1) PURE MATHS                                                       *
 * ------------------------------------------------------------------ */

$assertions['5% of 10000 = 500']            = (abs(tc_commission_amount(10000, 5) - 500.0) < 0.001);
$assertions['2.5% of 4000 = 100']           = (abs(tc_commission_amount(4000, 2.5) - 100.0) < 0.001);
$assertions['rounds to 2 dp']               = (abs(tc_commission_amount(333.33, 3) - 10.0) < 0.001); // 9.9999 -> 10.00
$assertions['zero base = 0']                = (tc_commission_amount(0, 5) === 0.0);
$assertions['zero percent = 0']             = (tc_commission_amount(10000, 0) === 0.0);
$assertions['negative base = 0']            = (tc_commission_amount(-500, 5) === 0.0);
$assertions['string inputs coerced']        = (abs(tc_commission_amount('2000.00', '5') - 100.0) < 0.001);
$assertions['label trims 5.00 -> 5%']       = (tc_commission_percent_label(5) === '5%');
$assertions['label keeps 2.5 -> 2.5%']      = (tc_commission_percent_label(2.5) === '2.5%');
$assertions['label 0 -> 0%']                = (tc_commission_percent_label(0) === '0%');

/* ------------------------------------------------------------------ *
 * 2) BEHAVIOURAL                                                      *
 * ------------------------------------------------------------------ */

$month_start = '2026-08-01';
$month_end   = '2026-08-31';
$me          = 77;   // the viewing TC's AdminID (as SalesAgent2)
$other       = 88;   // a different agent

// Mirrors the controller's $commission_sales_sql predicate set.
$card_where =
    "SalesAgent2 = {$me} "
    . "AND Status = 'Y' "
    . "AND CancelStatus = 'N' "
    . "AND BookingConfirmationTitle = 'BOOKING CONFIRMATION' "
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
    SalesAgent INTEGER,
    SalesAgent2 INTEGER
)");

$pdo->exec("INSERT INTO booking VALUES
    (1, 'completed TC2=me',        'Y', 'N', 'BOOKING CONFIRMATION', 10000, '2026-08-10', 88, 77), /* COUNT */
    (2, 'completed TC2=me #2',     'Y', 'N', 'BOOKING CONFIRMATION',  5000, '2026-08-20', 12, 77), /* COUNT */
    (3, 'not completed (pending)', 'P', 'N', 'BOOKING CONFIRMATION',  9000, '2026-08-11', 12, 77), /* EXCLUDE not completed */
    (4, 'cancelled',               'Y', 'Y', 'BOOKING CONFIRMATION',  8000, '2026-08-12', 12, 77), /* EXCLUDE cancelled */
    (5, 'me is TC1 not TC2',       'Y', 'N', 'BOOKING CONFIRMATION',  7000, '2026-08-12', 77, 88), /* EXCLUDE TC2 only */
    (6, 'last month',              'Y', 'N', 'BOOKING CONFIRMATION',  4000, '2026-07-31', 12, 77), /* EXCLUDE out of month */
    (7, 'quotation',               'Y', 'N', 'QUOTATION',              600, '2026-08-12', 12, 77), /* EXCLUDE QU */
    (8, 'other agent TC2',         'Y', 'N', 'BOOKING CONFIRMATION',  3000, '2026-08-12', 12, 88)  /* EXCLUDE other agent */
");

$ids = [];
$total = 0.0;
foreach ($pdo->query("SELECT BookingID, NetTotal FROM booking WHERE {$card_where} ORDER BY BookingID") as $r) {
    $ids[] = (int) $r['BookingID'];
    $total += (float) $r['NetTotal'];
}

$assertions['counts completed TC2=me bookings']     = (in_array(1, $ids, true) && in_array(2, $ids, true));
$assertions['excludes not-completed booking']       = !in_array(3, $ids, true);
$assertions['excludes cancelled booking']           = !in_array(4, $ids, true);
$assertions['excludes where me is TC1 not TC2']     = !in_array(5, $ids, true);
$assertions['excludes out-of-month booking']        = !in_array(6, $ids, true);
$assertions['excludes quotation']                   = !in_array(7, $ids, true);
$assertions['excludes other agent booking']         = !in_array(8, $ids, true);
$assertions['SUM(NetTotal) = 10000+5000 = 15000']   = (abs($total - 15000.0) < 0.001);
// End-to-end: 3% of 15000 = 450.00.
$assertions['commission @3% = 450.00']              = (abs(tc_commission_amount($total, 3) - 450.0) < 0.001);

/* ------------------------------------------------------------------ *
 * 3) SOURCE CONTRACT                                                  *
 * ------------------------------------------------------------------ */

$ctrl_path = __DIR__ . '/../../application/controllers/Booking.php';
$src = is_file($ctrl_path) ? file_get_contents($ctrl_path) : '';
$assertions['Booking.php is readable'] = ($src !== '');

$assertions['$commission_sales_sql defined'] =
    (bool) strpos($src, '$commission_sales_sql =');
$assertions['commission_month card assigned'] =
    (bool) strpos($src, "\$cards['commission_month']");

if (preg_match('/\$commission_sales_sql\s*=\s*"(.*?)";/s', $src, $m)) {
    $sql = $m[1];
    $assertions['commission query filters SalesAgent2'] =
        (stripos($sql, 'SalesAgent2 = ?') !== false);
    $assertions['commission query requires completed Status=Y'] =
        (strpos($sql, "Status='Y'") !== false);
    $assertions['commission query excludes cancelled'] =
        (strpos($sql, "CancelStatus='N'") !== false);
    $assertions['commission query keeps the month window'] =
        (stripos($sql, 'InsertDate AS DATE') !== false) && (stripos($sql, 'BETWEEN') !== false);
    $assertions['commission query is BC-only'] =
        (bool) strpos($sql, "BookingConfirmationTitle='BOOKING CONFIRMATION'");
} else {
    $assertions['commission query filters SalesAgent2'] = false;
    $assertions['commission query requires completed Status=Y'] = false;
    $assertions['commission query excludes cancelled'] = false;
    $assertions['commission query keeps the month window'] = false;
    $assertions['commission query is BC-only'] = false;
}

// Gated to the TC role (level 50) in the controller.
$assertions['commission block gated to level 50'] =
    (bool) preg_match('/\$level\s*===?\s*50/', $src);

// Uses the pure helper for the maths.
$assertions['controller uses tc_commission_amount()'] =
    (bool) strpos($src, 'tc_commission_amount(');

// Registry entry — TC (50) only, correct payload key + anchor.
$reg_path = __DIR__ . '/../../application/helpers/card_visibility_helper.php';
$reg = is_file($reg_path) ? file_get_contents($reg_path) : '';
$assertions['registry has tc_commission_month (level 50)'] =
    (bool) preg_match("/'tc_commission_month'.*'levels'\s*=>\s*array\(50\)/", $reg);
$assertions['registry key is commission_month'] =
    (bool) preg_match("/'tc_commission_month'.*'keys'\s*=>\s*array\('commission_month'\)/", $reg);

// The view keeps the card + level-50 gate.
$view_path = __DIR__ . '/../../application/views/booking/_summary_cards.php';
$view = is_file($view_path) ? file_get_contents($view_path) : '';
$assertions['view has the commission card title'] =
    (strpos($view, '<h3>Sales Commission (Month)</h3>') !== false);
$assertions['view has commission value anchor'] =
    (strpos($view, 'sc-commission-month-value') !== false);
$assertions['view gates commission card to level 50'] =
    (bool) preg_match("/level'\)\s*\)?\s*===?\s*50[\s\S]{0,120}Sales Commission \(Month\)/", $view)
    || (bool) preg_match("/Sales Commission \(Month\)/", $view) && (bool) preg_match("/=== 50/", $view);

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
