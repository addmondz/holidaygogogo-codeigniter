<?php
/**
 * Run with: php tests/helpers/GhlMessageLogViewTest.php
 *
 * Covers the pure logic behind the "View message log" feature (the chat modal
 * opened from the Guest List / Booking listing WhatsApp cells), from
 * application/helpers/ghl_message_log_helper.php:
 *
 *   1. ghl_message_log_normalize_number() — digits-only phone identity
 *   2. ghl_message_log_phone_candidates() — index-friendly IN match forms
 *   3. ghl_message_log_match_phones()     — which page phones have a stored log
 *   4. ghl_message_log_shape_message()    — DB row -> chat bubble
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require_once __DIR__ . '/../../application/helpers/ghl_message_log_helper.php';

$assertions = array();

// 1) normalize_number ------------------------------------------------------
$assertions['normalize "+60 12-345 6789" => 60123456789'] = ghl_message_log_normalize_number('+60 12-345 6789') === '60123456789';
$assertions['normalize "" => ""']                          = ghl_message_log_normalize_number('') === '';
$assertions['normalize letters => ""']                     = ghl_message_log_normalize_number('n/a') === '';

// 2) phone_candidates ------------------------------------------------------
$assertions['candidates keeps digits + plus form'] = ghl_message_log_phone_candidates('60123456789') === array('60123456789', '+60123456789');
$assertions['candidates formatted input']          = ghl_message_log_phone_candidates('+60 12 345 6789') === array('60123456789', '+60123456789');
$assertions['candidates empty => []']              = ghl_message_log_phone_candidates('') === array();

// 3) match_phones ----------------------------------------------------------
$stored = array(
    array('from_number' => '+60123456789', 'to_number' => '+60111000111'), // us -> lead A
    array('from_number' => '60999888777',  'to_number' => '+60111000111'), // lead B -> us (no +)
);
$page = array('+60 12-345 6789', '60999888777', '60555000000');
$matched = ghl_message_log_match_phones($stored, $page);
$assertions['match: contact with a stored log is found']     = in_array('60123456789', $matched, true);
$assertions['match: found even when stored has no + prefix']  = in_array('60999888777', $matched, true);
$assertions['match: contact with no messages is excluded']    = !in_array('60555000000', $matched, true);
$assertions['match: returns only matched phones (count = 2)']  = count($matched) === 2;
$assertions['match: no rows => nothing matches']              = ghl_message_log_match_phones(array(), $page) === array();
$assertions['match: same phone not duplicated'] = ghl_message_log_match_phones(
    $stored,
    array('60123456789', '+60123456789')
) === array('60123456789');

// 4) shape_message ---------------------------------------------------------
$out = ghl_message_log_shape_message(array(
    'direction'    => 'outbound',
    'body'         => 'Hello, your booking is confirmed',
    'message_type' => 'TYPE_WHATSAPP',
    'agent_name'   => 'Samantha',
    'contact_name' => 'Andrew Ng',
    'date_added'   => '2026-07-07 14:03:00',
));
$assertions['shape: outbound => side out']         = $out['side'] === 'out';
$assertions['shape: outbound author = agent']      = $out['author'] === 'Samantha';
$assertions['shape: body passed through']          = $out['body'] === 'Hello, your booking is confirmed';
$assertions['shape: time formatted']               = $out['time'] === '7 Jul 2026, 2:03 PM';

$in = ghl_message_log_shape_message(array(
    'direction'    => 'inbound',
    'body'         => '',
    'message_type' => 'TYPE_CALL',
    'agent_name'   => '',
    'contact_name' => 'Andrew Ng',
    'date_added'   => '2026-07-07 14:05:00',
));
$assertions['shape: inbound => side in']           = $in['side'] === 'in';
$assertions['shape: inbound author = contact']     = $in['author'] === 'Andrew Ng';
$assertions['shape: empty body => type label']     = $in['body'] === '[TYPE_CALL]';

$fallback = ghl_message_log_shape_message(array('direction' => 'outbound'));
$assertions['shape: missing agent => "Agent"']     = $fallback['author'] === 'Agent';
$assertions['shape: missing body+type => "[no text]"'] = $fallback['body'] === '[no text]';
$assertions['shape: missing date => ""']           = $fallback['time'] === '';

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
