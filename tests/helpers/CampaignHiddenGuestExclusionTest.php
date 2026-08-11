<?php
/**
 * Run with: php tests/helpers/CampaignHiddenGuestExclusionTest.php
 *
 * Pins the PER-CAMPAIGN "Show in picker" opt-out. When the campaign audience
 * picker reads for a campaign being edited (campaign_id > 0), every branch
 * appends:
 *
 *   AND NOT EXISTS (SELECT 1 FROM campaign_hidden_guests chg
 *                   WHERE chg.CampaignID = ? AND chg.DedupKey = <branch key>)
 *
 * so a person hidden FOR THAT CAMPAIGN disappears from its picker, keyed by
 * dedup_key across the booking-guest branch (gl.dedup_key) and the customer
 * branch (last-9 of c.phone_number). A hide for campaign A must NOT affect
 * campaign B, and Create mode (id 0 -> no clause) shows everyone. Mirrored
 * against SQLite without booting CodeIgniter.
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

$pdo->exec("CREATE TABLE guest_list (dedup_key TEXT, Name TEXT)");
$pdo->exec("CREATE TABLE customer (CustomerID INTEGER PRIMARY KEY, Status TEXT, name TEXT, phone_number TEXT)");
$pdo->exec("CREATE TABLE campaign_hidden_guests (CampaignID INTEGER, DedupKey TEXT, PRIMARY KEY (CampaignID, DedupKey))");

$pdo->exec("INSERT INTO guest_list (dedup_key, Name) VALUES
    ('123456789', 'Serap Kaya'),
    ('987654321', 'Arwa Adnan')");
$pdo->exec("INSERT INTO customer (CustomerID, Status, name, phone_number) VALUES
    (1, 'Y', 'Serap Kaya',  '+60 12-345 6789'),
    (2, 'Y', 'Yap Chui Wah','+60 128003299')");
// Serap (key 123456789) is opted out of CAMPAIGN 10 only.
$pdo->exec("INSERT INTO campaign_hidden_guests (CampaignID, DedupKey) VALUES (10, '123456789')");

$pdo->sqliteCreateFunction('digits', function ($s) {
    return preg_replace('/[^0-9]/', '', (string)$s);
}, 1);
$cust_key = "NULLIF(substr(digits(c.phone_number), -9), '')";

// $campaign_id = 0 emulates "off" (Create mode / normal listing): no clause.
$booking = function ($campaign_id) use ($pdo) {
    $excl = $campaign_id > 0
        ? " AND NOT EXISTS (SELECT 1 FROM campaign_hidden_guests chg WHERE chg.CampaignID = {$campaign_id} AND chg.DedupKey = gl.dedup_key) "
        : '';
    return $pdo->query("SELECT gl.Name FROM guest_list gl WHERE 1=1 {$excl} ORDER BY gl.Name")
               ->fetchAll(PDO::FETCH_COLUMN, 0);
};
$customer = function ($campaign_id) use ($pdo, $cust_key) {
    $excl = $campaign_id > 0
        ? " AND NOT EXISTS (SELECT 1 FROM campaign_hidden_guests chg WHERE chg.CampaignID = {$campaign_id} AND chg.DedupKey = {$cust_key}) "
        : '';
    return $pdo->query("SELECT c.name FROM customer c
            WHERE c.Status = 'Y' AND NULLIF(TRIM(c.name), '') IS NOT NULL {$excl}
            ORDER BY c.name")->fetchAll(PDO::FETCH_COLUMN, 0);
};

// Off (Create mode / normal listing) → everyone shows.
assert_eq('booking off keeps everyone', array('Arwa Adnan', 'Serap Kaya'), $booking(0));
assert_eq('customer off keeps everyone', array('Serap Kaya', 'Yap Chui Wah'), $customer(0));

// Editing CAMPAIGN 10 → Serap (hidden for 10) is dropped from both branches.
assert_eq('booking campaign 10 drops Serap', array('Arwa Adnan'), $booking(10));
assert_eq('customer campaign 10 drops Serap', array('Yap Chui Wah'), $customer(10));

// Editing CAMPAIGN 20 → Serap is NOT hidden there, so she stays (per-campaign).
assert_eq('booking campaign 20 keeps Serap', array('Arwa Adnan', 'Serap Kaya'), $booking(20));
assert_eq('customer campaign 20 keeps Serap', array('Serap Kaya', 'Yap Chui Wah'), $customer(20));

echo "\nAll assertions passed.\n";
