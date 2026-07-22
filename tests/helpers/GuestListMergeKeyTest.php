<?php
/**
 * Run with: php tests/helpers/GuestListMergeKeyTest.php
 *
 * Covers guest_list_merge_key() — the PHP mirror of
 * Guests_Model::Merge_Key_Expr(). Two contact rows collapse into one Guest List
 * row when they share the same Name + IdentificationNumber, even if their phone
 * numbers (and dedup_key) differ; rows without an IC keep their own dedup_key so
 * nothing merges unless we are sure it is the same person.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require_once __DIR__ . '/../../application/helpers/guest_contact_helper.php';

$assertions = array();

// IC present -> ic composite ----------------------------------------------
$assertions['ic builds composite']        = guest_list_merge_key('Ali Bin Abu', '900101-01-1234', '169546738') === 'ic:ali bin abu|900101011234';
$assertions['ic strips dashes/spaces']    = guest_list_merge_key('Ali', '900101 01 1234', 'x') === 'ic:ali|900101011234';
$assertions['name trimmed + lowered']     = guest_list_merge_key('  ALI Bin ABU ', '900101011234', 'x') === 'ic:ali bin abu|900101011234';
$assertions['passport letters uppercased'] = guest_list_merge_key('Jo', 'a1234567', 'k') === 'ic:jo|A1234567';

// same person, different phone -> SAME merge key (this is the whole point) --
$k1 = guest_list_merge_key('Siti', '880202021234', '111111111');
$k2 = guest_list_merge_key('Siti', '880202-02-1234', '222222222');
$assertions['same name+ic, diff phone merge'] = $k1 === $k2;

// different IC -> different key -------------------------------------------
$assertions['diff ic -> diff key']        = guest_list_merge_key('Siti', '880202021234', 'a') !== guest_list_merge_key('Siti', '990303031234', 'a');

// no IC -> falls back to dedup_key ----------------------------------------
$assertions['blank ic -> dedup key']      = guest_list_merge_key('Ali', '', '169546738') === '169546738';
$assertions['whitespace ic -> dedup key'] = guest_list_merge_key('Ali', '   ', '169546738') === '169546738';
$assertions['punct-only ic -> dedup key'] = guest_list_merge_key('Ali', '--', '169546738') === '169546738';
$assertions['null ic -> dedup key']       = guest_list_merge_key('Ali', null, '169546738') === '169546738';

// no IC, two people same phone-key share dedup (unchanged today) -----------
$assertions['no ic keeps dedup grouping'] = guest_list_merge_key('Ali', '', 'abc') === guest_list_merge_key('Bob', '', 'abc');

// ---- report -------------------------------------------------------------
$failed = 0;
foreach ($assertions as $label => $ok) {
    if (!$ok) {
        $failed++;
        echo "FAIL: {$label}\n";
    }
}
if ($failed === 0) {
    echo 'OK: all ' . count($assertions) . " assertions passed\n";
    exit(0);
}
echo "{$failed} of " . count($assertions) . " assertions FAILED\n";
exit(1);
