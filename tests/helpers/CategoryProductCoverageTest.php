<?php
/**
 * Run with: php tests/helpers/CategoryProductCoverageTest.php
 *
 * Locks the rule behind the booking "Incomplete Destination Products" warning.
 *
 * Setting -> Category lets a category define a set of expected products via the
 * category_product link table. When a booking selects that category as its
 * Destination, the inserted products must cover ALL of the category's expected
 * products; any that are missing trigger a soft warning (the user may proceed).
 *
 * Two pieces are locked here:
 *   1. The SQL shape of Booking_Model::Read_Category_Products() — only ACTIVE
 *      links to ACTIVE products feed the map (mirrors the model's WHERE clause).
 *   2. The set-difference "coverage gap" that the booking JS computes:
 *      missing = required ProductIDs NOT present among inserted ProductIDs.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE category (
    CategoryID INTEGER PRIMARY KEY,
    Name TEXT,
    IsDestination TEXT,
    Status TEXT
)");
$pdo->exec("CREATE TABLE product (
    ProductID INTEGER PRIMARY KEY,
    ProductCode TEXT,
    Name TEXT,
    Status TEXT
)");
$pdo->exec("CREATE TABLE category_product (
    CategoryProductID INTEGER PRIMARY KEY,
    CategoryID INTEGER,
    ProductID INTEGER,
    Status TEXT
)");

// One destination category.
$pdo->exec("INSERT INTO category VALUES (10, 'LANGKAWI', 'YES', 'Y')");

// Products: P1..P3 active, P4 inactive (a deactivated product).
$pdo->exec("INSERT INTO product VALUES
    (1, 'FERRY', 'FERRY TICKET', 'Y'),
    (2, 'HOTEL', 'HOTEL STAY', 'Y'),
    (3, 'TOUR',  'ISLAND TOUR', 'Y'),
    (4, 'OLD',   'RETIRED PRODUCT', 'N')");

// Category 10 expects P1, P2, P3. A 4th link points at the inactive product P4,
// and a 5th link is itself disabled (Status='N') — both must be ignored.
$pdo->exec("INSERT INTO category_product VALUES
    (100, 10, 1, 'Y'),
    (101, 10, 2, 'Y'),
    (102, 10, 3, 'Y'),
    (103, 10, 4, 'Y'),
    (104, 10, 1, 'N')");

// Mirror of Booking_Model::Read_Category_Products(): active links -> active
// products only. Returns the category's REQUIRED product ids.
$sql = "
    SELECT cp.ProductID
    FROM category_product cp
    JOIN product p ON p.ProductID = cp.ProductID
    WHERE cp.Status = 'Y'
      AND p.Status = 'Y'
      AND cp.CategoryID = :cid
    ORDER BY p.ProductCode ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute(array(':cid' => 10));
$required = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

// Pure set-difference used by the booking page: which required products are not
// among the inserted ones.
function coverage_gap(array $required, array $inserted) {
    $insertedSet = array_flip(array_map('strval', $inserted));
    $missing = array();
    foreach ($required as $rid) {
        if (!isset($insertedSet[(string) $rid])) {
            $missing[] = (int) $rid;
        }
    }
    return $missing;
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

// 1. Map excludes the inactive product (P4) and the disabled link, leaving P1-3.
assert_eq('required products for destination', array(1, 2, 3), $required);

// 2. Booking inserted only P1 -> P2 and P3 missing.
assert_eq('gap when partially covered', array(2, 3), coverage_gap($required, array(1)));

// 3. Booking inserted all three (order/duplicates irrelevant) -> fully covered.
assert_eq('gap when fully covered', array(), coverage_gap($required, array(3, 1, 2, 2)));

// 4. Booking inserted none -> all required missing.
assert_eq('gap when nothing inserted', array(1, 2, 3), coverage_gap($required, array()));

// 5. Inserting the inactive/extra product P4 does not satisfy any requirement.
assert_eq('gap ignores non-required inserts', array(1, 2, 3), coverage_gap($required, array(4)));

echo "\nAll assertions passed.\n";
