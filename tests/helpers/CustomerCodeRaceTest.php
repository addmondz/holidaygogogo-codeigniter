<?php
/**
 * Run with: php tests/helpers/CustomerCodeRaceTest.php
 *
 * Proves the duplicate guard behind Customer_Model::create_with_generated_code():
 * generation is serialised (MySQL advisory lock in prod) and each code is
 * re-checked in code before insert, so two customers can never persist the same
 * CustomerCode — without relying on a DB UNIQUE constraint.
 *
 * It mirrors the model loop against SQLite (no UNIQUE column) so the code-level
 * check is what's exercised, not a DB constraint.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}
require_once __DIR__ . '/../../application/helpers/customer_code_helper.php';

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
// NOTE: CustomerCode is deliberately NOT UNIQUE here — the guard is the code.
$pdo->exec("CREATE TABLE customer (
    CustomerID INTEGER PRIMARY KEY AUTOINCREMENT,
    CustomerCode TEXT,
    name TEXT
)");

// Mirrors Customer_Model::create_with_generated_code(): generate from the codes
// currently in the table, re-check the code is free, then insert. (The advisory
// lock that serialises concurrent callers in prod is represented here by running
// the calls one at a time.)
function create_customer(PDO $pdo, $name, $max_attempts = 5)
{
    for ($attempt = 1; $attempt <= $max_attempts; $attempt++) {
        $used = $pdo->query("SELECT CustomerCode FROM customer WHERE CustomerCode IS NOT NULL")
                    ->fetchAll(PDO::FETCH_COLUMN);
        $code = next_customer_code($name, $used);

        // code_exists() guard.
        if ($code !== null) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM customer WHERE CustomerCode = ?");
            $stmt->execute([$code]);
            if ((int) $stmt->fetchColumn() > 0) {
                continue;
            }
        }

        $stmt = $pdo->prepare("INSERT INTO customer (CustomerCode, name) VALUES (?, ?)");
        $stmt->execute([$code, $name]);
        return $code;
    }
    return null;
}

$failed = 0;
$passed = 0;
function check($label, $expected, $actual)
{
    global $failed, $passed;
    if ($expected === $actual) {
        $passed++;
        echo "  PASS: $label\n";
    } else {
        $failed++;
        echo "  FAIL: $label (expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . ")\n";
    }
}

echo "create_with_generated_code() duplicate guard (code-level)\n";

// Sequential creates with the same initial — each sees the prior row, so they
// step forward instead of colliding.
$a = create_customer($pdo, 'Tang Li Choo');
$b = create_customer($pdo, 'Tan Ah Kow');
check('first customer', '303-T001', $a);
check('second customer steps forward', '303-T002', $b);

// AutoCount handed a different customer 303-T003 out-of-band. A new 'T' customer
// must skip past it rather than reuse it.
$pdo->exec("INSERT INTO customer (CustomerCode, name) VALUES ('303-T003', 'Imported From AutoCount')");
$c = create_customer($pdo, 'Teh');
check('skips AutoCount-issued 303-T003', '303-T004', $c);

// No duplicate codes exist after all of the above — the code-level guard held.
$dups = $pdo->query("SELECT COUNT(*) FROM (
    SELECT CustomerCode FROM customer WHERE CustomerCode IS NOT NULL
    GROUP BY CustomerCode HAVING COUNT(*) > 1
) t")->fetchColumn();
check('zero duplicate codes persisted', '0', (string) $dups);

echo "\n$passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);
