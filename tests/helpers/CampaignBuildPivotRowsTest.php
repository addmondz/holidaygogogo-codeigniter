<?php
/**
 * Run with: php tests/helpers/CampaignBuildPivotRowsTest.php
 *
 * Verifies the pure dedup/normalisation step used by Campaign_Model when
 * persisting campaign_guests pivot rows. The DB write itself is exercised
 * manually end-to-end; this test pins the in-memory shaping logic.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

if (!class_exists('CI_Model')) {
    class CI_Model {}
}

require_once __DIR__ . '/../../application/models/Campaign_Model.php';

$assertions = [];

// -- dedup + snapshot fields preserved --------------------------------------
$out = Campaign_Model::Build_Pivot_Rows(
    7,
    [
        ['DedupKey' => '60123456789', 'GuestName' => 'Alice',   'ContactNum' => '0123456789', 'Email' => 'a@x.com', 'GuestType' => 'Booking Guest'],
        ['DedupKey' => 'ghl:42',       'GuestName' => 'Bob',     'ContactNum' => '',            'Email' => 'b@y.com', 'GuestType' => 'GHL'],
    ],
    99,
    '2026-05-15 09:00:00'
);
$assertions['two rows produced']        = count($out) === 2;
$assertions['campaign id propagated']   = $out[0]['CampaignID'] === 7 && $out[1]['CampaignID'] === 7;
$assertions['admin id propagated']      = $out[0]['InsertBy']   === 99;
$assertions['insert date propagated']   = $out[0]['InsertDate'] === '2026-05-15 09:00:00';
$assertions['snapshot name preserved']  = $out[0]['GuestName']  === 'Alice';
$assertions['snapshot type preserved']  = $out[1]['GuestType']  === 'GHL';

// -- duplicate dedup_key is dropped -----------------------------------------
$dup = Campaign_Model::Build_Pivot_Rows(
    1,
    [
        ['DedupKey' => 'abc', 'GuestName' => 'First',  'GuestType' => 'Booking Guest'],
        ['DedupKey' => 'abc', 'GuestName' => 'Second', 'GuestType' => 'Booking Guest'],
    ],
    1,
    'now'
);
$assertions['duplicate dedup key dropped']    = count($dup) === 1;
$assertions['first occurrence kept']          = $dup[0]['GuestName'] === 'First';

// -- empty dedup keys skipped -----------------------------------------------
$blank = Campaign_Model::Build_Pivot_Rows(
    1,
    [
        ['DedupKey' => '',  'GuestName' => 'NoKey'],
        ['DedupKey' => '   ',  'GuestName' => 'StillNoKey'],
        ['DedupKey' => 'k', 'GuestName' => 'Keep'],
    ],
    1,
    'now'
);
$assertions['blank dedup keys skipped'] = count($blank) === 1 && $blank[0]['GuestName'] === 'Keep';

// -- invalid GuestType nulled out -------------------------------------------
$bad = Campaign_Model::Build_Pivot_Rows(
    1,
    [['DedupKey' => 'k', 'GuestName' => 'X', 'GuestType' => 'Hacker']],
    1,
    'now'
);
$assertions['invalid guest type nulled'] = $bad[0]['GuestType'] === null;

// -- dedup key falls back to array key when not supplied as a field ---------
$keyed = Campaign_Model::Build_Pivot_Rows(
    1,
    ['fallback-key' => ['GuestName' => 'Yo']],
    1,
    'now'
);
$assertions['fallback to array key'] = count($keyed) === 1 && $keyed[0]['DedupKey'] === 'fallback-key';

// -- empty / non-array input ------------------------------------------------
$assertions['empty array returns empty'] = Campaign_Model::Build_Pivot_Rows(1, [], 1, 'now') === [];
$assertions['null returns empty']        = Campaign_Model::Build_Pivot_Rows(1, null, 1, 'now') === [];

$failed = 0;
foreach ($assertions as $label => $ok) {
    echo ($ok ? '  PASS  ' : '  FAIL  ') . $label . PHP_EOL;
    if (!$ok) { $failed++; }
}

echo PHP_EOL . ($failed === 0 ? "All assertions passed.\n" : "$failed assertion(s) failed.\n");
exit($failed === 0 ? 0 : 1);
