<?php
/**
 * Run with: php tests/helpers/PendingInsuranceChecklistTest.php
 *
 * Locks the SQL behind the OP "Pending Insurance Checklist" summary card.
 * A booking counts when ALL these hold for at least one active line item:
 *
 *   - The product has an insurance package_checklist assigned via
 *     product_package_checklist.package_checklist_json (JSON array of IDs)
 *   - booking_product.Status = 'Y' (active line)
 *   - booking_product.disable_checklist_payment_out = 0   <-- mirrors the
 *     modal/filter rule so the summary count can never disagree with the
 *     filtered list view (see CLAUDE memory feedback_checklist_filter).
 *   - No row in booking_checklist_completion matching (booking, product,
 *     insurance checklist id).
 *   - The booking is a non-cancelled, non-draft BOOKING CONFIRMATION.
 *
 * This test uses LIKE-on-JSON instead of JSON_CONTAINS because SQLite
 * doesn't ship JSON_CONTAINS; the production query uses JSON_CONTAINS on
 * MySQL. Both pick the same rows for these fixtures.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE booking (
    BookingID INTEGER PRIMARY KEY,
    BookingConfirmationTitle TEXT,
    CancelStatus TEXT,
    Status TEXT
)");
$pdo->exec("CREATE TABLE booking_product (
    BookingProductID INTEGER PRIMARY KEY,
    BookingID INTEGER,
    ProductID INTEGER,
    Status TEXT,
    disable_checklist_payment_out INTEGER
)");
$pdo->exec("CREATE TABLE product (
    ProductID INTEGER PRIMARY KEY,
    is_child_or_infant INTEGER
)");
$pdo->exec("CREATE TABLE product_package_checklist (
    id INTEGER PRIMARY KEY,
    product_id INTEGER,
    package_checklist_json TEXT
)");
$pdo->exec("CREATE TABLE package_checklist (
    ID INTEGER PRIMARY KEY,
    name TEXT
)");
$pdo->exec("CREATE TABLE booking_checklist_completion (
    id INTEGER PRIMARY KEY,
    booking_id INTEGER,
    product_id INTEGER,
    package_checklist_id INTEGER
)");

// Insurance checklist ID = 7.
$pdo->exec("INSERT INTO package_checklist VALUES (7, 'Travel Insurance')");
$pdo->exec("INSERT INTO package_checklist VALUES (8, 'Visa Application')");

$pdo->exec("INSERT INTO booking VALUES
    (1, 'BOOKING CONFIRMATION', 'N', 'P'),   /* candidate                       */
    (2, 'BOOKING CONFIRMATION', 'N', 'P'),   /* completion already exists       */
    (3, 'BOOKING CONFIRMATION', 'N', 'P'),   /* disable_checklist_payment_out=1 */
    (4, 'BOOKING CONFIRMATION', 'Y', 'P'),   /* cancelled                       */
    (5, 'BOOKING CONFIRMATION', 'N', 'N'),   /* draft                           */
    (6, 'QUOTATION',            'N', 'P'),   /* quotation                       */
    (7, 'BOOKING CONFIRMATION', 'N', 'P'),   /* product has no insurance        */
    (8, 'BOOKING CONFIRMATION', 'N', 'P')    /* two products: one pending, one completed -> still counts once */
");

// Products: 100 has insurance checklist, 200 has insurance checklist,
//           300 has only visa, 400 has insurance + child/infant (skipped).
$pdo->exec("INSERT INTO product VALUES (100, 0), (200, 0), (300, 0), (400, 1)");
$pdo->exec("INSERT INTO product_package_checklist VALUES
    (1, 100, '[7]'),
    (2, 200, '[7, 8]'),
    (3, 300, '[8]'),
    (4, 400, '[7]')
");

$pdo->exec("INSERT INTO booking_product VALUES
    (1, 1, 100, 'Y', 0),  /* booking 1: insurance pending -> COUNT             */
    (2, 2, 200, 'Y', 0),  /* booking 2: insurance pending but completion exists */
    (3, 3, 100, 'Y', 1),  /* booking 3: disable_checklist_payment_out=1         */
    (4, 4, 100, 'Y', 0),  /* booking 4: cancelled                               */
    (5, 5, 100, 'Y', 0),  /* booking 5: draft                                   */
    (6, 6, 100, 'Y', 0),  /* booking 6: quotation                               */
    (7, 7, 300, 'Y', 0),  /* booking 7: product has visa only, not insurance    */
    (8, 8, 100, 'Y', 0),  /* booking 8 line A: insurance pending -> COUNT once  */
    (9, 8, 200, 'Y', 0)   /* booking 8 line B: insurance has completion         */
");

// Completion records: booking 2 has it for the insurance checklist;
// booking 8's line B (product 200) has it but line A (product 100) does not.
$pdo->exec("INSERT INTO booking_checklist_completion VALUES
    (1, 2, 200, 7),
    (2, 8, 200, 7)
");

// Production query uses JSON_CONTAINS; here we approximate with LIKE on the
// JSON literal. With these fixtures the row selection is identical.
$insurance_id = 7;
$sql = "
    SELECT COUNT(DISTINCT booking.BookingID) AS cnt
    FROM booking
    WHERE booking.BookingConfirmationTitle = 'BOOKING CONFIRMATION'
      AND booking.CancelStatus = 'N'
      AND booking.Status != 'N'
      AND booking.BookingID IN (
        SELECT DISTINCT bp.BookingID
        FROM booking_product bp
        JOIN product p ON p.ProductID = bp.ProductID AND p.is_child_or_infant = 0
        JOIN product_package_checklist ppc ON ppc.product_id = bp.ProductID
          AND (ppc.package_checklist_json LIKE :json_a OR ppc.package_checklist_json LIKE :json_b)
        WHERE bp.Status = 'Y'
          AND bp.disable_checklist_payment_out = 0
          AND NOT EXISTS (
            SELECT 1 FROM booking_checklist_completion bcc
            WHERE bcc.booking_id = bp.BookingID
              AND bcc.product_id = bp.ProductID
              AND bcc.package_checklist_id = :cid
          )
      )
";
$stmt = $pdo->prepare($sql);
$stmt->execute(array(
    ':json_a' => '%[' . $insurance_id . ']%',     // bare ID array, e.g., [7]
    ':json_b' => '%' . $insurance_id . ',%',      // mid-array element, e.g., [7, 8]
    ':cid'    => $insurance_id,
));
$count = (int) $stmt->fetchColumn();

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

// Expected counted bookings: 1 (line pending) and 8 (line A pending, line B
// completed -> booking still counts once via DISTINCT). Bookings 2,3,4,5,6,7
// all excluded.
assert_eq('pending insurance bookings', 2, $count);

echo "\nAll assertions passed.\n";
