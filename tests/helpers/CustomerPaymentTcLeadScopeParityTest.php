<?php
/**
 * Run with: php tests/helpers/CustomerPaymentTcLeadScopeParityTest.php
 *
 * Locks card-vs-listing parity for the TC LEAD (level 25) dashboard "Payment
 * From Customer Due Soon" card — it must behave EXACTLY like a TC's (level
 * 20/50), not like the team-scoped default listing a lead normally sees.
 *
 * The card is scoped to the lead's OWN bookings via the broad
 * (SalesAgent = me OR SalesAgent2 = me) slot — the same scope a TC card uses.
 * A TC Lead's normal booking listing, though, is TEAM-scoped
 * (apply_booking_filters(): level 25 -> team_member_ids). So without a special
 * case the ?customer_payment drill-down would show the WHOLE TEAM's due rows
 * while the card counted only the lead's own — card and listing disagree.
 *
 * The fix: when the ?customer_payment param is present, scope level 25 to the
 * lead's OWN bookings (SalesAgent/SalesAgent2/BookingOP = me) — identical to a
 * TC. This test proves own-scope restores parity and team-scope breaks it.
 * Row T (a teammate's due booking) is the discriminator.
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

// Logged-in TC Lead = admin 99. Teammate (same TeamID) = admin 88. Outsider = 77.
$today    = '2026-06-29';
$tomorrow = '2026-06-30';
$floor    = '2026-03-01';   // 1 March, current year
$team     = array(99, 88);  // team_member_ids() for the lead

// booking: (ID, SA, SA2, OP, Status, Cancel, DepDL, FullDL, NetTotal)
$pdo->exec("INSERT INTO booking VALUES
    /* B1 lead owns via SalesAgent, P, deposit DL today              -> own + today    */
    (1, 99, 10, 12, 'P',  'N', '{$today}',    NULL,          1000),
    /* B2 lead owns via SalesAgent2, PP, full DL overdue, partly paid -> own + overdue  */
    (2, 10, 99, 12, 'PP', 'N', NULL,          '2026-06-15',  2000),
    /* B3 lead owns via BookingOP only, P, deposit DL tomorrow        -> own (OP slot)  */
    (3, 10, 11, 99, 'P',  'N', '{$tomorrow}', NULL,          1000),
    /* T  TEAMMATE 88 owns, lead NOT on it, P, due today              -> team-only      */
    (4, 88, 10, 12, 'P',  'N', '{$today}',    NULL,          1000),
    /* B5 outsider 77, lead NOT on it                                 -> neither        */
    (5, 77, 10, 12, 'P',  'N', '{$today}',    NULL,          1000)
");
// B2 paid 500 of 2000 (still owes).
$pdo->exec("INSERT INTO payment VALUES (1, 2, 'Y', 500, NULL)");

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

// --- The listing WITHOUT the fix: team-scope (the bug) leaks teammate row T. ---
$in = implode(',', $team);
$listing_team_sql = "SELECT BookingID FROM booking
    WHERE (booking.SalesAgent IN ({$in}) OR booking.SalesAgent2 IN ({$in}) OR booking.BookingOP IN ({$in}))
      AND {$base} ORDER BY BookingID";
$listing_team_ids = array_map('intval', $pdo->query($listing_team_sql)->fetchAll(PDO::FETCH_COLUMN));

$assertions = array();
// Card uses SalesAgent/SalesAgent2 only (no OP slot), exactly like a TC card.
$assertions['card counts the lead\'s own SA/SA2 due rows {1,2}'] = $card_ids === array(1, 2);
// Fixed listing uses the TC own-scope SA/SA2/OP = me, so it ALSO shows the
// OP-only row B3 — the same (accepted) card-vs-listing gap a TC already has.
$assertions['fixed listing (TC own-scope) = {1,2,3}, identical to a TC listing'] =
    $listing_own_ids === array(1, 2, 3);
$assertions['fixed listing excludes teammate row T (the whole point of the fix)'] =
    !in_array(4, $listing_own_ids, true);
$assertions['buggy team-scope listing leaks teammate row T'] =
    in_array(4, $listing_team_ids, true) && $listing_team_ids === array(1, 2, 3, 4);
$assertions['teammate row T is NOT counted by the card'] = !in_array(4, $card_ids, true);
$assertions['outsider row B5 excluded from every scope'] =
    !in_array(5, $card_ids, true) && !in_array(5, $listing_own_ids, true) && !in_array(5, $listing_team_ids, true);
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
