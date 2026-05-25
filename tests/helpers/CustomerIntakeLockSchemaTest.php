<?php
/**
 * Run with: php tests/helpers/CustomerIntakeLockSchemaTest.php
 *
 * Locks the one-shot guarantee of the customer intake feature at the schema
 * level, independent of the CodeIgniter DB layer:
 *
 *   - UNIQUE(booking_id) prevents two intake rows for the same booking.
 *   - The 'locked' column survives a round trip and is queryable for the
 *     is_locked() check used by Booking_Customer_Intake_Model.
 *   - Foreign-key cascade deletes intake_rooms when the intake row is
 *     removed (matches the ON DELETE CASCADE in the migration).
 *
 * SQLite is used as a stand-in for MySQL since both honour PRIMARY KEY,
 * UNIQUE, and ON DELETE CASCADE the same way for the columns we care about.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('PRAGMA foreign_keys = ON');

$pdo->exec("CREATE TABLE booking_customer_intake (
    id INTEGER PRIMARY KEY,
    booking_id INTEGER NOT NULL UNIQUE,
    booking_name TEXT,
    contact_number TEXT,
    ic_passport_no TEXT,
    travel_start_date TEXT,
    travel_end_date TEXT,
    special_remarks TEXT,
    submitted_at TEXT,
    locked INTEGER NOT NULL DEFAULT 1
)");
$pdo->exec("CREATE TABLE booking_customer_intake_room (
    id INTEGER PRIMARY KEY,
    intake_id INTEGER NOT NULL,
    room_type TEXT,
    adult_count INTEGER,
    child_ages TEXT,
    baby_ages TEXT,
    sort_order INTEGER,
    FOREIGN KEY (intake_id) REFERENCES booking_customer_intake(id) ON DELETE CASCADE
)");

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

// -- First submission persists, is locked ---------------------------------

$pdo->exec("INSERT INTO booking_customer_intake (booking_id, booking_name, contact_number, ic_passport_no, travel_start_date, travel_end_date, submitted_at, locked)
            VALUES (777, 'Ali bin Ahmad', '0123456789', 'A12345678', '2026-06-01', '2026-06-05', '2026-05-22 09:00:00', 1)");
$intake_id = (int) $pdo->lastInsertId();
$pdo->exec("INSERT INTO booking_customer_intake_room (intake_id, room_type, adult_count, child_ages, baby_ages, sort_order)
            VALUES ({$intake_id}, 'Deluxe', 2, '5,7', '1', 0)");
$pdo->exec("INSERT INTO booking_customer_intake_room (intake_id, room_type, adult_count, child_ages, baby_ages, sort_order)
            VALUES ({$intake_id}, 'Standard', 2, '', '', 1)");

$row = $pdo->query("SELECT locked FROM booking_customer_intake WHERE booking_id = 777")->fetch(PDO::FETCH_ASSOC);
assert_eq('First submission locked=1', 1, (int) $row['locked']);

$rooms = $pdo->query("SELECT COUNT(*) FROM booking_customer_intake_room WHERE intake_id = {$intake_id}")->fetchColumn();
assert_eq('Two rooms persisted', 2, (int) $rooms);

// -- UNIQUE(booking_id) rejects a second intake row for the same booking --

$caught = false;
try {
    $pdo->exec("INSERT INTO booking_customer_intake (booking_id, booking_name, contact_number, ic_passport_no, travel_start_date, travel_end_date, submitted_at, locked)
                VALUES (777, 'Hacker', '0', 'X', '2026-06-02', '2026-06-02', '2026-05-22 09:30:00', 1)");
} catch (PDOException $e) {
    $caught = true;
}
assert_eq('Duplicate booking_id rejected', true, $caught);

// -- ON DELETE CASCADE clears rooms when intake row is removed ------------

$pdo->exec("DELETE FROM booking_customer_intake WHERE id = {$intake_id}");
$rooms_after = (int) $pdo->query("SELECT COUNT(*) FROM booking_customer_intake_room WHERE intake_id = {$intake_id}")->fetchColumn();
assert_eq('Cascade removed child room rows', 0, $rooms_after);

// -- Different bookings can each have their own intake --------------------

$pdo->exec("INSERT INTO booking_customer_intake (booking_id, booking_name, contact_number, ic_passport_no, travel_start_date, travel_end_date, submitted_at, locked)
            VALUES (888, 'Other', '0', 'Y', '2026-06-03', '2026-06-03', '2026-05-22 10:00:00', 1)");
$pdo->exec("INSERT INTO booking_customer_intake (booking_id, booking_name, contact_number, ic_passport_no, travel_start_date, travel_end_date, submitted_at, locked)
            VALUES (999, 'Other2', '0', 'Z', '2026-06-04', '2026-06-04', '2026-05-22 11:00:00', 1)");
$count_distinct = (int) $pdo->query("SELECT COUNT(*) FROM booking_customer_intake")->fetchColumn();
assert_eq('Independent bookings keep their own intakes', 2, $count_distinct);

echo "\nAll assertions passed.\n";
