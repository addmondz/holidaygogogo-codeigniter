<?php
/**
 * Run with: php tests/helpers/GuestListModeSplitTest.php
 *
 * Locks the two-page split of the Guest List: booking guests and GHL leads now
 * live on separate pages, each locked to ONE branch via guest_list_branches_to_run().
 *   - Guest List page (mode 'guest') reads ONLY the booking branch.
 *   - GHL Leads page  (mode 'ghl')   reads ONLY the GHL branch.
 * A filter that can never match the page's own branch still drops it to empty,
 * reusing the same suppression predicates the old merged listing used.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}
require __DIR__ . '/../../application/helpers/guest_contact_helper.php';

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

// ---- default (no filters): each page reads exactly its own branch ----------
$g = guest_list_branches_to_run('guest', array());
assert_eq('guest page runs bookings', true,  $g['bookings']);
assert_eq('guest page skips ghl',     false, $g['ghl']);

$h = guest_list_branches_to_run('ghl', array());
assert_eq('ghl page skips bookings',  false, $h['bookings']);
assert_eq('ghl page runs ghl',        true,  $h['ghl']);

// ---- unknown mode falls back to the booking (Guest List) page --------------
$f = guest_list_branches_to_run('', array());
assert_eq('empty mode runs bookings', true,  $f['bookings']);
assert_eq('empty mode skips ghl',     false, $f['ghl']);

// ---- 'all' mode (campaign picker): both branches run, no filters -----------
$a = guest_list_branches_to_run('all', array());
assert_eq('all mode runs bookings', true, $a['bookings']);
assert_eq('all mode runs ghl',      true, $a['ghl']);

// ---- 'all' mode still honours per-branch suppression: a booking-only -------
// ---- Destination drops the GHL branch but keeps the booking branch. ---------
$ad = guest_list_branches_to_run('all', array('destination' => '7'));
assert_eq('all mode keeps bookings with destination', true,  $ad['bookings']);
assert_eq('all mode drops ghl with destination',      false, $ad['ghl']);

// ---- 'all' mode with Guest Role = Team Leader keeps booking guests but ------
// ---- drops leads (a lead only ever holds the "Lead" role). ------------------
$al = guest_list_branches_to_run('all', array('role' => 'Team Leader'));
assert_eq('all mode keeps bookings for Team Leader', true,  $al['bookings']);
assert_eq('all mode drops ghl for Team Leader',      false, $al['ghl']);

// ---- booking-only filter empties the GHL page (a lead can't match it) ------
$hd = guest_list_branches_to_run('ghl', array('destination' => '7'));
assert_eq('destination empties ghl page', false, $hd['ghl']);

$hp = guest_list_branches_to_run('ghl', array('pax_min' => '2'));
assert_eq('pax_min empties ghl page',     false, $hp['ghl']);

// ---- Guest Type is booking-guest-only: it empties the GHL page but keeps ----
// ---- the Guest List page on its booking branch. -----------------------------
$ht = guest_list_branches_to_run('ghl', array('guest_type' => array('CHILD')));
assert_eq('guest_type empties ghl page',  false, $ht['ghl']);
$gt = guest_list_branches_to_run('guest', array('guest_type' => array('CHILD')));
assert_eq('guest_type keeps guest page',  true,  $gt['bookings']);

// ---- Guest Role = Lead empties the Guest List page (a guest isn't a Lead) --
$gl = guest_list_branches_to_run('guest', array('role' => 'Lead'));
assert_eq('role=Lead empties guest page', false, $gl['bookings']);

// ---- Campaign / Follow date are remark ranges: a GHL lead carries no guest --
// ---- remark, so either empties the GHL page but keeps the Guest List page. ---
$hcamp = guest_list_branches_to_run('ghl', array('campaign_date' => '01/07/2026 - 31/07/2026'));
assert_eq('campaign_date empties ghl page', false, $hcamp['ghl']);
$hfol = guest_list_branches_to_run('ghl', array('follow_date' => '01/07/2026 - 31/07/2026'));
assert_eq('follow_date empties ghl page',   false, $hfol['ghl']);
$gcamp = guest_list_branches_to_run('guest', array('campaign_date' => '01/07/2026 - 31/07/2026'));
assert_eq('campaign_date keeps guest page', true,  $gcamp['bookings']);

// ---- The leader-fallback branch (unfilled bookings) also can't hold a remark,
// ---- so a Campaign / Follow range must suppress it. -------------------------
assert_eq('campaign_date suppresses leader fallback', true,
    guest_list_leader_fallback_suppressed_by_filters(array('campaign_date' => '01/07/2026 - 31/07/2026')));
assert_eq('follow_date suppresses leader fallback', true,
    guest_list_leader_fallback_suppressed_by_filters(array('follow_date' => '01/07/2026 - 31/07/2026')));

// ---- a shared filter (contact) leaves each page on its own branch ----------
$gc = guest_list_branches_to_run('guest', array('contact_number' => '012'));
assert_eq('contact keeps guest page', true, $gc['bookings']);
$hc = guest_list_branches_to_run('ghl', array('contact_number' => '012'));
assert_eq('contact keeps ghl page',   true, $hc['ghl']);

echo "\nAll GuestListModeSplit assertions passed.\n";
