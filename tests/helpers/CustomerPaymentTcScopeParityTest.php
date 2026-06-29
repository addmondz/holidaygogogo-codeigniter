<?php
/**
 * Run with: php tests/helpers/CustomerPaymentTcScopeParityTest.php
 *
 * Locks card-vs-listing parity for the sales-agent (TC, level 20/50) dashboard
 * "Payment From Customer Due Soon" card. Clicking a bucket opens /Booking with
 * customer_payment=<bucket> & status=A; the listing MUST return exactly the
 * rows the card counted.
 *
 * Scope decision this guards: the customer-payment card is scoped to the agent's
 * OWN bookings via the broad (SalesAgent = me OR SalesAgent2 = me) slot — the
 * SAME default scope the TC booking listing applies for level 20/50, and which
 * apply_customer_payment_filter() leans on (it adds NO agent predicate of its
 * own). This is DELIBERATELY broader than the "Travel in N Days – Not Yet Ready"
 * card, which uses the narrower credited-slot (TC1/TC2 cutoff) rule. Row B2
 * below is the discriminator: a PRE-cutoff booking where the agent is only the
 * second sales agent. Broad-OR (this card) keeps it; the credited-slot rule
 * (the not-ready card) would drop it. If someone "unifies" the two scopes, this
 * test fails and names why they must stay different.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE booking (
    BookingID INTEGER PRIMARY KEY,
    SalesAgent INTEGER,
    SalesAgent2 INTEGER,
    InsertDate TEXT,
    Status TEXT,
    CancelStatus TEXT,
    DepositDeadline TEXT,
    FullPaymentDeadline TEXT,
    NetTotal REAL
)");
$pdo->exec("CREATE TABLE payment (
    PaymentID INTEGER PRIMARY KEY,
    BookingID INTEGER,
    Status TEXT,
    Credit REAL,
    Type TEXT
)");

// Logged-in TC = admin 99. Server-derived window (fixed here for determinism):
$today    = '2026-06-29';
$tomorrow = '2026-06-30';
$floor    = '2026-03-01';   // 1 March, current year
$cutoff   = '2026-06-01';   // TC1 -> TC2 credited-slot cutoff

// booking: (ID, SA, SA2, InsertDate, Status, Cancel, DepDL, FullDL, NetTotal)
$pdo->exec("INSERT INTO booking VALUES
    /* B1 post-cutoff, credited TC2=99, P, deposit DL today              -> today    */
    (1, 10, 99, '2026-06-15', 'P',  'N', '{$today}',     NULL,          1000),
    /* B2 PRE-cutoff, 99 only in SalesAgent2 (NOT credited), P, today    -> today (broad-OR keeps; credited-slot drops) */
    (2, 10, 99, '2026-05-01', 'P',  'N', '{$today}',     NULL,           500),
    /* B3 TC1=99, PP, full DL overdue (>= floor), partly paid            -> overdue  */
    (3, 99, 10, '2026-04-10', 'PP', 'N', NULL,           '2026-06-15',  2000),
    /* B4 not owned by 99 at all                                          -> NO       */
    (4, 10, 11, '2026-06-15', 'P',  'N', '{$today}',     NULL,          1000),
    /* B5 owned, fully paid (outstanding 0)                               -> NO       */
    (5, 99, 10, '2026-06-15', 'P',  'N', '{$today}',     NULL,          1000),
    /* B6 owned, cancelled                                                -> NO       */
    (6, 99, 10, '2026-06-15', 'P',  'Y', '{$today}',     NULL,          1000),
    /* B7 owned, Status Y (not P/PP)                                      -> NO       */
    (7, 99, 10, '2026-06-15', 'Y',  'N', '{$today}',     NULL,          1000),
    /* B8 owned, deposit DL tomorrow                                      -> tomorrow */
    (8, 99, 10, '2026-06-15', 'P',  'N', '{$tomorrow}',  NULL,          1000),
    /* B9 owned, deadline before floor (Feb)                              -> NO       */
    (9, 99, 10, '2026-06-15', 'P',  'N', '2026-02-01',   NULL,          1000)
");
// Approved customer credits: B3 paid 500 (still owes), B5 paid in full.
$pdo->exec("INSERT INTO payment VALUES
    (1, 3, 'Y', 500,  NULL),
    (2, 5, 'Y', 1000, NULL),
    (3, 5, 'Y', 50,   'AGENT COMMISSION FROM SUPPLIER')   /* excluded credit type, must not reduce balance */
");

// Shared SQL fragments — identical to Booking::ajax_summary_cards / Booking_Model.
$nd  = "(CASE WHEN booking.Status = 'P' THEN COALESCE(booking.DepositDeadline, booking.FullPaymentDeadline) ELSE booking.FullPaymentDeadline END)";
$out = "(booking.NetTotal - COALESCE((SELECT SUM(p.Credit) FROM payment p"
    . " WHERE p.BookingID = booking.BookingID"
    . " AND p.Status = 'Y' AND p.Credit > 0"
    . " AND (p.Type IS NULL OR p.Type != 'AGENT COMMISSION FROM SUPPLIER')), 0))";

// Bucket -> deadline predicate on the operative next-due deadline column.
$range = function($col) use ($today, $tomorrow, $floor) {
    return array(
        'overdue'  => "{$col} >= '{$floor}' AND {$col} < '{$today}'",
        'today'    => "{$col} = '{$today}'",
        'tomorrow' => "{$col} = '{$tomorrow}'",
    );
};

// --- The card's count query (whole window = union of the three buckets) ---
// Scope: broad (SalesAgent = 99 OR SalesAgent2 = 99).
$card_sql = "SELECT BookingID FROM booking
    WHERE booking.CancelStatus = 'N'
      AND booking.Status IN ('P','PP')
      AND (booking.SalesAgent = 99 OR booking.SalesAgent2 = 99)
      AND {$nd} BETWEEN '{$floor}' AND '{$tomorrow}'
      AND {$out} > 0
    ORDER BY BookingID";
$card_ids = array_map('intval', $pdo->query($card_sql)->fetchAll(PDO::FETCH_COLUMN));

// --- The listing predicate: default broad-OR scope (level 20/50) AND the
// apply_customer_payment_filter() body, AND status=A (CancelStatus='N'). Built
// as the union of the three buckets so it matches the whole-window card. ----
$dl_p  = $range("COALESCE(booking.DepositDeadline, booking.FullPaymentDeadline)");
$dl_pp = $range("booking.FullPaymentDeadline");
$bucket_branches = array();
foreach (array('overdue', 'today', 'tomorrow') as $b) {
    $bucket_branches[] = "((booking.Status = 'P' AND ({$dl_p[$b]})) OR (booking.Status = 'PP' AND ({$dl_pp[$b]})))";
}
$listing_sql = "SELECT BookingID FROM booking
    WHERE (booking.SalesAgent = 99 OR booking.SalesAgent2 = 99)   /* default TC scope */
      AND booking.CancelStatus = 'N'                              /* status=A */
      AND (" . implode(' OR ', $bucket_branches) . ")
      AND {$out} > 0
    ORDER BY BookingID";
$listing_ids = array_map('intval', $pdo->query($listing_sql)->fetchAll(PDO::FETCH_COLUMN));

// --- What a credited-slot rule (the not-ready card's scope) WOULD count ----
// Pre-cutoff credits SalesAgent; on/after credits SalesAgent2. Used only to
// prove B2 is the deliberate broad-OR-only row.
$credited_sql = "SELECT BookingID FROM booking
    WHERE booking.CancelStatus = 'N'
      AND booking.Status IN ('P','PP')
      AND ((booking.InsertDate < '{$cutoff}' AND booking.SalesAgent = 99)
        OR (booking.InsertDate >= '{$cutoff}' AND booking.SalesAgent2 = 99))
      AND {$nd} BETWEEN '{$floor}' AND '{$tomorrow}'
      AND {$out} > 0
    ORDER BY BookingID";
$credited_ids = array_map('intval', $pdo->query($credited_sql)->fetchAll(PDO::FETCH_COLUMN));

$assertions = array();
$assertions['card counts exactly rows {1,2,3,8}'] = $card_ids === array(1, 2, 3, 8);
$assertions['listing equals card set (parity)']   = $listing_ids === $card_ids;
$assertions['B2 (pre-cutoff, SalesAgent2 only) IS counted by broad-OR card'] =
    in_array(2, $card_ids, true);
// Credited-slot keeps only B1 (post-cutoff TC2=99) and B3 (pre-cutoff TC1=99).
// It drops B2 (pre-cutoff, 99 only in the TC2 slot) AND B8 (post-cutoff, 99 in
// the TC1 slot) — both of which the broad-OR card rightly keeps as "own".
$assertions['credited-slot rule would DROP B2 (the deliberate difference)'] =
    !in_array(2, $credited_ids, true) && $credited_ids === array(1, 3);
$assertions['fully-paid B5 excluded (outstanding 0; commission credit ignored)'] =
    !in_array(5, $card_ids, true);
$assertions['cancelled B6 excluded'] = !in_array(6, $card_ids, true);
$assertions['non-P/PP B7 excluded'] = !in_array(7, $card_ids, true);
$assertions['before-floor B9 excluded'] = !in_array(9, $card_ids, true);
$assertions['unowned B4 excluded'] = !in_array(4, $card_ids, true);

$failed = 0;
foreach ($assertions as $label => $ok) {
    echo ($ok ? '  PASS  ' : '  FAIL  ') . $label . PHP_EOL;
    if (!$ok) {
        $failed++;
    }
}

echo PHP_EOL . ($failed === 0 ? "All assertions passed.\n" : "{$failed} assertion(s) failed.\n");
exit($failed === 0 ? 0 : 1);
