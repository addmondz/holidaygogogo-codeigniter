<?php
/**
 * Run with: php tests/helpers/CustomerDestinationSyncTest.php
 *
 * Locks the customer <-> destination attach behaviour behind Customer_Model's
 * Sync_Customer_Destinations() / Read_Customer_Destination_Ids(). A customer may
 * have MULTIPLE destinations manually attached on the edit form; they persist in
 * the customer_destination link table.
 *
 * Rules locked here (mirrors the model's SQL):
 *   1. Sync is a REPLACE — after saving, the customer's active links equal
 *      exactly the selected set (adds new, drops de-selected).
 *   2. Only real destination categories (category.IsDestination='YES', active)
 *      are stored; junk / non-destination CategoryIDs are dropped silently.
 *   3. An empty selection clears all links.
 *   4. Read returns the active CategoryIDs for one customer only.
 */

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE category (
    CategoryID INTEGER PRIMARY KEY,
    Name TEXT,
    IsDestination TEXT,
    Status TEXT
)");
$pdo->exec("CREATE TABLE customer_destination (
    CustomerDestinationID INTEGER PRIMARY KEY AUTOINCREMENT,
    CustomerID INTEGER,
    CategoryID INTEGER,
    Status TEXT
)");

// 10/11/12 are active destinations; 13 is a NON-destination; 14 is inactive.
$pdo->exec("INSERT INTO category VALUES
    (10, 'LANGKAWI', 'YES', 'Y'),
    (11, 'PENANG',   'YES', 'Y'),
    (12, 'SABAH',    'YES', 'Y'),
    (13, 'HQ',       'NO',  'Y'),
    (14, 'OLD DEST', 'YES', 'N')");

/** Mirror of Customer_Model::Sync_Customer_Destinations() SQL semantics. */
function sync_customer_destinations($pdo, $customer_id, $category_ids)
{
    if (empty($customer_id)) { return; }
    if (is_string($category_ids)) {                 // form posts a JSON string
        $decoded = json_decode($category_ids, true);
        $category_ids = is_array($decoded) ? $decoded : array();
    }

    // unique positive ints
    $wanted = array();
    foreach ((array) $category_ids as $cid) {
        $cid = (int) $cid;
        if ($cid > 0) { $wanted[$cid] = $cid; }
    }

    // keep only active destination categories
    $valid = array();
    if (!empty($wanted)) {
        $in  = implode(',', array_map('intval', array_values($wanted)));
        $sql = "SELECT CategoryID FROM category
                WHERE CategoryID IN ($in) AND IsDestination='YES' AND Status='Y'";
        foreach ($pdo->query($sql) as $row) {
            $valid[(int) $row['CategoryID']] = (int) $row['CategoryID'];
        }
    }

    // replace: drop all for this customer, then insert the validated set
    $del = $pdo->prepare("DELETE FROM customer_destination WHERE CustomerID = ?");
    $del->execute(array($customer_id));

    $ins = $pdo->prepare("INSERT INTO customer_destination (CustomerID, CategoryID, Status) VALUES (?, ?, 'Y')");
    foreach ($valid as $cid) {
        $ins->execute(array($customer_id, $cid));
    }
}

/** Mirror of Customer_Model::Read_Customer_Destination_Ids(). */
function read_customer_destination_ids($pdo, $customer_id)
{
    if (empty($customer_id)) { return array(); }
    $st = $pdo->prepare("SELECT CategoryID FROM customer_destination
                         WHERE CustomerID = ? AND Status = 'Y'");
    $st->execute(array($customer_id));
    $ids = array();
    foreach ($st as $row) { $ids[] = (int) $row['CategoryID']; }
    sort($ids);
    return $ids;
}

$fail = 0;
function check($label, $expected, $actual) {
    global $fail;
    if ($expected === $actual) {
        echo "PASS: $label\n";
    } else {
        $fail++;
        echo "FAIL: $label\n  expected: " . json_encode($expected)
           . "\n  actual:   " . json_encode($actual) . "\n";
    }
}

// 1. Attach two destinations to customer 1.
sync_customer_destinations($pdo, 1, array(10, 11));
check('attach two', array(10, 11), read_customer_destination_ids($pdo, 1));

// 2. Replace set (drop 10, keep 11, add 12).
sync_customer_destinations($pdo, 1, array(11, 12));
check('replace set', array(11, 12), read_customer_destination_ids($pdo, 1));

// 3. Junk + non-destination (13) + inactive (14) + dupes are dropped.
sync_customer_destinations($pdo, 1, array(10, 10, 13, 14, 999, 0, -5));
check('only valid active destinations kept', array(10), read_customer_destination_ids($pdo, 1));

// 4. Empty selection clears all.
sync_customer_destinations($pdo, 1, array());
check('clear all', array(), read_customer_destination_ids($pdo, 1));

// 5. Empty JSON string (how "clear all" posts from the form) also clears.
sync_customer_destinations($pdo, 1, array(10, 11));
sync_customer_destinations($pdo, 1, '[]');
check('empty json string clears', array(), read_customer_destination_ids($pdo, 1));

// 6. JSON string selection is honoured.
sync_customer_destinations($pdo, 1, '[11,12]');
check('json string selection', array(11, 12), read_customer_destination_ids($pdo, 1));

// 7. Per-customer isolation — customer 2 unaffected by customer 1.
sync_customer_destinations($pdo, 2, array(10));
check('customer 2 isolated', array(10), read_customer_destination_ids($pdo, 2));
check('customer 1 unchanged', array(11, 12), read_customer_destination_ids($pdo, 1));

echo $fail ? "\n$fail check(s) FAILED\n" : "\nAll checks passed\n";
exit($fail ? 1 : 0);
