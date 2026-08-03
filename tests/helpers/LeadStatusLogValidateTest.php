<?php
/**
 * Run with: php tests/helpers/LeadStatusLogValidateTest.php
 *
 * Covers the pure validators behind the Manual Lead "Lead Status log" feature
 * (Manual_Leads::Add_Lead_Status_Log) from application/helpers/guest_contact_helper.php:
 *
 *   1. guest_remark_validate_date()      — reused for the entry Date (required)
 *   2. lead_status_log_validate_status() — the Lead Status value (required, <=50)
 *   3. lead_status_log_validate_note()   — the optional Note (<=1000)
 *
 * The DB-side insert/read/soft-delete (lead_status_log) are covered by manual
 * verification, mirroring GuestRemarkValidateTest.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require_once __DIR__ . '/../../application/helpers/guest_contact_helper.php';

$assertions = array();

// 1) date (reused remark validator; required for a log entry) ---------------
$assertions['date: required empty rejected'] = guest_remark_validate_date('', true, 'Date')['ok'] === false;
$assertions['date: ISO accepted']            = guest_remark_validate_date('2026-08-03')['ok'] === true;
$assertions['date: ISO normalized']          = guest_remark_validate_date('2026-08-03')['value'] === '2026-08-03';
$assertions['date: garbage rejected']        = guest_remark_validate_date('not-a-date')['ok'] === false;

// 2) validate_status (required, <=50) ---------------------------------------
$assertions['status: empty rejected']        = lead_status_log_validate_status('')['ok'] === false;
$assertions['status: whitespace rejected']   = lead_status_log_validate_status("  \t")['ok'] === false;
$assertions['status: trims spaces']          = lead_status_log_validate_status('  Contacted  ')['value'] === 'Contacted';
$assertions['status: unicode allowed']       = lead_status_log_validate_status('已联络')['ok'] === true;
$assertions['status: 50 chars ok']           = lead_status_log_validate_status(str_repeat('a', 50))['ok'] === true;
$assertions['status: 51 chars rejected']     = lead_status_log_validate_status(str_repeat('a', 51))['ok'] === false;

// 3) validate_note (optional, <=1000) ---------------------------------------
$assertions['note: empty allowed']           = lead_status_log_validate_note('')['ok'] === true;
$assertions['note: empty value blank']       = lead_status_log_validate_note('')['value'] === '';
$assertions['note: whitespace → blank ok']   = lead_status_log_validate_note("   \n")['value'] === '';
$assertions['note: trims spaces']            = lead_status_log_validate_note('  Sent quote  ')['value'] === 'Sent quote';
$assertions['note: 1000 chars ok']           = lead_status_log_validate_note(str_repeat('a', 1000))['ok'] === true;
$assertions['note: 1001 chars rejected']     = lead_status_log_validate_note(str_repeat('a', 1001))['ok'] === false;

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
