<?php
/**
 * Run with: php tests/helpers/CustomerListRoleTest.php
 *
 * Pins the Guest Role tier + Role column of the Customer List
 * (Guests_Model::Build_Customer_Branch / Read_Customers_Rich).
 *
 * A customer "leads" a booking when that booking's own contact phone key equals
 * the customer's phone key — the same IsLeader idea the Guest List uses, written
 * as COALESCE(NULLIF(booking_mobile_key,''), customer_key) = customer_key:
 *   - booking has no distinct mobile  → leader is the customer  (Team Leader)
 *   - booking mobile == customer phone → leader is the customer  (Team Leader)
 *   - booking mobile != customer phone → someone else leads      (Team Member)
 *
 * MySQL's REGEXP_REPLACE/RIGHT are unavailable in SQLite, so the last-9 keys are
 * stored directly (mirrors GuestListLeaderFallbackTest). This pins the portable
 * WHERE core of "Team Leader" vs "Team Member".
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
    phone_key TEXT, Status TEXT)");
$pdo->exec("CREATE TABLE booking (BookingID INTEGER PRIMARY KEY, CustomerID INTEGER,
    mobile_key TEXT, Status TEXT, CancelStatus TEXT)");

// C1: booking mobile matches own phone key            → Team Leader
// C2: booking mobile is blank (falls back to customer) → Team Leader
// C3: booking mobile belongs to a different person     → Team Member
$pdo->exec("INSERT INTO customer (CustomerID, name, phone_number, phone_key, Status) VALUES
    (1, 'Leader Match', '011111', '111111', 'Y'),
    (2, 'Leader Blank', '022222', '222222', 'Y'),
    (3, 'Member Only',  '033333', '333333', 'Y')");

$pdo->exec("INSERT INTO booking (BookingID, CustomerID, mobile_key, Status, CancelStatus) VALUES
    (10, 1, '111111', 'A', 'N'),
    (20, 2, '',       'A', 'N'),
    (30, 3, '999999', 'A', 'N')");

$base = "c.Status = 'Y'";

// The leader-EXISTS core (mirrors Build_Customer_Branch's $leads_expr): a booking
// this customer leads by phone key. NULLIF('' ,'') → NULL, so COALESCE falls back
// to the customer's own key exactly like the model.
$leads = "EXISTS (SELECT 1 FROM booking b
    WHERE b.CustomerID = c.CustomerID AND b.Status <> 'N' AND b.CancelStatus = 'N'
        AND COALESCE(NULLIF(b.mobile_key, ''), c.phone_key) = c.phone_key)";

function names_where($pdo, $where, $params = array()) {
    $st = $pdo->prepare("SELECT c.name FROM customer c WHERE {$where} ORDER BY c.CustomerID");
    $st->execute($params);
    return array_map(function ($r) { return $r['name']; }, $st->fetchAll(PDO::FETCH_ASSOC));
}

// Role = "Team Leader" → leads at least one booking.
assert_eq('Team Leader filter',
    array('Leader Match', 'Leader Blank'),
    names_where($pdo, "{$base} AND {$leads}"));

// Role = "Team Member" → never leads.
assert_eq('Team Member filter',
    array('Member Only'),
    names_where($pdo, "{$base} AND NOT {$leads}"));

// Role column value derived from the same leader condition.
$role_col = "SELECT c.name, CASE WHEN {$leads} THEN 'Team Leader' ELSE 'Team Member' END AS Role
    FROM customer c ORDER BY c.CustomerID";
$rows = $pdo->query($role_col)->fetchAll(PDO::FETCH_ASSOC);
assert_eq('Role column C1', 'Team Leader', $rows[0]['Role']);
assert_eq('Role column C2', 'Team Leader', $rows[1]['Role']);
assert_eq('Role column C3', 'Team Member', $rows[2]['Role']);

echo "\nAll CustomerListRole assertions passed.\n";
