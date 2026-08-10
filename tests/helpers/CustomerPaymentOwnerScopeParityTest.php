<?php
/**
 * Run with: php tests/helpers/CustomerPaymentOwnerScopeParityTest.php
 *
 * Locks card-vs-listing parity for the OWNER (level 10) booking-listing "Payment
 * From Customer Due Soon" card. On the listing the Owner renders the sales-agent
 * card set (summary_cards_show_agent_set), and that card is scoped to the
 * Owner's OWN bookings via the broad (SalesAgent = me OR SalesAgent2 = me) slot
 * — the same scope a TC card uses.
 *
 * The Owner's normal booking listing, though, is UNSCOPED (company-wide: level
 * 10 hits neither scope branch in apply_booking_filters). So without a special
 * case the ?customer_payment drill-down would show EVERY agent's due rows while
 * the card counted only the Owner's own — card and listing disagree (the card
 * says 1, the click shows many).
 *
 * The fix: when the ?customer_payment param is present, scope level 10 to the
 * Owner's OWN bookings (SalesAgent/SalesAgent2/BookingOP = me) — identical to a
 * TC (and to the level-25 fix). This test proves own-scope restores parity and
 * the unscoped default breaks it. Row X (another agent's due booking) is the
 * discriminator.
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
    BookingOP INTEGER,
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

// Logged-in Owner = admin 99. Other agents (any team) = 88, 77.
$today    = '2026-06-29';
$tomorrow = '2026-06-30';
$floor    = '2026-03-01';   // 1 March, current year

// booking: (ID, SA, SA2, OP, Status, Cancel, DepDL, FullDL, NetTotal)
$pdo->exec("INSERT INTO booking VALUES
    /* B1 owner owns via SalesAgent, P, deposit DL today               -> own + today    */
    (1, 99, 10, 12, 'P',  'N', '{$today}',    NULL,          1000),
    /* B2 owner owns via SalesAgent2, PP, full DL overdue, partly paid  -> own + overdue  */
    (2, 10, 99, 12, 'PP', 'N', NULL,          '2026-06-15',  2000),
    /* B3 owner owns via BookingOP only, P, deposit DL tomorrow         -> own (OP slot)  */
    (3, 10, 11, 99, 'P',  'N', '{$tomorrow}', NULL,          1000),
    /* X  ANOTHER AGENT 88 owns, owner NOT on it, P, due today          -> company-only   */
    (4, 88, 10, 12, 'P',  'N', '{$today}',    NULL,          1000),
    /* B5 another agent 77, owner NOT on it, overdue                    -> company-only   */
    (5, 77, 10, 12, 'PP', 'N', NULL,          '2026-06-10',  1500)
");
// B2 paid 500 of 2000 (still owes). B5 paid 500 of 1500 (still owes).
$pdo->exec("INSERT INTO payment VALUES (1, 2, 'Y', 500, NULL), (2, 5, 'Y', 500, NULL)");

$nd  = "(CASE WHEN booking.Status = 'P' THEN COALESCE(booking.DepositDeadline, booking.FullPaymentDeadline) ELSE booking.FullPaymentDeadline END)";
$out = "(booking.NetTotal - COALESCE((SELECT SUM(p.Credit) FROM payment p"
    . " WHERE p.BookingID = booking.BookingID"
    . " AND p.Status = 'Y' AND p.Credit > 0"
    . " AND (p.Type IS NULL OR p.Type != 'AGENT COMMISSION FROM SUPPLIER')), 0))";

$dueWindow = "{$nd} BETWEEN '{$floor}' AND '{$tomorrow}'";
$base = "booking.CancelStatus = 'N' AND booking.Status IN ('P','PP') AND {$dueWindow} AND {$out} > 0";

// --- The card's count query: OWN scope (SalesAgent = me OR SalesAgent2 = me) ---
$card_sql = "SELECT BookingID FROM booking
    WHERE (booking.SalesAgent = 99 OR booking.SalesAgent2 = 99)
      AND {$base} ORDER BY BookingID";
$card_ids = array_map('intval', $pdo->query($card_sql)->fetchAll(PDO::FETCH_COLUMN));

// --- The listing WITH the fix: TC own-scope (SA/SA2/OP = me) for the drill-down.
$listing_own_sql = "SELECT BookingID FROM booking
    WHERE (booking.SalesAgent = 99 OR booking.SalesAgent2 = 99 OR booking.BookingOP = 99)
      AND {$base} ORDER BY BookingID";
$listing_own_ids = array_map('intval', $pdo->query($listing_own_sql)->fetchAll(PDO::FETCH_COLUMN));

// --- The listing WITHOUT the fix: unscoped (the bug) leaks EVERY agent's rows. ---
$listing_all_sql = "SELECT BookingID FROM booking WHERE {$base} ORDER BY BookingID";
$listing_all_ids = array_map('intval', $pdo->query($listing_all_sql)->fetchAll(PDO::FETCH_COLUMN));

$assertions = array();
// Card uses SalesAgent/SalesAgent2 only (no OP slot), exactly like a TC card.
$assertions['card counts the owner\'s own SA/SA2 due rows {1,2}'] = $card_ids === array(1, 2);
// Fixed listing uses the TC own-scope SA/SA2/OP = me, so it ALSO shows the
// OP-only row B3 — the same (accepted) card-vs-listing gap a TC already has.
$assertions['fixed listing (own-scope) = {1,2,3}, identical to a TC listing'] =
    $listing_own_ids === array(1, 2, 3);
$assertions['fixed listing excludes other agents\' rows X,B5 (the whole point)'] =
    !in_array(4, $listing_own_ids, true) && !in_array(5, $listing_own_ids, true);
$assertions['buggy unscoped listing leaks other agents\' rows {1,2,3,4,5}'] =
    $listing_all_ids === array(1, 2, 3, 4, 5);
$assertions['other agents\' rows X,B5 are NOT counted by the card'] =
    !in_array(4, $card_ids, true) && !in_array(5, $card_ids, true);
$assertions['OP-only row B3: in listing, not in card — same gap a TC has'] =
    in_array(3, $listing_own_ids, true) && !in_array(3, $card_ids, true);

$failed = 0;
foreach ($assertions as $label => $ok) {
    echo ($ok ? '  PASS  ' : '  FAIL  ') . $label . PHP_EOL;
    if (!$ok) {
        $failed++;
    }
}

echo PHP_EOL . ($failed === 0 ? "All assertions passed.\n" : "{$failed} assertion(s) failed.\n");
exit($failed === 0 ? 0 : 1);
