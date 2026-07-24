<?php
/**
 * Run with: php tests/helpers/CustomerContactUpdateTest.php
 *
 * Documents the SQL contract behind Guests_Model::Update_Customer_Contact(),
 * used so the Customer List's inline contact edit can set/change a customer's
 * phone even when they have NO guest_list rows and lead no booking (e.g. a
 * bulk-imported, name-only customer). The write is a plain
 *   UPDATE customer SET phone_number = ? WHERE CustomerID = ?
 * and, like Update_Customer_AltName, reports "found" (1) whenever the customer
 * exists — even if the number is unchanged — so re-saving is not "not found".
 *
 * Exercised against a SQLite :memory: mirror of the customer table, the same
 * approach GuestContactUpdateTest uses for the dedup SQL.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

$assertions = array();

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE customer (
    CustomerID INTEGER PRIMARY KEY,
    name TEXT,
    phone_number TEXT
)");

// Customer 1 has a number; customer 2 is a name-only import (no phone).
$pdo->exec("INSERT INTO customer VALUES
    (1, 'AH KAU',          '0123456789'),
    (2, 'HIDAYATI YUSOF',  NULL)
");

// Mirror of the model write: UPDATE ... WHERE CustomerID, then "found" is the
// row existing (not affected-rows), exactly like the model returns 1/0.
$update = function ($customer_id, $mobile) use ($pdo) {
    $exists = $pdo->query("SELECT 1 FROM customer WHERE CustomerID = " . (int) $customer_id)->fetchColumn();
    if (!$exists) {
        return 0;
    }
    $stmt = $pdo->prepare("UPDATE customer SET phone_number = ? WHERE CustomerID = ?");
    $stmt->execute(array($mobile, (int) $customer_id));
    return 1;
};
$phone_of = function ($id) use ($pdo) {
    return $pdo->query("SELECT phone_number FROM customer WHERE CustomerID = " . (int) $id)->fetchColumn();
};

// 1) Name-only customer with no phone gets one set -> found, value stored.
$assertions['name-only: set phone reports found'] = $update(2, '0107174078') === 1;
$assertions['name-only: phone now stored']        = $phone_of(2) === '0107174078';

// 2) Existing number changed on another customer, in isolation.
$assertions['existing: change phone reports found'] = $update(1, '0111111111') === 1;
$assertions['existing: phone changed']              = $phone_of(1) === '0111111111';
$assertions['existing: other row untouched']        = $phone_of(2) === '0107174078';

// 3) Re-saving the same number still reports found (not "not found").
$assertions['resave same number -> found'] = $update(1, '0111111111') === 1;

// 4) Unknown customer -> not found, nothing written.
$assertions['unknown customer -> 0'] = $update(999, '0100000000') === 0;

// Report -------------------------------------------------------------------
$failed = 0;
foreach ($assertions as $label => $ok) {
    echo ($ok ? '  PASS  ' : '  FAIL  ') . $label . PHP_EOL;
    if (!$ok) {
        $failed++;
    }
}

echo PHP_EOL . ($failed === 0 ? "All assertions passed.\n" : "$failed assertion(s) failed.\n");
exit($failed === 0 ? 0 : 1);
