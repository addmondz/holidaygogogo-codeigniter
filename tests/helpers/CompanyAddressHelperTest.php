<?php
/**
 * Run with: php tests/helpers/CompanyAddressHelperTest.php
 *
 * Verifies pdf_company_address_for_date() picks the legacy vs new address
 * based on the 2026-06-01 cutoff and tolerates empty / formatted dates.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require_once __DIR__ . '/../../application/helpers/company_address_helper.php';

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label}\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

$legacy = LEGACY_COMPANY_ADDRESS;
$new    = NEW_COMPANY_ADDRESS;

// Boundary: 2026-06-01 is the first day with the new address (inclusive).
assert_eq('Day before cutoff returns legacy',         $legacy, pdf_company_address_for_date('2026-05-31'));
assert_eq('Cutoff day returns new (inclusive)',       $new,    pdf_company_address_for_date('2026-06-01'));
assert_eq('Day after cutoff returns new',             $new,    pdf_company_address_for_date('2026-06-02'));

// Datetime strings straddling midnight of the cutoff.
assert_eq('Datetime just before cutoff returns legacy', $legacy, pdf_company_address_for_date('2026-05-31 23:59:59'));
assert_eq('Datetime at cutoff returns new',             $new,    pdf_company_address_for_date('2026-06-01 00:00:01'));

// Long-ago and far-future bookings.
assert_eq('Year 2023 returns legacy', $legacy, pdf_company_address_for_date('2023-05-15'));
assert_eq('Year 2030 returns new',    $new,    pdf_company_address_for_date('2030-01-01'));

// Display-formatted dates (the controllers reformat InsertDate to "j M Y" before
// the company block; the helper still handles either format via strtotime).
assert_eq('Formatted pre-cutoff date returns legacy',  $legacy, pdf_company_address_for_date('31 MAY 2026'));
assert_eq('Formatted cutoff date returns new',         $new,    pdf_company_address_for_date('1 JUN 2026'));

// Defensive: empty/malformed dates fall back to the new address so brand-new
// records (e.g. before InsertDate is populated) don't accidentally print legacy.
assert_eq('Empty string returns new',  $new, pdf_company_address_for_date(''));
assert_eq('Null returns new',          $new, pdf_company_address_for_date(null));
assert_eq('Garbage string returns new', $new, pdf_company_address_for_date('not-a-date'));

// Sanity: the constants are non-empty distinct strings.
assert_eq('Constants are different', true, $legacy !== $new && $legacy !== '' && $new !== '');

echo "\nAll assertions passed.\n";
