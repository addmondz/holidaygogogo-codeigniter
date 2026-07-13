<?php
/**
 * Run with: php tests/helpers/GuestRemarkValidateTest.php
 *
 * Covers the pure validators behind the Guest List remarks feature
 * (Guests::Add_Remark) from application/helpers/guest_contact_helper.php:
 *
 *   1. guest_remark_validate_datetime() — RemarkAt (required, normalized to DATETIME)
 *   2. guest_remark_validate_remark()   — Remark text (required, length-capped)
 *
 * The DB-side insert/read/soft-delete (guest_remarks) are covered by manual
 * verification, mirroring GuestFieldUpdateTest (which unit-tests only the
 * portable, DB-free pieces).
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require_once __DIR__ . '/../../application/helpers/guest_contact_helper.php';

$assertions = array();

// 1) validate_datetime -----------------------------------------------------
$assertions['dt: empty rejected']              = guest_remark_validate_datetime('')['ok'] === false;
$assertions['dt: whitespace-only rejected']    = guest_remark_validate_datetime("  \t")['ok'] === false;
$assertions['dt: datetime-local accepted']     = guest_remark_validate_datetime('2026-07-13T15:30')['ok'] === true;
$assertions['dt: datetime-local normalized']   = guest_remark_validate_datetime('2026-07-13T15:30')['value'] === '2026-07-13 15:30:00';
$assertions['dt: space form accepted']         = guest_remark_validate_datetime('2026-07-13 09:05:00')['value'] === '2026-07-13 09:05:00';
$assertions['dt: trims surrounding spaces']    = guest_remark_validate_datetime('  2026-07-13T15:30 ')['value'] === '2026-07-13 15:30:00';
$assertions['dt: garbage rejected']            = guest_remark_validate_datetime('not-a-date')['ok'] === false;

// 2) validate_remark -------------------------------------------------------
$assertions['remark: empty rejected']          = guest_remark_validate_remark('')['ok'] === false;
$assertions['remark: whitespace-only reject']  = guest_remark_validate_remark("   \n")['ok'] === false;
$assertions['remark: trims spaces']            = guest_remark_validate_remark('  Called guest  ')['value'] === 'Called guest';
$assertions['remark: unicode allowed']         = guest_remark_validate_remark('已联络客户，下周决定')['ok'] === true;
$assertions['remark: 1000 chars ok']           = guest_remark_validate_remark(str_repeat('a', 1000))['ok'] === true;
$assertions['remark: 1001 chars rejected']     = guest_remark_validate_remark(str_repeat('a', 1001))['ok'] === false;

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
