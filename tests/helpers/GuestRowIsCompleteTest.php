<?php
/**
 * Run with: php tests/helpers/GuestRowIsCompleteTest.php
 *
 * Verifies guest_row_is_complete() — the per-guest predicate behind
 * Guest_List_Model::Are_All_Guests_Complete() that decides whether a booking
 * can advance out of "Guest List Pending".
 *
 * Key rule under test: Mobile & Email (contact) are OPTIONAL for CHILD guests
 * but still REQUIRED for ADULT/INFANT guests.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require_once __DIR__ . '/../../application/helpers/guest_complete_helper.php';

function make_guest($overrides = []) {
    $base = [
        'Name'                 => 'John',
        'LastName'             => 'Doe',
        'Gender'               => 'MALE',
        'DateOfBirth'          => '2000-01-01',
        'Email'                => 'john@example.com',
        'Mobile'               => '123456789',
        'CountryCodeID'        => 60,
        'Type'                 => 'ADULT',
        'Nationality'          => 'foreigner',
        'IdentificationNumber' => '',
    ];
    return (object) array_merge($base, $overrides);
}

$assertions = [];

// Fully-filled adult -> complete
$assertions['Adult with all fields -> complete'] =
    guest_row_is_complete(make_guest()) === true;

// Adult missing contact -> incomplete (contact still required)
$assertions['Adult missing email -> incomplete'] =
    guest_row_is_complete(make_guest(['Email' => ''])) === false;
$assertions['Adult missing mobile -> incomplete'] =
    guest_row_is_complete(make_guest(['Mobile' => ''])) === false;
$assertions['Adult missing country code -> incomplete'] =
    guest_row_is_complete(make_guest(['CountryCodeID' => null])) === false;

// CHILD with no contact at all -> STILL complete (the change under test)
$assertions['Child missing email+mobile+country -> complete'] =
    guest_row_is_complete(make_guest([
        'Type' => 'CHILD', 'Email' => '', 'Mobile' => '', 'CountryCodeID' => null,
    ])) === true;

// Child still needs the basic identity fields
$assertions['Child missing name -> incomplete'] =
    guest_row_is_complete(make_guest([
        'Type' => 'CHILD', 'Name' => '', 'Email' => '', 'Mobile' => '', 'CountryCodeID' => null,
    ])) === false;
$assertions['Child missing DOB -> incomplete'] =
    guest_row_is_complete(make_guest([
        'Type' => 'CHILD', 'DateOfBirth' => '', 'Email' => '', 'Mobile' => '',
    ])) === false;

// INFANT is NOT exempt — contact still required (only CHILD is exempt)
$assertions['Infant missing contact -> incomplete'] =
    guest_row_is_complete(make_guest([
        'Type' => 'INFANT', 'Email' => '', 'Mobile' => '', 'CountryCodeID' => null,
    ])) === false;

// Malaysian needs an IC regardless of type
$assertions['Malaysian adult without IC -> incomplete'] =
    guest_row_is_complete(make_guest(['Nationality' => 'malaysian', 'IdentificationNumber' => ''])) === false;
$assertions['Malaysian adult with IC -> complete'] =
    guest_row_is_complete(make_guest(['Nationality' => 'malaysian', 'IdentificationNumber' => '990101011234'])) === true;
$assertions['Malaysian child without contact but with IC -> complete'] =
    guest_row_is_complete(make_guest([
        'Type' => 'CHILD', 'Nationality' => 'malaysian', 'IdentificationNumber' => '150101011234',
        'Email' => '', 'Mobile' => '', 'CountryCodeID' => null,
    ])) === true;

// Missing basic fields for adult
$assertions['Adult missing gender -> incomplete'] =
    guest_row_is_complete(make_guest(['Gender' => ''])) === false;

$failed = 0;
foreach ($assertions as $label => $ok) {
    echo ($ok ? '  PASS  ' : '  FAIL  ') . $label . PHP_EOL;
    if (!$ok) {
        $failed++;
    }
}

echo PHP_EOL . ($failed === 0 ? "All assertions passed.\n" : "$failed assertion(s) failed.\n");
exit($failed === 0 ? 0 : 1);
