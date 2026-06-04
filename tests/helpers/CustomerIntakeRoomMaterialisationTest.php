<?php
/**
 * Run with: php tests/helpers/CustomerIntakeRoomMaterialisationTest.php
 *
 * Locks the downstream-materialisation pattern that
 * Booking_Customer_Intake_Model::save_submission() performs after writing the
 * intake row + intake-room rows:
 *
 *   1. Sum the intake rooms into adult/child/baby totals (child & baby are
 *      counted from their comma-separated age lists). compute_intake_pax_totals()
 *      lives in customer_intake_helper.php and is exercised directly.
 *
 *   2. Insert one guest_list_room row per intake room (room_type UPPERCASED to
 *      match the existing convention in Booking::Create()), but ONLY when the
 *      booking has zero existing guest_list_room rows. This prevents the public
 *      submission from clobbering rooms staff may have started manually.
 *
 *   3. Overwrite booking.Adult/Children/Infant with the totals from step 1.
 *      The columns are VARCHAR(95) in production so we assert string values.
 *
 * The SQL portion runs against an in-memory SQLite copy of the schema (same
 * pattern as CustomerIntakeLockSchemaTest / ActiveLeadsCountTest).
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require_once __DIR__ . '/../../application/helpers/customer_intake_helper.php';

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

// -- 1) Pure pax-totals helper -------------------------------------------

$rooms_two = array(
    array('room_type' => 'Deluxe',  'adult_count' => 2, 'child_ages' => '5,7', 'baby_ages' => '1'),
    array('room_type' => 'Standard','adult_count' => 2, 'child_ages' => null,  'baby_ages' => null),
);
$t = compute_intake_pax_totals($rooms_two);
assert_eq('2-room totals adult', 4, $t['adult']);
assert_eq('2-room totals child', 2, $t['child']);
assert_eq('2-room totals baby',  1, $t['baby']);

// Empty rooms
$t = compute_intake_pax_totals(array());
assert_eq('empty totals adult', 0, $t['adult']);
assert_eq('empty totals child', 0, $t['child']);
assert_eq('empty totals baby',  0, $t['baby']);

// Single room, no children / babies
$rooms_one = array(
    array('room_type' => 'Suite', 'adult_count' => 3, 'child_ages' => '', 'baby_ages' => ''),
);
$t = compute_intake_pax_totals($rooms_one);
assert_eq('single room totals adult', 3, $t['adult']);
assert_eq('single room totals child', 0, $t['child']);
assert_eq('single room totals baby',  0, $t['baby']);

// String/null cleanup
$rooms_messy = array(
    array('room_type' => 'A', 'adult_count' => 1, 'child_ages' => '4',     'baby_ages' => null),
    array('room_type' => 'B', 'adult_count' => 0, 'child_ages' => null,    'baby_ages' => '2,3'),
);
$t = compute_intake_pax_totals($rooms_messy);
assert_eq('messy totals adult', 1, $t['adult']);
assert_eq('messy totals child', 1, $t['child']);
assert_eq('messy totals baby',  2, $t['baby']);

// -- 2) SQL materialisation against SQLite -------------------------------

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Minimal versions of the production schemas (only the columns we touch).
$pdo->exec("CREATE TABLE booking (
    BookingID INTEGER PRIMARY KEY,
    Adult TEXT,
    Children TEXT,
    Infant TEXT
)");
$pdo->exec("CREATE TABLE booking_customer_intake_room (
    id INTEGER PRIMARY KEY,
    intake_id INTEGER,
    room_type TEXT,
    adult_count INTEGER,
    child_ages TEXT,
    baby_ages TEXT,
    sort_order INTEGER
)");
$pdo->exec("CREATE TABLE guest_list_room (
    id INTEGER PRIMARY KEY,
    booking_id INTEGER,
    room_name TEXT,
    adult_count INTEGER,
    child_count INTEGER,
    infant_count INTEGER,
    Status TEXT,
    InsertBy INTEGER,
    InsertDate TEXT
)");

// Re-usable harness: insert intake rooms, then run the materialisation
// pattern, then return the resulting state.
function materialise(PDO $pdo, $booking_id, array $rooms, $glr_seed = array())
{
    // Reset state for repeat runs
    $pdo->exec("DELETE FROM booking_customer_intake_room");
    $pdo->exec("DELETE FROM guest_list_room");
    $pdo->exec("DELETE FROM booking");
    $pdo->exec("INSERT INTO booking (BookingID, Adult, Children, Infant) VALUES ({$booking_id}, '0', '0', '0')");
    foreach ($glr_seed as $g) {
        $stmt = $pdo->prepare("INSERT INTO guest_list_room (booking_id, room_name, adult_count, child_count, infant_count, Status) VALUES (?, ?, ?, ?, ?, 'Y')");
        $stmt->execute(array($booking_id, $g[0], $g[1], $g[2], $g[3]));
    }
    $order = 0;
    foreach ($rooms as $r) {
        $stmt = $pdo->prepare("INSERT INTO booking_customer_intake_room (intake_id, room_type, adult_count, child_ages, baby_ages, sort_order) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute(array(1, $r['room_type'], $r['adult_count'], $r['child_ages'], $r['baby_ages'], $order++));
    }

    // -- Pattern under test --
    $totals = compute_intake_pax_totals($rooms);
    $update = $pdo->prepare("UPDATE booking SET Adult = ?, Children = ?, Infant = ? WHERE BookingID = ?");
    $update->execute(array(
        (string) $totals['adult'],
        (string) $totals['child'],
        (string) $totals['baby'],
        $booking_id,
    ));

    // Hardened seeding: seed unless a *real* room (with pax) already exists.
    // Empty placeholder rooms (0/0/0 — e.g. the booking form's default "ROOM 1")
    // are cleared first so they can't block the customer's submission.
    $existing = $pdo->query("SELECT id, adult_count, child_count, infant_count FROM guest_list_room WHERE booking_id = {$booking_id}")->fetchAll(PDO::FETCH_ASSOC);
    $has_real_room = false;
    $empty_ids = array();
    foreach ($existing as $er) {
        if (((int) $er['adult_count'] + (int) $er['child_count'] + (int) $er['infant_count']) > 0) {
            $has_real_room = true;
        } else {
            $empty_ids[] = (int) $er['id'];
        }
    }
    if (!$has_real_room) {
        if (!empty($empty_ids)) {
            $pdo->exec("DELETE FROM guest_list_room WHERE id IN (" . implode(',', $empty_ids) . ")");
        }
        foreach ($rooms as $r) {
            $child_count = count_age_list($r['child_ages']);
            $baby_count  = count_age_list($r['baby_ages']);
            $stmt = $pdo->prepare("INSERT INTO guest_list_room (booking_id, room_name, adult_count, child_count, infant_count, Status, InsertBy, InsertDate) VALUES (?, ?, ?, ?, ?, 'Y', NULL, '2026-05-22 09:00:00')");
            $stmt->execute(array(
                $booking_id,
                strtoupper($r['room_type']),
                (int) $r['adult_count'],
                $child_count,
                $baby_count,
            ));
        }
    }

    $booking_row = $pdo->query("SELECT Adult, Children, Infant FROM booking WHERE BookingID = {$booking_id}")->fetch(PDO::FETCH_ASSOC);
    $rows = $pdo->query("SELECT room_name, adult_count, child_count, infant_count FROM guest_list_room WHERE booking_id = {$booking_id} ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    return array('booking' => $booking_row, 'rooms' => $rows);
}

// Helper mirrors the production logic the model will use to count ages.
function count_age_list($input)
{
    if ($input === null || $input === '') {
        return 0;
    }
    $parts = array_filter(array_map('trim', explode(',', (string) $input)), 'strlen');
    return count($parts);
}

// Scenario A: two rooms, fresh booking, no pre-existing guest_list_room rows.
$state = materialise($pdo, 9407, $rooms_two);
assert_eq('A: booking.Adult = "4"',    '4', $state['booking']['Adult']);
assert_eq('A: booking.Children = "2"', '2', $state['booking']['Children']);
assert_eq('A: booking.Infant = "1"',   '1', $state['booking']['Infant']);
assert_eq('A: 2 guest_list_rooms',     2,   count($state['rooms']));
assert_eq('A: row 1 name UPPER',      'DELUXE',   $state['rooms'][0]['room_name']);
assert_eq('A: row 1 adults',          2,          (int) $state['rooms'][0]['adult_count']);
assert_eq('A: row 1 child_count = 2', 2,          (int) $state['rooms'][0]['child_count']);
assert_eq('A: row 1 infant_count = 1',1,          (int) $state['rooms'][0]['infant_count']);
assert_eq('A: row 2 name UPPER',      'STANDARD', $state['rooms'][1]['room_name']);
assert_eq('A: row 2 adults',          2,          (int) $state['rooms'][1]['adult_count']);
assert_eq('A: row 2 child_count = 0', 0,          (int) $state['rooms'][1]['child_count']);
assert_eq('A: row 2 infant_count = 0',0,          (int) $state['rooms'][1]['infant_count']);

// Scenario B: a real staff-seeded guest_list_room row (with pax) already
// exists. The materialisation MUST NOT add intake rooms on top.
$state = materialise($pdo, 9407, $rooms_two, array(
    array('STAFF ROOM', 1, 0, 0),
));
assert_eq('B: pax totals still overwritten (Adult=4)', '4', $state['booking']['Adult']);
assert_eq('B: guest_list_room count stays at 1',       1,   count($state['rooms']));
assert_eq('B: staff room preserved verbatim',          'STAFF ROOM', $state['rooms'][0]['room_name']);

// Scenario D: only an EMPTY placeholder room exists (0/0/0 — the booking
// form's default "ROOM 1"). It must be cleared and replaced by the customer's
// intake rooms, not left to block the seed. This is the BC-2606-0030 bug.
$state = materialise($pdo, 9407, $rooms_two, array(
    array('ROOM 1', 0, 0, 0),
));
assert_eq('D: placeholder replaced, 2 intake rooms', 2, count($state['rooms']));
assert_eq('D: row 1 is the intake room',  'DELUXE',   $state['rooms'][0]['room_name']);
assert_eq('D: no leftover ROOM 1',        false,      in_array('ROOM 1', array_column($state['rooms'], 'room_name'), true));

// Scenario E: a real room AND an empty placeholder coexist. The real room is
// preserved and the customer's rooms are skipped (staff data wins).
$state = materialise($pdo, 9407, $rooms_two, array(
    array('STAFF ROOM', 2, 0, 0),
    array('ROOM 1', 0, 0, 0),
));
assert_eq('E: staff data wins, both staff rows kept', 2, count($state['rooms']));
assert_eq('E: no intake room seeded (DELUXE absent)', false, in_array('DELUXE', array_column($state['rooms'], 'room_name'), true));

// Scenario C: zero intake rooms (edge case — caller shouldn't normally hit
// this since validation requires at least one room, but be defensive).
$state = materialise($pdo, 9407, array());
assert_eq('C: totals reset to 0 / 0 / 0', '0', $state['booking']['Adult']);
assert_eq('C: no guest_list_room rows',   0,   count($state['rooms']));

echo "\nAll assertions passed.\n";
