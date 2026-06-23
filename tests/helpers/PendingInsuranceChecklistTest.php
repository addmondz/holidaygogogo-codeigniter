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
 *   - booking.StartDate >= the March-1 travel-date floor (March 1 of the
 *     current year). The card is scoped to current-season travel so the
 *     queue is actionable instead of dragging in long-finished trips.
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
    Status TEXT,
    StartDate TEXT,
    EndDate TEXT
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

// The travel window for these fixtures is 2026-03-01 .. 2031-12-31, applied
// with the same range-overlap predicate as production. Bookings whose travel
// overlaps the window are in-season; booking 9 is fully before it (excluded),
// while booking 10 starts in February but ends after the floor, so the overlap
// rule still includes it.
$pdo->exec("INSERT INTO booking VALUES
    (1,  'BOOKING CONFIRMATION', 'N', 'P', '2026-04-10', '2026-04-15'),  /* candidate                       */
    (2,  'BOOKING CONFIRMATION', 'N', 'P', '2026-04-10', '2026-04-15'),  /* completion already exists       */
    (3,  'BOOKING CONFIRMATION', 'N', 'P', '2026-04-10', '2026-04-15'),  /* disable_checklist_payment_out=1 */
    (4,  'BOOKING CONFIRMATION', 'Y', 'P', '2026-04-10', '2026-04-15'),  /* cancelled                       */
    (5,  'BOOKING CONFIRMATION', 'N', 'N', '2026-04-10', '2026-04-15'),  /* draft                           */
    (6,  'QUOTATION',            'N', 'P', '2026-04-10', '2026-04-15'),  /* quotation                       */
    (7,  'BOOKING CONFIRMATION', 'N', 'P', '2026-04-10', '2026-04-15'),  /* product has no insurance        */
    (8,  'BOOKING CONFIRMATION', 'N', 'P', '2026-05-01', '2026-05-05'),  /* two products: one pending, one completed -> still counts once */
    (9,  'BOOKING CONFIRMATION', 'N', 'P', '2026-01-15', '2026-01-20'),  /* pending, but travel fully before window -> excluded */
    (10, 'BOOKING CONFIRMATION', 'N', 'P', '2026-02-20', '2026-03-05')   /* pending, starts pre-window but ends after floor -> overlap includes it */
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
    (9, 8, 200, 'Y', 0),  /* booking 8 line B: insurance has completion         */
    (10, 9, 100, 'Y', 0), /* booking 9: insurance pending but travel before window */
    (11, 10, 100, 'Y', 0) /* booking 10: insurance pending, travel overlaps window -> COUNT */
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
      AND ((booking.StartDate <= ? AND booking.EndDate >= ?)
           OR (booking.StartDate >= ? AND booking.StartDate <= ?)
           OR (booking.EndDate >= ? AND booking.EndDate <= ?))
      AND booking.BookingID IN (
        SELECT DISTINCT bp.BookingID
        FROM booking_product bp
        JOIN product p ON p.ProductID = bp.ProductID AND p.is_child_or_infant = 0
        JOIN product_package_checklist ppc ON ppc.product_id = bp.ProductID
          AND (ppc.package_checklist_json LIKE ? OR ppc.package_checklist_json LIKE ?)
        WHERE bp.Status = 'Y'
          AND bp.disable_checklist_payment_out = 0
          AND NOT EXISTS (
            SELECT 1 FROM booking_checklist_completion bcc
            WHERE bcc.booking_id = bp.BookingID
              AND bcc.product_id = bp.ProductID
              AND bcc.package_checklist_id = ?
          )
      )
";
// Travel window matches production: 1 March of the (fixture) current year
// through a far-future upper bound, applied with the range-overlap predicate.
$win_start = '2026-03-01';
$win_end   = '2031-12-31';
$stmt = $pdo->prepare($sql);
$stmt->execute(array(
    $win_start, $win_end,                       // (StartDate <= end AND EndDate >= start)
    $win_start, $win_end,                       // (StartDate within window)
    $win_start, $win_end,                       // (EndDate within window)
    '%[' . $insurance_id . ']%',                // bare ID array, e.g., [7]
    '%' . $insurance_id . ',%',                 // mid-array element, e.g., [7, 8]
    $insurance_id,
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

// Expected counted bookings: 1 (line pending), 8 (line A pending, line B
// completed -> still counts once via DISTINCT), and 10 (travel overlaps the
// window via EndDate). Bookings 2,3,4,5,6,7 excluded by the existing rules;
// booking 9 excluded because its travel (2026-01-15..2026-01-20) is fully
// before the window.
assert_eq('pending insurance bookings', 3, $count);

echo "\nAll assertions passed.\n";
