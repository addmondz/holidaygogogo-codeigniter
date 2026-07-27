<?php
/**
 * Run with: php tests/helpers/ChatHistoryHelperTest.php
 *
 * Locks the pure chat_history_helper functions that power the "Chat History"
 * action on the Guest / Customer / GHL Leads listing:
 *   - chat_history_validate_upload : .txt-only, non-empty, <=5MB gate
 *   - chat_history_stored_name     : random, traversal-proof, always .txt
 *   - chat_history_is_outbound     : our (company) sender floats right
 *   - chat_history_parse           : WhatsApp export -> ordered messages, folding
 *                                    continuation lines, flagging system notices
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}
require __DIR__ . '/../../application/helpers/chat_history_helper.php';

function assert_eq($label, $expected, $actual) {
    if ($expected === $actual) {
        echo "  PASS  {$label} = " . var_export($actual, true) . "\n";
    } else {
        echo "  FAIL  {$label}: expected " . var_export($expected, true)
           . ", got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

function assert_true($label, $actual) { assert_eq($label, true, (bool) $actual); }
function assert_false($label, $actual) { assert_eq($label, false, (bool) $actual); }

// ---- validate_upload -------------------------------------------------------
assert_true('valid .txt accepted',        chat_history_validate_upload('WhatsApp Chat with +60.txt', 1234)['ok']);
assert_false('non-txt rejected',          chat_history_validate_upload('chat.pdf', 1234)['ok']);
assert_false('empty file rejected',       chat_history_validate_upload('chat.txt', 0)['ok']);
assert_false('blank name rejected',       chat_history_validate_upload('   ', 10)['ok']);
assert_false('oversize rejected',         chat_history_validate_upload('chat.txt', 6 * 1024 * 1024)['ok']);
assert_true('uppercase ext accepted',     chat_history_validate_upload('CHAT.TXT', 10)['ok']);

// ---- stored_name -----------------------------------------------------------
$stored = chat_history_stored_name('../../etc/passwd');
assert_true('stored ends .txt',          substr($stored, -4) === '.txt');
assert_false('stored has no slash',      strpos($stored, '/') !== false);
assert_false('stored has no dotdot',     strpos($stored, '..') !== false);
assert_true('two calls differ',          chat_history_stored_name('a.txt') !== chat_history_stored_name('a.txt'));

// ---- is_outbound -----------------------------------------------------------
assert_true('company sender is outbound',   chat_history_is_outbound('Holidaygogogo Tours Simon'));
assert_true('spaced/cased brand matches',   chat_history_is_outbound('holiday gogogo'));
assert_false('customer number not outbound', chat_history_is_outbound('+60 12-291 1210'));

// ---- parse -----------------------------------------------------------------
$sample = implode("\n", array(
    '7/23/26, 1:35 PM - Messages and calls are end-to-end encrypted. Only people in this chat can read them.',
    '7/23/26, 1:35 PM - ',
    '7/23/26, 1:35 PM - +60 12-291 1210: Hi,请问有什么团包括机票，价钱不上Rm2000一个人',
    '7/23/26, 1:36 PM - Holidaygogogo Tours Simon: 您好，可以麻烦您whatsapp到 +60102956786吗？',
    'This is a wrapped second line of Simon\'s message.',
    '7/23/26, 1:40 PM - +60 12-291 1210: 好的谢谢',
));
$msgs = chat_history_parse($sample);

// Blank "- " line dropped; E2E banner + 3 chat messages remain = 4 entries.
assert_eq('message count',        4, count($msgs));

// [0] system banner
assert_true('msg0 is system',     $msgs[0]['system']);
assert_eq('msg0 sender empty',    '', $msgs[0]['sender']);
assert_false('msg0 not outbound', $msgs[0]['outbound']);

// [1] inbound customer message
assert_false('msg1 not system',   $msgs[1]['system']);
assert_eq('msg1 sender',          '+60 12-291 1210', $msgs[1]['sender']);
assert_false('msg1 not outbound', $msgs[1]['outbound']);
assert_true('msg1 body preserved', strpos($msgs[1]['body'], '请问有什么团') !== false);

// [2] outbound company message with a folded continuation line
assert_true('msg2 outbound',      $msgs[2]['outbound']);
assert_eq('msg2 sender',          'Holidaygogogo Tours Simon', $msgs[2]['sender']);
assert_true('msg2 folds wrap',    strpos($msgs[2]['body'], 'wrapped second line') !== false);
assert_eq('msg2 ts',              '7/23/26, 1:36 PM', $msgs[2]['ts']);

// [3] final inbound message
assert_eq('msg3 body',            '好的谢谢', $msgs[3]['body']);

// Empty input is safe.
assert_eq('empty text -> []',     array(), chat_history_parse(''));

echo "\nAll ChatHistoryHelper assertions passed.\n";
