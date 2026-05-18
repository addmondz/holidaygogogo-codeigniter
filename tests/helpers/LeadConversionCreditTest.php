<?php
/**
 * Run with: php tests/helpers/LeadConversionCreditTest.php
 *
 * Verifies lead_conversion_credited_admin_id() — the pure rule that decides
 * which TC slot on a matched booking gets credit for a lead-to-booking
 * conversion. Bookings created before 2026-06-01 credit TC1 (SalesAgent);
 * on/after credit TC2 (SalesAgent2). Returns NULL when the relevant slot is
 * empty or the InsertDate is missing.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require_once __DIR__ . '/../../application/helpers/lead_conversion_credit_helper.php';

$assertions = [];

// Pre-cutoff: TC1 (SalesAgent) wins; TC2 ignored
$assertions['Pre-cutoff date: returns SalesAgent (TC1)'] =
    lead_conversion_credited_admin_id('2026-05-30 10:00:00', 7, 99) === 7;
$assertions['Pre-cutoff with TC2 null: returns SalesAgent'] =
    lead_conversion_credited_admin_id('2026-05-30 10:00:00', 7, null) === 7;
$assertions['Pre-cutoff with TC1 null: returns null (TC1 empty)'] =
    lead_conversion_credited_admin_id('2026-05-30 10:00:00', null, 99) === null;

// Boundary: last second of 2026-05-31 is still TC1
$assertions['Boundary 2026-05-31 23:59:59: still TC1'] =
    lead_conversion_credited_admin_id('2026-05-31 23:59:59', 7, 99) === 7;

// On/after cutoff: TC2 (SalesAgent2) wins; TC1 ignored
$assertions['On cutoff 2026-06-01 00:00:00: returns SalesAgent2 (TC2)'] =
    lead_conversion_credited_admin_id('2026-06-01 00:00:00', 7, 99) === 99;
$assertions['Post-cutoff 2026-06-15: returns SalesAgent2'] =
    lead_conversion_credited_admin_id('2026-06-15 12:30:00', 7, 99) === 99;
$assertions['Post-cutoff with TC2 null: returns null'] =
    lead_conversion_credited_admin_id('2026-06-15 12:30:00', 7, null) === null;
$assertions['Post-cutoff with TC2 empty string: returns null'] =
    lead_conversion_credited_admin_id('2026-06-15 12:30:00', 7, '') === null;
$assertions['Post-cutoff with TC2 zero: returns null'] =
    lead_conversion_credited_admin_id('2026-06-15 12:30:00', 7, 0) === null;
$assertions['Post-cutoff with TC1 set but TC2 null: returns null (credit no longer goes to TC1)'] =
    lead_conversion_credited_admin_id('2026-06-01 00:00:00', 7, null) === null;

// Date-only InsertDate (some inserts may store just YYYY-MM-DD)
$assertions['Pre-cutoff date-only: TC1'] =
    lead_conversion_credited_admin_id('2026-05-30', 7, 99) === 7;
$assertions['Cutoff date-only: TC2'] =
    lead_conversion_credited_admin_id('2026-06-01', 7, 99) === 99;

// Missing InsertDate => no credit derivable
$assertions['Null InsertDate: returns null'] =
    lead_conversion_credited_admin_id(null, 7, 99) === null;
$assertions['Empty InsertDate: returns null'] =
    lead_conversion_credited_admin_id('', 7, 99) === null;

// Numeric-string slots (DB may return strings) must cast cleanly
$assertions['Pre-cutoff string SalesAgent "7": returns 7'] =
    lead_conversion_credited_admin_id('2026-05-30 10:00:00', '7', '99') === 7;
$assertions['Post-cutoff string SalesAgent2 "99": returns 99'] =
    lead_conversion_credited_admin_id('2026-06-15 12:30:00', '7', '99') === 99;

// Custom cutoff override (so the helper stays usable if the date ever shifts)
$assertions['Custom cutoff 2027-01-01 places 2026-06-15 pre-cutoff'] =
    lead_conversion_credited_admin_id('2026-06-15 12:30:00', 7, 99, '2027-01-01') === 7;

$failed = 0;
foreach ($assertions as $label => $ok) {
    echo ($ok ? '  PASS  ' : '  FAIL  ') . $label . PHP_EOL;
    if (!$ok) {
        $failed++;
    }
}

echo PHP_EOL . ($failed === 0 ? "All assertions passed.\n" : "$failed assertion(s) failed.\n");
exit($failed === 0 ? 0 : 1);
