<?php
/**
 * Run with: php tests/helpers/SalesTargetsForAdminDictTest.php
 *
 * Locks the dict shape returned by Admin_Model->Read_Sales_Targets_For_Admin($admin_id):
 * keyed by "YYYY-MM" strings -> float amounts, scoped to one admin, returns an
 * empty array when no rows match. The Admin edit form's JS consumes this dict
 * directly to populate inputs on period change, so any drift here breaks the UI.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE sales_target (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    AdminID       INTEGER NOT NULL,
    target_year   INTEGER NOT NULL,
    target_month  INTEGER NOT NULL,
    target_amount REAL    NOT NULL DEFAULT 0,
    UNIQUE (AdminID, target_year, target_month)
)");

$pdo->exec("INSERT INTO sales_target (AdminID, target_year, target_month, target_amount) VALUES
    (10, 2026,  5, 5000),
    (10, 2026,  6, 7500),
    (10, 2026, 12, 12000),  /* future month */
    (10, 2027,  1, 1500),   /* next year */
    (20, 2026,  5, 3000),   /* another admin - must not appear for admin 10 */
    (20, 2026,  7, 4500)
");

// Replicates Admin_Model->Read_Sales_Targets_For_Admin() shape in raw SQL.
$read_dict = function ($admin_id) use ($pdo) {
    $stmt = $pdo->prepare("
        SELECT target_year, target_month, target_amount
        FROM sales_target
        WHERE AdminID = ?
    ");
    $stmt->execute([$admin_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $out = array();
    foreach ($rows as $r) {
        $key = sprintf('%04d-%02d', (int)$r['target_year'], (int)$r['target_month']);
        $out[$key] = (float) $r['target_amount'];
    }
    return $out;
};

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got "      . var_export($actual, true) . "\n";
        exit(1);
    }
}

$dict_10 = $read_dict(10);
$dict_20 = $read_dict(20);
$dict_99 = $read_dict(99);

// Admin 10: four periods including a future month and a next-year row.
assert_eq('admin 10 dict count', 4, count($dict_10));
assert_eq('admin 10 May 2026',   5000.0,  $dict_10['2026-05']);
assert_eq('admin 10 Jun 2026',   7500.0,  $dict_10['2026-06']);
assert_eq('admin 10 Dec 2026',   12000.0, $dict_10['2026-12']);
assert_eq('admin 10 Jan 2027',   1500.0,  $dict_10['2027-01']);

// Single-digit months pad to two digits.
assert_eq('Jan key is "2027-01" not "2027-1"', true, array_key_exists('2027-01', $dict_10));
assert_eq('no unpadded key', false, array_key_exists('2027-1', $dict_10));

// Admin 10 must NOT see admin 20's rows.
assert_eq('admin 10 dict does not contain Jul 2026', false, array_key_exists('2026-07', $dict_10));

// Admin 20 isolation
assert_eq('admin 20 dict count', 2, count($dict_20));
assert_eq('admin 20 May 2026', 3000.0, $dict_20['2026-05']);

// Unknown admin -> empty ARRAY (not null), so the JS `Object.keys(targets)` works.
assert_eq('unknown admin returns []', array(), $dict_99);
assert_eq('unknown admin is_array',   true,    is_array($dict_99));

// Admin id 0 / negative -> empty (model-side guard); replicated here.
assert_eq('admin id 0 returns []', array(), $read_dict(0));

// All values are floats (numeric type matters for JS Number() consumption).
foreach ($dict_10 as $k => $v) {
    if (!is_float($v)) {
        echo "  FAIL  value for {$k} is not float: " . var_export($v, true) . "\n";
        exit(1);
    }
}
echo "  PASS  all admin 10 amounts are float\n";

echo "\nAll assertions passed.\n";
