<?php
/**
 * Run with: php tests/helpers/CustomerCodeBumpOnSyncFailTest.php
 *
 * Proves the self-heal added to Cron::syncCustomer + Customer_Model::bump_customer_code():
 * when AutoCount rejects a create with `AccNo "303-T126" exists in Chart of
 * Account` (the code lives in AutoCount but NOT in our DB — an orphaned debtor),
 * the sync bumps to the next free CustomerCode and retries until AutoCount
 * accepts, instead of leaving the customer stuck in Failed forever.
 *
 * It mirrors the production loop against SQLite + an in-memory "Chart of
 * Account" set, exercising the real helpers (is_customer_code_clash() and
 * next_customer_code()).
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}
require_once __DIR__ . '/../../application/helpers/customer_code_helper.php';

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
        echo "  FAIL: $label\n";
        echo "        expected: " . var_export($expected, true) . "\n";
        echo "        actual:   " . var_export($actual, true) . "\n";
    }
}

/** Fresh customer table seeded so the next 'T' code generated is 303-T126. */
function fresh_pdo()
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("CREATE TABLE customer (
        CustomerID INTEGER PRIMARY KEY AUTOINCREMENT,
        CustomerCode TEXT,
        name TEXT
    )");
    // Highest existing 'T' code is 303-T125, so generation yields 303-T126 next.
    $stmt = $pdo->prepare("INSERT INTO customer (CustomerCode, name) VALUES (?, ?)");
    $stmt->execute(['303-T125', 'Existing T Customer']);
    return $pdo;
}

function all_codes(PDO $pdo)
{
    return $pdo->query("SELECT CustomerCode FROM customer WHERE CustomerCode IS NOT NULL")
               ->fetchAll(PDO::FETCH_COLUMN);
}

/** Mirror of Customer_Model::bump_customer_code(). */
function bump_code(PDO $pdo, $customer_id, $name)
{
    $stmt = $pdo->prepare("SELECT CustomerCode FROM customer WHERE CustomerID = ?");
    $stmt->execute([$customer_id]);
    $current = $stmt->fetchColumn();
    $current = $current === false ? null : $current;

    for ($i = 0; $i < 5; $i++) {
        $code = next_customer_code($name, all_codes($pdo));
        if (empty($code) || $code === $current) {
            return null;
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM customer WHERE CustomerCode = ? AND CustomerID != ?");
        $stmt->execute([$code, $customer_id]);
        $clash = (int) $stmt->fetchColumn() > 0;

        $up = $pdo->prepare("UPDATE customer SET CustomerCode = ? WHERE CustomerID = ?");
        $up->execute([$code, $customer_id]);

        if ($clash) {
            $current = $code;
            continue;
        }
        return $code;
    }
    return null;
}

/** Fake AutoCount debtor.create: rejects codes already in the Chart of Account. */
function ac_create(array &$chart, $code)
{
    if (in_array($code, $chart, true)) {
        return ['status' => 400, 'error' => "AccNo \"{$code}\" exists in Chart of Account"];
    }
    $chart[] = $code;
    return ['status' => 201, 'error' => null];
}

/** Mirror of the create + bump-on-failure loop in Cron::syncCustomer. */
function sync_create(PDO $pdo, array &$chart, $customer_id, $name, $code)
{
    $result = ac_create($chart, $code);

    $bump = 0;
    while ($bump < 5 && isset($result['error']) && is_customer_code_clash($result['error'])) {
        $bump++;
        $newCode = bump_code($pdo, $customer_id, $name);
        if (empty($newCode) || $newCode === $code) {
            break;
        }
        $code = $newCode;
        $result = ac_create($chart, $code);
    }

    return ['code' => $code, 'result' => $result];
}

function new_customer(PDO $pdo, $name, $code)
{
    $stmt = $pdo->prepare("INSERT INTO customer (CustomerCode, name) VALUES (?, ?)");
    $stmt->execute([$code, $name]);
    return (int) $pdo->lastInsertId();
}

echo "is_customer_code_clash()\n";
check('matches the AutoCount Chart-of-Account error', true,
    is_customer_code_clash('AccNo "303-T126" exists in Chart of Account'));
check('case-insensitive', true,
    is_customer_code_clash('accno "303-t126" EXISTS IN chart of account'));
check('ignores unrelated errors', false,
    is_customer_code_clash('Connection timed out'));
check('ignores empty', false, is_customer_code_clash(''));
check('ignores null', false, is_customer_code_clash(null));

echo "\nsync_create() self-heals an orphaned-code collision\n";

// One orphaned debtor 303-T126 already in AutoCount but not in our DB.
$pdo = fresh_pdo();
$chart = ['303-T126'];
$id = new_customer($pdo, 'TANG LI CHOO', '303-T126'); // generation handed it 126
$out = sync_create($pdo, $chart, $id, 'TANG LI CHOO', '303-T126');
check('bumps past the orphan to 303-T127', '303-T127', $out['code']);
check('sync ultimately succeeds', null, $out['result']['error']);
$stmt = $pdo->prepare("SELECT CustomerCode FROM customer WHERE CustomerID = ?");
$stmt->execute([$id]);
check('local row persisted the healed code', '303-T127', $stmt->fetchColumn());

echo "\nsync_create() bumps repeatedly across consecutive orphans\n";

$pdo = fresh_pdo();
$chart = ['303-T126', '303-T127']; // two consecutive orphans
$id = new_customer($pdo, 'TANG LI CHOO', '303-T126');
$out = sync_create($pdo, $chart, $id, 'TANG LI CHOO', '303-T126');
check('skips both orphans to 303-T128', '303-T128', $out['code']);
check('sync ultimately succeeds', null, $out['result']['error']);

echo "\nsync_create() leaves a clean create untouched\n";

$pdo = fresh_pdo();
$chart = []; // nothing orphaned
$id = new_customer($pdo, 'TANG LI CHOO', '303-T126');
$out = sync_create($pdo, $chart, $id, 'TANG LI CHOO', '303-T126');
check('keeps the original code (no bump)', '303-T126', $out['code']);
check('sync succeeds first try', null, $out['result']['error']);

echo "\n$passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);
