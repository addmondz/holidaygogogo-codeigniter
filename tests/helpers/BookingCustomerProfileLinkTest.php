<?php
/**
 * Run with: php tests/helpers/BookingCustomerProfileLinkTest.php
 *
 * Verifies booking_customer_profile_link() — the Customer cell on the booking
 * listing that links each name to the customer dashboard
 * (Customer/Update?customer_id=<CustomerID>).
 *
 * Rules under test:
 *   - Links only when the viewer can access Customer AND the booking has a
 *     positive CustomerID (a linked customer master).
 *   - Falls back to the plain, HTML-escaped name (no link) otherwise.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require_once __DIR__ . '/../../application/helpers/booking_customer_link_helper.php';

$base = 'http://test/';
$assertions = [];

// Links when allowed + customer id present.
$out = booking_customer_profile_link('John Tan', 10232, true, $base);
$assertions['Links name when allowed'] =
    strpos($out, '<a ') === 0
    && strpos($out, 'href="http://test/Customer/Update?customer_id=10232"') !== false
    && strpos($out, '>John Tan</a>') !== false;

// String id is coerced to int.
$assertions['String id coerced'] =
    strpos(booking_customer_profile_link('X', '10232', true, $base), 'customer_id=10232"') !== false;

// No permission -> plain escaped name, no link.
$assertions['No permission -> plain name'] =
    booking_customer_profile_link('John Tan', 10232, false, $base) === 'John Tan';

// No / invalid customer id -> plain name, no link.
$assertions['Null id -> plain name'] =
    booking_customer_profile_link('John Tan', null, true, $base) === 'John Tan';
$assertions['Zero id -> plain name'] =
    booking_customer_profile_link('John Tan', 0, true, $base) === 'John Tan';

// Name is HTML-escaped in both link and plain branches (no markup injection).
$linked = booking_customer_profile_link('A & B <x>', 55, true, $base);
$assertions['Escapes name inside link'] =
    strpos($linked, 'A &amp; B &lt;x&gt;') !== false
    && strpos($linked, '<x>') === false;
$assertions['Escapes name in plain branch'] =
    booking_customer_profile_link('A & B <x>', null, true, $base) === 'A &amp; B &lt;x&gt;';

// Base URL without trailing slash still builds a clean path.
$assertions['Handles base url without trailing slash'] =
    strpos(booking_customer_profile_link('X', 7, true, 'http://test'), 'http://test/Customer/Update?customer_id=7') !== false;

$failed = 0;
foreach ($assertions as $label => $ok) {
    echo ($ok ? '  PASS  ' : '  FAIL  ') . $label . PHP_EOL;
    if (!$ok) {
        $failed++;
    }
}

echo PHP_EOL . ($failed === 0 ? "All assertions passed.\n" : "$failed assertion(s) failed.\n");
exit($failed === 0 ? 0 : 1);
