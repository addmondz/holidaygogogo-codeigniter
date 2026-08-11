<?php
/**
 * Run with: php tests/helpers/CustomerDestinationFilterTest.php
 *
 * Locks the Customer-list Destination filter (Guests_Model::Build_Customer_Branch)
 * after attached destinations were merged into the Destination column. The filter
 * must match a customer when the chosen destination appears EITHER on one of their
 * bookings OR in their manually-attached destinations (customer_destination) — so
 * the filter agrees with what the merged column shows.
 */

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE customer (CustomerID INTEGER PRIMARY KEY, name TEXT, Status TEXT)");
$pdo->exec("CREATE TABLE booking (
    BookingID INTEGER PRIMARY KEY, CustomerID INTEGER, Destination INTEGER,
    Status TEXT, CancelStatus TEXT
)");
$pdo->exec("CREATE TABLE customer_destination (
    CustomerDestinationID INTEGER PRIMARY KEY AUTOINCREMENT,
    CustomerID INTEGER, CategoryID INTEGER, Status TEXT
)");

// 4 customers:
//  1 = booking to dest 10 only
//  2 = attached dest 10 only (NO booking)          <- new behaviour
//  3 = nothing for dest 10 (booking to 99, attached 88)
//  4 = attached dest 10 but link INACTIVE          <- must NOT match
$pdo->exec("INSERT INTO customer VALUES (1,'A','Y'),(2,'B','Y'),(3,'C','Y'),(4,'D','Y')");
$pdo->exec("INSERT INTO booking VALUES
    (100, 1, 10, 'Y', 'N'),
    (101, 3, 99, 'Y', 'N')");
$pdo->exec("INSERT INTO customer_destination (CustomerID, CategoryID, Status) VALUES
    (2, 10, 'Y'),
    (3, 88, 'Y'),
    (4, 10, 'N')");

/** Mirror of the combined destination predicate in Build_Customer_Branch. */
function customers_matching_destination($pdo, $dest_ids)
{
    $ph = implode(',', array_fill(0, count($dest_ids), '?'));
    $sql = "SELECT c.CustomerID FROM customer c
            WHERE c.Status = 'Y'
              AND ( EXISTS (
                    SELECT 1 FROM booking b
                    WHERE b.CustomerID = c.CustomerID AND b.Status != 'N' AND b.CancelStatus = 'N'
                      AND b.Destination IN ($ph)
                ) OR EXISTS (
                    SELECT 1 FROM customer_destination cd
                    WHERE cd.CustomerID = c.CustomerID AND cd.Status = 'Y'
                      AND cd.CategoryID IN ($ph)
                ) )
            ORDER BY c.CustomerID";
    $st = $pdo->prepare($sql);
    $st->execute(array_merge($dest_ids, $dest_ids)); // booking + attached bind sets
    $out = array();
    foreach ($st as $row) { $out[] = (int) $row['CustomerID']; }
    return $out;
}

$fail = 0;
function check($label, $expected, $actual) {
    global $fail;
    if ($expected === $actual) { echo "PASS: $label\n"; }
    else { $fail++; echo "FAIL: $label\n  expected: " . json_encode($expected)
        . "\n  actual:   " . json_encode($actual) . "\n"; }
}

// Filter by dest 10 -> customer 1 (booking) and 2 (attached); NOT 3, NOT 4 (inactive).
check('dest 10 matches booking + attached, excludes inactive',
    array(1, 2), customers_matching_destination($pdo, array(10)));

// Filter by dest 88 -> only customer 3 (attached).
check('dest 88 attached only', array(3), customers_matching_destination($pdo, array(88)));

// Filter by dest 99 -> only customer 3 (booking).
check('dest 99 booking only', array(3), customers_matching_destination($pdo, array(99)));

// Multi-select 10 + 88 -> customers 1, 2, 3.
check('multi-select union', array(1, 2, 3), customers_matching_destination($pdo, array(10, 88)));

// Unmatched dest -> none.
check('no match', array(), customers_matching_destination($pdo, array(777)));

echo $fail ? "\n$fail check(s) FAILED\n" : "\nAll checks passed\n";
exit($fail ? 1 : 0);
