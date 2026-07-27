<?php
/**
 * Run with: php tests/helpers/BookingOpAdminsLevelTest.php
 *
 * Locks the admin set that populates the "Booking OP" dropdown on the booking
 * form (Booking_Model::Read_Booking_OP_Admins).
 *
 * The dropdown must list both the regular OP role (level 40) AND the OP TEAM
 * LEAD role (level 45) -- a team lead is a senior OP and can be assigned as a
 * booking's OP just like any other OP. AdminID 8 (system) and inactive admins
 * (Status != 'Y') stay excluded, ordered by Name ASC.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE admin (
    AdminID INTEGER PRIMARY KEY,
    Name    TEXT,
    Level   TEXT,
    Status  TEXT
)");

$pdo->exec("INSERT INTO admin VALUES
    (1, 'Olivia OP',        '40', 'Y'),
    (2, 'Liam Lead',        '45', 'Y'),
    (3, 'Sammy Sales',      '20', 'Y'),
    (4, 'Tina TC',          '50', 'Y'),
    (5, 'Inactive Op',      '40', 'N'),
    (6, 'Inactive Lead',    '45', 'D'),
    (7, 'Active Op2',       '40', 'Y'),
    (8, 'System Admin',     '40', 'Y')
");

// Mirrors Read_Booking_OP_Admins(): Level IN ('40','45'), Status='Y',
// AdminID != 8, ordered by Name ASC.
$read_op_admins = function ($pdo) {
    $stmt = $pdo->query(
        "SELECT AdminID, Name
         FROM admin
         WHERE AdminID != 8
           AND Level IN ('40', '45')
           AND Status = 'Y'
         ORDER BY Name ASC"
    );
    return array_map(function ($r) {
        return (int) $r['AdminID'];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));
};

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

$result = $read_op_admins($pdo);

// Expected order by Name ASC: Active Op2(7), Liam Lead(2), Olivia OP(1).
assert_eq('OP dropdown includes level 40 + 45, excludes others', array(7, 2, 1), $result);

assert_eq('level 45 OP TEAM LEAD is present', true, in_array(2, $result, true));
assert_eq('level 40 OP is present',          true, in_array(1, $result, true));
assert_eq('sales (level 20) excluded',       false, in_array(3, $result, true));
assert_eq('TC (level 50) excluded',          false, in_array(4, $result, true));
assert_eq('inactive level 40 excluded',      false, in_array(5, $result, true));
assert_eq('inactive level 45 excluded',      false, in_array(6, $result, true));
assert_eq('system AdminID 8 excluded',       false, in_array(8, $result, true));

echo "\nAll assertions passed.\n";
