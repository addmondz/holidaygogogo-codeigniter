<?php
/**
 * Run with: php tests/helpers/SalesTargetYearUpsertTest.php
 *
 * Locks the yearly-target upsert semantics backing
 * Sales_Target_Model::upsert_year() / get_year_amount() and the
 * "Year Sales vs Target" dashboard card.
 *
 * Mirrors SalesTargetUpsertTest (the monthly table) but for sales_target_year,
 * whose unique key is (AdminID, target_year) — one row per TC per year.
 *
 * Rules:
 *   - Same (AdminID, target_year) upserts in place (row count stays put, amount
 *     overwritten).
 *   - Different year, or different admin, inserts a new row.
 *   - get_year_amount returns the stored amount, or 0.0 when no row exists.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE sales_target_year (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    AdminID       INTEGER NOT NULL,
    target_year   INTEGER NOT NULL,
    target_amount REAL    NOT NULL DEFAULT 0,
    created_at    TEXT    DEFAULT CURRENT_TIMESTAMP,
    updated_at    TEXT    DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (AdminID, target_year)
)");

$upsert_year = function ($admin_id, $year, $amount) use ($pdo) {
    $stmt = $pdo->prepare("
        INSERT INTO sales_target_year (AdminID, target_year, target_amount, updated_at)
        VALUES (:a, :y, :amt, CURRENT_TIMESTAMP)
        ON CONFLICT (AdminID, target_year)
        DO UPDATE SET target_amount = excluded.target_amount,
                      updated_at    = CURRENT_TIMESTAMP
    ");
    $stmt->execute([':a' => $admin_id, ':y' => $year, ':amt' => max(0.0, (float)$amount)]);
};

$get_year_amount = function ($admin_id, $year) use ($pdo) {
    $stmt = $pdo->prepare("SELECT target_amount FROM sales_target_year
                           WHERE AdminID = ? AND target_year = ?");
    $stmt->execute([$admin_id, $year]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? (float)$row['target_amount'] : 0.0;
};

$count = function () use ($pdo) {
    return (int) $pdo->query("SELECT COUNT(*) FROM sales_target_year")->fetchColumn();
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

// ---- missing row -> 0.0 ---------------------------------------------------
assert_eq('missing -> 0.0', 0.0, $get_year_amount(20, 2026));

// ---- first insert ---------------------------------------------------------
$upsert_year(20, 2026, 600000);
assert_eq('after insert count',   1,        $count());
assert_eq('reads back amount',    600000.0, $get_year_amount(20, 2026));

// ---- same admin+year overwrites in place ----------------------------------
$upsert_year(20, 2026, 720000);
assert_eq('upsert no new row',    1,        $count());
assert_eq('amount overwritten',   720000.0, $get_year_amount(20, 2026));

// ---- different year -> new row --------------------------------------------
$upsert_year(20, 2027, 800000);
assert_eq('new year new row',     2,        $count());
assert_eq('2026 untouched',       720000.0, $get_year_amount(20, 2026));
assert_eq('2027 stored',          800000.0, $get_year_amount(20, 2027));

// ---- different admin -> new row -------------------------------------------
$upsert_year(50, 2026, 300000);
assert_eq('new admin new row',    3,        $count());
assert_eq('admin 50 isolated',    300000.0, $get_year_amount(50, 2026));
assert_eq('admin 20 isolated',    720000.0, $get_year_amount(20, 2026));

// ---- negative clamps to 0 -------------------------------------------------
$upsert_year(20, 2028, -999);
assert_eq('negative clamps to 0', 0.0,      $get_year_amount(20, 2028));

echo "\nAll assertions passed.\n";
