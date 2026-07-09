<?php
/**
 * Run with: php tests/helpers/BookingPicDisplayTest.php
 *
 * Verifies booking_pic_display() — the pure function that decides which
 * agent(s) print on the "Booking PIC" line of the BC / Travel Voucher.
 *
 * Rules:
 *  - Both tick flags unset (NULL) => legacy fallback: show Sales Agent 1 only,
 *    with the label driven by the 2026-06-01 date cutoff
 *    ("Booking PIC" on/after, "Sales Agent" before).
 *  - Either flag set => manual mode: label is always "Booking PIC" and every
 *    ticked agent (with a name) prints. Both can print together.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require_once __DIR__ . '/../../application/helpers/booking_pic_pdf_helper.php';

$failed = 0;
function check($label, $expected, $actual) {
    global $failed;
    if ($expected === $actual) {
        echo "  PASS  {$label}\n";
    } else {
        echo "  FAIL  {$label}:\n        expected " . var_export($expected, true)
           . "\n        got      " . var_export($actual, true) . "\n";
        $failed = 1;
    }
}

$agents = array(
    'sales_agent_name'     => 'CHEN',
    'sales_agent_mobile'   => '+60123',
    'sales_agent_2_name'   => 'HC CS',
    'sales_agent_2_mobile' => '+60456',
);

// ---- Legacy fallback: both flags NULL ----------------------------------
$r = booking_pic_display(array_merge($agents, array(
    'sales_agent_is_pic'   => null,
    'sales_agent_2_is_pic' => null,
    'insert_date'          => '2026-06-01',
)));
check('Legacy on/after cutoff labels "Booking PIC"', 'Booking PIC', $r['label']);
check('Legacy shows only Sales Agent 1',
    array(array('name' => 'CHEN', 'mobile' => '+60123')), $r['entries']);

$r = booking_pic_display(array_merge($agents, array(
    'sales_agent_is_pic'   => null,
    'sales_agent_2_is_pic' => null,
    'insert_date'          => '2026-05-31',
)));
check('Legacy before cutoff labels "Sales Agent"', 'Sales Agent', $r['label']);
check('Legacy before cutoff still only Agent 1',
    array(array('name' => 'CHEN', 'mobile' => '+60123')), $r['entries']);

// ---- Manual mode: default (Agent 1 ticked only) ------------------------
$r = booking_pic_display(array_merge($agents, array(
    'sales_agent_is_pic'   => 1,
    'sales_agent_2_is_pic' => 0,
    'insert_date'          => '2026-05-31',
)));
check('Manual mode always labels "Booking PIC"', 'Booking PIC', $r['label']);
check('Only Agent 1 ticked => Agent 1 only',
    array(array('name' => 'CHEN', 'mobile' => '+60123')), $r['entries']);

// ---- Manual mode: both ticked -> both print ----------------------------
$r = booking_pic_display(array_merge($agents, array(
    'sales_agent_is_pic'   => 1,
    'sales_agent_2_is_pic' => 1,
    'insert_date'          => '2026-06-10',
)));
check('Both ticked => both agents print', array(
    array('name' => 'CHEN', 'mobile' => '+60123'),
    array('name' => 'HC CS', 'mobile' => '+60456'),
), $r['entries']);

// ---- Manual mode: only Agent 2 ticked ----------------------------------
$r = booking_pic_display(array_merge($agents, array(
    'sales_agent_is_pic'   => 0,
    'sales_agent_2_is_pic' => 1,
    'insert_date'          => '2026-06-10',
)));
check('Only Agent 2 ticked => Agent 2 only',
    array(array('name' => 'HC CS', 'mobile' => '+60456')), $r['entries']);

// ---- Manual mode: ticked agent with no name is skipped -----------------
$r = booking_pic_display(array(
    'sales_agent_is_pic'   => 1,
    'sales_agent_2_is_pic' => 1,
    'sales_agent_name'     => 'CHEN',
    'sales_agent_mobile'   => '+60123',
    'sales_agent_2_name'   => '',
    'sales_agent_2_mobile' => '',
    'insert_date'          => '2026-06-10',
));
check('Ticked Agent 2 with empty name is skipped',
    array(array('name' => 'CHEN', 'mobile' => '+60123')), $r['entries']);

// ---- Manual mode: both unticked => empty (deliberate) ------------------
$r = booking_pic_display(array_merge($agents, array(
    'sales_agent_is_pic'   => 0,
    'sales_agent_2_is_pic' => 0,
    'insert_date'          => '2026-06-10',
)));
check('Both unticked => no entries', array(), $r['entries']);
check('Both unticked => text is dash', '-', $r['text']);

// ---- String flags from POST ('1' / '0') behave like ints ---------------
$r = booking_pic_display(array_merge($agents, array(
    'sales_agent_is_pic'   => '1',
    'sales_agent_2_is_pic' => '0',
    'insert_date'          => '2026-06-10',
)));
check('String "1"/"0" flags => manual mode Agent 1 only',
    array(array('name' => 'CHEN', 'mobile' => '+60123')), $r['entries']);

// ---- text convenience string joins with <br> ---------------------------
$r = booking_pic_display(array_merge($agents, array(
    'sales_agent_is_pic'   => 1,
    'sales_agent_2_is_pic' => 1,
    'insert_date'          => '2026-06-10',
)));
check('text joins both entries with <br>',
    'CHEN (+60123)<br>HC CS (+60456)', $r['text']);

if ($failed) { exit(1); }
echo "\nAll assertions passed.\n";
