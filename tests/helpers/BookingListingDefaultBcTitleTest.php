<?php
/**
 * Run with: php tests/helpers/BookingListingDefaultBcTitleTest.php
 *
 * Locks the contract that the on-screen booking listing (and everything else
 * routed through Booking_Model::apply_booking_filters — the DataTables list,
 * the filtered count, and the Guest List / Booking Records exports) defaults
 * to showing ONLY Booking Confirmations for every level.
 *
 *   - No booking_confirmation_title param  -> WHERE BookingConfirmationTitle = 'BOOKING CONFIRMATION'
 *   - booking_confirmation_title = X,Y     -> WHERE BookingConfirmationTitle IN (X, Y)   (user override)
 *
 * A QUOTATION / PROFORMA INVOICE row only appears once the user explicitly
 * picks it in the BC Title filter.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

$failures = 0;
function check($label, $cond) {
    global $failures;
    if ($cond) {
        echo "  PASS  {$label}\n";
    } else {
        echo "  FAIL  {$label}\n";
        $failures++;
    }
}

/* ----------------------------------------------------------------------
 * 1. Source guard: apply_booking_filters must default to BOOKING CONFIRMATION
 *    when the booking_confirmation_title param is empty.
 * -------------------------------------------------------------------- */
$model_path = __DIR__ . '/../../application/models/Booking_Model.php';
$source = file_get_contents($model_path);

// Isolate the apply_booking_filters() body so we don't match some other spot.
preg_match('/function\s+apply_booking_filters\s*\([^)]*\)\s*\{/', $source, $m, PREG_OFFSET_CAPTURE);
$brace_start = $m[0][1] + strlen($m[0][0]) - 1;
$depth = 0; $body_end = null;
for ($i = $brace_start, $n = strlen($source); $i < $n; $i++) {
    if ($source[$i] === '{') { $depth++; }
    elseif ($source[$i] === '}') { $depth--; if ($depth === 0) { $body_end = $i; break; } }
}
$body = substr($source, $brace_start, $body_end - $brace_start + 1);

check(
    'apply_booking_filters keeps the user-supplied BC Title override',
    strpos($body, "where_in('booking.BookingConfirmationTitle', explode(',', \$this->input->get('booking_confirmation_title')))") !== false
);
check(
    'apply_booking_filters defaults to BOOKING CONFIRMATION when the param is empty',
    (bool) preg_match(
        "/booking_confirmation_title'\)\).*?\}\s*else\s*\{.*?where\('booking\.BookingConfirmationTitle',\s*'BOOKING CONFIRMATION'\)/s",
        $body
    )
);

/* ----------------------------------------------------------------------
 * 2. Behavioural demonstration of the contract in SQLite.
 * -------------------------------------------------------------------- */
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE booking (BookingID INTEGER PRIMARY KEY, BookingConfirmationTitle TEXT)");
$pdo->exec("INSERT INTO booking VALUES
    (1, 'BOOKING CONFIRMATION'),
    (2, 'BOOKING CONFIRMATION'),
    (3, 'QUOTATION'),
    (4, 'PROFORMA INVOICE')");

// Mirrors the apply_booking_filters branch: default = BC only, else = IN(list).
function bc_title_where($param) {
    if ($param !== '' && $param !== null) {
        $in = implode(',', array_map(fn($v) => "'" . $v . "'", explode(',', $param)));
        return "BookingConfirmationTitle IN ($in)";
    }
    return "BookingConfirmationTitle = 'BOOKING CONFIRMATION'";
}
$ids = function($param) use ($pdo) {
    $sql = "SELECT BookingID FROM booking WHERE " . bc_title_where($param) . " ORDER BY BookingID";
    return array_map('intval', $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN));
};

check('default (no filter) shows only the two BC rows', $ids('') === [1, 2]);
check('filter = QUOTATION shows only the quotation row', $ids('QUOTATION') === [3]);
check('filter = PROFORMA INVOICE shows only the proforma row', $ids('PROFORMA INVOICE') === [4]);
check('filter = QUOTATION,PROFORMA INVOICE shows both non-BC rows', $ids('QUOTATION,PROFORMA INVOICE') === [3, 4]);
check('filter = BOOKING CONFIRMATION matches the default set', $ids('BOOKING CONFIRMATION') === [1, 2]);

echo "\n";
if ($failures === 0) {
    echo "All assertions passed.\n";
    exit(0);
}
echo "{$failures} assertion(s) failed.\n";
exit(1);
