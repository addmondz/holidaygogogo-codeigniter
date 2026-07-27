<?php
/**
 * Run with: php tests/helpers/GuestListTeamLeaderLinksTest.php
 *
 * Covers guest_list_team_leader_links() — the parser behind the Guest List
 * "Team Leader" column links. Each leader name is shown as a link that reloads
 * the list filtered to the exact booking(s) that leader ran (by BookingID), so
 * clicking a leader on one row shows only that booking's team members — not
 * every booking that person has ever led.
 *
 * The raw value is a GROUP_CONCAT of "BookingID:LeaderName" pairs ('||'
 * separated). A guest grouped across several bookings can carry several pairs,
 * possibly the same leader on more than one booking, so pairs are grouped by
 * leader name (first-seen order, case-insensitive) with their BookingIDs
 * collected together.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require_once __DIR__ . '/../../application/helpers/guest_contact_helper.php';

$assertions = array();

// Single pair -> one leader with one booking ------------------------------
$assertions['single pair'] =
    guest_list_team_leader_links('12:Ali Bin Abu')
    === array(array('name' => 'Ali Bin Abu', 'booking_ids' => array('12')));

// Same leader across two bookings -> one entry, two ids -------------------
$assertions['same leader two bookings'] =
    guest_list_team_leader_links('12:Ho Kong Wah||34:Ho Kong Wah')
    === array(array('name' => 'Ho Kong Wah', 'booking_ids' => array('12', '34')));

// Distinct leaders keep order and their own ids ---------------------------
$assertions['distinct leaders'] =
    guest_list_team_leader_links('12:Ali||34:Siti')
    === array(
        array('name' => 'Ali',  'booking_ids' => array('12')),
        array('name' => 'Siti', 'booking_ids' => array('34')),
    );

// Case-insensitive grouping, keeps first-seen casing ----------------------
$assertions['case-insensitive group'] =
    guest_list_team_leader_links('12:Ali||34:ali')
    === array(array('name' => 'Ali', 'booking_ids' => array('12', '34')));

// Name containing a colon keeps everything after the first colon ----------
$assertions['name with colon'] =
    guest_list_team_leader_links('99:Tan: The Boss')
    === array(array('name' => 'Tan: The Boss', 'booking_ids' => array('99')));

// Blank name segments are dropped -----------------------------------------
$assertions['drops blank names'] =
    guest_list_team_leader_links('12:Ali||34:   ||56:Siti')
    === array(
        array('name' => 'Ali',  'booking_ids' => array('12')),
        array('name' => 'Siti', 'booking_ids' => array('56')),
    );

// Segment without an id (no colon) is skipped -----------------------------
$assertions['skips id-less segment'] =
    guest_list_team_leader_links('Ali||34:Siti')
    === array(array('name' => 'Siti', 'booking_ids' => array('34')));

// Empty / null input -> empty list ----------------------------------------
$assertions['empty string -> empty'] = guest_list_team_leader_links('') === array();
$assertions['null -> empty']         = guest_list_team_leader_links(null) === array();

// Duplicate id for same leader collapses ----------------------------------
$assertions['dedupes ids'] =
    guest_list_team_leader_links('12:Ali||12:Ali')
    === array(array('name' => 'Ali', 'booking_ids' => array('12')));

// --- report ---------------------------------------------------------------
$failed = 0;
foreach ($assertions as $name => $ok) {
    if (!$ok) { $failed++; echo "FAIL: {$name}\n"; }
}
if ($failed === 0) {
    echo "OK: all " . count($assertions) . " assertions passed\n";
    exit(0);
}
echo "\n{$failed} assertion(s) failed\n";
exit(1);
