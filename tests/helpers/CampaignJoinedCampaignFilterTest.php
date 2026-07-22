<?php
/**
 * Run with: php tests/helpers/CampaignJoinedCampaignFilterTest.php
 *
 * Pins the Campaign guest picker's "Joined Campaign" filter: picking an
 * existing campaign narrows the Available Guests to only the people already
 * in that campaign's roster (the campaign_guests snapshot pivot).
 *
 * The model keys the match on dedup_key so it matches the same person across
 * every one of their bookings, via:
 *   EXISTS (SELECT 1 FROM campaign_guests cg
 *           WHERE cg.CampaignID = ? AND cg.DedupKey = gl.dedup_key)
 *
 * This test mirrors that predicate against SQLite (CONCAT etc. avoided) so the
 * filter logic is exercised without booting CodeIgniter.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE booking (BookingID INTEGER PRIMARY KEY, Status TEXT, CancelStatus TEXT)");
$pdo->exec("CREATE TABLE guest_list (GuestListID INTEGER PRIMARY KEY, BookingID INTEGER, Status TEXT, dedup_key TEXT, Name TEXT)");
$pdo->exec("CREATE TABLE campaign_guests (CampaignID INTEGER, DedupKey TEXT, PRIMARY KEY (CampaignID, DedupKey))");

// Two active bookings, three guests. Alice appears on BOTH bookings under the
// same dedup_key 'A' (same person, two trips).
$pdo->exec("INSERT INTO booking (BookingID, Status, CancelStatus) VALUES
    (1, 'A', 'N'),
    (2, 'A', 'N')");
$pdo->exec("INSERT INTO guest_list (GuestListID, BookingID, Status, dedup_key, Name) VALUES
    (1, 1, 'Y', 'A', 'Alice'),
    (2, 1, 'Y', 'B', 'Bob'),
    (3, 2, 'Y', 'A', 'Alice'),
    (4, 2, 'Y', 'C', 'Carol')");

// Campaign 7 roster = Alice + Bob. Campaign 8 roster = Carol only.
$pdo->exec("INSERT INTO campaign_guests (CampaignID, DedupKey) VALUES
    (7, 'A'),
    (7, 'B'),
    (8, 'C')");

$sql =
    "SELECT DISTINCT gl.dedup_key
     FROM booking b
     JOIN guest_list gl ON gl.BookingID = b.BookingID AND gl.Status = 'Y'
     WHERE b.Status != 'N' AND b.CancelStatus = 'N'
       AND EXISTS (SELECT 1 FROM campaign_guests cg
                   WHERE cg.CampaignID = :cid AND cg.DedupKey = gl.dedup_key)
     ORDER BY gl.dedup_key";

$fetch_keys = function ($cid) use ($pdo, $sql) {
    $st = $pdo->prepare($sql);
    $st->execute(array(':cid' => $cid));
    return $st->fetchAll(PDO::FETCH_COLUMN, 0);
};

// Campaign 7 → only Alice (A) and Bob (B); Carol (C) excluded even though she
// is on an active booking. Alice appears once despite her two bookings.
assert_eq('campaign 7 members', array('A', 'B'), $fetch_keys(7));

// Campaign 8 → only Carol.
assert_eq('campaign 8 members', array('C'), $fetch_keys(8));

// A campaign with no roster returns nobody.
assert_eq('empty campaign members', array(), $fetch_keys(99));

echo "\nAll assertions passed.\n";
