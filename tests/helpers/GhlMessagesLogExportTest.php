<?php
/**
 * Run with: php tests/helpers/GhlMessagesLogExportTest.php
 *
 * Locks the CSV shaping for the Message Log export. The controller streams the
 * export in chunks, so these pure helpers decide the header, the per-row cell
 * order, and the download filename. Rules:
 *   - the export leads with a Contact column (the chatroom/lead the row belongs
 *     to) so rows grouped by contact can be analysed one thread at a time;
 *     the remaining columns follow the on-screen order: Date/Time, Agent, From,
 *     To, Message,
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
    array('Contact', 'Date / Time', 'Direction', 'Agent', 'From', 'To', 'Message'),
    ghl_message_log_export_columns());

// Direction label: normalized to a readable word; unknown/empty -> blank.
assert_eq('direction label inbound',  'Inbound',  ghl_message_log_direction_label('inbound'));
assert_eq('direction label outbound', 'Outbound', ghl_message_log_direction_label('OUTBOUND'));
assert_eq('direction label trims',    'Inbound',  ghl_message_log_direction_label('  inbound '));
assert_eq('direction label empty',    '',         ghl_message_log_direction_label(''));
assert_eq('direction label null',     '',         ghl_message_log_direction_label(null));
assert_eq('direction label unknown',  'Note',     ghl_message_log_direction_label('note'));

// Full outbound row -> cells in table order, led by the contact/chatroom.
$full = array(
    'contact_name'      => 'Lim Wei Jian',
    'message_timestamp' => '2026-06-20 11:19:59',
    'direction'         => 'outbound',
    'agent'             => 'Hani OP Team',
    'from_number'       => '+60 10-295 6786',
    'to_number'         => '+6591852988',
    'body'              => "Line one\nLine two, with comma",
);
assert_eq('full row cells',
    array('Lim Wei Jian', '2026-06-20 11:19:59', 'Outbound', 'Hani OP Team', '+60 10-295 6786', '+6591852988', "Line one\nLine two, with comma"),
    ghl_message_log_export_row($full));

// Inbound row: null contact/agent and null body -> empty strings, not null;
// direction still resolves to a readable label.
$inbound = array(
    'contact_name'      => null,
    'message_timestamp' => '2026-06-20 11:14:40',
    'direction'         => 'inbound',
    'agent'             => null,
    'from_number'       => '+60129871440',
    'to_number'         => '+60 10-295 6786',
    'body'              => null,
);
assert_eq('inbound row blanks nulls',
    array('', '2026-06-20 11:14:40', 'Inbound', '', '+60129871440', '+60 10-295 6786', ''),
    ghl_message_log_export_row($inbound));

// Missing keys entirely -> all blanks, never a warning.
assert_eq('missing keys -> blanks',
    array('', '', '', '', '', '', ''),
    ghl_message_log_export_row(array()));

assert_eq('filename encodes window',
    'ghl_message_log_2026-06-15_to_2026-06-22.csv',
    ghl_message_log_export_filename('2026-06-15', '2026-06-22'));

echo "\nAll assertions passed.\n";
