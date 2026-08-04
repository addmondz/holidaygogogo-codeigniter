<?php
/**
 * Run with: php tests/helpers/CustomerGuestSnapshotSyncTest.php
 *
 * Pins the denormalised customer "self guest" snapshot that replaced the
 * Customer List's per-request join/EXISTS against the multi-million-row
 * guest_list table (which was timing out). Customer_Model::Refresh_Snapshot_*
 * copies each customer's self guest record (Gender / DateOfBirth / Nationality /
 * GuestType) onto customer.* so the list filters/sorts on the small customer
 * table instead.
 *
 * This locks in the SELECTION RULE the sync uses — the self record is the
 * ACTIVE (Status='Y') guest_list row whose dedup_key matches the customer's
 * phone key, LOWEST GuestListID. The SQL below is the same derived-table pick
 * the real UPDATE uses; only the MySQL-only REGEXP key normalisation is
 * pre-computed into columns so SQLite can run it (that normalisation is already
 * covered by guest_contact_normalize_key's own tests).
 *
 * Rules verified:
 *   1. Multiple active rows  -> lowest GuestListID wins.
 *   2. Only a deleted row     -> snapshot is NULL (self-heals deletes).
 *   3. No guest row at all     -> snapshot is NULL.
 *   4. Two customers, one key -> both get the same snapshot.
 *   5. Birthday-filter helper emits the customer column when asked.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label}\n";
    } else {
        echo "  FAIL  {$label}:\n    expected " . var_export($expected, true)
           . "\n    got      " . var_export($actual, true) . "\n";
        exit(1);
    }
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// phone_key / dedup_key stand in for the last-9-digit REGEXP key.
$pdo->exec("CREATE TABLE customer (CustomerID INTEGER PRIMARY KEY, phone_key TEXT)");
$pdo->exec("CREATE TABLE guest_list (
    GuestListID INTEGER PRIMARY KEY, dedup_key TEXT, Gender TEXT, DateOfBirth TEXT,
    Nationality INTEGER, Type TEXT, Status TEXT
)");

$pdo->exec("INSERT INTO customer (CustomerID, phone_key) VALUES
    (1,'111'),   -- two active guest rows -> lowest GuestListID wins
    (2,'222'),   -- only a deleted guest row -> NULL
    (3,'333'),   -- no guest row -> NULL
    (4,'555'),   -- shares key with customer 5
    (5,'555')    -- shares key with customer 4
");

$pdo->exec("INSERT INTO guest_list (GuestListID,dedup_key,Gender,DateOfBirth,Nationality,Type,Status) VALUES
    (10,'111','F','1990-05-01',60,'ADULT','Y'),   -- lowest id for 111 -> WINS
    (14,'111','M','1985-01-01',99,'CHILD','Y'),   -- higher id, ignored
    (20,'222','M','1970-02-02',60,'ADULT','N'),   -- deleted -> excluded
    (30,'555','F','2001-03-03',82,'CHILD','Y')    -- shared key self record
");

// The exact self-record derived table + assignment join from Refresh_Snapshot_*.
$sql = "
SELECT c.CustomerID AS id, g.Gender, g.DateOfBirth, g.Nationality, g.Type
FROM customer c
LEFT JOIN (
    SELECT dedup_key, Gender, DateOfBirth, Nationality, Type FROM (
        SELECT gl.dedup_key, gl.Gender, gl.DateOfBirth, gl.Nationality, gl.Type,
            ROW_NUMBER() OVER (PARTITION BY gl.dedup_key ORDER BY gl.GuestListID ASC) AS rn
        FROM guest_list gl
        WHERE gl.Status = 'Y' AND gl.dedup_key IS NOT NULL
    ) z WHERE z.rn = 1
) g ON g.dedup_key = c.phone_key
ORDER BY c.CustomerID";

$rows = array();
foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $rows[(int) $r['id']] = $r;
}

// 1. Lowest GuestListID wins for a key with multiple active rows.
assert_eq('cust1 Gender = F (lowest GuestListID)', 'F', $rows[1]['Gender']);
assert_eq('cust1 DOB = 1990-05-01', '1990-05-01', $rows[1]['DateOfBirth']);
assert_eq('cust1 Nationality = 60', '60', (string) $rows[1]['Nationality']);
assert_eq('cust1 GuestType = ADULT', 'ADULT', $rows[1]['Type']);

// 2. Only a deleted guest row -> NULL snapshot.
assert_eq('cust2 Gender NULL (only deleted guest)', null, $rows[2]['Gender']);
assert_eq('cust2 DOB NULL', null, $rows[2]['DateOfBirth']);

// 3. No guest row -> NULL snapshot.
assert_eq('cust3 Gender NULL (no guest row)', null, $rows[3]['Gender']);

// 4. Shared key -> both customers get the same snapshot.
assert_eq('cust4 Gender = F (shared key)', 'F', $rows[4]['Gender']);
assert_eq('cust5 Gender = F (same shared key)', 'F', $rows[5]['Gender']);
assert_eq('cust4 & cust5 identical DOB', $rows[4]['DateOfBirth'], $rows[5]['DateOfBirth']);
assert_eq('cust5 GuestType = CHILD', 'CHILD', $rows[5]['Type']);

// 5. Birthday-filter helper targets the customer column when asked (the Customer
//    List passes 'c.DateOfBirth'; the Guest List keeps the gl.DateOfBirth default).
require_once __DIR__ . '/../../application/helpers/guest_contact_helper.php';
$bc = guest_list_birthday_clause('this_month', 'c.DateOfBirth');
assert_eq('birthday clause uses c.DateOfBirth',
    " AND MONTH(c.DateOfBirth) = MONTH(CURDATE()) ", $bc['sql']);
$bc_default = guest_list_birthday_clause('this_month');
assert_eq('birthday clause default still gl.DateOfBirth',
    " AND MONTH(gl.DateOfBirth) = MONTH(CURDATE()) ", $bc_default['sql']);

echo "\nAll assertions passed.\n";
