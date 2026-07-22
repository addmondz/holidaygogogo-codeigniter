<?php
/**
 * Run with: php tests/helpers/GuestRemarkValidateTest.php
 *
 * Covers the pure validators behind the Guest List remarks feature
 * (Guests::Add_Remark) from application/helpers/guest_contact_helper.php:
 *
 *   1. guest_remark_validate_date()        — Campaign Date (required) / Follow Date (optional)
 *   2. guest_remark_validate_destination() — Destination select (optional category id)
 *   3. guest_remark_validate_remark()      — Remark text (required, length-capped)
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

// 1) validate_date (Campaign Date = required, Follow Date = optional) --------
$assertions['date: required empty rejected']   = guest_remark_validate_date('', true, 'Campaign date')['ok'] === false;
$assertions['date: required whitespace reject'] = guest_remark_validate_date("  \t", true, 'Campaign date')['ok'] === false;
$assertions['date: ISO accepted']              = guest_remark_validate_date('2026-07-13')['ok'] === true;
$assertions['date: ISO normalized']            = guest_remark_validate_date('2026-07-13')['value'] === '2026-07-13';
$assertions['date: trims surrounding spaces']  = guest_remark_validate_date('  2026-07-13 ')['value'] === '2026-07-13';
$assertions['date: garbage rejected']          = guest_remark_validate_date('not-a-date')['ok'] === false;
$assertions['date: optional empty allowed']    = guest_remark_validate_date('', false, 'Follow date')['ok'] === true;
$assertions['date: optional empty value blank'] = guest_remark_validate_date('', false, 'Follow date')['value'] === '';
$assertions['date: optional garbage rejected'] = guest_remark_validate_date('nope', false, 'Follow date')['ok'] === false;

// 2) validate_destination (optional category id) ----------------------------
$assertions['dest: empty allowed → 0']         = guest_remark_validate_destination('') === array('ok' => true, 'error' => '', 'value' => 0);
$assertions['dest: numeric id accepted']       = guest_remark_validate_destination('7')['value'] === 7;
$assertions['dest: zero rejected']             = guest_remark_validate_destination('0')['ok'] === false;
$assertions['dest: non-numeric rejected']      = guest_remark_validate_destination('bali')['ok'] === false;

// 3) validate_remark -------------------------------------------------------
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
