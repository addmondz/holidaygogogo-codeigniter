<?php
/**
 * Run with: php tests/helpers/CustomerListCancelledCallingCodeTest.php
 *
 * Pins the calling-code source for the Customer List in
 * Guests_Model::Customer_Calling_Code_Subquery(). The customer row stores only
 * a raw local phone number; the international code is borrowed from the
 * customer's bookings. Unlike the latest-booking table (active-only), the
 * calling-code table INCLUDES cancelled bookings so a customer whose only
 * booking was cancelled still shows a code — matching the Customer Portal
 * (e.g. VICTOR ORTEGO MARTIN, whose sole booking is cancelled Spain -> +34).
 *
 * Rules this locks in:
 *   1. Only-cancelled booking      -> code IS shown  (the bug being fixed).
 *   2. Active + cancelled bookings -> ACTIVE code wins (active preferred).
 *   3. No booking at all           -> no code (nothing to borrow).
 *   4. Deleted booking (Status='N')-> ignored.
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

$pdo->exec("CREATE TABLE customer (CustomerID INTEGER PRIMARY KEY, phone_number TEXT)");
$pdo->exec("CREATE TABLE booking (
    BookingID     INTEGER PRIMARY KEY,
    CustomerID    INTEGER,
    CountryCodeID INTEGER,
    Status        TEXT,
    CancelStatus  TEXT,
    InsertDate    TEXT
)");
$pdo->exec("CREATE TABLE country_code (CountryCodeID INTEGER PRIMARY KEY, CountryCode TEXT)");

$pdo->exec("INSERT INTO country_code (CountryCodeID, CountryCode) VALUES (1,'+60'),(31,'+34')");
$pdo->exec("INSERT INTO customer (CustomerID, phone_number) VALUES
    (100, '722144102'),   -- only a cancelled booking  -> +34
    (200, '169546738'),   -- active + newer cancelled   -> +60 (active wins)
    (300, '111222333')    -- no booking                 -> no code
");
$pdo->exec("INSERT INTO booking (BookingID, CustomerID, CountryCodeID, Status, CancelStatus, InsertDate) VALUES
    (1, 100, 31, 'P', 'Y', '2026-01-01'),   -- cancelled Spain
    (2, 200,  1, 'P', 'N', '2026-01-01'),   -- active Malaysia (older)
    (3, 200, 31, 'P', 'Y', '2026-06-01'),   -- cancelled Spain (newer)
    (4, 100, 31, 'N', 'Y', '2026-07-01')    -- deleted -> ignored
");

// Exact subquery + join from Guests_Model::Read_Customers_Rich (bcc / ccp).
$bcc =
    "(SELECT CustomerID, CountryCodeID FROM (
        SELECT b.CustomerID, b.CountryCodeID,
            ROW_NUMBER() OVER (PARTITION BY b.CustomerID
                ORDER BY (CASE WHEN b.CancelStatus = 'N' THEN 0 ELSE 1 END), b.InsertDate DESC, b.BookingID DESC) AS rn
        FROM booking b
        WHERE b.Status != 'N' AND b.CountryCodeID IS NOT NULL
    ) w WHERE w.rn = 1)";

$sql =
    "SELECT c.CustomerID, ccp.CountryCode AS CallingCode
     FROM customer c
     LEFT JOIN {$bcc} bcc ON bcc.CustomerID = c.CustomerID
     LEFT JOIN country_code ccp ON ccp.CountryCodeID = bcc.CountryCodeID
     ORDER BY c.CustomerID";

$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_KEY_PAIR);

assert_eq('only-cancelled booking still shows code', '+34', $rows[100]);
assert_eq('active booking preferred over newer cancelled', '+60', $rows[200]);
assert_eq('no booking -> no code', null, $rows[300]);

// Guard: the OLD active-only rule (CancelStatus='N') would have hidden 100's code.
$old_bcc =
    "(SELECT CustomerID, CountryCodeID FROM (
        SELECT b.CustomerID, b.CountryCodeID,
            ROW_NUMBER() OVER (PARTITION BY b.CustomerID ORDER BY b.InsertDate DESC, b.BookingID DESC) AS rn
        FROM booking b
        WHERE b.Status != 'N' AND b.CancelStatus = 'N'
    ) w WHERE w.rn = 1)";
$old_sql =
    "SELECT c.CustomerID, ccp.CountryCode AS CallingCode
     FROM customer c
     LEFT JOIN {$old_bcc} bcc ON bcc.CustomerID = c.CustomerID
     LEFT JOIN country_code ccp ON ccp.CountryCodeID = bcc.CountryCodeID
     WHERE c.CustomerID = 100";
$old = $pdo->query($old_sql)->fetch(PDO::FETCH_ASSOC);
assert_eq('old active-only rule WOULD have hidden the code', null, $old['CallingCode']);

echo "\nAll assertions passed.\n";
