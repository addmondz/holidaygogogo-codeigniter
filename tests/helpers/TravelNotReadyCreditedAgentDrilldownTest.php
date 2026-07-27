<?php
/**
 * Run with: php tests/helpers/TravelNotReadyCreditedAgentDrilldownTest.php
 *
 * Locks card-vs-listing parity for the agent "Travel in 7/14 Days – Not Yet
 * Ready" card drill-down when the viewer's default listing scope is broader
 * than the card's credited-slot count.
 *
 * The card counts confirmed BCs the viewer is *credited* for (SalesAgent
 * pre-2026-06-01 / SalesAgent2 on/after) that start travel inside the window
 * and are still upstream (Status IN P/PBO/PGL/PTV). The ?upcoming_not_ready
 * drill-down applies that credited scope ONLY for levels 20/50, so the Owner
 * (10, unscoped listing) previously saw EVERY agent's not-ready bookings —
 * same leak class as the BC Created card.
 *
 * The fix adds ?credited_agent=<me> to the Travel-card link; the generic
 * credited_agent predicate in apply_booking_filters() then scopes the Owner /
 * TC-Lead drill-down to the same set the card counted. Row B_OTHER is the
 * discriminator.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

$cutoff = '2026-06-01';   // LEAD_CONVERSION_TC2_CUTOFF_DATE
$OWNER  = 10;
$OTHER  = 77;

// Window: tomorrow .. +7 days (StartDate BETWEEN win_start AND win_end).
$win_start = '2026-07-07';
$win_end   = '2026-07-13';

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE booking (
    BookingID INTEGER PRIMARY KEY,
    SalesAgent INTEGER,
    SalesAgent2 INTEGER,
    Status TEXT,
    CancelStatus TEXT,
    BookingConfirmationTitle TEXT,
    InsertDate TEXT,
    StartDate TEXT
)");

$BC = "'BOOKING CONFIRMATION'";
// (ID, SA, SA2, Status, Cancel, Title, InsertDate, StartDate)
$pdo->exec("INSERT INTO booking VALUES
    /* B1 owner TC2 (post-cutoff), upstream P, starts in window       -> counted      */
    (1, {$OTHER}, {$OWNER}, 'P',   'N', {$BC}, '2026-07-01', '2026-07-08'),
    /* B2 owner TC1 (pre-cutoff), upstream PBO, starts in window       -> counted      */
    (2, {$OWNER}, {$OTHER}, 'PBO', 'N', {$BC}, '2026-03-02', '2026-07-10'),
    /* B3 owner credited but already READY (PT/Pending Travel)         -> excluded      */
    (3, {$OTHER}, {$OWNER}, 'PT',  'N', {$BC}, '2026-07-01', '2026-07-09'),
    /* B4 owner credited, upstream, but travel OUTSIDE window          -> excluded      */
    (4, {$OTHER}, {$OWNER}, 'P',   'N', {$BC}, '2026-07-01', '2026-08-01'),
    /* B5 owner only in NON-credited slot (SalesAgent post-cutoff)     -> excluded      */
    (5, {$OWNER}, {$OTHER}, 'P',   'N', {$BC}, '2026-07-01', '2026-07-08'),
    /* B_OTHER other agent's upstream in-window BC, owner nowhere      -> the leak canary*/
    (6, {$OTHER}, {$OTHER}, 'P',   'N', {$BC}, '2026-07-01', '2026-07-08')
");

$upstream = "booking.Status IN ('P','PBO','PGL','PTV')";
$credit   = "((booking.InsertDate < '{$cutoff}' AND booking.SalesAgent = {$OWNER})"
          . " OR (booking.InsertDate >= '{$cutoff}' AND booking.SalesAgent2 = {$OWNER}))";
$window   = "booking.StartDate >= '{$win_start}' AND booking.StartDate <= '{$win_end}'";
$live     = "booking.CancelStatus = 'N' AND booking.BookingConfirmationTitle = 'BOOKING CONFIRMATION'";

// --- The card's count query: credited slot + upstream + in-window ---
$card_sql = "SELECT BookingID FROM booking
    WHERE {$live} AND {$upstream} AND {$window} AND {$credit} ORDER BY BookingID";
$card_ids = array_map('intval', $pdo->query($card_sql)->fetchAll(PDO::FETCH_COLUMN));

// --- Listing WITH the fix: upcoming_not_ready conditions + ?credited_agent
//     (Owner default scope is unscoped, so credited_agent alone scopes it). ---
$listing_fixed_sql = "SELECT BookingID FROM booking
    WHERE {$live} AND {$upstream} AND {$window} AND {$credit} ORDER BY BookingID";
$listing_fixed_ids = array_map('intval', $pdo->query($listing_fixed_sql)->fetchAll(PDO::FETCH_COLUMN));

// --- Listing WITHOUT the fix (the bug): Owner unscoped, upcoming_not_ready
//     applies upstream + window + BC only, no credited scope -> leaks. ---
$listing_bug_sql = "SELECT BookingID FROM booking
    WHERE {$live} AND {$upstream} AND {$window} ORDER BY BookingID";
$listing_bug_ids = array_map('intval', $pdo->query($listing_bug_sql)->fetchAll(PDO::FETCH_COLUMN));

$assertions = array();
$assertions['card counts owner\'s credited not-ready in-window BCs = {1,2}'] =
    $card_ids === array(1, 2);
$assertions['fixed drill-down MATCHES the card exactly = {1,2}'] =
    $listing_fixed_ids === $card_ids;
$assertions['ready row B3 excluded'] = !in_array(3, $listing_fixed_ids, true);
$assertions['out-of-window row B4 excluded'] = !in_array(4, $listing_fixed_ids, true);
$assertions['non-credited-slot row B5 excluded'] = !in_array(5, $listing_fixed_ids, true);
$assertions['other-agent row B_OTHER excluded (the reported leak)'] =
    !in_array(6, $listing_fixed_ids, true);
$assertions['BUG: unscoped Owner link leaks other-agent row B_OTHER'] =
    in_array(6, $listing_bug_ids, true);
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
