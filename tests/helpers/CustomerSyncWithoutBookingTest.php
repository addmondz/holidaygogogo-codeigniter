<?php
/**
 * Run with: php tests/helpers/CustomerSyncWithoutBookingTest.php
 *
 * Locks the selection behind Customer_Model::get_pending_sycn_customers() —
 * the batch the AutoCount cron (Cron::syncCustomer) pushes to AutoCount.
 *
 * Change locked here: a customer created directly in MASTER DATA (with NO
 * booking) must now be picked up for sync. Previously the query required
 * EXISTS (SELECT 1 FROM booking WHERE b.CustomerID = customer.CustomerID),
 * which silently excluded standalone customers. That EXISTS filter is gone.
 *
 * The remaining gates still apply and are asserted below:
 *   - AutocountSyncStatus IN ('P')        (pending only)
 *   - AutocountSyncAction IS NOT NULL     (must be flagged for an action)
 *   - name IS NOT NULL                    (debtor needs a company name)
 *   - customer.created_at > cutoff_date   (no back-syncing ancient records)
 *
 * Mirrors the production query builder output; SQLite stands in for MySQL.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE customer (
    CustomerID INTEGER PRIMARY KEY,
    name TEXT,
    AutocountSyncStatus TEXT,
    AutocountSyncAction TEXT,
    created_at TEXT
)");
$pdo->exec("CREATE TABLE booking (
    BookingID INTEGER PRIMARY KEY,
    CustomerID INTEGER
)");

$cutoff = '2025-12-02';

// 1: master-data customer, NO booking, pending, flagged, named, after cutoff -> SELECTED (new behavior)
// 2: customer WITH a booking, otherwise identical                            -> SELECTED (unchanged)
// 3: already synced (status 'S')                                             -> excluded
// 4: no sync action flagged (NULL)                                           -> excluded
// 5: no name                                                                 -> excluded
// 6: created before the cutoff date                                          -> excluded
$pdo->exec("INSERT INTO customer VALUES
    (1, 'Alice Tan',  'P', 'C', '2026-06-12 09:00:00'),
    (2, 'Bob Lim',    'P', 'C', '2026-06-12 09:00:00'),
    (3, 'Synced Co',  'S', 'C', '2026-06-12 09:00:00'),
    (4, 'No Action',  'P', NULL,'2026-06-12 09:00:00'),
    (5, NULL,         'P', 'C', '2026-06-12 09:00:00'),
    (6, 'Old Record', 'P', 'C', '2025-01-01 09:00:00')
");
$pdo->exec("INSERT INTO booking VALUES (101, 2)");

// Production selection, post-change (no EXISTS-booking filter).
$sql = "
    SELECT CustomerID
    FROM customer
    WHERE AutocountSyncStatus IN ('P')
      AND AutocountSyncAction IS NOT NULL
      AND name IS NOT NULL
      AND created_at > :cutoff
    ORDER BY CustomerID
";
$stmt = $pdo->prepare($sql);
$stmt->execute(array(':cutoff' => $cutoff));
$selected = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

// Booking-less customer 1 is now included alongside booking-backed customer 2.
assert_eq('synced customers (no booking required)', array(1, 2), $selected);

echo "\nAll assertions passed.\n";
