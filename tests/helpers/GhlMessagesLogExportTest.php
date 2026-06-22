<?php
/**
 * Run with: php tests/helpers/GhlMessagesLogExportTest.php
 *
 * Locks the CSV shaping for the Message Log export. The controller streams the
 * export in chunks, so these pure helpers decide the header, the per-row cell
 * order, and the download filename. Rules:
 *   - columns match the on-screen table order: Date/Time, Agent, From, To, Message,
 *   - a missing agent (inbound message) becomes '' not null, so Excel shows a
 *     blank cell rather than the word "null",
 *   - message bodies (which contain newlines/commas) pass through untouched;
 *     fputcsv handles quoting,
 *   - the filename encodes the exported date window.
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

require __DIR__ . '/../../application/helpers/ghl_messages_log_helper.php';

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

assert_eq('header columns',
    array('Date / Time', 'Agent', 'From', 'To', 'Message'),
    ghl_message_log_export_columns());

// Full outbound row -> cells in table order.
$full = array(
    'message_timestamp' => '2026-06-20 11:19:59',
    'agent'             => 'Hani OP Team',
    'from_number'       => '+60 10-295 6786',
    'to_number'         => '+6591852988',
    'body'              => "Line one\nLine two, with comma",
);
assert_eq('full row cells',
    array('2026-06-20 11:19:59', 'Hani OP Team', '+60 10-295 6786', '+6591852988', "Line one\nLine two, with comma"),
    ghl_message_log_export_row($full));

// Inbound row: null agent and null body -> empty strings, not null.
$inbound = array(
    'message_timestamp' => '2026-06-20 11:14:40',
    'agent'             => null,
    'from_number'       => '+60129871440',
    'to_number'         => '+60 10-295 6786',
    'body'              => null,
);
assert_eq('inbound row blanks nulls',
    array('2026-06-20 11:14:40', '', '+60129871440', '+60 10-295 6786', ''),
    ghl_message_log_export_row($inbound));

// Missing keys entirely -> all blanks, never a warning.
assert_eq('missing keys -> blanks',
    array('', '', '', '', ''),
    ghl_message_log_export_row(array()));

assert_eq('filename encodes window',
    'ghl_message_log_2026-06-15_to_2026-06-22.csv',
    ghl_message_log_export_filename('2026-06-15', '2026-06-22'));

echo "\nAll assertions passed.\n";
