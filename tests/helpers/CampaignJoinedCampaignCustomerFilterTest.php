<?php
/**
 * Run with: php tests/helpers/CampaignJoinedCampaignCustomerFilterTest.php
 *
 * Pins the Campaign guest picker's "Joined Campaign" filter on the CUSTOMER
 * branch (Type = Customer). Unlike the booking branch (which keys on
 * gl.dedup_key), the customer branch anchors on `customer c` and keys the
 * campaign_guests roster on the customer's OWN phone dedup key — the last 9
 * digits of c.phone_number — so it matches the same person across bookings:
 *
 *   {NOT }EXISTS (SELECT 1 FROM campaign_guests cg
 *                 WHERE cg.CampaignID IN (?) AND cg.DedupKey = <cust key>)
 *
 * This mirrors that predicate against SQLite so include/exclude and the
 * phone-key match are exercised without booting CodeIgniter.
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

$pdo->exec("CREATE TABLE customer (CustomerID INTEGER PRIMARY KEY, Status TEXT, name TEXT, phone_number TEXT)");
$pdo->exec("CREATE TABLE campaign_guests (CampaignID INTEGER, DedupKey TEXT, PRIMARY KEY (CampaignID, DedupKey))");

// Three customers. Serap's phone has punctuation/country code; its last 9
// digits ('123456789') are what the roster stores, proving the normalization.
$pdo->exec("INSERT INTO customer (CustomerID, Status, name, phone_number) VALUES
    (1, 'Y', 'Serap Kaya',  '+60 12-345 6789'),
    (2, 'Y', 'Yap Chui Wah','+60 128003299'),
    (3, 'Y', 'Arwa Adnan',  '')");

// Campaign 3 (test 3) roster = only Serap's normalized key.
$pdo->exec("INSERT INTO campaign_guests (CampaignID, DedupKey) VALUES
    (3, '123456789')");

// SQLite has no REGEXP_REPLACE/RIGHT; emulate the last-9-digits phone key the
// model builds (Customer_Dedup_Key_Expr) with a stripped-digits UDF + substr.
$pdo->sqliteCreateFunction('digits', function ($s) {
    return preg_replace('/[^0-9]/', '', (string)$s);
}, 1);
$key = "NULLIF(substr(digits(c.phone_number), -9), '')";

$run = function ($mode) use ($pdo, $key) {
    $neg = $mode === 'exclude' ? 'NOT ' : '';
    $sql =
        "SELECT c.name
         FROM customer c
         WHERE c.Status = 'Y' AND NULLIF(TRIM(c.name), '') IS NOT NULL
           AND {$neg}EXISTS (SELECT 1 FROM campaign_guests cg
                             WHERE cg.CampaignID IN (3) AND cg.DedupKey = {$key})
         ORDER BY c.name";
    return $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN, 0);
};

// Include → only Serap Kaya (the bug: without this clause all 3 returned).
assert_eq('include campaign 3', array('Serap Kaya'), $run('include'));

// Exclude → everyone EXCEPT Serap; the phone-less customer (NULL key) is kept
// because NOT EXISTS is true for her (she cannot be on a phone-keyed roster).
assert_eq('exclude campaign 3', array('Arwa Adnan', 'Yap Chui Wah'), $run('exclude'));

echo "\nAll assertions passed.\n";
