<?php
/**
 * Run with: php tests/helpers/GuestListInlineEditAnyGuestTest.php
 *
 * Pins the "inline edit works for ANY guest, not just the booking leader"
 * change on the Guest List dashboard (see the two removed restrictions):
 *
 *   1. Language is now a PER-GUEST field: guest_list.ChatLanguage. Editing a
 *      plain team MEMBER writes ONLY their own guest_list row — the booking /
 *      customer (the leader's language) is left untouched. Editing a LEADER
 *      writes their guest_list row AND the booking + customer, so their master
 *      record stays in sync. The listing shows COALESCE(gl, customer, booking),
 *      so an edited member shows their own language and an un-edited member
 *      still falls back to the booking's.
 *
 *   2. Contact edit on a synthetic "leader fallback" row (a booking whose guest
 *      list was never filled, so NO guest_list row exists) now reports success:
 *      the update still lands on booking.Mobile, so the affected count must
 *      include the booking write, not just the (zero) guest_list write.
 *
 * The model runs these on MySQL; here they are mirrored on SQLite with the same
 * intent (leader-key = the booking's own phone; member-key = the guest's phone).
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
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

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE booking (
    BookingID INTEGER PRIMARY KEY, Mobile TEXT, CustomerID INTEGER,
    ChatLanguage TEXT, Status TEXT DEFAULT 'A', CancelStatus TEXT DEFAULT 'N')");
$pdo->exec("CREATE TABLE customer (
    CustomerID INTEGER PRIMARY KEY, phone_number TEXT, ChatLanguage TEXT)");
$pdo->exec("CREATE TABLE guest_list (
    GuestListID INTEGER PRIMARY KEY, BookingID INTEGER, dedup_key TEXT,
    Mobile TEXT, ChatLanguage TEXT, Status TEXT DEFAULT 'Y')");

// Booking 1: leader "111111111" (customer 1), member "222222222".
// Booking 2: leader "333333333" filled the guest list.
// Booking 3: leader "444444444" NEVER filled the guest list (no gl row) —
//            this is the synthetic "leader fallback" row.
$pdo->exec("INSERT INTO customer (CustomerID, phone_number, ChatLanguage) VALUES
    (1, '111111111', 'EN'),
    (3, '333333333', 'EN'),
    (4, '444444444', 'EN')");
$pdo->exec("INSERT INTO booking (BookingID, Mobile, CustomerID, ChatLanguage) VALUES
    (1, '111111111', 1, 'EN'),
    (2, '333333333', 3, 'EN'),
    (3, '444444444', 4, 'EN')");
$pdo->exec("INSERT INTO guest_list (GuestListID, BookingID, dedup_key, Mobile, ChatLanguage) VALUES
    (1, 1, '111111111', '111111111', NULL),
    (2, 1, '222222222', '222222222', NULL),
    (3, 2, '333333333', '333333333', NULL)");

// Leader key of a booking = its own Mobile (fallback customer phone), mirroring
// Booking_Leader_Key_Expr(). A guest whose dedup_key equals it is the leader.
$LEADER_KEY = "COALESCE(NULLIF(b.Mobile, ''), c.phone_number)";

// ---- new model behavior, mirrored ----------------------------------------

// Language edit: write the guest's own gl row(s), plus (only when they lead a
// booking) the booking + customer. Returns rows affected across both writes.
$update_language = function ($dedup_key, $lang) use ($pdo, $LEADER_KEY) {
    $gl = $pdo->prepare(
        "UPDATE guest_list SET ChatLanguage = :lang
         WHERE dedup_key = :k AND Status = 'Y'
           AND BookingID IN (SELECT BookingID FROM booking
                             WHERE Status != 'N' AND CancelStatus = 'N')");
    $gl->execute(array(':lang' => $lang, ':k' => $dedup_key));
    $affected = $gl->rowCount();

    $lead_ids = $pdo->prepare(
        "SELECT b.BookingID, b.CustomerID FROM booking b
         LEFT JOIN customer c ON c.CustomerID = b.CustomerID
         WHERE b.Status != 'N' AND b.CancelStatus = 'N' AND {$LEADER_KEY} = :k");
    $lead_ids->execute(array(':k' => $dedup_key));
    foreach ($lead_ids->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $pdo->prepare("UPDATE booking SET ChatLanguage = ? WHERE BookingID = ?")
            ->execute(array($lang, $r['BookingID']));
        $affected++;
        if ($r['CustomerID'] !== null) {
            $pdo->prepare("UPDATE customer SET ChatLanguage = ? WHERE CustomerID = ?")
                ->execute(array($lang, $r['CustomerID']));
            // The real model does a single multi-table UPDATE booking JOIN customer;
            // its affected_rows counts BOTH changed rows, so mirror that here.
            $affected++;
        }
    }
    return $affected;
};

// Contact edit: write the guest's gl row(s) + reflect onto booking.Mobile for
// the bookings they lead. Success = EITHER write touched a row.
$update_contact = function ($dedup_key, $mobile) use ($pdo, $LEADER_KEY) {
    $gl = $pdo->prepare("UPDATE guest_list SET Mobile = :m WHERE dedup_key = :k AND Status = 'Y'");
    $gl->execute(array(':m' => $mobile, ':k' => $dedup_key));
    $affected = $gl->rowCount();

    $b = $pdo->prepare(
        "UPDATE booking SET Mobile = :m
         WHERE BookingID IN (
             SELECT b.BookingID FROM booking b
             LEFT JOIN customer c ON c.CustomerID = b.CustomerID
             WHERE b.Status != 'N' AND b.CancelStatus = 'N' AND {$LEADER_KEY} = :k)");
    $b->execute(array(':m' => $mobile, ':k' => $dedup_key));
    return $affected + $b->rowCount();
};

// Listing shows the guest's own language first, then the booking's.
$display_language = function ($guest_list_id) use ($pdo) {
    $stmt = $pdo->prepare(
        "SELECT COALESCE(gl.ChatLanguage, c.ChatLanguage, b.ChatLanguage) AS Language
         FROM guest_list gl
         JOIN booking b ON b.BookingID = gl.BookingID
         LEFT JOIN customer c ON c.CustomerID = b.CustomerID
         WHERE gl.GuestListID = ?");
    $stmt->execute(array($guest_list_id));
    return $stmt->fetchColumn();
};

// ---- 1. Member language edit lands on the member only ---------------------
assert_eq('member edit affected 1', 1, $update_language('222222222', 'CN'));
assert_eq('member row shows own CN', 'CN', $display_language(2));
// The leader (same booking) is NOT touched.
assert_eq('leader row still EN',      'EN', $display_language(1));
assert_eq('booking 1 ChatLanguage unchanged', 'EN',
    $pdo->query("SELECT ChatLanguage FROM booking WHERE BookingID = 1")->fetchColumn());
assert_eq('customer 1 ChatLanguage unchanged', 'EN',
    $pdo->query("SELECT ChatLanguage FROM customer WHERE CustomerID = 1")->fetchColumn());

// ---- 2. Leader language edit syncs gl + booking + customer ----------------
assert_eq('leader edit affected 3', 3, $update_language('111111111', 'ML')); // gl + booking + customer
assert_eq('leader row shows ML',    'ML', $display_language(1));
assert_eq('booking 1 now ML', 'ML',
    $pdo->query("SELECT ChatLanguage FROM booking WHERE BookingID = 1")->fetchColumn());
assert_eq('customer 1 now ML', 'ML',
    $pdo->query("SELECT ChatLanguage FROM customer WHERE CustomerID = 1")->fetchColumn());

// ---- 3. Un-edited member still falls back to the booking's language --------
assert_eq('member on booking 2 falls back to booking EN', 'EN', $display_language(3));

// ---- 4. Contact edit on a synthetic leader-fallback row succeeds -----------
// Booking 3 has NO guest_list row: gl write affects 0, but booking.Mobile does.
assert_eq('synthetic leader contact affected >= 1', 1, $update_contact('444444444', '999999999'));
assert_eq('booking 3 Mobile updated', '999999999',
    $pdo->query("SELECT Mobile FROM booking WHERE BookingID = 3")->fetchColumn());

// A member contact edit still works via their guest_list row.
assert_eq('member contact affected 1', 1, $update_contact('222222222', '888888888'));

echo "\nAll assertions passed.\n";
