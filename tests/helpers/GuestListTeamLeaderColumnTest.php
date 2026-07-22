<?php
/**
 * Run with: php tests/helpers/GuestListTeamLeaderColumnTest.php
 *
 * Covers guest_list_split_team_leaders() — the pure formatter behind the Guest
 * List "Team Leader" column. The column shows the booking name as per the BC
 * form (booking.Customer) for every team member; a guest grouped across several
 * bookings can carry several leader names, so the raw GROUP_CONCAT value must be
 * trimmed, de-duplicated and cleaned before display.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require_once __DIR__ . '/../../application/helpers/guest_contact_helper.php';

$assertions = array();

// Single name -> one entry -------------------------------------------------
$assertions['single name'] =
    guest_list_split_team_leaders('Ali Bin Abu') === array('Ali Bin Abu');

// Multiple distinct bookings -> keep all, first-seen order -----------------
$assertions['multiple leaders'] =
    guest_list_split_team_leaders('Ali||Siti||Chong') === array('Ali', 'Siti', 'Chong');

// Duplicate leader (same person leads two bookings) collapses --------------
$assertions['dedupes exact'] =
    guest_list_split_team_leaders('Ali||Ali') === array('Ali');

// Dedup is case-insensitive, keeps first-seen casing ----------------------
$assertions['dedupes case-insensitively'] =
    guest_list_split_team_leaders('Ali||ali') === array('Ali');

// Whitespace trimmed, blank segments dropped ------------------------------
$assertions['trims and drops blanks'] =
    guest_list_split_team_leaders('  Ali  || || Siti ') === array('Ali', 'Siti');

// Empty / null input -> empty list ----------------------------------------
$assertions['empty string -> empty'] =
    guest_list_split_team_leaders('') === array();
$assertions['null -> empty'] =
    guest_list_split_team_leaders(null) === array();
$assertions['only separators -> empty'] =
    guest_list_split_team_leaders('||||') === array();

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
