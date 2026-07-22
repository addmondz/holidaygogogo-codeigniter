<?php
/**
 * Run with: php tests/helpers/GuestListBookingPicTest.php
 *
 * The Guest List (web header + Excel export) now shows the Booking PIC the same
 * way the BC / Travel Voucher do, via booking_pic_display(). This locks the two
 * strings the guest list builds from that helper:
 *   - web header  : PICText  = entries joined "Name (Mobile)" by <br>
 *   - Excel col I : name(s) only, joined by ", "  (mobiles are not shown there)
 * and confirms the legacy fallback keeps the old single-agent behaviour.
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

// Mirror exactly what the export controller builds for column I.
function export_pic_names($data) {
    $pic = booking_pic_display($data);
    return implode(', ', array_column($pic['entries'], 'name'));
}

$agents = array(
    'sales_agent_name'     => 'YONG',
    'sales_agent_mobile'   => '+60166136385',
    'sales_agent_2_name'   => 'HC CS',
    'sales_agent_2_mobile' => '+60456',
);

// ---- Legacy fallback (both ticks unset) — old single-agent behaviour --------
$legacy = array_merge($agents, array(
    'sales_agent_is_pic'   => null,
    'sales_agent_2_is_pic' => null,
    'insert_date'          => '2026-06-05',
));
$r = booking_pic_display($legacy);
check('Legacy web line labelled Booking PIC', 'Booking PIC', $r['label']);
check('Legacy web text = Agent 1 only', 'YONG (+60166136385)', $r['text']);
check('Legacy export col I = Agent 1 name', 'YONG', export_pic_names($legacy));

// ---- Manual: only Agent 2 ticked -> Agent 2 replaces Agent 1 ----------------
$agent2 = array_merge($agents, array(
    'sales_agent_is_pic'   => 0,
    'sales_agent_2_is_pic' => 1,
    'insert_date'          => '2026-06-10',
));
check('Agent-2-only web text', 'HC CS (+60456)', booking_pic_display($agent2)['text']);
check('Agent-2-only export col I', 'HC CS', export_pic_names($agent2));

// ---- Manual: both ticked -> both show on guest list -------------------------
$both = array_merge($agents, array(
    'sales_agent_is_pic'   => 1,
    'sales_agent_2_is_pic' => 1,
    'insert_date'          => '2026-06-10',
));
check('Both-ticked web text joins with <br>',
    'YONG (+60166136385)<br>HC CS (+60456)', booking_pic_display($both)['text']);
check('Both-ticked export col I joins names with comma',
    'YONG, HC CS', export_pic_names($both));

// ---- Manual: both unticked -> dash on web, empty in export ------------------
$none = array_merge($agents, array(
    'sales_agent_is_pic'   => 0,
    'sales_agent_2_is_pic' => 0,
    'insert_date'          => '2026-06-10',
));
check('Both-unticked web text = dash', '-', booking_pic_display($none)['text']);
check('Both-unticked export col I = empty', '', export_pic_names($none));

if ($failed) { exit(1); }
echo "\nAll assertions passed.\n";
