<?php
/**
 * Run with: php tests/helpers/GuestContactUpdateTest.php
 *
 * Covers the pure logic behind the Guest List inline contact-number edit
 * (Guests::Update_Contact), from application/helpers/guest_contact_helper.php:
 *
 *   1. guest_contact_normalize_key()    — last-9-digit dedup key (phone branch)
 *   2. guest_contact_validate_mobile()  — input validation gate
 *   3. guest_contact_duplicate_key_sql()— "belongs to another person?" check,
 *      run against a SQLite :memory: schema that mirrors guest_list / booking /
 *      ghl_contacts with the dedup_key column seeded directly (the MySQL
 *      generated column never runs here).
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require_once __DIR__ . '/../../application/helpers/guest_contact_helper.php';

$assertions = array();

// 1) normalize_key ---------------------------------------------------------
$assertions['normalize "+60 12-345 6789" => 123456789'] = guest_contact_normalize_key('+60 12-345 6789') === '123456789';
$assertions['normalize "0123456789" (last 9)']           = guest_contact_normalize_key('0123456789') === '123456789';
$assertions['normalize "123456" keeps short digits']     = guest_contact_normalize_key('123456') === '123456';
$assertions['normalize "" => ""']                        = guest_contact_normalize_key('') === '';
$assertions['normalize "abc" => ""']                     = guest_contact_normalize_key('abc') === '';

// 2) validate_mobile -------------------------------------------------------
$assertions['validate empty rejected']        = guest_contact_validate_mobile('')['ok'] === false;
$assertions['validate too short rejected']    = guest_contact_validate_mobile('12345')['ok'] === false;
$assertions['validate letters rejected']      = guest_contact_validate_mobile('012-ABC-6789')['ok'] === false;
$assertions['validate "+60123456789" ok']     = guest_contact_validate_mobile('+60123456789')['ok'] === true;
$assertions['validate "012-345 6789" ok']     = guest_contact_validate_mobile('012-345 6789')['ok'] === true;
$assertions['validate 16 digits rejected']    = guest_contact_validate_mobile('1234567890123456')['ok'] === false;

// 3) duplicate detection ---------------------------------------------------
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE booking (
    BookingID INTEGER PRIMARY KEY,
    Status TEXT,
    CancelStatus TEXT
)");
$pdo->exec("CREATE TABLE guest_list (
    GuestListID INTEGER PRIMARY KEY,
    BookingID INTEGER,
    Status TEXT,
    Mobile TEXT,
    dedup_key TEXT
)");
$pdo->exec("CREATE TABLE ghl_contacts (
    id INTEGER PRIMARY KEY,
    phone TEXT,
    dedup_key TEXT
)");

// One active booking with two guests: a leader (key 111111111) and another
// person (key 222222222). A cancelled booking holds key 999999999 (must be
// ignored). A GHL lead holds key 333333333.
$pdo->exec("INSERT INTO booking VALUES
    (1, 'Y', 'N'),
    (2, 'Y', 'Y')
");
$pdo->exec("INSERT INTO guest_list VALUES
    (10, 1, 'Y', '0111111111', '111111111'),
    (11, 1, 'Y', '0122222222', '222222222'),
    (12, 1, 'N', '0144444444', '444444444'),
    (13, 2, 'Y', '0199999999', '999999999')
");
$pdo->exec("INSERT INTO ghl_contacts VALUES
    (1, '0133333333', '333333333')
");

$sql = guest_contact_duplicate_key_sql();
$is_dup = function ($new_key, $current_key) use ($pdo, $sql) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array($new_key, $current_key, $new_key, $current_key));
    return (int) $stmt->fetchColumn() === 1;
};

// Person currently keyed 111111111 tries to take 222222222 (another guest) -> blocked
$assertions['dup: collide with other booking guest -> true'] = $is_dup('222222222', '111111111') === true;
// Same key as current (re-saving own / formatting-only change) -> not a dup
$assertions['dup: same as current key -> false']             = $is_dup('111111111', '111111111') === false;
// Brand new unique key -> not a dup
$assertions['dup: unique key -> false']                      = $is_dup('555555555', '111111111') === false;
// Collide with a GHL lead -> blocked
$assertions['dup: collide with GHL lead -> true']            = $is_dup('333333333', '111111111') === true;
// Cancelled/inactive booking guest key must NOT count as a dup
$assertions['dup: cancelled-booking key ignored -> false']   = $is_dup('999999999', '111111111') === false;
// Status='N' guest row key must NOT count as a dup
$assertions['dup: inactive guest_list row ignored -> false'] = $is_dup('444444444', '111111111') === false;

// Report -------------------------------------------------------------------
$failed = 0;
foreach ($assertions as $label => $ok) {
    echo ($ok ? '  PASS  ' : '  FAIL  ') . $label . PHP_EOL;
    if (!$ok) {
        $failed++;
    }
}

echo PHP_EOL . ($failed === 0 ? "All assertions passed.\n" : "$failed assertion(s) failed.\n");
exit($failed === 0 ? 0 : 1);
