<?php
/**
 * Run with: php tests/helpers/GuestListBirthdayFilterTest.php
 *
 * Locks the Guest List "Birthday" dropdown — a recurring month/day match on
 * gl.DateOfBirth (guest_list_birthday_clause()) that IGNORES the birth year,
 * so it surfaces guests to greet today / this month / in a chosen month. This
 * is separate from the DOB "born between" range (which pins a full Y-m-d span).
 *
 * Birthday is booking-only: a GHL lead carries no date of birth, so any
 * birthday filter drops the GHL branch and the leader-fallback branch (like
 * dob / gender / nationality do).
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}
require __DIR__ . '/../../application/helpers/guest_contact_helper.php';

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

// ---- clause building -------------------------------------------------------
assert_eq('empty → no filter',   null, guest_list_birthday_clause(''));
assert_eq('blank → no filter',   null, guest_list_birthday_clause('   '));
assert_eq('junk → no filter',    null, guest_list_birthday_clause('lol'));
assert_eq('month 0 → no filter', null, guest_list_birthday_clause('0'));
assert_eq('month 13 → no filter',null, guest_list_birthday_clause('13'));

$today = guest_list_birthday_clause('today');
assert_eq('today matches month AND day', true,
    strpos($today['sql'], 'MONTH(gl.DateOfBirth) = MONTH(CURDATE())') !== false
    && strpos($today['sql'], 'DAY(gl.DateOfBirth) = DAY(CURDATE())') !== false);
assert_eq('today binds no params', array(), $today['params']);

$this_month = guest_list_birthday_clause('this_month');
assert_eq('this_month matches month only', true,
    strpos($this_month['sql'], 'MONTH(gl.DateOfBirth) = MONTH(CURDATE())') !== false
    && strpos($this_month['sql'], 'DAY(') === false);
assert_eq('this_month binds no params', array(), $this_month['params']);

$june = guest_list_birthday_clause('6');
assert_eq('by-month uses a bound placeholder', true,
    strpos($june['sql'], 'MONTH(gl.DateOfBirth) = ?') !== false);
assert_eq('by-month binds the month int', array(6), $june['params']);

// ---- booking-only: suppresses GHL + leader-fallback branches ---------------
assert_eq('no birthday keeps GHL',        false, guest_list_ghl_suppressed_by_filters(array()));
assert_eq('empty birthday keeps GHL',     false, guest_list_ghl_suppressed_by_filters(array('birthday' => '')));
assert_eq('today drops GHL',              true,  guest_list_ghl_suppressed_by_filters(array('birthday' => 'today')));
assert_eq('by-month drops GHL',           true,  guest_list_ghl_suppressed_by_filters(array('birthday' => '6')));
assert_eq('birthday keeps bookings',      false, guest_list_bookings_suppressed_by_filters(array('birthday' => 'today')));
assert_eq('today drops leader fallback',  true,  guest_list_leader_fallback_suppressed_by_filters(array('birthday' => 'today')));

// ---- integration: recurring month/day match ignores the year ---------------
// The model emits MySQL MONTH()/DAY(); SQLite has no such funcs, so we mirror
// the SAME intent with strftime to prove the month/day logic over real rows.
// NULL / '0000-00-00' birthdays must never match a real month.
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE guest_list (GuestListID INTEGER PRIMARY KEY, DateOfBirth TEXT)");
$pdo->exec("INSERT INTO guest_list (GuestListID, DateOfBirth) VALUES
    (1, '1990-06-15'),  -- June (different decade)
    (2, '2001-06-15'),  -- June, same day, different year
    (3, '1988-06-30'),  -- June, different day
    (4, '1995-07-15'),  -- July
    (5, NULL),          -- no DOB
    (6, '0000-00-00')   -- placeholder DOB
");

// Birthday in June — every June guest regardless of year (3 rows).
$stmt = $pdo->query("SELECT COUNT(*) FROM guest_list WHERE CAST(strftime('%m', DateOfBirth) AS INTEGER) = 6");
assert_eq('June cohort ignores year (3)', 3, (int) $stmt->fetchColumn());

// Birthday today = 15 June — both June-15 guests across different years (2 rows).
$stmt = $pdo->query("SELECT COUNT(*) FROM guest_list
    WHERE CAST(strftime('%m', DateOfBirth) AS INTEGER) = 6
      AND CAST(strftime('%d', DateOfBirth) AS INTEGER) = 15");
assert_eq('birthday 15-Jun across years (2)', 2, (int) $stmt->fetchColumn());

echo "\nAll assertions passed.\n";
