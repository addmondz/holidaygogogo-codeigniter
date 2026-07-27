<?php
/**
 * Run with: php tests/helpers/SalesTargetUpsertTest.php
 *
 * Locks the upsert semantics behind Sales_Target_Model->upsert().
 *
 *   - Inserting (AdminID, year, month, amount) for a new key creates a row.
 *   - Upserting the same (AdminID, year, month) replaces the amount and bumps
 *     updated_at; the unique key keeps it as ONE row, not two.
 *   - Different months / different admins live as independent rows.
 *
 * Production uses MySQL `INSERT ... ON DUPLICATE KEY UPDATE` (Sales_Target_Model
 * upsert). Here we use SQLite `INSERT ... ON CONFLICT DO UPDATE` for the same
 * behaviour without booting CI. The schema matches the migration columns.
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
    created_at    TEXT    DEFAULT CURRENT_TIMESTAMP,
    updated_at    TEXT    DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (AdminID, target_year, target_month)
)");

$upsert = function ($admin_id, $year, $month, $amount) use ($pdo) {
    $stmt = $pdo->prepare("
        INSERT INTO sales_target (AdminID, target_year, target_month, target_amount, updated_at)
        VALUES (:a, :y, :m, :amt, CURRENT_TIMESTAMP)
        ON CONFLICT (AdminID, target_year, target_month)
        DO UPDATE SET target_amount = excluded.target_amount,
                      updated_at    = CURRENT_TIMESTAMP
    ");
    $stmt->execute([':a' => $admin_id, ':y' => $year, ':m' => $month, ':amt' => $amount]);
};

$count = function () use ($pdo) {
    return (int) $pdo->query("SELECT COUNT(*) FROM sales_target")->fetchColumn();
};

$amount_for = function ($admin_id, $year, $month) use ($pdo) {
    $stmt = $pdo->prepare("SELECT target_amount FROM sales_target WHERE AdminID=:a AND target_year=:y AND target_month=:m");
    $stmt->execute([':a' => $admin_id, ':y' => $year, ':m' => $month]);
    $val = $stmt->fetchColumn();
    return $val === false ? null : (float) $val;
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

// Fresh insert for admin 10, May 2026.
$upsert(10, 2026, 5, 5000);
assert_eq('row count after first insert',       1,      $count());
assert_eq('amount for admin 10 May 2026',       5000.0, $amount_for(10, 2026, 5));

// Same key, new amount -> in-place update, still one row.
$upsert(10, 2026, 5, 7500);
assert_eq('row count after same-key upsert',    1,      $count());
assert_eq('updated amount',                      7500.0, $amount_for(10, 2026, 5));

// Different month for the same admin -> separate row.
$upsert(10, 2026, 6, 8000);
assert_eq('row count after new month',          2,      $count());
assert_eq('May amount unchanged',                7500.0, $amount_for(10, 2026, 5));
assert_eq('Jun amount',                          8000.0, $amount_for(10, 2026, 6));

// Different admin, same month -> separate row.
$upsert(20, 2026, 5, 3000);
assert_eq('row count after different admin',    3,      $count());
assert_eq('admin 20 May amount',                 3000.0, $amount_for(20, 2026, 5));
assert_eq('admin 10 May still 7500',             7500.0, $amount_for(10, 2026, 5));

// Future-month planning: setting Dec 2026 in May 2026 should be a normal insert.
$upsert(10, 2026, 12, 12000);
assert_eq('row count after future-month plan',  4,      $count());
assert_eq('Dec 2026 amount',                     12000.0, $amount_for(10, 2026, 12));

// Year boundary — Jan 2027 is independent from Jan 2026.
$upsert(10, 2027, 1, 1000);
$upsert(10, 2026, 1, 999);
assert_eq('row count across years',             6,      $count());
assert_eq('Jan 2027 amount',                     1000.0, $amount_for(10, 2027, 1));
assert_eq('Jan 2026 amount',                     999.0,  $amount_for(10, 2026, 1));

// Direct duplicate-key INSERT must raise — confirms the unique constraint
// is the real safety net even if upsert is bypassed.
$threw = false;
try {
    $stmt = $pdo->prepare("INSERT INTO sales_target (AdminID, target_year, target_month, target_amount) VALUES (10, 2026, 5, 999)");
    $stmt->execute();
} catch (PDOException $e) {
    $threw = true;
}
assert_eq('raw duplicate insert raises', true, $threw);

echo "\nAll assertions passed.\n";
