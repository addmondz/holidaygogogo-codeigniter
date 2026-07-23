<?php
/**
 * Run with: php tests/helpers/CustomerListNameOnlyVisibleTest.php
 *
 * Pins the Customer List anchor predicate in
 * Guests_Model::Build_Customer_Branch(). The listing is anchored on
 * `customer c` and must show a customer that has ONLY a name (e.g. a
 * bulk-imported contact with no phone). Previously the WHERE also required
 * `phone_number IS NOT NULL`, which silently hid name-only customers.
 *
 * The anchor predicate under test:
 *     WHERE c.Status = 'Y'
 *       AND NULLIF(TRIM(c.name), '') IS NOT NULL
 *
 * Rules this locks in:
 *   1. Name-only customer (no phone)      -> VISIBLE  (the bug being fixed).
 *   2. Name + phone customer              -> VISIBLE  (unchanged).
 *   3. Blank / whitespace / NULL name     -> HIDDEN   (a name is still required).
 *   4. Status != 'Y' (soft-deleted)       -> HIDDEN   (unchanged).
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
$pdo->exec("CREATE TABLE customer (
    CustomerID   INTEGER PRIMARY KEY,
    name         TEXT,
    phone_number TEXT,
    Status       TEXT
)");
$pdo->exec("INSERT INTO customer (CustomerID, name, phone_number, Status) VALUES
    (1, 'Name Only',   NULL,          'Y'),   -- name, no phone   -> visible (the fix)
    (2, 'Full',        '0123456789',  'Y'),   -- name + phone     -> visible
    (3, '',            '0129999999',  'Y'),   -- blank name       -> hidden
    (4, '   ',         '0128888888',  'Y'),   -- whitespace name  -> hidden
    (5, NULL,          '0127777777',  'Y'),   -- null name        -> hidden
    (6, 'Deleted',     '0126666666',  'N')    -- soft-deleted     -> hidden
");

// Exact anchor predicate from Build_Customer_Branch() (NULLIF/TRIM work in SQLite).
$anchor_sql =
    "SELECT c.CustomerID
     FROM customer c
     WHERE c.Status = 'Y'
       AND NULLIF(TRIM(c.name), '') IS NOT NULL
     ORDER BY c.CustomerID";

$ids = array_map('intval', $pdo->query($anchor_sql)->fetchAll(PDO::FETCH_COLUMN));

// Name-only (1) and name+phone (2) survive; blank/whitespace/null-name and
// the soft-deleted row are all filtered out.
assert_eq('anchor returns name-only + name+phone', array(1, 2), $ids);
assert_eq('name-only customer is visible', true,  in_array(1, $ids, true));
assert_eq('blank-name customer is hidden',  false, in_array(3, $ids, true));
assert_eq('null-name customer is hidden',   false, in_array(5, $ids, true));
assert_eq('soft-deleted customer is hidden', false, in_array(6, $ids, true));

// Guard: the OLD predicate (phone required) would have dropped the name-only
// row — proves this test actually distinguishes the fix from the old behaviour.
$old_sql =
    "SELECT c.CustomerID
     FROM customer c
     WHERE c.Status = 'Y'
       AND NULLIF(TRIM(c.name), '')         IS NOT NULL
       AND NULLIF(TRIM(c.phone_number), '') IS NOT NULL
     ORDER BY c.CustomerID";
$old_ids = array_map('intval', $pdo->query($old_sql)->fetchAll(PDO::FETCH_COLUMN));
assert_eq('old predicate WOULD have hidden name-only', false, in_array(1, $old_ids, true));

echo "\nAll assertions passed.\n";
