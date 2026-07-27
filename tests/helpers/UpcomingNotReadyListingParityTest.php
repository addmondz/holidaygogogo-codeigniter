<?php
/**
 * Run with: php tests/helpers/UpcomingNotReadyListingParityTest.php
 *
 * Locks card-vs-listing parity for the TC dashboard "Travel in N Days – Not
 * Yet Ready" cards. Clicking a card opens /Booking with
 * upcoming_not_ready=1 & travel_date=<window>; the listing MUST return exactly
 * the rows the card counted.
 *
 * Bug shape this guards against (count 0/1 on the card, 1/6 in the listing):
 * the listing's upcoming_not_ready branch used to apply only CancelStatus +
 * Status, leaning on the generic filters for the rest. That left three ways the
 * listing over-counted vs Booking::ajax_summary_cards:
 *   1. travel_date used a range-OVERLAP clause, so trips that START before the
 *      window but extend into it leaked in — the card uses StartDate BETWEEN;
 *   2. no BOOKING CONFIRMATION filter, so quotations leaked in;
 *   3. the broad (SalesAgent = me OR SalesAgent2 = me) TC scope instead of the
 *      credited-slot (TC1/TC2 cutoff) rule the card counts by.
 *
 * apply_booking_filters() now AND-s StartDate-in-window, BookingConfirmationTitle
 * = 'BOOKING CONFIRMATION', and the credited-slot clause into that branch. This
 * test reconstructs both queries' SQL and asserts identical row sets, and shows
 * the pre-fix listing returned the superset.
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
    StartDate TEXT,
    EndDate TEXT,
    Status TEXT,
    CancelStatus TEXT,
    BookingConfirmationTitle TEXT
)");

// Logged-in TC = admin 99 (hccs). 7-day window: 2026-06-06 .. 2026-06-12.
// Cutoff (TC1->TC2) is 2026-06-01. BC = 'BOOKING CONFIRMATION'.
$BC = 'BOOKING CONFIRMATION';
$pdo->exec("INSERT INTO booking VALUES
    /* 1: post-cutoff TC2=99, BC, P, StartDate in window                 -> COUNT */
    (1, 10, 99, '2026-06-15', '2026-06-08', '2026-06-12', 'P', 'N', '{$BC}'),
    /* 2: pre-cutoff  TC1=99, BC, P, StartDate in window                 -> COUNT */
    (2, 99, 10, '2026-05-20', '2026-06-10', '2026-06-14', 'P', 'N', '{$BC}'),
    /* 3: overlaps window but StartDate (06-01) BEFORE it - overlap leak  -> NO   */
    (3, 10, 99, '2026-06-15', '2026-06-01', '2026-06-09', 'P', 'N', '{$BC}'),
    /* 4: quotation (non-BC) otherwise matching - title leak             -> NO   */
    (4, 10, 99, '2026-06-15', '2026-06-08', '2026-06-12', 'P', 'N', 'QUOTATION'),
    /* 5: pre-cutoff but 99 only in SalesAgent2 (wrong slot) - OR leak    -> NO   */
    (5, 10, 99, '2026-05-20', '2026-06-08', '2026-06-12', 'P', 'N', '{$BC}'),
    /* 6: cancelled                                                       -> NO   */
    (6, 10, 99, '2026-06-15', '2026-06-08', '2026-06-12', 'P', 'Y', '{$BC}'),
    /* 7: Status PT is the readiness state, not chased                    -> NO   */
    (7, 10, 99, '2026-06-15', '2026-06-08', '2026-06-12', 'PT','N', '{$BC}'),
    /* 8: StartDate after the window                                      -> NO   */
    (8, 10, 99, '2026-06-15', '2026-06-20', '2026-06-25', 'P', 'N', '{$BC}')
");

$win_start = '2026-06-06';
$win_end   = '2026-06-12';
$credit    = lead_conversion_credit_booking_clause(); // two '?' bound to admin_id

// --- The card's count query (Booking::ajax_summary_cards) ---------------
$card_sql = "SELECT BookingID FROM booking
    WHERE {$credit}
      AND BookingConfirmationTitle = '{$BC}'
      AND CancelStatus = 'N'
      AND Status IN ('P','PBO','PGL','PTV')
      AND StartDate BETWEEN '{$win_start}' AND '{$win_end}'
    ORDER BY BookingID";
$stmt = $pdo->prepare($card_sql);
$stmt->execute([99, 99]);
$card_ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

// --- The FIXED listing predicate (apply_booking_filters upcoming_not_ready)
// Broad TC OR (always applied for level 20/50) AND the three new alignments;
// the travel_date range-overlap is still AND-ed in but collapses to
// StartDate-in-window because an in-window StartDate already satisfies it.
$overlap = "((StartDate <= '{$win_start}' AND EndDate >= '{$win_end}')"
         . " OR (StartDate >= '{$win_start}' AND StartDate <= '{$win_end}')"
         . " OR (EndDate >= '{$win_start}' AND EndDate <= '{$win_end}'))";
$listing_sql = "SELECT BookingID FROM booking
    WHERE (SalesAgent = 99 OR SalesAgent2 = 99)
      AND CancelStatus = 'N'
      AND Status IN ('P','PBO','PGL','PTV')
      AND BookingConfirmationTitle = '{$BC}'
      AND {$credit}
      AND {$overlap}
      AND StartDate >= '{$win_start}'
      AND StartDate <= '{$win_end}'
    ORDER BY BookingID";
$stmt = $pdo->prepare($listing_sql);
$stmt->execute([99, 99]);
$listing_ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

// --- The PRE-FIX listing predicate (to document the leak) ----------------
$old_sql = "SELECT BookingID FROM booking
    WHERE (SalesAgent = 99 OR SalesAgent2 = 99)
      AND CancelStatus = 'N'
      AND Status IN ('P','PBO','PGL','PTV')
      AND {$overlap}
    ORDER BY BookingID";
$old_ids = array_map('intval', $pdo->query($old_sql)->fetchAll(PDO::FETCH_COLUMN));

$assertions = [];
$assertions['card counts exactly rows {1,2}'] = $card_ids === [1, 2];
$assertions['fixed listing equals card set']  = $listing_ids === $card_ids;
$assertions['pre-fix listing over-counted (leaked 3,4,5)'] =
    $old_ids === [1, 2, 3, 4, 5];
// Spell out each leak the fix closed, so a regression names the cause.
$assertions['overlap leak (row 3) excluded by StartDate-in-window'] =
    !in_array(3, $listing_ids, true) && in_array(3, $old_ids, true);
$assertions['quotation leak (row 4) excluded by BOOKING CONFIRMATION'] =
    !in_array(4, $listing_ids, true) && in_array(4, $old_ids, true);
$assertions['wrong-slot leak (row 5) excluded by credited-slot rule'] =
    !in_array(5, $listing_ids, true) && in_array(5, $old_ids, true);

$failed = 0;
foreach ($assertions as $label => $ok) {
    echo ($ok ? '  PASS  ' : '  FAIL  ') . $label . PHP_EOL;
    if (!$ok) {
        $failed++;
    }
}

echo PHP_EOL . ($failed === 0 ? "All assertions passed.\n" : "{$failed} assertion(s) failed.\n");
exit($failed === 0 ? 0 : 1);
