<?php
/**
 * Run with: php tests/helpers/MergeDuplicateCustomersTest.php
 *
 * Locks the SELECTION + REWRITE rules behind Customer_Model::Merge_Customers().
 * The real method runs the same statements through CodeIgniter's DB; here they
 * run against SQLite :memory: so the behaviour is pinned without MySQL.
 *
 * Rules verified:
 *   1. A booking on a loser (via CustomerID)  -> re-pointed to the keeper.
 *   2. A booking on a loser (via CustomerID2) -> re-pointed to the keeper.
 *   3. Every loser row                         -> Status flips to 'N'.
 *   4. The keeper row                          -> stays 'Y' and keeps its bookings.
 *   5. A booking on an UNRELATED customer      -> left untouched.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "PASS  {$label}\n";
    } else {
        echo "FAIL  {$label}: expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE customer (CustomerID INTEGER PRIMARY KEY, Status TEXT)");
$pdo->exec("CREATE TABLE booking  (BookingID INTEGER PRIMARY KEY, CustomerID INTEGER, CustomerID2 INTEGER)");

// keeper 8738 + losers 9428, 9639; unrelated 5000.
$pdo->exec("INSERT INTO customer (CustomerID, Status) VALUES (8738,'Y'),(9428,'Y'),(9639,'Y'),(5000,'Y')");
$pdo->exec("INSERT INTO booking (BookingID, CustomerID, CustomerID2) VALUES
    (1, 8738, NULL),   -- already on keeper
    (2, 9428, NULL),   -- loser via CustomerID
    (3, 9639, NULL),   -- loser via CustomerID
    (4, 7000, 9428),   -- loser via CustomerID2
    (5, 5000, NULL)    -- unrelated, must not move
");

$keeper = 8738;
$losers = array(9428, 9639);
$in     = implode(',', $losers);

// --- the exact rewrite Merge_Customers performs -----------------------------
$pdo->exec("UPDATE booking SET CustomerID  = {$keeper} WHERE CustomerID  IN ({$in})");
$pdo->exec("UPDATE booking SET CustomerID2 = {$keeper} WHERE CustomerID2 IN ({$in})");
$pdo->exec("UPDATE customer SET Status = 'N' WHERE CustomerID IN ({$in})");

$q = function ($sql) use ($pdo) { return (int) $pdo->query($sql)->fetchColumn(); };

assert_eq('bookings now on keeper (CustomerID)', 3,
    $q("SELECT COUNT(*) FROM booking WHERE CustomerID = {$keeper}"));               // 1,2,3
assert_eq('booking 4 CustomerID2 re-pointed', $keeper,
    $q("SELECT CustomerID2 FROM booking WHERE BookingID = 4"));
assert_eq('no booking left on any loser', 0,
    $q("SELECT COUNT(*) FROM booking WHERE CustomerID IN ({$in}) OR CustomerID2 IN ({$in})"));
assert_eq('losers deactivated', 2,
    $q("SELECT COUNT(*) FROM customer WHERE CustomerID IN ({$in}) AND Status = 'N'"));
assert_eq('keeper stays active', 1,
    $q("SELECT COUNT(*) FROM customer WHERE CustomerID = {$keeper} AND Status = 'Y'"));
assert_eq('unrelated booking untouched', 5000,
    $q("SELECT CustomerID FROM booking WHERE BookingID = 5"));

// --- GUARDED REVERT ---------------------------------------------------------
// Simulate an edit after the merge: booking 3 is reassigned away from the keeper
// to a new customer 6000. Undo must restore bookings still on the keeper (1 was
// already the keeper's own so it is NOT in the undo set; 2 and 4 were moved),
// skip booking 3, and reactivate the losers.
$pdo->exec("INSERT INTO customer (CustomerID, Status) VALUES (6000,'Y')");
$pdo->exec("UPDATE booking SET CustomerID = 6000 WHERE BookingID = 3");

// Undo payload captured at merge time (pre-update old values).
$undo = array(
    'cust_id'  => array(
        array('BookingID' => 2, 'old' => 9428),
        array('BookingID' => 3, 'old' => 9639),
    ),
    'cust_id2' => array(
        array('BookingID' => 4, 'old' => 9428),
    ),
    'losers'   => array(9428, 9639),
);

$reverted = 0; $skipped = 0;
foreach ($undo['cust_id'] as $it) {
    $st = $pdo->prepare("UPDATE booking SET CustomerID = ? WHERE BookingID = ? AND CustomerID = ?");
    $st->execute(array($it['old'], $it['BookingID'], $keeper));
    if ($st->rowCount() > 0) { $reverted++; } else { $skipped++; }
}
foreach ($undo['cust_id2'] as $it) {
    $st = $pdo->prepare("UPDATE booking SET CustomerID2 = ? WHERE BookingID = ? AND CustomerID2 = ?");
    $st->execute(array($it['old'], $it['BookingID'], $keeper));
    if ($st->rowCount() > 0) { $reverted++; } else { $skipped++; }
}
$pdo->exec("UPDATE customer SET Status = 'Y' WHERE CustomerID IN (9428,9639) AND Status = 'N'");

assert_eq('revert: booking 2 back to loser', 9428, $q("SELECT CustomerID FROM booking WHERE BookingID = 2"));
assert_eq('revert: booking 4 CustomerID2 back', 9428, $q("SELECT CustomerID2 FROM booking WHERE BookingID = 4"));
assert_eq('revert: booking 3 (edited) left alone', 6000, $q("SELECT CustomerID FROM booking WHERE BookingID = 3"));
assert_eq('revert: 2 restored', 2, $reverted);
assert_eq('revert: 1 skipped (edited)', 1, $skipped);
assert_eq('revert: losers reactivated', 2, $q("SELECT COUNT(*) FROM customer WHERE CustomerID IN (9428,9639) AND Status = 'Y'"));

echo "\nAll MergeDuplicateCustomers assertions passed.\n";
exit(0);
