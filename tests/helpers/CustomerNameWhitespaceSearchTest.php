<?php
/**
 * Run with: php tests/helpers/CustomerNameWhitespaceSearchTest.php
 *
 * Locks the whitespace-tolerant contract for the customer-name search filter,
 * shared by:
 *   - Customer list page (/Customer), via Customer_Model::Read_Customers1,
 *     Customer_Model::Count_Customers, Customer_Model::Read_Customers_For_Export
 *   - Booking list customer filter, via Booking_Model::apply_booking_filters
 *
 * Bug shape this guards against:
 *   When a customer row is stored with leading or trailing whitespace in its
 *   `name` (common after CSV / GHL imports), or when the user types the search
 *   term with extra whitespace, the previous `LIKE 'q%'` (index-safe prefix)
 *   never matched. After the fix, both the input is trimmed and the column
 *   is wrapped in TRIM(...) inside the LIKE, so the comparison is
 *   whitespace-agnostic on both sides.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require_once __DIR__ . '/../../application/helpers/customer_name_search_helper.php';

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE customer (
    CustomerID INTEGER PRIMARY KEY,
    name TEXT,
    Status TEXT
)");

$pdo->exec("INSERT INTO customer VALUES
    (1, 'John Smith',   'Y'),
    (2, ' John Smith',  'Y'),
    (3, 'John Smith ',  'Y'),
    (4, '  John  ',     'Y'),
    (5, 'Jane Doe',     'Y'),
    (6, 'Johnny Cash',  'Y')
");

$pdo->exec("CREATE TABLE booking (
    BookingID INTEGER PRIMARY KEY,
    CustomerID INTEGER,
    Customer TEXT
)");

$pdo->exec("INSERT INTO booking VALUES
    (101, 1, 'John Smith'),
    (102, 2, ' John Smith'),
    (103, 5, 'Jane Doe'),
    (104, 6, ' Johnny')
");

function naive_escape_like_str($s)
{
    // Mirror the small surface of $this->db->escape_like_str() that the helper
    // depends on -- escape %, _, and \ so they don't act as wildcards.
    return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $s);
}

function run_customer_query(PDO $pdo, $raw_input)
{
    $q = trim((string) $raw_input);
    if ($q === '') return [];
    $like = naive_escape_like_str($q);
    $fragment = customer_name_trim_like_fragment('name', $like, 'after');
    $sql = "SELECT CustomerID FROM customer WHERE Status = 'Y' AND " . $fragment . " ORDER BY CustomerID";
    $rows = [];
    foreach ($pdo->query($sql) as $r) {
        $rows[] = (int) $r['CustomerID'];
    }
    return $rows;
}

function run_booking_query(PDO $pdo, $raw_input)
{
    $q = trim((string) $raw_input);
    if ($q === '') return [];
    $like = naive_escape_like_str($q);
    $fragment_book = customer_name_trim_like_fragment('booking.Customer', $like, 'both');
    $fragment_cust = customer_name_trim_like_fragment('customer.name', $like, 'both');
    $sql = "SELECT booking.BookingID FROM booking
            LEFT JOIN customer ON customer.CustomerID = booking.CustomerID
            WHERE ($fragment_book OR $fragment_cust)
            ORDER BY booking.BookingID";
    $rows = [];
    foreach ($pdo->query($sql) as $r) {
        $rows[] = (int) $r['BookingID'];
    }
    return $rows;
}

$assertions = [];

// ---- Customer-side: prefix search ----------------------------------------

// 1) Bare "John" must return the clean row AND the rows whose stored name has
//    leading or trailing whitespace.  Bug shape: rows 2, 3, 4 were missing.
$ids = run_customer_query($pdo, 'John');
$assertions['"John" hits clean row 1'] = in_array(1, $ids, true);
$assertions['"John" hits leading-space stored row 2'] = in_array(2, $ids, true);
$assertions['"John" hits trailing-space stored row 3'] = in_array(3, $ids, true);
$assertions['"John" hits double-space stored row 4'] = in_array(4, $ids, true);
$assertions['"John" still hits "Johnny Cash" (row 6) as prefix'] = in_array(6, $ids, true);
$assertions['"John" excludes "Jane Doe" (row 5)'] = !in_array(5, $ids, true);

// 2) Leading-space input is normalised to the same result as bare input.
$assertions['" John" matches the same set as "John"'] = run_customer_query($pdo, ' John') === $ids;

// 3) Trailing-space input is also normalised.
$assertions['"John " matches the same set as "John"'] = run_customer_query($pdo, 'John ') === $ids;

// 4) Specific prefix "Johnny" still excludes the bare-John rows but includes row 6.
$ids = run_customer_query($pdo, 'Johnny');
$assertions['"Johnny" returns only Johnny Cash'] = $ids === [6];

// 5) Empty / whitespace-only input -> no clause applied -> empty result via the
//    early return.  (The model's caller also short-circuits.)
$assertions['empty input returns no rows from helper'] = run_customer_query($pdo, '') === [];
$assertions['whitespace-only input returns no rows from helper'] = run_customer_query($pdo, '   ') === [];

// 6) "Jane" excludes any John row.
$ids = run_customer_query($pdo, 'Jane');
$assertions['"Jane" returns only Jane Doe'] = $ids === [5];

// ---- Booking-side: substring search --------------------------------------

// 8) Searching the booking customer filter for "John" must return all bookings
//    whose customer name (or denormalised booking.Customer string) contains
//    "John", even when either column has leading whitespace.
$ids = run_booking_query($pdo, 'John');
$assertions['booking "John" returns 101,102,104'] = $ids === [101, 102, 104];

// 9) Same when the user typed " John" with a leading space.
$assertions['booking " John" matches the same set'] = run_booking_query($pdo, ' John') === [101, 102, 104];

// 10) "Jane" only matches the Jane booking.
$ids = run_booking_query($pdo, 'Jane');
$assertions['booking "Jane" returns only 103'] = $ids === [103];

// ---- Fragment shape ------------------------------------------------------

// 11) Defensive shape check so we notice if the helper changes its emitted SQL.
$assertions['"after" fragment is prefix-shaped'] =
    customer_name_trim_like_fragment('name', 'John', 'after') === "TRIM(name) LIKE 'John%'";
$assertions['"both" fragment wraps with %...%'] =
    customer_name_trim_like_fragment('customer.name', 'John', 'both') === "TRIM(customer.name) LIKE '%John%'";

$failed = 0;
foreach ($assertions as $label => $ok) {
    echo ($ok ? '  PASS  ' : '  FAIL  ') . $label . PHP_EOL;
    if (!$ok) {
        $failed++;
    }
}

echo PHP_EOL . ($failed === 0 ? "All assertions passed.\n" : "$failed assertion(s) failed.\n");
exit($failed === 0 ? 0 : 1);
