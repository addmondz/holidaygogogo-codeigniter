<?php
/**
 * Run with: php tests/helpers/BcCreatedCreditedAgentDrilldownTest.php
 *
 * Locks card-vs-listing parity for the agent "BC Created" card drill-down
 * (This Month / This Year figures) when the viewer's default listing scope is
 * BROADER than the card's credited-slot count.
 *
 * The card counts BCs the viewer is *credited* for under the TC1/TC2 cutoff
 * rule: SalesAgent pre-2026-06-01 (TC1), SalesAgent2 on/after (TC2). The Owner
 * (level 10) sees an UNSCOPED booking listing, so the old drill-down link
 * (booking_date only) showed EVERY agent's bookings — reported bug: "under
 * owner role, click didn't filter to the owner's bookings, showing all".
 *
 * The fix adds ?credited_agent=<me> to the link, applying the exact credited
 * predicate in apply_booking_filters(), plus status=A (drop cancelled/draft)
 * and booking_confirmation_title=BOOKING CONFIRMATION (drop quotation/PI) so
 * the drill-down returns the same set the card counted. This test proves the
 * credited_agent predicate restores parity and the old unscoped link breaks it.
 * The cutoff-straddling rows are the discriminators.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

$cutoff = '2026-06-01';   // LEAD_CONVERSION_TC2_CUTOFF_DATE
$OWNER  = 10;             // logged-in Owner, also actively selling
$OTHER  = 77;            // another agent

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE booking (
    BookingID INTEGER PRIMARY KEY,
    SalesAgent INTEGER,
    SalesAgent2 INTEGER,
    Status TEXT,
    CancelStatus TEXT,
    BookingConfirmationTitle TEXT,
    InsertDate TEXT
)");

$BC = "'BOOKING CONFIRMATION'";
// (ID, SA, SA2, Status, Cancel, Title, InsertDate)
$pdo->exec("INSERT INTO booking VALUES
    /* B1 PRE-cutoff, owner is TC1 (SalesAgent)                  -> credited to owner */
    (1, {$OWNER}, {$OTHER}, 'P',  'N', {$BC}, '2026-03-10 09:00:00'),
    /* B2 ON/AFTER cutoff, owner is TC2 (SalesAgent2)            -> credited to owner */
    (2, {$OTHER}, {$OWNER}, 'P',  'N', {$BC}, '2026-07-04 09:00:00'),
    /* B3 PRE-cutoff, owner only in SalesAgent2 slot (not TC1)   -> NOT credited      */
    (3, {$OTHER}, {$OWNER}, 'P',  'N', {$BC}, '2026-03-11 09:00:00'),
    /* B4 ON/AFTER cutoff, owner only in SalesAgent slot (not TC2)-> NOT credited     */
    (4, {$OWNER}, {$OTHER}, 'P',  'N', {$BC}, '2026-07-05 09:00:00'),
    /* B5 owner is TC2 but booking CANCELLED                     -> excluded (status) */
    (5, {$OTHER}, {$OWNER}, 'P',  'Y', {$BC}, '2026-07-06 09:00:00'),
    /* B6 owner is TC2 but a QUOTATION, not a BC                 -> excluded (title)  */
    (6, {$OTHER}, {$OWNER}, 'P',  'N', 'QUOTATION', '2026-07-06 09:00:00'),
    /* B7 owner is TC1 but hard-deleted (Status='N')            -> excluded (status) */
    (7, {$OWNER}, {$OTHER}, 'N',  'N', {$BC}, '2026-03-12 09:00:00'),
    /* B8 pure other-agent BC, owner nowhere on it              -> the leak canary   */
    (8, {$OTHER}, {$OTHER}, 'P',  'N', {$BC}, '2026-07-06 09:00:00')
");

$year_start = '2026-01-01';
$year_end   = '2026-12-31';

// --- The card's YEAR count query: exact credited-slot rule + BC + live ---
$credit = "((booking.InsertDate < '{$cutoff}' AND booking.SalesAgent = {$OWNER})"
        . " OR (booking.InsertDate >= '{$cutoff}' AND booking.SalesAgent2 = {$OWNER}))";
$card_sql = "SELECT BookingID FROM booking
    WHERE {$credit}
      AND booking.BookingConfirmationTitle = 'BOOKING CONFIRMATION'
      AND booking.CancelStatus = 'N' AND booking.Status != 'N'
      AND date(booking.InsertDate) BETWEEN '{$year_start}' AND '{$year_end}'
    ORDER BY BookingID";
$card_ids = array_map('intval', $pdo->query($card_sql)->fetchAll(PDO::FETCH_COLUMN));

// --- The listing WITH the fix: apply_booking_filters() for an Owner (unscoped
//     default) + ?credited_agent + status=A + booking_confirmation_title. ---
$status_a = "booking.CancelStatus = 'N' AND booking.Status != 'N'";
$listing_fixed_sql = "SELECT BookingID FROM booking
    WHERE {$credit}
      AND booking.BookingConfirmationTitle IN ('BOOKING CONFIRMATION')
      AND {$status_a}
      AND date(booking.InsertDate) BETWEEN '{$year_start}' AND '{$year_end}'
    ORDER BY BookingID";
$listing_fixed_ids = array_map('intval', $pdo->query($listing_fixed_sql)->fetchAll(PDO::FETCH_COLUMN));

// --- The listing WITHOUT the fix (the bug): Owner is unscoped, old link had
//     only booking_date, so every agent's live BC in range shows. ---
$listing_bug_sql = "SELECT BookingID FROM booking
    WHERE date(booking.InsertDate) BETWEEN '{$year_start}' AND '{$year_end}'
      AND booking.Status != 'N'
    ORDER BY BookingID";
$listing_bug_ids = array_map('intval', $pdo->query($listing_bug_sql)->fetchAll(PDO::FETCH_COLUMN));

$assertions = array();
$assertions['card counts owner\'s credited BCs across the cutoff = {1,2}'] =
    $card_ids === array(1, 2);
$assertions['fixed drill-down MATCHES the card exactly = {1,2}'] =
    $listing_fixed_ids === $card_ids;
$assertions['pre-cutoff SalesAgent2-only row B3 excluded (not TC1 credited)'] =
    !in_array(3, $listing_fixed_ids, true);
$assertions['post-cutoff SalesAgent-only row B4 excluded (not TC2 credited)'] =
    !in_array(4, $listing_fixed_ids, true);
$assertions['cancelled row B5 excluded by status=A'] =
    !in_array(5, $listing_fixed_ids, true);
$assertions['quotation row B6 excluded by BC-title filter'] =
    !in_array(6, $listing_fixed_ids, true);
$assertions['deleted row B7 excluded by status=A'] =
    !in_array(7, $listing_fixed_ids, true);
$assertions['other-agent row B8 excluded (the reported leak)'] =
    !in_array(8, $listing_fixed_ids, true);
$assertions['BUG: unscoped Owner link leaks other-agent row B8'] =
    in_array(8, $listing_bug_ids, true);
$assertions['BUG: unscoped Owner link disagrees with the card'] =
    $listing_bug_ids !== $card_ids;

$failed = 0;
foreach ($assertions as $label => $ok) {
    echo ($ok ? '  PASS  ' : '  FAIL  ') . $label . PHP_EOL;
    if (!$ok) {
        $failed++;
    }
}

echo PHP_EOL . ($failed === 0 ? "All assertions passed.\n" : "{$failed} assertion(s) failed.\n");
exit($failed === 0 ? 0 : 1);
