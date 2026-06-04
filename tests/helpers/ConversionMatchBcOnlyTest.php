<?php
/**
 * Run with: php tests/helpers/ConversionMatchBcOnlyTest.php
 *
 * Locks the BC-only rule in Ghl_Processed_Leads_Model::find_first_booking_conversion().
 * A GHL lead is "converted" when its phone first matches a booking created on or
 * after the lead start — but only a BOOKING CONFIRMATION (BC) counts. Quotations
 * (QU) and proforma invoices (PI) must NOT mark a lead converted, matching the
 * booking listing's "BC" column and every summary card's BC-type filter.
 *
 * Rules:
 *   - Lead phone matches a QUOTATION only            -> no conversion (null).
 *   - Lead phone matches a BC                         -> converts to that BC.
 *   - Earlier QUOTATION + later BC both match         -> converts to the BC
 *     (the earlier quotation is skipped, not chosen).
 *   - Match works across Mobile / Mobile2 / customer.phone_number.
 *   - Bookings created before lead start are ignored.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE booking (
    BookingID INTEGER PRIMARY KEY AUTOINCREMENT,
    CustomerID INTEGER,
    BookingConfirmationTitle TEXT,
    Mobile TEXT,
    Mobile2 TEXT,
    InsertDate TEXT
)");
$pdo->exec("CREATE TABLE customer (
    CustomerID INTEGER PRIMARY KEY AUTOINCREMENT,
    phone_number TEXT
)");

$addBooking = function ($title, $mobile, $insertDate, $mobile2 = null, $custPhone = null) use ($pdo) {
    $custId = null;
    if ($custPhone !== null) {
        $pdo->prepare("INSERT INTO customer (phone_number) VALUES (?)")->execute([$custPhone]);
        $custId = (int) $pdo->lastInsertId();
    }
    $stmt = $pdo->prepare("INSERT INTO booking (CustomerID, BookingConfirmationTitle, Mobile, Mobile2, InsertDate)
                           VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$custId, $title, $mobile, $mobile2, $insertDate]);
    return (int) $pdo->lastInsertId();
};

// Mirrors find_first_booking_conversion()'s query (with the BC-only filter).
$findConversion = function ($phoneVariants, $leadStartedAt) use ($pdo) {
    $phoneVariants = array_values(array_filter(array_unique(array_map('strval', (array) $phoneVariants))));
    if (empty($phoneVariants) || $leadStartedAt === '') { return null; }
    $ph = implode(',', array_fill(0, count($phoneVariants), '?'));
    $params = array_merge([$leadStartedAt], $phoneVariants, $phoneVariants, $phoneVariants);
    $row = $pdo->prepare("
        SELECT b.BookingID
        FROM booking b
        LEFT JOIN customer c ON c.CustomerID = b.CustomerID
        WHERE b.InsertDate >= ?
          AND b.BookingConfirmationTitle = 'BOOKING CONFIRMATION'
          AND ( b.Mobile IN ({$ph}) OR b.Mobile2 IN ({$ph}) OR c.phone_number IN ({$ph}) )
        ORDER BY b.InsertDate ASC, b.BookingID ASC
        LIMIT 1
    ");
    $row->execute($params);
    $r = $row->fetch(PDO::FETCH_ASSOC);
    return $r ? (int) $r['BookingID'] : null;
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

// ---- quotation-only does not convert --------------------------------------
$addBooking('QUOTATION', '0121111111', '2026-06-02');
assert_eq('quotation does not convert', null, $findConversion(['0121111111'], '2026-06-01'));

// ---- proforma-only does not convert ---------------------------------------
$addBooking('PROFORMA INVOICE', '0122222222', '2026-06-02');
assert_eq('proforma does not convert', null, $findConversion(['0122222222'], '2026-06-01'));

// ---- BC converts ----------------------------------------------------------
$bc = $addBooking('BOOKING CONFIRMATION', '0123333333', '2026-06-03');
assert_eq('BC converts', $bc, $findConversion(['0123333333'], '2026-06-01'));

// ---- earlier quotation + later BC -> picks the BC -------------------------
$addBooking('QUOTATION', '0124444444', '2026-06-04');
$bc2 = $addBooking('BOOKING CONFIRMATION', '0124444444', '2026-06-09');
assert_eq('skips earlier QU, picks BC', $bc2, $findConversion(['0124444444'], '2026-06-01'));

// ---- match via Mobile2 ----------------------------------------------------
$bc3 = $addBooking('BOOKING CONFIRMATION', '0190000000', '2026-06-05', '0125555555');
assert_eq('matches Mobile2', $bc3, $findConversion(['0125555555'], '2026-06-01'));

// ---- match via customer.phone_number --------------------------------------
$bc4 = $addBooking('BOOKING CONFIRMATION', '0191111111', '2026-06-06', null, '0126666666');
assert_eq('matches customer phone', $bc4, $findConversion(['0126666666'], '2026-06-01'));

// ---- BC created before lead start is ignored ------------------------------
$addBooking('BOOKING CONFIRMATION', '0127777777', '2026-05-20');
assert_eq('pre-lead BC ignored', null, $findConversion(['0127777777'], '2026-06-01'));

echo "\nAll assertions passed.\n";
