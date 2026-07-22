<?php
/**
 * Run with: php tests/helpers/CustomerListAnchorTest.php
 *
 * Pins the Customer List (Guests_Model::Read_Customers_Rich / Count_Customers_Rich)
 * — the Guest List's twin anchored on the customer master table.
 *
 * The production query is MySQL-only (REGEXP_REPLACE, ROW_NUMBER() OVER,
 * GROUP_CONCAT ... SEPARATOR, DATE_ADD), so it cannot run under SQLite. Instead
 * this reproduces the branch's WHERE *cores* as portable SQL against
 * sqlite::memory: (storing the last-9 phone key directly, like
 * GuestListLeaderFallbackTest) and pins the two invariants that matter:
 *
 *   1. ONE ROW PER CUSTOMER — booking/guest/remark predicates are EXISTS, so a
 *      customer with several bookings never fans out (Count == listing count).
 *   2. Each of the three filter tiers (booking / guest / remark) + the pax
 *      aggregate gates the customer in/out correctly.
 */

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

$pdo->exec("CREATE TABLE customer (CustomerID INTEGER PRIMARY KEY, name TEXT, phone_number TEXT,
    phone_key TEXT, PrimaryEmail TEXT, ChatLanguage TEXT, customer_type TEXT, Status TEXT)");
$pdo->exec("CREATE TABLE booking (BookingID INTEGER PRIMARY KEY, CustomerID INTEGER, Customer TEXT,
    Mobile TEXT, SalesAgent INTEGER, Status TEXT, CancelStatus TEXT, Adult INTEGER, Children INTEGER, Infant INTEGER)");
$pdo->exec("CREATE TABLE guest_list (GuestListID INTEGER PRIMARY KEY, BookingID INTEGER, Status TEXT,
    dedup_key TEXT, Gender TEXT, Type TEXT)");
$pdo->exec("CREATE TABLE guest_remarks (RemarkID INTEGER PRIMARY KEY, dedup_key TEXT, Status TEXT,
    CampaignDate TEXT, FollowDate TEXT)");

// C1: one customer, phone key 123456789, with TWO active bookings + ONE cancelled.
//     (agent 5 on the first active booking.)
// C2: a second customer (agent 9) so filters can exclude C1 without emptying the set.
// C3: an inactive/blank-phone customer that the anchor must always drop.
$pdo->exec("INSERT INTO customer (CustomerID, name, phone_number, phone_key, PrimaryEmail, ChatLanguage, customer_type, Status) VALUES
    (1, 'Alice Tan',  '0123456789', '123456789', 'alice@x.com', 'EN', 'VIP',    'Y'),
    (2, 'Bob Lee',    '0129999999', '129999999', 'bob@x.com',   'CN', 'NORMAL', 'Y'),
    (3, 'Ghost',      '',           '',           NULL,         NULL, NULL,     'Y')");

$pdo->exec("INSERT INTO booking (BookingID, CustomerID, Customer, Mobile, SalesAgent, Status, CancelStatus, Adult, Children, Infant) VALUES
    (10, 1, 'Alice Tan', '0123456789', 5, 'A', 'N', 2, 0, 0),
    (11, 1, 'Alice Tan', '0123456789', 5, 'A', 'N', 1, 0, 0),
    (12, 1, 'Alice Tan', '0123456789', 5, 'N', 'N', 9, 0, 0),
    (20, 2, 'Bob Lee',   '0129999999', 9, 'A', 'N', 4, 0, 0)");

$pdo->exec("INSERT INTO guest_list (GuestListID, BookingID, Status, dedup_key, Gender, Type) VALUES
    (100, 10, 'Y', '123456789', 'Male',   'ADULT'),
    (101, 20, 'Y', '129999999', 'Female', 'ADULT')");

$pdo->exec("INSERT INTO guest_remarks (RemarkID, dedup_key, Status, CampaignDate, FollowDate) VALUES
    (1000, '123456789', 'Y', '2026-07-10', '2026-07-20')");

// The anchor + base predicates (mirrors Build_Customer_Branch's base WHERE).
$anchor = "FROM customer c WHERE c.Status = 'Y'
    AND NULLIF(TRIM(c.name), '')         IS NOT NULL
    AND NULLIF(TRIM(c.phone_number), '') IS NOT NULL";

function count_rows($pdo, $where, $params = array(), $joins = '') {
    $sql = "SELECT COUNT(*) AS cnt FROM customer c {$joins} WHERE " . $where;
    $st  = $pdo->prepare($sql);
    $st->execute($params);
    return (int) $st->fetch(PDO::FETCH_ASSOC)['cnt'];
}

$base = "c.Status = 'Y' AND NULLIF(TRIM(c.name), '') IS NOT NULL AND NULLIF(TRIM(c.phone_number), '') IS NOT NULL";

// ---- 1. one row per customer, no fan-out; blank/phone-less dropped ----
assert_eq('anchor: 2 valid customers (C1 with 3 bookings counts once, Ghost dropped)',
    2, count_rows($pdo, $base));

// ---- 2a. booking-tier EXISTS (sales_agent) ----
$exists_agent = $base . " AND EXISTS (SELECT 1 FROM booking b
    WHERE b.CustomerID = c.CustomerID AND b.Status <> 'N' AND b.CancelStatus = 'N' AND b.SalesAgent IN (5))";
assert_eq('booking EXISTS agent 5 → only Alice (still one row)', 1, count_rows($pdo, $exists_agent));

$exists_agent_none = $base . " AND EXISTS (SELECT 1 FROM booking b
    WHERE b.CustomerID = c.CustomerID AND b.Status <> 'N' AND b.CancelStatus = 'N' AND b.SalesAgent IN (77))";
assert_eq('booking EXISTS agent 77 → nobody', 0, count_rows($pdo, $exists_agent_none));

// A cancelled-only match must NOT surface the customer (booking 12 is Status N).
$exists_cancelled = $base . " AND EXISTS (SELECT 1 FROM booking b
    WHERE b.CustomerID = c.CustomerID AND b.Status <> 'N' AND b.CancelStatus = 'N' AND b.Adult = 9)";
assert_eq('booking EXISTS ignores cancelled booking', 0, count_rows($pdo, $exists_cancelled));

// ---- 2b. guest-tier EXISTS (gender, keyed by phone) ----
$exists_male = $base . " AND EXISTS (SELECT 1 FROM guest_list gl
    WHERE gl.Status = 'Y' AND gl.dedup_key = c.phone_key AND gl.Gender IN ('Male'))";
assert_eq('guest EXISTS gender Male → only Alice', 1, count_rows($pdo, $exists_male));

$exists_female = $base . " AND EXISTS (SELECT 1 FROM guest_list gl
    WHERE gl.Status = 'Y' AND gl.dedup_key = c.phone_key AND gl.Gender IN ('Female'))";
assert_eq('guest EXISTS gender Female → only Bob', 1, count_rows($pdo, $exists_female));

// ---- 2c. remark-tier EXISTS (campaign date range) ----
$exists_remark = $base . " AND EXISTS (SELECT 1 FROM guest_remarks gr
    WHERE gr.Status = 'Y' AND gr.dedup_key = c.phone_key
    AND gr.CampaignDate >= ? AND gr.CampaignDate <= ?)";
assert_eq('remark EXISTS in range → Alice', 1, count_rows($pdo, $exists_remark, array('2026-07-01', '2026-07-31')));
assert_eq('remark EXISTS out of range → nobody', 0, count_rows($pdo, $exists_remark, array('2026-06-01', '2026-06-30')));

// ---- 2d. pax aggregate (SUM across active bookings), bound PARAM_INT ----
$pax_join = "LEFT JOIN (SELECT b.CustomerID,
        SUM(COALESCE(b.Adult,0)+COALESCE(b.Children,0)+COALESCE(b.Infant,0)) AS TotalPax
    FROM booking b WHERE b.Status <> 'N' AND b.CancelStatus = 'N' GROUP BY b.CustomerID) bagg
    ON bagg.CustomerID = c.CustomerID";

function count_pax($pdo, $base, $pax_join, $op, $val) {
    $sql = "SELECT COUNT(*) AS cnt FROM customer c {$pax_join} WHERE {$base} AND bagg.TotalPax {$op} ?";
    $st  = $pdo->prepare($sql);
    $st->bindValue(1, (int) $val, PDO::PARAM_INT);
    $st->execute();
    return (int) $st->fetch(PDO::FETCH_ASSOC)['cnt'];
}
// Alice active pax = 2 + 1 = 3 (cancelled 9 excluded); Bob = 4.
assert_eq('pax >= 3 → Alice + Bob', 2, count_pax($pdo, $base, $pax_join, '>=', 3));
assert_eq('pax >= 4 → only Bob',    1, count_pax($pdo, $base, $pax_join, '>=', 4));
assert_eq('pax <= 3 → only Alice',  1, count_pax($pdo, $base, $pax_join, '<=', 3));

echo "\nAll CustomerListAnchor assertions passed.\n";
